# NIU Shield — Project tracker

The build runs as small **deploys** you approve one at a time.

## How each deploy works (the loop)

1. **Build** the deploy's scope.
2. **Verify** — Pest tests green, Larastan level 6 clean, Pint formatted.
3. **Serve** — I give you the preview link: **http://demo.localhost:9000/app** (and `…/admin`), login **admin / admin**.
4. **Track** — I set the deploy below to *Deployed — awaiting approval* with the link.
5. **You test & approve** — then I start the next deploy. I never roll several deploys together.

Status legend: ⬜ Pending · 🔨 In progress · 🚀 Deployed — awaiting approval · ✅ Approved · ⛔ Blocked (needs input)

## Deploy ladder

| # | Deploy | Scope (what you'll be able to test) | Needs from you | Status | Approved |
|---|--------|--------------------------------------|----------------|--------|----------|
| 0 | Foundation | Multi-tenant app, **admin/admin** login, styled UI, verify-a-NIU on demo data, all earlier fixes | — | ✅ Approved | ✅ |
| 1 | ACF intake + certificate monitor | Upload/scan an ACF → it reads reference, NIU, issue date → recorded in a **certificate monitor** with validity (valid / expiring / expired), 5-day buffer | — | ✅ Approved | ✅ |
| 2 | Four-check strict engine + stages | A verification runs the **4 checks**, applies **strict** accept/reject, records the `stage`; shows verdict + FCFA exposure | — | ✅ Approved | ✅ |
| 3 | Two-stage UI | The **Enregistrement ↔ Règlement** toggle: recording (upload ACF / NIU, one vendor) vs settlement (batch of NIUs); camera scan deferred | — | ✅ Approved | ✅ |
| 3.1 | Hotfix: upload-route tenancy | Uploading an ACF no longer bounces to login (Livewire upload route made session + tenant aware) | — | ✅ Approved | ✅ |
| 4 | Bilingual EN/FR | Every screen in English or French, browser-auto-detected with a header switcher (remembered); all new UI bilingual from here | — | ✅ Approved | ✅ |
| 5 | "Compliance Engine" + live toggle | Rename Attestations → **Compliance Engine**; a **live toggle** switches **Invoice recording** ⇄ **Invoice settlement** mid-task; checks run **inline** on the page and reveal a **results table** (one column per test) + a closing **comments/discrepancies** section — no separate screen. Redesigned UI: **liquid-glass, light-green** (WCAG-AA reviewed) | — | 🚀 Deployed — awaiting approval | ⬜ |
| 6 | Invoice conformity (conditions de forme et de fond) | At recording, submit the scanned invoice → **OCR auto-extracts** the art. 150 fields → forme (mentions present) + fond (HT×taux=TVA, HT+TVA=TTC, NIU match) checks, **blocking** (strict). OCR provider: **Anthropic Claude** (set `ANTHROPIC_API_KEY`) → Azure DI → fake | *(optional)* `ANTHROPIC_API_KEY` (or Azure) + 1 sample invoice to validate the live OCR mapping | 🚀 Deployed — awaiting approval | ⬜ |
| 6.1 | Recording flow + buyer Settings + upload fix | **Fixes the upload 500.** Recording drops payment-method + VAT-charged; scanning the invoice **auto-populates** the control fields + shows an extract panel. New **Settings** page = the organisation's own buyer identity (name + NIU); each invoice's **client** NIU + name are checked against it (art. 150 client mentions) | — | 🚀 Deployed — awaiting approval | ⬜ |
| 7 | Monthly file ingestion (NIU existence) | **Real data now live.** Downloaded the actual DGI **FICHIER MARS 2026** (39 MB) and ingested it → the NIU-existence check runs against the real published taxpayer file (présence ⇒ Actif + au fichier; absence ⇒ introuvable) with régime/centre. `taxpayer-file:fetch` re-pulls the latest published month | — (real file downloaded) | 🚀 Deployed — awaiting approval | ⬜ |
| 7.1 | Audit Logs + weekly extraction | **Audit Logs** section (read-only): per checked invoice, the immutable **Fiscalis extract** (vendor active) + SHA-256 evidence hash + the four checks + the **taxpayer-file extraction** it was checked against. A **Taxpayer file** sub-section logs every download (period, status, taxpayer count, hash, date/time). **Weekly** scheduled `taxpayer-file:fetch` (Mondays 03:00, ~52/yr) | — | 🚀 Deployed — awaiting approval | ⬜ |
| 6.2 | Settings: RCCM + billing address | Settings also hold the buyer's **RCCM** + **billing address**; each scanned invoice's client RCCM + address are checked against them (art. 150). New OCR field extraction (`clientRccm`, `clientAddress`) | — | 🚀 Deployed — awaiting approval | ⬜ |
| 8 | Live status connector | Real **NIU status** from the consultation portal (ViewState POST), tested on fixtures | **1 NIU-search result page (HTML)** | ⛔ Blocked | ⬜ |
| 9 | Authenticity connector | Real **document authenticity** via Browsershot rendering the Harmony2 page | **1 rendered Harmony2 page** + puppeteer | ⛔ Blocked | ⬜ |
| 10 | Evidence vault + diligence PDF | Stored artefacts (ACF, invoice, screenshots), **hash chain**, downloadable **diligence PDF** | — | ⬜ Pending | ⬜ |
| 11 | Monitoring + alerts + async | Scheduled re-checks, **pre-expiry ACF alerts**, batch on a queue, rate-limit/circuit-breaker | — | ⬜ Pending | ⬜ |
| 12 | Compliance-engine refinements (15 Jul 2026) | **Vendor name** everywhere: new column in the compliance register, on every settlement/recording result row, persisted on each verification. **Settlement report download** (CSV, one click, vendor + all checks + decision). **Absent ACF at settlement is neutral**: amber "Checks not performed — cannot conclude, certificate absent" instead of red "Failed / Do not pay". **Conformity matrix table removed** from results (checks still run and count). **Newest-validity-wins ACF**: a new certificate only replaces the registered one when its validity date is later — otherwise the upload is refused with an explanation | — | ✅ Approved · live on prod `e96f7d0` (15 Jul 2026) | ✅ |
| 13 | VAT checks gated by régime (21 Jul 2026) | **VAT controls apply only to Régime du Réel vendors.** For a vendor whose régime forbids VAT (IGS, Simplifié, non-profit, non-professional, hors-régime) the art. 150 VAT figure checks (mention/rate/calc) **no longer apply** — a no-VAT invoice passes cleanly with no exemption prompt. If such a vendor bills VAT anyway, it is rejected as **"VAT included in error"** — a régime/TVA-liability finding, **not** an art. 150 defect. Undetermined régime stays conservative (figures verified). `VendorRegime` now recognises the three non-business register categories. Gate: Pint clean, Larastan 0, Pest 512/512 | — | ⤳ Superseded by 14 (never deployed) | ✅ |
| 14 | Non-Réel = ignore ALL VAT + read invoice footers (21 Jul 2026) | **Simplified:** if the régime is not Réel, **all VAT controls are ignored entirely** — no reject-if-billed, no TTC/HT arithmetic; the invoice is judged only on its non-VAT merits (fixes a valid IGS flat-total invoice being wrongly rejected). **RSI** (incl. "R.S.I") now recognised as Régime Simplifié. **Invoice footer OCR:** the reader now scans the whole page incl. the footer, knows **"Contribuable N°" is the supplier's own NIU** (often footer-only) vs the "Doit:/Client" buyer, transcribes `raw_text`, and has a code fallback that recovers the supplier NIU from the footer (never reusing the client's) + mirrors a single no-VAT total into HT. Gate: Pint clean, Larastan 0, Pest 514/514 | — | ✅ Approved · live on prod `735beab` (21 Jul 2026) | ✅ |
| 15 | Optional user phone + order-independent address (23 Jul 2026) | **Phone number on users (optional):** new nullable `phone` on tenant users, captured in the admin console's *Add a user* and *Edit* forms, shown as a copyable column in the company Users table and in the operator's cross-workspace **All users** directory — a contact detail only, never used to sign in. **Company address matching is now order-independent:** the art. 150 client-address control compares WORD SETS, not word order, so a profile of "Rue Bertaut Bali" accepts an invoice reading "Bali Rue Bertaut" (also tolerant of case, punctuation, stray whitespace, extra detail such as a city, and accents — Béssengué = Bessengue). A genuinely different address still fails. Gate: Pint clean, Larastan 0, Pest 523/523 | — | ✅ Approved · live on prod `1b973d7` (23 Jul 2026) | ✅ |

## Confirmed decisions (14 June 2026)

- **Bilingual**: browser auto-detect on first visit, header switcher (FR ⇄ EN), remembered per user. All new UI bilingual.
- **Compliance Engine**: the section formerly "Attestations" becomes "Compliance Engine"; on entry the user picks the verification phase (recording / settlement).
- **Invoice conformity** at recording (CGI art. 150): **OCR auto-extracts** the invoice fields; checks split into *conditions de forme* (mandatory mentions present) and *conditions de fond* (HT×taux=montant TVA, HT+TVA=TTC, legal VAT rate or valid exemption, supplier NIU matches the verified one). Failures **block** the approval (strict), consistent with art. 150 / L 102.

## Notes

- "Deploy" = a local preview on port 9000 you can click through. A real cloud/staging deploy comes once the core is approved.
- Deploys 10, 11 are **unblocked** — buildable now. Deploy 6 ships with a built-in fake OCR reader so it is fully testable today; the real reader switches on automatically once `ANTHROPIC_API_KEY` (Claude, preferred) — or `AZURE_DI_ENDPOINT` + `AZURE_DI_KEY` (Azure) — is set in `.env`. Optional `ANTHROPIC_MODEL` (default `claude-sonnet-4-6`).
- Deploy 7 is **live on real data** (FICHIER MARS 2026, downloaded 14 June 2026). Deploys 8, 9 wire the remaining two real DGI sources (live NIU status, Harmony2 authenticity) and need one captured sample each so tests never hit the live portals.
- After ingesting the real file, the **demo NIUs change**: use a real NIU from the file (e.g. `P107000258207S`). The fake invoice OCR's demo supplier (`M058712345678X`) is absent from the real file → it now resolves *introuvable* (correct). Set a real `ANTHROPIC_API_KEY` and scan a real invoice to exercise the real OCR + real existence check together.
