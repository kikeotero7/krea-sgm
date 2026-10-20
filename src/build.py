import base64, os, json, shutil
from PIL import Image
os.chdir(os.path.dirname(os.path.abspath(__file__)))
b=lambda f:'data:image/webp;base64,'+base64.b64encode(open(f,'rb').read()).decode()
src=open('src.html',encoding='utf-8').read()
for k,f in (('__LOGO_FULL__','logo-full-s.webp'),('__LOGO_MARK__','logo-mark-s.webp'),('__KV_ARQUITECTURA__','photos/kv-arquitectura.webp')):
    src=src.replace(k,b(f))
open('sgm-presupuestos.html','w',encoding='utf-8').write(src)   # demo (artifact)
# real app
real=src.replace('const DEMO=true;','const DEMO=false;',1)
title_end=real.index('</title>')+len('</title>')
head_extra='''
<link rel="manifest" href="manifest.webmanifest">
<link rel="icon" type="image/png" sizes="192x192" href="icon-192.png">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="SGM">
<meta name="robots" content="noindex, nofollow">
<meta name="description" content="Presupuestos, facturas, clientes y obras de Construcciones SGM.">'''
reset='<style>:root{color-scheme:light;padding-top:env(safe-area-inset-top,0px);padding-bottom:env(safe-area-inset-bottom,0px)}body{margin:0;font:14px system-ui,sans-serif}img{max-width:100%}[hidden]{display:none!important}</style>'
doc='<!doctype html>\n<html lang="es">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">\n'+reset+'\n'+real[:title_end]+head_extra+'\n'
rest=real[title_end:]
# move <link>/<script src>/<style> that precede the app div into head
cut=rest.index('<div class="app" id="app"></div>')
doc+=rest[:cut]+'\n</head>\n<body>\n'+rest[cut:]+'\n</body>\n</html>\n'
d='dist'; shutil.rmtree(d,ignore_errors=True); os.makedirs(d)
open(f'{d}/index.html','w',encoding='utf-8').write(doc)
# icons
mark=Image.open('logo-mark.png').convert('RGBA')
def icon(size,pad,name,bg=(255,255,255,255),radius=0):
    im=Image.new('RGBA',(size,size),bg)
    w=int(size*(1-2*pad)); h=int(mark.size[1]*w/mark.size[0]); m=mark.resize((w,h),Image.LANCZOS)
    im.alpha_composite(m,((size-w)//2,(size-h)//2))
    im.convert('RGB').save(f'{d}/{name}',optimize=True)
icon(192,.1,'icon-192.png'); icon(512,.1,'icon-512.png'); icon(512,.2,'icon-512-maskable.png'); icon(180,.1,'apple-touch-icon.png')
json.dump({"name":"SGM · Presupuestos","short_name":"SGM","description":"Presupuestos, facturas, clientes y obras de Construcciones SGM.","lang":"es","start_url":"./","scope":"./","display":"standalone","orientation":"any","background_color":"#f2f5f0","theme_color":"#14741a",
 "icons":[{"src":"icon-192.png","sizes":"192x192","type":"image/png"},{"src":"icon-512.png","sizes":"512x512","type":"image/png"},{"src":"icon-512-maskable.png","sizes":"512x512","type":"image/png","purpose":"maskable"}]},
 open(f'{d}/manifest.webmanifest','w',encoding='utf-8'),ensure_ascii=False,indent=1)
open(f'{d}/sw.js','w').write('''// SGM · app sin conexión: guarda la app y las librerías para abrirla sin internet
const CACHE='sgm-v1';
const SHELL=['./','./index.html','./manifest.webmanifest','./icon-192.png','./icon-512.png','./apple-touch-icon.png'];
self.addEventListener('install',e=>{e.waitUntil(caches.open(CACHE).then(c=>c.addAll(SHELL)).then(()=>self.skipWaiting()))});
self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(ks=>Promise.all(ks.filter(k=>k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()))});
self.addEventListener('fetch',e=>{
  const r=e.request;if(r.method!=='GET')return;
  const u=new URL(r.url);
  if(r.mode==='navigate'){e.respondWith(fetch(r).then(res=>{const c=res.clone();caches.open(CACHE).then(x=>x.put('./index.html',c));return res}).catch(()=>caches.match('./index.html')));return}
  if(u.origin===location.origin||/cdnjs\\.cloudflare\\.com|fonts\\.(googleapis|gstatic)\\.com/.test(u.host)){
    e.respondWith(caches.match(r).then(hit=>{const net=fetch(r).then(res=>{if(res.ok||res.type==='opaque'){const c=res.clone();caches.open(CACHE).then(x=>x.put(r,c))}return res}).catch(()=>hit);return hit||net}))}
});
''')
open(f'{d}/.htaccess','w').write('''# Forzar HTTPS (necesario para instalar la app y compartir archivos)
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
# Que el navegador compruebe siempre si hay versión nueva
<FilesMatch "\\.(html|js|webmanifest)$">
  Header set Cache-Control "no-cache"
</FilesMatch>
AddType application/manifest+json .webmanifest
''')
print(os.listdir(d), os.path.getsize(f'{d}/index.html'))
