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
| 4 | Bilingual EN/FR | Every screen in English or French, browser-auto-detected with a header switcher (remembered); all new UI bilingual from here | — | 🔨 In progress | ⬜ |
| 5 | "Compliance Engine" + phase gate | Rename Attestations → **Compliance Engine**; entering it prompts **Invoice recording** vs **Invoice settlement** first | — | ⬜ Pending | ⬜ |
| 6 | Invoice conformity (conditions de forme et de fond) | At recording, submit the scanned invoice → **OCR auto-extracts** the art. 150 fields → forme (mentions present) + fond (HT×taux=TVA, HT+TVA=TTC, NIU match) checks, **blocking** (strict) | — | ⬜ Pending | ⬜ |
| 7 | Monthly file ingestion (NIU existence) | Import a monthly **FICHIER xlsx** → real offline existence + régime/centre lookup | **1 monthly FICHIER xlsx** | ⛔ Blocked | ⬜ |
| 8 | Live status connector | Real **NIU status** from the consultation portal (ViewState POST), tested on fixtures | **1 NIU-search result page (HTML)** | ⛔ Blocked | ⬜ |
| 9 | Authenticity connector | Real **document authenticity** via Browsershot rendering the Harmony2 page | **1 rendered Harmony2 page** + puppeteer | ⛔ Blocked | ⬜ |
| 10 | Evidence vault + diligence PDF | Stored artefacts (ACF, invoice, screenshots), **hash chain**, downloadable **diligence PDF** | — | ⬜ Pending | ⬜ |
| 11 | Monitoring + alerts + async | Scheduled re-checks, **pre-expiry ACF alerts**, batch on a queue, rate-limit/circuit-breaker | — | ⬜ Pending | ⬜ |

## Confirmed decisions (14 June 2026)

- **Bilingual**: browser auto-detect on first visit, header switcher (FR ⇄ EN), remembered per user. All new UI bilingual.
- **Compliance Engine**: the section formerly "Attestations" becomes "Compliance Engine"; on entry the user picks the verification phase (recording / settlement).
- **Invoice conformity** at recording (CGI art. 150): **OCR auto-extracts** the invoice fields; checks split into *conditions de forme* (mandatory mentions present) and *conditions de fond* (HT×taux=montant TVA, HT+TVA=TTC, legal VAT rate or valid exemption, supplier NIU matches the verified one). Failures **block** the approval (strict), consistent with art. 150 / L 102.

## Notes

- "Deploy" = a local preview on port 9000 you can click through. A real cloud/staging deploy comes once the core is approved.
- Deploys 3.1, 4, 5, 6, 10, 11 are **unblocked** — buildable now.
- Deploys 7, 8, 9 wire the three real DGI sources and need one captured sample each (saved into the repo) so tests never hit the live portals.
