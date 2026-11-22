<?php
// Copia de ejemplo. El archivo real (config.php) NO se sube al repositorio:
// lo genera la publicación automática con los secretos de GitHub.
return [
  'db_host' => 'xxxxx.mysql.db',          // Servidor MySQL de OVH
  'db_name' => 'nombre_base',
  'db_user' => 'usuario_base',
  'db_pass' => 'CLAVE',
  // Correos que pueden crear cuenta (la app es privada)
  'altas_permitidas' => ['correo@ejemplo.com'],
  // Dirección pública de la app (para los enlaces de recuperar contraseña)
  'url_app' => 'https://sgm.kreavance.es/',
  'correo_remitente' => 'no-responder@kreavance.es',
  // Asistente de presupuestos
  'anthropic_key' => 'sk-ant-...',
  'anthropic_model' => 'claude-opus-5-5',
];
