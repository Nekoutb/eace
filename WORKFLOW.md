# NIU Shield — Verification workflow (canonical) + toolchain

*The runtime workflow to confirm, with the exact tool used at each step. 14 June 2026.*

## The two stages

The same four checks run at two moments, because a vendor's tax standing can change
between when you book the invoice and when you pay it.

- **Stage A — Invoice recording.** A vendor deposits one invoice. Done individually,
  with the ACF physically present → scan its QR.
- **Stage B — Invoice settlement.** Finance pays many invoices later, after the
  payment terms. Done as a batch of NIUs → many checks at once.

One screen, a toggle: **Enregistrement** (scan, one at a time) ↔ **Règlement** (batch).

## Stage A — Invoice recording (runtime sequence)

| Step | What happens | Tool |
|---|---|---|
| A1 | Clerk opens *Enregistrement* and **scans the ACF QR** with the device camera (phone/webcam). Or uploads the ACF PDF. | `html5-qrcode` (browser JS, in a Livewire/Alpine component) · file upload via Filament |
| A2 | System reads the QR payload → the Harmony2 authenticity URL + the **ACF reference**; and extracts the **NIU, issue date, régime** from the document. | QR: `html5-qrcode` (camera) / `khanamiryan/qrcode-detector-decoder` + `spatie/pdf-to-image` (PDF). Text: `smalot/pdfparser`. NIU format: our `NiuNumber` |
| A3 | **Check 1 — Document authenticity.** Open the Harmony2 URL, render the JS page, read the "authentique" verdict + the fields it returns, capture a screenshot. | `spatie/browsershot` (headless Chromium / Puppeteer) |
| A4 | **Check 2 — NIU status.** Search the live consultation for the NIU → Actif / Suspendu / Radié; capture a screenshot. | `saloonphp/saloon` (ViewState-aware POST) + `symfony/dom-crawler`; `spatie/browsershot` for the evidence shot |
| A5 | **Check 3 — NIU existence.** Look the NIU up in the latest ingested monthly file → present? + régime / centre / activité. | offline lookup in the central registry, fed by `maatwebsite/excel` import of the monthly xlsx |
| A6 | **Check 4 — ACF validity.** Issue date + 3 months (1 month if moratorium) vs today → valid / expired. | `nesbot/carbon` (no network) |
| A7 | **Combine** the four checks → **approval is STRICT: all four must pass to accept; any red rejects** the invoice. The graded verdict + **FCFA exposure** are kept alongside for reporting/explanation. | `RiskEngine` + `ExposureCalculator` (already built) |
| A8 | Store everything as **hash-chained evidence** (ACF PDF, both screenshots, monthly-file reference, verdict) and generate a **diligence PDF**. Tag the verification `stage = recording`. | `spatie/laravel-medialibrary` + `hash('sha256')` + `barryvdh/laravel-dompdf` |

## Stage B — Invoice settlement (runtime sequence)

| Step | What happens | Tool |
|---|---|---|
| B1 | At payment time, finance opens *Règlement* and **inputs/imports a batch of NIUs** (or picks the invoices due). | Filament + `maatwebsite/excel` (CSV/XLSX import) |
| B2 | For each NIU, **re-run checks 2, 3, 4** (status, existence, ACF validity). Check 1 (authenticity) **reuses the ACF captured at recording IF it is still valid with at least the validity buffer (default 5 days) remaining**. If it is expired — or within the buffer — the system **blocks payment**: *« Paiement impossible : attestation de conformité fiscale expirée ou bientôt expirée »* and requests a fresh ACF. | same tools as A3–A6, run on a queue; buffer via `Carbon` |
| B3 | Each NIU gets an approval; results compiled into a **ranked report** (by exposure / rejected first), with changes-since-recording flagged (e.g. was Actif, now Suspendu). | `RiskEngine`; report export via `maatwebsite/excel` / `dompdf` |
| B4 | Evidence stored per NIU; `stage = settlement`. The vendor now has two verifications on file (recording + settlement). | medialibrary + hash chain |

Batch and live calls run **asynchronously on a queue** with a **global rate limiter** and a **circuit breaker** so we stay a polite client of the DGI portal and degrade to the last good snapshot during outages.

## Supporting / background jobs

| Job | What it does | Tool |
|---|---|---|
| Monthly file refresh | Download the newest "FICHIER <MOIS>.xlsx", import it, mark freshness | `spatie/browsershot` (postback download) + `maatwebsite/excel` + Laravel scheduler |
| Re-verification | Periodically re-check strategic vendors; alert on status change / ACF expiry | Laravel scheduler + queues; notifications |
| Resilience | Rate-limit live calls, open the breaker on repeated failure | Laravel rate limiter + the `fiscalis_health` table |

## Toolchain at a glance

- **Intake / scan:** `html5-qrcode` (camera), `smalot/pdfparser` + `khanamiryan/qrcode-detector-decoder` + `spatie/pdf-to-image` (PDF), `NiuNumber`.
- **Live data:** `spatie/browsershot` (Harmony2 render + screenshots, xlsx download), `saloonphp/saloon` + `symfony/dom-crawler` (consultation POST).
- **Offline data:** `maatwebsite/excel` (monthly file ingest + batch import/report).
- **Logic:** `RiskEngine` + `ExposureCalculator` + `Carbon` (built / pure PHP).
- **Evidence:** `spatie/laravel-medialibrary`, `hash('sha256')` chain, `barryvdh/laravel-dompdf`.
- **App / async / infra:** Filament v5, Livewire v4 + Alpine, Laravel queues (Redis prod, database dev) + scheduler, `stancl/tenancy`, PostgreSQL (prod) / SQLite (dev).
- **Quality:** Pest, Larastan (level 6), Pint. Live portals are only ever hit through recorded fixtures in tests.

New libraries to add (rest already installed): `spatie/browsershot` (+ `npm i puppeteer`), `smalot/pdfparser`, `khanamiryan/qrcode-detector-decoder`, `spatie/pdf-to-image` (needs Imagick + Ghostscript), and the `html5-qrcode` frontend asset.

## Confirmed decisions (14 June 2026)

1. **Approval is strict.** All four checks must pass to accept; any failing check
   rejects the invoice. The graded verdict + FCFA exposure remain for reporting.
2. **Settlement authenticity = reuse with a buffer.** Reuse the ACF captured at
   recording if it is still valid with **≥ 5 days** (configurable validity buffer)
   remaining; otherwise block payment with an "ACF expired / about to expire"
   message and request a fresh certificate.
3. **Intake at recording = both** camera scan of the physical card **and** PDF/image
   upload of the certificate.

## Data-model additions this implies

- **`acf_certificates`** (tenant table) — *every* certificate the platform processes
  is recorded and monitored for existence + validity over time: `reference`, `niu`,
  `third_party_id`, `issued_at`, `expires_at`, `authenticity_status` (Harmony2 result),
  `last_verified_at`, `status` (valid / expiring-soon / expired / not-authentic).
  A scheduled job re-checks these and raises pre-expiry alerts; settlement reads this
  table to decide reuse vs block. The validity buffer (default 5 days) is a setting.
- `verifications` gains `stage` (recording | settlement) and the per-check results.

## Known scope boundary

The four-check strict approval targets **Cameroonian-registered vendors** (those
with a NIU in the file). **Foreign suppliers** are out of this flow: CGI art. 143
exempts them from the registration/active-file conditions ("ces conditions ne sont
pas exigées"), and they instead follow the TSR 15 % + VAT self-assessment path
already handled by the graded RiskEngine (rule R11). A dedicated foreign-supplier
approval path is a later enhancement, not part of the confirmed four-check scope.

