# SGM Fichajes · Control de obra

**Cliente:** SGM Construcciones · Trabajos Integrales (Sevilla)
**Estudio:** KREA Digital Studio
**Tipo de proyecto:** App web instalable (PWA) para móvil, tablet y ordenador
**Estado:** Demo funcional, lista para enseñar a clientes

## El reto

Una empresa de reformas con varias obras abiertas a la vez necesita saber quién está trabajando, en qué obra y desde qué hora. También tiene que cumplir el registro diario de jornada (art. 34.9 del Estatuto de los Trabajadores), sin papeles ni hojas de cálculo.

## La solución

Una app con la imagen corporativa de SGM y dos caras:

- **Para el operario:** ficha con un PIN de 4 dígitos desde su móvil o desde la tablet de la obra. La app comprueba la ubicación con GPS y el horario. Si algo no cuadra, el operario explica el motivo y el fichaje queda pendiente de revisión.
- **Para el encargado (Sergio):** un panel de control con quién trabaja ahora, avisos de quien no ha fichado, horas del equipo, obras con su zona en el mapa, asistencia mensual, incidencias y correcciones.

## Funciones

| Sección | Qué hace |
|---|---|
| Fichar | Teclado de PIN, comprobación de zona GPS y de horario, motivo de incidencia, justificante, solicitud de corrección, modo tablet de obra |
| Resumen | Equipo trabajando en directo, avisos de fichaje, gráfica de horas de 7 días, avance de obras |
| Obras | Estado, avance, fecha fin, zona de fichaje con radio ajustable, búsqueda en Google Maps, pegar coordenadas o enlaces del mapa, mapa con los fichajes |
| Equipo | PIN único, puesto, obra asignada, fecha de alta, horas de la semana |
| Fichajes | Historial filtrable por empleado, obra y periodo, exportación CSV del registro de jornada |
| Asistencia | Calendario mensual: trabajados, faltas, ausencias justificadas, vacaciones, días por revisar y festivos, con motivo e historial |
| Incidencias | Revisión de fichajes fuera de zona u horario, salidas pendientes, aprobar o rechazar correcciones conservando el original |
| Ajustes | Horario de verano e invierno con margen, festivos y jornadas especiales, avisos al móvil, copias cifradas AES-256, accesos |

## Diseño

- Identidad SGM: verde corporativo del logotipo y cabecera oscura de obra.
- Tipografías Archivo (titulares), Public Sans (texto) y JetBrains Mono (horas y cifras).
- Modo claro y oscuro con botón en la cabecera.
- Pensada para usarse con guantes y al sol: botones grandes, mucho contraste y lectura rápida del estado con colores (verde: correcto, ámbar: revisar, rojo: falta).

## Capturas

| Escritorio | Móvil |
|---|---|
| `escritorio-resumen.webp` | `movil-fichar-pin.webp` |
| `escritorio-obras.webp` | `movil-fichar-empleado.webp` |
| `escritorio-obra-zona.webp` | `movil-fichar-comprobacion.webp` |
| `escritorio-asistencia.webp` | `movil-resumen-oscuro.webp` |
| `escritorio-incidencias.webp` | |
| `escritorio-fichajes.webp` | |
| `escritorio-fichar-pin.webp`, `escritorio-fichar-empleado.webp` | |

## Probar la demo

PIN de prueba: **Miguel 1234 · David 2345 · Antonio 3456**. La demo trae datos de ejemplo de un mes y se puede reiniciar desde la barra superior.

## Siguiente fase (versión real)

- Base de datos compartida en la nube para muchos usuarios a la vez.
- Inicio de sesión del encargado y de varios administradores.
- Avisos push al móvil del encargado.
- Informes mensuales en PDF por empleado para la gestoría.
- Integración con la app de presupuestos de SGM.
