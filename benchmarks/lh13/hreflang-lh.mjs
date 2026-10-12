// Lighthouse 13.5 SEO category on every store host: SEO score plus the hreflang and canonical audits.
// Usage: node hreflang-lh.mjs
// env: STORE_URLS (comma list, default https://magento-seo.test:8443,https://da.magento-seo.test:8443),
//      RESOLVE_IP (default 127.0.0.1; the hosts are mapped to it inside Chrome)
import lighthouse from 'lighthouse';
import * as chromeLauncher from 'chrome-launcher';

const BASES = (process.env.STORE_URLS || 'https://magento-seo.test:8443,https://da.magento-seo.test:8443')
  .split(',').map(u => u.trim().replace(/\/$/, ''));
const IP = process.env.RESOLVE_IP || '127.0.0.1';
const PATHS = ['/', '/women/tops-women/jackets-women.html', '/gear/bags.html?p=2', '/joust-duffle-bag.html', '/about-us'];
const chrome = await chromeLauncher.launch({ chromePath: process.env.CHROME_PATH,
  chromeFlags: ['--headless=new', `--host-resolver-rules=${BASES.map(b => `MAP ${new URL(b).hostname} ${IP}`).join(',')}`,
    '--ignore-certificate-errors', '--no-first-run'] });
for (const base of BASES) {
  for (const path of PATHS) {
    const { lhr } = await lighthouse(base + path, { port: chrome.port, onlyCategories: ['seo'], output: 'json', logLevel: 'error' });
    const a = id => lhr.audits[id];
    const fail = Object.values(lhr.audits).filter(x => x.score === 0).map(x => x.id);
    console.log(`${base}${path}  SEO ${Math.round(lhr.categories.seo.score * 100)}  hreflang ${a('hreflang').score}`
      + `  canonical ${a('canonical').score}${fail.length ? '  FAIL: ' + fail.join(',') : ''}`);
  }
}
await chrome.kill();
