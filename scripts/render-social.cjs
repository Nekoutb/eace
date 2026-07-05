// Renders each social frame from social-preview.html to a production PNG,
// sorted into per-platform folders. 9:16 -> 1080x1920, 4:5 -> 1080x1350.
const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');
const { pathToFileURL } = require('url');

const DIR = 'C:\\Users\\UltraBook 3.1\\Documents\\AI Projects\\EA NIU\\Social-Media';
const SRC = pathToFileURL(path.join(DIR, 'social-preview.html')).href;

(async () => {
  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox', '--allow-file-access-from-files'] });
  const page = await browser.newPage();
  await page.goto(SRC, { waitUntil: 'networkidle2', timeout: 60000 });

  const data = await page.evaluate(() => {
    const slug = (s) => (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/\(.*?\)/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 30);
    const num = (l) => { const m = (l || '').match(/^\s*(\d+)/); return m ? m[1].padStart(2, '0') : ''; };
    const rest = (l) => slug((l || '').replace(/^\s*\d+\s*·?\s*/, ''));
    const css = Array.from(document.querySelectorAll('style')).map((s) => s.textContent).join('\n');
    const symbol = document.querySelector('svg[aria-hidden]') ? document.querySelector('svg[aria-hidden]').outerHTML : '';

    const nodes = Array.from(document.querySelectorAll('h2.sec, p.spec, .set, .story .beat'));
    let platform = 'Misc', spec = '';
    const items = [];
    for (const node of nodes) {
      if (node.matches('h2.sec')) {
        platform = node.querySelector('.tag.ws') ? 'WhatsApp' : node.querySelector('.tag.tt') ? 'TikTok' : node.querySelector('.tag.li') ? 'LinkedIn' : 'Misc';
        spec = '';
      } else if (node.matches('p.spec')) {
        spec = node.textContent || '';
      } else if (node.matches('.beat')) {
        const ph = node.querySelector('.ph'); if (!ph) continue;
        const reel = /Reel B/i.test(spec) ? 'reel-b' : 'reel-a';
        let idx = 1, p = node.previousElementSibling; while (p) { if (p.matches && p.matches('.beat')) idx++; p = p.previousElementSibling; }
        items.push({ file: `TikTok/${reel}-${String(idx).padStart(2, '0')}`, html: ph.outerHTML });
      } else if (node.matches('.set')) {
        const lbl = node.querySelector('.lbl') ? node.querySelector('.lbl').textContent : '';
        const phs = Array.from(node.querySelectorAll('.ph'));
        if (platform === 'WhatsApp') {
          ['en', 'fr'].forEach((lang, i) => { const ph = phs[i]; if (ph) items.push({ file: `WhatsApp/${num(lbl)}-${rest(lbl)}-${lang}`, html: ph.outerHTML }); });
        } else if (platform === 'LinkedIn') {
          const ph = phs[0]; if (!ph) continue;
          const car = /Carousel 2/i.test(spec) ? 'c2' : 'c1';
          const lang = /·\s*FR|Le dossier|Sûr|gratuit/i.test(spec) ? 'fr' : 'en';
          items.push({ file: `LinkedIn/${car}-${num(lbl)}-${rest(lbl)}-${lang}`, html: ph.outerHTML });
        }
      }
    }
    return { css, symbol, items };
  });

  for (const item of data.items) {
    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><style>
      html,body{margin:0;padding:0;background:#fff}
      .ph{border-radius:0 !important;box-shadow:none !important;position:absolute;top:0;left:0}
      ${data.css}</style></head><body>${data.symbol}${item.html}</body></html>`;

    await page.setViewport({ width: 360, height: 700, deviceScaleFactor: 1 });
    await page.setContent(html, { waitUntil: 'load' });
    const box = await page.$eval('.ph', (el) => { const r = el.getBoundingClientRect(); return { w: r.width, h: r.height }; });
    const dsf = 1080 / box.w;
    await page.setViewport({ width: Math.ceil(box.w) + 2, height: Math.ceil(box.h) + 2, deviceScaleFactor: dsf });
    await new Promise((r) => setTimeout(r, 90));

    const out = path.join(DIR, item.file + '.png');
    fs.mkdirSync(path.dirname(out), { recursive: true });
    await page.screenshot({ path: out, clip: { x: 0, y: 0, width: box.w, height: box.h } });
    console.log('  ', item.file + '.png');
  }

  await browser.close();
  console.log('Done —', data.items.length, 'images.');
})();
