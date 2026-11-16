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
