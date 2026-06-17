// DGI National Taxpayer File per-NIU consultation driver.
//
// Drives the "Fichier National des Contribuables" portal
// (.../Consultation/listecontribuable.aspx, TabPanelCard) for one NIU and
// screenshots the result. The portal returns "<n> contribuable(s)" plus a row
// (NIU, activity, regime, centre…) when the taxpayer is on the national file —
// i.e. registered/active. Emits one JSON line on stdout:
//   { "status": "present|absent|unverified", "count": <n>, "text": "...", "screenshot": "<path>" }
//
// Invoked by App\Services\Fiscalis\DgiFiscalisConnector. Kept as a Node script
// because the portal is an ASP.NET UpdatePanel form that must be filled +
// submitted (real postback), which Browsershot's static render cannot do.
//
// Resilient: this government portal is slow/flaky, so each attempt waits for the
// real "<n> contribuable" result (not a fixed delay) and the whole drive is
// retried a few times before giving up with "unverified".

const puppeteer = require('puppeteer');

function arg(name, fallback = null) {
  const hit = process.argv.find(a => a.startsWith(`--${name}=`));
  return hit ? hit.slice(name.length + 3) : fallback;
}

const NIU = arg('niu');
const OUT = arg('out');
const CHROME = arg('chrome');
const URL = arg('url', 'https://teledeclaration-dgi.cm/modules/immatriculation/Consultation/listecontribuable.aspx');
const ATTEMPTS = 2;
const sleep = ms => new Promise(r => setTimeout(r, ms));

function emit(obj) {
  process.stdout.write(JSON.stringify(obj) + '\n');
  process.exit(0);
}

// One full drive. Returns a result object, or null on a transient miss so the
// caller can retry.
async function attempt(browser) {
  const page = await browser.newPage();
  try {
    await page.setViewport({ width: 1366, height: 1500 });
    await page.goto(URL, { waitUntil: 'networkidle2', timeout: 60000 });

    await page.click('#TabContainer3_TabPanelCard_txtNIU', { clickCount: 3 });
    await page.type('#TabContainer3_TabPanelCard_txtNIU', NIU, { delay: 25 });
    await sleep(300);
    await page.click('#TabContainer3_TabPanelCard_btnAfficher2');

    // Wait for the UpdatePanel postback to render the "<n> contribuable" counter.
    await page.waitForFunction(
      () => /\d+\s+contribuable/i.test(document.body.innerText || ''),
      { timeout: 25000 },
    ).catch(() => {});
    await sleep(1500); // let the grid rows paint

    const info = await page.evaluate(niu => {
      const body = document.body.innerText || '';
      const m = body.match(/(\d+)\s+contribuable/i);
      const count = m ? parseInt(m[1], 10) : null;
      const inGrid = [...document.querySelectorAll('td')].some(td => (td.textContent || '').trim() === niu);
      return { count, inGrid, snippet: body.replace(/\n{2,}/g, '\n').slice(0, 600) };
    }, NIU);

    // No counter found → the postback hasn't settled; signal a retry.
    if (info.count === null && !info.inGrid) {
      return null;
    }

    let shot = OUT;
    try {
      await page.screenshot({ path: OUT, fullPage: true });
    } catch (_) {
      shot = null;
    }

    const status = (info.count !== null ? info.count > 0 : false) || info.inGrid ? 'present' : 'absent';
    return { status, count: info.count ?? 0, text: info.snippet, screenshot: shot };
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
      args: ['--no-sandbox', '--disable-dev-shm-usage'],
      headless: 'new',
    });

    for (let i = 0; i < ATTEMPTS; i++) {
      const result = await attempt(browser).catch(() => null);
      if (result !== null) {
        await browser.close();
        return emit(result);
      }
      await sleep(1500); // brief backoff before retrying
    }

    await browser.close();
    return emit({ status: 'unverified', count: 0, text: 'no result after '+ATTEMPTS+' attempts', screenshot: null });
  } catch (e) {
    if (browser) { try { await browser.close(); } catch (_) {} }
    return emit({ status: 'unverified', count: 0, text: 'driver error: ' + (e && e.message ? e.message : String(e)), screenshot: null });
  }
})();
