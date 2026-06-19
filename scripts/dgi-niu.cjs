// DGI "Vérifier un NIU" driver (fiscalis.dgi.cm).
//
// Drives the official taxpayer-verification portal:
//   http://fiscalis.dgi.cm/modules/Common/Account/Login.aspx  ->  "Vérifier un NIU"
// fills the NIU, submits the magnifier search, and screenshots the result, which
// returns the full taxpayer card (Nom/Raison sociale, Sigle, CNI/RC, Activité,
// Régime fiscal, Centre des impôts) plus the authoritative **Etat** (Actif / …).
// Emits one JSON line on stdout:
//   { "status": "present|absent|unverified", "count": <0|1>, "text": "...", "screenshot": "<path>" }
//   present = Etat Actif; absent = found-but-not-Actif or not found; unverified = portal unreachable.
//
// Notes:
// - The site serves only over HTTP; headless Chrome auto-upgrades to HTTPS (which
//   404s), so the browser is launched with the HTTPS-upgrade features disabled.
// - Captured at deviceScaleFactor 3 so the stored snapshot stays sharp when zoomed.

const puppeteer = require('puppeteer');

function arg(name, fallback = null) {
  const hit = process.argv.find(a => a.startsWith(`--${name}=`));
  return hit ? hit.slice(name.length + 3) : fallback;
}

const NIU = arg('niu');
const OUT = arg('out');
const CHROME = arg('chrome');
const STAMP = arg('stamp'); // evidence band (brand + capture timestamp + NIU)
const URL = arg('url', 'http://fiscalis.dgi.cm/modules/Common/Account/Login.aspx');
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
const ATTEMPTS = 2;
const sleep = ms => new Promise(r => setTimeout(r, ms));

function emit(obj) {
  process.stdout.write(JSON.stringify(obj) + '\n');
  process.exit(0);
}

// One full drive: returns { settled, status, etat, raison, text, screenshot }, or
// null on a hard error (so the caller retries). settled = a definitive answer
// (taxpayer found, or an explicit "no match"); an unsettled result is retried.
async function attempt(browser) {
  const page = await browser.newPage();
  try {
    await page.setUserAgent(UA);
    await page.setViewport({ width: 1200, height: 1000, deviceScaleFactor: 3 });
    await page.goto(URL, { waitUntil: 'networkidle2', timeout: 60000 });
    await sleep(1200);

    await page.evaluate(() => document.getElementById('__tab_TabContainer1_TabPanelVerifyNIU')?.click());
    await sleep(1600);

    await page.click('#TabContainer1_TabPanelVerifyNIU_txtNIU2', { clickCount: 3 });
    await page.type('#TabContainer1_TabPanelVerifyNIU_txtNIU2', NIU, { delay: 25 });
    await sleep(300);
    await page.click('#TabContainer1_TabPanelVerifyNIU_ibFindContribuable');

    // Wait for the partial postback to populate the taxpayer's name.
    await page.waitForFunction(() => {
      const m = (document.body.innerText || '').match(/Nom\/Raison sociale\s+([^\n\t]+)/i);
      return !!(m && m[1].trim().length > 0);
    }, { timeout: 22000 }).catch(() => {});
    await sleep(1500);

    const body = await page.evaluate(() => document.body.innerText || '');
    const raisonM = body.match(/Nom\/Raison sociale[ \t]+([^\n\t]+)/i); // same-line value only
    const raison = raisonM ? raisonM[1].trim() : null;
    const etatM = body.match(/\b(Actif|Inactif|Suspendu|Radi[ée])\b/i);
    const noMatch = /aucune correspondance/i.test(body);
    const etat = noMatch ? 'Aucune correspondance trouvée' : (etatM ? etatM[1] : null);
    const active = !noMatch && !!etatM && /actif/i.test(etatM[1]);
    const found = !noMatch && (!!etatM || !!raison);

    // Bake an evidence band into the page so the screenshot itself proves WHEN it
    // was captured and which NIU was checked (headless captures show neither an
    // address bar nor a clock). The text is supplied by PHP.
    await page.evaluate(stamp => {
      const bar = document.createElement('div');
      bar.style.cssText = 'position:relative;z-index:99999;background:#0f231a;color:#fff;font:13px/1.5 ui-monospace,Menlo,Consolas,monospace;padding:10px 16px;word-break:break-all;border-bottom:3px solid #22c55e';
      bar.textContent = stamp;
      document.body.insertBefore(bar, document.body.firstChild);
    }, STAMP || ('NIU ' + NIU)).catch(e => { try { process.stderr.write('stamp-band-failed: ' + (e && e.message ? e.message : String(e)) + '\n'); } catch (_) {} });
    await sleep(400);

    let shot = OUT;
    try {
      await page.screenshot({ path: OUT, fullPage: true });
    } catch (_) {
      shot = null;
    }

    return {
      settled: found || noMatch,
      status: active ? 'present' : 'absent',
      etat,
      raison,
      text: (raison ? raison + ' — ' : '') + 'Etat: ' + (etat || 'inconnu'),
      screenshot: shot,
      captured_at_ms: Date.now(),
    };
  } finally {
    try { await page.close(); } catch (_) {}
  }
}

(async () => {
  if (!NIU || !OUT || !CHROME) return emit({ status: 'unverified', count: 0, text: 'missing --niu/--out/--chrome', screenshot: null });

  let browser;
  try {
    browser = await puppeteer.launch({
      executablePath: CHROME,
      headless: 'new',
      args: [
        '--no-sandbox', '--disable-dev-shm-usage', '--ignore-certificate-errors',
        '--disable-features=HttpsUpgrades,HttpsFirstBalancedModeAutoEnable,HttpsFirstModeV2,AutoupgradeMixedContent',
      ],
    });

    let last = null;
    for (let i = 0; i < ATTEMPTS; i++) {
      const r = await attempt(browser).catch(() => null);
      if (r !== null) {
        last = r;
        // A definitive answer (found, or an explicit "no match") — no need to retry.
        if (r.settled) {
          await browser.close();
          return emit({ status: r.status, count: r.status === 'present' ? 1 : 0, text: r.text, screenshot: r.screenshot });
        }
      }
      await sleep(1500);
    }

    await browser.close();
    if (last !== null) {
      // Never settled, but the portal did render — keep the (empty) shot as evidence.
      return emit({ status: last.status, count: 0, text: last.text, screenshot: last.screenshot });
    }
    return emit({ status: 'unverified', count: 0, text: 'portal unreachable after ' + ATTEMPTS + ' attempts', screenshot: null });
  } catch (e) {
    if (browser) { try { await browser.close(); } catch (_) {} }
    return emit({ status: 'unverified', count: 0, text: 'driver error: ' + (e && e.message ? e.message : String(e)), screenshot: null });
  }
})();
