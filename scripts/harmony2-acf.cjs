// Harmony2 ACF authenticity driver (deep-link).
//
// The DGI "Authentification des documents" portal accepts a direct deep-link
// carrying the certificate reference — exactly what the ACF's QR code encodes:
//   https://fanr.harmony2.cm/authentification?doc=ACF&reference=<REFERENCE>
// where <REFERENCE> is the 620-prefixed reference printed top-left on the ACF
// (beneath the tax centre). Navigating straight to it makes the SPA fetch and
// render the verdict — no need to drive the "Saisir la référence" form.
//
// Emits one JSON line on stdout:
//   { "status": "authentic|not_authentic|unverified", "text": "...", "screenshot": "<path>" }
//
// Invoked by App\Services\Acf\Harmony2AcfConnector via Symfony Process.
//
// Usage: node scripts/harmony2-acf.cjs --url=<full deep-link> --out=<png> --chrome=<chrome.exe>

const puppeteer = require('puppeteer');

function arg(name, fallback = null) {
  const hit = process.argv.find(a => a.startsWith(`--${name}=`));
  return hit ? hit.slice(name.length + 3) : fallback;
}

const URL = arg('url');
const OUT = arg('out');
const CHROME = arg('chrome');
const sleep = ms => new Promise(r => setTimeout(r, ms));

function classify(text) {
  const t = (text || '').toLowerCase();
  // Order matters: the negative verdict also contains the word "authentifié".
  if (/n['’]est pas authentifi|n['’]est pas reconnu|pas authentifi|non authentifi|non reconnu|introuvable|invalide/.test(t)) {
    return 'not_authentic';
  }
  if (/est authentifi|document authentifi|reconnu par le syst|est reconnu/.test(t)) {
    return 'authentic';
  }
  return 'unverified';
}

function fail(message) {
  process.stdout.write(JSON.stringify({ status: 'unverified', text: message, screenshot: null }) + '\n');
  process.exit(0); // graceful — the PHP side treats unverified as "could not confirm"
}

(async () => {
  if (!URL || !OUT || !CHROME) return fail('missing --url/--out/--chrome');

  let browser;
  try {
    browser = await puppeteer.launch({
      executablePath: CHROME,
      args: ['--no-sandbox', '--disable-dev-shm-usage'],
      headless: 'new',
    });
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 1500 });
    // Timeouts kept comfortably below the PHP-side Process timeout so a slow
    // portal yields a clean "unverified" rather than being SIGKILLed mid-run.
    await page.goto(URL, { waitUntil: 'networkidle2', timeout: 60000 });

    // Wait for the SPA to fetch and render a verdict (either polarity), then
    // give the embedded PDF preview a moment to paint before the screenshot.
    await page.waitForFunction(
      () => /authentifi|reconnu|introuvable|invalide/i.test(document.body.innerText || ''),
      { timeout: 20000 },
    ).catch(() => {});
    await sleep(3500);

    const info = await page.evaluate(() => ({
      text: document.body.innerText.replace(/\n{2,}/g, '\n').trim().slice(0, 2000),
      url: location.href,
    }));

    // Corroboration: only trust the keyword verdict if we are still on the
    // result view for THIS reference (its digits remain in the URL or body).
    // A redirect to a generic/login/maintenance page loses the reference, so it
    // is classified "unverified" instead of misreading a stray "authentifié".
    const ref = (URL.match(/reference=(\d+)/) || [])[1] || '';
    const onResultView = ref === '' || info.url.includes(ref) || info.text.includes(ref);
    const status = onResultView ? classify(info.text) : 'unverified';

    // Capture the screenshot independently — a render glitch must not discard an
    // already-computed verdict (the verdict is the contract; the shot is evidence).
    let shot = OUT;
    try {
      await page.screenshot({ path: OUT, fullPage: true });
    } catch (_) {
      shot = null;
    }

    process.stdout.write(JSON.stringify({ status, text: info.text, screenshot: shot }) + '\n');
    await browser.close();
    process.exit(0);
  } catch (e) {
    if (browser) { try { await browser.close(); } catch (_) {} }
    return fail('driver error: ' + (e && e.message ? e.message : String(e)));
  }
})();
