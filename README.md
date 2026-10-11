# Softcode Magento SEO Toolkit

[![tests](../../actions/workflows/tests.yml/badge.svg)](../../actions/workflows/tests.yml)

Open-source fixes for the SEO and Core Web Vitals problems of **vanilla Magento 2 with the native Luma frontend**, without replacing it.

Hyvä solves Luma's frontend weight by replacing the whole frontend stack. Many shops can't or won't do that: they rely on native layouts, Knockout UI components and extensions built for Luma. This toolkit targets them. Every module plugs into native Magento and can be switched off per store.

Every fix is measured: a before/after run on a real vanilla store, not a claim.

## Modules

| Module | What it fixes | Status |
|---|---|---|
| `Softcode_LumaSpeed` | LCP image is fetched late (lazy-loaded first listing row, low-priority PDP photo, unprioritised CMS hero); full-size CMS images on phones; images without width/height; `calendar.css` blocks render on every page; 5 font preloads compete with the LCP image; layered navigation jumps on load (CLS); menu and tab ARIA roles; low-contrast swatches and colour-only breadcrumb links. Optional: per-page-type "used CSS" | 0.3, measured |
| `Softcode_Canonical` | no canonical tags at all by default; no canonical on home/CMS even when enabled; page 2+ canonicalised to page 1; category-path product URLs serve duplicates with a 200 | 0.1, verified |
| `Softcode_Robots` | one global `INDEX,FOLLOW` on every page incl. filters, sorting, search, cart, checkout, account, 404; empty `robots.txt`; no `/llms.txt` | 0.2, verified |
| `Softcode_StructuredData` | no JSON-LD anywhere; PDP has Luma microdata only; no breadcrumbs, organization or site search markup | 0.1, verified |
| `Softcode_Sitemap` | no sitemap exists until one is created in the admin; daily generation off; robots.txt doesn't reference it | 0.1, verified |
| `Softcode_Meta` | no meta description on the homepage, CMS pages, categories, search, cart unless typed in by hand | 0.1, verified |

### Lighthouse 13.5 (the version PageSpeed Insights runs), 2026-10-11

Local runs with PageSpeed's own settings (simulated Moto G Power / desktop), warm cache, all modules on:

| Page | SEO | Best practices | Accessibility | Performance desktop | Performance mobile |
|---|---|---|---|---|---|
| Home, PLP, PDP, CMS | 100 | 100 | 100 | 100 | ~80 (61-83, see below) |

Before the toolkit: SEO 92, best practices 92-96, accessibility 85-88, desktop 97-100.

Mobile performance is held back by JavaScript: ~600-800 ms total blocking time, from ~2.2 s (simulated) of Luma's RequireJS modules plus ~0.9 s of typical third-party scripts (tag manager, analytics, consent, chat; local stand-ins). The per-run spread (61-83) is machine noise in when that JavaScript delays the LCP paint. That is the open work item.

Measured and **not** worth it (kept as options, off by default):
- inlining Luma's full stylesheet: FCP unchanged, the 52 KB just moves into every HTML page;
- RequireJS bundles (`tools/luma-speed/bundle-*.mjs`): 200+ script requests down to 10-14 and everything works, but bigger evaluation tasks raised total blocking time (PDP 729 -> 883 ms) and PLP desktop CLS.

Measured and kept: per-page-type **used CSS** (`tools/luma-speed/usedcss-build.mjs`, setting *Inline used CSS per page type*): only 42-65 KB of Luma's 449 KB of render-blocking CSS is used per page type; inlining that and loading the rest without blocking improved FCP in every A/B round (mobile ~1.53 -> ~1.36 s, desktop 362 -> 322 ms). Verified pixel-identical against the original, also with the full stylesheets blocked. Rebuild after every static content deploy.

### Sitemap

Magento's own sitemap is sound once it exists: on the test store it lists 221 URLs (4 CMS, 30 categories, 187 products, 181 with images), and a sample of 74 of them were all 200, indexable and self-canonical with the modules above. So `Softcode_Sitemap` doesn't replace it; it registers one per store view on install, turns on Magento's daily generation and adds the `Sitemap:` line to robots.txt.

Known Magento behaviour: the `Sitemap:` URL in robots.txt drops a non-standard port (`Sitemap::_getStoreBaseDomain()` uses scheme + host only). Irrelevant on port 443, visible on dev setups like `:8443`.

Not covered yet: hreflang alternates for multi-language stores.

### SEO check, vanilla vs. with Canonical + Robots

| Page | Vanilla | With modules |
|---|---|---|
| Home, CMS | no canonical | self canonical |
| PLP | no canonical | self canonical |
| PLP filtered (price, color) or sorted | `INDEX,FOLLOW`, no canonical | `NOINDEX,FOLLOW`, canonical to the PLP |
| PLP page 2 | no canonical | `INDEX,FOLLOW`, canonical `?p=2` |
| PDP | no canonical | canonical without category path |
| PDP via category path | **200**, duplicate | **301** to the PDP, query string kept |
| Search, account, cart, checkout, 404 | `INDEX,FOLLOW` | `NOINDEX,FOLLOW` |
| robots.txt | empty | disallows checkout, customer, search, wishlist, compare, review lists, SID |

### Structured data, vanilla vs. with StructuredData

| Page | Vanilla | With module |
|---|---|---|
| Home | nothing | `Organization` (name, url, logo), `WebSite` with `SearchAction` |
| PLP | nothing | `BreadcrumbList` |
| PDP (simple) | Luma microdata: Product, Offer, AggregateRating | JSON-LD `Product` (Offer, images, rating converted from Magento's 0-100 to 5 stars) + `BreadcrumbList`; microdata removed |
| PDP (configurable) | Luma microdata: Product, Offer | JSON-LD `Product` (`AggregateOffer` when child prices differ) + `BreadcrumbList` |

Products opened by their own URL get breadcrumbs from their deepest active category. The organization name comes from General > Store Information (falls back to the store view name).

The Canonical and Robots modules switch on or fill in Magento's own mechanisms (catalog canonical settings, page robots, the robots.txt design setting); anything saved in the admin still wins.

## How LumaSpeed works

It rewrites the final page HTML inside Magento's full-page-cache plugin, so the optimised HTML is what gets cached and the work is done once per cache entry. It does not override core templates, so it keeps working with any theme based on Luma or Blank.

Settings: **Stores > Configuration > Softcode > Luma Speed**. Flush the full page cache after changing them.

## Measuring

[`benchmarks/`](benchmarks/) contains the scripts behind every number in this README and the raw results in `benchmarks/results/`:

- a vanilla Magento 2.4.6-p15 store with Luma sample data, production mode (Mark Shust docker-magento);
- Lighthouse with **real (devtools) throttling**: 4x CPU slowdown and slow 4G on mobile. Lighthouse's default simulated throttling makes Luma look far better than it is (94-100 mobile vs. 25-45 measured);
- key pages: home, PLP, PDP, cart, checkout, with a filled guest cart;
- every claim from an **interleaved A/B**: the setting is switched off and on per round, so machine noise hits both arms;
- SEO output checked deterministically per page type (`seo-check.mjs`, `jsonld-check.mjs`, `sitemap-check.mjs`).

## Results so far (LumaSpeed 0.2, 2026-10-10)

Vanilla 2.4.6-p15 + Luma sample data, made realistic: 2560px hero, tag manager + analytics, sync consent banner, chat widget (local stand-ins). Magento minify (JS/CSS/HTML) + move-JS-to-bottom on, critical CSS off. Mobile, real throttling, module off/on interleaved over 3 rounds, median (every round in `benchmarks/results/ab-module2-*.json`):

| Page | Perf | LCP | Page weight |
|---|---|---|---|
| Home | 71 -> 90 | 5.07s -> 2.56s | 1063 KB -> 757 KB |
| PLP | 83 -> 87 | 3.77s -> 2.58s | unchanged |
| PDP | 89 -> 87 | 2.66s -> 2.44s | unchanged |
| Cart | 81 -> 82 | 3.02s -> 2.52s | unchanged |
| Checkout | 55 -> 55 | no change (not targeted yet) | unchanged |

LCP improved in every round on home, PLP and cart. Desktop is unchanged (already 100 on home/PDP/cart).

Findings that shape the roadmap:
- Magento's own `dev/css/use_css_critical_path` breaks Luma: CLS 0.7-1.26 on desktop on every page. Keep it off.
- Checkout has CLS 0.34 (mobile) / 0.42 (desktop) from Knockout rendering the shipping form after first paint. Not caused by move-JS-to-bottom, HTML minify or this module (all A/B-tested); JS/CSS minify not yet isolated.
- Checkout HTML is 605 KB before any JS/CSS (embedded checkout config JSON).
- Lazy-loading content images that are on screen hurts: the first 3 CMS images stay eager.
- Total blocking time is untouched: LumaSpeed doesn't change Luma's JavaScript yet.

Lessons from building it (all fixed, all covered by unit tests):
- PageCache's `BuiltinPlugin` stores the page in its own `afterRenderResult`; an HTML-rewriting plugin must sort below it or only the first, uncached visitor gets the result.
- A responsive `<link rel=preload>` placed before `<meta name=viewport>` is resolved against a 980px viewport, so phones download a desktop-size image too.
- `sizes="100vw"` on content images enlarges small promo tiles on phones; cap it at the original width.

## Requirements and status

Magento Open Source / Adobe Commerce 2.4.6+, PHP 8.1-8.3, native Luma (or a theme based on Luma/Blank).

Early versions. The modules are verified on one store: 2.4.6-p15 with Luma sample data. The unit tests also run against the Magento 2.4.8 framework. Try them on staging first.

## Install

Each module works on its own; install only the ones you need. Either copy them into `app/code`:

```bash
mkdir -p app/code/Softcode && cp -r modules/LumaSpeed modules/Canonical modules/Robots modules/StructuredData modules/Sitemap modules/Meta app/code/Softcode/
```

or add this repository's `modules/*` as a Composer path repository and `composer require softcode/module-luma-speed softcode/module-canonical ...`. Then:

```bash
bin/magento module:enable Softcode_LumaSpeed Softcode_Canonical Softcode_Robots Softcode_StructuredData Softcode_Sitemap Softcode_Meta
bin/magento setup:upgrade
bin/magento setup:di:compile                                   # production mode
bin/magento setup:static-content:deploy -f                     # production mode: LumaSpeed ships a RequireJS mixin
bin/magento cache:flush
```

Settings live under **Stores > Configuration > Softcode**.

### Optional: used CSS per page type

Needs Node 22+ and Chrome on a machine that can reach the store:

```bash
cd tools/luma-speed && npm install
STORE_URL=https://your-store.example node usedcss-build.mjs   # writes usedcss/*.json
# copy usedcss/ to pub/static/frontend/<Vendor>/<theme>/<locale>/Softcode_LumaSpeed/usedcss/
# then enable Stores > Configuration > Softcode > Luma Speed > CSS > Inline used CSS per page type
```

Rebuild and copy again after every `setup:static-content:deploy` (which empties `pub/static`). Without the files the setting changes nothing. Check the result visually: `benchmarks/lh13/screens.mjs` with `BLOCK_FULL_CSS=1` shows the first render with only the used CSS.

## Tests

The unit tests need Magento's framework classes; `composer.json` takes them from the [Mage-OS mirror](https://mirror.mage-os.org/), so no Adobe access keys are needed:

```bash
composer install
vendor/bin/phpunit
```

CI runs the same on PHP 8.2 and 8.3 (`.github/workflows/tests.yml`).

## License

MIT, see [LICENSE](LICENSE).
