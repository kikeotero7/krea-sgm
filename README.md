# SGM · Presupuestos

App de Construcciones SGM (presupuestos, facturas, clientes, obras, gastos y tarifa) creada por KREA Digital Studio.

Publicada en https://sgm.kreadigitalstudio.es (GitHub Pages, rama `main`, carpeta raíz; DNS en OVH: CNAME `sgm` → `kikeotero7.github.io.`).

- `index.html`, `manifest.webmanifest`, `sw.js`, iconos: app instalable (PWA) que se publica.
- `src/`: código fuente. `python3 src/build.py` genera la demo y la carpeta `dist/`; copia el contenido de `dist/` a la raíz para publicar.

## Contenido del repositorio

- Raíz: **SGM · Presupuestos** (presupuestos, facturas, clientes, obras, gastos y tarifa).
- `sgm-fichajes/`: **SGM Fichajes · Control de obra** (fichajes con PIN, geozona, asistencia e incidencias), publicada en https://sgm.kreadigitalstudio.es/sgm-fichajes/. Demo con PIN de prueba (Miguel 1234, David 2345, Antonio 3456) y datos de ejemplo que se renuevan cada día. Ficha y capturas en `sgm-fichajes/portafolio/`. Para regenerarla: editar `sgm-fichajes/app.src.html` y ejecutar `python3 sgm-fichajes/build.py`.

Pendiente de Sergio para la facturación: NIF, dirección fiscal, correo, IBAN, nombre y correo del gestor.
