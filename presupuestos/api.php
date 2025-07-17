
<?php
/**
 * API REST para el Sistema de Presupuesto de Compras - Versión 2.0
 */

// Configurar headers para CORS y JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
        'servidor' => $pais === 'uruguay' ? 'apps_uy' : 'apps'
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
    
} catch (Exception $e) {
    // Error general del sistema
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'trace' => defined('DEBUG') && DEBUG ? $e->getTraceAsString() : null
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>