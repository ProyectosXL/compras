
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
     * Venta proyectada de UN tramo, con el redondeo aplicado a ese tramo.
     *
     * Es la unidad mínima del cálculo: un tramo completo proyecta la venta anterior
     * por su índice, y un resto de temporada la prorratea por los días que quedan.
     * El redondeo va acá, por tramo, y NO sobre la suma: así el total de una columna
     * es exactamente la suma de los números que se muestran y se guardan por tramo.
     * Sumar primero y redondear después habría dejado los tramos sin cerrar contra
     * su propio total por uno o dos unidades.
     */
    public static function ventaProyectadaDeTramo($tramo, $ventaAnterior, $indice, $diasRestantes, $diasTotales) {
        $ventaAnterior = (float)$ventaAnterior;
        $indice = (float)$indice;

        if (empty($tramo['resto'])) {
            return (float)round($ventaAnterior * $indice);
        }

        // Un resto sin días totales no se puede prorratear; se trata como vacío antes
        // que dividir por cero.
        if ((int)$diasTotales <= 0) {
            return 0.0;
        }

        return (float)round($ventaAnterior * $indice * ($diasRestantes / $diasTotales));
    }

    /**
     * Suma de la venta proyectada de una columna ('verano' o 'invierno').
     *
     * Única definición de las dos columnas proyectadas: los tramos salen de
     * obtenerPeriodosProyeccion(), que ya sabía qué cubre cada columna en cada
     * solapa. Antes esta cuenta estaba escrita dos veces —acá con un if/else por
     * contexto y allá como lista de tramos— y nada garantizaba que describieran lo
     * mismo. Ahora el total ES la suma de los tramos, por construcción, que es lo
     * que permite guardar el detalle por tramo sin que deje de cerrar con el total.
     */
    private static function sumarColumnaProyectada($columna, $ventaAnterior, $indice, $fecha, $contextoSolapa) {
        $ventaAnterior = (float)$ventaAnterior;
        $indice = (float)$indice;

        if ($ventaAnterior <= 0) {
            return 0;
        }
        if ($indice <= 0) {
            $indice = 1.0; // Valor por defecto
        }

        $periodos = self::obtenerPeriodosProyeccion($fecha, $contextoSolapa);
        $diasRestantes = self::calcularDiasRestantesTemporada($fecha);
        $diasTotales = self::obtenerTemporadaActual($fecha)['dias'];

        $total = 0;
        foreach ($periodos[$columna]['tramos'] as $tramo) {
            $total += self::ventaProyectadaDeTramo($tramo, $ventaAnterior, $indice, $diasRestantes, $diasTotales);
        }

        return $total;
    }

    /**
     * Venta proyectada de la columna INVIERNO, según la solapa desde la que se mira.
     * La descripción del período está en obtenerPeriodosProyeccion(): acá solo se suma.
     */
    public static function calcularVentaProyectadaInvierno($ventaInviernoAnterior, $indiceVariacion, $fecha = null, $contextoSolapa = 'invierno') {
        return self::sumarColumnaProyectada('invierno', $ventaInviernoAnterior, $indiceVariacion, $fecha, $contextoSolapa);
    }

    /**
     * Venta proyectada de la columna VERANO, según la solapa desde la que se mira.
     * Espejo exacto de calcularVentaProyectadaInvierno().
     */
    public static function calcularVentaProyectadaVerano($ventaVeranoAnterior, $indiceVariacion, $fecha = null, $contextoSolapa = 'verano') {
        return self::sumarColumnaProyectada('verano', $ventaVeranoAnterior, $indiceVariacion, $fecha, $contextoSolapa);
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
        
        // Extraer ventas anteriores, junto con la temporada de la que salieron:
        // la version guardada tiene que poder decir sobre que base se proyecto.
        $baseVerano = self::extraerColumnaVentaAnterior($registro, 'VERANO');
        $baseInvierno = self::extraerColumnaVentaAnterior($registro, 'INVIERNO');
        $ventaVeranoAnterior = $baseVerano ? $baseVerano['valor'] : 0;
        $ventaInviernoAnterior = $baseInvierno ? $baseInvierno['valor'] : 0;
        
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

        // Y el mismo número abierto por tramo, que es lo que el cashflow necesita:
        // lo de INV 27 y lo de VER 27-28 llegan en contenedores distintos y se pagan
        // en meses distintos, así que un solo total no alcanza para proyectar pagos.
        $tramos = self::calcularTramosDeFila(
            $stockProyectado, $ventaVeranoAnterior, $indiceVariacion,
            $ventaInviernoAnterior, $indiceVariacionInvierno, $fecha, $contextoSolapa
        );

        return [
            // Las bases se devuelven junto con el resultado para que la pantalla muestre
            // exactamente el número desde el que se proyectó. El JS tenía su propia
            // búsqueda de la columna base, con una lista fija de solo dos años, y cuando
            // la última venta era más vieja mostraba 0 mientras el cálculo usaba otro valor.
            'venta_verano_anterior' => $ventaVeranoAnterior,
            'venta_invierno_anterior' => $ventaInviernoAnterior,
            'temporada_base_verano' => $baseVerano ? $baseVerano['codigo'] : null,
            'temporada_base_invierno' => $baseInvierno ? $baseInvierno['codigo'] : null,
            'venta_proy_verano' => $ventaProyVerano,
            'venta_proy_invierno' => $ventaProyInvierno,
            'compra_proyectada' => $compraProyectada,
            'tramos' => $tramos,
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
        $elegida = self::extraerColumnaVentaAnterior($registro, $temporada);
        return $elegida ? $elegida['valor'] : 0;
    }

    /**
     * Igual que extraerVentaAnterior pero devuelve tambien de que columna salio.
     *
     * Hace falta porque la columna base varia POR FILA: se toma la ultima
     * temporada con ventas, y un rubro sin movimiento reciente cae a una mas
     * vieja que el de al lado. Guardar solo el numero dejaba la version sin
     * poder decir de que temporada venia cada base.
     *
     * @return array|null ['valor' => float, 'columna' => string, 'codigo' => string]
     */
    public static function extraerColumnaVentaAnterior($registro, $temporada) {
        $candidatas = [];

        foreach ($registro as $columna => $valor) {
            if (stripos($columna, $temporada) === false
                || stripos($columna, 'PROY') !== false
                || !is_numeric($valor) || $valor <= 0) {
                continue;
            }

            // Solo columnas que correspondan a una temporada identificable: asi
            // quedan afuera CANT_PEND_OC_* y compania, que llevan la palabra
            // VERANO/INVIERNO en el nombre pero no son ventas historicas.
            $t = self::temporadaDeColumna($columna);
            if (!$t) {
                continue;
            }

            $candidatas[] = [
                'valor'   => (float)$valor,
                'columna' => $columna,
                'codigo'  => $t['codigo'],
                'desde'   => $t['desde']
            ];
        }

        if (empty($candidatas)) {
            return null;
        }

        // Mas reciente primero, por fecha real de inicio de temporada.
        usort($candidatas, function ($a, $b) {
            return strcmp($b['desde'], $a['desde']);
        });

        return $candidatas[0];
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
     * Define qué período cubre cada columna de venta proyectada, como lista de tramos.
     *
     * Es la ÚNICA definición de qué se suma en cada columna. Nació describiendo lo
     * que calculaban calcularVentaProyectadaVerano() y calcularVentaProyectadaInvierno()
     * —para que las etiquetas dejaran de nombrar una sola temporada cuando la columna
     * contenía dos— pero la relación se invirtió: ahora esas funciones suman los tramos
     * que salen de acá, y repartirCompraPorTramo() reparte la compra sobre los mismos.
     * Mientras hubo dos descripciones del mismo período, nada garantizaba que
     * coincidieran; ahora la etiqueta, el total y el detalle por tramo son la misma
     * lista leída de tres maneras.
     *
     * El resultado se memoriza por (fecha, solapa) porque el procesamiento lo pide una
     * vez por fila y por columna —unas 300 veces por pantalla— y siempre con los mismos
     * dos argumentos.
     *
     * @param string $contextoSolapa 'verano' o 'invierno'
     * @return array columnas 'verano' e 'invierno' + 'objetivo' (temporada que la compra debe cubrir)
     */
    public static function obtenerPeriodosProyeccion($fecha = null, $contextoSolapa = 'verano') {
        static $memo = [];

        $fecha = self::normalizarFecha($fecha);
        $clave = $fecha->format('Y-m-d') . '|' . $contextoSolapa;
        if (isset($memo[$clave])) {
            return $memo[$clave];
        }

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

        return $memo[$clave] = [
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
     * Los tramos de las dos columnas, en el orden en que se venden.
     *
     * El reparto del stock necesita un solo hilo cronológico, no dos columnas: el
     * stock que sobra del resto del verano en curso es el que después cubre INV 27.
     * Ordenar por fecha de inicio alcanza porque los tramos no se solapan: cada uno
     * arranca el día después de que termina el anterior.
     */
    public static function tramosCronologicos($periodos) {
        $tramos = array_merge($periodos['verano']['tramos'], $periodos['invierno']['tramos']);

        usort($tramos, function ($a, $b) {
            return strcmp($a['desde'], $b['desde']);
        });

        return $tramos;
    }

    /**
     * Reparte entre los tramos la compra que hoy es un solo número por fila.
     *
     * FUNCIÓN PURA: no lee el reloj, la sesión ni la base. Todo lo que necesita entra
     * por parámetro, así que el mismo reparto se puede recalcular meses después desde
     * una versión guardada y da idéntico. Es lo que permite migrar el historial sin
     * inventar nada y lo que hace auditable cada fila.
     *
     * CÓMO REPARTE
     * El stock proyectado es un pozo único que se consume en orden cronológico: cada
     * tramo toma lo que puede del stock que quedó y la compra de ese tramo es lo que
     * el stock no alcanzó a cubrir. Se descartó prorratear el stock entre los tramos
     * en proporción a su venta: el stock que hay hoy cubre primero lo que se vende
     * primero, no una fracción de cada temporada futura.
     *
     * Las OC pendientes ya vienen sumadas dentro del stock proyectado y NO se afectan
     * a la temporada de su oleada: entran al pozo común como cualquier unidad. Es una
     * simplificación deliberada —las fechas reales de esas OC las administra Comex y
     * esta app no las tiene— y se midió cuánto cuesta: sobre la versión oficial de
     * Argentina mueve 1.619 unidades del tramo objetivo, el 0,23 %, en 1 fila de 72.
     *
     * DÉFICIT DE COBERTURA
     * Cuando el stock de seguridad supera a todo lo disponible, el stock proyectado
     * arranca negativo (ACCESORIO DE CUERO: 0 - 309 = -309). Ese déficit NO se le carga
     * al primer tramo cronológico, porque ese tramo es el resto de la temporada en
     * curso y ya no se puede comprar: ahí el déficit desaparecería del presupuesto. Va
     * al primer tramo COMPRABLE, el primero que no sea ese resto, que es el contenedor
     * más cercano sobre el que todavía se puede actuar. Se descartó mandarlo al tramo
     * objetivo: lo habría atrasado hasta un año sin motivo. Queda además separado en
     * compra_deficit_cobertura para que el consumidor externo pueda tratarlo distinto
     * de una compra por venta.
     *
     * TRAMO NO COMPRABLE
     * El resto de la temporada en curso conserva su compra calculada, pero marcado con
     * es_comprable = 0: no es mercadería a comprar sino venta que va a quedar sin
     * cubrir, y se muestra aparte. Se lo deja dentro de `compra` —en vez de en una
     * columna separada— para que el invariante siga siendo literal.
     *
     * INVARIANTE, por fila: SUM(compra) == MAX(0, -compra_proyectada).
     * Se cumple exacto porque el pozo se redondea a unidades enteras igual que
     * compra_proyectada, y restar un entero no cambia la parte decimal de un redondeo.
     * Las filas con excedente dan compra 0 en todos los tramos.
     *
     * @param float $stockProyectado stock proyectado de la fila (puede ser negativo)
     * @param array $tramos          de tramosCronologicos()
     * @param array $bases           ['VERANO' => ['valor'=>, 'indice'=>], 'INVIERNO' => [...]]
     * @param int   $diasRestantes   días que quedan de la temporada en curso
     * @param int   $diasTotales     días totales de la temporada en curso
     * @param array $objetivo        ['codigo'=>, 'parcial'=>] de obtenerPeriodosProyeccion()
     * @return array una fila por tramo, en orden cronológico
     */
    public static function repartirCompraPorTramo($stockProyectado, $tramos, $bases,
                                                  $diasRestantes, $diasTotales, $objetivo = null,
                                                  $ventasPorTramo = null) {
        // El pozo va en unidades enteras: compra_proyectada también se redondea, y sin
        // esto el invariante fallaba por una unidad en las filas con stock fraccionario.
        $pozo = (float)round((float)$stockProyectado);

        // El déficit sale del pozo y se imputa aparte; el resto del reparto trabaja
        // siempre con un disponible >= 0, que es lo que vuelve legible el acumulado.
        $deficit  = max(0.0, -$pozo);
        $restante = max(0.0, $pozo);

        $filas = [];
        $indiceComprable = null;

        foreach ($tramos as $orden => $tramo) {
            $tipo = $tramo['temporada'];
            $base = isset($bases[$tipo]) ? (float)$bases[$tipo]['valor'] : 0.0;
            $indice = isset($bases[$tipo]) ? (float)$bases[$tipo]['indice'] : 1.0;
            if ($indice <= 0) {
                $indice = 1.0;
            }
            // Mismo corte que sumarColumnaProyectada(): sin venta anterior no se proyecta.
            if ($base <= 0) {
                $base = 0.0;
            }

            // Con $ventasPorTramo la venta de cada tramo ya viene resuelta (venta cargada
            // a mano, ver ventasDeColumna): se usa tal cual para que el reparto sume
            // exactamente lo que se ve en la columna.
            $clave = self::claveTramo($tramo);
            $venta = ($ventasPorTramo !== null && array_key_exists($clave, $ventasPorTramo))
                ? (float)$ventasPorTramo[$clave]
                : self::ventaProyectadaDeTramo($tramo, $base, $indice, $diasRestantes, $diasTotales);

            $aplicado = min($restante, $venta);
            $compra   = $venta - $aplicado;
            $restante -= $aplicado;

            $esResto = !empty($tramo['resto']);
            // Comprable = todo lo que no sea el resto de la temporada en curso. Solo ese
            // tramo se marca como resto en obtenerPeriodosProyeccion(), y es justamente
            // el que ya no llega a cubrirse con un contenedor nuevo.
            $esComprable = !$esResto;

            if ($esComprable && $indiceComprable === null) {
                $indiceComprable = $orden;
            }

            $filas[] = [
                'orden'            => $orden,
                'temporada_codigo' => $tramo['codigo'],
                'temporada_tipo'   => $tipo,
                'temporada_desde'  => $tramo['desde'],
                'temporada_hasta'  => $tramo['hasta'],
                'es_resto'         => $esResto,
                'es_comprable'     => $esComprable,
                'es_objetivo'      => $objetivo
                    ? ($tramo['codigo'] === $objetivo['codigo'] && $esResto === !empty($objetivo['parcial']))
                    : false,
                'venta_proyectada' => (int)$venta,
                'stock_aplicado'   => (int)$aplicado,
                'compra'           => (int)$compra,
                'compra_deficit_cobertura' => 0
            ];
        }

        if ($deficit > 0 && $filas) {
            // Si no hubiera ningún tramo comprable —hoy no pasa, las dos solapas tienen
            // siempre al menos una temporada completa— se usa el último antes que perderlo.
            $destino = $indiceComprable !== null ? $indiceComprable : count($filas) - 1;
            $filas[$destino]['compra'] += (int)$deficit;
            $filas[$destino]['compra_deficit_cobertura'] = (int)$deficit;
        }

        return $filas;
    }

    // ==================================================================
    // VENTA PROYECTADA EDITABLE Y COBERTURA (solo Argentina)
    // ==================================================================

    /** Identifica un tramo dentro de una fila: el resto y la temporada completa del mismo código son distintos. */
    public static function claveTramo($tramo) {
        return $tramo['codigo'] . (empty($tramo['resto']) ? '' : '|resto');
    }

    /** Proporción de la base que proyecta un tramo: 1 si es completo, días restantes / totales si es resto. */
    public static function factorTramo($tramo, $diasRestantes, $diasTotales) {
        if (empty($tramo['resto'])) {
            return 1.0;
        }
        return (int)$diasTotales > 0 ? $diasRestantes / $diasTotales : 0.0;
    }

    /** Días de venta de un tramo: los restantes si es resto, los de la temporada si es completo. */
    public static function diasDeTramo($tramo, $diasRestantes) {
        return empty($tramo['resto']) ? (int)$tramo['dias'] : (int)$diasRestantes;
    }

    /**
     * Venta de cada tramo de UNA columna proyectada, con o sin venta cargada a mano.
     *
     * Sin venta manual es la fórmula de siempre (ventaProyectadaDeTramo por tramo) y el
     * índice manda. Con venta manual V manda la venta:
     *
     *   - Con base > 0 se despeja el índice: V = base × índice × Σ factores, o sea
     *     índice = V / (base × Σ factores), el MISMO para todos los tramos de la columna.
     *     Se guarda con 6 decimales: con 2, una venta cargada de 1.000 podía volver como
     *     996 al recalcular. Los tramos se calculan con ese índice y la fórmula de
     *     siempre; como cada tramo redondea por su lado, la suma puede quedar a una
     *     unidad de V, y esa diferencia se carga en el ÚLTIMO tramo de la columna.
     *   - Con base 0 no hay índice que despejar ("venta manual"): V se reparte entre los
     *     tramos en proporción a sus días y el último se lleva el resto del redondeo.
     *
     * En los dos casos la suma de los tramos es exactamente V, y como cada tramo es un
     * entero, el invariante del reparto SUM(compra) == MAX(0, -compra_proyectada) sigue
     * valiendo: compra_proyectada se arma restando estas mismas ventas.
     *
     * @param array      $tramos      tramos de la columna (obtenerPeriodosProyeccion()[col]['tramos'])
     * @param float      $base        venta anterior de la fila
     * @param float      $indice      índice de la temporada (se ignora si hay venta manual)
     * @param int|null   $ventaManual venta cargada a mano, o null
     * @return array ['ventas' => [claveTramo => int], 'total' => int, 'indice' => float|null, 'manual' => bool]
     */
    public static function ventasDeColumna($tramos, $base, $indice, $ventaManual, $diasRestantes, $diasTotales) {
        $base = (float)$base;
        $ventas = [];

        if ($ventaManual === null || $ventaManual === '') {
            // Mismos cortes que sumarColumnaProyectada(): sin base no se proyecta y un
            // índice 0 (el SP lo da cuando no hubo venta el año anterior) vale 1,00.
            $indiceEfectivo = (float)$indice > 0 ? (float)$indice : 1.0;
            foreach ($tramos as $t) {
                $ventas[self::claveTramo($t)] = $base > 0
                    ? (int)self::ventaProyectadaDeTramo($t, $base, $indiceEfectivo, $diasRestantes, $diasTotales)
                    : 0;
            }
            return ['ventas' => $ventas, 'total' => array_sum($ventas), 'indice' => (float)$indice, 'manual' => false];
        }

        $objetivo = max(0, (int)round((float)$ventaManual));
        $sumaFactores = 0.0;
        foreach ($tramos as $t) {
            $sumaFactores += self::factorTramo($t, $diasRestantes, $diasTotales);
        }

        if ($base > 0 && $sumaFactores > 0) {
            $indiceExacto = round($objetivo / ($base * $sumaFactores), 6);
            foreach ($tramos as $t) {
                $ventas[self::claveTramo($t)] = (int)self::ventaProyectadaDeTramo($t, $base, $indiceExacto, $diasRestantes, $diasTotales);
            }
        } else {
            $indiceExacto = null;
            $sumaDias = 0;
            foreach ($tramos as $t) {
                $sumaDias += self::diasDeTramo($t, $diasRestantes);
            }
            foreach ($tramos as $t) {
                $ventas[self::claveTramo($t)] = $sumaDias > 0
                    ? (int)round($objetivo * self::diasDeTramo($t, $diasRestantes) / $sumaDias)
                    : 0;
            }
        }

        // Ajuste del redondeo: del último tramo hacia atrás, sin dejar ninguno negativo.
        $diferencia = $objetivo - array_sum($ventas);
        $claves = array_keys($ventas);
        for ($i = count($claves) - 1; $i >= 0 && $diferencia !== 0; $i--) {
            $nuevo = max(0, $ventas[$claves[$i]] + $diferencia);
            $diferencia -= ($nuevo - $ventas[$claves[$i]]);
            $ventas[$claves[$i]] = $nuevo;
        }

        return ['ventas' => $ventas, 'total' => array_sum($ventas), 'indice' => $indiceExacto, 'manual' => true];
    }

    /**
     * Índices redondos (2 decimales) más cercanos a un índice despejado de una venta
     * cargada, con la venta que daría cada uno. Es lo que ofrece la pantalla con
     * "Para un índice redondo: 1,23 → 996 · 1,24 → 1.004".
     *
     * @return array|null null si no hay base; 'aproximado' false si el índice ya es redondo
     */
    public static function sugerenciasIndiceRedondo($tramos, $base, $indiceExacto, $diasRestantes, $diasTotales) {
        if ((float)$base <= 0 || $indiceExacto === null) {
            return null;
        }
        $abajo = floor(round($indiceExacto * 100, 6)) / 100;
        $arriba = ceil(round($indiceExacto * 100, 6)) / 100;

        $venta = function ($indice) use ($tramos, $base, $diasRestantes, $diasTotales) {
            return self::ventasDeColumna($tramos, $base, $indice, null, $diasRestantes, $diasTotales)['total'];
        };

        return [
            'aproximado' => $abajo !== $arriba,
            'indice_exacto' => $indiceExacto,
            'abajo' => ['indice' => $abajo, 'venta' => $venta($abajo)],
            'arriba' => ['indice' => $arriba, 'venta' => $venta($arriba)]
        ];
    }

    /**
     * Stock de cobertura a partir del ritmo de venta diario.
     *
     *   ritmo_diario    = (vendido + venta proy. resto + venta proy. próxima)
     *                   / (días transcurridos + días restantes + días de la próxima)
     *   stock_cobertura = round(ritmo_diario × índice_cobertura × días_por_mes)
     *
     * "Próxima" es la próxima temporada completa del OTRO tipo (en verano, el próximo
     * invierno). Lo vendido y sus días entran solo si ya pasaron los días mínimos de
     * temporada: con una o dos semanas de venta real el ritmo lo decidiría el arranque,
     * que no es representativo.
     *
     * Reemplaza a la cobertura del SP (venta de 360 días / 360 × 45), que no se movía
     * al editar índices: ahora la cobertura sigue a la venta que se está proyectando.
     *
     * FUNCIÓN PURA: todo entra por parámetro.
     *
     * @param array $a vendido (float|null), dias_transcurridos, vp_resto, dias_restantes,
     *                 vp_proxima, dias_proxima, indice_cobertura, dias_por_mes, dias_minimos
     */
    public static function calcularCobertura(array $a) {
        $usaVendido = $a['vendido'] !== null
            && (int)$a['dias_transcurridos'] > 0
            && (int)$a['dias_transcurridos'] >= (int)$a['dias_minimos'];

        $ventas = (float)$a['vp_resto'] + (float)$a['vp_proxima'] + ($usaVendido ? (float)$a['vendido'] : 0.0);
        $dias = (int)$a['dias_restantes'] + (int)$a['dias_proxima'] + ($usaVendido ? (int)$a['dias_transcurridos'] : 0);
        $indice = (float)$a['indice_cobertura'];

        $ritmo = ($ventas > 0 && $dias > 0) ? $ventas / $dias : 0.0;
        $stock = ($ritmo > 0 && $indice > 0) ? (int)round($ritmo * $indice * (float)$a['dias_por_mes']) : 0;

        return [
            'stock_cobertura' => $stock,
            'ritmo_diario'    => round($ritmo, 4),
            'usa_vendido'     => $usaVendido,
            'ventas'          => $ventas,
            'dias'            => $dias,
            'indice'          => $indice,
            'dias_por_mes'    => (float)$a['dias_por_mes']
        ];
    }

    /**
     * Contexto común a todas las filas de un cálculo de Argentina: días de la
     * temporada, qué cubre lo vendido y los parámetros de cobertura. Se arma una vez
     * por request; calcularFila() no lee reloj, base ni parámetros por su cuenta.
     *
     * @param Parametros $parametros
     * @param array|null $generales  días por mes / días mínimos que vinieron con la carga
     *                               (para que una edición use los mismos que la carga)
     */
    public static function contextoCalculo($solapa, $fecha, $ultActualizacion, $hayColumnaVendido, $parametros, $generales = null) {
        $fecha = self::normalizarFecha($fecha);
        $venta = self::infoVentaTemporadaEnCurso($ultActualizacion, $hayColumnaVendido, $fecha);

        return [
            'solapa'             => $solapa === 'invierno' ? 'invierno' : 'verano',
            'fecha'              => $fecha->format('Y-m-d'),
            'dias_restantes'     => self::calcularDiasRestantesTemporada($fecha),
            'dias_totales'       => self::obtenerTemporadaActual($fecha)['dias'],
            'vendido_vigente'    => $venta['disponible'] && $venta['vigente'],
            'dias_transcurridos' => $venta['dias_transcurridos'],
            'dias_por_mes'       => (float)($generales['dias_por_mes'] ?? $parametros->numero('COBERTURA_DIAS_POR_MES')),
            'dias_minimos'       => (int)($generales['dias_minimos'] ?? $parametros->numero('COBERTURA_DIAS_MINIMOS_VENDIDO'))
        ];
    }

    /**
     * Todo el cálculo de UNA fila de una solapa de compra de Argentina: ventas
     * proyectadas (con venta manual), índices efectivos, cobertura, stock proyectado,
     * compra y reparto por tramo.
     *
     * Es la única implementación: la usan la carga de las solapas, recalcular-fila
     * (cada edición en pantalla), el guardado de versiones y "Retomar". Uruguay no
     * pasa por acá y sigue con procesarRegistroCompraProyectada().
     *
     * @param array $e  cant_stock, cant_stock_guardar, cant_pend_oc_verano/_invierno/_atemporal,
     *                  venta_verano_anterior, venta_invierno_anterior, indice_verano,
     *                  indice_invierno, venta_manual_verano, venta_manual_invierno (int|null),
     *                  indice_cobertura, vta_temporada_en_curso (float|null)
     * @param array $ctx de contextoCalculo()
     */
    public static function calcularFila(array $e, array $ctx) {
        $fecha = $ctx['fecha'];
        $dr = (int)$ctx['dias_restantes'];
        $dt = (int)$ctx['dias_totales'];
        $periodos = self::obtenerPeriodosProyeccion($fecha, $ctx['solapa']);
        $actual = self::obtenerTemporadaActual($fecha);

        $columnas = [];
        $ventasPorTramo = [];
        foreach (['verano' => 'VERANO', 'invierno' => 'INVIERNO'] as $col => $tipo) {
            $manual = $e["venta_manual_$col"] ?? null;
            $manual = ($manual === null || $manual === '') ? null : (int)$manual;
            $columnas[$col] = self::ventasDeColumna(
                $periodos[$col]['tramos'], $e["venta_{$col}_anterior"] ?? 0, $e["indice_$col"] ?? 1.0, $manual, $dr, $dt);
            $columnas[$col]['sugerencias'] = $manual === null ? null
                : self::sugerenciasIndiceRedondo($periodos[$col]['tramos'], $e["venta_{$col}_anterior"] ?? 0,
                                                 $columnas[$col]['indice'], $dr, $dt);
            $ventasPorTramo += $columnas[$col]['ventas'];
        }

        // Tramos de la cobertura: el resto de la temporada en curso y la próxima completa
        // del otro tipo. Los dos están en las dos solapas (obtenerPeriodosProyeccion).
        $otroTipo = $actual['temporada'] === 'VERANO' ? 'INVIERNO' : 'VERANO';
        $proxima = self::proximaTemporadaDeTipo($actual, $otroTipo);
        $vpResto = 0;
        $vpProxima = 0;
        foreach (self::tramosCronologicos($periodos) as $t) {
            if (!empty($t['resto'])) {
                $vpResto = $ventasPorTramo[self::claveTramo($t)] ?? 0;
            } elseif ($t['codigo'] === $proxima['codigo']) {
                $vpProxima = $ventasPorTramo[self::claveTramo($t)] ?? 0;
            }
        }

        $vendido = $e['vta_temporada_en_curso'] ?? null;
        $cobertura = self::calcularCobertura([
            'vendido'            => ($ctx['vendido_vigente'] && $vendido !== null) ? (float)$vendido : null,
            'dias_transcurridos' => $ctx['dias_transcurridos'],
            'vp_resto'           => $vpResto,
            'dias_restantes'     => $dr,
            'vp_proxima'         => $vpProxima,
            'dias_proxima'       => $proxima['dias'],
            'indice_cobertura'   => $e['indice_cobertura'] ?? 0,
            'dias_por_mes'       => $ctx['dias_por_mes'],
            'dias_minimos'       => $ctx['dias_minimos']
        ]);

        $stockProyectado = self::calcularStockProyectado(
            (float)($e['cant_stock'] ?? 0), (float)($e['cant_stock_guardar'] ?? 0),
            (float)($e['cant_pend_oc_verano'] ?? 0), (float)($e['cant_pend_oc_invierno'] ?? 0),
            (float)($e['cant_pend_oc_atemporal'] ?? 0), $cobertura['stock_cobertura']);

        $tramos = self::repartirCompraPorTramo(
            $stockProyectado,
            self::tramosCronologicos($periodos),
            [
                'VERANO'   => ['valor' => $e['venta_verano_anterior'] ?? 0,   'indice' => $e['indice_verano'] ?? 1.0],
                'INVIERNO' => ['valor' => $e['venta_invierno_anterior'] ?? 0, 'indice' => $e['indice_invierno'] ?? 1.0]
            ],
            $dr, $dt, $periodos['objetivo'], $ventasPorTramo);

        $vpv = $columnas['verano']['total'];
        $vpi = $columnas['invierno']['total'];

        return [
            'venta_proy_verano'     => $vpv,
            'venta_proy_invierno'   => $vpi,
            // Con venta manual el índice es el despejado (null si no hay base); sin ella,
            // el que se cargó, sin tocar.
            'indice_verano'         => $columnas['verano']['indice'],
            'indice_invierno'       => $columnas['invierno']['indice'],
            'venta_manual_verano'   => $columnas['verano']['manual'] ? $vpv : null,
            'venta_manual_invierno' => $columnas['invierno']['manual'] ? $vpi : null,
            'sugerencias_verano'    => $columnas['verano']['sugerencias'],
            'sugerencias_invierno'  => $columnas['invierno']['sugerencias'],
            'indice_cobertura'      => (float)($e['indice_cobertura'] ?? 0),
            'stock_cobertura'       => $cobertura['stock_cobertura'],
            'cobertura'             => $cobertura,
            'stock_proyectado'      => $stockProyectado,
            'compra_proyectada'     => (int)(round($stockProyectado) - $vpv - $vpi),
            'tramos'                => $tramos
        ];
    }

    /**
     * Reparto por tramo de una fila del presupuesto, armando los parámetros desde el
     * registro crudo. Atajo para los tres lugares que lo necesitan (el procesamiento
     * de cada solapa, el guardado y la migración) sin repetir el cableado.
     */
    public static function calcularTramosDeFila($stockProyectado, $ventaVeranoAnterior, $indiceVerano,
                                                $ventaInviernoAnterior, $indiceInvierno,
                                                $fecha = null, $contextoSolapa = 'verano',
                                                $diasRestantes = null, $diasTotales = null) {
        $periodos = self::obtenerPeriodosProyeccion($fecha, $contextoSolapa);

        // Los días pueden venir impuestos: al reconstruir una versión guardada se usan
        // los que quedaron en su cabecera, no los de hoy.
        if ($diasRestantes === null) {
            $diasRestantes = self::calcularDiasRestantesTemporada($fecha);
        }
        if ($diasTotales === null) {
            $diasTotales = self::obtenerTemporadaActual($fecha)['dias'];
        }

        return self::repartirCompraPorTramo(
            $stockProyectado,
            self::tramosCronologicos($periodos),
            [
                'VERANO'   => ['valor' => $ventaVeranoAnterior,   'indice' => $indiceVerano],
                'INVIERNO' => ['valor' => $ventaInviernoAnterior, 'indice' => $indiceInvierno]
            ],
            $diasRestantes,
            $diasTotales,
            $periodos['objetivo']
        );
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

        // 6 decimales y no 4: un índice despejado de una venta cargada a mano necesita
        // esa precisión para que, al recalcularlo (Retomar), vuelva la misma venta.
        return round($indice, 6);
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
     * Qué cubre VTA_TEMPORADA_EN_CURSO del SP y si sirve para la temporada de hoy.
     *
     * El SP suma la venta real desde el primer día de la temporada hasta el día
     * anterior a su corrida, así que la fecha de corte es ULT_ACTUALIZACION − 1. La
     * pantalla la suma con el resto proyectado, que arranca HOY: si el SP corrió hace
     * más de un día, entre las dos quedan días que no están en ningún lado, y eso se
     * informa en vez de esconderlo.
     *
     * Si el SP corrió en otra temporada (por ejemplo el 31/01 y hoy es 02/02), lo que
     * trae es venta de la temporada anterior: se marca como no vigente para no sumarle
     * el resto de una temporada distinta.
     *
     * @param DateTime|string|null $ultActualizacion ULT_ACTUALIZACION de la tabla del SP
     * @param bool $hayColumna si la tabla trae VTA_TEMPORADA_EN_CURSO (script 06)
     */
    public static function infoVentaTemporadaEnCurso($ultActualizacion, $hayColumna, $fecha = null) {
        $hoy = self::normalizarFecha($fecha);
        $temporada = self::obtenerTemporadaActual($hoy);

        $info = [
            'disponible' => (bool)$hayColumna && $ultActualizacion !== null,
            'codigo'     => $temporada['codigo'],
            'tipo'       => $temporada['temporada'],
            'desde'      => $temporada['desde'],
            'fecha_corte' => null,
            'dias_sin_contar' => 0,
            // Días que cubre lo vendido: del primer día de la temporada al corte
            // (inclusive). Es el D_transcurridos del ritmo de la cobertura.
            'dias_transcurridos' => 0,
            'vigente'    => false
        ];

        if (!$info['disponible']) {
            return $info;
        }

        $corrida = self::normalizarFecha($ultActualizacion);
        $corte = (clone $corrida)->modify('-1 day');

        $info['fecha_corte'] = $corte->format('Y-m-d');
        // La temporada de la corrida es la que usó el SP para elegir el primer día.
        $info['vigente'] = self::obtenerTemporadaActual($corrida)['codigo'] === $temporada['codigo'];
        // Días entre el corte y ayer que no suma nadie: el resto proyectado empieza hoy.
        $info['dias_sin_contar'] = $corrida < $hoy ? (int)$corrida->diff($hoy)->days : 0;
        // corte - desde + 1 = corrida - desde. Con el SP corrido el 12/11 en VER 26-27
        // son los 103 días del 01/08 al 11/11.
        $desde = new DateTime($temporada['desde']);
        $info['dias_transcurridos'] = ($info['vigente'] && $corrida > $desde) ? (int)$desde->diff($corrida)->days : 0;

        return $info;
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