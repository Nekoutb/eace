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
const STAMP = arg('stamp'); // evidence band (brand + capture timestamp + URL)
const ATTEMPTS = 2;
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

// One full drive. Returns { status, text, screenshot } or null on a transient
// miss (no verdict rendered) so the caller can retry.
async function attempt(browser) {
  const page = await browser.newPage();
  try {
    // deviceScaleFactor 3 → the stored snapshot stays sharp when zoomed.
    await page.setViewport({ width: 1100, height: 1500, deviceScaleFactor: 3 });
    await page.goto(URL, { waitUntil: 'networkidle2', timeout: 60000 });

    // Wait for the actual VERDICT — "reconnu" appears in both polarities
    // ("reconnu par le système" / "n'est pas reconnu") but NOT in the page title,
    // so this can't fire early on "Authentification des documents".
    const rendered = await page.waitForFunction(
      () => /reconnu|introuvable|invalide/i.test(document.body.innerText || ''),
      { timeout: 20000 },
    ).then(() => true).catch(() => false);
    await sleep(2500);

    const info = await page.evaluate(() => ({
      text: document.body.innerText.replace(/\n{2,}/g, '\n').trim().slice(0, 2000),
      url: location.href,
    }));

    // No verdict yet → the SPA hasn't answered; signal a retry.
    if (!rendered && !/reconnu|introuvable|invalide/i.test(info.text)) {
      return null;
    }

    // Corroboration: only trust the keyword verdict if we are still on the
    // result view for THIS reference (its digits remain in the URL or body).
    const ref = (URL.match(/reference=(\d+)/) || [])[1] || '';
    const onResultView = ref === '' || info.url.includes(ref) || info.text.includes(ref);
    const status = onResultView ? classify(info.text) : 'unverified';

    // An authentic verdict embeds the certificate PDF — wait for it to paint so
    // the proof shows the document. A not-authentic page has none, so skip.
    if (status === 'authentic') {
      await page.waitForFunction(
        () => [...document.querySelectorAll('canvas,iframe,embed,object')].some(e => e.offsetParent !== null && e.getBoundingClientRect().height > 200),
        { timeout: 18000 },
      ).catch(() => {});
      await sleep(5000);
    }

    // Bake an evidence band into the page so the screenshot itself proves WHEN it
    // was captured and which link was checked (headless captures show neither an
    // address bar nor a clock). The text is supplied by PHP: brand + capture
    // timestamp + the verification URL.
    await page.evaluate(stamp => {
      const bar = document.createElement('div');
      bar.style.cssText = 'position:relative;z-index:99999;background:#0f231a;color:#fff;font:13px/1.5 ui-monospace,Menlo,Consolas,monospace;padding:10px 16px;word-break:break-all;border-bottom:3px solid #22c55e';
      bar.textContent = stamp;
      document.body.insertBefore(bar, document.body.firstChild);
    }, STAMP || URL).catch(e => { try { process.stderr.write('stamp-band-failed: ' + (e && e.message ? e.message : String(e)) + '\n'); } catch (_) {} });
    await sleep(600);

    // Capture independently — a render glitch must not discard an already-computed verdict.
    let shot = OUT;
    try {
      await page.screenshot({ path: OUT, fullPage: true });
    } catch (_) {
      shot = null;
    }

    return { status, text: info.text, screenshot: shot, captured_at_ms: Date.now() };
  } finally {
    try { await page.close(); } catch (_) {}
  }
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

    for (let i = 0; i < ATTEMPTS; i++) {
      const result = await attempt(browser).catch(() => null);
      if (result !== null) {
        await browser.close();
        process.stdout.write(JSON.stringify(result) + '\n');
        process.exit(0);
      }
      await sleep(1500);
    }

    await browser.close();
    return fail('no verdict after ' + ATTEMPTS + ' attempts');
  } catch (e) {
    if (browser) { try { await browser.close(); } catch (_) {} }
    return fail('driver error: ' + (e && e.message ? e.message : String(e)));
  }
})();
