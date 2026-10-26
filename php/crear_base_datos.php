<?php
/**
 * Script de inicialización / comprobación de la base de datos DocuFlow AI
 * Protegido contra ejecuciones accidentales o públicas.
 * Solo ejecutable por CLI o con autorización explícita.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexion.php';

$esCli = (php_sapi_name() === 'cli');

// Protección de seguridad: No permitir ejecución pública arbitraria desde el navegador
if (!$esCli && (!isset($_GET['autorizado']) || $_GET['autorizado'] !== 'docuflow_setup_seguro')) {
    http_response_code(403);
    echo "<div style='font-family:sans-serif;padding:20px;background:#fef2f2;color:#991b1b;border-radius:8px;'>
            <h3>Acceso Protegido</h3>
            <p>Por seguridad, este script solo puede ser ejecutado desde la consola de comandos (CLI):</p>
            <code>php php/crear_base_datos.php</code>
          </div>";
    exit;
}

try {
    // Conectar al servidor MySQL
    $pdo = Database::conectar(false);

    // Verificar si la base de datos ya existe
    $dbCheck = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'")->fetchColumn();
    
    if ($dbCheck) {
        // Conectar a la base de datos existente y verificar tablas
        $pdoDb = Database::conectar(true);
        $tablas = $pdoDb->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($tablas) >= 11) {
            $msg = "La base de datos '" . DB_NAME . "' ya se encuentra completamente configurada con " . count($tablas) . " tablas. No se realizaron modificaciones destructivas.";
            if ($esCli) {
                echo "[INFO] $msg\n";
            } else {
                echo "<div style='font-family:sans-serif;padding:20px;background:#ecfdf5;color:#065f46;border-radius:8px;'>
                        <h3>Base de datos ya existente</h3>
                        <p>$msg</p>
                        <a href='test_conexion.php'>Verificar estado de tablas</a>
                      </div>";
            }
            exit;
        }
    }

    $sqlFile = __DIR__ . '/DocuFlow_AI_MySQL_Completo.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("El archivo SQL de esquema (" . basename($sqlFile) . ") no fue encontrado.");
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
    $error = "Error al verificar/inicializar la base de datos: " . $e->getMessage();
    if ($esCli) {
        echo "[ERROR] $error\n";
    } else {
        echo "<div style='font-family:sans-serif;padding:20px;background:#fef2f2;color:#991b1b;border-radius:8px;'>
                <h3>Error</h3>
                <p>$error</p>
              </div>";
    }
}
