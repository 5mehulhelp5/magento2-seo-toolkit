// "Used CSS" per page type: loads sample pages at phone and desktop width with Chrome's CSS coverage on and
// keeps only the rules those pages used from Luma's render-blocking stylesheets. @font-face/@keyframes are
// always kept; @media blocks keep their used rules; styles-l keeps its media query; relative url()s become absolute.
// Output: usedcss/<type>.css + usedcss/manifest.json   Usage: node usedcss-build.mjs
import fs from 'node:fs';
import puppeteer from 'puppeteer-core';
import * as chromeLauncher from 'chrome-launcher';

const BASE = (process.env.STORE_URL || 'https://magento-seo.test:8443').replace(/\/$/, ''), HOST = new URL(BASE).hostname;
const PAGES = {
  home: ['/'], cms: ['/about-us'], search: ['/catalogsearch/result/?q=bag'],
  plp: ['/women/tops-women/jackets-women.html', '/gear/bags.html'],
  pdp: ['/joust-duffle-bag.html', '/olivia-1-4-zip-light-jacket.html'],
};
const PAGE_TYPES = { 'cms-index-index': 'home', 'cms-page-view': 'cms', 'catalog-category-view': 'plp',
  'catalog-product-view': 'pdp', 'catalogsearch-result-index': 'search' };
const VIEWPORTS = [{ width: 412, height: 823, isMobile: true }, { width: 1366, height: 900 }];
const SHEETS = [/\/css\/styles-m(\.min)?\.css/, /\/css\/styles-l(\.min)?\.css/, /\/media\/styles\.css/];
const MEDIA_OF = { 'styles-l': 'screen and (min-width: 768px)' };

// Splits CSS into top-level items: {start, end, kind: 'rule'|'block-at'|'at', children?}
function parse(css, from = 0, to = css.length) {
  const items = [];
  let i = from;
  while (i < to) {
    while (i < to && /\s/.test(css[i])) i++;
    if (i >= to) break;
    if (css.startsWith('/*', i)) { const e = css.indexOf('*/', i + 2); i = e < 0 ? to : e + 2; continue; }
    const start = i;
    let depth = 0, quote = null;
    for (; i < to; i++) {
      const c = css[i];
      if (quote) { if (c === '\\') i++; else if (c === quote) quote = null; continue; }
      if (c === '"' || c === "'") { quote = c; continue; }
      if (c === ';' && depth === 0) { i++; break; }                 // @charset/@import
      if (c === '{') depth++;
      else if (c === '}') { depth--; if (depth === 0) { i++; break; } }
    }
    const text = css.slice(start, i);
    const open = text.indexOf('{');
    if (text.startsWith('@') && open > -1 && /^@(media|supports|document|layer|container)/.test(text)) {
      items.push({ start, end: i, kind: 'block-at', header: text.slice(0, open), children: parse(css, start + open + 1, i - 1) });
    } else {
      items.push({ start, end: i, kind: text.startsWith('@') ? 'at' : 'rule' });
    }
  }
  return items;
}

const used = (item, ranges) => ranges.some(r => r.start < item.end && r.end > item.start);
function keep(css, items, ranges) {
  let out = '';
  for (const it of items) {
    if (it.kind === 'block-at') {
      const inner = keep(css, it.children, ranges);
      if (inner) out += `${it.header}{${inner}}`;
    } else if (it.kind === 'at') {
      if (/^@(font-face|keyframes|-webkit-keyframes|charset)/.test(css.slice(it.start, it.start + 20))) out += css.slice(it.start, it.end);
    } else if (used(it, ranges)) out += css.slice(it.start, it.end);
  }
  return out;
}
const absolutize = (css, sheetUrl) => css.replace(/url\(\s*(['"]?)([^'")]+)\1\s*\)/gi, (m, q, ref) =>
  /^(data:|https?:|\/\/|\/|#)/i.test(ref) ? m : `url(${q}${new URL(ref, sheetUrl).href}${q})`);

const chrome = await chromeLauncher.launch({ chromePath: process.env.CHROME_PATH,
  chromeFlags: ['--headless=new', `--host-resolver-rules=MAP ${HOST} ${process.env.RESOLVE_IP || '127.0.0.1'}`, '--ignore-certificate-errors', '--no-first-run'] });
const browser = await puppeteer.connect({ browserURL: `http://127.0.0.1:${chrome.port}` });
fs.mkdirSync('usedcss', { recursive: true });
// defer: path fragments of the stylesheets the module loads without blocking once the used CSS is inline
const manifest = { builtAt: new Date().toISOString(), pageTypes: PAGE_TYPES, defer: ['css/styles-m.', 'css/styles-l.', 'media/styles.css'], types: {} };

for (const [type, paths] of Object.entries(PAGES)) {
  const sheets = new Map();   // url -> {text, ranges[]}
  for (const path of paths) for (const viewport of VIEWPORTS) {
    const page = await browser.newPage();
    await page.setViewport(viewport);
    await page.coverage.startCSSCoverage();
    await page.goto(BASE + path, { waitUntil: 'networkidle0', timeout: 90000 });
    await new Promise(r => setTimeout(r, 2500));
    for (const entry of await page.coverage.stopCSSCoverage()) {
      if (!SHEETS.some(re => re.test(entry.url))) continue;
      const s = sheets.get(entry.url) ?? { text: entry.text, ranges: [] };
      s.ranges.push(...entry.ranges);
      sheets.set(entry.url, s);
    }
    await page.close();
  }
  let css = '', total = 0;
  // cascade order matters: concatenate in the page's stylesheet order (styles-m, styles-l, media/styles.css),
  // not in the order coverage reports them, or earlier sheets override later ones
  const ordered = [...sheets].sort(([a], [b]) => SHEETS.findIndex(re => re.test(a)) - SHEETS.findIndex(re => re.test(b)));
  for (const [url, { text, ranges }] of ordered) {
    total += text.length;
    let part = absolutize(keep(text, parse(text), ranges), url);
    const media = Object.entries(MEDIA_OF).find(([k]) => url.includes(k))?.[1];
    if (media && part) part = `@media ${media}{${part}}`;
    css += part;
  }
  fs.writeFileSync(`usedcss/${type}.json`, JSON.stringify({ css }));   // .json: Magento's CSS minification leaves it alone
  manifest.types[type] = { bytes: css.length, sourceBytes: total };
  console.log(`${type.padEnd(7)} used ${Math.round(css.length / 1024)} KB of ${Math.round(total / 1024)} KB (${sheets.size} stylesheets)`);
}
fs.writeFileSync('usedcss/manifest.json', JSON.stringify(manifest));
await browser.disconnect();
await chrome.kill();
