# NIU Shield — Core service execution plan (revised after the real ACF + portal recon)

*Updated 14 June 2026, after decoding a real ACF QR and characterising the DGI portal.*

## What the reconnaissance established

Source document: a real ACF for **EXPRESS UNION FINANCE SA**, NIU **M020100021296Z**, ref **620264471967**, régime RÉEL, centre DGE IFU3, issued 19/03/2026, valid 3 months (→ 19/06/2026).

Three distinct official sources exist — the product must use all three, not one:

1. **Document authenticity (the QR).** The ACF QR decodes to
   `https://fanr.harmony2.cm/authentification?doc=ACF&reference=620264471967`.
   This is the DGI **Harmony2** system confirming the *document itself* is genuine
   (not a forged PDF). It is a JavaScript SPA — the verdict loads dynamically, so
   reading it requires **browser rendering (Browsershot)**, not a plain HTTP GET.
2. **NIU status (live).** `…/Consultation/listecontribuable.aspx` — ASP.NET
   WebForms (`__VIEWSTATE`, `__doPostBack`). Search by NIU returns the status
   (Actif / Suspendu / Radié). Needs a ViewState-aware POST.
3. **Full taxpayer file (bulk, offline).** The portal publishes the **entire file
   as monthly Excel exports** — "FICHIER JANVIER 2026.xlsx", "…FEVRIER…", "…MARS…".
   Download the latest monthly file, ingest it once, then do **offline existence +
   attribute lookups** with zero per-NIU portal calls. This is the primary, cheap
   source; the live status check is the on-demand confirmation.

Plus one check that needs no network call:

4. **ACF validity (date).** Issue date + 3 months (CGI art. L 94); 1 month if a
   payment moratorium applies.

**What I could and couldn't do live from here:** I decoded the QR and confirmed
its target, and characterised both portals. I could **not** submit the NIU search
(ASP.NET postback), read the Harmony2 authenticity verdict (JS-rendered), or
download the monthly xlsx (postback) — all three need the real connector with
POST/Browsershot, which is Phase 1 below.

## The corrected mental model: 4 checks × 2 stages

The verification is **four checks**, combined into one **approval** (accept /
reject the vendor invoice) with the existing graded verdict + FCFA exposure:

| # | Check | Source | Answers |
|---|---|---|---|
| 1 | Document authenticity | QR → Harmony2 (Browsershot) | Is the ACF a genuine DGI document? |
| 2 | NIU status | live consultation (POST) | Actif / Suspendu / Radié |
| 3 | NIU existence + attributes | monthly xlsx (offline) | Is the NIU in the national file? régime, centre, activité |
| 4 | ACF validity | date math | Within the 3-month window? |

These run at **two moments in time**, because status can change between them:

- **Invoice recording** — the vendor deposits an invoice *individually*. Scan/upload
  the ACF (read its QR + reference), run all four checks for that one NIU, store the
  evidence, return an accept/reject. One at a time, because vendors come one by one.
- **Invoice settlement** — finance pays *many* invoices later, after the payment
  terms elapse. Input a **batch** of NIUs, re-run the checks across all of them,
  produce a ranked report. Re-checking is the whole point: an ACF valid at recording
  may have expired, or a NIU may have been suspended, by settlement.

The UI is one screen with a **stage toggle** (Enregistrement ↔ Règlement): single
scan-driven input on the left, batch input on the right.

## What carries over from the current build (unchanged)

`NiuNumber`, the graded `Verdict`, the `RiskEngine` + `ExposureCalculator` + rules,
the tenant data model, the evidence model, `third_party_documents` (already has the
ACF type + `expires_at`), the multi-tenant + auth foundation. 95 tests stay green.
The risk engine simply gains the four checks as inputs.

## Revised core build steps

### Phase 1 — The three connectors (critical path)

| Step | Deliverable | Verify | Needs |
|---|---|---|---|
| 1 | **Taxpayer-file ingestion**: download/import a monthly FICHIER xlsx into the central registry; offline lookup by NIU | import a real (or representative) xlsx; existence lookup test | one monthly xlsx (or I model the columns from a sample) |
| 2 | **Live status connector** (Saloon, ViewState POST) → status | MockClient tests over a captured search response | one captured NIU-search HTML |
| 3 | **Document authenticator** (Browsershot render of the Harmony2 URL) → authentic? + parsed ACF fields | render test over a captured authenticity page | one captured authenticity page |
| 4 | **ACF reader**: parse the ACF PDF/QR (reference, NIU, issue date) on upload — already proven locally (pyzbar + pdf text) | unit test on the sample ACF | — (done in spike) |
| 5 | Resilience: rate-limit the live calls, circuit breaker (`fiscalis_health`), monthly-file freshness banner | simulated-outage tests | — |

### Phase 2 — The four-check engine + approval

| Step | Deliverable | Verify |
|---|---|---|
| 6 | `VerificationCheck` abstraction; wrap the 4 checks; each yields a result + evidence pointer | per-check tests |
| 7 | Approval decision = combine the 4 + the existing rules → accept/reject + verdict + FCFA exposure | engine tests over all combinations |
| 8 | `VerificationStage` enum (Recording/Settlement); record stage + the 4 check results per verification | feature test |

### Phase 3 — Evidence vault

| Step | Deliverable | Verify |
|---|---|---|
| 9 | Capture + store: ACF PDF, Harmony2 render, live-status screenshot, monthly-file reference; hash-chained | files written + chain test |
| 10 | Per-verification diligence **PDF** (the four checks, articles, timestamp, hashes) | render + snapshot |

### Phase 4 — The two-stage UI

| Step | Deliverable | Verify |
|---|---|---|
| 11 | Recording screen: scan/upload ACF → QR + reference read → 4 checks → approval card | Livewire test |
| 12 | Settlement screen: batch NIU input → queued checks → ranked report + export | feature test |
| 13 | Stage toggle, third-party linkage, verification history (both stages side by side per vendor) | feature test |

### Phase 5 — Async, monitoring (next ring)

Queue the live calls; scheduled monthly-file refresh; re-verify strategic vendors;
ACF-expiry alerts; API; billing.

## What unblocks the critical path

To build Phase 1 against fixtures (no test ever hits the live portals), I need any
**one** of, ideally all three, saved into the repo:
1. one **monthly FICHIER xlsx** (or just its header row + a few rows),
2. one **NIU-search result** HTML from the consultation page,
3. one **Harmony2 authenticity** page (saved after it renders the result).

Until then I build Phases 2–4 against the existing fake source — all real,
shippable product work — and the ACF reader (Step 4) which is already proven.
