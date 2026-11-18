# SGM · forma de trabajar (KREAVANCE)

- Cliente: Sergio García Molina, Construcciones SGM · Trabajos integrales (Cazalla de la Sierra, Sevilla). En la app se habla a Sergio de tú.
- Repo público: nunca claves, correos autorizados ni datos personales de Sergio o de sus clientes. Esos van en los secretos de GitHub, en «Mis datos» o en el enlace `#datos=`.
- App: misma base que Daalcor (`kikeotero7/krea-daalcor`). Si se mejora una, valorar pasar la mejora a la otra.
- Código fuente en `src/src.html`; para probar, `python3 src/build.py` y abrir `src/dist/demo/` (contraseña `sgm2026`).
- Backend PHP + MySQL en `api/` (OVH, tablas `sgm_`). Se publica sola con GitHub Actions por FTP al hacer push a `main`.
- Marca: verde `#14741a`, bosque `#14261a`, logo con letras oscuras (va siempre sobre blanco). PDF con el formato de cajas verdes que le gusta a Kike.
- Entrada y parte legal según el estándar KREA (skill `krea-entrada-apps`); © KREAVANCE al pie.
- Kike revisa por enlace: tras cada cambio, publicar y pasarle https://sgm.kreadigitalstudio.es (y `/demo/`).
