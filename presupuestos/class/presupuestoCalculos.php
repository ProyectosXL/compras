
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
     * CORREGIDO: Calcula la venta proyectada para INVIERNO
     * Si estamos en temporada de invierno, se aplica proporción de días restantes
     */
    public static function calcularVentaProyectadaInvierno($ventaInviernoAnterior, $indiceVariacion, $fecha = null) {
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
        
        // CORRECCIÓN: Aplicar índice siempre
        $ventaProyectada = $ventaInviernoAnterior * $indiceVariacion;
        
        // Si estamos en temporada de INVIERNO, ajustar por días restantes
        if ($temporadaActual['temporada'] === 'INVIERNO') {
            $diasRestantes = self::calcularDiasRestantesTemporada($fecha);
            $diasTotalTemporada = 180; // 6 meses aproximadamente (febrero a julio)
            
            // FÓRMULA CORREGIDA: (venta total invierno anterior / 180) * días restantes * índice
            $ventaProyectada = ($ventaInviernoAnterior / $diasTotalTemporada) * $diasRestantes * $indiceVariacion;
        }
        
        return round($ventaProyectada, 2);
    }
    
    /**
     * CORREGIDO: Calcula la venta proyectada para VERANO
     * Si estamos en temporada de verano, se aplica proporción de días restantes
     */
    public static function calcularVentaProyectadaVerano($ventaVeranoAnterior, $indiceVariacion, $fecha = null) {
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
        
        // CORRECCIÓN: Aplicar índice siempre
        $ventaProyectada = $ventaVeranoAnterior * $indiceVariacion;
        
        // Si estamos en temporada de VERANO, ajustar por días restantes
        if ($temporadaActual['temporada'] === 'VERANO') {
            $diasRestantes = self::calcularDiasRestantesTemporada($fecha);
            $diasTotalTemporada = 180; // 6 meses aproximadamente (agosto a enero)
            
            // FÓRMULA CORREGIDA: (venta total verano anterior / 180) * días restantes * índice
            $ventaProyectada = ($ventaVeranoAnterior / $diasTotalTemporada) * $diasRestantes * $indiceVariacion;
        }
        
        return round($ventaProyectada, 2);
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
     * CORREGIDO: Procesa un registro individual para la solapa de compra proyectada
     */
    public static function procesarRegistroCompraProyectada($registro, $stockProyectado, $temporadaProyectada, $fecha = null) {
        $temporadaActual = self::obtenerTemporadaActual($fecha);
        $indiceVariacion = (float)($registro['INDICE_VARIACION'] ?? 1.0);
        
        // CORRECCIÓN: Buscar ventas de temporadas anteriores con mejor lógica
        $ventaVeranoAnterior = self::extraerVentaAnterior($registro, 'VERANO');
        $ventaInviernoAnterior = self::extraerVentaAnterior($registro, 'INVIERNO');
        
        // Debug para diagnóstico
        error_log("Debug - Procesando registro: " . ($registro['RUBRO'] ?? 'Unknown'));
        error_log("Debug - Venta Verano Anterior: $ventaVeranoAnterior");
        error_log("Debug - Venta Invierno Anterior: $ventaInviernoAnterior");
        error_log("Debug - Índice Variación: $indiceVariacion");
        error_log("Debug - Temporada Actual: " . $temporadaActual['codigo']);
        
        // Calcular ventas proyectadas
        $ventaProyVerano = self::calcularVentaProyectadaVerano($ventaVeranoAnterior, $indiceVariacion, $fecha);
        $ventaProyInvierno = self::calcularVentaProyectadaInvierno($ventaInviernoAnterior, $indiceVariacion, $fecha);
        
        // Debug de resultados
        error_log("Debug - Venta Proy Verano: $ventaProyVerano");
        error_log("Debug - Venta Proy Invierno: $ventaProyInvierno");
        
        // Calcular compra proyectada
        $compraProyectada = self::calcularCompraProyectada($stockProyectado, $ventaProyVerano, $ventaProyInvierno);
        
        return [
            'venta_proy_verano' => $ventaProyVerano,
            'venta_proy_invierno' => $ventaProyInvierno,
            'compra_proyectada' => $compraProyectada,
            'temporada_actual' => $temporadaActual,
            'dias_restantes' => self::calcularDiasRestantesTemporada($fecha)
        ];
    }
    
    /**
     * CORREGIDO: Extrae la venta anterior de una temporada específica
     * Ahora busca la temporada anterior correcta (ej: si estamos en VERANO 25-26, busca VERANO 24-25)
     */
    private static function extraerVentaAnterior($registro, $temporada) {
        $ventaAnterior = 0;
        $anoActual = (int)date('y'); // Año actual en formato de 2 dígitos
        
        // CORRECCIÓN: Para verano, buscar el formato XX-XX anterior
        if ($temporada === 'VERANO') {
            // Si estamos en VERANO 25-26, buscar VERANO 24-25
            for ($i = 1; $i <= 3; $i++) {
                $anoInicialAnterior = ($anoActual - $i) < 0 ? ($anoActual - $i + 100) : ($anoActual - $i);
                $anoFinalAnterior = ($anoActual - $i + 1) < 0 ? ($anoActual - $i + 1 + 100) : ($anoActual - $i + 1);
                
                $anoInicialStr = str_pad($anoInicialAnterior, 2, '0', STR_PAD_LEFT);
                $anoFinalStr = str_pad($anoFinalAnterior, 2, '0', STR_PAD_LEFT);
                
                // Probar diferentes formatos incluyendo el formato XX-XX
                $posiblesColumnas = [
                    'VERANO ' . $anoInicialStr . '-' . $anoFinalStr,
                    'VERANO ' . $anoInicialStr,
                    'VERANO_' . $anoInicialStr,
                    'VERANO' . $anoInicialStr,
                    'VTA_VERANO_' . $anoInicialStr,
                    'VTA_VERANO' . $anoInicialStr
                ];
                
                foreach ($posiblesColumnas as $columna) {
                    if (isset($registro[$columna]) && is_numeric($registro[$columna]) && $registro[$columna] > 0) {
                        error_log("Debug - Encontrada columna VERANO: $columna con valor: " . $registro[$columna]);
                        return (float)$registro[$columna];
                    }
                }
            }
        } else {
            // Para invierno, mantener la lógica anterior (solo año)
            for ($i = 1; $i <= 3; $i++) {
                $anoObjetivo = $anoActual - $i;
                if ($anoObjetivo < 0) $anoObjetivo += 100;
                
                $anoStr = str_pad($anoObjetivo, 2, '0', STR_PAD_LEFT);
                
                $posiblesColumnas = [
                    'INVIERNO ' . $anoStr,
                    'INVIERNO_' . $anoStr,
                    'INVIERNO' . $anoStr,
                    'VTA_INVIERNO_' . $anoStr,
                    'VTA_INVIERNO' . $anoStr
                ];
                
                foreach ($posiblesColumnas as $columna) {
                    if (isset($registro[$columna]) && is_numeric($registro[$columna]) && $registro[$columna] > 0) {
                        error_log("Debug - Encontrada columna INVIERNO: $columna con valor: " . $registro[$columna]);
                        return (float)$registro[$columna];
                    }
                }
            }
        }
        
        // Si no encontramos nada específico, buscar cualquier columna que contenga la temporada
        foreach ($registro as $columna => $valor) {
            if (stripos($columna, $temporada) !== false && is_numeric($valor) && $valor > 0) {
                error_log("Debug - Columna genérica encontrada: $columna con valor: $valor");
                return (float)$valor;
            }
        }
        
        error_log("Debug - No se encontró venta anterior para temporada: $temporada");
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