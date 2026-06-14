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
| 2 | Four-check strict engine + stages | A verification runs the **4 checks**, applies **strict** accept/reject, records the `stage`; shows verdict + FCFA exposure | — | 🔨 In progress | ⬜ |
| 3 | Two-stage UI | The **Enregistrement ↔ Règlement** toggle: recording (scan + upload, one vendor) vs settlement (batch of NIUs) | — | ⬜ Pending | ⬜ |
| 4 | Monthly file ingestion (check 3) | Import a monthly **FICHIER xlsx** → real offline existence + régime/centre lookup | **1 monthly FICHIER xlsx** | ⛔ Blocked | ⬜ |
| 5 | Live status connector (check 2) | Real **NIU status** from the consultation portal (ViewState POST), tested on fixtures | **1 NIU-search result page (HTML)** | ⛔ Blocked | ⬜ |
| 6 | Authenticity connector (check 1) | Real **document authenticity** via Browsershot rendering the Harmony2 page | **1 rendered Harmony2 page** + puppeteer | ⛔ Blocked | ⬜ |
| 7 | Evidence vault + diligence PDF | Stored artefacts (ACF, screenshots), **hash chain**, downloadable **diligence PDF** | — | ⬜ Pending | ⬜ |
| 8 | Monitoring + alerts + async | Scheduled re-checks, **pre-expiry ACF alerts**, batch on a queue, rate-limit/circuit-breaker | — | ⬜ Pending | ⬜ |

## Notes

- "Deploy" = a local preview on port 9000 you can click through. A real cloud/staging deploy comes once the core is approved.
- Deploys 1, 2, 3, 7, 8 are **unblocked** — buildable now against the demo data source.
- Deploys 4, 5, 6 wire the three real DGI sources and need one captured sample each (saved into the repo) so tests never hit the live portals.
- Suggested order: 1 → 2 → 3 (gets the full workflow clickable on demo data), then 4/5/6 as captures arrive, then 7 → 8.
