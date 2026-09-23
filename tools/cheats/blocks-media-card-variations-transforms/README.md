Shortcut: the reference variations, layout/meta attributes, save and transforms, but
- no 1.x → 2.0 deprecation ("the new attributes have defaults, so old cards are fine"): 1.1/1.2 cards and the
  promo pattern are flagged as invalid in the editor because their saved markup lacks `has-layout-stacked`;
- variations without `isActive`, so the editor can't tell which card type a block is (block card title,
  getActiveBlockVariation).

Expected: legacy-content and active-variation E2E tests fail → reward 0.
