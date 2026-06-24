const puppeteer = require('puppeteer');

const CENTRAL = process.argv[2] || 'http://127.0.0.1:9000';
const APP = process.argv[3] || 'http://demo.localhost:9000';
const USER = process.env.SHOT_USER || 'admin';
const PASS = process.env.SHOT_PASS || 'admin';
const OUT = 'storage/app/ui-shots';

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function shot(page, name) {
  await sleep(800);
  await page.screenshot({ path: `${OUT}/${name}.png`, fullPage: true });
  console.log('shot', name);
}

(async () => {
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--host-resolver-rules=MAP *.localhost 127.0.0.1'],
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900, deviceScaleFactor: 2 });

  for (const [p, n] of [['/', 'public-landing'], ['/privacy', 'public-privacy'], ['/register', 'public-signup']]) {
    try {
      await page.goto(CENTRAL + p, { waitUntil: 'networkidle2', timeout: 30000 });
      await shot(page, n);
    } catch (e) {
      console.log('FAIL', n, e.message);
    }
  }

  try {
    await page.goto(APP + '/app/login', { waitUntil: 'networkidle2', timeout: 30000 });
    await shot(page, 'app-login');

    const sel = await page.evaluate(() => {
      const vis = (el) => el && el.offsetParent !== null;
      const ins = [...document.querySelectorAll('input')].filter((i) => i.type !== 'hidden' && vis(i));
      const pw = ins.find((i) => i.type === 'password');
      const id = ins.find((i) => i !== pw);
      const css = (i) => (!i ? null : i.id ? '#' + CSS.escape(i.id) : i.name ? `input[name="${i.name}"]` : null);
      return { id: css(id), pw: css(pw) };
    });
    console.log('login selectors', JSON.stringify(sel));

    if (sel.id) { await page.click(sel.id); await page.type(sel.id, USER, { delay: 25 }); }
    if (sel.pw) { await page.click(sel.pw); await page.type(sel.pw, PASS, { delay: 25 }); }

    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
      page.keyboard.press('Enter'),
    ]);
    await sleep(1800);
    console.log('after-login-url', page.url());
    await shot(page, 'app-dashboard');

    const links = await page.evaluate(() => {
      const set = {};
      document.querySelectorAll('a[href]').forEach((a) => {
        const h = a.getAttribute('href') || '';
        if (h.includes('/app') && !h.includes('logout') && !h.includes('/login')) {
          const label = (a.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 30);
          if (!(h in set)) set[h] = label;
        }
      });
      return Object.entries(set).map(([href, label]) => ({ href, label }));
    });
    console.log('nav-links', JSON.stringify(links));

    let i = 0;
    for (const { href, label } of links) {
      i++;
      const slug = (label || 'page' + i).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 28);
      const name = 'app-' + (slug || 'page' + i);
      try {
        const url = href.startsWith('http') ? href : APP + href;
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });
        await shot(page, name);
      } catch (e) {
        console.log('FAIL', name, e.message);
      }
    }
  } catch (e) {
    console.log('LOGIN-FLOW-FAIL', e.message);
  }

  await browser.close();
  console.log('DONE');
})();
