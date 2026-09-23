#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
cp seed/mu-plugins/*.php /wordpress/wp-content/mu-plugins/

term() { # slug title status short excerpt content
  local id
  id=$(wp post create --user=admin --post_type=glossary_term --post_status="$3" --post_name="$1" --post_title="$2" \
    --post_excerpt="$5" --post_content="$6" --porcelain)
  if [ -n "$4" ]; then wp post meta update "$id" _acme_glossary_short "$4" >/dev/null; fi
  echo "$id"
}
term cache "Cache" publish "A store of copies of data, so that future requests are served faster." "" "<p>A cache keeps copies of expensive results.</p>" >/dev/null
inv=$(term cache-invalidation "Cache invalidation" publish "Removing or refreshing cached data when the original changes." "" "<p>One of the two hard things.</p>")
wp post meta update "$inv" _docs_deprecated 1 >/dev/null
term cdn "CDN" publish "Content delivery network: servers around the world that deliver files from a location near the visitor." "" "<p>See our CDN guide.</p>" >/dev/null
ttfb=$(term ttfb "Time to first byte" publish "How long the browser waits for the first byte of the response." "" "<p>TTFB includes DNS, TLS and server time.</p>")
term edge-function "Edge function" publish "" "Code that runs on CDN servers close to the visitor." "<p>Edge functions run in isolates.</p>" >/dev/null
term origin-server "Origin server" publish "" "" "<p>The origin server is the machine that holds the original version of your site and answers every request that the CDN or a cache cannot answer by itself, including logged-in traffic and uncached pages, admin screens and REST calls.</p>" >/dev/null
term stale-while-revalidate "Stale-while-revalidate" publish 'Serve the <stale> copy & refresh it "in the background".' "" "<p>An HTTP cache directive.</p>" >/dev/null
term cache-stampede "Cache stampede" draft "Many requests regenerating the same expired item at once." "" "<p>Draft.</p>" >/dev/null
term internal-cdn "Internal CDN" private "Our private CDN (do not publish)." "" "<p>Private.</p>" >/dev/null

sed "s/%TTFB%/$ttfb/g" seed/posts/caching-basics.txt > /tmp/caching-basics.txt
sed "s/%TTFB%/$ttfb/g" seed/posts/cdn-setup.txt > /tmp/cdn-setup.txt
wp post create /tmp/caching-basics.txt --user=admin --post_type=post --post_status=publish --post_name=caching-basics --post_title="Caching basics" >/dev/null
wp post create /tmp/cdn-setup.txt --user=admin --post_type=post --post_status=publish --post_name=cdn-setup --post_title="Setting up a CDN" >/dev/null
rm -f /tmp/caching-basics.txt /tmp/cdn-setup.txt
wp post create seed/posts/block-shortcode.html --user=admin --post_type=post --post_status=publish --post_name=block-shortcode --post_title="Shortcode in a block" >/dev/null
wp post create seed/posts/glossary-page.html --user=admin --post_type=page --post_status=publish --post_name=glossary-terms --post_title="Glossary" >/dev/null

wp user create writer1 writer1@example.org --role=author --user_pass=password --display_name="Wanda Writer" >/dev/null
wp rewrite flush >/dev/null
