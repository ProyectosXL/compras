<?php
// --- config/database.php (VERSIÓN PARA DETECTAR EL ERROR REAL) ---

require_once __DIR__ . '/../vendor/autoload.php';

// 1. Definimos la ruta donde creemos que está el .env (3 niveles arriba)
$path_env = dirname(__DIR__, 3);

// VERIFICACIÓN MANUAL: Antes de cargar Dotenv, miramos si el archivo existe físicamente.
if (!file_exists($path_env . '/.env')) {
    // Si entra aquí, el 3 es incorrecto o el archivo no se llama .env
    http_response_code(500);
    die(json_encode([
        'status' => 'critical_error',
        'message' => 'No se encuentra el archivo .env fisicamente.',
        'buscado_en' => $path_env, // Esto te dirá dónde está buscando
        'archivo_esperado' => $path_env . DIRECTORY_SEPARATOR . '.env'
    ]));
}

try {
    $dotenv = Dotenv\Dotenv::createImmutable($path_env); 
    $dotenv->load();
} catch (\Exception $e) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Error cargando Dotenv: ' . $e->getMessage()]));
}

function getDatabaseConnection($hostKey, $dbKey, $userKey, $passKey) {
    // Validar que las variables existen en el .env cargado
    if (!isset($_ENV[$hostKey], $_ENV[$dbKey], $_ENV[$userKey], $_ENV[$passKey], $_ENV['CHARACTER'])) {
        http_response_code(500);
        die(json_encode([
            'status' => 'config_error', 
            'message' => "Faltan variables en el .env para conectar a: $dbKey",
            'variables_leidas' => array_keys($_ENV) // Para ver qué está leyendo
        ]));
    }

    $connectionInfo = [
        "Database" => $_ENV[$dbKey], 
        "UID" => $_ENV[$userKey], 
        "PWD" => $_ENV[$passKey], 
        "CharacterSet" => $_ENV['CHARACTER'],
        "TrustServerCertificate" => true // Importante para evitar errores de SSL
    ];

    $conn = sqlsrv_connect($_ENV[$hostKey], $connectionInfo);

    if ($conn === false) {
        // AQUÍ ESTÁ EL CAMBIO: No devolvemos null. Matamos el proceso y mostramos el error SQL.
        http_response_code(500);
        die(json_encode([
            'status' => 'sql_connection_error',
            'message' => 'Fallo al conectar con la base de datos: ' . $_ENV[$dbKey],
            'host_intentado' => $_ENV[$hostKey],
            'usuario_intentado' => $_ENV[$userKey],
            'errores_sql_server' => sqlsrv_errors() // <--- ESTO ES LO QUE NECESITAMOS VER
        ]));
    }
    return $conn;
}

// Inicializar conexiones
$conn = getDatabaseConnection('HOST_CENTRAL', 'DATABASE_CENTRAL', 'USER', 'PASS');
$conn_sistemas = getDatabaseConnection('HOST_APPS', 'DATABASE_APPS', 'USER', 'PASS');

?>