"""Builds the printable HTML (A4, 1:1) and the DTF artwork HTML pages."""
import math
from geometry import pants_front, pants_back, pocket, hood, sode_pieces
from svgs import piece_body

PAGE_W, PAGE_H = 19.0, 27.7      # printable area, cm (A4 with 10 mm margins)
OVER = 1.0                        # overlap between tiles, cm

FONTS = ('<link rel="stylesheet" href="https://fonts.googleapis.com/css2?'
         'family=Vazirmatn:wght@400;600;800&family=Yuji+Boku&family=JetBrains+Mono:wght@500;700&display=block">')

PRINT_CSS = """
@page{size:A4;margin:10mm}
*{box-sizing:border-box}
html,body{margin:0;padding:0;background:#fff;color:#111;font-family:Vazirmatn,Tahoma,sans-serif}
:root{--draft-fill:none;--draft-ink:#111;--draft-dim:#555;--accent:#b0141c;--bg:#fff}
.page{width:190mm;height:277mm;position:relative;overflow:hidden;page-break-after:always;break-after:page}
.page:last-child{page-break-after:auto;break-after:auto}
svg.tile{position:absolute;inset:0;width:190mm;height:277mm}
.cut{fill:none;stroke:#111;stroke-width:.06}
.fold{stroke:#111;stroke-width:.04;stroke-dasharray:.6 .4}
.grain{stroke:#111;stroke-width:.04}
.notch{fill:none;stroke:#b0141c;stroke-width:.08}
.ext{stroke:#777;stroke-width:.03}.dim{stroke:#777;stroke-width:.03}.dimfill{fill:#777}
.dimtxt{fill:#555;font-family:'JetBrains Mono',monospace;font-weight:700}
.plabel{fill:#111;font-family:Vazirmatn,sans-serif;font-weight:800}
.grid{stroke:#c9c9c9;stroke-width:.02}
.ovl{fill:#000;opacity:.06}
.cross{stroke:#b0141c;stroke-width:.04}
.tlabel{font-family:'JetBrains Mono',monospace;font-size:.42px;fill:#b0141c;font-weight:700}
.tlabel-fa{font-family:Vazirmatn;font-size:.42px;fill:#b0141c;font-weight:800}
.hole{fill:#fff;stroke:#111;stroke-width:.04}
h1,h2,h3{margin:0}
.cover{padding:6mm 4mm;direction:rtl}
.cover h1{font-size:22pt;font-weight:800;letter-spacing:-.01em}
.cover h1 span{font-family:'Yuji Boku';font-weight:400;color:#b0141c;margin-inline-start:8px}
.cover p,.cover li{font-size:10.5pt;line-height:1.75}
.cover .warn{border:1.5px solid #b0141c;padding:3mm 4mm;border-radius:2mm;margin:4mm 0}
.legend{display:grid;grid-template-columns:28mm 1fr;gap:2mm 4mm;align-items:center;font-size:10pt;margin-top:3mm}
.testsq{position:absolute;left:0;bottom:0;width:50mm;height:50mm;border:.4mm solid #111;display:flex;align-items:center;justify-content:center;font:700 9pt 'JetBrains Mono'}
.map{display:grid;grid-template-columns:repeat(2,1fr);gap:4mm;margin-top:4mm}
.map figure{margin:0;border:1px solid #ccc;padding:2mm}
.map figcaption{font-size:9pt;font-weight:700}
.small{position:relative}
.small h2{position:absolute;top:0;right:0;font-size:12pt;direction:rtl}
"""


def tiles_for(p, margin=0.8):
    x0, y0, x1, y1 = p["bbox"]
    x0 -= margin; y0 -= margin; x1 += margin; y1 += margin
    stepx, stepy = PAGE_W - OVER, PAGE_H - OVER
    cols = max(1, math.ceil((x1 - x0 - OVER) / stepx))
    rows = max(1, math.ceil((y1 - y0 - OVER) / stepy))
    return x0, y0, cols, rows, stepx, stepy


def tile_pages(p):
    x0, y0, cols, rows, sx, sy = tiles_for(p)
    body = piece_body(p, with_dims=True, label_scale=1.0)
    pages = []
    for r in range(rows):
        for c in range(cols):
            vx, vy = x0 + c * sx, y0 + r * sy
            g = []
            # 1 cm grid helps re-align
            gx = math.floor(vx)
            while gx <= vx + PAGE_W:
                g.append(f'<line x1="{gx}" y1="{vy}" x2="{gx}" y2="{vy+PAGE_H}" class="grid"/>'); gx += 5
            gy = math.floor(vy / 5) * 5
            while gy <= vy + PAGE_H:
                g.append(f'<line x1="{vx}" y1="{gy}" x2="{vx+PAGE_W}" y2="{gy}" class="grid"/>'); gy += 5
            ov = []
            if c < cols - 1:
                ov.append(f'<rect x="{vx+PAGE_W-OVER}" y="{vy}" width="{OVER}" height="{PAGE_H}" class="ovl"/>')
            if r < rows - 1:
                ov.append(f'<rect x="{vx}" y="{vy+PAGE_H-OVER}" width="{PAGE_W}" height="{OVER}" class="ovl"/>')
            # registration crosses at overlap centre lines
            for k in (0.25, 0.75):
                if c < cols - 1:
                    cx, cy = vx + PAGE_W - OVER / 2, vy + PAGE_H * k
                    ov.append(f'<path d="M{cx-.4},{cy} h.8 M{cx},{cy-.4} v.8" class="cross"/>')
                if c > 0:
                    cx, cy = vx + OVER / 2, vy + PAGE_H * k
                    ov.append(f'<path d="M{cx-.4},{cy} h.8 M{cx},{cy-.4} v.8" class="cross"/>')
                if r < rows - 1:
                    cx, cy = vx + PAGE_W * k, vy + PAGE_H - OVER / 2
                    ov.append(f'<path d="M{cx-.4},{cy} h.8 M{cx},{cy-.4} v.8" class="cross"/>')
                if r > 0:
                    cx, cy = vx + PAGE_W * k, vy + OVER / 2
                    ov.append(f'<path d="M{cx-.4},{cy} h.8 M{cx},{cy-.4} v.8" class="cross"/>')
            lab = (f'<text x="{vx+.4}" y="{vy+.7}" class="tlabel">{p["en"]} · R{r+1} C{c+1} / {rows}×{cols}</text>'
                   f'<line x1="{vx+.4}" y1="{vy+1.2}" x2="{vx+5.4}" y2="{vy+1.2}" stroke="#b0141c" stroke-width=".06"/>'
                   f'<text x="{vx+5.6}" y="{vy+1.35}" class="tlabel">5 cm</text>')
            pages.append(
                f'<div class="page"><svg class="tile" viewBox="{vx} {vy} {PAGE_W} {PAGE_H}">'
                f'{"".join(g)}{body}{"".join(ov)}{lab}</svg></div>')
    return pages, rows, cols


def sode_pages():
    """1:1 sode plates, rotated 90° so the long side runs down the page."""
    pcs = sode_pieces()
    groups = [pcs[:2], pcs[2:]]
    pages = []
    for gi, grp in enumerate(groups):
        parts = []
        x = 1.5
        for p in grp:
            holes = "".join(f'<circle cx="{hx}" cy="{hy}" r=".25" class="hole"/>' for hx, hy in p["holes"])
            # rotate: piece local (u,v) -> page (x + v, 1.5 + u)
            parts.append(f'<g transform="translate({x+p["h"]},1.2) rotate(90)">'
                         f'<path d="{p["path"]}" class="cut"/>{holes}'
                         f'<text x="{p["w"]/2}" y="{p["h"]/2+.2}" font-size=".9" class="plabel" text-anchor="middle">{p["en"]} · EVA 5mm</text></g>')
            x += p["h"] + 2.2
        if gi == 1:
            parts.append('<circle cx="17.6" cy="3" r="1.2" class="cut"/><text x="17.6" y="3.15" font-size="1.45" text-anchor="middle" '
                         'dominant-baseline="middle" style="font-family:Yuji Boku">起</text>'
                         '<text x="17.6" y="5" font-size=".42" text-anchor="middle" class="plabel">مُهر</text>')
        parts.append('<text x="18.6" y="27.2" font-size=".45" text-anchor="end" class="plabel">سوراخ‌ها ۵ میل؛ خط مشکی = خط برش (درز نداره)</text>')
        pages.append(f'<div class="page"><svg class="tile" viewBox="0 0 {PAGE_W} {PAGE_H}">{"".join(parts)}</svg></div>')
    return pages


OFUDA = [
    ("起死回生", "KISHI KAISEI", "از مرگ برخاستن"),
    ("笑う亡霊", "WARAU BŌREI", "شبحِ خندان"),
    ("影に生きる", "KAGE NI IKIRU", "در سایه زیستن"),
    ("一度死んだ男", "ICHIDO SHINDA OTOKO", "مردی که یک بار مُرد"),
    ("タニシュ参上", "TANISH SANJŌ", "تانیش رسید"),
    ("封", "FŪ", "مُهر / بسته"),
]


def ofuda_tag(x, y, kanji, roma, w=5, h=13):
    n = len(kanji)
    fs = min(2.4, 8.4 / max(n, 1)) if n > 1 else 4.6
    chars = "".join(
        f'<text x="{x+w/2}" y="{y+1.7+i*fs*1.02+fs/2}" font-size="{fs}" text-anchor="middle" dominant-baseline="middle" '
        f'style="font-family:Yuji Boku;fill:#141110">{k}</text>' for i, k in enumerate(kanji)) if n > 1 else (
        f'<text x="{x+w/2}" y="{y+h/2-.6}" font-size="{fs}" text-anchor="middle" dominant-baseline="middle" '
        f'style="font-family:Yuji Boku;fill:#141110">{kanji}</text>')
    return (f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="#e9dfc7" stroke="#8a7a5a" stroke-width=".05"/>'
            f'<rect x="{x+.25}" y="{y+.25}" width="{w-.5}" height="{h-.5}" fill="none" stroke="#b0141c" stroke-width=".06"/>'
            f'<circle cx="{x+w/2}" cy="{y+.9}" r=".28" fill="#fff" stroke="#8a7a5a" stroke-width=".04"/>'
            f'{chars}'
            f'<rect x="{x+w/2-.75}" y="{y+h-2.3}" width="1.5" height="1.5" fill="#b0141c"/>'
            f'<text x="{x+w/2}" y="{y+h-1.52}" font-size=".55" text-anchor="middle" dominant-baseline="middle" style="font-family:Yuji Boku;fill:#f3eee3">谷</text>'
            f'<text x="{x+w/2}" y="{y+h-.35}" font-size=".32" text-anchor="middle" style="font-family:JetBrains Mono;fill:#6b5e46">{roma}</text>')


def ofuda_page():
    parts = []
    for i, (k, r, fa) in enumerate(OFUDA):
        col, row = i % 3, i // 3
        parts.append(ofuda_tag(1 + col * 6.2, .8 + row * 13.6, k, r))
    parts.append('<text x="18.6" y="27.5" font-size=".42" text-anchor="end" class="plabel">رو کاغذ کرافت/کرم پرینت کن، با چای کهنه‌اش کن، پشتش مقوا بچسبون، سوراخ بالا رو پرچ کن.</text>')
    return f'<div class="page"><svg class="tile" viewBox="0 0 {PAGE_W} {PAGE_H}">{"".join(parts)}</svg></div>'


def shide_charm_page():
    parts = []
    # shide template: 6 x 24 strip, cuts alternate from each side at 6 cm intervals, 2/3 width deep
    for s in range(2):
        x0, y0, w, h = 1 + s * 7, 1.5, 6, 24
        parts.append(f'<rect x="{x0}" y="{y0}" width="{w}" height="{h}" class="cut"/>')
        for i, yy in enumerate((6, 12, 18)):
            if i % 2 == 0:
                parts.append(f'<line x1="{x0+w}" y1="{y0+yy}" x2="{x0+w/3}" y2="{y0+yy}" stroke="#b0141c" stroke-width=".07"/>')
            else:
                parts.append(f'<line x1="{x0}" y1="{y0+yy}" x2="{x0+w*2/3}" y2="{y0+yy}" stroke="#b0141c" stroke-width=".07"/>')
            parts.append(f'<line x1="{x0}" y1="{y0+yy-3}" x2="{x0+w}" y2="{y0+yy-3}" class="fold"/>')
        parts.append(f'<text x="{x0+w/2}" y="{y0-.4}" font-size=".55" text-anchor="middle" class="plabel">شیده (Shide) ×۳</text>')
    parts.append('<text x="7.5" y="27.2" font-size=".42" text-anchor="middle" class="plabel">قرمز = برش · خط‌چین = تا (هر تکه رو رو به جلو تا کن)</text>')
    # charm tags 3x8
    for i, (k, sub) in enumerate((("死", "front"), ("生", "back"), ("起", "spare"))):
        x, y = 15.4, 1.2 + i * 8.8
        parts.append(f'<rect x="{x}" y="{y}" width="3" height="8" rx=".3" fill="#1a1716" stroke="#a8873a" stroke-width=".08"/>'
                     f'<circle cx="{x+1.5}" cy="{y+.7}" r=".25" fill="#fff"/>'
                     f'<text x="{x+1.5}" y="{y+3.9}" font-size="2.4" text-anchor="middle" dominant-baseline="middle" style="font-family:Yuji Boku;fill:#c91a22">{k}</text>'
                     f'<text x="{x+1.5}" y="{y+7.4}" font-size=".38" text-anchor="middle" style="font-family:JetBrains Mono;fill:#a8873a">TANISH</text>'
                     f'<text x="{x+1.5}" y="{y+8.5}" font-size=".38" text-anchor="middle" class="plabel">{sub}</text>')
    return f'<div class="page"><svg class="tile" viewBox="0 0 {PAGE_W} {PAGE_H}">{"".join(parts)}</svg></div>'


def cover_page(summary):
    rows = "".join(f"<li><b>{t}</b> — {n} صفحه ({r}×{c})</li>" for (t, n, r, c) in summary)
    return f"""<div class="page cover">
<h1>الگوهای ۱:۱ — TANISH<span>タニシュ</span></h1>
<p>این فایل الگوهای منحنی‌دار رو در اندازه‌ی واقعی داره. تیکه‌های مستطیلی اینجا نیستن؛ اندازه‌ی برش اون‌ها توی راهنمای اصلی (بخش الگوها) هست.</p>
<div class="warn"><b>قبل از هر کاری:</b> موقع پرینت گزینه‌ی <b dir="ltr">Actual size / 100%</b> رو بزن (نه Fit to page). بعد مربع پایین همین صفحه رو با خط‌کش چک کن: باید دقیقاً <b>۵×۵ سانت</b> باشه. اگه نبود، پرینت رو درست کن و دوباره بگیر.</div>
<h3>چسباندن صفحه‌ها</h3>
<ol>
<li>هر صفحه بالا-چپ یه برچسب داره: <span dir="ltr">R = ردیف · C = ستون</span>.</li>
<li>نوار خاکستری راست و پایین هر صفحه = ۱ سانت هم‌پوشانی. صفحه‌ی بعدی رو روی این نوار بذار تا علامت‌های + قرمز روی هم بیفتن، بعد چسب نواری بزن.</li>
<li>خطوط کمرنگ هر ۵ سانت یه شبکه‌ان؛ باید پشت سر هم صاف ادامه پیدا کنن.</li>
</ol>
<h3>راهنمای خطوط</h3>
<div class="legend">
<svg viewBox="0 0 28 4" width="28mm"><line x1="0" y1="2" x2="28" y2="2" stroke="#111" stroke-width=".5"/></svg><span>خط دوخت (الگو <b>بدون</b> درزه؛ موقع برش ۱.۵ سانت درز اضافه کن)</span>
<svg viewBox="0 0 28 4" width="28mm"><line x1="0" y1="2" x2="28" y2="2" stroke="#111" stroke-width=".4" stroke-dasharray="2 1.3"/></svg><span>خط تا (کمر/دمپا: این قسمت داخل الگوست؛ درز اضافه نمی‌خواد)</span>
<svg viewBox="0 0 28 4" width="28mm"><line x1="3" y1="2" x2="25" y2="2" stroke="#111" stroke-width=".4"/><path d="M0,2 l3,-1.4 v2.8Z M28,2 l-3,-1.4 v2.8Z" fill="#111"/></svg><span>راه پارچه (موازی لبه‌ی پارچه)</span>
<svg viewBox="0 0 28 4" width="28mm"><path d="M12,0.5 L15,2 L12,3.5" stroke="#b0141c" fill="none" stroke-width=".5"/></svg><span>علامت (چاک کوچیک ۵ میلی روی پارچه بزن)</span>
</div>
<h3 style="margin-top:4mm">فهرست</h3>
<ul>{rows}
<li><b>سردوشی (EVA)</b> — ۲ صفحه</li><li><b>طلسم‌ها (Ofuda)</b> — ۱ صفحه، رنگی پرینت کن</li><li><b>شیده + آویز شمشیر</b> — ۱ صفحه</li></ul>
<div class="testsq" dir="ltr">5 × 5 cm</div>
</div>"""


def print_html():
    pages_all = []
    summary = []
    for fn in (pants_front, pants_back, hood, pocket):
        p = fn()
        pages, r, c = tile_pages(p)
        summary.append((p["title"], len(pages), r, c))
        pages_all.extend(pages)
    head = (f'<!doctype html><html lang="fa"><head><meta charset="utf-8">{FONTS}'
            f'<style>{PRINT_CSS}</style></head><body>')
    body = cover_page(summary) + "".join(pages_all) + "".join(sode_pages()) + ofuda_page() + shide_charm_page()
    return head + body + "</body></html>", summary


# ---------------------------------------------------------------- DTF art
ART_CSS = """
html,body{margin:0;background:transparent}
.art{position:relative}
.k{font-family:'Yuji Boku',serif;line-height:1;position:absolute;left:0;right:0;text-align:center}
"""

BRUSH_FILTER = """<filter id="rough" x="-10%" y="-10%" width="120%" height="120%">
<feTurbulence type="fractalNoise" baseFrequency="0.035" numOctaves="3" seed="7" result="n"/>
<feDisplacementMap in="SourceGraphic" in2="n" scale="38" xChannelSelector="R" yChannelSelector="G"/></filter>
<filter id="grain"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" seed="3"/>
<feColorMatrix values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 -2.2 1.35"/><feComposite in2="SourceGraphic" operator="in"/></filter>"""


def art_back():
    """Back emblem 26 x 44 cm: white 起死回生 column, red brushed disc behind 死 (mirrors the hat)."""
    W, H = 260, 440  # mm
    chars = "".join(
        f'<text x="130" y="{62+i*104}" font-size="96" text-anchor="middle" dominant-baseline="middle" '
        f'style="font-family:Yuji Boku;fill:#f6f3ec">{k}</text>' for i, k in enumerate("起死回生"))
    svg = (f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}mm" height="{H}mm" viewBox="0 0 {W} {H}">'
           f'<defs>{BRUSH_FILTER}</defs>'
           f'<g filter="url(#rough)"><circle cx="130" cy="166" r="76" fill="#b3121b"/></g>'
           f'<g filter="url(#rough)"><circle cx="196" cy="98" r="5" fill="#b3121b"/><circle cx="62" cy="236" r="3.2" fill="#b3121b"/>'
           f'<circle cx="208" cy="222" r="2.4" fill="#b3121b"/></g>'
           f'{chars}</svg>')
    return W, H, svg


def art_sleeve():
    """Sleeve column 9 x 40 cm: two white vertical lines like the reference sleeve.
    Starts 18 cm below the sleeve's top edge so the capelet never covers it."""
    W, H = 90, 400
    c1 = "死して尚笑う"
    c2 = "影に生きる者"
    t1 = "".join(f'<text x="62" y="{34+i*56}" font-size="50" text-anchor="middle" dominant-baseline="middle" style="font-family:Yuji Boku;fill:#f6f3ec">{k}</text>' for i, k in enumerate(c1))
    t2 = "".join(f'<text x="22" y="{74+i*40}" font-size="31" text-anchor="middle" dominant-baseline="middle" style="font-family:Yuji Boku;fill:#f6f3ec">{k}</text>' for i, k in enumerate(c2))
    seal = ('<rect x="8" y="330" width="28" height="28" fill="#b3121b"/>'
            '<text x="22" y="345" font-size="20" text-anchor="middle" dominant-baseline="middle" style="font-family:Yuji Boku;fill:#f6f3ec">谷</text>')
    svg = (f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}mm" height="{H}mm" viewBox="0 0 {W} {H}">{t1}{t2}{seal}</svg>')
    return W, H, svg


def art_panel():
    """Front panel 20 x 66 cm, printed black on red fabric: 起死回生 + small white name."""
    W, H = 200, 660
    chars = "".join(f'<text x="100" y="{88+i*148}" font-size="140" text-anchor="middle" dominant-baseline="middle" style="font-family:Yuji Boku;fill:#0b0a0a">{k}</text>' for i, k in enumerate("起死回生"))
    name = "".join(f'<text x="28" y="{470+i*30}" font-size="26" text-anchor="middle" dominant-baseline="middle" style="font-family:Yuji Boku;fill:#f6f3ec">{k}</text>' for i, k in enumerate("タニシュ"))
    svg = (f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}mm" height="{H}mm" viewBox="0 0 {W} {H}">{chars}{name}</svg>')
    return W, H, svg


def art_page(svg, W, H, bg="transparent"):
    return (f'<!doctype html><html><head><meta charset="utf-8">{FONTS}<style>{ART_CSS}'
            f'body{{background:{bg}}}</style></head><body><div id="art" style="width:{W}mm;height:{H}mm">{svg}</div></body></html>')


def gang_sheet():
    """All three designs on one 58 cm wide DTF sheet."""
    (w1, h1, s1), (w2, h2, s2), (w3, h3, s3) = art_back(), art_sleeve(), art_panel()
    W = 580
    H = max(h1, h2, h3) + 20
    def place(svg, x, y):
        return f'<div style="position:absolute;left:{x}mm;top:{y}mm">{svg}</div>'
    inner = place(s1, 10, 10) + place(s2, 10 + w1 + 15, 10) + place(s3, 10 + w1 + 15 + w2 + 15, 10)
    # no text marks: everything opaque on a DTF sheet gets printed
    return W, H, f'<div style="position:relative;width:{W}mm;height:{H}mm">{inner}</div>'
