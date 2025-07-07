
<?php

/**
 * Clase para manejar todos los cálculos del presupuesto de compras
 * Separa la lógica de cálculo del resto del sistema
 */
class PresupuestoCalculos {
    
    /**
     * Determina la temporada actual basada en la fecha
     * Verano: 01/08 al 31/01 del año siguiente
     * Invierno: 01/02 al 31/07
     */
    public static function obtenerTemporadaActual($fecha = null) {
        if (!$fecha) {
            $fecha = new DateTime();
        } elseif (is_string($fecha)) {
            $fecha = new DateTime($fecha);
        }
        
        $mes = (int)$fecha->format('n');
        $ano = (int)$fecha->format('Y');
        
        if ($mes >= 8 && $mes <= 12) {
            // Agosto a Diciembre = Verano del año siguiente
            return [
                'temporada' => 'VERANO',
                'ano' => $ano + 1,
                'codigo' => 'VERANO ' . str_pad(($ano + 1) % 100, 2, '0', STR_PAD_LEFT)
            ];
        } elseif ($mes >= 1 && $mes <= 1) {
            // Enero = Verano del año actual
            return [
                'temporada' => 'VERANO',
                'ano' => $ano,
                'codigo' => 'VERANO ' . str_pad($ano % 100, 2, '0', STR_PAD_LEFT)
            ];
        } else {
            // Febrero a Julio = Invierno del año actual
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
     * Calcula la venta proyectada para verano
     */
    public static function calcularVentaProyectadaVerano($ventaVeranoAnterior, $indiceVariacion, $fecha = null) {
        $temporadaActual = self::obtenerTemporadaActual($fecha);
        
        if ($temporadaActual['temporada'] === 'INVIERNO') {
            return 0; // Si estamos en invierno, la venta proyectada de verano es 0
        }
        
        // Si estamos en verano
        $diasRestantes = self::calcularDiasRestantesTemporada($fecha);
        $ventaPorDia = $ventaVeranoAnterior / 180; // 180 días de temporada verano
        
        return ($ventaPorDia * $diasRestantes * $indiceVariacion);
    }
    
    /**
     * Calcula la venta proyectada para invierno
     */
    public static function calcularVentaProyectadaInvierno($ventaInviernoAnterior, $indiceVariacion, $fecha = null) {
        $temporadaActual = self::obtenerTemporadaActual($fecha);
        
        if ($temporadaActual['temporada'] === 'VERANO') {
            // Si estamos en verano, usar venta total de invierno anterior * índice
            return $ventaInviernoAnterior * $indiceVariacion;
        }
        
        // Si estamos en invierno
        $diasRestantes = self::calcularDiasRestantesTemporada($fecha);
        $ventaPorDia = $ventaInviernoAnterior / 180; // 180 días de temporada invierno
        
        return ($ventaPorDia * $diasRestantes * $indiceVariacion);
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
     * Obtiene las columnas de ventas ordenadas según la temporada proyectada
     */
    public static function obtenerColumnasVentas($datos, $temporadaProyectada) {
        if (empty($datos)) return [];
        
        $primeraFila = $datos[0];
        $columnasVenta = [];
        
        // Buscar todas las columnas que contienen temporadas
        foreach (array_keys($primeraFila) as $columna) {
            if (preg_match('/(VERANO|INVIERNO)\s+\d{2}/', $columna)) {
                $columnasVenta[] = $columna;
            }
        }
        
        // Ordenar las columnas de manera lógica
        usort($columnasVenta, function($a, $b) {
            // Extraer año y temporada
            preg_match('/(VERANO|INVIERNO)\s+(\d{2})/', $a, $matchesA);
            preg_match('/(VERANO|INVIERNO)\s+(\d{2})/', $b, $matchesB);
            
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
     * Procesa un registro individual para la solapa de compra proyectada
     */
    public static function procesarRegistroCompraProyectada($registro, $stockProyectado, $temporadaProyectada, $fecha = null) {
        $temporadaActual = self::obtenerTemporadaActual($fecha);
        $indiceVariacion = (float)($registro['INDICE_VARIACION'] ?? 1.0);
        
        // Buscar ventas de temporadas anteriores
        $ventaVeranoAnterior = 0;
        $ventaInviernoAnterior = 0;
        
        // Buscar la venta de la temporada anterior correspondiente
        foreach ($registro as $columna => $valor) {
            if (preg_match('/(VERANO|INVIERNO)\s+(\d{2})/', $columna, $matches)) {
                $temporada = $matches[1];
                $ano = (int)$matches[2];
                
                // Para proyección, usar la temporada anterior más reciente
                if ($temporada === 'VERANO') {
                    $ventaVeranoAnterior = max($ventaVeranoAnterior, (float)$valor);
                } else {
                    $ventaInviernoAnterior = max($ventaInviernoAnterior, (float)$valor);
                }
            }
        }
        
        // Calcular ventas proyectadas
        $ventaProyVerano = self::calcularVentaProyectadaVerano($ventaVeranoAnterior, $indiceVariacion, $fecha);
        $ventaProyInvierno = self::calcularVentaProyectadaInvierno($ventaInviernoAnterior, $indiceVariacion, $fecha);
        
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
     * Genera las etiquetas dinámicas para las columnas de venta proyectada
     */
    public static function generarEtiquetasVentaProyectada($fecha = null) {
        $temporadaActual = self::obtenerTemporadaActual($fecha);
        $anoActual = $temporadaActual['ano'];
        
        return [
            'verano' => 'VENTA PROY. VER ' . str_pad($anoActual % 100, 2, '0', STR_PAD_LEFT),
            'invierno' => 'VENTA PROY. INV ' . str_pad($anoActual % 100, 2, '0', STR_PAD_LEFT)
        ];
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
}
?>