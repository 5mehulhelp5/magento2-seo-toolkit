# Changelog

All notable changes to this project. Toolkit versions are git tags; each module carries its own version in its `composer.json`.
Every measured claim below is backed by a script and raw results in [`benchmarks/`](benchmarks/).

## [0.5.0] - 2026-10-12

### Added
- **`Softcode_Hreflang` 0.1.0**: `rel="alternate" hreflang` links across store views on the homepage, CMS pages, categories (page 2+ keeps `?p=N`) and products.
  - Each store view's own URL from its URL rewrites (own URL key and suffix), the same URL the canonical uses.
  - Store views where the page doesn't exist are left out: product disabled, not visible or not in the website; category inactive or outside the root category; CMS page not assigned. A page that exists in one store view only gets no hreflang.
  - Self reference, reciprocal sets, `x-default` (website default store view, configurable), duplicate hreflang values resolved.
  - Value from the store view locale (`da_DK` -> `da-DK`, or language only), per-store-view override (`es-419`, `en-GB`); all websites or the current website only.
  - Verified on a second store view (Danish, own host): Lighthouse 13.5 `hreflang` and `canonical` audits pass, SEO 100 on both hosts.
- `benchmarks/lh13/hreflang-lh.mjs` and `benchmarks/store-setup/create-da-store.php`.

### Known limitations
- CMS pages are matched by identifier; per-language pages with different identifiers (`about-us` / `om-os`) are not linked.
- No hreflang in the XML sitemap (the head links are equivalent for Google).

## [0.4.0] - 2026-10-12

### Fixed
- **`Softcode_LumaSpeed` 0.5.0**: checkout.
  - Magento enforces its Content Security Policy on checkout. Deferred stylesheets now use a small activator script instead of `onload="..."` handlers, and inline scripts carry Magento's CSP nonce on uncached pages.
  - Layout shift: the checkout stays invisible while Magento's `#checkout-loader` is present (CSS `:has()`, no JavaScript). CLS 0.235 / 0.408 -> 0.012 / 0.000 (mobile / desktop).
  - ARIA: steps list items lose `role="presentation"`, and the order summary dialog gets an accessible name.
  - Third-party gating no longer treats `scroll` as interaction, because the checkout scrolls by script.
  - Checkout Lighthouse 13.5 performance 48-66 -> 89 (mobile), 75-78 -> 97 (desktop); accessibility and best practices 100.

## [0.3.0] - 2026-10-12

### Added
- **`Softcode_LumaSpeed` 0.4.0**: third-party script gating.
  - Two admin lists of URL fragments: "load after consent" (tracking) and "load on first interaction" (chat, widgets).
  - Consent from a generic hook, Magento's cookie restriction mode, Cookiebot, CookieYes or OneTrust.
  - Gated scripts start in document order. Embed snippets waiting for `window` `load` still run, and Magento's own scripts are never touched. Both lists are empty by default.
  - Interleaved A/B, mobile median total blocking time: home 732 -> 151 ms, PLP 891 -> 280 ms, PDP 1028 -> 614 ms, CMS 865 -> 182 ms.

## [0.2.0] - 2026-10-11

### Added
- **`Softcode_Meta` 0.1.0**: meta description fallback for categories, CMS pages, the homepage and other pages without one.
- **`Softcode_Robots` 0.2.0**: `/llms.txt` generated from the store.
- **`Softcode_LumaSpeed` 0.3.0**:
  - Missing image width/height filled in from the files.
  - Menu ARIA roles, and an `aria-owns` tablist for Luma's tabs.
  - Swatch contrast, and breadcrumb links distinguishable without colour.
  - Layered-navigation filters stay closed until the accordion initialises (PLP desktop CLS 0.108 -> 0.010).
  - Optional per-page-type "used CSS" (FCP better in every A/B round).
  - Optional RequireJS bundles (measured worse; off by default).
- Tools to build used CSS and bundles (`tools/luma-speed/`), plus Lighthouse 13.5 benchmark scripts.

### Result
- Lighthouse 13.5: SEO, best practices and accessibility 100, and desktop performance 100, on home, PLP, PDP and CMS.

## [0.1.0] - 2026-10-11

### Added
- **`Softcode_LumaSpeed` 0.2.0**:
  - LCP image priority and preload.
  - The first listing row loads eagerly.
  - Responsive CMS images.
  - `calendar.css` is deferred and font preloads are trimmed.
  - Measured on mobile: home LCP 5.07 -> 2.56 s, PLP 3.77 -> 2.58 s.
- **`Softcode_Canonical` 0.1.0**:
  - Catalog canonicals on by default.
  - Canonicals on the homepage and CMS pages.
  - Page 2+ gets a self-referencing canonical.
  - Category-path product URLs 301 to the product URL.
- **`Softcode_Robots` 0.1.0**:
  - `NOINDEX,FOLLOW` on filtered and sorted listings, search, account, cart, checkout and 404.
  - A default `robots.txt`.
- **`Softcode_StructuredData` 0.1.0**:
  - JSON-LD `Product` and `BreadcrumbList` on PDP; `BreadcrumbList` on PLP.
  - `Organization` and `WebSite` with `SearchAction` on the homepage.
  - Luma's microdata is removed.
- **`Softcode_Sitemap` 0.1.0**:
  - A sitemap per store view on install.
  - Daily generation turned on.
  - A `Sitemap:` line in robots.txt.
- Unit tests and CI (PHP 8.2 / 8.3, Magento framework from the Mage-OS mirror).
- Benchmark scripts and raw results.

[0.5.0]: https://github.com/DritonCa/magento2-seo-toolkit/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/DritonCa/magento2-seo-toolkit/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/DritonCa/magento2-seo-toolkit/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/DritonCa/magento2-seo-toolkit/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/DritonCa/magento2-seo-toolkit/releases/tag/v0.1.0
