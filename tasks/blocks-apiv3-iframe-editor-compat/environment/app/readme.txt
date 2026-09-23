=== Acme Charts ===
Contributors: acmeweb
Requires at least: 6.5
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later

Simple bar charts and chart legends for the block editor.

== Description ==

* Block "Bar chart" (`acme/chart`): enter bars in the block settings (label, value, optional colour) or paste CSV. Optional title, height, "show values".
* Block "Chart legend" (`acme/chart-legend`): lists the bars of a chart on the same page (the chart needs an HTML anchor). Clicking an item shows/hides its bar.
* Settings → Charts: colour palette, default height, show values.
* Themes can change the palette with the `acme_charts_palette` filter.

The chart SVG is drawn in the browser by `assets/js/chart-renderer.js` (front end and editor preview). The saved block markup contains the data (`data-chart` JSON) and a screen-reader table, so content stays readable without JavaScript.

= Front-end markup =

Themes and our QA scripts rely on this:

`<figure class="wp-block-acme-chart acme-chart" data-chart="{…}">`
`  <div class="acme-chart__canvas" style="height:240px" aria-hidden="true"><svg class="acme-chart__svg" width="…" height="240">`
`    <rect class="acme-chart__bar" data-index="0" …/><text class="acme-chart__label" …>Q1</text> …`
`  </svg></div>`
`  <figcaption class="acme-chart__title">…</figcaption>`
`  <table class="acme-chart__table">…</table>`
`</figure>`
`<ul class="wp-block-acme-chart-legend acme-legend" data-chart="{anchor}"><li><button class="acme-legend__item" data-index="0" aria-pressed="true">…</button></li>…</ul>`

A hidden bar has the class `is-hidden`, and its legend item has `aria-pressed="false"`.

== Changelog ==

= 1.6.0 =
* Chart legend block.
* "Minimal" block style.

= 1.5.0 =
* Paste CSV into the chart settings.
* Convert a two-column table block to a chart.

= 1.4.0 =
* Settings → Charts (palette, default height, show values).
* `acme_charts_palette` filter.

= 1.3.0 =
* New saved markup: `<figure>` with `data-chart` and a screen-reader data table. 1.0 charts keep working and are upgraded when edited.
* Wide and full alignment.

= 1.0.0 =
* Initial release.
