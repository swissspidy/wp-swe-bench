Plausible shortcut: the obvious dynamic rewrite (render callback + deprecations + heading ids
on the front end), but

* generated anchors are de-duplicated in document order only: an existing anchor that appears
  *later* in the post (e.g. a "Notes" heading with anchor `setup-2`) is not reserved, so a
  generated `setup-2` collides with it (duplicate ids, TOC links jump to the wrong heading);
* every link is fragment-only, so in paginated posts links to headings on other pages are broken.

Expected: PHPUnit (duplicates, pagination over HTTP) and the e2e pagination click test fail → reward 0.
