=== Acme UI Kit ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.9.0
License: GPLv2 or later

Tabs, accordions and carousels for the Acme sites.

== Description ==

* Blocks: Tabs (`acme/tabs`), Accordion (`acme/accordion`), Carousel (`acme/carousel`).
* Legacy shortcode: `[acme_tabs][acme_tab title="One"]…[/acme_tab][/acme_tabs]`.
* JS runtime `window.AcmeUI` (`register( name, init )`, `init( root )`) that other plugins build on:
  any element with `data-acme-component="<name>"` is initialised by the component registered under that name.
* PHP: `acme_ui_enqueue( $components = array() )` – load the runtime and components on the current page
  (for plugins that print UI Kit markup or register their own components).
* Filter `acme_ui_config` – the runtime configuration (`window.AcmeUIConfig`).
* Settings → UI Kit: accent colour, animation speed, carousel autoplay.

= Assets =

Assets are only loaded on pages that use a component (blocks anywhere on the page – content, synced
patterns, template parts, widgets –, the shortcode, or acme_ui_enqueue()). Scripts are deferred.

Script handles: `acme-ui-core` (runtime), `acme-ui-motion` (bundled AcmeMotion 3.4.1, carousel only),
`acme-ui-tabs`, `acme-ui-accordion`, `acme-ui-carousel` (component behaviour), `acme-ui-components`
(all three, kept for back-compat). Style handles: `acme-ui` (tokens), `acme-tabs-style`,
`acme-accordion-style`, `acme-carousel-style` (per component), `acme-ui-icons` (icon set).
Admin: `acme-ui-admin` (script + style, settings screen only), `acme-ui-blocks-editor` (script, block editor).

== Changelog ==

= 2.9.0 =
* Performance: assets are only loaded where a component is used, per component (the 300 KB motion library only with a carousel), and scripts are deferred.
* Admin: nothing is loaded outside the settings screen and the block editor.
* Component styles split into `acme-tabs-style`, `acme-accordion-style`, `acme-carousel-style`.

= 2.8.0 =
* Carousel: autoplay option.
* Accent colour and animation speed are applied as CSS custom properties.

= 2.5.0 =
* Blocks for tabs, accordion and carousel.

= 2.0.0 =
* `window.AcmeUI` runtime with `register()`; components are initialised from `data-acme-component`.
* `acme_ui_enqueue()`.

= 1.0.0 =
* `[acme_tabs]` shortcode.
