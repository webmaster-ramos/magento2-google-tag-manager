# WebRamos_GoogleTagManager

Google Tag Manager for Adobe Commerce and Magento Open Source, Luma and Hyvä:
the container on every storefront page, safe on staging copies of your
database, working on the strict-CSP checkout, with Google Consent Mode v2 set
before anything loads.

## Why this exists

Magento Open Source has no Tag Manager integration. The usual workaround –
pasting the GTM snippet into Content > Design > Configuration > HTML Head –
has three problems that only show up later:

- **Staging sends production data.** Staging and local environments run on
  copies of the production database, so the snippet comes along with the data.
  Test visits and test orders land in your real Analytics and Ads accounts.
- **The checkout blocks it.** Since 2.4.7 Magento runs the checkout under a
  strict Content Security Policy with inline scripts switched off. A pasted
  snippet carries no nonce, and the tags inside the container – Custom HTML
  tags above all – are blocked exactly where conversions happen.
- **Consent comes too late, or not at all.** Google Consent Mode v2 requires
  the default consent state to be set before the container loads. A pasted
  snippet does not do that.

This module replaces the paste with a loader that handles all three. It is
deliberately small: it loads your container and sets consent; your tags,
triggers and variables stay in Tag Manager.

## Features

- **Container on every storefront page** – in `<head>`, with the `<noscript>`
  fallback right after `<body>`, on Luma and Hyvä alike. Full-page-cache
  friendly.
- **Staging guard.** The container loads only when the store's base URL host is
  on your list of production hosts. A copy of the database on
  `staging.example.com` or `shop.local` sends nothing – no hits, no test orders.
  An empty list loads nothing anywhere, so the guard cannot be forgotten.
- **Strict-CSP checkout.** On pages that carry a CSP nonce (the checkout), the
  nonce is set on `gtm.js`, as in Google's nonce-aware snippet, and pushed to
  the dataLayer as `cspNonce` for your Custom HTML tags.
- **Your tag hosts in the CSP, from the admin.** List the hosts your tags load
  from or send to (for example `connect.facebook.net`, `*.clarity.ms`); they are
  added to the storefront policy, checkout included. No code change, no custom
  module per pixel.
- **Google Consent Mode v2.** Defaults set before `gtm.js`: every signal denied
  in the EEA, the UK and Switzerland, your choice elsewhere. Updates come from
  Magento's own cookie notice (Luma and Hyvä), or you leave them to your consent
  platform. Ads data redaction and URL passthrough are one switch each.
- **Your own `gtm.js` URL.** Serve the container from your domain – server-side
  Tag Manager or Google tag gateway – by changing one field.
- **Validated on save.** Container IDs, hosts and URLs are checked when you save;
  a CSP keyword or a bare `*` cannot slip into the checkout policy.

What it does **not** do: it pushes no ecommerce events (`view_item`,
`add_to_cart`, `purchase` …) and sends nothing server-side. If you need a GA4
ecommerce data layer, use a data-layer module for that – and make sure only one
module loads the container.

## Requirements

- Adobe Commerce or Magento Open Source 2.4.7 – 2.4.9
- PHP 8.1 – 8.5
- Storefronts: Luma, Hyvä Theme (with native Magento checkout)
- A Google Tag Manager web container

## Installation

```bash
composer require webmaster-ramos/module-google-tag-manager
bin/magento module:enable WebRamos_GoogleTagManager
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Configuration

Stores > Configuration > Webmaster Ramos > Google Tag Manager

### General

| Field | Scope | Default | What it does |
| --- | --- | --- | --- |
| Enabled | store view | No | Loads the container on this scope. Needs a container ID and at least one allowed host. |
| Container ID | store view | – | `GTM-XXXXXXX`, from your Tag Manager workspace. |
| Allowed Hosts | default | – | Production hosts, one per line. The comment under the field lists the base URL hosts your installation has. Do not list staging or local hosts. |
| GTM Script Base URL | website | `https://www.googletagmanager.com` | Where `gtm.js` and `ns.html` are served from. Change it only for server-side Tag Manager or Google tag gateway. |

### Content Security Policy

| Field | Scope | Default | What it does |
| --- | --- | --- | --- |
| Additional Tag Hosts | website | – | Hosts your tags load from or send to, one per line; a leading `*.` covers subdomains. Added to `script-src`, `connect-src` and `img-src`. |
| Expose Nonce to Custom HTML Tags | website | Yes | Pushes `cspNonce` to the dataLayer on pages that have a nonce. |

### Consent Mode v2

| Field | Scope | Default | What it does |
| --- | --- | --- | --- |
| Consent Source | store view | Off | Off: no consent commands. Magento cookie notice: defaults, then granted when the visitor accepts Magento's cookie notice. External consent platform: defaults only; the platform sends updates. |
| Default Outside EEA, UK and Switzerland | store view | Granted | Starting state for visitors elsewhere. Inside those regions every signal always starts denied. |
| Wait for Update (ms) | store view | 500 | How long tags wait for a consent update before firing. |
| Redact Ads Data | store view | Yes | No ad click identifiers are sent while ad storage is denied. |
| URL Passthrough | store view | No | Carries ad click information in page URLs while cookies are denied. |

## Usage

### Setting up the staging guard

List every production host your stores answer on – `www.example.com` and
`example.com` are different hosts. The comparison is against the store's
configured base URL, so make sure your staging environment has its own base
URL (it needs one anyway). After that, copying the production database to
staging is safe: the container stays off there.

### Custom HTML tags on the checkout

`gtm.js` itself always carries the page nonce. Custom HTML tags need one line
of work in Tag Manager:

1. Create a **Data Layer Variable** named `cspNonce`, reading the key `cspNonce`.
2. In every Custom HTML tag that must run on the checkout, open the script with
   `<script nonce="{{cspNonce}}">`.
3. Add the hosts the tag loads from to **Additional Tag Hosts**.

Pages other than the checkout allow inline scripts, so nothing changes there.

### Consent Mode

- **Magento cookie notice** – switch on Cookie Restriction Mode under Stores >
  Configuration > General > Web > Default Cookie Settings. When the visitor
  clicks *Allow Cookies*, all four signals (`ad_storage`, `ad_user_data`,
  `ad_personalization`, `analytics_storage`) become granted, and stay granted
  on every later page.
- **External consent platform** – the module sets the defaults; your platform's
  Tag Manager template sends the updates. If that template sets defaults of its
  own, choose **Off** instead and let the platform do both, so defaults are not
  set twice.

In Tag Manager, use the built-in consent checks on your tags (Admin > Container
Settings > Enable consent overview).

## Uninstallation

```bash
bin/magento module:disable WebRamos_GoogleTagManager
bin/magento setup:upgrade
bin/magento cache:flush
composer remove webmaster-ramos/module-google-tag-manager
```

The module keeps nothing but its configuration values
(`webramos_google_tag_manager/*` in `core_config_data`).

## Notes and limitations

- Hyvä Checkout (the Magewire checkout) has not been tested; Hyvä stores using
  the native Magento checkout are covered.
- Admin-area pages, headless storefronts and GraphQL are out of scope.
- Whoever can edit this configuration section decides which scripts run on
  your storefront – that is what a Tag Manager container is. Grant the
  *Google Tag Manager* ACL resource only to roles you trust with that.

## Links

- [Extension page](https://webmaster-ramos.com/extensions/google-tag-manager) –
  features, the user guide (PDF) and the download.
- [Article](https://webmaster-ramos.com/docs/google-tag-manager) – Google Tag
  Manager on Magento without test orders in your reports: what the extension is
  for, how it can be used and how it compares.
- [Comparison](https://webmaster-ramos.com/docs/google-tag-manager-vs-yireo-googletagmanager2) –
  Google Tag Manager & Consent Mode vs Yireo GoogleTagManager2.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Support

contact@webmaster-ramos.com

## License

Open Software License 3.0 – see [LICENSE.txt](LICENSE.txt).
