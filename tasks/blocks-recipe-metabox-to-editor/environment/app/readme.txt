=== Acme Recipes ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later

Recipes for the Acme food blog.

== Description ==

* Post type "Recipe" (`acme_recipe`) with cuisines (`acme_cuisine`).
* "Recipe details" box on the edit screen: ingredients (amount, unit, ingredient), prep and cook time,
  servings, difficulty, private kitchen notes and (editors only) the "Staff pick" flag.
* A recipe card (times, servings, difficulty, ingredients) is added below the content of every recipe.
  Themes can override `templates/recipe-card.php` in `{theme}/acme-recipes/recipe-card.php` or filter
  `acme_recipes_card_html`.
* schema.org `Recipe` JSON-LD on recipe pages (`acme_recipes_schema` filter).

Data is stored in post meta, see `includes/functions.php`. Other tools read it directly (the newsletter
exporter reads `_acme_recipe_ingredients` as a serialized array of `amount`/`unit`/`item` arrays), so the
storage format must not change.

== Changelog ==

= 1.6.0 =
* schema.org JSON-LD.
* Private kitchen notes.

= 1.5.0 =
* "Staff pick" flag (editors only).

= 1.4.0 =
* Ingredients are stored as amount/unit/item. Recipes from 1.x (one text line per ingredient, times as
  text like "1 h 30 min", capitalized difficulty) are still read correctly.

= 1.0.0 =
* Initial release.
