
<?php

/**
 * Controlador específico para actualización de índices de variación
 */
class IndiceController {
    
    private $presupuesto;
    
    public function __construct() {
        require_once __DIR__ . '/../class/presupuesto.php';
        require_once __DIR__ . '/../class/PresupuestoCalculos.php';
        require_once __DIR__ . '/ProcesadorDatos.php';
        $this->presupuesto = new Presupuesto();
    }
    
    /**
     * Actualizar índice de variación para un registro específico
     */
    public function actualizarIndiceVariacion() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $rubro = $input['rubro'] ?? '';
            $categoria = $input['categoria'] ?? '';
            $nuevoIndice = $input['indice'] ?? 1.0;
            $solapa = $input['solapa'] ?? 'verano';
            
            // Validar entrada
            if (!$this->validarEntrada($rubro, $categoria, $nuevoIndice)) {
                return;
            }
            
            $nuevoIndice = PresupuestoCalculos::validarIndiceVariacion($nuevoIndice);
            
            // Obtener y actualizar datos
            $datosBase = $this->obtenerDatosBase();
            $registroActualizado = $this->actualizarRegistro($datosBase, $rubro, $categoria, $nuevoIndice, $solapa);
            
            if ($registroActualizado) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Índice actualizado correctamente',
                    'data' => $registroActualizado
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ], 404);
            }
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al actualizar índice: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Actualizar múltiples índices de una vez
     */
    public function actualizarMultiplesIndices() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $actualizaciones = $input['actualizaciones'] ?? [];
            $solapa = $input['solapa'] ?? 'verano';
            
            if (empty($actualizaciones)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'No se proporcionaron actualizaciones'
                ], 400);
                return;
            }
            
            $datosBase = $this->obtenerDatosBase();
            $resultados = [];
            
            foreach ($actualizaciones as $actualizacion) {
                $rubro = $actualizacion['rubro'] ?? '';
                $categoria = $actualizacion['categoria'] ?? '';
                $nuevoIndice = $actualizacion['indice'] ?? 1.0;
                
                if ($rubro && $categoria) {
                    $nuevoIndice = PresupuestoCalculos::validarIndiceVariacion($nuevoIndice);
                    $resultado = $this->actualizarRegistro($datosBase, $rubro, $categoria, $nuevoIndice, $solapa);
                    
                    $resultados[] = [
                        'rubro' => $rubro,
                        'categoria' => $categoria,
                        'exitoso' => $resultado !== null,
                        'datos' => $resultado
                    ];
                }
            }
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Actualizaciones procesadas',
                'data' => $resultados,
                'total_procesados' => count($resultados)
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al actualizar múltiples índices: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Resetear índices a valor por defecto
     */
    public function resetearIndices() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $valorDefecto = $input['valor_defecto'] ?? 1.0;
            $filtros = $input['filtros'] ?? [];
            
            $valorDefecto = PresupuestoCalculos::validarIndiceVariacion($valorDefecto);
            
            $datosBase = $this->obtenerDatosBase();
            $contadorActualizados = 0;
            
            foreach ($datosBase as &$registro) {
                // Aplicar filtros si existen
                if ($this->aplicarFiltros($registro, $filtros)) {
                    $registro['INDICE_VARIACION'] = $valorDefecto;
                    $contadorActualizados++;
                }
            }
            
            $this->jsonResponse([
                'success' => true,
                'message' => "Se resetearon $contadorActualizados índices",
                'registros_actualizados' => $contadorActualizados,
                'valor_defecto' => $valorDefecto
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al resetear índices: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener estadísticas de índices
     */
    public function obtenerEstadisticasIndices() {
        try {
            $datos = $this->obtenerDatosBase();
            $indices = array_column($datos, 'INDICE_VARIACION');
            $indices = array_map('floatval', array_filter($indices, 'is_numeric'));
            
            if (empty($indices)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'No se encontraron índices válidos'
                ], 404);
                return;
            }
            
            $estadisticas = [
                'total_registros' => count($indices),
                'promedio' => round(array_sum($indices) / count($indices), 4),
                'minimo' => min($indices),
                'maximo' => max($indices),
                'mediana' => $this->calcularMediana($indices),
                'registros_por_rango' => $this->contarPorRango($indices)
            ];
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Estadísticas calculadas',
                'data' => $estadisticas
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al calcular estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Validar entrada de datos
     */
    private function validarEntrada($rubro, $categoria, $indice) {
        if (!$rubro || !$categoria) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Rubro y categoría son requeridos'
            ], 400);
            return false;
        }
        
        if (!is_numeric($indice) || $indice < 0 || $indice > 10) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'El índice debe ser un número entre 0 y 10'
            ], 400);
            return false;
        }
        
        return true;
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
     * Actualizar un registro específico
     */
    private function actualizarRegistro(&$datosBase, $rubro, $categoria, $nuevoIndice, $solapa) {
        foreach ($datosBase as &$registro) {
            if ($registro['RUBRO'] === $rubro && $registro['CATEGORIA_PADRE'] === $categoria) {
                $registro['INDICE_VARIACION'] = $nuevoIndice;
                
                // Recalcular según la solapa
                $procesador = new ProcesadorDatos();
                if ($solapa === 'verano') {
                    return $procesador->procesarRegistroCompraVerano($registro);
                } else {
                    return $procesador->procesarRegistroCompraInvierno($registro);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Aplicar filtros a un registro
     */
    private function aplicarFiltros($registro, $filtros) {
        if (empty($filtros)) {
            return true; // Sin filtros, aplicar a todos
        }
        
        foreach ($filtros as $campo => $valor) {
            if (isset($registro[$campo]) && $registro[$campo] !== $valor) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Calcular mediana de un array
     */
    private function calcularMediana($array) {
        sort($array);
        $count = count($array);
        $middle = floor(($count - 1) / 2);
        
        if ($count % 2) {
            return $array[$middle];
        } else {
            return ($array[$middle] + $array[$middle + 1]) / 2;
        }
    }
    
    /**
     * Contar registros por rango de índices
     */
    private function contarPorRango($indices) {
        $rangos = [
            'muy_bajo' => 0,    // 0 - 0.5
            'bajo' => 0,        // 0.5 - 0.8
            'normal' => 0,      // 0.8 - 1.2
            'alto' => 0,        // 1.2 - 2.0
            'muy_alto' => 0     // > 2.0
        ];
        
        foreach ($indices as $indice) {
            if ($indice < 0.5) {
                $rangos['muy_bajo']++;
            } elseif ($indice < 0.8) {
                $rangos['bajo']++;
            } elseif ($indice <= 1.2) {
                $rangos['normal']++;
            } elseif ($indice <= 2.0) {
                $rangos['alto']++;
            } else {
                $rangos['muy_alto']++;
            }
        }
        
        return $rangos;
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