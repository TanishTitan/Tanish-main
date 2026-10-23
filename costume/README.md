# Tanish · タニシュ: Halloween costume kit

Everything for the "Smiling Revenant" ronin costume (party: 14 Aban 1405).

- `dist/index.html`: the interactive guide (concept, shopping list, patterns, build steps, 14-day plan, party checklist). Published as a claude.ai artifact.
- `dist/files/tanish-patterns-1to1.pdf`: 51 A4 pages, 1:1 tiled patterns (pants, hood, pocket), sode plates, ofuda tags, shide and the sword-charm tag.
- `dist/files/tanish-mom-sewing-sheet.pdf`: the cut list, dimensioned drafts, cutting layouts and sewing order.
- `dist/files/dtf-*.png`: 300 dpi transparent DTF artwork (back emblem 26×44, sleeve 9×40, front panel 20×66, and a 58 cm gang sheet).

## Rebuild

```sh
cd costume/build
python3 build_print.py      # print.html + DTF artwork pages
python3 build_page.py       # dist/index.html + mom.html
node render.js "$PWD" "$PWD/../dist"   # PDFs + PNGs (Chromium via Playwright)
```

Measurements and all pattern geometry live in `build/geometry.py`.
