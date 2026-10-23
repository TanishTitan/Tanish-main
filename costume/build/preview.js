const path = require('path');
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const [dist, out] = process.argv.slice(2);
const fs = require('fs');
(async () => {
  const body = fs.readFileSync(path.join(dist, 'index.html'), 'utf8');
  const page = `<!doctype html><html><head><meta charset=utf8><meta name=viewport content="width=device-width,initial-scale=1,viewport-fit=cover"><style>:root{color-scheme:light}body{margin:0;font:14px system-ui}img{max-width:100%}[hidden]{display:none!important}</style></head><body>${body}</body></html>`;
  fs.writeFileSync(path.join(dist, '_preview.html'), page);
  const b = await chromium.launch({ proxy: process.env.HTTPS_PROXY ? { server: process.env.HTTPS_PROXY } : undefined });
  for (const [name, w, h, scheme] of [['desk-dark', 1280, 900, 'dark'], ['phone-light', 390, 844, 'light']]) {
    const ctx = await b.newContext({ viewport: { width: w, height: h }, colorScheme: scheme, deviceScaleFactor: 1 });
    const p = await ctx.newPage();
    const errs = [];
    p.on('pageerror', e => errs.push(e.message)); p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
    await p.goto('file://' + path.join(dist, '_preview.html'));
    await p.waitForLoadState('networkidle'); await p.evaluate(() => document.fonts.ready);
    const ov = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    console.log(name, 'overflowX', ov, 'height', await p.evaluate(() => document.body.scrollHeight), errs.slice(0, 5));
    await p.screenshot({ path: path.join(out, name + '.png'), fullPage: true });
    await ctx.close();
  }
  await b.close();
})();
