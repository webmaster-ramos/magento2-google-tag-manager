# Changelog

All notable changes to this extension are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-10-19

The package metadata and the README now link to the extension page, the
documentation and the source repository; the extension behaves exactly as
before.

### Changed

- The package metadata (composer.json) links to the extension page, the
  documentation and the source repository, and names
  contact@webmaster-ramos.com as the support and author address.
- The README links to the extension page, the article on what the extension
  is for and the comparison with Yireo GoogleTagManager2.

### Upgrade notes

- Nothing to do beyond the usual update: no setting, database table or code
  changed.

## [1.0.0] - 2026-10-09

First public release.

### Added

- Your Google Tag Manager container on every storefront page, Luma and Hyvä
  Theme alike, with the noscript fallback right after the opening body tag.
  Full-page-cache friendly.
- Staging guard. The container loads only when the store's base URL host is on
  your list of production hosts, so staging and local copies of the production
  database send no hits and no test orders. An empty list loads nothing, and
  the module cannot be enabled without at least one host.
- Strict-CSP checkout. On pages that carry a Content Security Policy nonce –
  the checkout since Magento 2.4.7 – the nonce is set on gtm.js and pushed to
  the dataLayer as cspNonce, ready for Custom HTML tags.
- Your tag hosts in the storefront Content Security Policy. List the hosts
  your tags load from or send to under "Additional Tag Hosts"; they are added
  to the policy on every storefront page, the checkout included.
- Google Consent Mode v2. Defaults are set before the container loads: every
  signal denied in the EEA, the UK and Switzerland, granted or denied
  elsewhere as you choose, with optional ads data redaction and URL
  passthrough. Updates come from Magento's own cookie notice on Luma and Hyvä
  Theme, or are left to your consent platform.
- Your own gtm.js address for server-side Tag Manager or Google tag gateway,
  under "GTM Script Base URL".
- Validation on save for the container ID, the host lists and the base URL; a
  bare `*` or a CSP keyword is refused.
