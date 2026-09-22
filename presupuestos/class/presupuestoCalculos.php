
<?php

/**
 * Clase para manejar todos los cálculos del presupuesto de compras
 *
 * Es la ÚNICA fuente de verdad del calendario de temporadas: qué temporada está
 * en curso, desde y hasta cuándo va, cuántos días tiene y cómo se llama. El
 * navegador no vuelve a calcular nada de esto: lo recibe en `info_temporada` y
 * lo usa tal cual. Antes cada calculadora JS repetía las mismas cuentas con su
 * propio reloj, y el resultado dependía de la hora del cliente (ver
 * calcularDiasRestantesTemporada).
 *
 * CONVENCIÓN DE NOMBRES (única en toda la app):
 *   - Verano  "VER AA-AA": VER 26-27 = 01/08/2026 a 31/01/2027 (cruza el año)
 *   - Invierno "INV AA"   : INV 27   = 01/02/2027 a 31/07/2027
 * Coincide con los códigos de oleada de Comercio Exterior (VER01-26, INV01-27),
 * que numeran el verano por el año en que arranca. Se descartó mantener el
 * "VERANO 27" de las columnas del SP —que numera por el año en que termina—
 * porque para el mismo período convivían dos números distintos según dónde se
 * leyera. Los nombres de columna del SP NO se tocan: el renombre es de
 * presentación y la traducción vive en etiquetaColumnaHistorica().
 */
class PresupuestoCalculos {

    /**
     * Construye una temporada concreta a partir de su tipo y del año en que TERMINA.
     *
     * El año de referencia sigue siendo el año de cierre porque es el que ya usaban
     * obtenerTemporadaActual() y el encabezado; cambiarlo habría obligado a tocar
     * todos los consumidores sin ganar nada. La convención nueva se expresa en
     * 'codigo', que es lo único que se muestra.
     *
     * @param string $tipo   'VERANO' o 'INVIERNO'
     * @param int    $anoFin Año calendario en que termina la temporada
     */
    public static function construirTemporada($tipo, $anoFin) {
        if ($tipo === 'VERANO') {
            $desde = new DateTime(($anoFin - 1) . '-08-01');
            $hasta = new DateTime($anoFin . '-01-31');
            $codigo = 'VER ' . str_pad(($anoFin - 1) % 100, 2, '0', STR_PAD_LEFT)
                    . '-' . str_pad($anoFin % 100, 2, '0', STR_PAD_LEFT);
        } else {
            $desde = new DateTime($anoFin . '-02-01');
            $hasta = new DateTime($anoFin . '-07-31');
            $codigo = 'INV ' . str_pad($anoFin % 100, 2, '0', STR_PAD_LEFT);
        }

        return [
            'temporada' => $tipo,
            'ano'       => $anoFin,
            'codigo'    => $codigo,
            'desde'     => $desde->format('Y-m-d'),
            'hasta'     => $hasta->format('Y-m-d'),
            // +1 porque desde y hasta son ambos inclusive (01/08 y 31/01 son días de venta).
            'dias'      => (int)$desde->diff($hasta)->days + 1
        ];
    }

    /**
     * Devuelve la temporada SIGUIENTE a la recibida (verano -> invierno -> verano).
     * La usan las proyecciones para nombrar "la próxima temporada completa".
     */
    public static function temporadaSiguiente($temporada) {
        // El invierno AA termina en julio AA y el verano que sigue cierra en enero AA+1;
        // el verano que cierra en enero AA es seguido por el invierno del mismo año AA.
        return $temporada['temporada'] === 'VERANO'
            ? self::construirTemporada('INVIERNO', $temporada['ano'])
            : self::construirTemporada('VERANO', $temporada['ano'] + 1);
    }

    /**
     * Determina la temporada actual basada en la fecha.
     * Verano: 01/08 al 31/01 del año siguiente. Invierno: 01/02 al 31/07.
     */
    public static function obtenerTemporadaActual($fecha = null) {
        $fecha = self::normalizarFecha($fecha);

        $mes = (int)$fecha->format('n');
        $ano = (int)$fecha->format('Y');

        if ($mes >= 8) {
            // Agosto a diciembre: el verano en curso cierra en enero del año siguiente.
            return self::construirTemporada('VERANO', $ano + 1);
        } elseif ($mes === 1) {
            // Enero: seguimos dentro del verano que cierra este mismo año.
            return self::construirTemporada('VERANO', $ano);
        }

        // Febrero a julio: invierno del año en curso.
        return self::construirTemporada('INVIERNO', $ano);
    }

    /**
     * Normaliza cualquier entrada de fecha a un DateTime a medianoche.
     *
     * Truncar la hora es lo que vuelve determinista todo el cálculo de días: antes
     * el resto de temporada se medía en milisegundos contra el 31/01 23:59:59, así
     * que el mismo día daba 132 días a la mañana y 131 a la tarde. PHP lo disimulaba
     * con diff()->days (que trunca), pero el JS usaba Math.round() y sí cambiaba de
     * valor al mediodía. Se descartó "arreglar solo el JS" porque el bug estaba en
     * medir un intervalo de días con precisión de milisegundos, no en el redondeo.
     */
    private static function normalizarFecha($fecha = null) {
        if (!$fecha) {
            $fecha = new DateTime();
        } elseif (is_string($fecha)) {
            $fecha = new DateTime($fecha);
        } else {
            // Clonar: los consumidores pasan su propio DateTime y no esperan que se les modifique.
            $fecha = clone $fecha;
        }

        $fecha->setTime(0, 0, 0);
        return $fecha;
    }

    /**
     * Calcula los días restantes de la temporada en curso, incluido el día de hoy.
     *
     * Devuelve el mismo número a cualquier hora del día (ver normalizarFecha).
     */
    public static function calcularDiasRestantesTemporada($fecha = null) {
        $fecha = self::normalizarFecha($fecha);
        $temporadaActual = self::obtenerTemporadaActual($fecha);

        $fin = new DateTime($temporadaActual['hasta']);
        if ($fecha > $fin) {
            return 0;
        }

        // +1 para incluir el día actual: si hoy es el último día, queda 1 día de venta.
        return (int)$fecha->diff($fin)->days + 1;
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
                // Los días totales salen de la temporada en curso, que es la que se prorratea.
                // Antes se calculaban sobre el año calendario actual, que para el verano apuntaba
                // a la temporada anterior; daba el mismo número por casualidad (agosto-enero son
                // siempre 184 días) pero describía otro período.
                $diasTotales = $temporadaActual['dias'];
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
                $diasTotales = $temporadaActual['dias'];
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
                $diasTotales = $temporadaActual['dias'];
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
                $diasTotales = $temporadaActual['dias'];
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

        // Buscar todas las columnas de venta histórica y quedarse con su fecha de inicio.
        //
        // Se ordena por la fecha real de comienzo de la temporada y no por el número que
        // aparece en el nombre. Comparar números no funciona porque cada formato numera
        // distinto: "VERANO 26" usa el año en que termina y "VER 25-26" el año en que
        // empieza, así que verano e invierno dejaban de estar en la misma escala y
        // "VERANO 24-25" se colaba antes que "INVIERNO 24". Además el regex anterior
        // esperaba un espacio ("VERANO 26") y las columnas reales traen guión bajo
        // ("VTA_VERANO_26"), por lo que nunca matcheaba y el orden caía a un strcmp que
        // agrupaba por temporada en vez de intercalar cronológicamente.
        foreach (array_keys($primeraFila) as $columna) {
            $temporada = self::temporadaDeColumna($columna);
            if ($temporada) {
                $columnasVenta[] = ['columna' => $columna, 'desde' => $temporada['desde']];
            }
        }

        usort($columnasVenta, function($a, $b) {
            return strcmp($a['desde'], $b['desde']);
        });

        return array_column($columnasVenta, 'columna');
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
            // Las bases se devuelven junto con el resultado para que la pantalla muestre
            // exactamente el número desde el que se proyectó. El JS tenía su propia
            // búsqueda de la columna base, con una lista fija de solo dos años, y cuando
            // la última venta era más vieja mostraba 0 mientras el cálculo usaba otro valor.
            'venta_verano_anterior' => $ventaVeranoAnterior,
            'venta_invierno_anterior' => $ventaInviernoAnterior,
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
     * Devuelve la próxima temporada del tipo pedido, a partir de la temporada en curso.
     */
    public static function proximaTemporadaDeTipo($temporadaActual, $tipo) {
        // Si pedimos el mismo tipo que está en curso, la próxima es la del año siguiente:
        // la actual ya está empezada y su resto se proyecta aparte.
        if ($temporadaActual['temporada'] === $tipo) {
            return self::construirTemporada($tipo, $temporadaActual['ano'] + 1);
        }

        return self::temporadaSiguiente($temporadaActual);
    }

    /**
     * Describe qué período cubre realmente cada columna de venta proyectada.
     *
     * Es la traducción literal de lo que calculan calcularVentaProyectadaVerano() y
     * calcularVentaProyectadaInvierno(): mismo if/else, mismos contextos. Existe
     * porque las etiquetas viejas nombraban una sola temporada cuando la columna
     * podía contener dos (el resto de la que está en curso MÁS la próxima completa),
     * y en la solapa invierno llegaban a nombrar una temporada que no era la que
     * estaba sumada. Se descartó poner solo la temporada "principal" y aclarar el
     * resto en el tooltip: el número de la celda es la suma de los dos tramos, así
     * que la etiqueta tiene que mostrarlos a los dos.
     *
     * @param string $contextoSolapa 'verano' o 'invierno'
     * @return array columnas 'verano' e 'invierno' + 'objetivo' (temporada que la compra debe cubrir)
     */
    public static function obtenerPeriodosProyeccion($fecha = null, $contextoSolapa = 'verano') {
        $fecha = self::normalizarFecha($fecha);
        $actual = self::obtenerTemporadaActual($fecha);

        $proximoVerano   = self::proximaTemporadaDeTipo($actual, 'VERANO');
        $proximoInvierno = self::proximaTemporadaDeTipo($actual, 'INVIERNO');

        // El resto de la temporada en curso arranca hoy, no el día en que empezó la temporada.
        $resto = array_merge($actual, ['resto' => true, 'desde' => $fecha->format('Y-m-d')]);

        $transitandoVerano = ($actual['temporada'] === 'VERANO');
        $solapaVerano      = ($contextoSolapa === 'verano');

        if ($transitandoVerano) {
            // La columna de verano lleva el resto del verano en curso; se le suma el próximo
            // verano completo solo en la solapa verano, que es la que compra para esa temporada.
            $tramosVerano   = $solapaVerano ? [$resto, $proximoVerano] : [$resto];
            $tramosInvierno = [$proximoInvierno];
        } else {
            // Transitando invierno: espejo exacto del caso anterior.
            $tramosVerano   = [$proximoVerano];
            $tramosInvierno = $solapaVerano ? [$resto] : [$resto, $proximoInvierno];
        }

        $columnaVerano   = self::describirColumnaProyectada($tramosVerano);
        $columnaInvierno = self::describirColumnaProyectada($tramosInvierno);

        // La compra tiene que alcanzar hasta el final del horizonte cubierto: la temporada
        // objetivo es el último tramo completo, porque es la que los contenedores deben llegar a cubrir.
        $objetivo = ($columnaVerano['hasta'] >= $columnaInvierno['hasta'])
            ? end($tramosVerano)
            : end($tramosInvierno);

        return [
            'verano'   => $columnaVerano,
            'invierno' => $columnaInvierno,
            'objetivo' => [
                'codigo' => $objetivo['codigo'],
                'desde'  => $objetivo['desde'],
                'hasta'  => $objetivo['hasta'],
                'parcial' => !empty($objetivo['resto'])
            ]
        ];
    }

    /**
     * Arma la etiqueta y el rango de una columna proyectada a partir de sus tramos.
     * Un tramo marcado como resto se rotula "Resto X": sin eso, una columna que cubre
     * cuatro meses de temporada se leería como si cubriera la temporada entera.
     */
    private static function describirColumnaProyectada($tramos) {
        $partes = [];
        $detalle = [];

        foreach ($tramos as $t) {
            $partes[] = (!empty($t['resto']) ? 'Resto ' : '') . $t['codigo'];
            $detalle[] = (!empty($t['resto']) ? 'Resto ' : '')
                . $t['codigo'] . ': '
                . date('d/m/Y', strtotime($t['desde'])) . ' a ' . date('d/m/Y', strtotime($t['hasta']));
        }

        return [
            'etiqueta' => implode(' + ', $partes),
            'detalle'  => implode(' · ', $detalle),
            'desde'    => $tramos[0]['desde'],
            'hasta'    => $tramos[count($tramos) - 1]['hasta'],
            'tramos'   => $tramos
        ];
    }

    /**
     * Etiquetas de las columnas de venta proyectada.
     * Se mantiene el nombre y la forma (claves 'verano'/'invierno' con strings) porque
     * MainController y actualizarHeadersDinamicos() ya las consumían así.
     */
    public static function generarEtiquetasVentaProyectada($fecha = null, $contextoSolapa = 'verano') {
        $periodos = self::obtenerPeriodosProyeccion($fecha, $contextoSolapa);

        return [
            'verano'           => $periodos['verano']['etiqueta'],
            'invierno'         => $periodos['invierno']['etiqueta'],
            'verano_detalle'   => $periodos['verano']['detalle'],
            'invierno_detalle' => $periodos['invierno']['detalle'],
            'objetivo'         => $periodos['objetivo']
        ];
    }

    /**
     * Traduce el nombre de una columna histórica del SP a la convención de la app.
     * VTA_VERANO_26 (01/08/2025 a 31/01/2026) -> "VER 25-26"
     * VTA_INVIERNO_26 (01/02/2026 a 31/07/2026) -> "INV 26"
     *
     * El SP numera el verano por el año en que termina, así que hay que restarle uno
     * para obtener el año de inicio. Se descartó renombrar las columnas en el SP:
     * son la clave con la que viajan los datos y romperían a cualquier otro consumidor.
     */
    public static function etiquetaColumnaHistorica($columna) {
        $temporada = self::temporadaDeColumna($columna);
        return $temporada ? $temporada['codigo'] : $columna;
    }

    /**
     * Identifica a qué temporada corresponde el nombre de una columna de venta histórica.
     * Devuelve null si el nombre no es de una columna de temporada.
     *
     * Reconoce los dos formatos que puede traer el SP:
     *   VTA_VERANO_26 / "VERANO 26"  -> el número es el año en que TERMINA
     *   "VERANO 25-26"               -> el par es inicio-fin (TEMPORADA_ORIGINAL)
     * Interpretar el par como si fuera un año suelto era lo que rompía el orden:
     * tomaba el 25 y lo comparaba contra el 26 de un invierno, que está en otra escala.
     */
    public static function temporadaDeColumna($columna) {
        // Las cantidades pendientes de OC también llevan VERANO/INVIERNO en el nombre
        // pero no son ventas históricas.
        if (stripos($columna, 'CANT_PEND_OC') !== false) {
            return null;
        }

        // Par inicio-fin: el año de cierre es el segundo.
        if (preg_match('/(VERANO|INVIERNO)[\s_]*(\d{2})\s*-\s*(\d{2})/i', $columna, $m)) {
            return self::construirTemporada(strtoupper($m[1]), 2000 + (int)$m[3]);
        }

        if (preg_match('/(VERANO|INVIERNO)[\s_]*(\d{2})/i', $columna, $m)) {
            return self::construirTemporada(strtoupper($m[1]), 2000 + (int)$m[2]);
        }

        return null;
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
     * Obtiene información completa de la temporada actual para debugging - MODIFICADA
     */
    public static function obtenerInfoTemporada($fecha = null) {
        $fecha = self::normalizarFecha($fecha);
        $temporada = self::obtenerTemporadaActual($fecha);
        $diasRestantes = self::calcularDiasRestantesTemporada($fecha);
        $diasTotales = self::calcularDiasTotalesTemporadaActual($fecha);

        return [
            'temporada_actual' => $temporada,
            'dias_totales' => $diasTotales,
            'dias_restantes' => $diasRestantes,
            'etiquetas_proyeccion' => self::generarEtiquetasVentaProyectada($fecha, 'verano'),
            // Los períodos van por solapa: la misma columna cubre distinto período en cada una.
            // El JS los usa para rotular los encabezados y para recalcular al editar un índice,
            // en vez de volver a deducir la temporada con el reloj del navegador.
            'periodos' => [
                'verano'   => self::obtenerPeriodosProyeccion($fecha, 'verano'),
                'invierno' => self::obtenerPeriodosProyeccion($fecha, 'invierno')
            ],
            'fecha_calculo' => $fecha->format('Y-m-d')
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

    /**
     * Calcular días totales de la temporada actual.
     * Los días ya vienen calculados en la temporada; se mantiene el método porque
     * lo consumen obtenerInfoTemporada() y el encabezado.
     */
    public static function calcularDiasTotalesTemporadaActual($fecha = null) {
        $temporadaActual = self::obtenerTemporadaActual($fecha);
        return $temporadaActual['dias'];
    }
}
?>