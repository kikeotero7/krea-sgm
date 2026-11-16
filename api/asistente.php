<?php
// Asistente de presupuestos de SGM (Claude, API de Anthropic).
// La app manda la conversación, su tarifa, sus clientes y el presupuesto abierto; aquí se ponen
// las reglas y las herramientas (para que no sirva como proxy genérico de IA) y se devuelve la
// respuesta de Claude tal cual. Las herramientas se ejecutan en la app, que vuelve a llamar con
// los resultados hasta que el asistente termina.
// Se llama a la API por HTTP directo: el hosting no tiene Composer y así los bloques de la
// respuesta (pensamiento, herramientas) vuelven a la app sin tocar.
declare(strict_types=1);

const IA_REGLAS = <<<'TXT'
Eres el asistente de presupuestos de Construcciones SGM, empresa de reformas integrales de Cazalla de la Sierra (Sevilla). Ayudas a Sergio, el dueño, a montar y cambiar presupuestos hablando: albañilería, demoliciones, alicatados y solados, fontanería, electricidad, climatización, carpintería, pintura, pladur, cocinas, baños, impermeabilización, fachadas y cerrajería.

Cómo trabajas:
- Cambia el presupuesto siempre con las herramientas; no basta con decirlo.
- Organiza el presupuesto por áreas (oficios). Si Sergio describe una obra con varias áreas («integral con albañilería, electricidad, fontanería, pintura…»), añade en este mismo presupuesto las partidas principales de CADA área que nombre, con cantidades razonables para el tamaño que diga, y pregúntale lo que falte de cada área. Después, cuando pida materiales de un área («ahora los materiales de electricidad»), añade los de su capítulo «Materiales · …»: la app los coloca solos dentro de su área.
- Los capítulos «Materiales · …» son solo material a precio de tienda (Obramat, Leroy Merlin), sin mano de obra. Para trabajos usa las partidas normales de la tarifa, que ya incluyen material y mano de obra; usa los materiales cuando Sergio pida el material aparte, quiera saber cuánto cuesta un material o lo aporte él. Si Sergio da un precio suyo, manda el suyo.
- Usa el nombre EXACTO de la partida de la tarifa cuando exista. Si no existe, crea una partida con nombre claro, unidad y un precio razonable de mercado en Sevilla en 2026, y avisa de que hay que revisarlo.
- No pongas precio en partidas de la tarifa salvo que Sergio diga uno concreto: la app usa su tarifa.
- Calcula medidas: suelo = largo × ancho; alicatado o pintura de paredes = perímetro × altura (2,5 m si no la dice), restando unos 2 m² por puerta y 1,5 m² por ventana. Escribe la cuenta en la nota de la partida, por ejemplo "(2+2,5)×2×2,5 m − puerta".
- Añade lo que un profesional no olvidaría cuando tenga sentido (protección, retirada de escombros y contenedor, ayudas de albañilería a otros oficios, limpieza final) y dilo.
- Si falta un dato importante, pon una cantidad razonable y pregunta para confirmarla.
- Si Sergio nombra al cliente o el trabajo, usa datos_generales. IVA del 10 % para reforma de vivienda particular; 21 % en locales, empresas y lo demás.
- La tarifa incluye las partidas propias de Sergio, marcadas con [propia]: son su forma de trabajar y su precio. Úsalas antes que otras parecidas cuando encajen.
- Si Sergio da un precio suyo para un trabajo que no está en la tarifa, o te pide guardar algo, usa guardar_partida_propia (y añádelo también al presupuesto si es para este trabajo). Usa como oficio el nombre de un capítulo de la tarifa.
- Responde en español de España, cercano y breve (2 a 4 frases): qué has hecho y qué necesitas saber. No menciones herramientas, JSON ni detalles técnicos.

Si Sergio pregunta cómo se hace algo en la app, explícaselo en pocos pasos con los nombres de los botones:
- Presupuestos: «Nuevo presupuesto» → 1 Cliente y obra → 2 Trabajos (catálogo por oficios, asistente con voz o texto, o «Nueva partida propia») → 3 Revisar (IVA, condiciones, estado, descargar PDF y «Enviar» por WhatsApp o correo). En Inicio hay plantillas: baño, cocina, pintura, suelos y reforma integral.
- Facturas: se crean desde un presupuesto aceptado (todo lo que queda, anticipo del 30 % o pago del 40 %). Se marcan como cobradas y se mandan al gestor con «Enviar al gestor».
- Clientes y Obras: fichas de cada cliente y de cada obra, con lo presupuestado frente a lo gastado.
- Gastos: se apuntan con la foto del ticket y se asignan a una obra.
- Mis precios: la tarifa por oficios (se cambia el precio escribiendo encima), «Mis partidas propias» (Añadir, lápiz para editar, papelera para borrar) y «Pegar desde Excel».
- Mis datos (en el menú): tus datos y los de la empresa (NIF, dirección, IBAN), que salen en presupuestos y facturas.
- Ajustes: IVA habitual, condiciones, numeración, contraseña y copia de seguridad.
- Arriba están la campana de avisos (facturas vencidas, presupuestos sin respuesta…) y «Salir».
TXT;

function ia_herramientas(): array {
  $unidades = ['ud', 'm²', 'ml', 'm³', 'h', 'kg', 'pa', 'l', 'saco', 'rollo', 'caja'];
  return [
    ['name' => 'anadir_partidas', 'description' => 'Añade partidas al presupuesto. Si la partida ya está, sustituye su cantidad. Devuelve lo que se ha hecho.',
     'input_schema' => ['type' => 'object', 'required' => ['partidas'], 'properties' => ['partidas' => ['type' => 'array', 'items' => [
       'type' => 'object', 'required' => ['nombre', 'cantidad'], 'properties' => [
         'nombre' => ['type' => 'string', 'description' => 'Nombre exacto de la tarifa, o uno nuevo y claro'],
         'cantidad' => ['type' => 'number'],
         'unidad' => ['type' => 'string', 'enum' => $unidades],
         'precio' => ['type' => 'number', 'description' => 'Solo si no está en la tarifa o Sergio dio un precio'],
         'nota' => ['type' => 'string', 'description' => 'Medidas, estancia o acabado; la ve el cliente'],
       ]]]]]],
    ['name' => 'cambiar_partida', 'description' => 'Cambia la cantidad, el precio o la nota de una partida que ya está en el presupuesto.',
     'input_schema' => ['type' => 'object', 'required' => ['nombre'], 'properties' => [
       'nombre' => ['type' => 'string'], 'cantidad' => ['type' => 'number'], 'precio' => ['type' => 'number'], 'nota' => ['type' => 'string']]]],
    ['name' => 'quitar_partida', 'description' => 'Quita una partida del presupuesto.',
     'input_schema' => ['type' => 'object', 'required' => ['nombre'], 'properties' => ['nombre' => ['type' => 'string']]]],
    ['name' => 'guardar_partida_propia', 'description' => 'Guarda en la biblioteca de partidas propias de Sergio un trabajo con su precio, para que salga en futuros presupuestos.',
     'input_schema' => ['type' => 'object', 'required' => ['nombre', 'unidad', 'precio'], 'properties' => [
       'nombre' => ['type' => 'string'],
       'oficio' => ['type' => 'string', 'description' => 'Nombre del capítulo de la tarifa, por ejemplo Electricidad; si no encaja, Otros trabajos'],
       'unidad' => ['type' => 'string', 'enum' => $unidades],
       'precio' => ['type' => 'number', 'description' => 'Precio sin IVA']]]],
    ['name' => 'datos_generales', 'description' => 'Pone el título del trabajo, el cliente (si existe uno con ese nombre lo usa, si no lo crea) y el IVA.',
     'input_schema' => ['type' => 'object', 'properties' => [
       'titulo' => ['type' => 'string'], 'cliente' => ['type' => 'string'], 'telefono' => ['type' => 'string'],
       'direccion' => ['type' => 'string'], 'iva' => ['type' => 'number', 'enum' => [21, 10, 0]]]]],
  ];
}

/** Devuelve [código HTTP, respuesta para la app]. */
function asistente(string $crudo): array {
  global $CFG;
  $clave = preg_replace('/[^\x21-\x7E]/', '', (string)($CFG['anthropic_key'] ?? ''));
  if ($clave === '') return [500, ['ok' => false, 'error' => 'Falta la clave de la IA en la configuración del servidor.']];
  if (strlen($crudo) > 400000) return [413, ['ok' => false, 'error' => 'La conversación es demasiado larga. Empieza una nueva.']];
  // Se lee como objetos para que los {} vacíos de la conversación sigan siendo {}
  $b = json_decode($crudo);
  $mensajes = is_object($b) && is_array($b->messages ?? null) ? array_slice($b->messages, -40) : [];
  if (!$mensajes || ($mensajes[0]->role ?? '') !== 'user') return [400, ['ok' => false, 'error' => 'Conversación no válida.']];
  foreach ($mensajes as $m) if (!in_array($m->role ?? '', ['user', 'assistant'], true)) return [400, ['ok' => false, 'error' => 'Conversación no válida.']];
  $tarifa = mb_substr((string)($b->tarifa ?? ''), 0, 120000);
  $clientes = mb_substr((string)($b->clientes ?? ''), 0, 8000);

  $peticion = [
    'model' => $CFG['anthropic_model'] ?? 'claude-opus-5-5',
    'max_tokens' => 16000,
    'fallbacks' => 'default',                    // si el modelo rechaza la petición, la repite otro modelo
    'output_config' => ['effort' => 'medium'],
    'system' => [
      ['type' => 'text', 'text' => IA_REGLAS],
      ['type' => 'text', 'text' => "Tarifa de Sergio (partida: unidad, precio sin IVA):\n$tarifa\n\nClientes existentes: " . ($clientes !== '' ? $clientes : 'ninguno') . '.',
       'cache_control' => ['type' => 'ephemeral']],
    ],
    'tools' => ia_herramientas(),
    'messages' => $mensajes,
  ];
  @set_time_limit(170);
  $ch = curl_init('https://api.anthropic.com/v1/messages');
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 160,
    CURLOPT_HTTPHEADER => [
      'x-api-key: ' . $clave,
      'anthropic-version: 2023-06-01',
      'anthropic-beta: server-side-fallback-2026-07-01',
      'content-type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($peticion, JSON_UNESCAPED_UNICODE),
  ]);
  $txt = curl_exec($ch);
  $estado = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $fallo = curl_error($ch);
  curl_close($ch);
  if ($txt === false) { error_log('[sgm] IA sin conexión: ' . $fallo); return [502, ['ok' => false, 'error' => 'No se ha podido conectar con la IA. Vuelve a intentarlo.']]; }
  $r = json_decode($txt);
  if ($estado === 429) return [429, ['ok' => false, 'error' => 'Ahora mismo hay demasiadas consultas. Espera un momento y vuelve a enviarlo.']];
  if ($estado === 401) return [500, ['ok' => false, 'error' => 'La clave de la IA no es válida.']];
  if ($estado !== 200 || !is_object($r)) {
    error_log('[sgm] IA ' . $estado . ': ' . mb_substr((string)$txt, 0, 500));
    return [502, ['ok' => false, 'error' => "La IA ha devuelto un error ($estado). Vuelve a intentarlo."]];
  }
  if (($r->stop_reason ?? '') === 'refusal') {
    return [200, ['ok' => true, 'content' => [], 'stop_reason' => 'refusal', 'message' => 'No he podido responder a eso. Prueba a describir el trabajo de otra forma.']];
  }
  return [200, ['ok' => true, 'content' => $r->content ?? [], 'stop_reason' => $r->stop_reason ?? 'end_turn']];
}

/** Gastos: a partir de lo que dice Sergio o de la foto de un ticket, devuelve los campos del gasto. [código HTTP, respuesta]. */
function gasto_ia(string $crudo): array {
  global $CFG;
  $clave = preg_replace('/[^\x21-\x7E]/', '', (string)($CFG['anthropic_key'] ?? ''));
  if ($clave === '') return [500, ['ok' => false, 'error' => 'Falta la clave de la IA en la configuración del servidor.']];
  if (strlen($crudo) > 4000000) return [413, ['ok' => false, 'error' => 'La foto es demasiado grande.']];
  $b = json_decode($crudo, true);
  if (!is_array($b)) return [400, ['ok' => false, 'error' => 'Petición no válida.']];
  $texto = mb_substr(trim((string)($b['texto'] ?? '')), 0, 1500);
  $imagen = (string)($b['imagen'] ?? '');
  if ($texto === '' && $imagen === '') return [400, ['ok' => false, 'error' => 'No hay nada que leer.']];
  $obras = array_slice(is_array($b['obras'] ?? null) ? $b['obras'] : [], 0, 200);
  $lista = implode('; ', array_map(fn($o) => mb_substr((string)($o['id'] ?? ''), 0, 40) . ': ' . mb_substr((string)($o['nombre'] ?? ''), 0, 120), $obras)) ?: 'ninguna';
  $tipos = ['Material', 'Subcontrata', 'Transporte', 'Herramienta', 'Vehículo', 'Otros'];
  $hoy = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($b['hoy'] ?? '')) ? $b['hoy'] : date('Y-m-d');
  $reglas = "Ayudas a Sergio, de Construcciones SGM (Cazalla de la Sierra, Sevilla), a apuntar un gasto de obra. Hoy es $hoy.\n"
    . "Obras abiertas (id: nombre): $lista.\n"
    . "Rellena: importe total pagado con IVA en euros (0 si no se sabe); concepto corto y claro (qué se ha comprado o pagado); proveedor o tienda (vacío si no se sabe); obra: el id de la obra que encaje por nombre, cliente o lugar, o vacío si no se dice; tipo: uno de " . implode(', ', $tipos) . "; fecha en formato AAAA-MM-DD (hoy si no se dice; «ayer», «el lunes»… calcúlala); respuesta: una frase corta en español de lo que has entendido o de lo que falta.\n"
    . "En un ticket, el importe es el TOTAL final a pagar, no la base ni una línea suelta. No inventes datos que no estén.";
  $contenido = [];
  if ($imagen !== '') {
    if (!preg_match('#^data:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/=]+)$#', $imagen, $m)) return [400, ['ok' => false, 'error' => 'La foto no es válida.']];
    $contenido[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $m[1], 'data' => $m[2]]];
    $contenido[] = ['type' => 'text', 'text' => 'Lee este ticket o factura de compra y rellena el gasto.' . ($texto !== '' ? " Sergio añade: «{$texto}»" : '')];
  } else {
    $contenido[] = ['type' => 'text', 'text' => "Lo que ha dicho Sergio: «{$texto}»"];
  }
  $esquema = ['type' => 'object', 'additionalProperties' => false,
    'required' => ['importe', 'concepto', 'proveedor', 'obra', 'tipo', 'fecha', 'respuesta'],
    'properties' => [
      'importe' => ['type' => 'number'], 'concepto' => ['type' => 'string'], 'proveedor' => ['type' => 'string'],
      'obra' => ['type' => 'string'], 'tipo' => ['type' => 'string', 'enum' => $tipos], 'fecha' => ['type' => 'string'],
      'respuesta' => ['type' => 'string']]];
  $peticion = [
    'model' => $CFG['anthropic_model'] ?? 'claude-opus-5-5',
    'max_tokens' => 4000,
    'fallbacks' => 'default',
    'output_config' => ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $esquema]],
    'system' => $reglas,
    'messages' => [['role' => 'user', 'content' => $contenido]],
  ];
  @set_time_limit(120);
  $ch = curl_init('https://api.anthropic.com/v1/messages');
  curl_setopt_array($ch, [
    CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 110,
    CURLOPT_HTTPHEADER => ['x-api-key: ' . $clave, 'anthropic-version: 2023-06-01', 'anthropic-beta: server-side-fallback-2026-07-01', 'content-type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($peticion, JSON_UNESCAPED_UNICODE),
  ]);
  $txt = curl_exec($ch); $estado = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
  if ($txt === false) return [502, ['ok' => false, 'error' => 'No se ha podido conectar con la IA. Vuelve a intentarlo.']];
  if ($estado === 429) return [429, ['ok' => false, 'error' => 'Ahora mismo hay demasiadas consultas. Espera un momento.']];
  $r = json_decode($txt, true);
  if ($estado !== 200 || !is_array($r)) { error_log('[sgm] gasto IA ' . $estado . ': ' . mb_substr((string)$txt, 0, 400)); return [502, ['ok' => false, 'error' => "La IA ha devuelto un error ($estado)."]]; }
  if (($r['stop_reason'] ?? '') === 'refusal') return [200, ['ok' => false, 'error' => 'No he podido leerlo. Escribe el gasto a mano.']];
  $salida = '';
  foreach ($r['content'] ?? [] as $bl) if (($bl['type'] ?? '') === 'text') $salida .= $bl['text'];
  $g = json_decode($salida, true);
  if (!is_array($g)) return [502, ['ok' => false, 'error' => 'No he entendido el gasto. Escríbelo a mano.']];
  return [200, ['ok' => true, 'gasto' => $g]];
}
