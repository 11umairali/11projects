#!/usr/bin/env python3
"""Apply assets/brand/official_logo.png to Flutter, Android and iOS.
Run from any directory: python3 tools/apply_official_logo.py (requires Pillow).
Uses the approved PNG directly, preserving its glyph, primary tile and rounding.
"""
from pathlib import Path
import json
from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
PRIMARY = (37, 99, 235)
logo = Image.open(ROOT / 'assets/brand/official_logo.png').convert('RGBA')

def scaled(size):
    return logo.resize((size, size), Image.Resampling.LANCZOS)

def foreground(size, fraction):
    canvas = Image.new('RGBA', (size, size))
    n = round(size * fraction)
    canvas.alpha_composite(scaled(n), ((size-n)//2, (size-n)//2))
    return canvas

def monochrome(size, fraction):
    # The supplied glyph is white; exclude the blue tile from themed icons.
    mask = Image.new('L', logo.size)
    mask.putdata([a if min(r,g,b) > 225 else 0 for r,g,b,a in logo.getdata()])
    mark = Image.new('RGBA', logo.size, 'white'); mark.putalpha(mask)
    n = round(size*fraction); canvas = Image.new('RGBA',(size,size))
    canvas.alpha_composite(mark.resize((n,n),Image.Resampling.LANCZOS),((size-n)//2,(size-n)//2))
    return canvas

def splash(size):
    # 288dp canvas, centred 144dp official tile, safely inside Android's mask.
    return foreground(size, .5)

brand = ROOT/'lib/core/brand.dart'
brand.write_text(brand.read_text().replace("logoAsset = 'assets/brand/logo.png'", "logoAsset = 'assets/brand/official_logo.png'"))
pubspec = ROOT/'pubspec.yaml'
pubspec.write_text(pubspec.read_text().replace('  android: true\n  ios: true', '  android: ic_dahimail_launcher\n  ios: true'))
scaled(1024).save(ROOT/'assets/brand/logo.png')
scaled(1024).save(ROOT/'assets/icon/icon.png')
foreground(1024,.60).save(ROOT/'assets/icon/icon_fg.png')
monochrome(1024,.60).save(ROOT/'assets/icon/icon_mono.png')
splash(1152).save(ROOT/'assets/brand/splash_symbol.png')
res=ROOT/'android/app/src/main/res'
for density, legacy, adaptive, factor in [('mdpi',48,108,1),('hdpi',72,162,1.5),('xhdpi',96,216,2),('xxhdpi',144,324,3),('xxxhdpi',192,432,4)]:
    mip=res/f'mipmap-{density}'; draw=res/f'drawable-{density}'
    mip.mkdir(parents=True,exist_ok=True); draw.mkdir(parents=True,exist_ok=True)
    scaled(legacy).save(mip/'ic_dahimail_launcher.png')
    foreground(adaptive,.60).save(draw/'ic_dahimail_launcher_foreground.png')
    monochrome(adaptive,.60).save(draw/'ic_dahimail_launcher_monochrome.png')
    splash(round(288*factor)).save(draw/'dahimail_splash.png')
(res/'mipmap-anydpi-v26/ic_dahimail_launcher.xml').write_text('''<?xml version="1.0" encoding="utf-8"?>
<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">
    <background android:drawable="@color/ic_launcher_background"/>
    <foreground android:drawable="@drawable/ic_dahimail_launcher_foreground"/>
    <monochrome android:drawable="@drawable/ic_dahimail_launcher_monochrome"/>
</adaptive-icon>
''')
for styles in res.glob('values*/styles.xml'):
    styles.write_text(styles.read_text().replace('@drawable/splash_symbol','@drawable/dahimail_splash'))
manifest=ROOT/'android/app/src/main/AndroidManifest.xml'
manifest.write_text(manifest.read_text().replace('@mipmap/ic_launcher','@mipmap/ic_dahimail_launcher'))
icons=ROOT/'ios/Runner/Assets.xcassets/AppIcon.appiconset'
opaque=Image.new('RGB',logo.size,PRIMARY);opaque.paste(logo,mask=logo.getchannel('A'))
for item in json.loads((icons/'Contents.json').read_text())['images']:
    if 'filename' in item:
        size=round(float(item['size'].split('x')[0])*float(item['scale'].rstrip('x')))
        opaque.resize((size,size),Image.Resampling.LANCZOS).save(icons/item['filename'])
launch=ROOT/'ios/Runner/Assets.xcassets/LaunchImage.imageset'
for name,factor in [('LaunchImage.png',1),('LaunchImage@2x.png',2),('LaunchImage@3x.png',3)]:
    splash(288*factor).save(launch/name)
print('Applied official logo to Flutter assets, Android launcher/splash and iOS icon/launch assets.')
