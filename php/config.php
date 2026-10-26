<?php
/**
 * Configuración de la Base de Datos - DocuFlow AI
 * 
 * Configuración centralizada de credenciales y parámetros de conexión.
 */

// NOTA: Usar '127.0.0.1' en lugar de 'localhost' garantiza que la conexión
// viaje por TCP/IP y respete el puerto personalizado (3307).
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3307);
define('DB_NAME', 'docuflow_db'); // Cambia este nombre por el de tu base de datos
define('DB_USER', 'root');        // Usuario por defecto de XAMPP
define('DB_PASS', '');            // Contraseña vacía por defecto de XAMPP
define('DB_CHARSET', 'utf8mb4');
