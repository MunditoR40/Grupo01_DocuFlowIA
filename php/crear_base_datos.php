<?php
/**
 * Script de inicialización de la base de datos DocuFlow AI
 * Ejecuta schema.sql en MySQL (puerto 3307)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexion.php';

$esCli = (php_sapi_name() === 'cli');

try {
    // Conectar al servidor sin forzar dbname para poder crearla
    $pdo = Database::conectar(false);

    $sqlFile = __DIR__ . '/schema.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("El archivo schema.sql no fue encontrado.");
    }

    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);

    $mensaje = "Base de datos '" . DB_NAME . "' y tablas inicializadas con éxito en el puerto " . DB_PORT . ".";

    if ($esCli) {
        echo "[EXITO] $mensaje\n";
    } else {
        echo "<div style='font-family:sans-serif;padding:20px;background:#ecfdf5;color:#065f46;border-radius:8px;'>
                <h3>¡Base de datos lista!</h3>
                <p>$mensaje</p>
                <a href='test_conexion.php'>Ir al verificador de conexión</a>
              </div>";
    }

} catch (Exception $e) {
    $error = "Error al inicializar la base de datos: " . $e->getMessage();
    if ($esCli) {
        echo "[ERROR] $error\n";
    } else {
        echo "<div style='font-family:sans-serif;padding:20px;background:#fef2f2;color:#991b1b;border-radius:8px;'>
                <h3>Error</h3>
                <p>$error</p>
              </div>";
    }
}
