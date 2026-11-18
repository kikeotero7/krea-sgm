"""Mete en la tarifa de materiales los precios de tienda (Leroy Merlin, Obramat…) de los CSV de app/datos/precios/.

Uso: python3 tools/materiales_desde_csv.py   (y después python3 tools/tarifa_a_app.py para pasarlo a la app)

- Lista de partida: app/datos/materiales-origen.json (copia de la tarifa de materiales antes de la primera
  importación; se crea sola la primera vez). Resultado: app/datos/materiales-base.json.
- CSV (UTF-8, separados por «;»): tienda;capitulo;codigo;descripcion;ref;unidad;pvp_iva;url;nota
- Se pueden añadir más CSV cuando lleguen: se leen todos en orden de nombre y se vuelve a generar todo, sin
  duplicar (cada material se identifica por tienda + referencia, o por tienda + código si no tiene referencia).
  Si el mismo material sale en dos CSV, manda el del archivo más reciente.
- Un material de la lista que aparece en el CSV se sustituye por sus equivalentes con precio (uno por tienda).
  Si en el CSV no tiene precio en ninguna tienda, se deja como estaba.
- Precio de la app = pvp_iva / 1,21 (sin IVA, 2 decimales). Se guardan también pvp_iva, tienda, ref y url.
- Deja un informe en app/datos/precios/informe.txt: totales, repetidos y precios raros para revisar.
"""
import csv, glob, json, os, re, shutil, unicodedata

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DAT = os.path.join(ROOT, 'app', 'datos')
ORIGEN, SALIDA, CSVS = os.path.join(DAT, 'materiales-origen.json'), os.path.join(DAT, 'materiales-base.json'), os.path.join(DAT, 'precios')
UNIDADES = {'ud', 'm2', 'ml', 'kg', 'l', 'saco', 'rollo', 'caja'}
CORTO = {'leroy merlin': 'LM', 'obramat': 'OB'}

if not os.path.exists(ORIGEN):
    shutil.copy(SALIDA, ORIGEN)
origen = json.load(open(ORIGEN, encoding='utf-8'))
caps = {c['id']: c for c in origen['capitulos']}
por_codigo = {p['codigo']: (c['id'], p) for c in origen['capitulos'] for p in c['partidas']}

def norm(s):
    s = unicodedata.normalize('NFD', str(s or '')).encode('ascii', 'ignore').decode().lower()
    return re.sub(r'[^a-z0-9]+', ' ', s).strip()
def num(v):
    v = str(v or '').strip().replace('€', '').replace(' ', '')
    if not v: return None
    if ',' in v and '.' in v: v = v.replace('.', '').replace(',', '.')
    else: v = v.replace(',', '.')
    try: return float(v)
    except ValueError: return None

avisos, filas, archivos = [], {}, sorted(glob.glob(os.path.join(CSVS, '*.csv')))
for f in archivos:
    with open(f, encoding='utf-8-sig', newline='') as fh:
        for i, r in enumerate(csv.DictReader(fh, delimiter=';'), start=2):
            r = {k.strip().lower(): (v or '').strip() for k, v in r.items() if k}
            tienda, cap, cod, ref = r.get('tienda', ''), r.get('capitulo', ''), r.get('codigo', ''), r.get('ref', '')
            donde = f'{os.path.basename(f)}:{i}'
            if cap not in caps: avisos.append(f'{donde} capítulo desconocido «{cap}» (fila ignorada)'); continue
            if cod and cod not in por_codigo: avisos.append(f'{donde} código «{cod}» no está en la lista (se trata como nuevo)'); cod = ''
            if cod and por_codigo[cod][0] != cap: avisos.append(f'{donde} {cod} es de {por_codigo[cod][0]}, no de {cap} (se usa {por_codigo[cod][0]})'); cap = por_codigo[cod][0]
            uni = r.get('unidad', '').lower().replace('m²', 'm2')
            pvp = num(r.get('pvp_iva'))
            if pvp is not None and uni not in UNIDADES: avisos.append(f'{donde} unidad «{uni}» no válida → ud'); uni = 'ud'
            # clave estable: misma tienda y referencia (o código si no hay referencia) = mismo material
            clave = (norm(tienda), cod, ref or norm(r.get('descripcion'))) if cod else (norm(tienda), '', ref or norm(r.get('descripcion')))
            if clave in filas and filas[clave]['_f'] == f: avisos.append(f'{donde} repetido dentro del mismo archivo: {tienda} {ref or r.get("descripcion")}')
            filas[clave] = dict(r, tienda=tienda, capitulo=cap, codigo=cod, ref=ref, unidad=uni, pvp=pvp, _f=f, _donde=donde)

def item(r, codigo):
    t = CORTO.get(norm(r['tienda']), norm(r['tienda'])[:2].upper())
    nombre = f"{r['descripcion']} ({r['tienda']}{' ref. ' + r['ref'] if r['ref'] else ''})"
    return {'codigo': codigo, 'descripcion': nombre, 'unidad': r['unidad'], 'precio': round(r['pvp'] / 1.21, 2), 'pvp_iva': round(r['pvp'], 2),
            'tienda': r['tienda'], 'ref': r['ref'], 'url': r.get('url', ''), 'nota': r.get('nota', ''), 'fecha': os.path.basename(r['_f'])[-14:-4]}

raros, salida = [], json.loads(json.dumps(origen))
salida['notas'] = origen.get('notas', '') + ' Precios de tienda importados de los CSV de app/datos/precios/ con tools/materiales_desde_csv.py.'
nombres = {}
for c in salida['capitulos']:
    nuevas = []
    for p in c['partidas']:
        todas = [r for r in filas.values() if r['codigo'] == p['codigo']]
        eq = sorted([r for r in todas if r['pvp']], key=lambda r: norm(r['tienda']), reverse=True)
        # Si la tienda de la que venía el precio anterior no aparece en el CSV, ese material se queda como estaba
        if not eq or not any(norm(r['tienda']) and norm(r['tienda']) in norm(p.get('tienda', '')) for r in todas):
            nuevas.append(p)
        if not eq:
            continue
        for r in eq:
            x = item(r, p['codigo'] + '-' + CORTO.get(norm(r['tienda']), 'XX'))
            ant = p.get('pvp_iva')
            if ant and (x['pvp_iva'] > ant * 2.5 or x['pvp_iva'] < ant / 2.5) and r['unidad'] == p['unidad']:
                raros.append(f"{r['_donde']} {p['codigo']} {r['tienda']}: {x['pvp_iva']} € con IVA (antes {ant} €, {p.get('tienda', '')}) · {r['descripcion']}")
            if r['unidad'] != p['unidad']:
                raros.append(f"{r['_donde']} {p['codigo']} {r['tienda']}: unidad {r['unidad']} (en la lista {p['unidad']}) · {r['descripcion']}")
            nuevas.append(x)
    for r in sorted((r for r in filas.values() if not r['codigo'] and r['capitulo'] == c['id'] and r['pvp']), key=lambda r: (norm(r['tienda']), norm(r['descripcion']))):
        cod = 'N-' + CORTO.get(norm(r['tienda']), 'XX') + '-' + (r['ref'] or norm(r['descripcion'])[:24].replace(' ', '-'))
        nuevas.append(item(r, cod))
    # El mismo producto de la misma tienda puede ser equivalente de dos materiales: en un mismo capítulo sale una sola vez
    vistos, unicas = set(), []
    for x in nuevas:
        k = (norm(x.get('tienda')), x.get('ref')) if x.get('ref') else None
        if k and k in vistos: continue
        if k: vistos.add(k)
        unicas.append(x)
    nuevas = unicas
    for x in nuevas:
        k = norm(x['descripcion'])
        if k in nombres: avisos.append(f"nombre repetido: «{x['descripcion']}» ({nombres[k]} y {x['codigo']})")
        nombres[k] = x['codigo']
    c['partidas'] = nuevas

for r in filas.values():
    if r['pvp'] is not None and (r['pvp'] <= 0 or r['pvp'] > 5000): raros.append(f"{r['_donde']} precio fuera de rango: {r['pvp']} · {r['descripcion']}")
json.dump(salida, open(SALIDA, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)

tot = sum(len(c['partidas']) for c in salida['capitulos'])
por_tienda = {}
for c in salida['capitulos']:
    for p in c['partidas']: por_tienda[p.get('tienda', '?')] = por_tienda.get(p.get('tienda', '?'), 0) + 1
lineas = [f'Archivos: {", ".join(os.path.basename(f) for f in archivos) or "ninguno"}', f'Materiales en la tarifa: {tot}',
          'Por tienda: ' + ', '.join(f'{k} {v}' for k, v in sorted(por_tienda.items(), key=lambda x: -x[1])), '',
          'Por capítulo:'] + [f'  {c["id"]}: {len(c["partidas"])}' for c in salida['capitulos']]
sin = [r for r in filas.values() if r['pvp'] is None]
lineas += ['', f'Filas sin precio (sin equivalente en esa tienda): {len(sin)}', '', f'Avisos ({len(avisos)}):'] + ['  ' + a for a in avisos]
lineas += ['', f'Precios o unidades para revisar ({len(raros)}):'] + ['  ' + a for a in raros]
os.makedirs(CSVS, exist_ok=True)
open(os.path.join(CSVS, 'informe.txt'), 'w', encoding='utf-8').write('\n'.join(lineas) + '\n')
print('\n'.join(lineas[:20]))
print(f'… informe completo en {os.path.join(CSVS, "informe.txt")}')
