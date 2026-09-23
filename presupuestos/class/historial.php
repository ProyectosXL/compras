<?php
require_once __DIR__.'/../../class/conexion.php';
require_once __DIR__.'/presupuestoCalculos.php';
require_once __DIR__.'/CostoProyeccion.php';

/**
 * Historial de presupuestos de compra proyectada.
 *
 * Una version guardada son dos cosas: una fila de CABECERA que describe la
 * version entera (cuando se calculo, para que temporada, si es completa, si es
 * la oficial) y N filas de DETALLE, una por rubro/categoria.
 *
 * La cabecera existe porque esta tabla la va a leer el cashflow de finanzas y el
 * detalle solo no alcanza: la columna `temporada` guarda la solapa
 * ('verano'/'invierno'), no una temporada con año, y nada decia cual version era
 * la vigente ni con que costo se habia calculado.
 *
 * Compatibilidad: el detalle conserva nombre_presupuesto y temporada, asi que
 * cualquier lector que hoy consulte solo esa tabla sigue funcionando igual.
 */
class Historial {
    // protected y no private para poder instanciar la clase contra otra base
    // (por ejemplo tempdb) en las pruebas, sin duplicar la logica.
    protected $cid;
    protected $nameServer;

    // Qué scripts de sql/ tiene aplicados ESTA base. Se consultan una vez por
    // instancia y no una vez por fila insertada, pero tampoco en un static de
    // función: cada instancia puede estar apuntada a otra base.
    private $cacheDetalleAmpliado = null;
    private $cacheHayTramos = null;
    private $cacheEstadoTramos = null;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->nameServer = $this->determinarBaseDatos();
        $conexion = new Conexion();
        $this->cid = $conexion->conectar($this->nameServer);
    }

    private function determinarBaseDatos() {
        $pais = 'argentina'; // Default
        if (isset($_SESSION['pais_seleccionado'])) {
            $pais = strtolower($_SESSION['pais_seleccionado']);
        }
        // FIX: Connect to the application's database ('apps_power' or 'apps_power_uy'), not the source ERP database.
        return $pais === 'uruguay' ? 'apps_power_uy' : 'apps_power';
    }

    private function paisActual() {
        return $this->nameServer === 'apps_power_uy' ? 'uruguay' : 'argentina';
    }

    /**
     * Usuario que ejecuta la accion.
     *
     * Sale de $_SESSION['usuario_dns'], la convencion del resto de las apps. El
     * modulo de presupuestos todavia no tiene autenticacion, asi que hoy devuelve
     * NULL: se prefiere dejarlo vacio antes que inventar un usuario, porque estas
     * columnas son la trazabilidad de quien marco la version oficial.
     */
    private function usuarioActual() {
        return isset($_SESSION['usuario_dns']) && $_SESSION['usuario_dns'] !== ''
            ? $_SESSION['usuario_dns']
            : null;
    }

    /**
     * Indica si la base ya tiene la cabecera de versiones.
     *
     * Se consulta en vez de asumirla porque los scripts de la fase 2 se corren
     * por base y por pais: mientras una de las dos no este migrada, el historial
     * tiene que seguir funcionando contra el detalle solo.
     */
    private function hayCabecera() {
        $stmt = sqlsrv_query($this->cid,
            "SELECT CASE WHEN OBJECT_ID('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA','U') IS NULL
                         THEN 0 ELSE 1 END AS existe");
        if ($stmt === false) {
            return false;
        }
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return (bool)$row['existe'];
    }

    /**
     * Guarda una version del presupuesto proyectado.
     *
     * @param array $datos nombre_presupuesto, temporada (solapa), filas,
     *                     es_completa, filas_totales
     */
    public function guardarPresupuesto($datos) {
        if (!$this->cid) {
            return ['success' => false, 'message' => 'Error de conexión a la base de datos.'];
        }

        $nombrePresupuesto = $datos['nombre_presupuesto'] ?? null;
        $solapa = strtolower(trim($datos['temporada'] ?? '')) === 'invierno' ? 'invierno' : 'verano';
        $filas = $datos['filas'] ?? [];
        $pais = $this->paisActual();

        if (empty($nombrePresupuesto) || empty($filas)) {
            return ['success' => false, 'message' => 'Faltan datos requeridos (nombre, filas).'];
        }

        // La fecha la pone el SERVIDOR. Antes venia del navegador, asi que
        // dependia de como estuviera la maquina de quien guardaba, y es la fecha
        // contra la que se prorratean los restos de temporada.
        $ahora = new DateTime();
        $fechaCalculo = $ahora->format('Y-m-d');

        $periodos = PresupuestoCalculos::obtenerPeriodosProyeccion($fechaCalculo, $solapa);
        $temporadaActual = PresupuestoCalculos::obtenerTemporadaActual($fechaCalculo);

        $filasGuardadas = count($filas);
        $filasTotales = (int)($datos['filas_totales'] ?? 0);
        // Completa solo si cubre todo. Ante la duda, parcial: una parcial no
        // puede marcarse oficial, asi que equivocarse hacia parcial no arrastra
        // una version incompleta al consumidor externo.
        $esCompleta = ($filasTotales > 0 && $filasGuardadas >= $filasTotales) ? 1 : 0;

        // Costos vigentes al momento de guardar. FP_T_COSTOS_PARAMETROS se pisa
        // en el lugar (no tiene version ni fecha), asi que si no se copian acá
        // la version deja de ser reproducible en cuanto alguien cambie un costo.
        $costos = $this->obtenerCostos();

        $tramosGuardados = 0;

        try {
            if (sqlsrv_begin_transaction($this->cid) === false) {
                throw new Exception("No se pudo iniciar la transacción: " . print_r(sqlsrv_errors(), true));
            }

            $idCabecera = null;

            if ($this->hayCabecera()) {
                $sqlCab = "INSERT INTO RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA (
                                nombre_presupuesto, fecha_guardado, usuario_guardado, pais, solapa,
                                fecha_calculo, dias_restantes_temporada, dias_totales_temporada,
                                temporada_objetivo, temporada_objetivo_desde, temporada_objetivo_hasta,
                                periodo_venta_verano_desde, periodo_venta_verano_hasta, periodo_venta_verano_etiqueta,
                                periodo_venta_invierno_desde, periodo_venta_invierno_hasta, periodo_venta_invierno_etiqueta,
                                es_completa, filas_guardadas, filas_totales, es_oficial
                           ) OUTPUT INSERTED.id
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";

                $stmtCab = sqlsrv_query($this->cid, $sqlCab, [
                    $nombrePresupuesto,
                    $ahora->format('Y-m-d H:i:s'),
                    $this->usuarioActual(),
                    $pais,
                    $solapa,
                    $fechaCalculo,
                    PresupuestoCalculos::calcularDiasRestantesTemporada($fechaCalculo),
                    $temporadaActual['dias'],
                    $periodos['objetivo']['codigo'],
                    $periodos['objetivo']['desde'],
                    $periodos['objetivo']['hasta'],
                    $periodos['verano']['desde'],
                    $periodos['verano']['hasta'],
                    $periodos['verano']['etiqueta'],
                    $periodos['invierno']['desde'],
                    $periodos['invierno']['hasta'],
                    $periodos['invierno']['etiqueta'],
                    $esCompleta,
                    $filasGuardadas,
                    $filasTotales ?: null
                ]);

                if ($stmtCab === false) {
                    throw new Exception("Error al insertar la cabecera: " . print_r(sqlsrv_errors(), true));
                }
                $idCabecera = (int)sqlsrv_fetch_array($stmtCab, SQLSRV_FETCH_NUMERIC)[0];
                sqlsrv_free_stmt($stmtCab);
            }

            $registrosGuardados = $this->insertarDetalle(
                $idCabecera, $nombrePresupuesto, $solapa, $pais,
                $ahora->format('Y-m-d H:i'), $filas, $costos,
                $fechaCalculo, $tramosGuardados
            );

            // El reparto se calculó con los días de ESTE guardado, así que la versión
            // queda marcada como calculada y no como reconstruida: es la diferencia
            // entre un reparto exacto y uno deducido después por la migración.
            if ($idCabecera && $this->hayTramos()) {
                $up = sqlsrv_query($this->cid,
                    "UPDATE RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA
                        SET tramos_estado = 'CALCULADO' WHERE id = ?", [$idCabecera]);
                if ($up === false) {
                    throw new Exception("Al marcar el estado del reparto: " . print_r(sqlsrv_errors(), true));
                }
                sqlsrv_free_stmt($up);
            }

            sqlsrv_commit($this->cid);

            return [
                'success' => true,
                'message' => "Presupuesto guardado como '{$nombrePresupuesto}' "
                           . "(" . ($esCompleta ? 'completo' : 'parcial') . ", "
                           . "temporada objetivo {$periodos['objetivo']['codigo']}).",
                'registros_guardados' => $registrosGuardados,
                'tramos_guardados' => $tramosGuardados,
                'id_cabecera' => $idCabecera,
                'es_completa' => (bool)$esCompleta,
                'temporada_objetivo' => $periodos['objetivo']['codigo']
            ];

        } catch (Exception $e) {
            if ($this->cid) {
                sqlsrv_rollback($this->cid);
            }
            error_log("Error en guardarPresupuesto: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al guardar el presupuesto: ' . $e->getMessage()];
        }
    }

    /**
     * Inserta las filas de detalle.
     *
     * Las columnas nuevas se completan solo si la base ya las tiene: los scripts
     * de la fase 2 se aplican por base, y guardar tiene que seguir funcionando
     * en la que todavia no se migro.
     */
    private function insertarDetalle($idCabecera, $nombre, $solapa, $pais, $fecha, $filas, $costos,
                                     $fechaCalculo = null, &$tramosGuardados = 0) {
        $ampliado = $this->detalleAmpliado();
        $conTramos = $this->hayTramos();

        if ($ampliado) {
            $sql = "INSERT INTO RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO (
                        nombre_presupuesto, fecha_guardado, temporada, pais, rubro,
                        categoria_padre, stock_proyectado, indice_variacion_original,
                        indice_verano_variacion, venta_verano_anterior, venta_proyectada_verano,
                        indice_invierno_variacion, venta_invierno_anterior, venta_proyectada_invierno,
                        compra_proyectada,
                        id_cabecera, cant_stock, cant_stock_guardar,
                        cant_pend_oc_verano, cant_pend_oc_invierno, cant_pend_oc_atemporal,
                        stock_cobertura, costo_prom, inc_fob, vcosto,
                        temporada_base_verano, temporada_base_invierno
                    ) OUTPUT INSERTED.id VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        } else {
            $sql = "INSERT INTO RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO (
                        nombre_presupuesto, fecha_guardado, temporada, pais, rubro,
                        categoria_padre, stock_proyectado, indice_variacion_original,
                        indice_verano_variacion, venta_verano_anterior, venta_proyectada_verano,
                        indice_invierno_variacion, venta_invierno_anterior, venta_proyectada_invierno,
                        compra_proyectada
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        }

        $registrosGuardados = 0;

        foreach ($filas as $fila) {
            $rubro = $fila['rubro'] ?? null;
            $categoria = $fila['categoria_padre'] ?? null;

            // El reparto se calcula ANTES de armar la fila y de él salen los totales
            // de la fila, en vez de copiar los que mandó el navegador.
            //
            // El navegador recalcula la venta proyectada al editar un índice, así que
            // hasta ahora la versión guardaba números del cliente y —desde este cambio—
            // un reparto del servidor. Con dos orígenes, cualquier diferencia entre
            // ellos rompía el invariante y dejaba la versión incoherente consigo misma
            // justo en la tabla que lee el cashflow. Numéricamente da lo mismo (las dos
            // implementaciones usan la misma fórmula y los mismos días del servidor);
            // lo que cambia es que ahora no puede NO dar lo mismo.
            $calculo = $conTramos
                ? $this->totalesDesdeTramos($fila, $solapa, $fechaCalculo)
                : null;

            $params = [
                $nombre,
                $fecha,
                $solapa,
                $pais,
                $rubro,
                $categoria,
                // round() y no (int): el cast trunca, y el reparto redondea. Hoy los
                // componentes vienen enteros, pero si alguno trajera decimales el
                // stock guardado y el del reparto se separarían por una unidad.
                (int)round((float)($fila['stock_proyectado'] ?? 0)),
                (float)($fila['indice_variacion_original'] ?? 0),
                (float)($fila['indice_verano_variacion'] ?? 0),
                (int)($fila['venta_verano_anterior'] ?? 0),
                $calculo ? $calculo['venta_proy_verano'] : (int)($fila['venta_proyectada_verano'] ?? 0),
                (float)($fila['indice_invierno_variacion'] ?? 0),
                (int)($fila['venta_invierno_anterior'] ?? 0),
                $calculo ? $calculo['venta_proy_invierno'] : (int)($fila['venta_proyectada_invierno'] ?? 0),
                $calculo ? $calculo['compra_proyectada'] : (int)($fila['compra_proyectada'] ?? 0)
            ];

            if ($ampliado) {
                $clave = trim((string)$rubro) . '|' . trim((string)$categoria);
                $costo = $costos[$clave] ?? null;

                $params = array_merge($params, [
                    $idCabecera,
                    $this->intONull($fila, 'cant_stock'),
                    $this->intONull($fila, 'cant_stock_guardar'),
                    $this->intONull($fila, 'cant_pend_oc_verano'),
                    $this->intONull($fila, 'cant_pend_oc_invierno'),
                    $this->intONull($fila, 'cant_pend_oc_atemporal'),
                    $this->intONull($fila, 'stock_cobertura'),
                    // NULL y no 0 cuando no hay costo cargado: 0 se leeria como
                    // "cuesta cero" y falsearia cualquier valorizacion.
                    $costo ? (float)$costo['costo_prom'] : null,
                    $costo ? (float)$costo['inc_fob'] : null,
                    $costo ? (float)$costo['vcosto'] : null,
                    $fila['temporada_base_verano'] ?? null,
                    $fila['temporada_base_invierno'] ?? null
                ]);
            }

            $stmt = sqlsrv_query($this->cid, $sql, $params);
            if ($stmt === false) {
                throw new Exception("Error al insertar fila: " . print_r(sqlsrv_errors(), true));
            }

            if ($ampliado) {
                // Con OUTPUT el driver devuelve una fila, no un contador: el id se lee
                // acá porque es lo que enlaza los tramos con su fila de detalle.
                $idDetalle = (int)sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC)[0];
                $registrosGuardados++;
            } else {
                $idDetalle = null;
                $registrosGuardados += sqlsrv_rows_affected($stmt);
            }
            sqlsrv_free_stmt($stmt);

            if ($conTramos && $idDetalle) {
                $tramosGuardados += $this->insertarTramos(
                    $idCabecera, $idDetalle, $rubro, $categoria, $calculo['tramos']
                );
            }
        }

        return $registrosGuardados;
    }

    /**
     * Reparto por tramo de una fila del payload, y los totales que salen de él.
     *
     * Los totales se DERIVAN de los tramos en vez de venir del payload: así
     * venta_proyectada_verano es, por definición, la suma de los tramos de verano, y
     * el invariante de la tabla de tramos se cumple por construcción y no por
     * coincidencia entre dos implementaciones.
     *
     * De la fila se usan solo los datos de entrada —stock, bases, índices—, que no
     * son editables salvo los índices. Los resultados que mandó el navegador no se
     * copian.
     */
    private function totalesDesdeTramos($fila, $solapa, $fechaCalculo) {
        $indiceVerano = (float)($fila['indice_verano_variacion'] ?? 1.0);
        $indiceInvierno = (float)($fila['indice_invierno_variacion'] ?? $indiceVerano);
        $stock = (float)($fila['stock_proyectado'] ?? 0);

        $tramos = PresupuestoCalculos::calcularTramosDeFila(
            $stock,
            (float)($fila['venta_verano_anterior'] ?? 0),   $indiceVerano,
            (float)($fila['venta_invierno_anterior'] ?? 0), $indiceInvierno,
            $fechaCalculo,
            $solapa
        );

        $ventaVerano = 0;
        $ventaInvierno = 0;
        foreach ($tramos as $t) {
            if ($t['temporada_tipo'] === 'VERANO') {
                $ventaVerano += $t['venta_proyectada'];
            } else {
                $ventaInvierno += $t['venta_proyectada'];
            }
        }

        return [
            'tramos' => $tramos,
            'venta_proy_verano' => (int)$ventaVerano,
            'venta_proy_invierno' => (int)$ventaInvierno,
            'compra_proyectada' => (int)(round($stock) - $ventaVerano - $ventaInvierno)
        ];
    }

    /** Escribe las filas de tramo ya calculadas. */
    private function insertarTramos($idCabecera, $idDetalle, $rubro, $categoria, $tramos) {
        $sql = "INSERT INTO RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO (
                    id_cabecera, id_detalle, rubro, categoria_padre, orden,
                    temporada_codigo, temporada_tipo, temporada_desde, temporada_hasta,
                    es_resto, es_objetivo, es_comprable,
                    venta_proyectada, stock_aplicado, compra, compra_deficit_cobertura
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $guardados = 0;
        foreach ($tramos as $t) {
            $stmt = sqlsrv_query($this->cid, $sql, [
                $idCabecera, $idDetalle, $rubro, $categoria, $t['orden'],
                $t['temporada_codigo'], $t['temporada_tipo'], $t['temporada_desde'], $t['temporada_hasta'],
                $t['es_resto'] ? 1 : 0, $t['es_objetivo'] ? 1 : 0, $t['es_comprable'] ? 1 : 0,
                $t['venta_proyectada'], $t['stock_aplicado'], $t['compra'], $t['compra_deficit_cobertura']
            ]);
            if ($stmt === false) {
                throw new Exception("Error al insertar el tramo {$t['temporada_codigo']}: " . print_r(sqlsrv_errors(), true));
            }
            sqlsrv_free_stmt($stmt);
            $guardados++;
        }

        return $guardados;
    }

    /**
     * Indica si la base ya tiene la tabla de tramos (script 03).
     *
     * Se consulta igual que detalleAmpliado() y por el mismo motivo: los scripts se
     * corren por base y por país, y guardar tiene que seguir funcionando en la que
     * todavía no se migró, solo que sin el reparto.
     */
    private function hayTramos() {
        if ($this->cacheHayTramos !== null) {
            return $this->cacheHayTramos;
        }

        $stmt = sqlsrv_query($this->cid,
            "SELECT CASE WHEN OBJECT_ID('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO','U') IS NULL
                         THEN 0 ELSE 1 END AS existe");
        if ($stmt === false) {
            return $this->cacheHayTramos = false;
        }
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);

        return $this->cacheHayTramos = (bool)$row['existe'];
    }

    /**
     * Indica si el detalle ya tiene las columnas de la fase 2.
     *
     * Se cachea en la INSTANCIA y no en un static de funcion: sin cache se
     * consultaba el catalogo una vez por fila insertada, pero un static lo habria
     * compartido entre instancias, y esta clase esta pensada para poder apuntarse a
     * otra base en las pruebas —donde la respuesta es distinta— sin duplicar la
     * logica. Se toma id_cabecera como testigo porque las columnas se agregan todas
     * juntas en el mismo bloque del script 01.
     */
    private function detalleAmpliado() {
        if ($this->cacheDetalleAmpliado !== null) {
            return $this->cacheDetalleAmpliado;
        }

        $stmt = sqlsrv_query($this->cid,
            "SELECT CASE WHEN COL_LENGTH('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO','id_cabecera') IS NULL
                         THEN 0 ELSE 1 END AS existe");
        if ($stmt === false) {
            return $this->cacheDetalleAmpliado = false;
        }
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);

        return $this->cacheDetalleAmpliado = (bool)$row['existe'];
    }

    /** Entero del payload, o NULL si el campo no vino (no 0: 0 es un valor real). */
    private function intONull($fila, $campo) {
        return isset($fila[$campo]) && $fila[$campo] !== '' ? (int)$fila[$campo] : null;
    }

    /**
     * Costos vigentes por rubro/categoria, indexados como en el resto de la app.
     * Viven en la base `sistemas`, no en la del historial, y son unicos para los
     * dos paises (asi los lee tambien el circuito de distribucion).
     */
    private function obtenerCostos() {
        try {
            $cp = new CostoProyeccion();
            $params = $cp->obtenerParametrosGlobales();
            return is_array($params) ? $params : [];
        } catch (Exception $e) {
            // No se aborta el guardado: sin costo la version igual sirve, solo
            // pierde la valorizacion. Queda el log para poder detectarlo.
            error_log("No se pudieron leer los costos al guardar el presupuesto: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca en el historial de presupuestos guardados.
     *
     * @param array $filtros Los filtros a aplicar en la búsqueda.
     * @return array Resultado de la búsqueda.
     */
    public function buscarHistorial($filtros) {
        if (!$this->cid) {
            return ['success' => false, 'message' => 'Error de conexión a la base de datos.'];
        }

        try {
            $conCabecera = $this->hayCabecera();

            // Se listan las columnas del detalle explicitamente en vez de SELECT *
            // para que agregar una columna nueva no se filtre sola al front.
            $campos = "d.id, d.nombre_presupuesto, d.fecha_guardado, d.temporada, d.pais,
                       d.rubro, d.categoria_padre, d.stock_proyectado,
                       d.venta_verano_anterior, d.venta_proyectada_verano,
                       d.venta_invierno_anterior, d.venta_proyectada_invierno,
                       d.compra_proyectada, d.indice_variacion_original,
                       d.indice_verano_variacion, d.indice_invierno_variacion";

            if ($conCabecera) {
                $campos .= ", c.id AS id_cabecera, c.temporada_objetivo,
                             c.temporada_objetivo_desde, c.temporada_objetivo_hasta,
                             c.es_completa, c.es_oficial, c.filas_guardadas, c.filas_totales,
                             c.periodo_venta_verano_etiqueta, c.periodo_venta_invierno_etiqueta,
                             c.usuario_guardado";
                if ($this->cabeceraConEstadoTramos()) {
                    // Sin esto, una versión sin reparto no se distingue en pantalla de
                    // una que sí lo tiene pero cuyas filas todavía no se leyeron.
                    $campos .= ", c.tramos_estado, c.tramos_observacion";
                }
                $sql = "SELECT $campos
                        FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO d
                        LEFT JOIN RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA c ON c.id = d.id_cabecera";
            } else {
                $sql = "SELECT $campos FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO d";
            }

            $where = [];
            $params = [];

            // Filtro por término de búsqueda (en rubro y categoría)
            if (!empty($filtros['termino'])) {
                $where[] = "(d.rubro LIKE ? OR d.categoria_padre LIKE ?)";
                $params[] = '%' . $filtros['termino'] . '%';
                $params[] = '%' . $filtros['termino'] . '%';
            }

            // Filtro por versión guardada: es el que permite mirar un solo
            // presupuesto en vez de todo el historial mezclado. Por id cuando hay
            // cabecera y por nombre para las versiones que todavía no la tienen.
            if (!empty($filtros['id_cabecera']) && $conCabecera) {
                $where[] = "d.id_cabecera = ?";
                $params[] = (int)$filtros['id_cabecera'];
            } elseif (!empty($filtros['nombre_presupuesto'])) {
                $where[] = "d.nombre_presupuesto = ?";
                $params[] = $filtros['nombre_presupuesto'];
            }

            // Filtro por rubro específico
            if (!empty($filtros['rubro'])) {
                $where[] = "d.rubro = ?";
                $params[] = $filtros['rubro'];
            }

            // Filtro por categoría específica
            if (!empty($filtros['categoria'])) {
                $where[] = "d.categoria_padre = ?";
                $params[] = $filtros['categoria'];
            }

            // Filtro por rango de fechas
            if (!empty($filtros['fecha_desde'])) {
                $where[] = "d.fecha_guardado >= ?";
                $params[] = $filtros['fecha_desde'];
            }
            if (!empty($filtros['fecha_hasta'])) {
                // Agregamos un día para incluir todo el día de la fecha hasta
                $fechaHasta = new DateTime($filtros['fecha_hasta']);
                $fechaHasta->modify('+1 day');
                $where[] = "d.fecha_guardado < ?";
                $params[] = $fechaHasta->format('Y-m-d');
            }

            // Solo las versiones oficiales, para el consumidor que quiere la vigente.
            if ($conCabecera && !empty($filtros['solo_oficiales'])) {
                $where[] = "c.es_oficial = 1";
            }

            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }

            $sql .= " ORDER BY d.fecha_guardado DESC";

            $stmt = sqlsrv_query($this->cid, $sql, $params);
            if ($stmt === false) {
                throw new Exception("Error al buscar en el historial: " . print_r(sqlsrv_errors(), true));
            }

            $resultados = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $resultados[] = $this->completarTemporadaObjetivo($row);
            }
            sqlsrv_free_stmt($stmt);

            $resultados = $this->adjuntarTramos($resultados);

            return ['success' => true, 'data' => $resultados];

        } catch (Exception $e) {
            error_log("Error en buscarHistorial: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al buscar en el historial: ' . $e->getMessage()];
        }
    }

    /**
     * Agrega a cada fila del historial su reparto por tramo.
     *
     * En UNA consulta para todas las filas y no una por fila: el historial trae
     * cientos de filas y consultarlas de a una multiplicaba los viajes a la base por
     * el tamaño del resultado. Se filtra por los id de detalle que ya se leyeron, que
     * es lo que mantiene la consulta acotada al mismo universo que se va a mostrar.
     */
    private function adjuntarTramos($filas) {
        if (empty($filas) || !$this->hayTramos()) {
            return $filas;
        }

        $ids = [];
        foreach ($filas as $f) {
            if (!empty($f['id'])) {
                $ids[] = (int)$f['id'];
            }
        }
        if (empty($ids)) {
            return $filas;
        }

        // Los id son enteros propios, ya casteados: se interpolan porque un IN con
        // parámetros necesita un placeholder por valor y acá pueden ser cientos.
        $lista = implode(',', $ids);
        $stmt = sqlsrv_query($this->cid,
            "SELECT id_detalle, orden, temporada_codigo, temporada_tipo,
                    temporada_desde, temporada_hasta, es_resto, es_objetivo, es_comprable,
                    venta_proyectada, stock_aplicado, compra, compra_deficit_cobertura
               FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO
              WHERE id_detalle IN ($lista)
              ORDER BY id_detalle, orden");
        if ($stmt === false) {
            // El historial sirve igual sin el desglose, así que no se aborta la
            // búsqueda entera por esto: queda el log para poder detectarlo.
            error_log('No se pudieron leer los tramos del historial: ' . print_r(sqlsrv_errors(), true));
            return $filas;
        }

        $porDetalle = [];
        while ($t = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            foreach (['temporada_desde', 'temporada_hasta'] as $k) {
                if ($t[$k] instanceof DateTime) {
                    $t[$k] = $t[$k]->format('Y-m-d');
                }
            }
            foreach (['es_resto', 'es_objetivo', 'es_comprable'] as $k) {
                $t[$k] = (bool)$t[$k];
            }
            $porDetalle[(int)$t['id_detalle']][] = $t;
        }
        sqlsrv_free_stmt($stmt);

        foreach ($filas as &$f) {
            $f['tramos'] = $porDetalle[(int)($f['id'] ?? 0)] ?? [];
        }
        unset($f);

        return $filas;
    }

    /** Indica si la cabecera ya tiene las columnas de estado del reparto (script 03). */
    private function cabeceraConEstadoTramos() {
        if ($this->cacheEstadoTramos !== null) {
            return $this->cacheEstadoTramos;
        }

        $stmt = sqlsrv_query($this->cid,
            "SELECT CASE WHEN COL_LENGTH('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA','tramos_estado') IS NULL
                         THEN 0 ELSE 1 END AS existe");
        if ($stmt === false) {
            return $this->cacheEstadoTramos = false;
        }
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);

        return $this->cacheEstadoTramos = (bool)$row['existe'];
    }

    /**
     * Lista las versiones guardadas (una fila por version, no por rubro).
     * Es la vista sobre la que se marca la version oficial.
     */
    public function listarVersiones($filtros = []) {
        if (!$this->cid) {
            return ['success' => false, 'message' => 'Error de conexión a la base de datos.'];
        }

        // Sin cabecera todavía se listan las versiones agrupando el detalle: así
        // el panel y el filtro por versión sirven desde el primer día, antes de
        // correr los scripts. Lo que no se puede en ese modo es marcar oficial.
        if (!$this->hayCabecera()) {
            return $this->listarVersionesSinCabecera();
        }

        try {
            $sql = "SELECT c.*, (SELECT COUNT(*) FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO d
                                  WHERE d.id_cabecera = c.id) AS filas_detalle
                    FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA c";
            $where = [];
            $params = [];

            if (!empty($filtros['temporada_objetivo'])) {
                $where[] = "c.temporada_objetivo = ?";
                $params[] = $filtros['temporada_objetivo'];
            }
            if (!empty($filtros['solo_oficiales'])) {
                $where[] = "c.es_oficial = 1";
            }
            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }
            $sql .= " ORDER BY c.fecha_guardado DESC";

            $stmt = sqlsrv_query($this->cid, $sql, $params);
            if ($stmt === false) {
                throw new Exception("Error al listar versiones: " . print_r(sqlsrv_errors(), true));
            }

            $data = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                foreach ($row as $k => $v) {
                    if ($v instanceof DateTime) {
                        $row[$k] = $v->format('Y-m-d H:i:s');
                    }
                }
                $data[] = $row;
            }
            sqlsrv_free_stmt($stmt);

            return ['success' => true, 'data' => $data];

        } catch (Exception $e) {
            error_log("Error en listarVersiones: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al listar versiones: ' . $e->getMessage()];
        }
    }

    /**
     * Versiones deducidas del detalle, para cuando la base todavia no tiene
     * cabecera. Mismas claves que listarVersiones() para que el front no tenga
     * que distinguir un caso del otro.
     */
    private function listarVersionesSinCabecera() {
        $sql = "SELECT nombre_presupuesto,
                       MAX(temporada) AS solapa,
                       MAX(pais) AS pais,
                       MIN(fecha_guardado) AS fecha_guardado,
                       COUNT(*) AS filas_detalle,
                       COUNT(DISTINCT ISNULL(rubro,'') + '|' + ISNULL(categoria_padre,'')) AS filas_guardadas
                  FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO
                 GROUP BY nombre_presupuesto
                 ORDER BY MIN(fecha_guardado) DESC";

        $stmt = sqlsrv_query($this->cid, $sql);
        if ($stmt === false) {
            return ['success' => false, 'message' => print_r(sqlsrv_errors(), true)];
        }

        $data = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if ($row['fecha_guardado'] instanceof DateTime) {
                $row['fecha_guardado'] = $row['fecha_guardado']->format('Y-m-d H:i:s');
            }

            $row['id'] = null;               // no hay cabecera a la que apuntar
            $row['es_oficial'] = 0;
            $row['es_completa'] = null;      // no se puede saber sin la cabecera
            $row['filas_totales'] = null;
            $row['oficial_usuario'] = null;
            $row['oficial_fecha'] = null;

            $periodos = $this->periodosDe($row['fecha_guardado'], $row['solapa']);
            $row['temporada_objetivo'] = $periodos ? $periodos['objetivo']['codigo'] : null;
            $row['temporada_objetivo_desde'] = $periodos ? $periodos['objetivo']['desde'] : null;
            $row['temporada_objetivo_hasta'] = $periodos ? $periodos['objetivo']['hasta'] : null;
            $row['temporada_objetivo_derivada'] = true;

            $data[] = $row;
        }
        sqlsrv_free_stmt($stmt);

        return [
            'success' => true,
            'data' => $data,
            'sin_cabecera' => true,
            'message' => 'Todavía no se corrieron los scripts de presupuestos/sql/: '
                       . 'se puede filtrar y eliminar, pero no marcar una versión como oficial.'
        ];
    }

    /** Períodos de una versión a partir de su fecha y su solapa. */
    private function periodosDe($fecha, $solapa) {
        if (!$fecha || !$solapa) {
            return null;
        }
        try {
            return PresupuestoCalculos::obtenerPeriodosProyeccion(
                substr((string)$fecha, 0, 10),
                strtolower($solapa) === 'invierno' ? 'invierno' : 'verano'
            );
        } catch (Exception $e) {
            error_log('No se pudo derivar la temporada objetivo: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Elimina una version guardada: su cabecera, su detalle y su log.
     *
     * En dos pasos, igual que marcarOficial(): sin `confirmado` no borra nada y
     * devuelve que se llevaria puesto, para que la UI lo muestre antes. Es la
     * unica operacion destructiva del modulo.
     *
     * Acepta id de cabecera o nombre, porque las versiones anteriores a la fase 2
     * —y las de una base donde todavia no se corrieron los scripts— no tienen
     * cabecera y solo se pueden identificar por nombre.
     */
    public function eliminarVersion($idCabecera = null, $nombrePresupuesto = null, $confirmado = false) {
        if (!$this->cid) {
            return ['success' => false, 'message' => 'Error de conexión a la base de datos.'];
        }

        $conCabecera = $this->hayCabecera();
        $cabecera = null;

        if ($conCabecera && $idCabecera) {
            $stmt = sqlsrv_query($this->cid,
                "SELECT id, nombre_presupuesto, temporada_objetivo, es_oficial, pais
                   FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA WHERE id = ?", [(int)$idCabecera]);
            if ($stmt === false) {
                return ['success' => false, 'message' => print_r(sqlsrv_errors(), true)];
            }
            $cabecera = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);

            if (!$cabecera) {
                return ['success' => false, 'message' => 'No existe la versión indicada.'];
            }
            $nombrePresupuesto = $cabecera['nombre_presupuesto'];
        }

        if (empty($nombrePresupuesto)) {
            return ['success' => false, 'message' => 'Falta indicar qué versión eliminar.'];
        }

        // Cuántas filas de detalle se van a borrar. Se cuenta por cabecera cuando
        // la hay, y por nombre cuando no, que es como están agrupadas las viejas.
        if ($cabecera) {
            $stmt = sqlsrv_query($this->cid,
                "SELECT COUNT(*) AS n FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO
                  WHERE id_cabecera = ?", [(int)$cabecera['id']]);
        } else {
            $stmt = sqlsrv_query($this->cid,
                "SELECT COUNT(*) AS n FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO
                  WHERE nombre_presupuesto = ?", [$nombrePresupuesto]);
        }
        if ($stmt === false) {
            return ['success' => false, 'message' => print_r(sqlsrv_errors(), true)];
        }
        $filas = (int)sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['n'];
        sqlsrv_free_stmt($stmt);

        if ($filas === 0 && !$cabecera) {
            return ['success' => false, 'message' => 'No se encontró esa versión.'];
        }

        $esOficial = $cabecera ? ((int)$cabecera['es_oficial'] === 1) : false;

        if (!$confirmado) {
            return [
                'success' => true,
                'requiere_confirmacion' => true,
                'version' => [
                    'id_cabecera' => $cabecera ? (int)$cabecera['id'] : null,
                    'nombre' => $nombrePresupuesto,
                    'filas' => $filas,
                    'es_oficial' => $esOficial,
                    'temporada_objetivo' => $cabecera['temporada_objetivo'] ?? null
                ]
            ];
        }

        try {
            if (sqlsrv_begin_transaction($this->cid) === false) {
                throw new Exception("No se pudo iniciar la transacción: " . print_r(sqlsrv_errors(), true));
            }

            // Orden obligado por las claves foráneas: primero lo que apunta a la
            // cabecera, después la cabecera.
            if ($cabecera) {
                $this->ejecutar("DELETE FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO WHERE id_cabecera = ?",
                    [(int)$cabecera['id']], 'detalle');
                $this->ejecutar("DELETE FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_OFICIAL_LOG WHERE id_cabecera = ?",
                    [(int)$cabecera['id']], 'log de oficial');
                $this->ejecutar("DELETE FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA WHERE id = ?",
                    [(int)$cabecera['id']], 'cabecera');
            } else {
                // Sin cabecera: se borra por nombre, que es como se agrupan las
                // versiones viejas. Se excluyen las filas que ya tienen cabecera
                // para no llevarse por delante otra versión del mismo nombre.
                $where = $conCabecera ? " AND id_cabecera IS NULL" : "";
                $this->ejecutar("DELETE FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO
                                  WHERE nombre_presupuesto = ?" . $where,
                    [$nombrePresupuesto], 'detalle');
            }

            sqlsrv_commit($this->cid);

            return [
                'success' => true,
                'message' => "Se eliminó '{$nombrePresupuesto}' ({$filas} fila"
                           . ($filas === 1 ? '' : 's') . ")."
                           . ($esOficial ? ' Era la versión oficial: no quedó ninguna vigente para esa temporada.' : '')
            ];

        } catch (Exception $e) {
            @sqlsrv_rollback($this->cid);
            error_log("Error en eliminarVersion: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al eliminar la versión: ' . $e->getMessage()];
        }
    }

    /** Ejecuta una sentencia dentro de la transacción y aborta si falla. */
    private function ejecutar($sql, $params, $que) {
        $stmt = sqlsrv_query($this->cid, $sql, $params);
        if ($stmt === false) {
            throw new Exception("Al borrar el $que: " . print_r(sqlsrv_errors(), true));
        }
        sqlsrv_free_stmt($stmt);
    }

    /**
     * Marca una version como oficial para su pais y temporada objetivo.
     *
     * Desmarca la anterior de la misma combinacion en la MISMA transaccion: el
     * indice unico filtrado no permite dos oficiales a la vez, asi que hacerlo
     * en dos pasos sueltos fallaria a la mitad y dejaria la combinacion sin
     * ninguna vigente.
     *
     * @param int  $idCabecera  version a marcar
     * @param bool $confirmado  la UI ya mostro cual se va a reemplazar
     */
    public function marcarOficial($idCabecera, $confirmado = false) {
        if (!$this->cid) {
            return ['success' => false, 'message' => 'Error de conexión a la base de datos.'];
        }
        if (!$this->hayCabecera()) {
            return ['success' => false, 'message' => 'Esta base todavía no tiene la cabecera de versiones.'];
        }

        $idCabecera = (int)$idCabecera;

        try {
            $stmt = sqlsrv_query($this->cid,
                "SELECT id, nombre_presupuesto, pais, temporada_objetivo, es_completa, es_oficial
                   FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA WHERE id = ?", [$idCabecera]);
            if ($stmt === false) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }
            $version = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);

            if (!$version) {
                return ['success' => false, 'message' => 'No existe la versión indicada.'];
            }
            if ((int)$version['es_oficial'] === 1) {
                return ['success' => true, 'message' => 'Esa versión ya es la oficial.', 'sin_cambios' => true];
            }
            // Se rechaza en la aplicacion ademas del CHECK de la base, para poder
            // explicar el motivo en lugar de devolver un error de constraint.
            if ((int)$version['es_completa'] !== 1) {
                return [
                    'success' => false,
                    'message' => 'Una versión parcial no puede marcarse como oficial. '
                               . 'Volvé a guardar el presupuesto completo.'
                ];
            }

            // Cual se va a reemplazar.
            $stmt = sqlsrv_query($this->cid,
                "SELECT id, nombre_presupuesto, fecha_guardado
                   FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA
                  WHERE es_oficial = 1 AND pais = ? AND temporada_objetivo = ?",
                [$version['pais'], $version['temporada_objetivo']]);
            if ($stmt === false) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }
            $anterior = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);

            if ($anterior && $anterior['fecha_guardado'] instanceof DateTime) {
                $anterior['fecha_guardado'] = $anterior['fecha_guardado']->format('Y-m-d H:i');
            }

            // Sin confirmar no se escribe: se devuelve que se va a reemplazar
            // para que la UI lo muestre y vuelva a llamar con confirmado = true.
            if (!$confirmado) {
                return [
                    'success' => true,
                    'requiere_confirmacion' => true,
                    'version' => [
                        'id' => (int)$version['id'],
                        'nombre' => $version['nombre_presupuesto'],
                        'temporada_objetivo' => $version['temporada_objetivo'],
                        'pais' => $version['pais']
                    ],
                    'reemplaza' => $anterior ? [
                        'id' => (int)$anterior['id'],
                        'nombre' => $anterior['nombre_presupuesto'],
                        'fecha_guardado' => $anterior['fecha_guardado']
                    ] : null
                ];
            }

            if (sqlsrv_begin_transaction($this->cid) === false) {
                throw new Exception("No se pudo iniciar la transacción: " . print_r(sqlsrv_errors(), true));
            }

            $usuario = $this->usuarioActual();

            if ($anterior) {
                $up = sqlsrv_query($this->cid,
                    "UPDATE RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA
                        SET es_oficial = 0
                      WHERE id = ?", [(int)$anterior['id']]);
                if ($up === false) {
                    throw new Exception("Al desmarcar la anterior: " . print_r(sqlsrv_errors(), true));
                }
                sqlsrv_free_stmt($up);

                $this->registrarLogOficial(
                    (int)$anterior['id'], 'DESMARCAR', $version['pais'], $version['temporada_objetivo'],
                    $idCabecera, $usuario,
                    "Reemplazada por la versión {$idCabecera} ({$version['nombre_presupuesto']})"
                );
            }

            $up = sqlsrv_query($this->cid,
                "UPDATE RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA
                    SET es_oficial = 1, oficial_usuario = ?, oficial_fecha = GETDATE()
                  WHERE id = ?", [$usuario, $idCabecera]);
            if ($up === false) {
                throw new Exception("Al marcar la nueva: " . print_r(sqlsrv_errors(), true));
            }
            sqlsrv_free_stmt($up);

            $this->registrarLogOficial(
                $idCabecera, 'MARCAR', $version['pais'], $version['temporada_objetivo'],
                $anterior ? (int)$anterior['id'] : null, $usuario,
                $anterior ? "Reemplaza a la versión {$anterior['id']} ({$anterior['nombre_presupuesto']})" : 'Primera versión oficial de la combinación'
            );

            sqlsrv_commit($this->cid);

            return [
                'success' => true,
                'message' => "'{$version['nombre_presupuesto']}' es la versión oficial de "
                           . "{$version['temporada_objetivo']} ({$version['pais']})."
                           . ($anterior ? " Se desmarcó '{$anterior['nombre_presupuesto']}'." : ''),
                'id_cabecera' => $idCabecera,
                'desmarcada' => $anterior ? (int)$anterior['id'] : null
            ];

        } catch (Exception $e) {
            @sqlsrv_rollback($this->cid);
            error_log("Error en marcarOficial: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al marcar la versión oficial: ' . $e->getMessage()];
        }
    }

    /** Una fila del log por cada marcado y cada desmarcado. */
    private function registrarLogOficial($idCabecera, $accion, $pais, $temporada, $idReemplazada, $usuario, $observacion) {
        $stmt = sqlsrv_query($this->cid,
            "INSERT INTO RO_T_HISTORIAL_COMPRAS_PROYECTADAS_OFICIAL_LOG
                (id_cabecera, accion, pais, temporada_objetivo, id_cabecera_reemplazada, usuario, observacion)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$idCabecera, $accion, $pais, $temporada, $idReemplazada, $usuario, $observacion]);

        if ($stmt === false) {
            throw new Exception("Al registrar el log de oficial: " . print_r(sqlsrv_errors(), true));
        }
        sqlsrv_free_stmt($stmt);
    }

    /** Historial de marcados de una combinación. */
    public function historialOficial($temporadaObjetivo = null) {
        if (!$this->cid || !$this->hayCabecera()) {
            return ['success' => true, 'data' => []];
        }

        $sql = "SELECT l.*, c.nombre_presupuesto
                  FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_OFICIAL_LOG l
                  LEFT JOIN RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA c ON c.id = l.id_cabecera";
        $params = [];
        if ($temporadaObjetivo) {
            $sql .= " WHERE l.temporada_objetivo = ?";
            $params[] = $temporadaObjetivo;
        }
        $sql .= " ORDER BY l.fecha DESC, l.id DESC";

        $stmt = sqlsrv_query($this->cid, $sql, $params);
        if ($stmt === false) {
            return ['success' => false, 'message' => print_r(sqlsrv_errors(), true)];
        }

        $data = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if ($row['fecha'] instanceof DateTime) {
                $row['fecha'] = $row['fecha']->format('Y-m-d H:i');
            }
            $data[] = $row;
        }
        sqlsrv_free_stmt($stmt);

        return ['success' => true, 'data' => $data];
    }

    /**
     * Completa la temporada objetivo de una fila del historial.
     *
     * Si la fila ya tiene cabecera, se usa la guardada. Si no —versiones viejas
     * todavia sin migrar— se deriva de la fecha de guardado y de la solapa, que
     * es lo que uso el calculo en su momento. El calculo sale de
     * PresupuestoCalculos para no tener una segunda definicion de temporada.
     */
    private function completarTemporadaObjetivo($row) {
        foreach ($row as $k => $v) {
            if ($v instanceof DateTime) {
                $row[$k] = $v->format('Y-m-d H:i:s');
            }
        }

        if (!empty($row['temporada_objetivo'])) {
            $row['temporada_objetivo_derivada'] = false;
            if ($row['temporada_objetivo_desde'] instanceof DateTime) {
                $row['temporada_objetivo_desde'] = $row['temporada_objetivo_desde']->format('Y-m-d');
            }
            return $row;
        }

        $row['temporada_objetivo'] = null;
        $row['temporada_objetivo_desde'] = null;
        $row['temporada_objetivo_hasta'] = null;
        $row['temporada_objetivo_derivada'] = true;

        $fecha = $row['fecha_guardado'] ?? null;
        if (!$fecha || empty($row['temporada'])) {
            return $row;
        }

        try {
            $periodos = PresupuestoCalculos::obtenerPeriodosProyeccion(
                substr((string)$fecha, 0, 10),
                strtolower($row['temporada']) === 'invierno' ? 'invierno' : 'verano'
            );
            $row['temporada_objetivo'] = $periodos['objetivo']['codigo'];
            $row['temporada_objetivo_desde'] = $periodos['objetivo']['desde'];
            $row['temporada_objetivo_hasta'] = $periodos['objetivo']['hasta'];
        } catch (Exception $e) {
            error_log('No se pudo derivar la temporada objetivo del historial: ' . $e->getMessage());
        }

        return $row;
    }

    public function __destruct() {
        if ($this->cid) {
            sqlsrv_close($this->cid);
        }
    }
}

