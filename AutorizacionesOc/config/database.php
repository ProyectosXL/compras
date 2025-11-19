<?php
// --- config/database.php (VERSIÓN DEFINITIVA CON RUTA A /compras/.env) ---

// 1. Cargar el autoloader de Composer, siempre primero.
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Cargar las variables de entorno con la ruta correcta.
try {
    // ---- INICIO DE LA CORRECCIÓN CLAVE ----
    // __DIR__ es '.../compras/AutorizacionesOc/config'
    // dirname(__DIR__, 2) sube dos niveles, apuntando a '.../compras/'
    // Ahora Dotenv buscará el archivo .env en la carpeta 'compras'.
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2)); 
    // ---- FIN DE LA CORRECCIÓN CLAVE ----
    
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    die("ERROR CRÍTICO: No se puede encontrar el archivo .env. " . $e->getMessage());
}

// 3. La función de conexión (esta parte ya estaba bien)
function getDatabaseConnection($hostKey, $dbKey, $userKey, $passKey) {
    if (!isset($_ENV[$hostKey], $_ENV[$dbKey], $_ENV[$userKey], $_ENV[$passKey], $_ENV['CHARACTER'])) {
        error_log("Faltan variables de entorno para la conexión: {$hostKey}, {$dbKey}");
        return null;
    }
    $connectionInfo = ["Database" => $_ENV[$dbKey], "UID" => $_ENV[$userKey], "PWD" => $_ENV[$passKey], "CharacterSet" => $_ENV['CHARACTER']];
    try {
        $conn = sqlsrv_connect($_ENV[$hostKey], $connectionInfo);
        if ($conn === false) {
            error_log("Error de conexión a la BD {$_ENV[$dbKey]}: ".print_r(sqlsrv_errors(), true));
            return null;
        }
        return $conn;
    } catch (Exception $e) {
        error_log("Excepción de conexión a la BD {$_ENV[$dbKey]}: " . $e->getMessage());
        return null;
    }
}

// 4. Se definen las conexiones globales como antes
$conn = getDatabaseConnection('HOST_CENTRAL', 'DATABASE_CENTRAL', 'USER', 'PASS');
$conn_sistemas = getDatabaseConnection('HOST_APPS', 'DATABASE_APPS_ARG', 'USER', 'PASS');

?>