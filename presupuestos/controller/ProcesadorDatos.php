
<?php

/**
 * Clase dedicada al procesamiento de datos para las diferentes solapas
 * Sistema de Presupuesto de Compras v2.1
 * 
 * CORRECCIÓN: Manejo mejorado de datos y prevención de duplicados
 */
class ProcesadorDatos {
    
    /**
     * Procesar datos para la solapa de compra verano
     */
    public function procesarDatosCompraVerano($datos) {
        $resultado = [];
        
        if (empty($datos)) {
            return $resultado;
        }
        
        // Asegurar que no hay duplicados en los datos fuente
        $datosUnicos = $this->eliminarDuplicados($datos);
        
        foreach ($datosUnicos as $registro) {
            try {
                $stockProyectado = $this->calcularStockProyectadoRegistro($registro);
                $calculosCompra = PresupuestoCalculos::procesarRegistroCompraProyectada(
                    $registro, 
                    $stockProyectado, 
                    'VERANO'
                );
                
                $registroProcesado = [
                    'RUBRO' => $this->limpiarTexto($registro['RUBRO'] ?? ''),
                    'CATEGORIA_PADRE' => $this->limpiarTexto($registro['CATEGORIA_PADRE'] ?? ''),
                    'STOCK_PROYECTADO' => round($stockProyectado, 2),
                    'INDICE_VARIACION' => round((float)($registro['INDICE_VARIACION'] ?? 1.0), 4),
                    'VENTA_PROY_VERANO' => round($calculosCompra['venta_proy_verano'], 2),
                    'VENTA_PROY_INVIERNO' => round($calculosCompra['venta_proy_invierno'], 2),
                    'COMPRA_PROYECTADA' => round($calculosCompra['compra_proyectada'], 2)
                ];
                
                // Agregar columnas de ventas históricas de forma controlada
                $columnasVenta = $this->obtenerColumnasVentasSeguras($registro);
                foreach ($columnasVenta as $columna) {
                    $registroProcesado[$columna] = round((float)($registro[$columna] ?? 0), 2);
                }
                
                $resultado[] = $registroProcesado;
                
            } catch (Exception $e) {
                error_log("Error procesando registro de verano: " . $e->getMessage());
                continue; // Saltar registro problemático
            }
        }
        
        return $resultado;
    }
    
    /**
     * Procesar datos para la solapa de compra invierno
     */
    public function procesarDatosCompraInvierno($datos) {
        $resultado = [];
        
        if (empty($datos)) {
            return $resultado;
        }
        
        // Asegurar que no hay duplicados en los datos fuente
        $datosUnicos = $this->eliminarDuplicados($datos);
        
        foreach ($datosUnicos as $registro) {
            try {
                $stockProyectado = $this->calcularStockProyectadoRegistro($registro);
                $calculosCompra = PresupuestoCalculos::procesarRegistroCompraProyectada(
                    $registro, 
                    $stockProyectado, 
                    'INVIERNO'
                );
                
                $registroProcesado = [
                    'RUBRO' => $this->limpiarTexto($registro['RUBRO'] ?? ''),
                    'CATEGORIA_PADRE' => $this->limpiarTexto($registro['CATEGORIA_PADRE'] ?? ''),
                    'STOCK_PROYECTADO' => round($stockProyectado, 2),
                    'INDICE_VARIACION' => round((float)($registro['INDICE_VARIACION'] ?? 1.0), 4),
                    'VENTA_PROY_VERANO' => round($calculosCompra['venta_proy_verano'], 2),
                    'VENTA_PROY_INVIERNO' => round($calculosCompra['venta_proy_invierno'], 2),
                    'COMPRA_PROYECTADA' => round($calculosCompra['compra_proyectada'], 2)
                ];
                
                // Agregar columnas de ventas históricas de forma controlada
                $columnasVenta = $this->obtenerColumnasVentasSeguras($registro);
                foreach ($columnasVenta as $columna) {
                    $registroProcesado[$columna] = round((float)($registro[$columna] ?? 0), 2);
                }
                
                $resultado[] = $registroProcesado;
                
            } catch (Exception $e) {
                error_log("Error procesando registro de invierno: " . $e->getMessage());
                continue; // Saltar registro problemático
            }
        }
        
        return $resultado;
    }
    
    /**
     * Procesar datos para la solapa de stock proyectado
     */
    public function procesarDatosStockProyectado($datos) {
        $resultado = [];
        
        if (empty($datos)) {
            return $resultado;
        }
        
        // Asegurar que no hay duplicados en los datos fuente
        $datosUnicos = $this->eliminarDuplicados($datos);
        
        foreach ($datosUnicos as $registro) {
            try {
                $stock = round((float)($registro['CANT_STOCK'] ?? 0), 2);
                $stockGuardar = round((float)($registro['CANT_STOCK_GUARDAR'] ?? 0), 2);
                $comprasVerano = round((float)($registro['CANT_PEND_OC_VERANO'] ?? 0), 2);
                $comprasInvierno = round((float)($registro['CANT_PEND_OC_INVIERNO'] ?? 0), 2);
                $comprasAtemporal = round((float)($registro['CANT_PEND_OC_ATEMPORAL'] ?? 0), 2);
                
                // Calcular stock cobertura
                $stockCobertura = round((float)($registro['STOCK_COBERTURA'] ?? 0), 2);
                
                $stockProyectado = PresupuestoCalculos::calcularStockProyectado(
                    $stock, 
                    $stockGuardar, 
                    $comprasVerano, 
                    $comprasInvierno, 
                    $comprasAtemporal, 
                    $stockCobertura
                );
                
                $resultado[] = [
                    'RUBRO' => $this->limpiarTexto($registro['RUBRO'] ?? ''),
                    'CATEGORIA_PADRE' => $this->limpiarTexto($registro['CATEGORIA_PADRE'] ?? ''),
                    'STOCK' => $stock,
                    'STOCK_GUARDAR' => $stockGuardar,
                    'COMPRAS_VERANO' => $comprasVerano,
                    'COMPRAS_INVIERNO' => $comprasInvierno,
                    'COMPRAS_ATEMPORAL' => $comprasAtemporal,
                    'STOCK_COBERTURA' => $stockCobertura,
                    'STOCK_PROYECTADO' => round($stockProyectado, 2)
                ];
                
            } catch (Exception $e) {
                error_log("Error procesando registro de stock: " . $e->getMessage());
                continue; // Saltar registro problemático
            }
        }
        
        return $resultado;
    }
    
    /**
     * NUEVO: Eliminar registros duplicados basado en RUBRO + CATEGORIA_PADRE
     */
    private function eliminarDuplicados($datos) {
        $vistos = [];
        $unicos = [];
        
        foreach ($datos as $registro) {
            $clave = trim($registro['RUBRO'] ?? '') . '|' . trim($registro['CATEGORIA_PADRE'] ?? '');
            
            if (!isset($vistos[$clave])) {
                $vistos[$clave] = true;
                $unicos[] = $registro;
            }
        }
        
        return $unicos;
    }
    
    /**
     * NUEVO: Obtener columnas de ventas de forma segura (sin duplicados)
     */
    private function obtenerColumnasVentasSeguras($registro) {
        $columnasVenta = [];
        $columnasExcluidas = [
            'RUBRO', 'CATEGORIA_PADRE', 'CANT_STOCK', 'CANT_STOCK_GUARDAR',
            'CANT_PEND_OC_VERANO', 'CANT_PEND_OC_INVIERNO', 'CANT_PEND_OC_ATEMPORAL',
            'INDICE_VARIACION', 'STOCK_COBERTURA', 'VENTA_PROY_VERANO', 
            'VENTA_PROY_INVIERNO', 'COMPRA_PROYECTADA', 'STOCK_PROYECTADO'
        ];
        
        foreach (array_keys($registro) as $columna) {
            // Verificar si es columna de venta histórica
            if (!in_array($columna, $columnasExcluidas) && 
                (stripos($columna, 'VERANO') !== false || 
                 stripos($columna, 'INVIERNO') !== false ||
                 stripos($columna, 'VTA_') !== false)) {
                $columnasVenta[] = $columna;
            }
        }
        
        // Eliminar duplicados y ordenar
        $columnasVenta = array_unique($columnasVenta);
        sort($columnasVenta);
        
        return $columnasVenta;
    }
    
    /**
     * NUEVO: Limpiar texto para evitar problemas de encoding/espacios
     */
    private function limpiarTexto($texto) {
        return trim($texto);
    }
    
    /**
     * Procesar registro individual para compra verano
     */
    public function procesarRegistroCompraVerano($registro) {
        $stockProyectado = $this->calcularStockProyectadoRegistro($registro);
        $calculosCompra = PresupuestoCalculos::procesarRegistroCompraProyectada(
            $registro, 
            $stockProyectado, 
            'VERANO'
        );
        
        return array_merge($registro, [
            'STOCK_PROYECTADO' => round($stockProyectado, 2),
            'VENTA_PROY_VERANO' => round($calculosCompra['venta_proy_verano'], 2),
            'VENTA_PROY_INVIERNO' => round($calculosCompra['venta_proy_invierno'], 2),
            'COMPRA_PROYECTADA' => round($calculosCompra['compra_proyectada'], 2)
        ]);
    }
    
    /**
     * Procesar registro individual para compra invierno
     */
    public function procesarRegistroCompraInvierno($registro) {
        $stockProyectado = $this->calcularStockProyectadoRegistro($registro);
        $calculosCompra = PresupuestoCalculos::procesarRegistroCompraProyectada(
            $registro, 
            $stockProyectado, 
            'INVIERNO'
        );
        
        return array_merge($registro, [
            'STOCK_PROYECTADO' => round($stockProyectado, 2),
            'VENTA_PROY_VERANO' => round($calculosCompra['venta_proy_verano'], 2),
            'VENTA_PROY_INVIERNO' => round($calculosCompra['venta_proy_invierno'], 2),
            'COMPRA_PROYECTADA' => round($calculosCompra['compra_proyectada'], 2)
        ]);
    }
    
    /**
     * Calcular stock proyectado para un registro individual
     */
    public function calcularStockProyectadoRegistro($registro) {
        $stock = (float)($registro['CANT_STOCK'] ?? 0);
        $stockGuardar = (float)($registro['CANT_STOCK_GUARDAR'] ?? 0);
        $comprasVerano = (float)($registro['CANT_PEND_OC_VERANO'] ?? 0);
        $comprasInvierno = (float)($registro['CANT_PEND_OC_INVIERNO'] ?? 0);
        $comprasAtemporal = (float)($registro['CANT_PEND_OC_ATEMPORAL'] ?? 0);
        $stockCobertura = (float)($registro['STOCK_COBERTURA'] ?? 0);
        
        return PresupuestoCalculos::calcularStockProyectado(
            $stock, 
            $stockGuardar, 
            $comprasVerano, 
            $comprasInvierno, 
            $comprasAtemporal, 
            $stockCobertura
        );
    }
    
    /**
     * Obtener resumen de datos procesados
     */
    public function obtenerResumenDatos($datos, $tipo = 'general') {
        if (empty($datos)) {
            return [
                'total_registros' => 0,
                'rubros_unicos' => 0,
                'categorias_unicas' => 0
            ];
        }
        
        $resumen = [
            'total_registros' => count($datos),
            'rubros_unicos' => count(array_unique(array_column($datos, 'RUBRO'))),
            'categorias_unicas' => count(array_unique(array_column($datos, 'CATEGORIA_PADRE')))
        ];
        
        switch ($tipo) {
            case 'compra':
                $resumen = array_merge($resumen, $this->calcularResumenCompra($datos));
                break;
            case 'stock':
                $resumen = array_merge($resumen, $this->calcularResumenStock($datos));
                break;
        }
        
        return $resumen;
    }
    
    /**
     * Calcular resumen específico para datos de compra
     */
    private function calcularResumenCompra($datos) {
        $totalCompraProyectada = array_sum(array_column($datos, 'COMPRA_PROYECTADA'));
        $totalVentaVerano = array_sum(array_column($datos, 'VENTA_PROY_VERANO'));
        $totalVentaInvierno = array_sum(array_column($datos, 'VENTA_PROY_INVIERNO'));
        $totalStock = array_sum(array_column($datos, 'STOCK_PROYECTADO'));
        
        // Contar registros por estado de compra
        $comprasPositivas = count(array_filter($datos, function($item) {
            return ($item['COMPRA_PROYECTADA'] ?? 0) > 0;
        }));
        
        $comprasNegativas = count(array_filter($datos, function($item) {
            return ($item['COMPRA_PROYECTADA'] ?? 0) < 0;
        }));
        
        return [
            'total_compra_proyectada' => round($totalCompraProyectada, 2),
            'total_venta_verano' => round($totalVentaVerano, 2),
            'total_venta_invierno' => round($totalVentaInvierno, 2),
            'total_stock_proyectado' => round($totalStock, 2),
            'compras_positivas' => $comprasPositivas,
            'compras_negativas' => $comprasNegativas,
            'porcentaje_compras_positivas' => count($datos) > 0 ? round(($comprasPositivas / count($datos)) * 100, 2) : 0
        ];
    }
    
    /**
     * Calcular resumen específico para datos de stock
     */
    private function calcularResumenStock($datos) {
        $totalStock = array_sum(array_column($datos, 'STOCK'));
        $totalStockGuardar = array_sum(array_column($datos, 'STOCK_GUARDAR'));
        $totalComprasVerano = array_sum(array_column($datos, 'COMPRAS_VERANO'));
        $totalComprasInvierno = array_sum(array_column($datos, 'COMPRAS_INVIERNO'));
        $totalComprasAtemporal = array_sum(array_column($datos, 'COMPRAS_ATEMPORAL'));
        $totalStockProyectado = array_sum(array_column($datos, 'STOCK_PROYECTADO'));
        
        return [
            'total_stock_actual' => round($totalStock, 2),
            'total_stock_guardar' => round($totalStockGuardar, 2),
            'total_compras_verano' => round($totalComprasVerano, 2),
            'total_compras_invierno' => round($totalComprasInvierno, 2),
            'total_compras_atemporal' => round($totalComprasAtemporal, 2),
            'total_stock_proyectado' => round($totalStockProyectado, 2),
            'incremento_stock' => round($totalStockProyectado - $totalStock, 2)
        ];
    }
    
    /**
     * Validar datos procesados (MEJORADO)
     */
    public function validarDatosProcesados($datos, $tipo = 'general') {
        $errores = [];
        $advertencias = [];
        
        foreach ($datos as $index => $registro) {
            // Validaciones generales
            if (empty(trim($registro['RUBRO'] ?? ''))) {
                $errores[] = "Registro $index: RUBRO vacío";
            }
            
            if (empty(trim($registro['CATEGORIA_PADRE'] ?? ''))) {
                $errores[] = "Registro $index: CATEGORIA_PADRE vacía";
            }
            
            // Validaciones específicas por tipo
            switch ($tipo) {
                case 'compra':
                    $this->validarDatosCompra($registro, $index, $errores, $advertencias);
                    break;
                case 'stock':
                    $this->validarDatosStock($registro, $index, $errores, $advertencias);
                    break;
            }
        }
        
        return [
            'valido' => empty($errores),
            'errores' => $errores,
            'advertencias' => $advertencias,
            'total_errores' => count($errores),
            'total_advertencias' => count($advertencias)
        ];
    }
    
    /**
     * Validar datos específicos de compra (MEJORADO)
     */
    private function validarDatosCompra($registro, $index, &$errores, &$advertencias) {
        $campos = ['STOCK_PROYECTADO', 'INDICE_VARIACION', 'VENTA_PROY_VERANO', 'VENTA_PROY_INVIERNO', 'COMPRA_PROYECTADA'];
        
        foreach ($campos as $campo) {
            if (!isset($registro[$campo]) || !is_numeric($registro[$campo])) {
                $errores[] = "Registro $index: $campo no es numérico";
            }
        }
        
        if (isset($registro['INDICE_VARIACION'])) {
            $indice = (float)$registro['INDICE_VARIACION'];
            if ($indice < 0 || $indice > 10) {
                $errores[] = "Registro $index: INDICE_VARIACION fuera de rango (0-10)";
            } elseif ($indice > 5) {
                $advertencias[] = "Registro $index: INDICE_VARIACION muy alto (>5): $indice";
            }
        }
        
        // Validar coherencia de compra proyectada
        if (isset($registro['COMPRA_PROYECTADA'])) {
            $compra = (float)$registro['COMPRA_PROYECTADA'];
            if (abs($compra) > 10000) {
                $advertencias[] = "Registro $index: COMPRA_PROYECTADA muy alta: " . number_format($compra);
            }
        }
    }
    
    /**
     * Validar datos específicos de stock (MEJORADO)
     */
    private function validarDatosStock($registro, $index, &$errores, &$advertencias) {
        $campos = ['STOCK', 'STOCK_GUARDAR', 'COMPRAS_VERANO', 'COMPRAS_INVIERNO', 'COMPRAS_ATEMPORAL', 'STOCK_PROYECTADO'];
        
        foreach ($campos as $campo) {
            if (!isset($registro[$campo]) || !is_numeric($registro[$campo])) {
                $errores[] = "Registro $index: $campo no es numérico";
            }
        }
        
        // Validar que el stock proyectado sea coherente
        if (isset($registro['STOCK']) && isset($registro['STOCK_PROYECTADO'])) {
            $stock = (float)$registro['STOCK'];
            $stockProyectado = (float)$registro['STOCK_PROYECTADO'];
            
            if ($stock > 0) {
                $diferenciaPorcentual = abs($stockProyectado - $stock) / $stock * 100;
                if ($diferenciaPorcentual > 1000) { // Más del 1000% de diferencia
                    $errores[] = "Registro $index: STOCK_PROYECTADO tiene una diferencia muy grande respecto al STOCK";
                } elseif ($diferenciaPorcentual > 500) {
                    $advertencias[] = "Registro $index: STOCK_PROYECTADO con diferencia alta respecto al STOCK";
                }
            }
        }
    }
    
    /**
     * Ordenar datos según criterio
     */
    public function ordenarDatos($datos, $campo = 'RUBRO', $direccion = 'ASC') {
        if (empty($datos) || !isset($datos[0][$campo])) {
            return $datos;
        }
        
        usort($datos, function($a, $b) use ($campo, $direccion) {
            $valorA = $a[$campo] ?? '';
            $valorB = $b[$campo] ?? '';
            
            // Si son números, comparar numéricamente
            if (is_numeric($valorA) && is_numeric($valorB)) {
                $resultado = $valorA <=> $valorB;
            } else {
                // Comparar como strings
                $resultado = strcasecmp($valorA, $valorB);
            }
            
            return $direccion === 'DESC' ? -$resultado : $resultado;
        });
        
        return $datos;
    }
    
    /**
     * Aplicar filtros a los datos
     */
    public function aplicarFiltros($datos, $filtros = []) {
        if (empty($filtros)) {
            return $datos;
        }
        
        return array_filter($datos, function($registro) use ($filtros) {
            foreach ($filtros as $campo => $valor) {
                if (!isset($registro[$campo])) {
                    return false;
                }
                
                $valorRegistro = $registro[$campo];
                
                // Si el valor es un array, buscar si está incluido
                if (is_array($valor)) {
                    if (!in_array($valorRegistro, $valor)) {
                        return false;
                    }
                } else {
                    // Comparación exacta o parcial (si contiene *)
                    if (strpos($valor, '*') !== false) {
                        $patron = str_replace('*', '.*', preg_quote($valor, '/'));
                        if (!preg_match("/^$patron$/i", $valorRegistro)) {
                            return false;
                        }
                    } else {
                        if (strcasecmp($valorRegistro, $valor) !== 0) {
                            return false;
                        }
                    }
                }
            }
            
            return true;
        });
    }
    
    // [Resto de los métodos se mantienen igual que en la versión anterior]
    // ... (métodos como filtrarPorRango, agruparDatos, calcularEstadisticas, etc.)
    
    /**
     * NUEVO: Método para diagnosticar problemas de datos
     */
    public function diagnosticarDatos($datos) {
        $diagnostico = [
            'total_registros' => count($datos),
            'registros_vacios' => 0,
            'duplicados_potenciales' => 0,
            'columnas_encontradas' => [],
            'valores_extremos' => []
        ];
        
        if (empty($datos)) {
            return $diagnostico;
        }
        
        // Analizar primera fila para obtener columnas
        $diagnostico['columnas_encontradas'] = array_keys($datos[0]);
        
        $rubroCategoriaVistos = [];
        
        foreach ($datos as $index => $registro) {
            // Contar registros vacíos
            if (empty(trim($registro['RUBRO'] ?? '')) || empty(trim($registro['CATEGORIA_PADRE'] ?? ''))) {
                $diagnostico['registros_vacios']++;
            }
            
            // Detectar duplicados potenciales
            $clave = trim($registro['RUBRO'] ?? '') . '|' . trim($registro['CATEGORIA_PADRE'] ?? '');
            if (isset($rubroCategoriaVistos[$clave])) {
                $diagnostico['duplicados_potenciales']++;
            } else {
                $rubroCategoriaVistos[$clave] = $index;
            }
            
            // Analizar valores extremos en campos numéricos
            foreach (['STOCK_PROYECTADO', 'COMPRA_PROYECTADA', 'INDICE_VARIACION'] as $campo) {
                if (isset($registro[$campo]) && is_numeric($registro[$campo])) {
                    $valor = (float)$registro[$campo];
                    
                    if (!isset($diagnostico['valores_extremos'][$campo])) {
                        $diagnostico['valores_extremos'][$campo] = [
                            'min' => $valor,
                            'max' => $valor,
                            'suma' => $valor,
                            'count' => 1
                        ];
                    } else {
                        $diagnostico['valores_extremos'][$campo]['min'] = min($diagnostico['valores_extremos'][$campo]['min'], $valor);
                        $diagnostico['valores_extremos'][$campo]['max'] = max($diagnostico['valores_extremos'][$campo]['max'], $valor);
                        $diagnostico['valores_extremos'][$campo]['suma'] += $valor;
                        $diagnostico['valores_extremos'][$campo]['count']++;
                    }
                }
            }
        }
        
        // Calcular promedios
        foreach ($diagnostico['valores_extremos'] as $campo => &$stats) {
            $stats['promedio'] = $stats['count'] > 0 ? round($stats['suma'] / $stats['count'], 2) : 0;
            unset($stats['suma'], $stats['count']);
        }
        
        return $diagnostico;
    }
}
?>