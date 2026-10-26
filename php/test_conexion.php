<?php
/**
 * Script de Verificación y Diagnóstico de Conexión MySQL (Puerto 3307)
 * DocuFlow AI
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexion.php';

$esCli = (php_sapi_name() === 'cli');

$servidorOk = false;
$bdOk = false;
$mensajeServidor = '';
$mensajeBd = '';
$listaBds = [];
$versionMysql = '';

// Acción opcional para crear la base de datos si el usuario hace clic en el botón
if (isset($_POST['crear_bd']) && !empty(DB_NAME)) {
    try {
        $pdoServidor = Database::conectar(false);
        $pdoServidor->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci");
        $creada = true;
    } catch (PDOException $e) {
        $errorCrear = $e->getMessage();
    }
}

// 1. Probar conexión al Servidor MySQL (sin especificar BD)
try {
    $pdoServidor = Database::conectar(false);
    $servidorOk = true;
    $versionMysql = $pdoServidor->query('SELECT VERSION()')->fetchColumn();
    $listaBds = $pdoServidor->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
    $mensajeServidor = "Conectado exitosamente al motor MySQL en el puerto " . DB_PORT . " (Versión: $versionMysql).";
} catch (PDOException $e) {
    $servidorOk = false;
    $mensajeServidor = "No se pudo conectar al servidor MySQL en " . DB_HOST . ":" . DB_PORT . ". Error: " . $e->getMessage();
}

// 2. Probar conexión a la Base de Datos específica
$tablasBd = [];
$conteoPorTabla = [];
if ($servidorOk) {
    try {
        $pdoDb = Database::conectar(true);
        $bdOk = true;
        $tablasBd = $pdoDb->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tablasBd as $tbl) {
            try {
                $count = $pdoDb->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
                $conteoPorTabla[$tbl] = (int)$count;
            } catch (Exception $e) {
                $conteoPorTabla[$tbl] = 'Error';
            }
        }

        $mensajeBd = "La base de datos '" . DB_NAME . "' existe y la conexión fue exitosa (" . count($tablasBd) . " tablas detectadas).";
    } catch (PDOException $e) {
        $bdOk = false;
        $mensajeBd = "El servidor responde, pero no se pudo acceder a la BD '" . DB_NAME . "'. Error: " . $e->getMessage();
    }
}

// Si se ejecuta desde terminal CLI (e.g. php test_conexion.php)
if ($esCli) {
    echo "========================================\n";
    echo " DIAGNÓSTICO DE CONEXIÓN MYSQL - PUERTO " . DB_PORT . "\n";
    echo "========================================\n";
    echo "Host:    " . DB_HOST . "\n";
    echo "Puerto:  " . DB_PORT . "\n";
    echo "Usuario: " . DB_USER . "\n";
    echo "Base BD: " . DB_NAME . "\n";
    echo "----------------------------------------\n";
    if ($servidorOk) {
        echo "[OK] " . $mensajeServidor . "\n";
        echo "Bases de datos encontradas: " . implode(', ', $listaBds) . "\n";
        echo "----------------------------------------\n";
        if ($bdOk) {
            echo "[OK] " . $mensajeBd . "\n";
            if (!empty($tablasBd)) {
                echo "Listado de tablas (" . count($tablasBd) . "):\n";
                foreach ($tablasBd as $tbl) {
                    $filas = $conteoPorTabla[$tbl] ?? 0;
                    echo "  • " . str_pad($tbl, 25) . " => $filas registros\n";
                }
            }
        } else {
            echo "[AVISO] " . $mensajeBd . "\n";
            echo "Tip: Puedes crearla ejecutando en MySQL: CREATE DATABASE `" . DB_NAME . "`;\n";
        }
    } else {
        echo "[ERROR] " . $mensajeServidor . "\n";
        echo "Tip: Revisa que el servicio MySQL en XAMPP esté iniciado y escuchando en el puerto " . DB_PORT . ".\n";
    }
    echo "========================================\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocuFlow AI — Test de Conexión MySQL</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="min-h-full flex items-center justify-center p-6 bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(120,119,198,0.25),rgba(255,255,255,0))]">
    <div class="w-full max-w-xl bg-slate-900/90 backdrop-blur-xl border border-slate-800 rounded-2xl shadow-2xl p-8">
        
        <!-- Header -->
        <div class="flex items-center space-x-3 pb-6 border-b border-slate-800">
            <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 text-xl">
                <i class="fa-solid fa-database"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-white">DocuFlow AI — Test de Conexión</h1>
                <p class="text-xs text-slate-400">Verificación de conexión MySQL en el puerto <?= htmlspecialchars((string)DB_PORT) ?></p>
            </div>
        </div>

        <!-- Parámetros Configurados -->
        <div class="grid grid-cols-2 gap-3 my-6">
            <div class="bg-slate-950/60 p-3 rounded-lg border border-slate-800/80">
                <span class="text-xs text-slate-400 block">Host y Puerto:</span>
                <span class="font-mono text-sm font-semibold text-slate-200"><?= htmlspecialchars(DB_HOST) ?>:<?= htmlspecialchars((string)DB_PORT) ?></span>
            </div>
            <div class="bg-slate-950/60 p-3 rounded-lg border border-slate-800/80">
                <span class="text-xs text-slate-400 block">Usuario:</span>
                <span class="font-mono text-sm font-semibold text-slate-200"><?= htmlspecialchars(DB_USER) ?></span>
            </div>
            <div class="bg-slate-950/60 p-3 rounded-lg border border-slate-800/80">
                <span class="text-xs text-slate-400 block">Base de Datos:</span>
                <span class="font-mono text-sm font-semibold text-indigo-400"><?= htmlspecialchars(DB_NAME) ?></span>
            </div>
            <div class="bg-slate-950/60 p-3 rounded-lg border border-slate-800/80">
                <span class="text-xs text-slate-400 block">Charset:</span>
                <span class="font-mono text-sm font-semibold text-slate-200"><?= htmlspecialchars(DB_CHARSET) ?></span>
            </div>
        </div>

        <!-- Estado Servidor MySQL -->
        <div class="space-y-4">
            <div class="p-4 rounded-xl border <?= $servidorOk ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-rose-500/10 border-rose-500/30 text-rose-300' ?>">
                <div class="flex items-start space-x-3">
                    <i class="fa-solid <?= $servidorOk ? 'fa-circle-check text-emerald-400' : 'fa-circle-xmark text-rose-400' ?> text-lg mt-0.5"></i>
                    <div>
                        <h2 class="font-semibold text-sm"><?= $servidorOk ? 'Servidor MySQL Conectado (Puerto ' . DB_PORT . ')' : 'Error de Conexión al Servidor' ?></h2>
                        <p class="text-xs mt-1 text-slate-300"><?= htmlspecialchars($mensajeServidor) ?></p>
                    </div>
                </div>
            </div>

            <!-- Estado Base de Datos -->
            <?php if ($servidorOk): ?>
                <div class="p-4 rounded-xl border <?= $bdOk ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-amber-500/10 border-amber-500/30 text-amber-300' ?>">
                    <div class="flex items-start space-x-3">
                        <i class="fa-solid <?= $bdOk ? 'fa-database text-emerald-400' : 'fa-triangle-exclamation text-amber-400' ?> text-lg mt-0.5"></i>
                        <div class="flex-1">
                            <h2 class="font-semibold text-sm"><?= $bdOk ? 'Base de Datos Lista' : 'Base de Datos No Encontrada' ?></h2>
                            <p class="text-xs mt-1 text-slate-300"><?= htmlspecialchars($mensajeBd) ?></p>
                            
                            <?php if (!$bdOk): ?>
                                <form method="POST" class="mt-3">
                                    <button type="submit" name="crear_bd" value="1" class="inline-flex items-center space-x-2 px-3 py-1.5 bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 text-xs font-medium rounded-lg border border-amber-500/40 transition">
                                        <i class="fa-solid fa-plus"></i>
                                        <span>Crear base de datos '<?= htmlspecialchars(DB_NAME) ?>' automáticamente</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tablas de la base de datos -->
                <?php if ($bdOk): ?>
                    <div class="bg-indigo-950/30 p-4 rounded-xl border border-indigo-500/30 text-xs">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-indigo-300 font-semibold flex items-center space-x-1.5">
                                <i class="fa-solid fa-table"></i>
                                <span>Tablas en '<?= htmlspecialchars(DB_NAME) ?>':</span>
                            </span>
                            <span class="px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 font-bold text-[11px]">
                                <?= count($tablasBd) ?> tablas
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <?php foreach ($tablasBd as $tbl): ?>
                                <div class="px-2.5 py-1.5 rounded-lg bg-slate-900/80 border border-indigo-500/20 text-slate-200 font-mono text-[11px] flex items-center justify-between">
                                    <span class="truncate flex items-center">
                                        <i class="fa-solid fa-table-cells text-indigo-400 mr-2 text-[10px]"></i>
                                        <?= htmlspecialchars($tbl) ?>
                                    </span>
                                    <span class="ml-2 px-1.5 py-0.5 rounded text-[10px] font-semibold <?= ($conteoPorTabla[$tbl] ?? 0) > 0 ? 'bg-indigo-500/20 text-indigo-300' : 'bg-slate-800 text-slate-400' ?>">
                                        <?= htmlspecialchars((string)($conteoPorTabla[$tbl] ?? 0)) ?> filas
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Bases de datos disponibles -->
                <div class="bg-slate-950/40 p-4 rounded-xl border border-slate-800 text-xs">
                    <span class="text-slate-400 font-medium block mb-2">Bases de datos disponibles en el servidor (MySQL 3307):</span>
                    <div class="flex flex-wrap gap-1.5">
                        <?php foreach ($listaBds as $bd): ?>
                            <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 font-mono"><?= htmlspecialchars($bd) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="mt-6 pt-4 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-500">
            <span>DocuFlow AI &copy; <?= date('Y') ?></span>
            <a href="../index.html" class="text-indigo-400 hover:text-indigo-300 transition flex items-center space-x-1">
                <span>Ir al Landing Page</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

    </div>
</body>
</html>
