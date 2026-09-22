<?php
/**
 * API REST para el Sistema de Presupuesto de Compras - Versión 2.0
 */

// Desactivar reporte de avisos deprecados para evitar corromper las respuestas JSON
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

/* Zona horaria del negocio.
   El php.ini de XAMPP viene con Europe/Berlin, cinco horas adelante: a partir de
   las 19:00 hora local PHP ya estaba en el día siguiente. Eso movía la fecha de
   cálculo y con ella los días restantes de temporada (131 en vez de 132), así que
   las proyecciones cambiaban solas al caer la tarde. También dejaba las fechas de
   guardado cinco horas adelantadas respecto del nombre del presupuesto.
   Se fija acá y no en el php.ini porque ese archivo lo comparten todas las apps
   del servidor. Argentina y Uruguay están en el mismo huso, así que alcanza con
   uno para los dos países. */
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Configurar headers para CORS y JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Manejar cambio de país ANTES de incluir otros archivos
if (isset($_GET['accion']) && $_GET['accion'] === 'cambiar_pais') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $pais = $_GET['pais'] ?? 'argentina';
    $_SESSION['pais_seleccionado'] = strtolower($pais);
    
    echo json_encode([
        'success' => true,
        'message' => "País cambiado a " . ($pais === 'uruguay' ? 'Uruguay' : 'Argentina'),
        'pais' => $pais,
        'servidor' => $pais === 'uruguay' ? 'apps_power_uy' : 'apps_power'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Incluir el controlador principal
require_once __DIR__ . '/controller/MainController.php';

try {
    // Crear instancia del controlador principal
    $controller = new MainController();
    
    // Procesar la solicitud
    $controller->procesarSolicitud();
    
} catch (Throwable $e) {
    // Error general del sistema
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>