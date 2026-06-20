const puppeteer = require('puppeteer');

(async () => {
  const base = process.argv[2] || 'http://127.0.0.1:8131';
  const shots = [
    ['/app/login', 'app-login'],
    ['/admin/login', 'admin-login'],
    ['/', 'landing'],
    ['/register', 'signup'],
    ['/security', 'security'],
  ];
  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  for (const [path, name] of shots) {
    try {
      await page.goto(base + path, { waitUntil: 'networkidle2', timeout: 25000 });
      await new Promise((r) => setTimeout(r, 600));
      await page.screenshot({ path: `storage/app/ui-shots/${name}.png`, fullPage: false });
      console.log('shot', name);
    } catch (e) {
      console.log('FAIL', name, e.message);
    }
  }
  await browser.close();
})();
