
<?php

/**
 * Controlador para el detalle de compras pendientes
 */
class ComprasDetalleController {
    
    private $presupuesto;
    
    public function __construct() {
        require_once __DIR__ . '/../class/presupuesto.php';
        $this->presupuesto = new Presupuesto();
    }
    
    /**
     * Obtener detalle de compras pendientes
     */
    public function obtenerComprasDetalle() {
        try {
            $filtros = $this->obtenerFiltros();
            $datos = $this->presupuesto->obtenerComprasDetalle($filtros);
            
            if (isset($datos['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $datos['mensaje']
                ], 500);
                return;
            }
            
            // Procesar datos para agregar totales y formatear
            $datosProcesados = $this->procesarDatosComprasDetalle($datos);
            $resumen = $this->calcularResumen($datosProcesados);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Datos de compras detalle obtenidos correctamente',
                'data' => $datosProcesados,
                'resumen' => $resumen,
                'total_registros' => count($datosProcesados)
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener detalle de compras: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener proveedores únicos para filtro
     */
    public function obtenerProveedores() {
        try {
            $proveedores = $this->presupuesto->obtenerProveedoresCompras();
            
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
     * Obtener rubros únicos para filtro
     */
    public function obtenerRubrosCompras() {
        try {
            $rubros = $this->presupuesto->obtenerRubrosCompras();
            
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
            
            $datos = $this->presupuesto->buscarComprasDetalle($termino, $filtros);
            
            if (isset($datos['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $datos['mensaje']
                ], 500);
                return;
            }
            
            $datosProcesados = $this->procesarDatosComprasDetalle($datos);
            $resumen = $this->calcularResumen($datosProcesados);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Búsqueda completada',
                'data' => $datosProcesados,
                'resumen' => $resumen,
                'termino' => $termino,
                'total_registros' => count($datosProcesados)
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error en búsqueda: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener resumen de compras por temporada
     */
    public function obtenerResumenCompras() {
        try {
            $filtros = $this->obtenerFiltros();
            $datos = $this->presupuesto->obtenerComprasDetalle($filtros);
            
            if (isset($datos['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $datos['mensaje']
                ], 500);
                return;
            }
            
            $resumen = $this->calcularResumen($datos);
            $resumenPorProveedor = $this->calcularResumenPorProveedor($datos);
            $resumenPorRubro = $this->calcularResumenPorRubro($datos);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Resumen calculado correctamente',
                'resumen_general' => $resumen,
                'resumen_por_proveedor' => $resumenPorProveedor,
                'resumen_por_rubro' => $resumenPorRubro
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al calcular resumen: ' . $e->getMessage()
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
     * Procesar datos para agregar totales y formatear
     */
    private function procesarDatosComprasDetalle($datos) {
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
     * Calcular resumen general
     */
    private function calcularResumen($datos) {
        $totales = [
            'verano' => 0,
            'invierno' => 0,
            'atemporal' => 0,
            'total' => 0,
            'ordenes_unicas' => 0,
            'proveedores_unicos' => 0,
            'articulos_unicos' => 0
        ];
        
        $ordenesUnicas = [];
        $proveedoresUnicos = [];
        $articulosUnicos = [];
        
        foreach ($datos as $item) {
            $totales['verano'] += (float)($item['VERANO'] ?? 0);
            $totales['invierno'] += (float)($item['INVIERNO'] ?? 0);
            $totales['atemporal'] += (float)($item['ATEMPORAL'] ?? 0);
            
            if (!in_array($item['N_ORDEN_CO'], $ordenesUnicas)) {
                $ordenesUnicas[] = $item['N_ORDEN_CO'];
            }
            
            if (!in_array($item['NOM_PROVEE'], $proveedoresUnicos)) {
                $proveedoresUnicos[] = $item['NOM_PROVEE'];
            }
            
            if (!in_array($item['COD_ARTICU'], $articulosUnicos)) {
                $articulosUnicos[] = $item['COD_ARTICU'];
            }
        }
        
        $totales['total'] = $totales['verano'] + $totales['invierno'] + $totales['atemporal'];
        $totales['ordenes_unicas'] = count($ordenesUnicas);
        $totales['proveedores_unicos'] = count($proveedoresUnicos);
        $totales['articulos_unicos'] = count($articulosUnicos);
        
        return $totales;
    }
    
    /**
     * Calcular resumen por proveedor
     */
    private function calcularResumenPorProveedor($datos) {
        $resumen = [];
        
        foreach ($datos as $item) {
            $proveedor = $item['NOM_PROVEE'] ?? 'Sin proveedor';
            
            if (!isset($resumen[$proveedor])) {
                $resumen[$proveedor] = [
                    'verano' => 0,
                    'invierno' => 0,
                    'atemporal' => 0,
                    'total' => 0,
                    'ordenes' => 0
                ];
            }
            
            $resumen[$proveedor]['verano'] += (float)($item['VERANO'] ?? 0);
            $resumen[$proveedor]['invierno'] += (float)($item['INVIERNO'] ?? 0);
            $resumen[$proveedor]['atemporal'] += (float)($item['ATEMPORAL'] ?? 0);
            $resumen[$proveedor]['ordenes']++;
        }
        
        // Calcular totales
        foreach ($resumen as $proveedor => &$datos) {
            $datos['total'] = $datos['verano'] + $datos['invierno'] + $datos['atemporal'];
        }
        
        // Ordenar por total descendente
        uasort($resumen, function($a, $b) {
            return $b['total'] <=> $a['total'];
        });
        
        return $resumen;
    }
    
    /**
     * Calcular resumen por rubro
     */
    private function calcularResumenPorRubro($datos) {
        $resumen = [];
        
        foreach ($datos as $item) {
            $rubro = $item['RUBRO'] ?? 'Sin rubro';
            
            if (!isset($resumen[$rubro])) {
                $resumen[$rubro] = [
                    'verano' => 0,
                    'invierno' => 0,
                    'atemporal' => 0,
                    'total' => 0,
                    'articulos' => 0
                ];
            }
            
            $resumen[$rubro]['verano'] += (float)($item['VERANO'] ?? 0);
            $resumen[$rubro]['invierno'] += (float)($item['INVIERNO'] ?? 0);
            $resumen[$rubro]['atemporal'] += (float)($item['ATEMPORAL'] ?? 0);
            $resumen[$rubro]['articulos']++;
        }
        
        // Calcular totales
        foreach ($resumen as $rubro => &$datos) {
            $datos['total'] = $datos['verano'] + $datos['invierno'] + $datos['atemporal'];
        }
        
        // Ordenar por total descendente
        uasort($resumen, function($a, $b) {
            return $b['total'] <=> $a['total'];
        });
        
        return $resumen;
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