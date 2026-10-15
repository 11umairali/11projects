#!/usr/bin/env python3
"""
Rebrand the app from one file (brand.json):  name, tagline, server URL, mail domain, package id, return-link scheme,
brand colour, logo, launcher icons, launch screen (logo + name + tagline), notification icon.

    pip install pillow
    python tools/rebrand.py            (run from the project folder)

Then:  flutter pub get  ->  flutter pub run flutter_launcher_icons  ->  flutter clean  ->  flutter build ...
Safe to run again after every change of brand.json.
"""
import colorsys, json, os, re, shutil, sys
from pathlib import Path

try:
    from PIL import Image, ImageDraw, ImageFont
except ImportError:
    sys.exit('Pillow is missing.  Run:  pip install pillow')

ROOT = Path(__file__).resolve().parent.parent
CFG = json.loads((ROOT / (sys.argv[1] if len(sys.argv) > 1 else 'brand.json')).read_text(encoding='utf-8'))
log = []

def say(msg): print('  ' + msg)
def read(p):
    with open(p, 'r', encoding='utf-8', newline='') as f: return f.read()
def write(p, s):
    with open(p, 'w', encoding='utf-8', newline='') as f: f.write(s)

# ───────────────────────── colours ─────────────────────────
def hex_rgb(h):
    h = h.lstrip('#'); return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))
def rgb_hex(c): return '#%02X%02X%02X' % tuple(max(0, min(255, int(round(v)))) for v in c)
def mix(a, b, t): return tuple(a[i] + (b[i] - a[i]) * t for i in range(3))
def shade(c, dl):
    h, l, s = colorsys.rgb_to_hls(*[v / 255 for v in c]); l = max(0, min(1, l + dl))
    return tuple(v * 255 for v in colorsys.hls_to_rgb(h, l, s))
def dart(c): return 'Color(0xFF%s)' % rgb_hex(c)[1:]

P = hex_rgb(CFG['primaryColor'])
TOP = shade(P, +0.07)                 # gradient start (lighter)
MID = mix(TOP, P, 0.5)                # native launch-screen colour (middle of the gradient)
SPLASH_BG = hex_rgb(CFG['splashBackground']) if CFG.get('splashBackground') else MID   # launch-screen colour
SPLASH_TEXT = hex_rgb(CFG.get('splashTextColor', '#FFFFFF'))                          # app name on the launch screen
SOFT = mix(P, (255, 255, 255), 0.90)  # tinted fills, light mode
SOFT_DARK = mix(P, (20, 19, 30), 0.78)
ON_DARK = mix(P, (255, 255, 255), 0.55)

# ───────────────────────── 1. Dart source ─────────────────────────
def set_const(path, name, value):
    s = read(path)
    new, n = re.subn(r"(static const %s = )'[^']*'" % name, lambda m: m.group(1) + "'" + value.replace("'", "\\'") + "'", s, 1)
    if n: write(path, new)
    return n

def dart_files():
    cfg = ROOT / 'lib/core/config.dart'
    if not cfg.exists(): sys.exit('lib/core/config.dart not found – run this from the project folder.')
    set_const(cfg, 'appName', CFG['appName']); set_const(cfg, 'tagline', CFG['tagline'])
    set_const(cfg, 'mailDomain', CFG['mailDomain']); set_const(cfg, 'urlScheme', CFG['urlScheme'])
    s = read(cfg)
    s = re.sub(r"(defaultValue: )'https?://[^']*'(\);\n  static const apiBase)", lambda m: m.group(1) + "'" + CFG['baseUrl'].rstrip('/') + "'" + m.group(2), s, 1)
    write(cfg, s); say('lib/core/config.dart  (name, tagline, server, mail domain, scheme)')

    theme = ROOT / 'lib/core/theme.dart'; s = read(theme)
    for name, col in [('primary', P), ('primaryOnDark', ON_DARK)]:
        s = re.sub(r"(static const %s = )Color\(0x[0-9A-Fa-f]{8}\)" % name, lambda m: m.group(1) + dart(col), s, 1)
    write(theme, s)
    brand = ROOT / 'lib/core/brand.dart'; s = read(brand)
    s = re.sub(r"(static const splashColor = )Color\(0x[0-9A-Fa-f]{8}\)", lambda m: m.group(1) + dart(SPLASH_BG), s, 1)
    s = re.sub(r"(splashGradient = LinearGradient\(colors: \[)Color\(0x[0-9A-Fa-f]{8}\), Color\(0x[0-9A-Fa-f]{8}\)", lambda m: m.group(1) + dart(SPLASH_BG) + ', ' + dart(SPLASH_BG), s, 1)
    write(brand, s); say('lib/core/theme.dart + brand.dart  (colours %s)' % CFG['primaryColor'])

# ───────────────────────── 2. Android native ─────────────────────────
def android_roots():
    return [r for r in (ROOT / 'native_config/android', ROOT / 'android') if (r / 'app').exists()]

def android_files():
    new_id = CFG['applicationId']; scheme = CFG['urlScheme']
    scheme = CFG['urlScheme']
    for root in android_roots():
        gradle = next((root / 'app' / n for n in ('build.gradle.kts', 'build.gradle') if (root / 'app' / n).exists()), None)
        old_id = None
        if gradle:
            g = read(gradle); m = re.search(r'applicationId\s*=\s*"([^"]+)"', g)
            old_id = m.group(1) if m else None
            g = re.sub(r'(applicationId\s*=\s*)"[^"]+"', lambda m: m.group(1) + '"' + new_id + '"', g)
            g = re.sub(r'(namespace\s*=\s*)"[^"]+"', lambda m: m.group(1) + '"' + new_id + '"', g)
            write(gradle, g)
        man = root / 'app/src/main/AndroidManifest.xml'
        if man.exists():
            s = read(man)
            s = re.sub(r'(android:label=)"[^"]*"', lambda m: m.group(1) + '"' + CFG['appName'] + '"', s, 1)
            # only the return-link activity – NOT the https entry inside <queries>
            s = re.sub(r'(CallbackActivity.*?<data android:scheme=)"[^"]*"', lambda m: m.group(1) + '"' + scheme + '"', s, 1, re.S)
            s = re.sub(r'redirect to \w+://oauth', 'redirect to %s://oauth' % scheme, s)
            write(man, s)
        kt_root = root / 'app/src/main/kotlin'
        if kt_root.exists() and old_id:
            old_dir = kt_root.joinpath(*old_id.split('.')); new_dir = kt_root.joinpath(*new_id.split('.'))
            src = old_dir / 'MainActivity.kt'
            if src.exists() and old_dir != new_dir:
                new_dir.mkdir(parents=True, exist_ok=True)
                t = read(src); t = re.sub(r'^package\s+[\w.]+', 'package ' + new_id, t, 1, re.M)
                write(new_dir / 'MainActivity.kt', t); src.unlink()
                p = old_dir
                while p != kt_root and p.exists() and not any(p.iterdir()): p.rmdir(); p = p.parent
            elif src.exists():
                t = read(src); write(src, re.sub(r'^package\s+[\w.]+', 'package ' + new_id, t, 1, re.M))
        res = root / 'app/src/main/res/values/colors.xml'
        if res.exists():
            s = read(res)
            s = re.sub(r'(<color name="ic_launcher_background">)#[0-9A-Fa-f]{6}', lambda m: m.group(1) + rgb_hex(ICON_BG if COLOR_MODE else P), s)
            s = re.sub(r'(<color name="splash_bg">)#[0-9A-Fa-f]{6}', lambda m: m.group(1) + rgb_hex(SPLASH_BG), s)
            write(res, s)
        for styles in (root / 'app/src/main/res').glob('values*/styles.xml'):
            text = read(styles).replace('?android:colorBackground', '@color/splash_bg')
            if 'windowSplashScreenBackground' in text:
                for key, value in [('android:windowBackground', '@color/splash_bg'),
                                   ('android:statusBarColor', '@color/splash_bg'),
                                   ('android:navigationBarColor', '@color/splash_bg'),
                                   ('android:windowLightStatusBar', 'true'),
                                   ('android:windowLightNavigationBar', 'true')]:
                    item = '<item name="%s">%s</item>' % (key, value)
                    if ('<item name="%s">' % key) not in text.split('</style>')[0]:
                        text = text.replace('<item name="windowSplashScreenBackground">', item + '\n        <item name="windowSplashScreenBackground">', 1)
                text = text.replace('brand colour + logo/name', 'white background + logo/name')
                write(styles, text)
        say('%s  (id %s, label, return scheme, colours)' % (root.relative_to(ROOT), new_id))
    ios = ROOT / 'native_config/ios/README_iOS.txt'
    if ios.exists():
        write(ios, re.sub(r'(CFBundleURLSchemes</key><array><string>)[^<]*', lambda m: m.group(1) + scheme, read(ios)))
    pub = ROOT / 'pubspec.yaml'
    if pub.exists():
        s = read(pub)
        s = re.sub(r'(adaptive_icon_background:\s*)"#[0-9A-Fa-f]{6}"', lambda m: m.group(1) + '"' + rgb_hex(ICON_BG if COLOR_MODE else P) + '"', s)
        s = re.sub(r'(?m)^description:.*$', 'description: %s mobile app.' % CFG['appName'], s, 1)
        write(pub, s); say('pubspec.yaml  (icon colour, description)')

# ───────────────────────── 2b. iOS (only if an ios/ folder exists) ─────────────────────────
def ios_files():
    ios = ROOT / 'ios'
    if not (ios / 'Runner').exists(): return
    name, scheme, new_id = CFG['appName'], CFG['urlScheme'], CFG['applicationId']
    # Info.plist: names, return-link scheme, permission texts (Face ID is REQUIRED by the app lock)
    plist = ios / 'Runner/Info.plist'
    if plist.exists():
        s = read(plist); nl = '\r\n' if '\r\n' in s else '\n'
        s = re.sub(r'(<key>CFBundleDisplayName</key>\s*<string>)[^<]*', lambda m: m.group(1) + name, s)
        s = re.sub(r'(<key>CFBundleName</key>\s*<string>)[^<]*', lambda m: m.group(1) + name, s)
        def add(key, body):
            nonlocal s
            if '<key>%s</key>' % key in s: return
            i = s.rfind('</dict>')
            s = s[:i] + ('\t<key>%s</key>' % key) + nl + body + nl + s[i:]
        add('NSFaceIDUsageDescription', '\t<string>%s uses Face ID to unlock the app.</string>' % name)
        add('NSMicrophoneUsageDescription', '\t<string>%s uses the microphone for audio calls.</string>' % name)
        add('NSCameraUsageDescription', '\t<string>Take a profile photo or logo.</string>')
        add('NSPhotoLibraryUsageDescription', '\t<string>Choose a profile photo or logo.</string>')
        if '<key>CFBundleURLTypes</key>' in s:
            s = re.sub(r'(<key>CFBundleURLSchemes</key>\s*<array>\s*<string>)[^<]*', lambda m: m.group(1) + scheme, s)
        else:
            add('CFBundleURLTypes', nl.join(['\t<array>', '\t\t<dict>', '\t\t\t<key>CFBundleURLSchemes</key>', '\t\t\t<array>', '\t\t\t\t<string>%s</string>' % scheme, '\t\t\t</array>', '\t\t</dict>', '\t</array>']))
        write(plist, s)
    # project.pbxproj: bundle id (app + tests)
    pb = ios / 'Runner.xcodeproj/project.pbxproj'
    if pb.exists():
        s = read(pb)
        ids = re.findall(r'PRODUCT_BUNDLE_IDENTIFIER = ([\w.\-]+);', s)
        old = next((i for i in ids if not i.endswith('.RunnerTests')), None)
        if old:
            s = s.replace('PRODUCT_BUNDLE_IDENTIFIER = %s.RunnerTests;' % old, 'PRODUCT_BUNDLE_IDENTIFIER = %s.RunnerTests;' % new_id)
            s = s.replace('PRODUCT_BUNDLE_IDENTIFIER = %s;' % old, 'PRODUCT_BUNDLE_IDENTIFIER = %s;' % new_id)
            write(pb, s)
    # launch screen: brand colour + the same logo/name picture as Android
    sb = ios / 'Runner/Base.lproj/LaunchScreen.storyboard'
    if sb.exists():
        s = read(sb)
        s = re.sub(r'(<color key="backgroundColor" )red="[^"]*" green="[^"]*" blue="[^"]*"', lambda m: m.group(1) + 'red="%.6f" green="%.6f" blue="%.6f"' % tuple(v / 255 for v in SPLASH_BG), s, 1)
        s = re.sub(r'<image name="LaunchImage" width="\d+" height="\d+"/>', '<image name="LaunchImage" width="288" height="288"/>', s)
        write(sb, s)
    imgs = ios / 'Runner/Assets.xcassets/LaunchImage.imageset'
    if imgs.exists():
        for fn, f in (('LaunchImage.png', 1), ('LaunchImage@2x.png', 2), ('LaunchImage@3x.png', 3)):
            splash_symbol(f).save(imgs / fn)
    say('ios/  (display name, bundle id, return scheme, Face ID + camera texts, launch screen colour + picture)')
    say('ios app icons come from:  flutter pub run flutter_launcher_icons')

# ───────────────────────── 3. pictures ─────────────────────────
FONT_BOLD = [CFG.get('fontBold'), 'C:/Windows/Fonts/arialbd.ttf', '/Library/Fonts/Arial Bold.ttf', '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
             '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf']
FONT_REG = [CFG.get('fontRegular'), 'C:/Windows/Fonts/arial.ttf', '/Library/Fonts/Arial.ttf', '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf']
def font(paths, size, weight=400):
    for p in paths:
        if p:
            path = Path(p) if Path(p).is_absolute() else ROOT / p
            if path.exists():
                face = ImageFont.truetype(str(path), size)
                try:
                    axes = face.get_variation_axes()
                    face.set_variation_by_axes([weight if axis['name'] == b'Weight' else axis['default'] for axis in axes])
                except (AttributeError, OSError): pass
                return face
    try: return ImageFont.load_default(size)
    except TypeError: return ImageFont.load_default()

def logo_mask():
    """Alpha mask of the customer's logo (cropped), or None to use the built-in mark."""
    lp = CFG.get('logo')
    if not lp: return None
    path = ROOT / lp
    if not path.exists(): sys.exit('logo file not found: %s' % path)
    im = Image.open(path).convert('RGBA'); a = im.getchannel('A')
    global LOGO_RGBA
    if CFG.get('logoStyle') == 'original':
        # Preserve the supplied artwork, including its background and spacing.
        LOGO_RGBA = im
        return im.convert('L').point(lambda v: max(0, min(255, (v - 180) * 255 // 75)))
    if a.getextrema()[0] == 255:                       # no transparency → derive a mask from brightness
        g = im.convert('L'); corner = g.getpixel((0, 0))
        a = g.point(lambda v: 255 - v) if corner > 127 else g
        say('logo has no transparency – using brightness as mask (a transparent PNG gives a better result)')
    bb = a.getbbox()
    rgba = im.copy(); rgba.putalpha(a); LOGO_RGBA = rgba.crop(bb) if bb else None
    return a.crop(bb) if bb else None

LOGO_RGBA = None
LOGO = logo_mask()
COLOR_MODE = CFG.get('logoStyle', 'silhouette') in ('color', 'original') and LOGO is not None
ICON_BG = hex_rgb(CFG.get('iconBackground', '#FFFFFF'))

def builtin_mark(size, w_frac, flap_frac=0.085):
    S = size * 4; m = Image.new('L', (S, S), 0); d = ImageDraw.Draw(m)
    w = int(S * w_frac); h = int(w * 0.74); cx = cy = S // 2
    x0, y0, x1, y1 = cx - w // 2, cy - h // 2, cx + w // 2, cy + h // 2; r = int(h * 0.22)
    d.rounded_rectangle((x0, y0, x1, y1), radius=r, fill=255)
    tx = x0 + int(w * 0.16)
    d.polygon([(tx, y1 - r), (tx + int(w * 0.24), y1 - r), (tx - int(w * 0.02), y1 + int(h * 0.30))], fill=255)
    d.rounded_rectangle((tx, y1 - r - 2, tx + int(w * 0.24), y1 - r + int(h * 0.10)), radius=6, fill=255)
    lw = max(3, int(h * flap_frac)); pad = int(lw * 1.5)
    d.line([(x0 + pad, y0 + pad + lw // 2), (cx, cy + int(h * 0.12)), (x1 - pad, y0 + pad + lw // 2)], fill=0, width=lw, joint='curve')
    for px, py in [(x0 + pad, y0 + pad + lw // 2), (x1 - pad, y0 + pad + lw // 2)]: d.ellipse((px - lw // 2, py - lw // 2, px + lw // 2, py + lw // 2), fill=0)
    bb = m.getbbox(); o = Image.new('L', (S, S), 0); o.paste(m, (S // 2 - (bb[0] + bb[2]) // 2, S // 2 - (bb[1] + bb[3]) // 2))
    return o.resize((size, size), Image.LANCZOS)

def mark_mask(size, w_frac):
    """Square mask, logo centred, `w_frac` = logo width as a fraction of the canvas (height is capped)."""
    if LOGO is None: return builtin_mark(size, w_frac)
    tw = size * w_frac; th = size * w_frac * 0.9
    sc = min(tw / LOGO.width, th / LOGO.height)
    lg = LOGO.resize((max(1, int(LOGO.width * sc)), max(1, int(LOGO.height * sc))), Image.LANCZOS)
    out = Image.new('L', (size, size), 0); out.paste(lg, ((size - lg.width) // 2, (size - lg.height) // 2)); return out

def white(size, w_frac):
    i = Image.new('RGBA', (size, size), (255, 255, 255, 0)); i.putalpha(mark_mask(size, w_frac)); return i

def mark(size, w_frac):
    """The logo as shown on coloured backgrounds: original colours (logoStyle = color) or a white silhouette."""
    if not COLOR_MODE: return white(size, w_frac)
    tw = size * w_frac; th = size * w_frac * 0.9
    sc = min(tw / LOGO_RGBA.width, th / LOGO_RGBA.height)
    lg = LOGO_RGBA.resize((max(1, int(LOGO_RGBA.width * sc)), max(1, int(LOGO_RGBA.height * sc))), Image.LANCZOS)
    out = Image.new('RGBA', (size, size), (0, 0, 0, 0)); out.alpha_composite(lg, ((size - lg.width) // 2, (size - lg.height) // 2)); return out

def gradient(size):
    im = Image.new('RGB', (size, size)); px = im.load()
    for y in range(size):
        for x in range(size):
            t = (x + y) / (2 * size); px[x, y] = tuple(int(TOP[i] + (P[i] - TOP[i]) * t) for i in range(3))
    return im

def wrap(text, fnt, maxw, draw):
    words, lines, cur = text.split(), [], ''
    for w in words:
        t = (cur + ' ' + w).strip()
        if draw.textlength(t, font=fnt) <= maxw or not cur: cur = t
        else: lines.append(cur); cur = w
    if cur: lines.append(cur)
    return lines[:3]

def splash_symbol(f):
    """288dp native canvas: centered 100dp tile, 24dp gap, Inter Semibold title.
    All artwork fits Android 12's 192dp splash mask.
    """
    scale = f * 3
    big = int(288 * scale)
    img = Image.new('RGBA', (big, big), (0, 0, 0, 0))
    tile_px = int(100 * scale)
    tile = rounded_tile(tile_px, 0.22, 0.82) if CFG.get('logoStyle') == 'tile' else mark(tile_px, 0.88)
    img.alpha_composite(tile, ((big - tile_px) // 2, int(64 * scale)))
    d = ImageDraw.Draw(img)
    name = CFG['appName']
    title_size = 24
    nf = font(FONT_BOLD, int(title_size * scale), weight=600)
    while d.textlength(name, font=nf) > 150 * scale and title_size > 12:
        title_size -= 1
        nf = font(FONT_BOLD, int(title_size * scale), weight=600)
    x = big / 2 - d.textlength(name, font=nf) / 2
    d.text((x, int(188 * scale)), name, font=nf, anchor='lt', fill=tuple(int(v) for v in SPLASH_TEXT) + (255,))
    return img.resize((int(288 * f), int(288 * f)), Image.LANCZOS)

def rounded_tile(size, radius_frac=0.22, logo_frac=0.62, bg=None):
    """A square tile in the icon background colour with ROUNDED corners (transparent outside) and the logo centred."""
    bg = bg or ICON_BG
    S = size * 2
    tile = Image.new('RGBA', (S, S), bg + (255,))
    tile.alpha_composite(mark(S, logo_frac))
    m = Image.new('L', (S, S), 0)
    ImageDraw.Draw(m).rounded_rectangle((0, 0, S - 1, S - 1), radius=int(S * radius_frac), fill=255)
    tile.putalpha(m)
    return tile.resize((size, size), Image.LANCZOS)

def flat(img, bg=(255, 255, 255)):
    out = Image.new('RGB', img.size, bg)
    out.paste(img, mask=img.getchannel('A') if img.mode == 'RGBA' else None)
    return out

def launcher_icons():
    """Writes what flutter_launcher_icons would write, so the project is complete even before you run it."""
    assets = ROOT / 'assets'
    tile = Image.open(assets / 'icon/icon.png').convert('RGBA')
    fg = Image.open(assets / 'icon/icon_fg.png').convert('RGBA')
    mono = Image.open(assets / 'icon/icon_mono.png').convert('RGBA')
    for root in android_roots():
        res = root / 'app/src/main/res'
        for dn, legacy, adaptive in (('mdpi', 48, 108), ('hdpi', 72, 162), ('xhdpi', 96, 216), ('xxhdpi', 144, 324), ('xxxhdpi', 192, 432)):
            (res / ('mipmap-' + dn)).mkdir(parents=True, exist_ok=True); (res / ('drawable-' + dn)).mkdir(parents=True, exist_ok=True)
            tile.resize((legacy, legacy), Image.LANCZOS).save(res / ('mipmap-' + dn) / 'ic_launcher.png')
            fg.resize((adaptive, adaptive), Image.LANCZOS).save(res / ('drawable-' + dn) / 'ic_launcher_foreground.png')
            mono.resize((adaptive, adaptive), Image.LANCZOS).save(res / ('drawable-' + dn) / 'ic_launcher_monochrome.png')
        adaptive_dir = res / 'mipmap-anydpi-v26'
        adaptive_dir.mkdir(parents=True, exist_ok=True)
        (adaptive_dir / 'ic_launcher.xml').write_text('''<?xml version="1.0" encoding="utf-8"?>
<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">
    <background android:drawable="@color/ic_launcher_background"/>
    <foreground android:drawable="@drawable/ic_launcher_foreground"/>
    <monochrome android:drawable="@drawable/ic_launcher_monochrome"/>
</adaptive-icon>
''')
        for old in ('drawable-nodpi/splash_icon.png', 'drawable-nodpi/splash_logo.png', 'drawable/launch_background.xml', 'drawable-v21/launch_background.xml'):
            if (res / old).exists(): (res / old).unlink()          # left over from older versions, not used any more
        for dn in ('mdpi', 'hdpi', 'xhdpi', 'xxhdpi', 'xxxhdpi'):
            for old in ('splash_art.png', 'splash_mark.png'):
                if (res / ('drawable-' + dn) / old).exists(): (res / ('drawable-' + dn) / old).unlink()
        say('%s/res  (launcher icons: legacy rounded, adaptive foreground, themed icon)' % root.relative_to(ROOT))
    ios = ROOT / 'ios/Runner/Assets.xcassets/AppIcon.appiconset'
    if ios.exists():
        import json
        base = flat(tile, ICON_BG if COLOR_MODE else P)               # iOS is opaque; match the icon background through its corners
        for it in json.loads((ios / 'Contents.json').read_text(encoding='utf-8')).get('images', []):
            if 'filename' not in it: continue
            px = int(round(float(it['size'].split('x')[0]) * float(it['scale'].rstrip('x'))))
            base.resize((px, px), Image.LANCZOS).save(ios / it['filename'])
        say('ios/…/AppIcon.appiconset  (all icon sizes)')

def images():
    assets = ROOT / 'assets'; (assets / 'icon').mkdir(parents=True, exist_ok=True); (assets / 'brand').mkdir(parents=True, exist_ok=True)
    if CFG.get('logoStyle') == 'original':
        # No redraw, recolouring or extra tile around the customer's logo.
        shutil.copyfile(ROOT / CFG['logo'], assets / 'brand/logo.png')
        flat(LOGO_RGBA, ICON_BG).resize((1024, 1024), Image.LANCZOS).save(assets / 'icon/icon.png')
        mark(1024, 0.70).save(assets / 'icon/icon_fg.png')
    elif CFG.get('logoStyle') == 'tile':
        tile = rounded_tile(1024, 0.22, 0.82)
        tile.save(assets / 'icon/icon.png')
        tile.save(assets / 'brand/logo.png')
        mark(1024, 0.55).save(assets / 'icon/icon_fg.png')
    elif COLOR_MODE:
        icon = rounded_tile(1024, 0.22, 0.62)                       # white tile, rounded edges, logo in its own colours
        icon.save(assets / 'icon/icon.png')
        rounded_tile(1024, 0.26, 0.62).save(assets / 'brand/logo.png')   # in-app logo (login screen …): same rounded white tile
        mark(1024, 0.70).save(assets / 'icon/icon_fg.png')           # adaptive foreground (the launcher adds a 16 % inset)
    else:
        icon = gradient(1024).convert('RGBA'); icon.alpha_composite(mark(1024, 0.60))
        icon.convert('RGB').save(assets / 'icon/icon.png'); icon.convert('RGB').save(assets / 'brand/logo.png')
        mark(1024, 0.55).save(assets / 'icon/icon_fg.png')
    white(1024, 0.55).save(assets / 'icon/icon_mono.png')
    white(512, 0.80).save(assets / 'brand/mark_white.png')           # always a white silhouette (used on coloured backgrounds)
    say('assets/icon + assets/brand  (launcher, adaptive, themed icon, in-app logo)')
    for root in android_roots():
        res = root / 'app/src/main/res'
        for dn, f in {'mdpi': 1, 'hdpi': 1.5, 'xhdpi': 2, 'xxhdpi': 3, 'xxxhdpi': 4}.items():
            (res / ('drawable-' + dn)).mkdir(parents=True, exist_ok=True)
            splash_symbol(f).save(res / ('drawable-' + dn) / 'splash_symbol.png')
            px = {'mdpi': 24, 'hdpi': 36, 'xhdpi': 48, 'xxhdpi': 72, 'xxxhdpi': 96}[dn]
            white(px, 0.88).save(res / ('drawable-' + dn) / 'ic_stat_notify.png')
        splash_symbol(4).save(assets / 'brand/splash_symbol.png')
        say('%s/res  (splash symbol = logo + name, notification icon)' % root.relative_to(ROOT))

if __name__ == '__main__':
    print('Rebranding "%s" …' % CFG['appName'])
    dart_files(); android_files(); images(); launcher_icons(); ios_files()
    if (ROOT / 'assets/brand/official_logo.png').exists():
        import runpy
        runpy.run_path(str(ROOT / 'tools/apply_official_logo.py'), run_name='__main__')
    print('\nDone. Next:')
    print('  1) flutter pub get')
    print('  2) (launcher icons are already written; running  flutter pub run flutter_launcher_icons  is optional)')
    print('  3) if you use the native_config folder:  xcopy native_config\\android android /E /I /Y')
    print('  4) flutter clean   then build (see BRANDING.md)')
    print('  5) SERVER: in app/Http/Middleware/MobileOAuthReturn.php change  dahimail://oauth  to  %s://oauth' % CFG['urlScheme'])
    print('     and set APP_URL in the website .env. iOS (Mac): set the bundle id and URL scheme in Xcode.')
