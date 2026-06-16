// Harmony2 ACF authenticity driver.
//
// Drives the DGI "Authentification des documents" SPA
// (https://fanr.harmony2.cm/authentification) for one ACF reference and writes
// a full-page screenshot of the verdict. Emits a single JSON line on stdout:
//   { "status": "authentic|not_authentic|unverified", "text": "...", "screenshot": "<path>" }
//
// Invoked by App\Services\Acf\Harmony2AcfConnector via Symfony Process. Kept as
// a Node script (not Browsershot) because the portal is a MUI/React form that
// must be clicked through — Browsershot only renders a static URL.
//
// Usage: node scripts/harmony2-acf.cjs --reference=<ref> --out=<png> \
//          --chrome=<chrome.exe> [--url=<base>] [--type=acf]

const puppeteer = require('puppeteer');

function arg(name, fallback = null) {
  const hit = process.argv.find(a => a.startsWith(`--${name}=`));
  return hit ? hit.slice(name.length + 3) : fallback;
}

const REFERENCE = arg('reference');
const OUT = arg('out');
const CHROME = arg('chrome');
const URL = arg('url', 'https://fanr.harmony2.cm/authentification');
const DOC_TYPE = arg('type', 'acf');
const sleep = ms => new Promise(r => setTimeout(r, ms));

function classify(text) {
  const t = (text || '').toLowerCase();
  // Order matters: the negative verdict also contains the word "authentifié".
  if (/n['’]est pas authentifi|n['’]est pas reconnu|pas authentifi|non authentifi|non reconnu|introuvable|invalide/.test(t)) {
    return 'not_authentic';
  }
  if (/est authentifi|document authentifi|reconnu par le syst|est reconnu|valide/.test(t)) {
    return 'authentic';
  }
  return 'unverified';
}

function fail(message) {
  process.stdout.write(JSON.stringify({ status: 'unverified', text: message, screenshot: null }) + '\n');
  process.exit(0); // graceful — the PHP side treats unverified as "could not confirm"
}

(async () => {
  if (!REFERENCE || !OUT || !CHROME) return fail('missing --reference/--out/--chrome');

  let browser;
  try {
    browser = await puppeteer.launch({
      executablePath: CHROME,
      args: ['--no-sandbox', '--disable-dev-shm-usage'],
      headless: 'new',
    });
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 1400 });
    await page.goto(URL, { waitUntil: 'networkidle2', timeout: 90000 });

    // Reveal the reference form.
    await page.locator('button::-p-text(Saisir la référence)').click();
    await sleep(1500);

    // Open the document-type select and pick the requested type by data-value.
    await page.locator('[role=combobox]').click();
    await sleep(1000);
    const opts = await page.$$('li[role=option], [role=option]');
    let chose = false;
    for (const h of opts) {
      const val = await h.evaluate(e => e.getAttribute('data-value') || '');
      if (val === DOC_TYPE) { await h.click(); chose = true; break; }
    }
    if (!chose) {
      // fall back to matching ACF by label
      for (const h of opts) {
        const t = (await h.evaluate(e => e.innerText || '')).toLowerCase();
        if (/conformit[ée] fiscale|\bacf\b/.test(t)) { await h.click(); chose = true; break; }
      }
    }
    await sleep(700);

    // Enter the reference and submit.
    await page.locator('input[name=reference]').fill(REFERENCE);
    await sleep(400);
    await page.locator('button::-p-text(Valider)').click();
    await sleep(7000); // allow the API round-trip + result render

    const text = await page.evaluate(() => document.body.innerText.replace(/\n{2,}/g, '\n').trim().slice(0, 2000));
    await page.screenshot({ path: OUT, fullPage: true });

    process.stdout.write(JSON.stringify({ status: classify(text), text, screenshot: OUT }) + '\n');
    await browser.close();
    process.exit(0);
  } catch (e) {
    if (browser) { try { await browser.close(); } catch (_) {} }
    return fail('driver error: ' + (e && e.message ? e.message : String(e)));
  }
})();
