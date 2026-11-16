"""Mete la tarifa de app/datos/tarifa-base.json en src/src.html (CAPS, plantillas y palabras clave del modo básico).

Uso: python tools/tarifa_a_app.py   (vuelve a generar los tres bloques; se puede repetir tras cambiar la tarifa)
"""
import json, os, re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, 'src', 'src.html')
t = json.load(open(os.path.join(ROOT, 'app', 'datos', 'tarifa-base.json'), encoding='utf-8'))
UNIT = {'m2': 'm²', 'm3': 'm³', 'ml': 'ml', 'ud': 'ud', 'h': 'h', 'kg': 'kg', 'pa': 'pa', 'l': 'l', 'saco': 'saco', 'rollo': 'rollo', 'caja': 'caja'}
by_code = {p['codigo']: p for c in t['capitulos'] for p in c['partidas']}
# Materiales sueltos (solo material, precio de tienda sin IVA): van detrás, en capítulos «Materiales · …»
mat_path = os.path.join(ROOT, 'app', 'datos', 'materiales-base.json')
materiales = json.load(open(mat_path, encoding='utf-8'))['capitulos'] if os.path.exists(mat_path) else []
D = lambda code: by_code[code]['descripcion']
js = lambda x: json.dumps(x, ensure_ascii=False)

# --- capítulos ---
caps = ',\n'.join(' {id:%s,name:%s,items:[%s]}' % (js(c['id']), js(c['nombre']),
       ','.join('[%s,%s,%s]' % (js(p['descripcion']), js(UNIT[p['unidad']]), js(p['precio'])) for p in c['partidas']))
       for c in t['capitulos'] + materiales)
caps_block = 'const CAPS=[\n' + caps + '\n];'

# --- plantillas (código, cantidad, nota) ---
TPL = [
    ('bano', 'Baño', 'Reforma de baño', [('DEM-004', 22, '(2+2,5)×2×2,5 m − puerta'), ('DEM-005', 5), ('DEM-012', 3), ('DEM-020', 1), ('FON-001', 1), ('BAN-004', 4),
        ('SOL-008', 22), ('SOL-002', 5), ('PYL-007', 5), ('BAN-002', 1), ('CAR-017', 1), ('BAN-005', 1), ('BAN-007', 1), ('BAN-011', 1),
        ('ELE-004', 2), ('ELE-007', 2), ('ELE-018', 1), ('GES-011', 5)]),
    ('cocina', 'Cocina', 'Reforma de cocina', [('DEM-004', 18), ('DEM-005', 9), ('DEM-013', 3.5), ('DEM-020', 1), ('COC-013', 1), ('SOL-008', 18), ('SOL-002', 9),
        ('COC-002', 3.5), ('COC-006', 3.5), ('COC-009', 1), ('COC-010', 3), ('ELE-004', 2), ('ELE-007', 6), ('GES-011', 9)]),
    ('pintura', 'Pintura', 'Pintura de vivienda', [('DEM-022', 1), ('PIN-006', 220), ('PIN-001', 220), ('PIN-002', 80), ('PIN-007', 6)]),
    ('suelos', 'Suelos', 'Cambio de suelo', [('DEM-005', 60), ('DEM-007', 55), ('DEM-019', 1), ('SOL-013', 60), ('SOL-001', 60), ('SOL-012', 55)]),
    ('integral', 'Reforma integral', 'Reforma integral de vivienda · 100 m²', [('DEM-022', 1), ('DEM-018', 100), ('DEM-019', 3), ('ALB-001', 40), ('ALB-005', 180),
        ('ALB-014', 1), ('ALB-015', 1), ('ALB-016', 1), ('FON-003', 1), ('ELE-002', 100), ('ELE-014', 1), ('SOL-008', 40), ('SOL-001', 85), ('SOL-012', 90),
        ('BAN-002', 1), ('BAN-005', 1), ('BAN-007', 1), ('CAR-017', 1), ('COC-002', 4), ('COC-006', 4), ('MAD-001', 6), ('MAD-007', 2), ('CAR-002', 6),
        ('PYL-006', 30), ('PIN-001', 260), ('PIN-002', 100), ('CLI-002', 2), ('GES-001', 1), ('GES-012', 1)]),
]
def tline(x):
    code, qty, *rest = x
    return '[%s,%s%s]' % (js(D(code)), js(qty), (',' + js(rest[0])) if rest else '')
tpl_block = 'const TEMPLATES=[\n' + ',\n'.join(' {id:%s,name:%s,title:%s,lines:[%s]}' % (js(i), js(n), js(ti), ','.join(tline(x) for x in ls)) for i, n, ti, ls in TPL) + '\n];'

# --- palabras clave del modo básico: nombre antiguo de SGM -> partida nueva ---
OLD = {
    'Protección de zonas comunes': 'DEM-022', 'Retirada de pavimento': 'DEM-005', 'Retirada de azulejos': 'DEM-004', 'Demolición de tabique': 'DEM-001',
    'Retirada de sanitarios': 'DEM-012', 'Desmontaje de bañera': 'DEM-011', 'Carga y retirada de escombros': 'DEM-019', 'Tabique de ladrillo': 'ALB-001',
    'Tabique de placa de yeso': 'PYL-001', 'Tabique hidrófugo de yeso laminado': 'PYL-002', 'Trasdosado de pared': 'PYL-004', 'Enfoscado de paredes': 'ALB-007',
    'Enlucido y alisado': 'ALB-005', 'Apertura y cierre de rozas': 'ALB-010', 'Reparación de grietas': 'ALB-019', 'Recrecido de mortero': 'ALB-008',
    'Mano de obra de albañil': 'ALB-021', 'Nivelación de suelo': 'SOL-013', 'Colocación de porcelánico': 'SOL-002', 'Colocación de gres cerámico': 'SOL-003',
    'Colocación de suelo laminado': 'MAD-010', 'Colocación de vinilo': 'MAD-012', 'Colocación de microcemento': 'SOL-018', 'Alicatado de paredes': 'SOL-008',
    'Colocación de rodapié': 'SOL-011', 'Rejuntado': 'SOL-015', 'Protección de mobiliario': 'DEM-022', 'Eliminación de gotelé': 'PIN-004',
    'Preparación y reparación de paredes': 'PIN-006', 'Pintura de paredes': 'PIN-001', 'Pintura de techos': 'PIN-002', 'Pintura de fachada': 'PIN-010',
    'Esmaltado de puertas': 'PIN-007', 'Tratamiento antihumedad': 'ALB-020', 'Renovación de tubería de agua': 'FON-007', 'Renovación de desagües': 'FON-005',
    'Punto de agua y desagüe': 'FON-004', 'Instalación de inodoro': 'BAN-005', 'Instalación de lavabo': 'BAN-008', 'Instalación de plato de ducha': 'BAN-002',
    'Instalación de bañera': 'BAN-013', 'Instalación de mampara': 'CAR-017', 'Instalación de grifería': 'BAN-010', 'Conexión de fregadero': 'COC-011',
    'Conexión de termo': 'FON-012', 'Sustitución de llave de paso': 'FON-006', 'Prueba de estanqueidad': 'FON-017', 'Renovación de cableado': 'ELE-002',
    'Cuadro eléctrico': 'ELE-003', 'Punto de enchufe': 'ELE-007', 'Punto de luz': 'ELE-004', 'Interruptor o conmutador': 'ELE-005',
    'Línea dedicada de electrodoméstico': 'ELE-011', 'Instalación de luminaria': 'ELE-016', 'Iluminación LED': 'ELE-015', 'Instalación de extractor': 'ELE-018',
    'Punto de datos': 'ELE-010', 'Puerta maciza abatible con instalación': 'MAD-001', 'Puerta hueca abatible con instalación': 'MAD-001',
    'Ajuste de premarco': 'ALB-013', 'Instalación de puerta de entrada': 'MAD-005', 'Instalación de ventana': 'CAR-002', 'Instalación de persiana': 'CAR-010',
    'Frente de armario': 'MAD-007', 'Cerramiento de aluminio': 'CAR-009', 'Falso techo de yeso laminado': 'PYL-006', 'Techo hidrófugo': 'PYL-007',
    'Techo registrable': 'PYL-008', 'Aislamiento de lana de roca': 'PYL-014', 'Aislamiento acústico': 'AIS-003', 'Cajón para instalaciones': 'PYL-012',
    'Instalación de equipo de aire': 'CLI-001', 'Preinstalación de aire acondicionado': 'CLI-009', 'Conductos': 'CLI-007', 'Instalación de radiador': 'BAN-014',
    'Impermeabilización de ducha': 'BAN-004', 'Impermeabilización de terraza': 'CUB-001', 'Reparación de cubierta': 'CUB-008', 'Colocación de teja': 'CUB-009',
    'Pavimento exterior': 'SOL-006', 'Reparación de canalón': 'CUB-010', 'Montaje de muebles de cocina': 'COC-004', 'Colocación de encimera de cuarzo': 'COC-006',
    'Colocación de encimera laminada': 'COC-005', 'Instalación de electrodoméstico': 'COC-010', 'Montaje de mueble de baño': 'BAN-007',
    'Limpieza de fin de obra': 'GES-012',
}

s = open(SRC, encoding='utf-8').read()
def swap(start, block):
    global s
    i = s.index(start); j = s.index('\n];', i) + 3
    s = s[:i] + block + s[j:]
swap('const CAPS=[', caps_block)
swap('const TEMPLATES=[', tpl_block)
# reglas: cambia el nombre de destino; quita las que apuntan a partidas que ya no existen
i = s.index('const RULES=['); j = s.index('\n];', i)
rules = s[i:j].split('\n')
out, dropped = [], []
for line in rules:
    m = re.search(r",'([^']+)'\](,?)\s*$", line)
    if m and m.group(1) in OLD:
        line = line[:m.start()] + ',' + js(D(OLD[m.group(1)])) + ']' + m.group(2)
    elif m:
        dropped.append(m.group(1)); continue
    out.append(line)
if out[-1].endswith(','):
    out[-1] = out[-1][:-1]
s = s[:i] + '\n'.join(out) + s[j:]
# datos de ejemplo de la demo: mkLine('nombre antiguo', ...) -> partida nueva
for old, code in OLD.items():
    s = s.replace("mkLine('%s'" % old, 'mkLine(%s' % js(D(code)))
left = sorted({m for m in re.findall(r"mkLine\('([^']+)'", s)} - set(by_code[c]['descripcion'] for c in by_code))
if left:
    print('ojo, partidas de ejemplo sin tarifa:', left)
s = re.sub(r"const UNITS=\[[^\]]*\];", "const UNITS=['ud','m²','ml','m³','h','kg','pa','l','saco','rollo','caja'];", s, count=1)
open(SRC, 'w', encoding='utf-8').write(s)
print('capítulos', len(t['capitulos']), 'partidas', len(by_code), '· materiales', sum(len(c['partidas']) for c in materiales), '· reglas quitadas:', dropped)
