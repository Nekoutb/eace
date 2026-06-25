const puppeteer = require('puppeteer');

const APP = process.argv[2] || 'http://demo.localhost:9090';
const USER = process.env.SHOT_USER || 'demo@eliteadvisors.cm';
const PASS = process.env.SHOT_PASS || 'admin';
const OUT = 'storage/app/ui-shots/';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox', '--host-resolver-rules=MAP *.localhost 127.0.0.1'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900, deviceScaleFactor: 2 });

  await page.goto(APP + '/app/login', { waitUntil: 'networkidle2', timeout: 30000 });
  const sel = await page.evaluate(() => {
    const ins = [...document.querySelectorAll('input')].filter((i) => i.type !== 'hidden' && i.offsetParent !== null);
    const pw = ins.find((i) => i.type === 'password');
    const id = ins.find((i) => i !== pw);
    const css = (i) => (!i ? null : i.id ? '#' + CSS.escape(i.id) : 'input[name="' + i.name + '"]');
    return { id: css(id), pw: css(pw) };
  });
  await page.click(sel.id); await page.type(sel.id, USER, { delay: 20 });
  await page.click(sel.pw); await page.type(sel.pw, PASS, { delay: 20 });
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}), page.keyboard.press('Enter')]);
  await sleep(1500);
  console.log('after-login', page.url());

  const shots = [['/app', 'cust-dashboard'], ['/app/compliance-engine', 'cust-engine'], ['/app/acf-certificates', 'cust-certs'], ['/app/audit-logs', 'cust-audit']];
  for (const [p, n] of shots) {
    try {
      await page.goto(APP + p, { waitUntil: 'networkidle2', timeout: 30000 });
      await sleep(900);
      await page.screenshot({ path: OUT + n + '.png', fullPage: false });
      console.log('shot', n);
    } catch (e) {
      console.log('FAIL', n, e.message);
    }
  }
  await browser.close();
  console.log('DONE');
})();
