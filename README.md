# SGM · Presupuestos

App de Construcciones SGM (Sergio García Molina): presupuestos, facturas, clientes, obras, gastos, tarifa por oficios con materiales, avisos y asistente de IA con voz. Creada por KREAVANCE. Desde noviembre de 2026 usa la misma base que la app de Daalcor (`kikeotero7/krea-daalcor`), con la marca de SGM.

Dirección: https://sgm.kreavance.es (demo pública en `/demo/`, contraseña `sgm2026`; caso de estudio en `/portafolio/`).

## Cómo está montado

- `src/src.html`: toda la app (HTML, CSS y JS en un archivo). `python3 src/build.py` genera `src/dist/` con la app instalable y la demo (`dist/demo/`). Subir la versión de `CACHE` en `build.py` si cambia algo de la app instalada.
- `api/`: servidor en PHP + MySQL del hosting de OVH (igual que Daalcor). Cuentas con correo y contraseña (cookie de sesión), datos de cada usuario como un documento JSON con versión y copias de seguridad, y el asistente de presupuestos y de gastos con Claude (`asistente.php`). Tablas con prefijo `sgm_`, que se crean solas (pueden convivir en la misma base que otra app). Solo pueden crear cuenta los correos de `ALTAS_PERMITIDAS`.
- `.github/workflows/publicar-ovh.yml`: cada cambio en `main` genera la app, crea `api/config.php` con los secretos de GitHub y lo sube por FTP a la carpeta `sgm` del hosting (OVH Multisitio), junto con `portafolio/` y `sgm-fichajes/`. Secretos: `OVH_FTP_USER`, `OVH_FTP_PASSWORD`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `ALTAS_PERMITIDAS`, `ANTHROPIC_API_KEY`. Sin los del FTP, genera todo pero no sube nada.
- `app/datos/`: tarifa de 316 partidas por oficio (precios de Sevilla 2026) y 351 materiales; `tools/tarifa_a_app.py` los mete en `src/src.html`.
- `portafolio/`: caso de estudio. `sgm-fichajes/`: redirección a https://sgm-fichajes.kreadigitalstudio.es (repositorio `kikeotero7/krea-sgm-fichajes`) y capturas que usa el portafolio.
- Los datos personales de Sergio (NIF, dirección, correo, IBAN) no van en el repositorio: se rellenan en «Mis datos» o con el enlace personal `#datos=`.

## Publicación

Desde el 16/11/2026 `sgm.kreavance.es` apunta al hosting de OVH (Multisitio, carpeta `sgm`). La raíz del repositorio ya no se publica en GitHub Pages: todo sale de `src/`, `api/`, `portafolio/` y `sgm-fichajes/` con el flujo «Publicar en OVH».
