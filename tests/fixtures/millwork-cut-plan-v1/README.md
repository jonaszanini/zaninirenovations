# Millwork cut-plan v1 fixtures

`closet_default.json` and `heavy_shelf.json` were generated from an older `main`
(`bc380f47`) with the pre-fix calculator (sides dropped, Baltic 5×5 swaps, no
`cut_plan.version`). They prove saved quotes keep that stored output.

`main-94489657/*.json` are frozen full outputs from `main` @ `94489657`
(lines, materials, sheets, SVG/legend). Live legacy (`cut_plan_version` null/1)
must stay byte-equal to those files. Main never wrote `species` on sheet data
and labelled SVG sizes as placed (`a × b`), not longest-first.
