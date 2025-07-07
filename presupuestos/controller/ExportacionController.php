
<?php

/**
 * Controlador específico para funcionalidades de exportación
 */
class ExportacionController {
    
    private $presupuesto;
    
    public function __construct() {
        require_once __DIR__ . '/../class/presupuesto.php';
        require_once __DIR__ . '/../class/ExcelExporter.php';
        require_once __DIR__ . '/ProcesadorDatos.php';
        $this->presupuesto = new Presupuesto();
    }
    
    /**
     * Exportar a Excel según la solapa especificada
     */
    public function exportarExcel() {
        try {
            $solapa = $_GET['solapa'] ?? 'verano';
            
            switch ($solapa) {
                case 'verano':
                    $this->exportarCompraVeranoExcel();
                    break;
                case 'invierno':
                    $this->exportarCompraInviernoExcel();
                    break;
                case 'stock':
                    $this->exportarStockProyectadoExcel();
                    break;
                case 'completo':
                    $this->exportarPresupuestoCompletoExcel();
                    break;
                default:
                    $this->jsonResponse([
                        'success' => false,
                        'message' => 'Solapa no válida. Use: verano, invierno, stock o completo'
                    ], 400);
            }
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al exportar: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Exportar compra verano a Excel
     */
    private function exportarCompraVeranoExcel() {
        $datosBase = $this->obtenerDatosBase();
        $procesador = new ProcesadorDatos();
        $datos = $procesador->procesarDatosCompraVerano($datosBase);
        
        ExcelExporter::exportarExcel(
            $datos, 
            'Compra Verano', 
            'compra_verano_' . date('Y-m-d') . '.xlsx'
        );
    }
    
    /**
     * Exportar compra invierno a Excel
     */
    private function exportarCompraInviernoExcel() {
        $datosBase = $this->obtenerDatosBase();
        $procesador = new ProcesadorDatos();
        $datos = $procesador->procesarDatosCompraInvierno($datosBase);
        
        ExcelExporter::exportarExcel(
            $datos, 
            'Compra Invierno', 
            'compra_invierno_' . date('Y-m-d') . '.xlsx'
        );
    }
    
    /**
     * Exportar stock proyectado a Excel
     */
    private function exportarStockProyectadoExcel() {
        $datosBase = $this->obtenerDatosBase();
        $procesador = new ProcesadorDatos();
        $datos = $procesador->procesarDatosStockProyectado($datosBase);
        
        ExcelExporter::exportarExcel(
            $datos, 
            'Stock Proyectado', 
            'stock_proyectado_' . date('Y-m-d') . '.xlsx'
        );
    }
    
    /**
     * Exportar presupuesto completo con todas las solapas
     */
    private function exportarPresupuestoCompletoExcel() {
        $datosBase = $this->obtenerDatosBase();
        $procesador = new ProcesadorDatos();
        
        $datosVerano = $procesador->procesarDatosCompraVerano($datosBase);
        $datosInvierno = $procesador->procesarDatosCompraInvierno($datosBase);
        $datosStock = $procesador->procesarDatosStockProyectado($datosBase);
        
        ExcelExporter::exportarPresupuestoCompleto($datosVerano, $datosInvierno, $datosStock);
    }
    
    /**
     * Obtener datos base con validación
     */
    private function obtenerDatosBase() {
        $datos = $this->presupuesto->obtenerPresupuestoCompras();
        
        if (isset($datos['error'])) {
            throw new Exception($datos['mensaje']);
        }
        
        return $datos;
    }
    
    /**
     * Enviar respuesta JSON
     */
    private function jsonResponse($data, $httpCode = 200) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
?>