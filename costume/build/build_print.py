import os
from printpack import print_html, art_back, art_sleeve, art_panel, art_page, gang_sheet
HERE = os.path.dirname(os.path.abspath(__file__))
html, summary = print_html()
open(os.path.join(HERE, 'print.html'), 'w').write(html)
for name, fn in (('dtf-back', art_back), ('dtf-sleeve', art_sleeve), ('dtf-panel', art_panel), ('dtf-gangsheet', gang_sheet)):
    W, H, svg = fn()
    open(os.path.join(HERE, name + '.html'), 'w').write(art_page(svg, W, H))
print(summary)
