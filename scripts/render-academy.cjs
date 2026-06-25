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
  await sleep(700);

  const next = async (n) => { for (let i = 0; i < n; i++) { await page.evaluate(() => window.go(1)); await sleep(300); } };

  await next(1);
  await page.screenshot({ path: OUT + 'academy2-01-problem.png', fullPage: true });
  console.log('shot problem');

  await next(4);
  await page.evaluate(() => document.querySelectorAll('.obj').forEach((o) => o.classList.add('open')));
  await sleep(300);
  await page.screenshot({ path: OUT + 'academy2-05-sales.png', fullPage: true });
  console.log('shot sales');

  await next(3);
  await sleep(400);
  await page.screenshot({ path: OUT + 'academy2-08-quiz.png', fullPage: true });
  console.log('shot quiz');

  const checks = await page.evaluate(() => {
    const attrs = [...document.querySelectorAll('[data-fr]')].map((e) => (e.getAttribute('data-fr') || '') + (e.getAttribute('data-en') || '')).join(' ');
    return {
      quizCount: document.querySelectorAll('#quiz .quiz').length,
      leakTipMarkup: [...document.querySelectorAll('.obj .a')].some((e) => e.textContent.includes('<span')),
      engineFullName: attrs.includes('Elite Advisors Compliance Engine'),
      has579: attrs.includes('579'),
    };
  });
  console.log('checks', JSON.stringify(checks));

  await browser.close();
  console.log('DONE');
})();
