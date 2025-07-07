
<?php
/**
 * API REST para el Sistema de Presupuesto de Compras - Versión 2.0
 * 
 * Endpoints principales:
 * GET /presupuestos/api.php?accion=datos-base - Obtener datos base del presupuesto
 * GET /presupuestos/api.php?accion=compra-verano - Datos para solapa Compra Proyectada Verano
 * GET /presupuestos/api.php?accion=compra-invierno - Datos para solapa Compra Proyectada Invierno
 * GET /presupuestos/api.php?accion=stock-proyectado - Datos para solapa Stock Proyectado
 * 
 * Endpoints de búsqueda:
 * GET /presupuestos/api.php?accion=buscar&q=termino&solapa=verano - Buscar por rubro/categoría
 * GET /presupuestos/api.php?accion=rubros - Obtener lista de rubros únicos
 * GET /presupuestos/api.php?accion=filtrar-rubro&rubro=NOMBRE&solapa=verano - Filtrar por rubro
 * 
 * Endpoints de exportación:
 * GET /presupuestos/api.php?accion=exportar&solapa=verano - Exportar a Excel (verano/invierno/stock/completo)
 * 
 * Endpoints de índices:
 * POST /presupuestos/api.php?accion=actualizar-indice - Actualizar índice de variación
 * POST /presupuestos/api.php?accion=actualizar-multiples-indices - Actualizar múltiples índices
 * POST /presupuestos/api.php?accion=resetear-indices - Resetear índices a valor por defecto
 * GET /presupuestos/api.php?accion=estadisticas-indices - Obtener estadísticas de índices
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