// Creates a guest session with one product in the cart and prints its Cookie header.
// Usage: node cart-session.mjs [/product-url.html]
import https from 'node:https';

const BASE = new URL(process.env.STORE_URL || 'https://magento-seo.test:8443'), HOST = BASE.hostname;
const productPath = process.argv[2] || '/joust-duffle-bag.html';
const jar = {};

function request(method, path, body) {
  const headers = { Host: BASE.host, Cookie: Object.entries(jar).map(([k, v]) => `${k}=${v}`).join('; ') };
  if (body) Object.assign(headers, { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': Buffer.byteLength(body) });
  return new Promise((resolve, reject) => {
    const req = https.request({ method, host: process.env.RESOLVE_IP || '127.0.0.1', port: BASE.port || 443, path, servername: HOST, headers, rejectUnauthorized: false }, res => {
      for (const c of res.headers['set-cookie'] || []) {
        const [kv] = c.split(';'); const i = kv.indexOf('=');
        jar[kv.slice(0, i)] = kv.slice(i + 1);
      }
      let data = ''; res.on('data', d => data += d); res.on('end', () => resolve({ status: res.statusCode, body: data }));
    });
    req.on('error', reject); if (body) req.write(body); req.end();
  });
}

const page = await request('GET', productPath);
const productId = page.body.match(/name="product" value="(\d+)"/)?.[1];
if (!productId) throw new Error(`no product id on ${productPath}`);
jar.form_key ||= Math.random().toString(36).slice(2, 18);
const add = await request('POST', '/checkout/cart/add/',
  new URLSearchParams({ product: productId, qty: '1', form_key: jar.form_key }).toString());
const cart = await request('GET', '/checkout/cart/');
const inCart = /cart-item|class="cart item"/.test(cart.body) && !/You have no items in your shopping cart/.test(cart.body);
if (!inCart) throw new Error(`add-to-cart failed (status ${add.status})`);
process.stdout.write(Object.entries(jar).map(([k, v]) => `${k}=${v}`).join('; '));
