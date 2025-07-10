
<?php

/**
 * Controlador para gestionar compras pendientes
 */
class ComprasController {
    
    private $compras;
    
    public function __construct() {
        require_once __DIR__ . '/../class/compras.php';
        $this->compras = new Compras();
    }
    
    /**
     * Obtener datos de compras pendientes
     */
    public function obtenerComprasDetalle() {
        try {
            $filtros = $this->obtenerFiltros();
            $datos = $this->compras->obtenerComprasDetalle($filtros);
            
            if (isset($datos['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $datos['mensaje']
                ], 500);
                return;
            }
            
            // Procesar datos para agregar totales
            $datosProcesados = $this->procesarDatosCompras($datos);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Datos de compras detalle obtenidos correctamente',
                'data' => $datosProcesados,
                'total_registros' => count($datosProcesados),
                'filtros_aplicados' => $filtros
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener compras detalle: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Buscar en compras detalle
     */
    public function buscarComprasDetalle() {
        try {
            $termino = $_GET['q'] ?? '';
            $filtros = $this->obtenerFiltros();
            
            if (strlen($termino) < 2) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'El término de búsqueda debe tener al menos 2 caracteres'
                ], 400);
                return;
            }
            
            $datos = $this->compras->buscarComprasDetalle($termino, $filtros);
            
            if (isset($datos['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $datos['mensaje']
                ], 500);
                return;
            }
            
            $datosProcesados = $this->procesarDatosCompras($datos);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Búsqueda completada',
                'data' => $datosProcesados,
                'termino' => $termino,
                'total_registros' => count($datosProcesados),
                'filtros_aplicados' => $filtros
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error en búsqueda: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener proveedores para filtro
     */
    public function obtenerProveedores() {
        try {
            $proveedores = $this->compras->obtenerProveedores();
            
            if (isset($proveedores['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $proveedores['mensaje']
                ], 500);
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $proveedores
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener proveedores: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener rubros para filtro
     */
    public function obtenerRubros() {
        try {
            $rubros = $this->compras->obtenerRubros();
            
            if (isset($rubros['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $rubros['mensaje']
                ], 500);
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $rubros
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener rubros: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener resumen de compras
     */
    public function obtenerResumenCompras() {
        try {
            $filtros = $this->obtenerFiltros();
            $resumen = $this->compras->obtenerResumenCompras($filtros);
            
            if (isset($resumen['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $resumen['mensaje']
                ], 500);
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Resumen calculado correctamente',
                'data' => $resumen
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener resumen: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Probar conexión
     */
    public function probarConexion() {
        try {
            $resultado = $this->compras->probarConexion();
            
            $this->jsonResponse([
                'success' => $resultado['conexion'],
                'message' => $resultado['mensaje'],
                'data' => $resultado['datos'] ?? null
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error en prueba de conexión: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener filtros desde parámetros GET
     */
    private function obtenerFiltros() {
        return [
            'proveedor' => $_GET['proveedor'] ?? '',
            'rubro' => $_GET['rubro'] ?? '',
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
            'temporada' => $_GET['temporada'] ?? ''
        ];
    }
    
    /**
     * Procesar datos de compras para agregar totales
     */
    private function procesarDatosCompras($datos) {
        $resultado = [];
        
        foreach ($datos as $item) {
            $verano = (float)($item['VERANO'] ?? 0);
            $invierno = (float)($item['INVIERNO'] ?? 0);
            $atemporal = (float)($item['ATEMPORAL'] ?? 0);
            $total = $verano + $invierno + $atemporal;
            
            $resultado[] = [
                'FEC_EMISIO' => $item['FEC_EMISIO'] ?? '',
                'N_ORDEN_CO' => $item['N_ORDEN_CO'] ?? '',
                'NOM_PROVEE' => $item['NOM_PROVEE'] ?? '',
                'COD_ARTICU' => $item['COD_ARTICU'] ?? '',
                'DESCRIPCIO' => $item['DESCRIPCIO'] ?? '',
                'RUBRO' => $item['RUBRO'] ?? '',
                'CATEGORIA_PADRE' => $item['CATEGORIA_PADRE'] ?? '',
                'VERANO' => $verano,
                'INVIERNO' => $invierno,
                'ATEMPORAL' => $atemporal,
                'TOTAL' => $total
            ];
        }
        
        return $resultado;
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