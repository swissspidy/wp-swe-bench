# Cheat: routing rewrite without per-product path resolution

Takes the whole reference routing/flush/preview fix, but keeps the 1.x doc lookup: the path is resolved
with WordPress' page-path lookup (which ignores products and versions) and the product/version is
only checked afterwards. Pages below `/docs/`, pagination, flushing and draft previews work, and
"wrong doc" URLs now 404 instead of showing the Cloud doc, yet the CLI and v2 docs that share a path
with a Cloud doc are unreachable (404), and so are their cross-links.

Expected: the same-path routing cases, cross-link and preview tests fail → reward 0.
