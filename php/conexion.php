<?php
/**
 * Clase de Conexión a Base de Datos - PDO
 * DocuFlow AI
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $conexion = null;

    /**
     * Obtiene una instancia singleton de la conexión PDO.
     * 
     * @param bool $incluirDb Indica si la conexión debe conectarse a DB_NAME o solo al servidor MySQL.
     * @return PDO
     * @throws PDOException
     */
    public static function conectar(bool $incluirDb = true): PDO {
        if (self::$conexion !== null && $incluirDb) {
            return self::$conexion;
        }

        try {
            // Data Source Name (DSN) especificando host, puerto 3307 y charset
            $dsn = sprintf(
                'mysql:host=%s;port=%d;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_CHARSET
            );

            if ($incluirDb && !empty(DB_NAME)) {
                $dsn .= ';dbname=' . DB_NAME;
            }

            $opciones = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '" . DB_CHARSET . "'"
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);

            if ($incluirDb) {
                self::$conexion = $pdo;
            }

            return $pdo;

        } catch (PDOException $e) {
            // Si la base de datos específica no existe, lanzar error claro
            error_log("Error de conexión a la Base de Datos: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cierra la conexión activa si existe.
     */
    public static function desconectar(): void {
        self::$conexion = null;
    }
}

// Variable procedural disponible por defecto para scripts que hacen require_once 'conexion.php'
try {
    $pdo = Database::conectar();
} catch (PDOException $e) {
    // Si no existe la BD al cargarlo de forma directa, $pdo queda en null
    // para permitir manejo manual o comprobación en test_conexion.php
    $pdo = null;
    $errorConexion = $e->getMessage();
}
