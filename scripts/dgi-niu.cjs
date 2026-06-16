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

const puppeteer = require('puppeteer');

function arg(name, fallback = null) {
  const hit = process.argv.find(a => a.startsWith(`--${name}=`));
  return hit ? hit.slice(name.length + 3) : fallback;
}

const NIU = arg('niu');
const OUT = arg('out');
const CHROME = arg('chrome');
const URL = arg('url', 'https://teledeclaration-dgi.cm/modules/immatriculation/Consultation/listecontribuable.aspx');
const sleep = ms => new Promise(r => setTimeout(r, ms));

function fail(message) {
  process.stdout.write(JSON.stringify({ status: 'unverified', count: 0, text: message, screenshot: null }) + '\n');
  process.exit(0);
}

(async () => {
  if (!NIU || !OUT || !CHROME) return fail('missing --niu/--out/--chrome');

  let browser;
  try {
    browser = await puppeteer.launch({
      executablePath: CHROME,
      args: ['--no-sandbox', '--disable-dev-shm-usage'],
      headless: 'new',
    });
    const page = await browser.newPage();
    await page.setViewport({ width: 1366, height: 1500 });
    await page.goto(URL, { waitUntil: 'networkidle2', timeout: 90000 });

    // Fill the card NIU field and submit via a real click (fires the postback).
    await page.click('#TabContainer3_TabPanelCard_txtNIU', { clickCount: 3 });
    await page.type('#TabContainer3_TabPanelCard_txtNIU', NIU, { delay: 25 });
    await sleep(300);
    await page.click('#TabContainer3_TabPanelCard_btnAfficher2');
    await sleep(9000); // UpdatePanel partial postback + grid render

    // Classify from the "<n> contribuable(s)" counter and the presence of the
    // NIU in the result grid.
    const info = await page.evaluate(niu => {
      const body = document.body.innerText || '';
      const m = body.match(/(\d+)\s+contribuable/i);
      const count = m ? parseInt(m[1], 10) : null;
      // A result row carries the NIU inside a data cell (not the input box).
      const inGrid = [...document.querySelectorAll('td')].some(td => (td.textContent || '').trim() === niu);
      return { count, inGrid, snippet: body.replace(/\n{2,}/g, '\n').slice(0, 600) };
    }, NIU);

    let status = 'unverified';
    if (info.count !== null) status = (info.count > 0 || info.inGrid) ? 'present' : 'absent';
    else if (info.inGrid) status = 'present';

    await page.screenshot({ path: OUT, fullPage: true });
    process.stdout.write(JSON.stringify({
      status, count: info.count ?? 0, text: info.snippet, screenshot: OUT,
    }) + '\n');
    await browser.close();
    process.exit(0);
  } catch (e) {
    if (browser) { try { await browser.close(); } catch (_) {} }
    return fail('driver error: ' + (e && e.message ? e.message : String(e)));
  }
})();
