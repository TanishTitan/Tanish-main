"""Assembles dist/index.html and build/mom.html from the template + generated SVGs."""
import os
from geometry import pants_front, pants_back, pocket, hood, RECTS, WEBBING, layouts
from svgs import draft, coat_body_draft, capelet_draft, rect_draft, layout_svg, sode_preview, DRAFT_CSS
from figure import figure_svg, FIG_CSS

HERE = os.path.dirname(os.path.abspath(__file__))
DIST = os.path.join(HERE, '..', 'dist')
read = lambda f: open(os.path.join(HERE, f), encoding='utf-8').read()

FAB = {"twill": "کتان مشکی", "pants": "پارچه‌ی شلوار", "red": "قرمز", "gauze": "تور مشکی"}
FAB_KIND = {"twill": "twill", "pants": "pants", "red": "red", "gauze": "gauze"}


def draft_cards():
    out = []
    for fn in (pants_front, pants_back, hood, pocket):
        p = fn()
        notes = "".join(f"<li>{n}</li>" for n in p["notes"])
        out.append(f'<div class="draftbox"><h4><span>{p["title"]} — {p["cut"]}</span><span class="en">{p["en"]}</span></h4>'
                   f'{draft(p)}<ul>{notes}</ul></div>')
    return "".join(out)


def rect_cards():
    out = []
    for (key, fab, title, en, w, h, count, note) in RECTS:
        if key in ("coat_body", "cape_half"):
            continue
        out.append(f'<div class="draftbox"><h4><span>{title}</span><span class="tag">{count}</span></h4>'
                   f'{rect_draft(key, w, h, FAB[fab])}<p><span class="en">{en} · {w}×{h} cm</span><br>{note}</p></div>')
    return "".join(out)


def cutlist():
    rows = []
    curved = [(pants_front(), "pants"), (pants_back(), "pants"), (pocket(), "twill"), (hood(), "twill")]
    for p, fab in curved:
        rows.append(f'<tr><td><b>{p["title"]}</b> <span class="en">{p["en"]}</span></td><td>{FAB[fab] if p["key"] != "hood" else "مشکی + آستر قرمز"}</td>'
                    f'<td class="num">PDF / نقشه</td><td>{p["cut"].split("—")[0]}</td><td>بدون درز؛ ۱.۵ اضافه کن</td></tr>')
    for (key, fab, title, en, w, h, count, note) in RECTS:
        rows.append(f'<tr><td><b>{title}</b> <span class="en">{en}</span></td><td>{FAB[fab]}</td><td class="num">{w} × {h}</td><td>{count}</td><td>{note}</td></tr>')
    return "".join(rows)


def webbing_rows():
    return "".join(f'<tr><td><b>{t}</b> <span class="en">{en}</span></td><td>{m}</td><td>{w}</td></tr>' for (_, t, en, m, w) in WEBBING)


def layout_cards():
    kinds = ["twill", "pants", "red"]
    out = []
    for (title, length, pieces), k in zip(layouts(), kinds):
        out.append(f'<div class="draftbox"><h4><span>{title}</span><span class="en">{length} cm</span></h4>{layout_svg(title, length, pieces, k)}</div>')
    return "".join(out)


def mom_steps():
    parts = read("build_steps.html").split("<details")
    keep = ["<details open>" + p.split(">", 1)[1] for p in parts[1:] if "pill mom" in p]
    return "".join(keep)


def fill(tpl, mapping):
    for k, v in mapping.items():
        tpl = tpl.replace("{{" + k + "}}", v)
    assert "{{" not in tpl, tpl[tpl.index("{{"):tpl.index("{{") + 40]
    return tpl


def main():
    m = {
        "DRAFT_CSS": DRAFT_CSS, "FIG_CSS": FIG_CSS,
        "FIG_FRONT": figure_svg("front"), "FIG_BACK": figure_svg("back"),
        "DRAFT_CARDS": draft_cards(), "D_COAT": coat_body_draft(), "D_CAPE": capelet_draft(), "D_SODE": sode_preview(),
        "RECTS": rect_cards(), "WEBBING": webbing_rows(), "LAYOUTS": layout_cards(), "CUTLIST": cutlist(),
        "BUILD": read("build_steps.html"), "DATA_JS": read("data.js"), "APP_JS": read("app.js"),
    }
    html = fill(read("template.html"), m)
    open(os.path.join(DIST, "index.html"), "w", encoding="utf-8").write(html)
    print("index.html", len(html) // 1024, "KB")

    # Mom's sewing sheet: same drafts in print colours, plus cut list and the sewing steps.
    mom = f"""<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700;900&family=Yuji+Boku&family=JetBrains+Mono:wght@500;700&display=block">
<style>
@page{{size:A4;margin:12mm}}
:root{{--bg:#fff;--fg:#111;--muted:#555;--line:#ccc;--accent:#b0141c;--accent-soft:#f6e3e3;--surface:#fff;--surface2:#f1f1f1;
--draft-ink:#111;--draft-dim:#8a1a20;--draft-fill:#fff;--fab-black:#3a3a3a;--fab-black2:#555;--fab-gauze:#888;--silver:#888}}
body{{font:400 10.5pt/1.75 Vazirmatn,sans-serif;color:#111;margin:0}}
h1{{font-size:20pt;margin:0}} h1 span{{font-family:'Yuji Boku';color:#b0141c;margin-inline-start:8px}}
h2{{font-size:13pt;margin:14px 0 6px;border-bottom:2px solid #b0141c;padding-bottom:2px}}
h3{{font-size:11pt;margin:8px 0 4px}}
table{{border-collapse:collapse;width:100%;font-size:9.5pt}} th,td{{border:1px solid #ccc;padding:4px 6px;text-align:right;vertical-align:top}}
th{{background:#f3f3f3}} .num{{font-family:'JetBrains Mono';direction:ltr;text-align:center;white-space:nowrap}}
.en{{font-family:'JetBrains Mono';font-size:8pt;color:#777;direction:ltr;unicode-bidi:isolate}}
.g2{{display:grid;grid-template-columns:1fr 1fr;gap:10px}} .box{{border:1px solid #ccc;border-radius:6px;padding:8px;break-inside:avoid}}
svg.draft{{width:100%;height:auto;max-height:120mm}} .big svg.draft{{max-height:228mm}} svg.draft.wide{{max-height:none}} svg.layout{{width:100%;height:auto;max-height:150mm}}
.laytxt{{fill:#fff;font-family:Vazirmatn;font-weight:700}}
.pb{{break-before:page}} .note{{border-right:3px solid #b0141c;background:#f6e3e3;padding:6px 10px;margin:8px 0}}
details{{display:block}} summary{{display:block;font-weight:900;font-size:11.5pt;margin-top:10px}} summary .n{{color:#b0141c;margin-left:6px}} summary .t{{font-weight:400;color:#666;font-size:9pt;margin-right:8px}}
.pill{{font-size:8pt;border:1px solid #b0141c;color:#b0141c;border-radius:4px;padding:0 5px;margin-right:6px}}
ol,ul{{margin:4px 0;padding-right:20px}} li{{margin-bottom:3px}}
{DRAFT_CSS}
</style></head><body>
<h1>برگه‌ی خیاطی — کاستوم تانیش<span>タニシュ</span></h1>
<p>سلام! این برگه همه‌ی چیزهاییه که برای دوخت لازمه. همه‌ی قطعه‌ها <b>درز صاف</b> دارن؛ بیشترشون مستطیلن. تیکه‌های منحنی (شلوار، هود، جیب) هم با اندازه کشیده شدن و هم توی فایل PDF جدا با اندازه‌ی واقعی هستن.</p>
<div class="note"><b>قانون درز:</b> تیکه‌های منحنی <b>بدون درز</b> کشیده شدن؛ ۱.۵ سانت اضافه کنید (به جز تای کمر ۵ و تای دمپا ۴ که داخل الگوست). اندازه‌ی مستطیل‌ها <b>اندازه‌ی برش</b> هست و درز توش حساب شده. لبه‌هایی که ریش‌ریش میشن (پایین کت، شنل، پنل‌ها) سجاف نمی‌خوان. پارچه‌های نخی رو قبل از برش یه بار بشورید.</div>
<h2>اندازه‌ها</h2>
<table><tr><th>قد</th><th>سینه</th><th>کمر</th><th>باسن</th><th>سرشانه</th><th>قد دست</th><th>فاق</th><th>داخل پا</th><th>کمر تا زمین</th><th>دور سر</th></tr>
<tr class="num"><td class="num">187</td><td class="num">92</td><td class="num">78</td><td class="num">96</td><td class="num">48</td><td class="num">70</td><td class="num">30</td><td class="num">88</td><td class="num">118</td><td class="num">58</td></tr></table>
<h2>لیست برش</h2>
<table><tr><th>تیکه</th><th>پارچه</th><th>برش (سانت)</th><th>تعداد</th><th>توضیح</th></tr>{cutlist()}</table>
<div class="pb"></div>
<h2>شلوار و جیب</h2>
<div class="g2 big">{"".join(f'<div class="box"><h3>{p["title"]} — {p["cut"]}</h3>{draft(p)}</div>' for p in (pants_front(), pants_back()))}</div>
<div class="pb"></div>
<div class="g2" style="margin-top:8px">{"".join(f'<div class="box"><h3>{p["title"]} — {p["cut"]}</h3>{draft(p)}</div>' for p in (hood(), pocket()))}</div>
<div class="pb"></div>
<h2>بدنه‌ی کت (۳۳ × ۲۶۲، دو تا قرینه)</h2>
<div class="box">{coat_body_draft()}<ul>
<li>هر تیکه یکسره از پایین جلو، از روی شونه، تا پایین پشت. شونه درز نداره و تا میخوره (خط ۱۳۱).</li>
<li>لبه‌ی بالای شکل = لبه‌ی داخلی: نیمه‌ی پشت = درز وسط پشت؛ نیمه‌ی جلو باز می‌مونه.</li>
<li>یقه‌ی V: از ۱۰.۵ سانتی روی خط شونه تا ۲۸ سانت پایین‌تر روی لبه‌ی جلو. پشت گردن گودی ۲ سانت.</li>
<li>حلقه‌ی آستین: ۲۲ سانت دو طرف شونه. چاک کناری از ۷۵ سانت پایین‌تر از شونه.</li></ul></div>
<div class="g2" style="margin-top:8px"><div class="box"><h3>شنل — نیم‌دایره‌ی توخالی ×۲</h3>{capelet_draft()}</div>
<div class="box"><h3>نقشه‌ی برش پارچه‌ی کت (عرض ۱۵۰)</h3>{layout_svg(*layouts()[0], 'twill')}</div></div>
<div class="pb"></div>
<div class="g2"><div class="box"><h3>نقشه‌ی برش شلوار (دولا)</h3>{layout_svg(*layouts()[1], 'pants')}</div>
<div class="box"><h3>نقشه‌ی برش قرمز</h3>{layout_svg(*layouts()[2], 'red')}</div></div>
<div class="pb"></div>
<h2>ترتیب دوخت</h2>
{mom_steps()}
</body></html>"""
    open(os.path.join(HERE, "mom.html"), "w", encoding="utf-8").write(mom)
    print("mom.html ok")


if __name__ == "__main__":
    main()
