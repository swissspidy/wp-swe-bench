=== Acme Pricing Tables ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later

Pricing table block for the Acme marketing sites: plans with prices, features and a call to action.

== Description ==

* Block: "Pricing table" (`acme/pricing-table`). Up to four plans side by side, one of them can be highlighted.
* Prices are formatted per currency (USD, EUR, GBP, CHF – more through the `acme_pricing_currencies` filter).
* Structured data: pages with pricing tables get a `Product` JSON-LD script with one `Offer` per plan
  (Settings → Pricing → Structured data). The SEO plugin extends it through the `acme_pricing_schema` filter.
* Shortcode for prices in running text: `[acme_price amount="19.50" currency="EUR"]` → "19,50 €".
* Settings → Pricing: default currency of new tables, structured data on/off.

== Markup ==

Themes style the pricing table through these classes (see `src/pricing-table/style.scss` for the defaults):

    <div class="wp-block-acme-pricing-table acme-pricing acme-pricing--cols-{n} acme-pricing--currency-{code}">
      <div class="acme-pricing__plan [is-highlighted]">
        <h3 class="acme-pricing__name">…</h3>
        <p class="acme-pricing__price"><span class="acme-pricing__amount">$19</span><span class="acme-pricing__period">/month</span></p>
        <ul class="acme-pricing__features"><li>…</li></ul>
        <a class="acme-pricing__button" href="…">…</a>
      </div>
    </div>

== Theme integration ==

* `acme_pricing_format_price( $amount, $currency )` – format a price like the block does.
* `acme_pricing_has_table( $post )` – whether a post contains a pricing table.

== Changelog ==

= 1.6.0 =
* Structured data now follows synced patterns.
* GBP support.

= 1.5.0 =
* CHF formatting ("CHF 49.–"), thousands separators.
* `[acme_price]` shortcode.

= 1.4.0 =
* Plans beyond the column count are kept when the count is reduced (and come back when it is increased again).

= 1.3.0 =
* Plans are stored as a list of plan objects; per-plan button texts; currencies (USD, EUR, CHF).
* The highlighted plan uses the `is-highlighted` class.
* Tables from 1.0–1.2 are upgraded automatically in the editor.

= 1.2.0 =
* Structured data (JSON-LD).

= 1.0.0 =
* Initial release (US dollars only).
