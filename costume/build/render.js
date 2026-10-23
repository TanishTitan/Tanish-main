// Renders the printable PDF and the DTF artwork PNGs with Chromium.
// usage: node render.js <buildDir> <distDir>
const path = require('path');
const { chromium } = require('/opt/node22/lib/node_modules/playwright');

const [buildDir, distDir] = process.argv.slice(2);
const proxy = process.env.HTTPS_PROXY ? { server: process.env.HTTPS_PROXY } : undefined;

async function ready(page) {
  await page.waitForLoadState('networkidle');
  const ok = await page.evaluate(async () => {
    await document.fonts.load("40px 'Yuji Boku'", '死起回生');
    await document.fonts.load('800 20px Vazirmatn', 'سلام');
    await document.fonts.load("700 20px 'JetBrains Mono'", 'cm');
    await document.fonts.ready;
    return document.fonts.check("40px 'Yuji Boku'", '死');
  });
  if (!ok) throw new Error('fonts did not load');
}

(async () => {
  const browser = await chromium.launch({ proxy });
  // 1) printable patterns PDF
  {
    const page = await browser.newPage();
    await page.goto('file://' + path.join(buildDir, 'print.html'));
    await ready(page);
    await page.pdf({ path: path.join(distDir, 'files', 'tanish-patterns-1to1.pdf'), format: 'A4', printBackground: true, preferCSSPageSize: true, scale: 1 });
    console.log('pdf ok');
    await page.goto('file://' + path.join(buildDir, 'mom.html'));
    await ready(page);
    await page.pdf({ path: path.join(distDir, 'files', 'tanish-mom-sewing-sheet.pdf'), format: 'A4', printBackground: true, preferCSSPageSize: true });
    console.log('mom pdf ok');
    await page.close();
  }
  // 2) DTF artwork, 300 dpi transparent PNGs (+ small previews on black)
  const arts = ['dtf-back', 'dtf-sleeve', 'dtf-panel', 'dtf-gangsheet'];
  for (const a of arts) {
    const ctx = await browser.newContext({ deviceScaleFactor: 300 / 96, viewport: { width: 2400, height: 3000 } });
    const page = await ctx.newPage();
    await page.goto('file://' + path.join(buildDir, a + '.html'));
    await ready(page);
    await page.locator('#art').screenshot({ path: path.join(distDir, 'files', a + '.png'), omitBackground: true });
    await ctx.close();
    const ctx2 = await browser.newContext({ deviceScaleFactor: 0.42, viewport: { width: 2400, height: 3000 } });
    const p2 = await ctx2.newPage();
    await p2.goto('file://' + path.join(buildDir, a + '.html'));
    await ready(p2);
    await p2.evaluate((bg) => { document.body.style.background = bg; document.getElementById('art').style.background = bg; }, a === 'dtf-panel' ? '#8e1016' : a === 'dtf-gangsheet' ? '#6f6a6a' : '#121112');
    await p2.locator('#art').screenshot({ path: path.join(distDir, 'img', a + '-preview.png') });
    await ctx2.close();
    console.log(a, 'ok');
  }
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
