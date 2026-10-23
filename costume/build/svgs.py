"""SVG builders: dimensioned drafts, cutting layouts, the costume map figure."""
import math
from geometry import (pants_front, pants_back, pocket, hood, sode_pieces, RECTS, layouts)

FONT = "Vazirmatn, Tahoma, sans-serif"


def _arrow(x, y, ang, s):
    a1, a2 = ang + 2.7, ang - 2.7
    return (f'<path d="M{x},{y} L{x+s*math.cos(a1):.2f},{y+s*math.sin(a1):.2f} '
            f'L{x+s*math.cos(a2):.2f},{y+s*math.sin(a2):.2f} Z" class="dimfill"/>')


def dim(p1, p2, label, off, fs=2.0):
    (x1, y1), (x2, y2) = p1, p2
    L = math.hypot(x2 - x1, y2 - y1) or 1
    nx, ny = -(y2 - y1) / L, (x2 - x1) / L
    ax, ay, bx, by = x1 + nx * off, y1 + ny * off, x2 + nx * off, y2 + ny * off
    ang = math.atan2(by - ay, bx - ax)
    s = [f'<line x1="{x1}" y1="{y1}" x2="{ax:.2f}" y2="{ay:.2f}" class="ext"/>',
         f'<line x1="{x2}" y1="{y2}" x2="{bx:.2f}" y2="{by:.2f}" class="ext"/>',
         f'<line x1="{ax:.2f}" y1="{ay:.2f}" x2="{bx:.2f}" y2="{by:.2f}" class="dim"/>',
         _arrow(ax, ay, ang, 1.2), _arrow(bx, by, ang + math.pi, 1.2)]
    mx, my = (ax + bx) / 2, (ay + by) / 2
    deg = math.degrees(ang)
    if deg > 90 or deg < -90:
        deg += 180
    tx, ty = mx + nx * (1.2 if off > 0 else -1.2), my + ny * (1.2 if off > 0 else -1.2)
    s.append(f'<text x="{tx:.2f}" y="{ty:.2f}" font-size="{fs}" class="dimtxt" text-anchor="middle" '
             f'dominant-baseline="middle" transform="rotate({deg:.1f} {tx:.2f} {ty:.2f})">{label}</text>')
    return "".join(s)


def piece_body(p, with_dims=True, label_scale=1.0):
    """Inner SVG content of a curved piece in cm coordinates."""
    out = [f'<path d="{p["path"]}" class="cut"/>']
    for (a, b, t) in p["folds"]:
        out.append(f'<line x1="{a[0]}" y1="{a[1]}" x2="{b[0]}" y2="{b[1]}" class="fold"/>')
    (gx1, gy1), (gx2, gy2) = p["grain"]
    out.append(f'<line x1="{gx1}" y1="{gy1}" x2="{gx2}" y2="{gy2}" class="grain"/>')
    out.append(_arrow(gx1, gy1, math.atan2(gy1 - gy2, gx1 - gx2), 1.6))
    out.append(_arrow(gx2, gy2, math.atan2(gy2 - gy1, gx2 - gx1), 1.6))
    for (nx, ny) in p["notches"]:
        out.append(f'<path d="M{nx-0.9},{ny-0.9} L{nx+0.9},{ny} L{nx-0.9},{ny+0.9}" class="notch"/>'
                   if nx <= 1 else
                   f'<path d="M{nx+0.9},{ny-0.9} L{nx-0.9},{ny} L{nx+0.9},{ny+0.9}" class="notch"/>')
    for i, (lx, ly, t) in enumerate(p["labels"]):
        fs = (3.2 if i == 0 else 2.0) * label_scale
        out.append(f'<text x="{lx}" y="{ly}" font-size="{fs}" class="plabel" text-anchor="middle">{t}</text>')
    if with_dims:
        for (a, b, t, off) in p["dims"]:
            out.append(dim(a, b, t, off))
    return "".join(out)


DRAFT_CSS = (".cut{fill:var(--draft-fill);stroke:var(--draft-ink);stroke-width:.3}"
             ".fold{stroke:var(--draft-ink);stroke-width:.2;stroke-dasharray:1.2 .8}"
             ".grain{stroke:var(--draft-ink);stroke-width:.2}"
             ".notch{fill:none;stroke:var(--accent);stroke-width:.35}"
             ".ext{stroke:var(--draft-dim);stroke-width:.12}"
             ".dim{stroke:var(--draft-dim);stroke-width:.15}"
             ".dimfill{fill:var(--draft-dim)}"
             ".dimtxt{fill:var(--draft-dim);font-family:'JetBrains Mono',monospace;font-weight:600}"
             ".plabel{fill:var(--draft-ink);font-family:Vazirmatn,sans-serif;font-weight:700}"
             ".rect{fill:var(--draft-fill);stroke:var(--draft-ink);stroke-width:.3}"
             ".hole{fill:var(--bg);stroke:var(--draft-ink);stroke-width:.15}")


def draft(p):
    x0, y0, x1, y1 = p["bbox"]
    w, h = x1 - x0, y1 - y0
    return (f'<svg class="draft" viewBox="{x0} {y0} {w} {h}" role="img" aria-label="{p["en"]}">'
            f'{piece_body(p)}</svg>')


def coat_body_draft():
    """Coat panel drawn horizontally: x = along length (0..262), y = across width (0..33)."""
    W, Lh = 33, 262
    f = 131
    o = [f'<rect x="0" y="0" width="{Lh}" height="{W}" class="rect"/>',
         f'<line x1="{f}" y1="-3" x2="{f}" y2="{W+3}" class="fold"/>',
         f'<text x="{f}" y="-4.5" font-size="3.4" class="plabel" text-anchor="middle">خط شونه (تا) — 131</text>']
    # neck: inner edge is y=0 (+1.5 seam). neck point 9 from seam line on the fold.
    ny = 1.5 + 9
    o.append(f'<path d="M{f-0.01},{ny} Q{f+1.5},{ny*0.45} {f+2},1.5 L{f+2},0" class="notch"/>')  # back neck dip toward back half
    o.append(f'<path d="M{f},{ny} L{f-28},1.5 L{f-28},0" class="notch"/>')  # front V
    o.append(f'<path d="M{f},0 L{f},{ny} L{f-28},0 Z" style="fill:var(--accent);opacity:.18"/>')
    for d in (22, -22):
        o.append(f'<line x1="{f+d}" y1="{W}" x2="{f+d}" y2="{W+2.5}" class="dim"/>')
    for d in (75, -75):
        o.append(f'<path d="M{f+d-1.2},{W+2.2} L{f+d},{W} L{f+d+1.2},{W+2.2}" class="notch"/>')
    o.append(dim((f, W), (f + 22, W), "22 حلقه آستین", 5))
    o.append(dim((f, W), (f - 75, W), "75 تا شروع چاک", 9))
    o.append(dim((0, 0), (0, W), "33", -4))
    o.append(dim((f - 28, 0), (f, 0), "28 یقه V", -4))
    o.append(dim((f, 0), (f, ny), "9+1.5", 5))
    o.append(f'<text x="{f/2}" y="{W/2+1}" font-size="4.5" class="plabel" text-anchor="middle">جلو — FRONT</text>')
    o.append(f'<text x="{f+f/2}" y="{W/2+1}" font-size="4.5" class="plabel" text-anchor="middle">پشت — BACK</text>')
    o.append(f'<text x="{f+f/2}" y="{W-3}" font-size="2.2" class="plabel" text-anchor="middle">لبه‌ی بالا (y=0) = درز وسط پشت / لبه‌ی جلوی باز</text>')
    # ragged guide
    zz = " ".join(f"L{(i%2)*4+2},{i*33/8:.1f}" for i in range(9))
    o.append(f'<path d="M2,0 {zz}" class="fold" style="stroke:var(--accent)"/>')
    o.append(f'<path d="M{Lh-2},0 ' + " ".join(f"L{Lh-2-(i%2)*6},{i*33/8:.1f}" for i in range(9)) + '" class="fold" style="stroke:var(--accent)"/>')
    o.append(f'<text x="8" y="{W+6}" font-size="2.4" class="plabel">⟵ لبه‌ی پایین جلو: ریش‌ریش، کوتاه‌تر</text>')
    o.append(f'<text x="{Lh-8}" y="{W+6}" font-size="2.4" class="plabel" text-anchor="end">لبه‌ی پایین پشت: ریش‌ریش، بلندتر ⟶</text>')
    return f'<svg class="draft wide" viewBox="-8 -10 278 58" role="img" aria-label="Coat body panel">{"".join(o)}</svg>'


def capelet_draft():
    r1, r2 = 8, 53.5
    cx, cy = 53.5, 0
    o = [f'<path d="M{cx-r2},{cy} A{r2},{r2} 0 0 0 {cx+r2},{cy} L{cx+r1},{cy} A{r1},{r1} 0 0 1 {cx-r1},{cy} Z" class="cut"/>']
    # ragged outer guide
    pts = []
    for i in range(0, 37):
        a = math.pi * i / 36
        rr = r2 - (0 if i % 2 == 0 else 7 + 6 * math.sin(i * 1.7) ** 2)
        pts.append(f"{cx + rr*math.cos(a):.1f},{cy + rr*math.sin(a):.1f}")
    o.append(f'<polyline points="{" ".join(pts)}" class="fold" style="stroke:var(--accent);fill:none"/>')
    o.append(dim((cx, cy), (cx + r1, cy), "8", -3))
    o.append(dim((cx, cy), (cx - r2, cy), "53.5", -3))
    o.append(f'<text x="{cx}" y="30" font-size="3.4" class="plabel" text-anchor="middle">نیم‌شنل ×۲</text>')
    o.append(f'<text x="{cx}" y="35" font-size="2.2" class="plabel" text-anchor="middle">لبه‌ی صاف یکی = وسط پشت (درز) / دیگری = جلوی باز</text>')
    o.append(f'<circle cx="{cx}" cy="{cy}" r=".8" style="fill:var(--accent)"/>')
    return f'<svg class="draft" viewBox="-4 -9 115 66" role="img" aria-label="Capelet">{"".join(o)}</svg>'


def rect_draft(key, w, h, label, extras=""):
    scale_note = f'<text x="{w/2}" y="{h/2}" font-size="{max(2, min(w, h)/7):.1f}" class="plabel" text-anchor="middle" dominant-baseline="middle">{label}</text>'
    return (f'<svg class="draft mini" viewBox="-6 -6 {w+12} {h+12}" role="img" aria-label="{key}">'
            f'<rect x="0" y="0" width="{w}" height="{h}" class="rect"/>{scale_note}'
            f'{dim((0,0),(w,0),str(w),-3, fs=max(1.6, min(w,h)/14))}{dim((w,0),(w,h),str(h),-3, fs=max(1.6, min(w,h)/14))}{extras}</svg>')


FAB_COLORS = {"twill": "var(--fab-black)", "pants": "var(--fab-black2)", "red": "var(--accent)", "gauze": "var(--fab-gauze)"}


def layout_svg(title, length, pieces, kind):
    W = 150
    o = [f'<rect x="0" y="0" width="{W}" height="{length}" style="fill:var(--surface);stroke:var(--draft-ink);stroke-width:.4;stroke-dasharray:2 1.5"/>']
    for (lab, x, y, w, h) in pieces:
        col = FAB_COLORS[kind]
        o.append(f'<rect x="{x+.4}" y="{y+.4}" width="{w-.8}" height="{h-.8}" rx=".8" style="fill:{col};stroke:var(--draft-ink);stroke-width:.3;opacity:.9"/>')
        fs = max(2.6, min(5, w / 7, h / 3))
        o.append(f'<text x="{x+w/2}" y="{y+h/2}" font-size="{fs:.1f}" class="laytxt" text-anchor="middle" dominant-baseline="middle" '
                 f'{"transform=" + chr(34) + f"rotate(-90 {x+w/2} {y+h/2})" + chr(34) if h > w * 2.2 and w < 20 else ""}>{lab}</text>')
    for yy in range(0, length + 1, 50):
        o.append(f'<line x1="-3" y1="{yy}" x2="0" y2="{yy}" style="stroke:var(--muted);stroke-width:.3"/>'
                 f'<text x="-4" y="{yy}" font-size="3.6" text-anchor="end" dominant-baseline="middle" class="dimtxt">{yy}</text>')
    o.append(f'<text x="75" y="-4" font-size="4" class="dimtxt" text-anchor="middle">عرض پارچه 150 سانت</text>')
    return f'<svg class="layout" viewBox="-16 -9 170 {length+14}" role="img" aria-label="{title}">{"".join(o)}</svg>'


def sode_preview():
    """Assembled sode, lames hanging on red cords with 1 cm gaps."""
    o = []
    y = 0
    pieces = sode_pieces()
    for i, p in enumerate(pieces):
        off = (27 - p["w"]) / 2
        o.append(f'<g transform="translate({off},{y})"><path d="{p["path"]}" style="fill:var(--fab-black);stroke:var(--silver);stroke-width:.35"/>')
        for (hx, hy) in p["holes"]:
            o.append(f'<circle cx="{hx}" cy="{hy}" r=".35" style="fill:var(--accent)"/>')
        o.append('</g>')
        if i < len(pieces) - 1:
            nxt = pieces[i + 1]
            noff = (27 - nxt["w"]) / 2
            bot = [h for h in p["holes"] if h[1] > 3][:4]
            top = [h for h in nxt["holes"] if h[1] < 2]
            for (bx, by), (tx, ty) in zip(bot, top):
                o.append(f'<line x1="{bx+off}" y1="{by+y}" x2="{tx+noff}" y2="{ty+y+p["h"]+1}" style="stroke:var(--accent);stroke-width:.55"/>')
        y += p["h"] + 1
    o.append(f'<text x="13.5" y="3.9" font-size="2.6" text-anchor="middle" style="fill:var(--silver);font-family:\'Yuji Boku\',serif">起</text>')
    return f'<svg class="draft sodeprev" viewBox="-2 -2 31 {y+2}" role="img" aria-label="Sode assembled">{"".join(o)}</svg>'
