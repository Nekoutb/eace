const puppeteer = require('puppeteer');
const path = require('path');
const { pathToFileURL } = require('url');

// Usage: node render-pdf.cjs <input.html> <output.pdf>  (defaults to the FR deck)
const DIR = 'C:\\Users\\UltraBook 3.1\\Documents\\AI Projects\\EA NIU\\Customer-Brochure';
const inputName = process.argv[2] || 'brochure.html';
const outputName = process.argv[3] || 'Elite-Advisors-Compliance-Engine.pdf';

const FILE = pathToFileURL(path.join(DIR, inputName)).href;
const OUT = path.join(DIR, outputName);

(async () => {
  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox', '--allow-file-access-from-files'] });
  const page = await browser.newPage();
  await page.goto(FILE, { waitUntil: 'networkidle2', timeout: 60000 });
  await new Promise((r) => setTimeout(r, 1200));
  await page.pdf({ path: OUT, format: 'A4', printBackground: true, preferCSSPageSize: true });
  await browser.close();
  console.log('PDF written:', OUT);
})();
