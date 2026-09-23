# Cheat for rest-member-directory-visibility

The "obvious" fix: build the REST API and the profile form (complete, with validation) and call
`Visibility::visible_fields( $id, get_current_user_id() )` on every surface. It misses the parts
that need thinking about *who* the output is for:

- the RSS feed is rendered with the requesting user's view, so an administrator (or member)
  fetching the feed - which aggregators then cache - exposes members-only/private data;
- the directory's transient cache is still shared between all visitors, so whatever the first
  visitor (e.g. an administrator) could see is served to anonymous visitors for 10 minutes;
- hidden members are still listed in WordPress' users sitemap.

Expected: SurfacesTest fails -> reward 0.
