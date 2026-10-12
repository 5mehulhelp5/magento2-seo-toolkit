# Benchmarks

Everything used to produce the numbers in the main README: a vanilla store, the changes applied to it, and the scripts that measure them. Results of the runs quoted there are in `results/`.

## Setup

- **Store:** Magento 2.4.6-p15 + Luma sample data, production mode, [Mark Shust's docker-magento](https://github.com/markshust/docker-magento) in WSL2 (Ubuntu), served at `https://magento-seo.test:8443` (the scripts map that host to 127.0.0.1 themselves; no hosts entry needed).
  - 2.4.6 needs `composer config audit.block-insecure false` before `composer install`: it depends on `league/flysystem` 2.x, which has a published security advisory.
- **Measuring:** Node 18+, Chrome, `npm install` in this folder (Lighthouse 10.4).
- **Paths:** bash scripts read `STORE_DIR` (docker-magento project), `TOOLKIT_DIR` (repo checkout) and `BENCH_DIR` (this folder); PowerShell scripts read `WSL_DISTRO` and `STORE_DIR`. `CHROME_PATH` overrides Chrome detection.

## Method

- Lighthouse with **real throttling** (`THROTTLE=devtools`): Lighthouse's default simulated throttling showed vanilla Luma at 94-100 on mobile; with real 4x CPU slowdown and slow 4G it is 25-45.
- Key pages: home, PLP, PDP, cart and checkout, with a guest cart filled by `cart-session.mjs`.
- **Interleaved A/B** for every claim: `ab.ps1` / `ab-setting.ps1` switch one config value off and on per round (a cache flush, no redeploy), so background noise on the machine hits both arms. Runs measured an hour apart were not comparable: the same unchanged PDP scored 83 and 48.
- Don't use the machine while measuring.

## Scripts

| Script | What it does |
|---|---|
| `measure.mjs` | Lighthouse (median of `RUNS`) + raw SEO tags per page; saves after every page |
| `stage.ps1` | measures the key pages for one named stage with a filled cart and real throttling |
| `ab.ps1`, `ab-setting.ps1` | interleaved off/on rounds of the LumaSpeed module / any config value |
| `ab-compare.mjs`, `ab-setting-compare.mjs`, `compare.mjs` | summaries, including every individual round |
| `diag.mjs`, `cls.mjs` | why a run is slow: LCP element and phases, request timeline, shifting elements |
| `seo-check.mjs` | status, redirect, canonical and robots for 15 page types, plus robots.txt |
| `jsonld-check.mjs` | JSON-LD per page checked against Google's required fields, leftover microdata |
| `sitemap-check.mjs` | crawls `/sitemap.xml` and checks its URLs are 200, indexable and self-canonical |
| `store-setup/make-3p.mjs`, `apply.sh` | "realistic" store: 2560px hero and local stand-ins for tag manager, analytics, consent banner and chat |
| `store-setup/native-config.sh` | Magento's own frontend settings (minify, move JS, critical CSS) |
| `store-setup/*-module*.sh`, `verify-*.sh` | install the toolkit modules into the store and check their output |
| `store-setup/create-da-store.php` | second store view `da` (Danish, da_DK) on `https://da.magento-seo.test:8443/` for the hreflang test. nginx must pass the store code per host: `set $run_code default; if ($host = "da.magento-seo.test") { set $run_code da; }` at server level and `fastcgi_param MAGE_RUN_CODE $run_code; fastcgi_param MAGE_RUN_TYPE store;` in the PHP location |
| `lh13/hreflang-lh.mjs` | Lighthouse SEO, `hreflang` and `canonical` audits on every store host |
