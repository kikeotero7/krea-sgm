<?php
// API de SGM · Presupuestos (KREAVANCE)
// Cuentas con correo y contraseña, los datos de cada usuario guardados como un documento JSON
// y el asistente de presupuestos con Claude. Todo en el hosting de OVH (PHP + MySQL).

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require __DIR__ . '/comun.php';
require __DIR__ . '/asistente.php';
const SESION_DIAS = 180;           // la sesión se mantiene abierta medio año
const MAX_DATOS = 8 * 1024 * 1024; // 8 MB por usuario (incluye fotos pequeñas de tickets)
const COOKIE = 'sgm_sesion';

function responder(int $codigo, array $datos): void {
  http_response_code($codigo);
  echo json_encode($datos, JSON_UNESCAPED_UNICODE);
  exit;
}
function error_(int $codigo, string $mensaje): void { responder($codigo, ['ok' => false, 'error' => $mensaje]); }

// Límite de intentos (contra quien pruebe contraseñas a lo loco)
function limitar(string $clave, int $max, int $minutos): void {
  $pdo = db();
  $pdo->prepare('DELETE FROM sgm_intentos WHERE momento < NOW() - INTERVAL 1 DAY')->execute();
  $st = $pdo->prepare('SELECT COUNT(*) FROM sgm_intentos WHERE clave = ? AND momento > NOW() - INTERVAL ? MINUTE');
  $st->execute([$clave, $minutos]);
  if ((int)$st->fetchColumn() >= $max) error_(429, 'Demasiados intentos. Espera unos minutos y vuelve a probar.');
  $pdo->prepare('INSERT INTO sgm_intentos (clave) VALUES (?)')->execute([$clave]);
}

function ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0'; }
function cuerpo_crudo(): string { static $raw = null; return $raw ??= (file_get_contents('php://input') ?: '{}'); }
function cuerpo(): array { $j = json_decode(cuerpo_crudo(), true); return is_array($j) ? $j : []; }
function email_valido(string $e): bool { return (bool)filter_var($e, FILTER_VALIDATE_EMAIL) && strlen($e) <= 190; }

function crear_sesion(int $uid): void {
  $token = bin2hex(random_bytes(32));
  db()->prepare('INSERT INTO sgm_sesiones (token_hash, usuario_id, expira) VALUES (?, ?, NOW() + INTERVAL ? DAY)')
    ->execute([hash('sha256', $token), $uid, SESION_DIAS]);
  setcookie(COOKIE, $token, ['expires' => time() + SESION_DIAS * 86400, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
}
function usuario_actual(): ?array {
  $t = $_COOKIE[COOKIE] ?? '';
  if (!preg_match('/^[a-f0-9]{64}$/', $t)) return null;
  $st = db()->prepare('SELECT u.id, u.email FROM sgm_sesiones s JOIN sgm_usuarios u ON u.id = s.usuario_id WHERE s.token_hash = ? AND s.expira > NOW()');
  $st->execute([hash('sha256', $t)]);
  return $st->fetch() ?: null;
}
function exigir_usuario(): array {
  $u = usuario_actual();
  if (!$u) error_(401, 'Tu sesión ha caducado. Vuelve a entrar.');
  return $u;
}

// Solo peticiones hechas por la propia app (protección CSRF junto a la cookie SameSite=Strict)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['HTTP_X_SGM'] ?? '') !== '1') error_(400, 'Petición no válida.');

$accion = $_GET['accion'] ?? '';
$b = cuerpo();

try {
  switch ($accion) {

    case 'sesion': {
      $u = usuario_actual();
      responder(200, ['ok' => true, 'usuario' => $u ? ['email' => $u['email']] : null]);
    }

    case 'entrar': {
      $email = strtolower(trim((string)($b['email'] ?? ''))); $clave = (string)($b['clave'] ?? '');
      limitar('entrar:' . ip(), 20, 15); limitar('entrar:' . $email, 8, 15);
      $st = db()->prepare('SELECT id, hash FROM sgm_usuarios WHERE email = ?'); $st->execute([$email]);
      $u = $st->fetch();
      if (!$u || !password_verify($clave, $u['hash'])) error_(401, 'Correo o contraseña incorrectos. Si no la recuerdas, pulsa «¿Has olvidado tu contraseña?».');
      if (password_needs_rehash($u['hash'], PASSWORD_DEFAULT)) db()->prepare('UPDATE sgm_usuarios SET hash = ? WHERE id = ?')->execute([password_hash($clave, PASSWORD_DEFAULT), $u['id']]);
      crear_sesion((int)$u['id']);
      responder(200, ['ok' => true, 'usuario' => ['email' => $email]]);
    }

    case 'crear': {
      global $CFG;
      $email = strtolower(trim((string)($b['email'] ?? ''))); $clave = (string)($b['clave'] ?? '');
      limitar('crear:' . ip(), 10, 60);
      if (!email_valido($email)) error_(400, 'Escribe un correo válido.');
      if (strlen($clave) < 8) error_(400, 'La contraseña debe tener al menos 8 caracteres.');
      $permitidas = array_map('strtolower', $CFG['altas_permitidas'] ?? []);
      if (!in_array($email, $permitidas, true)) error_(403, 'Este correo no tiene acceso a la app de SGM.');
      $st = db()->prepare('SELECT id FROM sgm_usuarios WHERE email = ?'); $st->execute([$email]);
      if ($st->fetch()) error_(409, 'Ese correo ya tiene cuenta. Pulsa «Ya tengo cuenta: entrar» o «¿Has olvidado tu contraseña?».');
      db()->prepare('INSERT INTO sgm_usuarios (email, hash) VALUES (?, ?)')->execute([$email, password_hash($clave, PASSWORD_DEFAULT)]);
      crear_sesion((int)db()->lastInsertId());
      responder(200, ['ok' => true, 'usuario' => ['email' => $email]]);
    }

    case 'salir': {
      $t = $_COOKIE[COOKIE] ?? '';
      if ($t) db()->prepare('DELETE FROM sgm_sesiones WHERE token_hash = ?')->execute([hash('sha256', $t)]);
      setcookie(COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
      responder(200, ['ok' => true]);
    }

    case 'recuperar': {
      global $CFG;
      $email = strtolower(trim((string)($b['email'] ?? '')));
      limitar('recuperar:' . ip(), 5, 60); limitar('recuperar:' . $email, 3, 60);
      $st = db()->prepare('SELECT id FROM sgm_usuarios WHERE email = ?'); $st->execute([$email]);
      $u = $st->fetch();
      if ($u) {
        $token = bin2hex(random_bytes(32));
        db()->prepare('INSERT INTO sgm_recuperaciones (token_hash, usuario_id, expira) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)')->execute([hash('sha256', $token), $u['id']]);
        $enlace = $CFG['url_app'] . '?recuperar=' . $token;
        $asunto = '=?UTF-8?B?' . base64_encode('SGM · Cambia tu contraseña') . '?=';
        $texto = "Hola:\n\nPara elegir una contraseña nueva en la app de SGM, abre este enlace (caduca en 1 hora):\n\n$enlace\n\nSi no lo has pedido tú, ignora este correo.\n\nSGM · Presupuestos · KREAVANCE";
        $rem = $CFG['correo_remitente'];
        $dom = substr(strrchr($rem, '@'), 1);
        // OVH solo entrega bien si el remitente del sobre (-f) es del mismo dominio que el «From»
        $cab = "From: SGM <$rem>\r\nReply-To: $rem\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n"
             . 'Date: ' . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(12)) . "@$dom>";
        $ok = @mail($email, $asunto, $texto, $cab, '-f' . $rem);
        if (!$ok) error_log('SGM: no se pudo enviar el correo de recuperación');
      }
      // Siempre la misma respuesta, para no revelar qué correos tienen cuenta
      responder(200, ['ok' => true]);
    }

    case 'nueva_clave': {
      $token = (string)($b['token'] ?? ''); $clave = (string)($b['clave'] ?? '');
      limitar('nueva:' . ip(), 10, 60);
      if (strlen($clave) < 8) error_(400, 'La contraseña debe tener al menos 8 caracteres.');
      $st = db()->prepare('SELECT r.usuario_id, u.email FROM sgm_recuperaciones r JOIN sgm_usuarios u ON u.id = r.usuario_id WHERE r.token_hash = ? AND r.expira > NOW()');
      $st->execute([hash('sha256', $token)]);
      $f = $st->fetch();
      if (!$f) error_(400, 'El enlace ha caducado o ya se usó. Pide otro desde «¿Has olvidado tu contraseña?».');
      $uid = (int)$f['usuario_id'];
      db()->prepare('UPDATE sgm_usuarios SET hash = ? WHERE id = ?')->execute([password_hash($clave, PASSWORD_DEFAULT), $uid]);
      db()->prepare('DELETE FROM sgm_recuperaciones WHERE usuario_id = ?')->execute([$uid]);
      db()->prepare('DELETE FROM sgm_sesiones WHERE usuario_id = ?')->execute([$uid]);
      crear_sesion($uid);
      responder(200, ['ok' => true, 'email' => $f['email']]);
    }

    case 'cambiar_clave': {
      $u = exigir_usuario();
      $clave = (string)($b['clave'] ?? '');
      limitar('cambiar:' . $u['id'], 10, 60);
      if (strlen($clave) < 8) error_(400, 'La contraseña debe tener al menos 8 caracteres.');
      db()->prepare('UPDATE sgm_usuarios SET hash = ? WHERE id = ?')->execute([password_hash($clave, PASSWORD_DEFAULT), $u['id']]);
      responder(200, ['ok' => true]);
    }

    case 'leer': {
      $u = exigir_usuario();
      $st = db()->prepare('SELECT datos, version FROM sgm_estado WHERE usuario_id = ?'); $st->execute([$u['id']]);
      $f = $st->fetch();
      responder(200, ['ok' => true, 'datos' => $f ? json_decode($f['datos'], true) : null, 'version' => $f ? (int)$f['version'] : 0]);
    }

    case 'guardar': {
      $u = exigir_usuario();
      $datos = $b['datos'] ?? null; $version = (int)($b['version'] ?? 0);
      if (!is_array($datos)) error_(400, 'Datos no válidos.');
      $json = json_encode($datos, JSON_UNESCAPED_UNICODE);
      if (strlen($json) > MAX_DATOS) error_(413, 'Hay demasiados datos para guardar de una vez.');
      $pdo = db(); $pdo->beginTransaction();
      $st = $pdo->prepare('SELECT version FROM sgm_estado WHERE usuario_id = ? FOR UPDATE'); $st->execute([$u['id']]);
      $actual = $st->fetchColumn();
      if ($actual === false) {
        $pdo->prepare('INSERT INTO sgm_estado (usuario_id, datos, version) VALUES (?, ?, 1)')->execute([$u['id'], $json]);
        $nueva = 1;
      } else {
        // Si otro dispositivo guardó antes, la app junta los cambios y vuelve a guardar
        if ($version !== (int)$actual) { $pdo->rollBack(); error_(409, 'Hay cambios más nuevos guardados desde otro dispositivo.'); }
        $nueva = (int)$actual + 1;
        // Copia de seguridad de lo que había antes (se guardan las 200 últimas de cada usuario)
        $pdo->prepare('INSERT INTO sgm_estado_copias (usuario_id, version, datos) SELECT usuario_id, version, datos FROM sgm_estado WHERE usuario_id = ?')->execute([$u['id']]);
        $pdo->prepare('DELETE FROM sgm_estado_copias WHERE usuario_id = ? AND version <= ?')->execute([$u['id'], $nueva - 200]);
        $pdo->prepare('UPDATE sgm_estado SET datos = ?, version = ? WHERE usuario_id = ?')->execute([$json, $nueva, $u['id']]);
      }
      $pdo->commit();
      responder(200, ['ok' => true, 'version' => $nueva]);
    }

    case 'asistente': {
      $u = exigir_usuario();
      limitar('ia:' . $u['id'], 120, 60);
      [$codigo, $res] = asistente(cuerpo_crudo());
      responder($codigo, $res);
    }

    case 'gasto': {
      $u = exigir_usuario();
      limitar('gasto:' . $u['id'], 120, 60);
      [$codigo, $res] = gasto_ia(cuerpo_crudo());
      responder($codigo, $res);
    }

    default: error_(404, 'Acción desconocida.');
  }
} catch (Throwable $e) {
  error_log('[sgm] ' . $e->getMessage());
  // Fallo al conectar con la base de datos: lo decimos claro (sin dar detalles del servidor)
  if ($e instanceof PDOException && in_array((int)($e->errorInfo[1] ?? $e->getCode()), [1044, 1045, 2002, 2005], true)) {
    error_(503, 'La app no puede conectar con su base de datos. Avisa a KREAVANCE.');
  }
  error_(500, 'No se ha podido completar. Inténtalo de nuevo en un momento.');
}
