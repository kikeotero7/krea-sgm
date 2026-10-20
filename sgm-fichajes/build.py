# Genera la versión para el enlace de vista previa (logo embebido) y la versión web instalable (index.html)
import base64, pathlib, sys
here = pathlib.Path(__file__).parent
src = (here / 'app.src.html').read_text()
logo = 'data:image/webp;base64,' + base64.b64encode((here / 'logo.webp').read_bytes()).decode()
out_preview = pathlib.Path(sys.argv[1]) if len(sys.argv) > 1 else here / 'preview.html'
out_preview.write_text(src.replace('__LOGO__', logo))
head, body = src.split('<header class="top">', 1)
page = f'''<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="description" content="Obras, equipo y fichajes de SGM Trabajos Integrales. Desarrollado por KREA Digital Studio.">
<meta name="theme-color" content="#19211b">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="SGM Fichajes">
<link rel="manifest" href="manifest.webmanifest">
<link rel="icon" href="icon-192.png">
<link rel="apple-touch-icon" href="icon-192.png">
{head.replace('<title>SGM Fichajes</title>', '<title>SGM · Control de obra | KREA Digital Studio</title>')}
<style>:root{{padding-top:env(safe-area-inset-top,0px);padding-bottom:env(safe-area-inset-bottom,0px)}}body{{margin:0}}[hidden]{{display:none!important}}img{{max-width:100%}}</style>
</head>
<body>
<header class="top">{body.replace('__LOGO__', 'logo.webp')}
</body>
</html>
'''
(here / 'index.html').write_text(page)
print('ok', len(page))
