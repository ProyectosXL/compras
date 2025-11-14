<?php
// --- config/database.php (VERSIÓN CON .ENV) ---

// 1. Requerir el autoloader de Composer para poder usar las librerías instaladas
// Esto asume que la carpeta 'vendor' está en la raíz del proyecto ('AutorizacionesOc')
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Cargar el archivo .env
// Dotenv buscará un archivo .env en la carpeta especificada.
// '__DIR__ . '/../../'` sube dos niveles desde 'config' hasta 'compras'. Ajusta si es necesario.
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../'); // Apunta a la carpeta 'compras'
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    // Manejar el error si el archivo .env no se encuentra
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode(['status' => 'error', 'message' => 'Error crítico: No se encuentra el archivo de configuración .env.']));
}

// 3. Obtener las variables de entorno
$serverName = $_ENV['HOST_PRUEBA'] ?? '';
$database   = $_ENV['DATABASE_CENTRAL'] ?? '';
$uid        = $_ENV['USER'] ?? '';
$pwd        = $_ENV['PASS'] ?? '';

// 4. OPCIONES DE CONEXIÓN
$connectionOptions = [
    "Database" => $database,
    "Uid" => $uid,
    "PWD" => $pwd,
    "CharacterSet" => "UTF-8"
];

// 5. ESTABLECER CONEXIÓN
$conn = sqlsrv_connect($serverName, $connectionOptions);

// 6. MANEJO DE ERRORES DE CONEXIÓN
if ($conn === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    
    // NO mostrar credenciales en producción
    $error_message = 'Error crítico: No se pudo conectar a la base de datos.';
    
    // Si tienes un modo de depuración, podrías mostrar más detalles.
    // if ($_ENV['ENV'] === 'DEV') {
    //     $error_message .= ' Verifique las credenciales en el archivo .env.';
    // }

    echo json_encode(['status' => 'error', 'message' => $error_message], JSON_UNESCAPED_UNICODE);
    exit();
}

?>