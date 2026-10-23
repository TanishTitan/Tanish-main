"""Costume map: front and back technical figures with numbered callouts.

Coordinates are in an arbitrary 300 x 640 frame (≈3.1 units per cm of body)."""


def ragged(x0, x1, y, depth, n, seed=1.0):
    import math
    pts = []
    for i in range(n + 1):
        x = x0 + (x1 - x0) * i / n
        d = depth * (0.35 + 0.65 * abs(math.sin(i * 1.9 + seed))) if i % 2 else 0
        pts.append(f"{x:.1f},{y + d:.1f}")
    return " ".join(pts)


def callout(n, x, y):
    return (f'<g class="co"><circle cx="{x}" cy="{y}" r="8.5"/>'
            f'<text x="{x}" y="{y+.5}" text-anchor="middle" dominant-baseline="middle">{n}</text></g>')


def kasa(cx=150, top=46, brim=96, half=92, back=False):
    s = [f'<path d="M{cx-half},{brim} L{cx},{top} L{cx+half},{brim} Q{cx},{brim+10} {cx-half},{brim} Z" class="f-kasa"/>']
    for i in range(1, 7):
        s.append(f'<line x1="{cx}" y1="{top}" x2="{cx-half+i*2*half/7:.1f}" y2="{brim+3}" class="kasa-weave"/>')
    if not back:
        s.append(f'<circle cx="{cx}" cy="{brim-22}" r="7" class="f-red"/>')
        s.append(f'<text x="{cx}" y="{brim-21}" class="kanji-w" font-size="8" text-anchor="middle" dominant-baseline="middle">死</text>')
        s.append(f'<path d="M{cx-half+4},{brim+1} q-3,14 2,30" class="cord"/><path d="M{cx+half-4},{brim+1} q3,14 -2,30" class="cord"/>')
        s.append(f'<path d="M{cx-half+1},{brim+29} l2,10 l3,-10 Z" class="f-red"/><path d="M{cx+half-6},{brim+29} l2,10 l3,-10 Z" class="f-red"/>')
    return "".join(s)


def front():
    o = []
    # sword on back: hilt over the character's right shoulder (viewer left)
    o.append('<line x1="104" y1="168" x2="74" y2="112" class="hilt"/>')
    o.append('<ellipse cx="101" cy="162" rx="7" ry="3" transform="rotate(-62 101 162)" class="f-brass"/>')
    o.append('<path d="M74,112 q-4,10 -2,22" class="cord"/><path d="M69,133 l3,12 l4,-12 Z" class="f-red"/><circle cx="72" cy="128" r="2.2" class="f-brass"/>')
    o.append('<line x1="214" y1="350" x2="234" y2="392" class="scab"/>')
    # boots
    o.append('<path d="M100,560 h44 l4,30 q-2,8 -10,8 h-40 q-6,0 -4,-10 Z" class="f-boot"/>')
    o.append('<path d="M156,560 h44 l4,30 q-2,8 -10,8 h-40 q-6,0 -2,-10 Z" class="f-boot"/>')
    # pants (wide, bloused at boot top)
    o.append('<path d="M116,318 L150,318 L148,566 Q124,572 96,564 Z" class="f-pants"/>')
    o.append('<path d="M150,318 L186,318 L206,564 Q178,572 152,566 Z" class="f-pants"/>')
    o.append('<path d="M98,556 q25,8 50,2 M154,558 q25,6 50,-2" class="seam"/>')
    # red hip panels peeking behind coat slits
    o.append(f'<polygon points="110,300 128,300 128,{445} {ragged(128,108,445,16,5,2)}" class="f-red"/>')
    o.append(f'<polygon points="172,300 192,300 194,440 {ragged(194,172,440,18,5,4)}" class="f-red"/>')
    o.append('<polygon points="104,300 126,300 122,470 100,468" class="f-gauze"/>')
    # coat body (open front)
    o.append(f'<polygon points="108,150 140,150 146,232 138,300 130,470 {ragged(130,92,470,26,6,1)} 96,330" class="f-coat"/>')
    o.append(f'<polygon points="160,150 194,150 204,330 212,486 {ragged(212,170,486,24,6,3)} 162,300 154,232" class="f-coat"/>')
    o.append('<path d="M140,150 L146,232 L138,300 L130,470 M160,150 L154,232 L162,300 L170,486" class="collar"/>')
    # dickey: right-over-left (dead person's way)
    o.append('<path d="M138,152 L156,236 L162,236 L145,152 Z" class="f-red"/>')
    o.append('<path d="M162,152 L142,236 L136,236 L155,152 Z" class="f-red"/>')
    # wearer's left panel first, wearer's right panel (viewer-left shoulder) on top = hidari-mae
    o.append('<path d="M161,152 L146,232 L140,232 L156,152 Z" class="f-coat2"/>')
    o.append('<path d="M139,152 L158,232 L152,232 L134,154 Z" class="f-coat2"/>')
    # obi + belt
    o.append('<rect x="118" y="262" width="66" height="30" rx="2" class="f-red"/>')
    o.append('<rect x="118" y="282" width="66" height="7" class="f-belt"/>')
    o.append('<path d="M151,279 l4,6 l-4,6 l-4,-6 Z" class="f-silver"/>')
    # front panel with kanji
    o.append('<rect x="139" y="292" width="24" height="112" class="f-red"/>')
    o.append('<rect x="139" y="292" width="24" height="7" class="f-red2"/>')
    for i, k in enumerate("起死回生"):
        o.append(f'<text x="151" y="{318+i*22}" class="kanji-b" font-size="16" text-anchor="middle" dominant-baseline="middle">{k}</text>')
    # chain + ofuda
    o.append('<path d="M122,290 q8,36 20,6" class="chain"/>')
    o.append('<path d="M178,290 v14 M186,290 v20 M194,290 v12" class="cord"/>')
    o.append('<rect x="173" y="304" width="10" height="26" class="f-paper"/><rect x="181" y="310" width="10" height="26" class="f-paper"/><rect x="189" y="302" width="10" height="26" class="f-paper"/>')
    o.append('<text x="178" y="316" class="kanji-r" font-size="8" text-anchor="middle">封</text><text x="186" y="322" class="kanji-r" font-size="8" text-anchor="middle">死</text><text x="194" y="314" class="kanji-r" font-size="8" text-anchor="middle">笑</text>')
    # right arm (viewer left): wide sleeve with kanji column
    o.append(f'<polygon points="108,152 82,168 62,300 {ragged(62,104,300,14,5,5)} 104,200" class="f-coat"/>')
    o.append('<text x="84" y="210" class="kanji-w" font-size="7.5" writing-mode="tb" style="writing-mode:vertical-rl">死して尚笑う</text>')
    o.append('<path d="M76,300 l6,22 l14,0 l4,-20 Z" class="f-glove"/>')
    o.append('<path d="M84,322 q-10,22 2,46" class="beads"/><path d="M86,368 l-3,10 l6,0 Z" class="f-red"/>')
    # left arm (viewer right): turtleneck sleeve + bracer + glove
    o.append('<path d="M194,158 L214,170 L226,300 L210,302 L200,214 Z" class="f-knit"/>')
    o.append('<path d="M206,246 L224,244 L227,292 L210,296 Z" class="f-leather"/>')
    o.append('<path d="M208,252 l16,6 M208,262 l17,6 M209,272 l17,6 M209,282 l17,6" class="lace"/>')
    o.append('<path d="M210,300 l16,-2 l4,22 l-16,4 Z" class="f-glove"/>')
    o.append('<path d="M201,204 l18,-3" class="armband"/>')
    # capelet (ragged) over shoulders
    o.append(f'<polygon points="118,132 182,132 210,160 214,196 {ragged(214,86,196,18,10,6)} 86,196 90,160" class="f-coat"/>')
    # baldric: right shoulder (viewer left) -> left hip (viewer right)
    o.append('<path d="M112,160 L192,292" class="strap"/><rect x="146" y="214" width="9" height="7" transform="rotate(58 150 217)" class="f-silver"/>')
    # shimenawa rope: left shoulder (viewer right) -> right hip (viewer left)
    o.append('<path d="M196,158 L114,296" class="rope"/><path d="M196,158 L114,296" class="rope-twist"/>')
    o.append('<path d="M172,198 l6,6 l-6,6 l6,6 l-6,6 l6,6" class="shide"/><path d="M134,262 l6,6 l-6,6 l6,6 l-6,6 l6,6" class="shide"/>')
    # sode on left shoulder (viewer right)
    sode = [(190, 150, 30, 9), (192, 162, 31, 8), (194, 173, 32, 8), (196, 184, 33, 8)]
    for (x, y, w, h) in sode:
        o.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="1.5" class="f-plate" transform="rotate(14 {x} {y})"/>')
    o.append('<path d="M199,158 l-3,12 M208,160 l-3,12 M217,162 l-3,12 M197,170 l-3,11 M206,172 l-3,11 M216,174 l-3,11" class="lacing"/>')
    # hood + red lining + mask
    o.append('<path d="M118,136 Q112,92 132,70 L170,62 Q190,84 184,136 Z" class="f-coat"/>')
    o.append('<path d="M126,134 Q122,96 138,78 L164,74 Q180,96 176,134 Z" class="f-red"/>')
    o.append('<ellipse cx="151" cy="112" rx="20" ry="25" class="f-glow"/>')
    o.append('<path d="M134,96 Q151,86 168,96 Q170,124 151,138 Q132,124 134,96 Z" class="f-mask"/>')
    o.append('<path d="M138,107 q6,-6 11,1 q-6,-3 -11,-1 Z M153,108 q6,-7 11,-1 q-5,-2 -11,1 Z" class="f-ink"/>')
    o.append('<path d="M139,118 q12,16 24,0 q-12,8 -24,0 Z" class="f-ink"/>')
    o.append(kasa())
    # callouts (numbers match the parts table)
    co = [(1, 150, 34), (2, 186, 104), (3, 118, 126), (4, 150, 196), (5, 122, 240), (6, 230, 176),
          (7, 184, 206), (8, 100, 186), (9, 150, 270), (10, 151, 418), (11, 186, 344), (12, 120, 470),
          (13, 70, 248), (14, 236, 270), (15, 94, 382), (16, 128, 520), (17, 124, 594), (18, 62, 112),
          (19, 242, 392)]
    o.extend(callout(*c) for c in co)
    return o


def back():
    o = []
    o.append('<path d="M100,560 h44 l4,30 q-2,8 -10,8 h-40 q-6,0 -4,-10 Z" class="f-boot"/>')
    o.append('<path d="M156,560 h44 l4,30 q-2,8 -10,8 h-40 q-6,0 -2,-10 Z" class="f-boot"/>')
    o.append('<path d="M114,318 L150,318 L148,566 Q124,572 96,564 Z" class="f-pants"/>')
    o.append('<path d="M150,318 L186,318 L206,564 Q178,572 152,566 Z" class="f-pants"/>')
    o.append(f'<polygon points="104,320 122,320 118,452 {ragged(118,98,452,18,4,2)}" class="f-red"/>')
    # coat back (closed), longer, ragged
    o.append(f'<polygon points="104,150 196,150 206,330 216,500 {ragged(216,84,500,34,12,1.3)} 84,500 94,330" class="f-coat"/>')
    o.append('<path d="M150,150 L150,500" class="seam"/>')
    o.append('<path d="M94,330 L84,500 M206,330 L216,500" class="seam"/>')
    o.append('<rect x="104" y="262" width="92" height="28" class="f-red"/><rect x="104" y="280" width="92" height="7" class="f-belt"/>')
    o.append('<path d="M150,262 l-12,-8 l0,22 Z M150,262 l12,-8 l0,22 Z" class="f-red2"/>')
    # back emblem: white kanji column with red disc behind 死
    o.append('<circle cx="150" cy="338" r="15" class="f-red"/>')
    for i, k in enumerate("起死回生"):
        o.append(f'<text x="150" y="{314+i*24}" class="kanji-w" font-size="19" text-anchor="middle" dominant-baseline="middle">{k}</text>')
    # rag strips
    for x, l in [(104, 80), (118, 60), (184, 70), (196, 50)]:
        o.append(f'<path d="M{x},196 q3,{l/2} -2,{l}" class="rag"/>')
    # sword across back: hilt over the right shoulder (viewer right in back view)
    o.append('<line x1="96" y1="384" x2="186" y2="176" class="scab"/>')
    o.append('<line x1="186" y1="176" x2="212" y2="116" class="hilt"/>')
    o.append('<ellipse cx="188" cy="172" rx="8" ry="3" transform="rotate(-66 188 172)" class="f-brass"/>')
    o.append('<path d="M212,116 q6,10 4,22" class="cord"/><path d="M212,137 l3,12 l4,-12 Z" class="f-red"/>')
    o.append('<path d="M116,330 L198,150" class="strap"/>')
    o.append('<rect x="128" y="276" width="10" height="5" transform="rotate(-66 133 278)" class="f-belt"/><rect x="164" y="198" width="10" height="5" transform="rotate(-66 169 200)" class="f-belt"/>')
    o.append('<path d="M104,158 L194,300" class="rope"/><path d="M104,158 L194,300" class="rope-twist"/>')
    # capelet + hood
    # arms: right sleeve (viewer right) wide + ragged, left arm (viewer left) knit + bracer + glove
    o.append(f'<polygon points="192,152 218,168 238,300 {ragged(238,196,300,14,5,5)} 196,200" class="f-coat"/>')
    o.append('<path d="M106,158 L86,170 L74,300 L90,302 L100,214 Z" class="f-knit"/>')
    o.append('<path d="M94,246 L76,244 L73,292 L90,296 Z" class="f-leather"/>')
    o.append('<path d="M90,300 l-16,-2 l-4,22 l16,4 Z" class="f-glove"/>')
    o.append(f'<polygon points="118,132 182,132 210,160 214,198 {ragged(214,86,198,22,10,2)} 86,198 90,160" class="f-coat"/>')
    o.append('<path d="M118,138 Q110,90 134,66 L176,48 Q188,96 184,138 Z" class="f-coat"/>')
    # sode on the character's left shoulder (viewer left in back view)
    for (x, y, w, h) in [(80, 150, 30, 9), (78, 162, 31, 8), (76, 173, 32, 8), (74, 184, 33, 8)]:
        o.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="1.5" class="f-plate" transform="rotate(-14 {x+w} {y})"/>')
    o.append('<path d="M100,158 l3,12 M91,160 l3,12 M82,162 l3,12 M102,170 l3,11 M93,172 l3,11 M84,174 l3,11" class="lacing"/>')
    o.append(kasa(back=True))
    o.append(f'<polygon points="88,99 212,99 {ragged(212,88,150,22,14,3)}" class="f-gauze"/>')
    for x in range(96, 210, 12):
        o.append(f'<line x1="{x}" y1="101" x2="{x + 2}" y2="{138 + (x % 5) * 3}" class="seam"/>')
    co = [(20, 150, 34), (21, 226, 118), (22, 182, 352), (23, 124, 228), (24, 132, 300), (25, 70, 520), (26, 62, 172)]
    o.extend(callout(*c) for c in co)
    return o


FIG_CSS = """
.fig .f-coat{fill:var(--fig-coat);stroke:var(--fig-line);stroke-width:1.2}
.fig .f-coat2{fill:var(--fig-coat2);stroke:var(--fig-line);stroke-width:.8}
.fig .f-pants{fill:var(--fig-pants);stroke:var(--fig-line);stroke-width:1.2}
.fig .f-boot{fill:var(--fig-boot);stroke:var(--fig-line);stroke-width:1.2}
.fig .f-red{fill:var(--accent);stroke:var(--fig-line);stroke-width:.8}
.fig .f-red2{fill:var(--accent-deep)}
.fig .f-gauze{fill:var(--fig-gauze);stroke:var(--fig-line);stroke-width:.6;stroke-dasharray:2 2}
.fig .f-belt{fill:var(--fig-boot)}
.fig .f-silver{fill:var(--silver)}
.fig .f-brass{fill:#a8873a}
.fig .f-paper{fill:#d9cfb8;stroke:#7b6f57;stroke-width:.5}
.fig .f-glove,.fig .f-leather{fill:var(--fig-boot);stroke:var(--fig-line);stroke-width:.8}
.fig .f-knit{fill:var(--fig-knit);stroke:var(--fig-line);stroke-width:1}
.fig .f-plate{fill:var(--fig-boot);stroke:var(--silver);stroke-width:1}
.fig .f-mask{fill:#f4f2ee;stroke:#bdb8ae;stroke-width:.8}
.fig .f-ink{fill:#0d0c0c}
.fig .f-glow{fill:url(#glow)}
.fig .f-kasa{fill:#1b1a1d;stroke:var(--fig-line);stroke-width:1.2}
.fig .kasa-weave{stroke:#3a383e;stroke-width:.8}
.fig .hilt{stroke:#141313;stroke-width:6;stroke-linecap:round;stroke-dasharray:4 1.5}
.fig .scab{stroke:#111;stroke-width:6;stroke-linecap:round}
.fig .cord{fill:none;stroke:var(--accent);stroke-width:1.6}
.fig .lacing,.fig .lace{stroke:var(--accent);stroke-width:1.4}
.fig .lace{stroke:#555}
.fig .armband{stroke:var(--accent);stroke-width:4}
.fig .chain{fill:none;stroke:var(--silver);stroke-width:1.8;stroke-dasharray:2.5 1.2}
.fig .beads{fill:none;stroke:#0b0b0b;stroke-width:5;stroke-linecap:round;stroke-dasharray:.1 6}
.fig .strap{stroke:#0f0f10;stroke-width:6}
.fig .rope{stroke:#a98c5c;stroke-width:7;stroke-linecap:round}
.fig .rope-twist{stroke:#6f5a36;stroke-width:7;stroke-dasharray:1.5 4}
.fig .shide{fill:none;stroke:#f2efe8;stroke-width:3;stroke-linejoin:bevel}
.fig .rag{fill:none;stroke:var(--fig-line);stroke-width:3;opacity:.7}
.fig .seam{fill:none;stroke:var(--fig-line);stroke-width:.8;stroke-dasharray:3 2;opacity:.8}
.fig .collar{fill:none;stroke:var(--fig-line);stroke-width:4;opacity:.9}
.fig .kanji-w{fill:#f4f2ee;font-family:'Yuji Boku',serif}
.fig .kanji-b{fill:#0d0c0c;font-family:'Yuji Boku',serif}
.fig .kanji-r{fill:var(--accent);font-family:'Yuji Boku',serif}
.fig .co circle{fill:var(--callout-bg);stroke:var(--accent);stroke-width:1.5}
.fig .co text{fill:var(--callout-fg);font:700 10px 'JetBrains Mono',monospace}
"""


def figure_svg(which):
    body = front() if which == "front" else back()
    defs = ('<defs><radialGradient id="glow" cx="50%" cy="60%" r="60%">'
            '<stop offset="0" stop-color="#ff2a2a" stop-opacity=".75"/>'
            '<stop offset="1" stop-color="#ff2a2a" stop-opacity="0"/></radialGradient></defs>')
    return (f'<svg class="fig" viewBox="40 20 220 590" role="img" aria-label="{which} view">'
            f'{defs}{"".join(body)}</svg>')
