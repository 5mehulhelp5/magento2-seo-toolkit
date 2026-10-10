// Generates local stand-ins for typical third-party scripts on a Magento shop.
// Sizes/main-thread costs approximate what these vendors ship (measured on desktop,
// Lighthouse's 4x CPU throttle multiplies the cost). Nothing leaves the machine.
import fs from 'node:fs';

const out = process.argv[2] || 'demo-3p';
fs.mkdirSync(out, { recursive: true });

// Parse cost comes from real code volume; execution cost from a timed busy loop.
function script(name, kb, workMs, body) {
  const filler = [];
  let i = 0;
  while (filler.join('').length < kb * 1024) {
    filler.push(`function _${name}_${i}(a,b){var c=[a,b,${i}];for(var j=0;j<c.length;j++){c[j]=String(c[j]).split('').reverse().join('')}return c.join('|')}\n`);
    i++;
  }
  const work = `(function(){var t=performance.now();while(performance.now()-t<${workMs}){Math.sqrt(Math.random())}})();\n`;
  fs.writeFileSync(`${out}/${name}.js`, `/* local stand-in: ${name}, ~${kb} KB, ~${workMs} ms main-thread */\n${filler.join('')}${work}${body}\n`);
}

// Tag manager (GTM-like): async in head, then pulls analytics.
script('tag-manager', 110, 60, `
var s=document.createElement('script');s.async=true;s.src=document.currentScript.src.replace('tag-manager','analytics');document.head.appendChild(s);`);
script('analytics', 90, 40, '');

// Consent banner (Cookiebot/OneTrust-like): synchronous in head, as these vendors recommend.
script('consent', 60, 30, `
document.addEventListener('DOMContentLoaded',function(){
  var b=document.createElement('div');
  b.id='demo-consent';
  b.style.cssText='position:fixed;left:0;right:0;bottom:0;z-index:9999;background:#fff;box-shadow:0 -2px 12px rgba(0,0,0,.2);padding:20px;font:15px/1.5 sans-serif';
  b.innerHTML='<p style="margin:0 0 12px">We use cookies to personalise content and ads, to provide social media features and to analyse our traffic. We also share information about your use of our site with our social media, advertising and analytics partners.</p><button onclick="this.parentNode.remove()">Accept all</button> <button onclick="this.parentNode.remove()">Necessary only</button>';
  document.body.appendChild(b);
});`);

// Chat widget: loaded after window load, as most vendors do.
script('chat', 220, 80, `
var l=document.createElement('div');l.style.cssText='position:fixed;right:20px;bottom:90px;width:60px;height:60px;border-radius:50%;background:#1979c3;z-index:9998';document.body.appendChild(l);`);

const includes = `<link  rel="stylesheet" type="text/css"  media="all" href="{{MEDIA_URL}}styles.css" />
<script src="{{MEDIA_URL}}demo-3p/consent.js"></script>
<script async src="{{MEDIA_URL}}demo-3p/tag-manager.js"></script>
<script>window.addEventListener('load',function(){var s=document.createElement('script');s.src='{{MEDIA_URL}}demo-3p/chat.js';document.body.appendChild(s);});</script>`;
fs.writeFileSync(`${out}/head-includes.html`, includes);
for (const f of fs.readdirSync(out)) console.log(f, fs.statSync(`${out}/${f}`).size);
