// DGI live taxpayer-list export driver.
//
// Drives the official registration-consultation portal:
//   https://teledeclaration-dgi.cm/modules/immatriculation/Consultation/listecontribuable.aspx
// an ASP.NET WebForms page with tab-specific "Exporter" buttons. The full taxpayer
// list is the "Organismes" tab (TabContainer3_TabPanelList) → btnExporter00, a
// ViewState postback that streams an .xlsx download (~579k rows). A raw cURL
// ViewState replay does NOT work (it re-renders the page), so this drives a real
// headless Chromium and captures the download via the browser-level CDP download
// API (Page.setDownloadBehavior is ignored under headless "new").
//
// Emits one JSON line on stdout:
//   { "ok": true, "file": "<path>", "bytes": N, "suggested": "<name>" }  on success
//   { "ok": false, "error": "..." }                                      otherwise
// Never throws.
//
// Usage: node scripts/dgi-export.cjs --dir=<downloadDir> --chrome=<chrome> [--url=…] [--timeout=300]

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

function arg(name, fallback = null) {
  const hit = process.argv.find(a => a.startsWith(`--${name}=`));
  return hit ? hit.slice(name.length + 3) : fallback;
}

const URL = arg('url', 'https://teledeclaration-dgi.cm/modules/immatriculation/Consultation/listecontribuable.aspx');
const DIR = arg('dir');
const CHROME = arg('chrome');
const TIMEOUT = parseInt(arg('timeout', '300'), 10) * 1000;
const sleep = ms => new Promise(r => setTimeout(r, ms));
const log = m => { try { process.stderr.write('[dgi-export] ' + m + '\n'); } catch (_) {} };

function fail(msg) {
  process.stdout.write(JSON.stringify({ ok: false, error: msg }) + '\n');
  process.exit(0);
}

(async () => {
  if (!DIR || !CHROME) return fail('missing --dir/--chrome');
  try { fs.mkdirSync(DIR, { recursive: true }); } catch (_) {}

  let browser;
  try {
    browser = await puppeteer.launch({
      executablePath: CHROME,
      headless: 'new',
      args: ['--no-sandbox', '--disable-dev-shm-usage'],
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 900 });

    // Browser-level download capture (the page-level API is ignored in new headless).
    const cdp = await browser.target().createCDPSession();
    await cdp.send('Browser.setDownloadBehavior', { behavior: 'allow', downloadPath: DIR, eventsEnabled: true });

    const downloads = {}; // guid -> { name, state }
    cdp.on('Browser.downloadWillBegin', e => {
      downloads[e.guid] = { name: e.suggestedFilename || e.guid, state: 'inProgress' };
      log('download begin: ' + (e.suggestedFilename || e.guid));
    });
    cdp.on('Browser.downloadProgress', e => {
      if (downloads[e.guid]) downloads[e.guid].state = e.state;
      if (e.state === 'completed') log('download completed: ' + e.guid);
    });

    log('navigating ' + URL);
    await page.goto(URL, { waitUntil: 'networkidle2', timeout: 60000 });
    await sleep(2500);

    // "Exporter" dumps the entire register — just click the one visible on load
    // (the default tab), with a REAL mouse click so the ASP.NET postback fires.
    const targetId = await page.evaluate(() => {
      const b = [...document.querySelectorAll('input[value="Exporter"]')].find(e => e.offsetParent !== null);
      return b ? b.id : null;
    });
    if (!targetId) { await browser.close(); return fail('no visible Exporter button on the page'); }

    log('clicking Exporter #' + targetId);
    await page.click('#' + targetId);
    log('Exporter clicked — waiting for the export to generate + download');

    // The server generates ~579k rows before the download begins, so wait patiently
    // for a download to BEGIN and then COMPLETE (or be canceled).
    const deadline = Date.now() + TIMEOUT;
    let completed = null;
    while (Date.now() < deadline) {
      const done = Object.values(downloads).find(d => d.state === 'completed');
      if (done) { completed = done; break; }
      if (Object.values(downloads).some(d => d.state === 'canceled')) { await browser.close(); return fail('download canceled by browser'); }
      await sleep(2000);
    }

    await sleep(1500);
    await browser.close();

    if (!completed) return fail('no download completed within timeout (export may need a filter, or the site was slow)');

    // Browser.setDownloadBehavior saves the file under DIR named by GUID — pick the largest.
    const files = fs.readdirSync(DIR)
      .map(f => path.join(DIR, f))
      .filter(p => { try { return fs.statSync(p).isFile(); } catch (_) { return false; } })
      .sort((a, b) => fs.statSync(b).size - fs.statSync(a).size);

    if (files.length === 0) return fail('download reported complete but no file on disk');

    return process.stdout.write(JSON.stringify({ ok: true, file: files[0], bytes: fs.statSync(files[0]).size, suggested: completed.name }) + '\n');
  } catch (e) {
    if (browser) { try { await browser.close(); } catch (_) {} }
    return fail('driver error: ' + (e && e.message ? e.message : String(e)));
  }
})();
