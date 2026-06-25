const puppeteer = require('puppeteer');
const { pathToFileURL } = require('url');

const FILE = pathToFileURL('C:\\Users\\UltraBook 3.1\\Documents\\AI Projects\\EA NIU\\Customer-Brochure\\brochure.html').href;
const OUT = 'C:\\Users\\UltraBook 3.1\\Documents\\AI Projects\\EA NIU\\Customer-Brochure\\Elite-Advisors-Compliance-Engine.pdf';

(async () => {
  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox', '--allow-file-access-from-files'] });
  const page = await browser.newPage();
  await page.goto(FILE, { waitUntil: 'networkidle2', timeout: 60000 });
  await new Promise((r) => setTimeout(r, 1000));
  await page.pdf({ path: OUT, format: 'A4', printBackground: true, preferCSSPageSize: true });
  await browser.close();
  console.log('PDF written:', OUT);
})();
