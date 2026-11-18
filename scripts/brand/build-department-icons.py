"""Build department SVGs and preview from shared geometry/tokens (ADR-370).

Run: python scripts/brand/build-department-icons.py
No network, packages or application boot required. L embeds the supplied PNG.
"""
import json
import hashlib
import base64
import math
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'public/img/departments'
TOKENS = (ROOT / 'public/css/tokens.css').read_text(encoding='utf-8')
GLYPHS = json.loads((Path(__file__).with_name('department-glyphs.json')).read_text(encoding='utf-8-sig'))
PATTERNS = json.loads((Path(__file__).with_name('department-patterns.json')).read_text(encoding='utf-8'))
DEPARTMENTS = [
    ('perancang', 'P', 'Planning', 'Kontur topografi'),
    ('bangunan', 'B', 'Building', 'Bumbung limas berjubin'),
    ('kejuruteraan', 'E', 'Engineering', 'Struktur jambatan'),
    ('landskap', 'L', 'Landscape', 'Dedaun botani'),
]


def token(name):
    value = re.search(r'--' + re.escape(name) + r':\s*([^;]+);', TOKENS).group(1)
    return re.sub(r'var\(--([^\)]+)\)', lambda m: token(m[1]), value)


def path(d, **attrs):
    extra = ' '.join(f'{k.replace("_", "-")}="{v}"' for k, v in attrs.items())
    return f'<path d="{d}" {extra}/>'


def engineering(compact):
    """Precision vector bridge elevation, following the approved K composition."""
    result = []
    if not compact:
        grid = ''.join(f'M{n} 8V112M8 {n}H112' for n in range(12, 113, 7))
        result.append(path(grid, stroke_width='var(--stroke-department-engineering-grid)',
                           opacity='var(--opacity-department-engineering-grid)'))
        # Dimension and projection lines, deliberately without invented values.
        result.append(path('M17 18H103M17 14V34M103 14V34'
                           'M17 18L20 16.5M17 18L20 19.5M103 18L100 16.5M103 18L100 19.5'
                           'M33 105H87M33 102V108M87 102V108'
                           'M33 105L36 103.5M33 105L36 106.5M87 105L84 103.5M87 105L84 106.5'
                           'M106 47V94M102 47H109M102 94H109'
                           'M106 47L104.5 50M106 47L107.5 50M106 94L104.5 91M106 94L107.5 91'))
        result.append(path('M8 82H112', stroke_dasharray='2 2', opacity='.45'))

    # Deck, upper chord and paired steel members form a coherent elevation.
    structure = path('M3 73H117V77H3ZM3 70.8H117M3 75H117'
                     'M3 54L28 40 60 36 92 40 117 54'
                     'M3 56L28 42 60 38 92 42 117 56')

    def member(x1, y1, x2, y2, width=1.05):
        length = math.hypot(x2-x1, y2-y1)
        nx, ny = -(y2-y1)/length*width/2, (x2-x1)/length*width/2
        return path(f'M{x1+nx:.2f} {y1+ny:.2f}L{x2+nx:.2f} {y2+ny:.2f}'
                    f'L{x2-nx:.2f} {y2-ny:.2f}L{x1-nx:.2f} {y1-ny:.2f}Z')

    for top, bottom in [((5,55),(5,71)), ((28,41),(28,71)),
                        ((60,37),(60,71)), ((92,41),(92,71)), ((115,55),(115,71))]:
        structure += member(*top, *bottom)
    for a,b in [((5,55),(17,71)), ((17,71),(28,41)), ((28,41),(44,71)),
                ((44,71),(60,37)), ((60,37),(76,71)), ((76,71),(92,41)),
                ((92,41),(103,71)), ((103,71),(115,55))]:
        structure += member(*a,*b, width=.8 if compact else 1.15)

    # Bearings, tapered reinforced-concrete piers and boxed footings.
    for cx in [24,96]:
        structure += path(f'M{cx-6} 77H{cx+6}V80H{cx-6}Z'
                          f'M{cx-3} 80H{cx+3}L{cx+4.5} 96H{cx-4.5}Z'
                          f'M{cx-9} 96H{cx+9}V111H{cx-9}Z')
        if not compact:
            structure += path(f'M{cx-9} 99H{cx+9}M{cx-2} 82L{cx-3} 94M{cx+2} 82L{cx+3} 94')
            hatch = ''.join(f'M{cx-8+i} 110L{cx-8+i+4} 101' for i in range(0,13,3))
            result.append(path(hatch))
    result.append(f'<g stroke-width="var(--stroke-department-engineering-structure)">{structure}</g>')

    if not compact:
        # Explicit node plates and rivets; all coordinates align to the members.
        for x,y in [(5,55),(28,41),(60,37),(92,41),(115,55),
                    (5,71),(17,71),(28,71),(44,71),(60,71),(76,71),(92,71),(103,71),(115,71)]:
            result.append(path(f'M{x-1.25} {y-1.25}H{x+1.25}V{y+1.25}H{x-1.25}Z'))
            result.append(f'<circle cx="{x}" cy="{y}" r=".35" fill="var(--department-line)" stroke="none"/>')
        result.append(path('M8 111H112M10 113H110'))
    return ''.join(result)


def artwork(dept, letter, compact=False):
    if dept == 'kejuruteraan':
        source = base64.b64encode((Path(__file__).with_name('kejuruteraan-soft.png')).read_bytes()).decode('ascii')
        return ('<svg width="120" height="120" viewBox="82 112 1087 1080" preserveAspectRatio="none" overflow="hidden">'
                f'<image width="1243" height="1265" href="data:image/png;base64,{source}"/></svg>')
    if dept == 'landskap':
        # Preserve the owner's supplied artwork exactly. The nested viewport
        # excludes the surrounding screenshot; no raster pixels are rewritten.
        source = base64.b64encode((Path(__file__).with_name('landskap-soft.png')).read_bytes()).decode('ascii')
        return ('<svg width="120" height="120" viewBox="154 104 1031 1010" preserveAspectRatio="none" overflow="hidden">'
                f'<image width="1269" height="1239" href="data:image/png;base64,{source}"/></svg>')
    prefix = dept + ('-compact' if compact else '-detailed')
    # Trace the owner's approved concept rather than substituting generic motifs.
    pattern = engineering(compact) if dept == 'kejuruteraan' else path(PATTERNS[dept])
    pattern_opacity = '--opacity-department-perancang-pattern' if dept == 'perancang' else '--opacity-department-pattern'
    glyph = GLYPHS[letter]
    x, y, w, h = map(float, glyph['bounds'].split(','))
    scale = 64 / h
    tx, ty = 60 - (x + w / 2) * scale, 60 - (y + h / 2) * scale
    # Fixed sharp 8/120 frame, matching the approved concept's visible proportion.
    return f'''<defs>
  <linearGradient id="{prefix}-fill" x2="1" y2="1"><stop stop-color="{token(f'color-department-{dept}-light')}"/><stop offset="1" stop-color="{token(f'color-department-{dept}-deep')}"/></linearGradient>
  <clipPath id="{prefix}-face"><path d="M8 8H112V112H8Z"/></clipPath>
</defs>
<path fill="var(--department-frame)" d="M0 0H120V120H0Z"/>
<path fill="url(#{prefix}-fill)" d="M8 8H112V112H8Z"/>
<g clip-path="url(#{prefix}-face)" fill="none" stroke="var(--department-line)" stroke-width="var(--stroke-department-pattern{'-compact' if compact else ''})" opacity="var({pattern_opacity})" stroke-linejoin="round">{pattern}</g>
<path fill="var(--color-department-letter)" transform="translate({tx:.4f} {ty:.4f}) scale({scale:.4f})" d="{glyph['path']}"/>
'''


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    symbols = []
    for dept, letter, name, _ in DEPARTMENTS:
        for compact in [False, True]:
            variant = 'compact' if compact else 'detailed'
            markup = artwork(dept, letter, compact)
            props = {f'--department-{key}': token(f'color-department-{dept}-{key}') for key in ['light', 'deep', 'frame', 'line']}
            for key in ['color-department-letter', 'stroke-department-pattern', 'stroke-department-pattern-compact', 'opacity-department-pattern', 'opacity-department-perancang-pattern', 'opacity-department-botanical-pattern', 'stroke-department-engineering-structure', 'stroke-department-engineering-grid', 'opacity-department-engineering-grid']:
                props['--' + key] = token(key)
            styles = ';'.join(f'{key}:{value}' for key, value in props.items())
            (OUT / f'{dept}-{variant}.svg').write_text(
                f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" width="120" height="120" role="img" aria-label="iNDECS {name}" style="{styles}">\n{markup}</svg>\n', encoding='utf-8')
            symbols.append(f'<symbol id="{dept}-{variant}" viewBox="0 0 120 120">{markup}</symbol>')
    (OUT / 'sprite.svg').write_text('<svg xmlns="http://www.w3.org/2000/svg">\n' + '\n'.join(symbols) + '\n</svg>\n', encoding='utf-8')
    preview = ROOT / 'output/department-icons'
    preview.mkdir(parents=True, exist_ok=True)

    revision = hashlib.sha256((OUT / 'sprite.svg').read_bytes()).hexdigest()[:12]

    def icon(dept, variant='detailed', extra=''):
        return f'<svg class="department-icon department-icon-{dept} {extra}" viewBox="0 0 120 120" aria-hidden="true"><use href="../../public/img/departments/sprite.svg?v={revision}#{dept}-{variant}"/></svg>'

    cards = ''.join(f'<article class="content-card department-preview-card">{icon(dept, extra="department-icon-preview")}<h2>{name}</h2><p>{description}</p><div class="department-preview-sizes">{icon(dept)}{icon(dept,"compact","department-icon-compact")}<span>64 / 32 px</span></div><p><a href="../../public/img/departments/{dept}-detailed.svg">SVG terperinci</a> · <a href="../../public/img/departments/{dept}-compact.svg">SVG ringkas</a></p></article>' for dept, _, name, description in DEPARTMENTS)
    (preview / 'index.html').write_text(f'''<!doctype html>
<html lang="ms" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Identiti jabatan — iNDECS</title><link rel="stylesheet" href="../../public/css/tokens.css"><link rel="stylesheet" href="../../public/css/indecs.css"><script src="preview.js" defer></script></head>
<body><main class="department-preview"><header class="department-preview-head"><div><h1>Identiti jabatan</h1><p>iNDECS · Pratonton aset SVG</p></div><button class="button button-secondary" id="preview-theme" type="button" aria-pressed="false">Mod gelap</button></header><section class="department-preview-grid" aria-label="Empat jabatan">{cards}</section><section class="content-card department-preview-system"><img src="../../public/img/indecs-mark.svg" alt="Logo iNDECS" width="64" height="64"><div><h2>iNDECS</h2><p>Sistem utama dan portal awam mengekalkan identiti teal.</p></div></section></main></body></html>\n''', encoding='utf-8')
    (preview / 'preview.js').write_text('''document.querySelector('#preview-theme').addEventListener('click', event => {
    const dark = document.documentElement.dataset.theme !== 'dark';
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    event.currentTarget.setAttribute('aria-pressed', String(dark));
    event.currentTarget.textContent = dark ? 'Mod terang' : 'Mod gelap';
});
''', encoding='utf-8')
    print('Built eight department SVG exports, shared sprite and browser preview.')


if __name__ == '__main__':
    main()
