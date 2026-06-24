const puppeteer = require('puppeteer');
const { pathToFileURL } = require('url');

const FILE = pathToFileURL('C:\\Users\\UltraBook 3.1\\Documents\\AI Projects\\EA NIU\\Elite-Advisors-Academy\\index.html').href;
const OUT = 'storage/app/ui-shots/';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox', '--allow-file-access-from-files'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 880, deviceScaleFactor: 2 });
  await page.goto(FILE, { waitUntil: 'networkidle2', timeout: 30000 });
  await sleep(800);

  const next = async (n) => { for (let i = 0; i < n; i++) { await page.evaluate(() => window.go(1)); await sleep(380); } };

  await next(2);
  await page.screenshot({ path: OUT + 'academy-02-solution.png', fullPage: true });
  console.log('shot solution');

  await next(2);
  await page.screenshot({ path: OUT + 'academy-04-value.png', fullPage: true });
  console.log('shot value');

  await next(3);
  await page.screenshot({ path: OUT + 'academy-07-security.png', fullPage: true });
  console.log('shot security');

  await page.evaluate(() => window.setLang('en'));
  await sleep(500);
  await page.screenshot({ path: OUT + 'academy-07-security-en.png', fullPage: true });
  console.log('shot security-en');

  await browser.close();
  console.log('DONE');
})();
