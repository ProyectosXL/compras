
<?php

/**
 * Clase dedicada al procesamiento de datos para las diferentes solapas
 * Sistema de Presupuesto de Compras v2.1 - CORREGIDA PARA VENTA PROYECTADA
 */
class ProcesadorDatos {
    
    /**
     * CORREGIDO: Procesar datos para la solapa de compra verano CON CONTEXTO
     */
    public function procesarDatosCompraVerano($datos) {
        $resultado = [];
        
        if (empty($datos)) {
            return $resultado;
        }
        
        // Asegurar que no hay duplicados en los datos fuente
        $datosUnicos = $this->eliminarDuplicados($datos);
        
        foreach ($datosUnicos as $index => $registro) {
            try {
                $stockProyectado = $this->calcularStockProyectadoRegistro($registro);
                
                // CORRECCIÓN: Especificar contexto de solapa 'verano'
                $calculosCompra = PresupuestoCalculos::procesarRegistroCompraProyectada(
                    $registro, 
                    $stockProyectado, 
                    'VERANO',
                    null, // fecha actual
                    'verano' // CONTEXTO ESPECÍFICO
                );
                
                $registroProcesado = [
                    'RUBRO' => $this->limpiarTexto($registro['RUBRO'] ?? ''),
                    'CATEGORIA_PADRE' => $this->limpiarTexto($registro['CATEGORIA_PADRE'] ?? ''),
                    'STOCK_PROYECTADO' => round($stockProyectado, 2),
                    'INDICE_VAR_ORIGINAL' => round((float)($registro['INDICE_VAR_ORIGINAL'] ?? $registro['INDICE_ORIGINAL'] ?? $registro['INDICE_VARIACION'] ?? 1.0), 2),
                    'INDICE_ORIGINAL' => round((float)($registro['INDICE_ORIGINAL'] ?? $registro['INDICE_VAR_ORIGINAL'] ?? $registro['INDICE_VARIACION'] ?? 1.0), 2),
                    'INDICE_VARIACION' => round((float)($registro['INDICE_VARIACION'] ?? 1.0), 2),
                    'INDICE_VARIACION_INVIERNO' => round((float)($registro['INDICE_VARIACION_INVIERNO'] ?? $registro['INDICE_VARIACION'] ?? 1.0), 2),
                    // Componentes de STOCK_PROYECTADO. Viajan para poder guardarlos en la
                    // versión: sin ellos, meses después no se sabe cuánto había pedido y
                    // sin ingresar, que es lo que vuelve NETA a la compra proyectada.
                    'CANT_STOCK' => (int)($registro['CANT_STOCK'] ?? 0),
                    'CANT_STOCK_GUARDAR' => (int)($registro['CANT_STOCK_GUARDAR'] ?? 0),
                    'CANT_PEND_OC_VERANO' => (int)($registro['CANT_PEND_OC_VERANO'] ?? 0),
                    'CANT_PEND_OC_INVIERNO' => (int)($registro['CANT_PEND_OC_INVIERNO'] ?? 0),
                    'CANT_PEND_OC_ATEMPORAL' => (int)($registro['CANT_PEND_OC_ATEMPORAL'] ?? 0),
                    'STOCK_COBERTURA' => (int)($registro['STOCK_COBERTURA'] ?? 0),
                    // Bases efectivas del cálculo: la pantalla muestra estas, no las que
                    // el front buscaba por su cuenta (ver presupuestoCalculos).
                    'VENTA_VERANO_ANTERIOR' => round($calculosCompra['venta_verano_anterior'], 0),
                    'TEMPORADA_BASE_VERANO' => $calculosCompra['temporada_base_verano'],
                    'TEMPORADA_BASE_INVIERNO' => $calculosCompra['temporada_base_invierno'],
                    'VENTA_INVIERNO_ANTERIOR' => round($calculosCompra['venta_invierno_anterior'], 0),
                    'VENTA_PROY_VERANO' => round($calculosCompra['venta_proy_verano'], 0),
                    'VENTA_PROY_INVIERNO' => round($calculosCompra['venta_proy_invierno'], 0),
                    'COMPRA_PROYECTADA' => round($calculosCompra['compra_proyectada'], 0),
                    // Compra abierta por tramo. Viaja con la fila para que la pantalla,
                    // el Excel y el guardado usen el mismo reparto que calculó el servidor,
                    // en vez de que cada uno lo rehaga por su cuenta.
                    'TRAMOS' => $calculosCompra['tramos']
                ];

                // Agregar columnas de ventas históricas de forma controlada
                $columnasVenta = $this->obtenerColumnasVentasSeguras($registro);
                foreach ($columnasVenta as $columna) {
                    $registroProcesado[$columna] = round((float)($registro[$columna] ?? 0), 0);
                }

                $resultado[] = $registroProcesado;

            } catch (Exception $e) {
                error_log("Error procesando registro de verano (índice $index): " . $e->getMessage());
                continue;
            }
        }
        
        error_log("✅ VERANO procesado con contexto específico: " . count($resultado) . " registros");
        return $resultado;
    }
    
    /**
     * CORREGIDO: Procesar datos para la solapa de compra invierno CON CONTEXTO
     */
    public function procesarDatosCompraInvierno($datos) {
        $resultado = [];
        
        if (empty($datos)) {
            return $resultado;
        }
        
        // Asegurar que no hay duplicados en los datos fuente
        $datosUnicos = $this->eliminarDuplicados($datos);
        
        foreach ($datosUnicos as $index => $registro) {
            try {
                $stockProyectado = $this->calcularStockProyectadoRegistro($registro);
                
                // CORRECCIÓN: Especificar contexto de solapa 'invierno'
                $calculosCompra = PresupuestoCalculos::procesarRegistroCompraProyectada(
                    $registro, 
                    $stockProyectado, 
                    'INVIERNO',
                    null, // fecha actual
                    'invierno' // CONTEXTO ESPECÍFICO
                );
                
                $registroProcesado = [
                    'RUBRO' => $this->limpiarTexto($registro['RUBRO'] ?? ''),
                    'CATEGORIA_PADRE' => $this->limpiarTexto($registro['CATEGORIA_PADRE'] ?? ''),
                    'STOCK_PROYECTADO' => round($stockProyectado, 2),
                    'INDICE_VAR_ORIGINAL' => round((float)($registro['INDICE_VAR_ORIGINAL'] ?? $registro['INDICE_ORIGINAL'] ?? $registro['INDICE_VARIACION'] ?? 1.0), 2),
                    'INDICE_ORIGINAL' => round((float)($registro['INDICE_ORIGINAL'] ?? $registro['INDICE_VAR_ORIGINAL'] ?? $registro['INDICE_VARIACION'] ?? 1.0), 2),
                    'INDICE_VARIACION' => round((float)($registro['INDICE_VARIACION'] ?? 1.0), 2),
                    'INDICE_VARIACION_INVIERNO' => round((float)($registro['INDICE_VARIACION_INVIERNO'] ?? $registro['INDICE_VARIACION'] ?? 1.0), 2),
                    // Ver el comentario equivalente en procesarDatosCompraVerano().
                    'CANT_STOCK' => (int)($registro['CANT_STOCK'] ?? 0),
                    'CANT_STOCK_GUARDAR' => (int)($registro['CANT_STOCK_GUARDAR'] ?? 0),
                    'CANT_PEND_OC_VERANO' => (int)($registro['CANT_PEND_OC_VERANO'] ?? 0),
                    'CANT_PEND_OC_INVIERNO' => (int)($registro['CANT_PEND_OC_INVIERNO'] ?? 0),
                    'CANT_PEND_OC_ATEMPORAL' => (int)($registro['CANT_PEND_OC_ATEMPORAL'] ?? 0),
                    'STOCK_COBERTURA' => (int)($registro['STOCK_COBERTURA'] ?? 0),
                    'VENTA_VERANO_ANTERIOR' => round($calculosCompra['venta_verano_anterior'], 0),
                    'TEMPORADA_BASE_VERANO' => $calculosCompra['temporada_base_verano'],
                    'TEMPORADA_BASE_INVIERNO' => $calculosCompra['temporada_base_invierno'],
                    'VENTA_INVIERNO_ANTERIOR' => round($calculosCompra['venta_invierno_anterior'], 0),
                    'VENTA_PROY_VERANO' => round($calculosCompra['venta_proy_verano'], 0),
                    'VENTA_PROY_INVIERNO' => round($calculosCompra['venta_proy_invierno'], 0),
                    'COMPRA_PROYECTADA' => round($calculosCompra['compra_proyectada'], 0),
                    // Compra abierta por tramo. Viaja con la fila para que la pantalla,
                    // el Excel y el guardado usen el mismo reparto que calculó el servidor,
                    // en vez de que cada uno lo rehaga por su cuenta.
                    'TRAMOS' => $calculosCompra['tramos']
                ];

                // Agregar columnas de ventas históricas de forma controlada
                $columnasVenta = $this->obtenerColumnasVentasSeguras($registro);
                foreach ($columnasVenta as $columna) {
                    $registroProcesado[$columna] = round((float)($registro[$columna] ?? 0), 0);
                }

                $resultado[] = $registroProcesado;

            } catch (Exception $e) {
                error_log("Error procesando registro de invierno (índice $index): " . $e->getMessage());
                continue;
            }
        }
        
        error_log("✅ INVIERNO procesado con contexto específico: " . count($resultado) . " registros");
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
     * CORREGIDO: Obtener columnas de ventas de forma segura (sin duplicados)
     */
    private function obtenerColumnasVentasSeguras($registro) {
        $columnasVenta = [];
        $columnasExcluidas = [
            'RUBRO', 'CATEGORIA_PADRE', 'CANT_STOCK', 'CANT_STOCK_GUARDAR',
            'CANT_PEND_OC_VERANO', 'CANT_PEND_OC_INVIERNO', 'CANT_PEND_OC_ATEMPORAL',
            'INDICE_VARIACION', 'STOCK_COBERTURA', 'VENTA_PROY_VERANO',
            'VENTA_PROY_INVIERNO', 'COMPRA_PROYECTADA', 'STOCK_PROYECTADO',
            // Bases del cálculo: llevan VERANO/INVIERNO en el nombre pero no son
            // columnas históricas, son el valor elegido para proyectar.
            'VENTA_VERANO_ANTERIOR', 'VENTA_INVIERNO_ANTERIOR'
        ];

        foreach (array_keys($registro) as $columna) {
            if (in_array($columna, $columnasExcluidas)) {
                continue;
            }

            // Solo entran las que corresponden a una temporada identificable: así se
            // descartan de una las que apenas contienen la palabra VERANO/INVIERNO.
            if (PresupuestoCalculos::temporadaDeColumna($columna)) {
                $columnasVenta[$columna] = PresupuestoCalculos::temporadaDeColumna($columna)['desde'];
            }
        }

        // Orden cronológico, no alfabético: un sort() de texto agrupaba todos los
        // inviernos y después todos los veranos, en vez de intercalarlos por fecha.
        asort($columnasVenta);

        return array_keys($columnasVenta);
    }
    
    /**
     * NUEVO: Limpiar texto para evitar problemas de encoding/espacios
     */
    private function limpiarTexto($texto) {
        return trim($texto);
    }
    
    /**
     * CORREGIDO: Procesar registro individual para compra verano CON CONTEXTO
     */
    public function procesarRegistroCompraVerano($registro) {
        $stockProyectado = $this->calcularStockProyectadoRegistro($registro);
        
        // CORRECCIÓN: Especificar contexto 'verano'
        $calculosCompra = PresupuestoCalculos::procesarRegistroCompraProyectada(
            $registro, 
            $stockProyectado, 
            'VERANO',
            null,
            'verano' // CONTEXTO ESPECÍFICO
        );
        
        return array_merge($registro, [
            'STOCK_PROYECTADO' => round($stockProyectado, 2),
            'VENTA_VERANO_ANTERIOR' => round($calculosCompra['venta_verano_anterior'], 0),
            'VENTA_INVIERNO_ANTERIOR' => round($calculosCompra['venta_invierno_anterior'], 0),
            'VENTA_PROY_VERANO' => round($calculosCompra['venta_proy_verano'], 0),
            'VENTA_PROY_INVIERNO' => round($calculosCompra['venta_proy_invierno'], 0),
            'COMPRA_PROYECTADA' => round($calculosCompra['compra_proyectada'], 0),
            // Ver el comentario equivalente en procesarDatosCompraVerano().
            'TRAMOS' => $calculosCompra['tramos']
        ]);
    }

    /**
     * CORREGIDO: Procesar registro individual para compra invierno CON CONTEXTO
     */
    public function procesarRegistroCompraInvierno($registro) {
        $stockProyectado = $this->calcularStockProyectadoRegistro($registro);
        
        // CORRECCIÓN: Especificar contexto 'invierno'
        $calculosCompra = PresupuestoCalculos::procesarRegistroCompraProyectada(
            $registro, 
            $stockProyectado, 
            'INVIERNO',
            null,
            'invierno' // CONTEXTO ESPECÍFICO
        );
        
        return array_merge($registro, [
            'STOCK_PROYECTADO' => round($stockProyectado, 2),
            'VENTA_VERANO_ANTERIOR' => round($calculosCompra['venta_verano_anterior'], 0),
            'VENTA_INVIERNO_ANTERIOR' => round($calculosCompra['venta_invierno_anterior'], 0),
            'VENTA_PROY_VERANO' => round($calculosCompra['venta_proy_verano'], 0),
            'VENTA_PROY_INVIERNO' => round($calculosCompra['venta_proy_invierno'], 0),
            'COMPRA_PROYECTADA' => round($calculosCompra['compra_proyectada'], 0),
            // Ver el comentario equivalente en procesarDatosCompraVerano().
            'TRAMOS' => $calculosCompra['tramos']
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
     * NUEVO: Función para diagnosticar problemas en los datos
     */
    public function diagnosticarProblemas($datos) {
        $diagnostico = [
            'total_registros' => count($datos),
            'registros_sin_ventas' => 0,
            'registros_con_ventas' => 0,
            'columnas_venta_encontradas' => [],
            'ejemplos_problematicos' => []
        ];
        
        if (empty($datos)) {
            return $diagnostico;
        }
        
        foreach ($datos as $index => $registro) {
            $tieneVentas = false;
            $ventasEncontradas = [];
            
            foreach ($registro as $columna => $valor) {
                if ((stripos($columna, 'VERANO') !== false || stripos($columna, 'INVIERNO') !== false) 
                    && is_numeric($valor) && $valor > 0) {
                    $tieneVentas = true;
                    $ventasEncontradas[$columna] = $valor;
                    
                    if (!in_array($columna, $diagnostico['columnas_venta_encontradas'])) {
                        $diagnostico['columnas_venta_encontradas'][] = $columna;
                    }
                }
            }
            
            if ($tieneVentas) {
                $diagnostico['registros_con_ventas']++;
            } else {
                $diagnostico['registros_sin_ventas']++;
                
                // Guardar algunos ejemplos problemáticos
                if (count($diagnostico['ejemplos_problematicos']) < 5) {
                    $diagnostico['ejemplos_problematicos'][] = [
                        'indice' => $index,
                        'rubro' => $registro['RUBRO'] ?? 'N/A',
                        'categoria' => $registro['CATEGORIA_PADRE'] ?? 'N/A',
                        'columnas_disponibles' => array_keys($registro)
                    ];
                }
            }
        }
        
        return $diagnostico;
    }
    
    /**
     * NUEVO: Obtener muestra de datos para debug
     */
    public function obtenerMuestraDatos($datos, $cantidad = 3) {
        $muestra = [];
        
        for ($i = 0; $i < min($cantidad, count($datos)); $i++) {
            $registro = $datos[$i];
            $muestraRegistro = [
                'rubro' => $registro['RUBRO'] ?? 'N/A',
                'categoria' => $registro['CATEGORIA_PADRE'] ?? 'N/A',
                'indice_variacion' => $registro['INDICE_VARIACION'] ?? 'N/A',
                'ventas_historicas' => []
            ];
            
            foreach ($registro as $columna => $valor) {
                if (stripos($columna, 'VERANO') !== false || stripos($columna, 'INVIERNO') !== false) {
                    $muestraRegistro['ventas_historicas'][$columna] = $valor;
                }
            }
            
            $muestra[] = $muestraRegistro;
        }
        
        return $muestra;
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
        
        // NUEVO: Contar registros con ventas proyectadas en cero
        $ventasVeranoCero = count(array_filter($datos, function($item) {
            return ($item['VENTA_PROY_VERANO'] ?? 0) == 0;
        }));
        
        $ventasInviernoCero = count(array_filter($datos, function($item) {
            return ($item['VENTA_PROY_INVIERNO'] ?? 0) == 0;
        }));
        
        return [
            'total_compra_proyectada' => round($totalCompraProyectada, 2),
            'total_venta_verano' => round($totalVentaVerano, 2),
            'total_venta_invierno' => round($totalVentaInvierno, 2),
            'total_stock_proyectado' => round($totalStock, 2),
            'compras_positivas' => $comprasPositivas,
            'compras_negativas' => $comprasNegativas,
            'porcentaje_compras_positivas' => count($datos) > 0 ? round(($comprasPositivas / count($datos)) * 100, 2) : 0,
            'ventas_verano_cero' => $ventasVeranoCero,
            'ventas_invierno_cero' => $ventasInviernoCero,
            'porcentaje_ventas_problematicas' => count($datos) > 0 ? round((($ventasVeranoCero + $ventasInviernoCero) / (count($datos) * 2)) * 100, 2) : 0
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
        
        // NUEVO: Validar que las ventas proyectadas no sean cero
        if (isset($registro['VENTA_PROY_VERANO']) && $registro['VENTA_PROY_VERANO'] == 0) {
            $advertencias[] = "Registro $index: VENTA_PROY_VERANO es cero - verificar datos históricos";
        }
        
        if (isset($registro['VENTA_PROY_INVIERNO']) && $registro['VENTA_PROY_INVIERNO'] == 0) {
            $advertencias[] = "Registro $index: VENTA_PROY_INVIERNO es cero - verificar datos históricos";
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
}
?>