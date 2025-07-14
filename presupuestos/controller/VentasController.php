
<?php

/**
 * Controlador para gestionar ventas de 6 meses
 */
class VentasController {
    
    private $ventas;
    
    public function __construct() {
        require_once __DIR__ . '/../class/ventas.php';
        $this->ventas = new Ventas();
    }
    
    /**
     * Obtener datos de ventas de 6 meses - MÉTODO FALTANTE AGREGADO
     */
    public function obtenerVentas6Meses() {
        try {
            $filtros = $this->obtenerFiltros();
            $datos = $this->ventas->obtenerVentas6Meses($filtros);
            
            if (isset($datos['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $datos['mensaje']
                ], 500);
                return;
            }
            
            $datosProcesados = $this->procesarDatosVentas($datos);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Datos de ventas de 6 meses obtenidos correctamente',
                'data' => $datosProcesados,
                'total_registros' => count($datosProcesados),
                'columnas_meses' => $this->extraerColumnasMeses($datosProcesados)
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener ventas: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Buscar en ventas de 6 meses
     */
    public function buscarVentas6Meses() {
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
            
            $datos = $this->ventas->buscarVentas6Meses($termino, $filtros);
            
            if (isset($datos['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $datos['mensaje']
                ], 500);
                return;
            }
            
            $datosProcesados = $this->procesarDatosVentas($datos);
            
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
     * Obtener rubros para filtro
     */
    public function obtenerRubrosVentas() {
        try {
            $rubros = $this->ventas->obtenerRubrosVentas();
            
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
     * Obtener categorías para filtro
     */
    public function obtenerCategoriasVentas() {
        try {
            $categorias = $this->ventas->obtenerCategoriasVentas();
            
            if (isset($categorias['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $categorias['mensaje']
                ], 500);
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $categorias
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener categorías: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener resumen de ventas
     */
    public function obtenerResumenVentas() {
        try {
            $filtros = $this->obtenerFiltros();
            $resumen = $this->ventas->obtenerResumenVentas($filtros);
            
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
            $resultado = $this->ventas->probarConexion();
            
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
            'rubro' => $_GET['rubro'] ?? '',
            'categoria' => $_GET['categoria'] ?? '',
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? ''
        ];
    }
    
    /**
     * Procesar datos de ventas para agregar información adicional
     */
    private function procesarDatosVentas($datos) {
        $resultado = [];
        
        foreach ($datos as $item) {
            $ventasActuales = (float)($item['VTA_ULT_60_DIAS'] ?? 0);
            $ventasAnteriores = (float)($item['VTA_ULT_60_DIAS_ANO_ANT'] ?? 0);
            $indiceVariacion = (float)($item['INDICE_VARIACION'] ?? 1.0);
            
            // Calcular variación porcentual
            $variacionPorcentual = 0;
            if ($ventasAnteriores > 0) {
                $variacionPorcentual = (($ventasActuales - $ventasAnteriores) / $ventasAnteriores) * 100;
            }
            
            // Determinar estado
            $estado = 'estable';
            if ($indiceVariacion > 1.2) {
                $estado = 'mejora';
            } elseif ($indiceVariacion < 0.8) {
                $estado = 'declive';
            }
            
            $itemProcesado = [
                'RUBRO' => $item['RUBRO'] ?? '',
                'CATEGORIA_PADRE' => $item['CATEGORIA_PADRE'] ?? '',
                'VTA_ULT_60_DIAS' => $ventasActuales,
                'VTA_ULT_60_DIAS_ANO_ANT' => $ventasAnteriores,
                'INDICE_VARIACION' => $indiceVariacion,
                'VARIACION_PORCENTUAL' => round($variacionPorcentual, 2),
                'ESTADO' => $estado
            ];
            
            // Agregar columnas dinámicas de meses
            foreach ($item as $key => $value) {
                if (strpos($key, 'VTA_') === 0 && preg_match('/VTA_\d+_\d{4}/', $key)) {
                    $itemProcesado[$key] = (float)($value ?? 0);
                }
            }
            
            $resultado[] = $itemProcesado;
        }
        
        return $resultado;
    }
    
    /**
     * Extraer información de columnas de meses para el frontend
     */
    private function extraerColumnasMeses($datos) {
        if (empty($datos)) return [];
        
        $columnas = [];
        $primer = $datos[0];
        
        foreach (array_keys($primer) as $key) {
            if (preg_match('/^VTA_(\d+)_(\d{4})$/', $key, $matches)) {
                $mes = (int)$matches[1];
                $ano = (int)$matches[2];
                
                if ($mes >= 1 && $mes <= 12) {
                    $columnas[] = [
                        'campo' => $key,
                        'mes' => $mes,
                        'ano' => $ano,
                        'nombre' => $this->formatearNombreMes($mes, $ano)
                    ];
                }
            }
        }
        
        // Ordenar por fecha
        usort($columnas, function($a, $b) {
            $fechaA = mktime(0, 0, 0, $a['mes'], 1, $a['ano']);
            $fechaB = mktime(0, 0, 0, $b['mes'], 1, $b['ano']);
            return $fechaA - $fechaB;
        });
        
        return $columnas;
    }
    
    /**
     * Formatear nombre del mes
     */
    private function formatearNombreMes($mes, $ano) {
        $meses = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
        ];
        
        return $meses[$mes] . ' ' . $ano;
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