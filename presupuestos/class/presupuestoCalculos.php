
<?php

/**
 * Clase para manejar todos los cálculos del presupuesto de compras - CORREGIDA
 * Separa la lógica de cálculo del resto del sistema
 */
class PresupuestoCalculos {
    
    /**
     * Determina la temporada actual basada en la fecha
     * Verano: 01/08 al 31/01 del año siguiente (VERANO 24-25, VERANO 25-26, etc.)
     * Invierno: 01/02 al 31/07 (INVIERNO 25, INVIERNO 26, etc.)
     */
    public static function obtenerTemporadaActual($fecha = null) {
        if (!$fecha) {  // CORREGIDO: era !fecha
            $fecha = new DateTime();
        } elseif (is_string($fecha)) {
            $fecha = new DateTime($fecha);
        }
        
        $mes = (int)$fecha->format('n');
        $ano = (int)$fecha->format('Y');
        
        if ($mes >= 8 && $mes <= 12) {
            // Agosto a Diciembre = Verano del año siguiente (ej: ago 2024 = VERANO 24-25)
            $anoInicial = $ano % 100;
            $anoFinal = ($ano + 1) % 100;
            return [
                'temporada' => 'VERANO',
                'ano' => $ano + 1,
                'codigo' => 'VERANO ' . str_pad($anoInicial, 2, '0', STR_PAD_LEFT) . '-' . str_pad($anoFinal, 2, '0', STR_PAD_LEFT)
            ];
        } elseif ($mes >= 1 && $mes <= 1) {
            // Enero = Verano del año actual (ej: ene 2025 = VERANO 24-25)
            $anoInicial = ($ano - 1) % 100;
            $anoFinal = $ano % 100;
            return [
                'temporada' => 'VERANO',
                'ano' => $ano,
                'codigo' => 'VERANO ' . str_pad($anoInicial, 2, '0', STR_PAD_LEFT) . '-' . str_pad($anoFinal, 2, '0', STR_PAD_LEFT)
            ];
        } else {
            // Febrero a Julio = Invierno del año actual (ej: mar 2025 = INVIERNO 25)
            return [
                'temporada' => 'INVIERNO',
                'ano' => $ano,
                'codigo' => 'INVIERNO ' . str_pad($ano % 100, 2, '0', STR_PAD_LEFT)
            ];
        }
    }
    
    /**
     * Calcula los días restantes para completar la temporada actual
     */
    public static function calcularDiasRestantesTemporada($fecha = null) {
        if (!$fecha) {
            $fecha = new DateTime();
        } elseif (is_string($fecha)) {
            $fecha = new DateTime($fecha);
        }
        
        $temporadaActual = self::obtenerTemporadaActual($fecha);
        $mes = (int)$fecha->format('n');
        $ano = (int)$fecha->format('Y');
        
        if ($temporadaActual['temporada'] === 'VERANO') {
            if ($mes >= 8) {
                // Estamos en verano, calcular hasta el 31 de enero del año siguiente
                $finTemporada = new DateTime(($ano + 1) . '-01-31 23:59:59');
            } else {
                // Enero, calcular hasta el 31 de enero
                $finTemporada = new DateTime($ano . '-01-31 23:59:59');
            }
        } else {
            // Invierno: calcular hasta el 31 de julio
            $finTemporada = new DateTime($ano . '-07-31 23:59:59');
        }
        
        $diferencia = $fecha->diff($finTemporada);
        return $diferencia->days + 1; // +1 para incluir el día actual
    }
    
    /**
     * CORREGIDO: Calcula la venta proyectada para INVIERNO según contexto de temporada
     */
    public static function calcularVentaProyectadaInvierno($ventaInviernoAnterior, $indiceVariacion, $fecha = null, $contextoSolapa = 'invierno') {
        // Validar datos de entrada
        $ventaInviernoAnterior = (float)$ventaInviernoAnterior;
        $indiceVariacion = (float)$indiceVariacion;
        
        if ($ventaInviernoAnterior <= 0) {
            error_log("Venta invierno anterior es 0 o negativa: $ventaInviernoAnterior");
            return 0;
        }
        
        if ($indiceVariacion <= 0) {
            $indiceVariacion = 1.0; // Valor por defecto
        }
        
        $temporadaActual = self::obtenerTemporadaActual($fecha);
        
        if ($contextoSolapa === 'invierno') {
            // SOLAPA COMPRA PROYECTADA INVIERNO
            if ($temporadaActual['temporada'] === 'INVIERNO') {
                // Contexto: Transitando invierno
                // Proporcional Invierno Actual + Próximo Invierno Completo
                $diasRestantes = self::calcularDiasRestantesTemporada($fecha);
                $diasTotales = self::calcularDiasTotalesInvierno();
                $proporcion = $diasRestantes / $diasTotales;
                
                $inviernoActualProporcional = round($ventaInviernoAnterior * $indiceVariacion * $proporcion);
                $proximoInviernoCompleto = round($ventaInviernoAnterior * $indiceVariacion);
                $ventaProyectada = $inviernoActualProporcional + $proximoInviernoCompleto;
                
                error_log("INVIERNO PHP (Solapa Inv - Transitando Inv): Proporcional ($inviernoActualProporcional) + Completo ($proximoInviernoCompleto) = $ventaProyectada");
            } else {
                // Contexto: Transitando verano - Próximo Invierno Completo
                $ventaProyectada = round($ventaInviernoAnterior * $indiceVariacion);
                error_log("INVIERNO PHP (Solapa Inv - Transitando Ver): Próximo completo = $ventaProyectada");
            }
        } else {
            // SOLAPA COMPRA PROYECTADA VERANO
            if ($temporadaActual['temporada'] === 'INVIERNO') {
                // Contexto: Transitando invierno - Proporcional Invierno Actual
                $diasRestantes = self::calcularDiasRestantesTemporada($fecha);
                $diasTotales = self::calcularDiasTotalesInvierno();
                $proporcion = $diasRestantes / $diasTotales;
                
                $ventaProyectada = round($ventaInviernoAnterior * $indiceVariacion * $proporcion);
                error_log("INVIERNO PHP (Solapa Ver - Transitando Inv): Proporcional = $ventaProyectada");
            } else {
                // Contexto: Transitando verano - Próximo Invierno Completo
                $ventaProyectada = round($ventaInviernoAnterior * $indiceVariacion);
                error_log("INVIERNO PHP (Solapa Ver - Transitando Ver): Próximo completo = $ventaProyectada");
            }
        }
        
        return $ventaProyectada;
    }
    
    /*
    * CORREGIDO: Calcula la venta proyectada para VERANO según contexto de temporada
    */
    public static function calcularVentaProyectadaVerano($ventaVeranoAnterior, $indiceVariacion, $fecha = null, $contextoSolapa = 'verano') {
        // Validar datos de entrada
        $ventaVeranoAnterior = (float)$ventaVeranoAnterior;
        $indiceVariacion = (float)$indiceVariacion;
        
        if ($ventaVeranoAnterior <= 0) {
            error_log("Venta verano anterior es 0 o negativa: $ventaVeranoAnterior");
            return 0;
        }
        
        if ($indiceVariacion <= 0) {
            $indiceVariacion = 1.0; // Valor por defecto
        }
        
        $temporadaActual = self::obtenerTemporadaActual($fecha);
        
        if ($contextoSolapa === 'verano') {
            // SOLAPA COMPRA PROYECTADA VERANO
            if ($temporadaActual['temporada'] === 'VERANO') {
                // Contexto: Transitando verano
                // Proporcional Verano Actual + Próximo Verano Completo
                $diasRestantes = self::calcularDiasRestantesTemporada($fecha);
                $diasTotales = self::calcularDiasTotalesVerano();
                $proporcion = $diasRestantes / $diasTotales;
                
                $veranoActualProporcional = round($ventaVeranoAnterior * $indiceVariacion * $proporcion);
                $proximoVeranoCompleto = round($ventaVeranoAnterior * $indiceVariacion);
                $ventaProyectada = $veranoActualProporcional + $proximoVeranoCompleto;
                
                error_log("VERANO PHP (Solapa Ver - Transitando Ver): Proporcional ($veranoActualProporcional) + Completo ($proximoVeranoCompleto) = $ventaProyectada");
            } else {
                // Contexto: Transitando invierno - Próximo Verano Completo
                $ventaProyectada = round($ventaVeranoAnterior * $indiceVariacion);
                error_log("VERANO PHP (Solapa Ver - Transitando Inv): Próximo completo = $ventaProyectada");
            }
        } else {
            // SOLAPA COMPRA PROYECTADA INVIERNO
            if ($temporadaActual['temporada'] === 'VERANO') {
                // Contexto: Transitando verano - Proporcional Verano Actual
                $diasRestantes = self::calcularDiasRestantesTemporada($fecha);
                $diasTotales = self::calcularDiasTotalesVerano();
                $proporcion = $diasRestantes / $diasTotales;
                
                $ventaProyectada = round($ventaVeranoAnterior * $indiceVariacion * $proporcion);
                error_log("VERANO PHP (Solapa Inv - Transitando Ver): Proporcional = $ventaProyectada");
            } else {
                // Contexto: Transitando invierno - Próximo Verano Completo
                $ventaProyectada = round($ventaVeranoAnterior * $indiceVariacion);
                error_log("VERANO PHP (Solapa Inv - Transitando Inv): Próximo completo = $ventaProyectada");
            }
        }
        
        return $ventaProyectada;
    }
    
    /**
     * Calcula el stock proyectado
     */
    public static function calcularStockProyectado($stock, $stockGuardar, $comprasVerano, $comprasInvierno, $comprasAtemporal, $stockCobertura) {
        return $stock + $stockGuardar + $comprasVerano + $comprasInvierno + $comprasAtemporal - $stockCobertura;
    }
    
    /**
     * Calcula la compra proyectada
     */
    public static function calcularCompraProyectada($stockProyectado, $ventaProyVerano, $ventaProyInvierno) {
        return $stockProyectado - $ventaProyVerano - $ventaProyInvierno;
    }
    
    /**
     * CORREGIDO: Obtiene las columnas de ventas ordenadas según la temporada proyectada
     */
    public static function obtenerColumnasVentas($datos, $temporadaProyectada) {
        if (empty($datos)) return [];
        
        $primeraFila = $datos[0];
        $columnasVenta = [];
        
        // Buscar todas las columnas que contienen temporadas
        foreach (array_keys($primeraFila) as $columna) {
            if (preg_match('/(VERANO|INVIERNO)\s*\d{2}(-\d{2})?/', $columna) || 
                preg_match('/VTA_.*(VERANO|INVIERNO)/', $columna)) {
                $columnasVenta[] = $columna;
            }
        }
        
        // Ordenar las columnas de manera lógica
        usort($columnasVenta, function($a, $b) {
            // Extraer año y temporada (ahora soporta formato XX-XX)
            preg_match('/(VERANO|INVIERNO)\s*(\d{2})(-\d{2})?/', $a, $matchesA);
            preg_match('/(VERANO|INVIERNO)\s*(\d{2})(-\d{2})?/', $b, $matchesB);
            
            if (empty($matchesA) || empty($matchesB)) {
                return strcmp($a, $b);
            }
            
            $anoA = (int)$matchesA[2];
            $anoB = (int)$matchesB[2];
            $temporadaA = $matchesA[1];
            $temporadaB = $matchesB[1];
            
            // Primero ordenar por año
            if ($anoA !== $anoB) {
                return $anoA <=> $anoB;
            }
            
            // Si el año es igual, verano va antes que invierno
            if ($temporadaA !== $temporadaB) {
                return $temporadaA === 'VERANO' ? -1 : 1;
            }
            
            return 0;
        });
        
        return $columnasVenta;
    }
    
    /**
     * CORREGIDO: Procesar registro individual para compra proyectada con contexto de solapa
     */
    public static function procesarRegistroCompraProyectada($registro, $stockProyectado, $temporadaProyectada, $fecha = null, $contextoSolapa = null) {
        $temporadaActual = self::obtenerTemporadaActual($fecha);
        $indiceVariacion = (float)($registro['INDICE_VARIACION'] ?? 1.0);
        $indiceVariacionInvierno = (float)($registro['INDICE_VARIACION_INVIERNO'] ?? $indiceVariacion);
        
        // Extraer ventas anteriores
        $ventaVeranoAnterior = self::extraerVentaAnterior($registro, 'VERANO');
        $ventaInviernoAnterior = self::extraerVentaAnterior($registro, 'INVIERNO');
        
        // Debug
        error_log("Debug - Procesando registro: " . ($registro['RUBRO'] ?? 'Unknown'));
        error_log("Debug - Contexto Solapa: " . ($contextoSolapa ?? 'no especificado'));
        error_log("Debug - Venta Verano Anterior: $ventaVeranoAnterior");
        error_log("Debug - Venta Invierno Anterior: $ventaInviernoAnterior");
        error_log("Debug - Índice Verano: $indiceVariacion");
        error_log("Debug - Índice Invierno: $indiceVariacionInvierno");
        error_log("Debug - Temporada Actual: " . $temporadaActual['codigo']);
        
        // Determinar contexto de solapa si no se especifica
        if (!$contextoSolapa) {
            $contextoSolapa = ($temporadaProyectada === 'VERANO') ? 'verano' : 'invierno';
        }
        
        // Calcular ventas proyectadas con contexto específico
        $ventaProyVerano = self::calcularVentaProyectadaVerano($ventaVeranoAnterior, $indiceVariacion, $fecha, $contextoSolapa);
        $ventaProyInvierno = self::calcularVentaProyectadaInvierno($ventaInviernoAnterior, $indiceVariacionInvierno, $fecha, $contextoSolapa);
        
        // Calcular compra proyectada
        $compraProyectada = self::calcularCompraProyectada($stockProyectado, $ventaProyVerano, $ventaProyInvierno);
        
        return [
            'venta_proy_verano' => $ventaProyVerano,
            'venta_proy_invierno' => $ventaProyInvierno,
            'compra_proyectada' => $compraProyectada,
            'temporada_actual' => $temporadaActual,
            'dias_restantes' => self::calcularDiasRestantesTemporada($fecha),
            'contexto_aplicado' => $contextoSolapa
        ];
    }
    
    /**
     * CORREGIDO: Extrae la venta anterior de una temporada específica
     * Ahora busca la columna más reciente con datos
     */
    private static function extraerVentaAnterior($registro, $temporada) {
        $ventaAnterior = 0;
        
        if ($temporada === 'VERANO') {
            // Buscar columnas de VERANO y ordenar por año (más reciente primero)
            $columnasVerano = [];
            foreach ($registro as $columna => $valor) {
                if (stripos($columna, 'VERANO') !== false && 
                    stripos($columna, 'PROY') === false && 
                    is_numeric($valor) && $valor > 0) {
                    
                    // Extraer año de la columna
                    preg_match('/\d{2}/', $columna, $matches);
                    $ano = isset($matches[0]) ? intval($matches[0]) : 0;
                    
                    $columnasVerano[] = [
                        'columna' => $columna,
                        'valor' => floatval($valor),
                        'ano' => $ano
                    ];
                }
            }
            
            // Ordenar por año descendente (más reciente primero)
            usort($columnasVerano, function($a, $b) {
                return $b['ano'] - $a['ano'];
            });
            
            // Tomar la primera (más reciente)
            if (!empty($columnasVerano)) {
                error_log("PHP VERANO elegido: " . $columnasVerano[0]['columna'] . " = " . $columnasVerano[0]['valor']);
                return $columnasVerano[0]['valor'];
            }
            
        } else if ($temporada === 'INVIERNO') {
            // Buscar columnas de INVIERNO y ordenar por año (más reciente primero)
            $columnasInvierno = [];
            foreach ($registro as $columna => $valor) {
                if (stripos($columna, 'INVIERNO') !== false && 
                    stripos($columna, 'PROY') === false && 
                    is_numeric($valor) && $valor > 0) {
                    
                    // Extraer año de la columna
                    preg_match('/\d{2}/', $columna, $matches);
                    $ano = isset($matches[0]) ? intval($matches[0]) : 0;
                    
                    $columnasInvierno[] = [
                        'columna' => $columna,
                        'valor' => floatval($valor),
                        'ano' => $ano
                    ];
                }
            }
            
            // Ordenar por año descendente (más reciente primero)
            usort($columnasInvierno, function($a, $b) {
                return $b['ano'] - $a['ano'];
            });
            
            // Tomar la primera (más reciente)
            if (!empty($columnasInvierno)) {
                error_log("PHP INVIERNO elegido: " . $columnasInvierno[0]['columna'] . " = " . $columnasInvierno[0]['valor']);
                return $columnasInvierno[0]['valor'];
            }
        }
        
        error_log("PHP No se encontró venta anterior para temporada: $temporada");
        return 0;
    }
    
    /**
     * CORREGIDO: Genera las etiquetas dinámicas para las columnas de venta proyectada
     */
    public static function generarEtiquetasVentaProyectada($fecha = null) {
        $temporadaActual = self::obtenerTemporadaActual($fecha);
        
        if ($temporadaActual['temporada'] === 'VERANO') {
            // Si estamos en verano, proyectamos para el próximo verano e invierno
            $anoVerano = $temporadaActual['ano'] % 100;
            $anoInvierno = $temporadaActual['ano'] % 100;
            
            $proximoVeranoInicial = $anoVerano;
            $proximoVeranoFinal = ($anoVerano + 1) > 99 ? 0 : ($anoVerano + 1);
            
            return [
                'verano' => 'PROY. VER ' . str_pad($proximoVeranoInicial, 2, '0', STR_PAD_LEFT) . '-' . str_pad($proximoVeranoFinal, 2, '0', STR_PAD_LEFT),
                'invierno' => 'PROY. INV ' . str_pad($anoInvierno, 2, '0', STR_PAD_LEFT)
            ];
        } else {
            // Si estamos en invierno, proyectamos para el próximo invierno y el verano siguiente
            $anoInvierno = $temporadaActual['ano'] % 100;
            $anoVeranoInicial = $anoInvierno;
            $anoVeranoFinal = ($anoInvierno + 1) > 99 ? 0 : ($anoInvierno + 1);
            
            return [
                'verano' => 'PROY. VER ' . str_pad($anoVeranoInicial, 2, '0', STR_PAD_LEFT) . '-' . str_pad($anoVeranoFinal, 2, '0', STR_PAD_LEFT),
                'invierno' => 'PROY. INV ' . str_pad($anoInvierno, 2, '0', STR_PAD_LEFT)
            ];
        }
    }
    
    /**
     * Valida y sanitiza el índice de variación
     */
    public static function validarIndiceVariacion($indice) {
        $indice = (float)$indice;
        
        // Límites razonables para el índice de variación
        if ($indice < 0) return 0;
        if ($indice > 10) return 10; // Máximo 1000% de variación
        
        return round($indice, 4);
    }

    /**
     * NUEVO: Calcular días totales de una temporada
     */
    private static function calcularDiasTotalesVerano() {
        // Verano: 1 agosto año anterior al 31 enero año actual
        $anoActual = date('Y');
        $inicioVerano = new DateTime(($anoActual - 1) . '-08-01');
        $finVerano = new DateTime($anoActual . '-01-31');
        $diferencia = $finVerano->diff($inicioVerano);
        return $diferencia->days + 1;
    }

    /**
     * NUEVO: Calcular días totales de invierno
     */
    private static function calcularDiasTotalesInvierno() {
        // Invierno: 1 febrero al 31 julio del mismo año
        $anoActual = date('Y');
        $inicioInvierno = new DateTime($anoActual . '-02-01');
        $finInvierno = new DateTime($anoActual . '-07-31');
        $diferencia = $finInvierno->diff($inicioInvierno);
        return $diferencia->days + 1;
    }
    
    /**
     * Obtiene información completa de la temporada actual para debugging
     */
    public static function obtenerInfoTemporada($fecha = null) {
        $temporada = self::obtenerTemporadaActual($fecha);
        $diasRestantes = self::calcularDiasRestantesTemporada($fecha);
        $etiquetas = self::generarEtiquetasVentaProyectada($fecha);
        
        return [
            'temporada_actual' => $temporada,
            'dias_restantes' => $diasRestantes,
            'etiquetas_proyeccion' => $etiquetas,
            'fecha_calculo' => $fecha ? $fecha->format('Y-m-d') : date('Y-m-d')
        ];
    }
    
    /**
     * NUEVO: Función de diagnóstico para debug
     */
    public static function diagnosticarDatos($registro) {
        $info = [
            'rubro' => $registro['RUBRO'] ?? 'N/A',
            'categoria' => $registro['CATEGORIA_PADRE'] ?? 'N/A',
            'indice_variacion' => $registro['INDICE_VARIACION'] ?? 'N/A',
            'columnas_disponibles' => array_keys($registro),
            'columnas_venta' => []
        ];
        
        foreach ($registro as $columna => $valor) {
            if (preg_match('/(VERANO|INVIERNO)/', $columna) && is_numeric($valor)) {
                $info['columnas_venta'][$columna] = $valor;
            }
        }
        
        return $info;
    }
}
?>