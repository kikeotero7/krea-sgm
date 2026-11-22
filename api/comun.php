<?php
// Configuración y base de datos de la API de SGM
declare(strict_types=1);

$CFG = require __DIR__ . '/config.php';

function db(): PDO {
  static $pdo = null;
  global $CFG;
  if ($pdo) return $pdo;
  $pdo = new PDO("mysql:host={$CFG['db_host']};dbname={$CFG['db_name']};charset=utf8mb4", $CFG['db_user'], $CFG['db_pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  // Las tablas se crean solas la primera vez
  $pdo->exec("CREATE TABLE IF NOT EXISTS sgm_usuarios (
      id INT AUTO_INCREMENT PRIMARY KEY,
      email VARCHAR(190) NOT NULL UNIQUE,
      hash VARCHAR(255) NOT NULL,
      creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS sgm_sesiones (
      token_hash CHAR(64) PRIMARY KEY,
      usuario_id INT NOT NULL,
      expira DATETIME NOT NULL,
      INDEX (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS sgm_estado (
      usuario_id INT PRIMARY KEY,
      datos LONGTEXT NOT NULL,
      version INT NOT NULL DEFAULT 1,
      actualizado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS sgm_recuperaciones (
      token_hash CHAR(64) PRIMARY KEY,
      usuario_id INT NOT NULL,
      expira DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS sgm_intentos (
      clave VARCHAR(200) NOT NULL,
      momento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX (clave, momento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // Copias de seguridad: cada guardado deja aquí la versión anterior
  $pdo->exec("CREATE TABLE IF NOT EXISTS sgm_estado_copias (
      id INT AUTO_INCREMENT PRIMARY KEY,
      usuario_id INT NOT NULL,
      version INT NOT NULL,
      datos LONGTEXT NOT NULL,
      creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX (usuario_id, version)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  return $pdo;
}

// Envío de correo. Si hay buzón configurado (secretos SMTP_USER y SMTP_PASS), sale por el servidor de correo
// de OVH con usuario y clave, que es lo que Gmail acepta sin problemas. Si no, se intenta con mail() del alojamiento.
function enviar_correo(string $para, string $asunto, string $texto, string $nombre = 'SGM'): bool {
  global $CFG;
  $usr = (string)($CFG['smtp_user'] ?? ''); $clave = (string)($CFG['smtp_pass'] ?? '');
  $rem = $usr !== '' ? $usr : (string)$CFG['correo_remitente'];
  $dom = substr(strrchr($rem, '@'), 1);
  $asuntoC = '=?UTF-8?B?' . base64_encode($asunto) . '?=';
  $nombreC = '=?UTF-8?B?' . base64_encode($nombre) . '?=';
  $cab = "From: $nombreC <$rem>\r\nReply-To: $rem\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n"
       . 'Date: ' . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(12)) . "@$dom>";
  $cuerpo = chunk_split(base64_encode($texto));
  if ($usr === '' || $clave === '') {
    $ok = @mail($para, $asuntoC, $cuerpo, $cab, '-f' . $rem);
    if (!$ok) error_log('Correo: mail() no pudo enviar');
    return $ok;
  }
  $host = (string)($CFG['smtp_host'] ?? 'ssl0.ovh.net'); $puerto = (int)($CFG['smtp_port'] ?? 465);
  $s = @stream_socket_client("ssl://$host:$puerto", $en, $es, 15);
  if (!$s) { error_log("Correo: sin conexión con $host ($es)"); return false; }
  stream_set_timeout($s, 15);
  $leer = function () use ($s) { $r = ''; while (($l = fgets($s, 515)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return $r; };
  $paso = function (?string $orden, string $espera) use ($s, $leer) {
    if ($orden !== null) fwrite($s, $orden . "\r\n");
    $r = $leer();
    if (strncmp($r, $espera, 3) !== 0) throw new RuntimeException(trim(preg_replace('/AUTH.*/', 'AUTH …', (string)$orden)) . ' → ' . trim($r));
  };
  try {
    $paso(null, '220');
    $paso('EHLO ' . $dom, '250');
    $paso('AUTH LOGIN', '334');
    $paso(base64_encode($usr), '334');
    $paso(base64_encode($clave), '235');
    $paso("MAIL FROM:<$rem>", '250');
    $paso("RCPT TO:<$para>", '250');
    $paso('DATA', '354');
    $datos = "To: <$para>\r\nSubject: $asuntoC\r\n$cab\r\n\r\n" . preg_replace('/^\./m', '..', $cuerpo);
    $paso($datos . "\r\n.", '250');
    fwrite($s, "QUIT\r\n"); fclose($s);
    return true;
  } catch (Throwable $e) {
    error_log('Correo SMTP: ' . $e->getMessage()); @fclose($s); return false;
  }
}
