
<?php

/**
 * Controlador específico para funcionalidades de búsqueda
 */
class BusquedaController {
    
    private $presupuesto;
    
    public function __construct() {
        require_once __DIR__ . '/../class/presupuesto.php';
        require_once __DIR__ . '/../class/PresupuestoCalculos.php';
        $this->presupuesto = new Presupuesto();
    }
    
    /**
     * Buscar datos por rubro o categoría
     */
    public function buscarDatos() {
        try {
            $termino = $_GET['q'] ?? '';
            $solapa = $_GET['solapa'] ?? 'verano';
            
            if (strlen($termino) < 2) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'El término de búsqueda debe tener al menos 2 caracteres'
                ], 400);
                return;
            }
            
            $datosBase = $this->presupuesto->obtenerPresupuestoCompras();
            
            if (isset($datosBase['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $datosBase['mensaje']
                ], 500);
                return;
            }
            
            // Filtrar datos por término de búsqueda
            $datosFiltrados = $this->filtrarDatos($datosBase, $termino);
            
            // Procesar según la solapa
            $datosProcessados = $this->procesarDatosSegunSolapa($datosFiltrados, $solapa);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Búsqueda completada',
                'data' => $datosProcessados,
                'termino' => $termino,
                'total_registros' => count($datosProcessados)
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error en búsqueda: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener rubros únicos para filtros
     */
    public function obtenerRubros() {
        try {
            $datos = $this->presupuesto->obtenerPresupuestoCompras();
            
            if (isset($datos['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $datos['mensaje']
                ], 500);
                return;
            }
            
            $rubros = array_unique(array_column($datos, 'RUBRO'));
            sort($rubros);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Rubros obtenidos correctamente',
                'data' => $rubros,
                'total_registros' => count($rubros)
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener rubros: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Filtrar por rubro específico
     */
    public function filtrarPorRubro() {
        try {
            $rubro = $_GET['rubro'] ?? '';
            $solapa = $_GET['solapa'] ?? 'verano';
            
            if (!$rubro) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Debe especificar un rubro'
                ], 400);
                return;
            }
            
            $datos = $this->presupuesto->obtenerPresupuestoCompras();
            
            if (isset($datos['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $datos['mensaje']
                ], 500);
                return;
            }
            
            // Filtrar por rubro exacto
            $datosFiltrados = array_filter($datos, function($item) use ($rubro) {
                return strcasecmp($item['RUBRO'], $rubro) === 0;
            });
            
            $datosProcessados = $this->procesarDatosSegunSolapa($datosFiltrados, $solapa);
            
            $this->jsonResponse([
                'success' => true,
                'message' => "Datos del rubro '$rubro' obtenidos correctamente",
                'data' => array_values($datosProcessados),
                'total_registros' => count($datosProcessados)
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al filtrar por rubro: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Filtrar datos por término de búsqueda
     */
    private function filtrarDatos($datos, $termino) {
        return array_filter($datos, function($registro) use ($termino) {
            $rubro = strtolower($registro['RUBRO'] ?? '');
            $categoria = strtolower($registro['CATEGORIA_PADRE'] ?? '');
            $terminoBusqueda = strtolower($termino);
            
            return strpos($rubro, $terminoBusqueda) !== false || 
                   strpos($categoria, $terminoBusqueda) !== false;
        });
    }
    
    /**
     * Procesar datos según la solapa especificada
     */
    private function procesarDatosSegunSolapa($datos, $solapa) {
        require_once __DIR__ . '/ProcesadorDatos.php';
        $procesador = new ProcesadorDatos();
        
        switch ($solapa) {
            case 'verano':
                return $procesador->procesarDatosCompraVerano($datos);
            case 'invierno':
                return $procesador->procesarDatosCompraInvierno($datos);
            case 'stock':
                return $procesador->procesarDatosStockProyectado($datos);
            default:
                return array_values($datos);
        }
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