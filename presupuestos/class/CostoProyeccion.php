<?php
require_once __DIR__.'/../../Class/conexion.php';

class CostoProyeccion {
    private $cid_sistemas;

    public function __construct() {
        $conexion = new Conexion();
        $this->cid_sistemas = $conexion->conectar('apps');
    }

    /**
     * Guardar costos en FP_T_COSTOS_PROYECCION
     */
    public function guardarCostos($filas) {
        try {
            if (!$this->cid_sistemas) {
                throw new Exception("Error de conexión a la base de datos");
            }

            if (sqlsrv_begin_transaction($this->cid_sistemas) === false) {
                throw new Exception("Error al iniciar transacción: " . print_r(sqlsrv_errors(), true));
            }

            $fechaGuardado = date('Y-m-d H:i:s');
            
            // SQL para guardar
            $sqlInsert = "INSERT INTO dbo.FP_T_COSTOS_PROYECCION (
                            fecha_guardado, pais, temporada, nombre_distribucion, rubro, categoria_padre,
                            costo_prom, inc_fob, vcosto
                          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            foreach ($filas as $fila) {
                // Normalizar todas las claves a minúsculas
                $fila = array_change_key_case($fila, CASE_LOWER);
                
                $nombreDist = !empty($fila['nombre_distribucion']) ? trim($fila['nombre_distribucion']) : 'Por defecto';
                $paisLimpio = strtolower(trim($fila['pais'] ?? ''));
                
                // Borrar anterior idéntico
                $sqlDelete = "DELETE FROM dbo.FP_T_COSTOS_PROYECCION 
                              WHERE pais = ? AND temporada = ? AND nombre_distribucion = ? AND rubro = ? AND categoria_padre = ?";
                $deleteParams = [
                    $paisLimpio,
                    $fila['temporada'],
                    $nombreDist,
                    $fila['rubro'],
                    $fila['categoria_padre']
                ];
                $deleteStmt = sqlsrv_query($this->cid_sistemas, $sqlDelete, $deleteParams);
                if ($deleteStmt === false) {
                    sqlsrv_rollback($this->cid_sistemas);
                    throw new Exception("Error al limpiar costo anterior: " . print_r(sqlsrv_errors(), true));
                }
                sqlsrv_free_stmt($deleteStmt);

                // Insertar el nuevo
                $insertParams = [
                    $fechaGuardado,
                    $paisLimpio,
                    $fila['temporada'],
                    $nombreDist,
                    $fila['rubro'],
                    $fila['categoria_padre'],
                    (float)$fila['costo_prom'],
                    (float)$fila['inc_fob'],
                    (float)$fila['vcosto']
                ];

                $insertStmt = sqlsrv_query($this->cid_sistemas, $sqlInsert, $insertParams);
                if ($insertStmt === false) {
                    sqlsrv_rollback($this->cid_sistemas);
                    throw new Exception("Error al insertar costo: " . print_r(sqlsrv_errors(), true));
                }
                sqlsrv_free_stmt($insertStmt);
            }

            sqlsrv_commit($this->cid_sistemas);
            return true;
        } catch (Exception $e) {
            error_log("Error en CostoProyeccion::guardarCostos: " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener costos previamente guardados
     */
    public function obtenerCostosGuardados($pais, $temporada, $nombreDistribucion) {
        try {
            if (!$this->cid_sistemas) {
                return [];
            }

            $pais = strtolower(trim($pais));
            $sql = "SELECT * FROM dbo.FP_T_COSTOS_PROYECCION 
                    WHERE pais = ? AND temporada = ? AND nombre_distribucion = ?
                    ORDER BY rubro, categoria_padre";
            
            $params = [$pais, $temporada, $nombreDistribucion];
            $stmt = sqlsrv_query($this->cid_sistemas, $sql, $params);
            
            if ($stmt === false) {
                throw new Exception("Error al obtener costos guardados: " . print_r(sqlsrv_errors(), true));
            }
            
            $costos = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $clave = trim($row['rubro']) . '|' . trim($row['categoria_padre']);
                $costos[$clave] = [
                    'costo_prom' => (float)$row['costo_prom'],
                    'inc_fob' => (float)$row['inc_fob'],
                    'vcosto' => (float)$row['vcosto']
                ];
            }
            sqlsrv_free_stmt($stmt);
            return $costos;
        } catch (Exception $e) {
            error_log("Error en CostoProyeccion::obtenerCostosGuardados: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener el tipo de cambio oficial vendedor actual (hoy)
     */
    public function obtenerTipoCambioHoy() {
        try {
            if (!$this->cid_sistemas) {
                return 1500.0; // Fallback prudencial
            }
            // Buscar última cotización vendedora del BCRA
            $sql = "SELECT TOP 1 Vendedor FROM dbo.dolar_oficial_bcra ORDER BY Fecha DESC";
            $stmt = sqlsrv_query($this->cid_sistemas, $sql);
            if ($stmt !== false) {
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                sqlsrv_free_stmt($stmt);
                if (!empty($row['Vendedor']) && (float)$row['Vendedor'] > 0) {
                    return (float)$row['Vendedor'];
                }
            }
            return 1500.0;
        } catch (Exception $e) {
            error_log("Error en CostoProyeccion::obtenerTipoCambioHoy: " . $e->getMessage());
            return 1500.0;
        }
    }

    /**
     * Calcular y sincronizar automáticamente COSTO PROM (U$D) ejecutando
     * RO_SP_COSTO_MEDIANA_RUBRO_CATEGORIA en XL-TANGO -> LAKER_SA (últimos 90 días).
     * Convierte los costos de ARS a USD con la cotización de hoy y actualiza FP_T_COSTOS_PARAMETROS.
     */
    public function sincronizarCostoMediana90Dias() {
        try {
            $conexion = new Conexion();
            $cidCentral = $conexion->conectar('central'); // XL-TANGO -> LAKER_SA

            if (!$cidCentral) {
                throw new Exception("No se pudo conectar a la base central (XL-TANGO -> LAKER_SA).");
            }

            $tcHoy = $this->obtenerTipoCambioHoy();
            if ($tcHoy <= 0) {
                $tcHoy = 1500.0;
            }

            $desde = date('Y-m-d', strtotime('-90 days'));
            $hasta = date('Y-m-d');

            // Ejecutar el SP de mediana
            $sql = "EXEC dbo.RO_SP_COSTO_MEDIANA_RUBRO_CATEGORIA @DESDE = ?, @HASTA = ?, @DETALLE = 0";
            $params = [$desde, $hasta];
            $stmt = sqlsrv_query($cidCentral, $sql, $params);

            if ($stmt === false) {
                throw new Exception("Error al ejecutar RO_SP_COSTO_MEDIANA_RUBRO_CATEGORIA: " . print_r(sqlsrv_errors(), true));
            }

            // Agrupar filas por Rubro y Categoría
            // Para cada combinación rubro|categoría, recolectar las medianas de cada canal/mes
            $agrupado = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rubro = trim($row['RUBRO'] ?? '');
                $cat = trim($row['CATEGORIA'] ?? '');
                $costoARS = (float)($row['COSTO'] ?? 0);
                if ($rubro === '' || $cat === '' || $costoARS <= 0) continue;

                $clave = "$rubro|$cat";
                if (!isset($agrupado[$clave])) {
                    $agrupado[$clave] = [
                        'rubro' => $rubro,
                        'categoria_padre' => $cat,
                        'costos' => []
                    ];
                }
                $agrupado[$clave]['costos'][] = $costoARS;
            }
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($cidCentral);

            if (empty($agrupado)) {
                throw new Exception("El procedimiento de mediana no devolvió datos para los últimos 90 días.");
            }

            // Obtener los parámetros globales actuales para conservar INC_FOB y Markups existentes
            $paramsActuales = $this->obtenerParametrosGlobales();

            $filasParaGuardar = [];
            foreach ($agrupado as $clave => $item) {
                $rubro = $item['rubro'];
                $cat = $item['categoria_padre'];
                $costosLista = $item['costos'];
                sort($costosLista);
                $n = count($costosLista);
                $medianaARS = ($n % 2 === 0) 
                    ? ($costosLista[$n / 2 - 1] + $costosLista[$n / 2]) / 2 
                    : $costosLista[floor($n / 2)];

                // Conversión a Dólares con el tipo de cambio de hoy
                $costoUSD = round($medianaARS / $tcHoy, 2);

                // Conservar markups existentes si los tiene; inc_fob queda en 0 porque
                // RO_SP_COSTO_MEDIANA_RUBRO_CATEGORIA ya devuelve el costo nacionalizado (1 + COSTO_NAC)
                $existente = $paramsActuales[$clave] ?? null;
                $incFob = 0.0;
                $vcosto = $costoUSD;

                $filasParaGuardar[] = [
                    'rubro' => $rubro,
                    'categoria_padre' => $cat,
                    'costo_prom' => $costoUSD,
                    'inc_fob' => $incFob,
                    'vcosto' => $vcosto,
                    'markup_locales_propios' => $existente ? (float)$existente['markup_locales_propios'] : 0.0,
                    'markup_franquicias' => $existente ? (float)$existente['markup_franquicias'] : 0.0,
                    'markup_mayoristas' => $existente ? (float)$existente['markup_mayoristas'] : 0.0,
                    'markup_ecommerce' => $existente ? (float)$existente['markup_ecommerce'] : 0.0
                ];
            }

            // Guardar en la tabla FP_T_COSTOS_PARAMETROS
            $resGuardar = $this->guardarParametrosGlobales($filasParaGuardar);
            if ($resGuardar !== true) {
                throw new Exception($resGuardar['mensaje'] ?? 'Error al guardar parámetros calculados.');
            }

            return [
                'success' => true,
                'total_actualizados' => count($filasParaGuardar),
                'tc_utilizado' => $tcHoy,
                'periodo' => "$desde a $hasta"
            ];
        } catch (Exception $e) {
            error_log("Error en CostoProyeccion::sincronizarCostoMediana90Dias: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Extrae el número de mes (1..12) desde claves 'M-AAAA' (ej. '2-2026')
     * o etiquetas tipo 'Feb 27', 'Ene 27', 'Ago 26'.
     */
    public static function extraerNumeroMes($clave) {
        $s = trim((string)$clave);
        if ($s === '') return 0;
        if (preg_match('/^(\d{1,2})[-\\/]\d{4}$/', $s, $m)) {
            $n = (int)$m[1];
            return ($n >= 1 && $n <= 12) ? $n : 0;
        }
        $mapa = [
            'ENE' => 1, 'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'ABR' => 4, 'APR' => 4,
            'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AGO' => 8, 'AUG' => 8,
            'SEP' => 9, 'SET' => 9, 'OCT' => 10, 'NOV' => 11, 'DIC' => 12, 'DEC' => 12
        ];
        $pref = strtoupper(substr($s, 0, 3));
        return $mapa[$pref] ?? 0;
    }

    /**
     * Resuelve la última temporada cerrada equivalente del año anterior
     * ('INVIERNO': 01/02..31/07 | 'VERANO': 01/08..31/01).
     */
    public static function resolverUltimaTemporadaCerrada($temporada = null) {
        require_once __DIR__ . '/PresupuestoCalculos.php';
        $tipo = (strtoupper(trim((string)$temporada)) === 'INVIERNO') ? 'INVIERNO' : 'VERANO';
        $actual = PresupuestoCalculos::obtenerTemporadaActual();
        $hoy = date('Y-m-d');

        $cand = PresupuestoCalculos::construirTemporada($tipo, (int)$actual['ano']);
        if ($cand['hasta'] >= $hoy) {
            $cand = PresupuestoCalculos::construirTemporada($tipo, (int)$cand['ano'] - 1);
        }

        $meses = ($tipo === 'INVIERNO') ? [2, 3, 4, 5, 6, 7] : [8, 9, 10, 11, 12, 1];
        $cand['meses'] = $meses;
        return $cand;
    }

    /**
     * Obtener el período por defecto (última temporada cerrada equivalente).
     * Mantiene la firma para compatibilidad de endpoints existentes.
     */
    public function obtenerPeriodoDefaultMarkup($temporada = null) {
        $info = self::resolverUltimaTemporadaCerrada($temporada);
        return [
            'success' => true,
            'temporada' => $info['temporada'],
            'codigo' => $info['codigo'],
            'desde' => $info['desde'],
            'hasta' => $info['hasta'],
            'texto' => date('d/m/Y', strtotime($info['desde'])) . ' al ' . date('d/m/Y', strtotime($info['hasta']))
        ];
    }

    /**
     * Obtiene el tipo de cambio oficial vendedor promedio mensual desde
     * RO_V_DOLAR_OFICIAL_BCRA en central (clave "ANIO-MES").
     */
    private function obtenerTasasMensualesBCRA($cidCentral) {
        $tasas = [];
        $sql = "SELECT Año, Mes, TCC, TCV FROM dbo.RO_V_DOLAR_OFICIAL_BCRA WHERE Año >= 2024 ORDER BY Año, Mes";
        $stmt = sqlsrv_query($cidCentral, $sql);
        if ($stmt !== false) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $clave = (int)$r['Año'] . '-' . (int)$r['Mes'];
                $tcv = (float)($r['TCV'] ?? 0);
                $tcc = (float)($r['TCC'] ?? 0);
                if ($tcv > 0) {
                    $tasas[$clave] = $tcv;
                } elseif ($tcc > 0) {
                    $tasas[$clave] = $tcc;
                }
            }
            sqlsrv_free_stmt($stmt);
        }
        return $tasas;
    }

    /**
     * Calcular y sincronizar automáticamente el Mark-Up real en USD
     * (Precio Real USD / Vcosto USD del parámetro) por Rubro, Categoría y Canal.
     *
     * Toma la facturación neta sin IVA y unidades reales desde central
     * (GVA12/GVA53, CTA02/CTA03, STA14/STA20), convierte cada mes a USD con la
     * cotización oficial BCRA de ese mes y divide por las unidades y por el
     * Vcosto USD vigente en FP_T_COSTOS_PARAMETROS. De esta manera el mark-up
     * queda expresado en USD homogéneos (evitando el sesgo del costo contable
     * histórico en ARS) y protege contra distorsiones por devoluciones o bajo
     * volumen (< 5 u.) y ratios extremos (> 8.0x o < 0.5x).
     *
     * @param string|null $fechaDesde Fecha inicio (ej: '2026-09-01')
     * @param string|null $fechaHasta Fecha fin (ej: '2026-10-31')
     */
    /**
     * Calcular y sincronizar automáticamente el Mark-Up real en USD
     * (Precio Real USD / Vcosto USD del parámetro) por Rubro, Categoría, Canal y Mes
     * contra la última temporada cerrada equivalente del año anterior
     * ('INVIERNO': Feb–Jul | 'VERANO': Ago–Ene).
     *
     * Persiste el desglose mensual en FP_T_COSTOS_MARKUP_MENSUAL con cascada
     * de respaldo de 4 niveles (Categoría+Mes -> Rubro+Mes -> Categoría+Temp -> Base)
     * y actualiza también el promedio estacional en FP_T_COSTOS_PARAMETROS.
     */
    public function sincronizarMarkupReal($fechaDesde = null, $fechaHasta = null, $temporada = null) {
        try {
            $conexion = new Conexion();
            $cidCentral = $conexion->conectar('central');

            if (!$cidCentral) {
                throw new Exception("No se pudo conectar a la base central (XL-TANGO -> LAKER_SA).");
            }

            $infoTemp = self::resolverUltimaTemporadaCerrada($temporada);
            if (empty($fechaDesde) || empty($fechaHasta)) {
                $fechaDesde = $infoTemp['desde'];
                $fechaHasta = $infoTemp['hasta'];
            }
            $temporadaNorm = $infoTemp['temporada'];
            $mesesTemporada = $infoTemp['meses'];

            // Normalizar a límites de mes completo para abarcar todos los cierres mensuales incluidos
            $desdeNormalizado = date('Y-m-01', strtotime($fechaDesde));
            $hastaNormalizado = date('Y-m-t', strtotime($fechaHasta));

            if ($desdeNormalizado > $hastaNormalizado) {
                sqlsrv_close($cidCentral);
                throw new Exception("La fecha desde ($fechaDesde) no puede ser posterior a la fecha hasta ($fechaHasta).");
            }

            $tasasMes = $this->obtenerTasasMensualesBCRA($cidCentral);
            $tcFallback = $this->obtenerTipoCambioHoy();
            if ($tcFallback <= 0) $tcFallback = 1500.0;

            $paramsActuales = $this->obtenerParametrosGlobales();

            // Consultar Venta neta sin IVA (ARS) y Unidades reales por Mes, Canal, Rubro y Categoría Padre
            $sql = "
                SELECT
                    YEAR(X.FECHA) AS ANIO,
                    MONTH(X.FECHA) AS MES,
                    X.CANAL,
                    TRIM(F.RUBRO) AS RUBRO,
                    TRIM(CASE WHEN F.CATEGORIA_PADRE IS NULL OR F.CATEGORIA_PADRE = '' THEN F.RUBRO ELSE F.CATEGORIA_PADRE END) AS CATEGORIA_PADRE,
                    SUM(X.CANTIDAD) AS UNIDADES,
                    SUM(X.IMPORTE) AS VENTA_ARS
                FROM (
                    SELECT A.FECHA_EMIS AS FECHA,
                           CASE WHEN A.COD_CLIENT = '000000' THEN 'ECOMMERCE'
                                WHEN A.COD_CLIENT LIKE 'MA%' THEN 'MAYORISTAS'
                                WHEN A.COD_CLIENT LIKE 'FR%' THEN 'FRANQUICIAS'
                           END AS CANAL,
                           B.COD_ARTICU,
                           CASE WHEN A.T_COMP LIKE 'NC%' THEN -B.CANTIDAD ELSE B.CANTIDAD END AS CANTIDAD,
                           CASE WHEN A.T_COMP LIKE 'NC%' THEN -CAST(B.IMP_NETO_P AS FLOAT) ELSE CAST(B.IMP_NETO_P AS FLOAT) END AS IMPORTE
                    FROM dbo.GVA12 A
                    INNER JOIN dbo.GVA53 B ON A.N_COMP = B.N_COMP AND A.T_COMP = B.T_COMP
                    WHERE (A.COD_CLIENT LIKE '[MF][AR]%' OR (A.COD_CLIENT = '000000' AND A.NRO_DE_LIS = '20'))
                      AND A.T_COMP LIKE '[NF]%' AND B.COD_ARTICU_KIT = ''
                      AND A.FECHA_EMIS BETWEEN ? AND ?

                    UNION ALL
                    SELECT A.FECHA_EMIS AS FECHA,
                           CASE WHEN A.COD_CLIENT LIKE 'FR%' THEN 'FRANQUICIAS'
                                WHEN A.COD_CLIENT LIKE 'MA%' THEN 'MAYORISTAS'
                           END AS CANAL,
                           B.COD_ARTICU,
                           CASE WHEN A.T_COMP LIKE 'NC%' THEN -B.CANTIDAD ELSE B.CANTIDAD END AS CANTIDAD,
                           CASE WHEN A.T_COMP LIKE 'NC%' THEN -CAST(B.IMP_NETO_P AS FLOAT) ELSE CAST(B.IMP_NETO_P AS FLOAT) END AS IMPORTE
                    FROM dbo.GVA12 A
                    INNER JOIN dbo.GVA53 B ON A.N_COMP = B.N_COMP AND A.T_COMP = B.T_COMP
                    WHERE A.COD_CLIENT LIKE '[MF]%' AND A.T_COMP LIKE '[NF]%' AND B.PROMOCION = 1
                      AND A.FECHA_EMIS BETWEEN ? AND ?

                    UNION ALL
                    SELECT CAST(A.FECHA_EMIS AS DATE) AS FECHA,
                           'LOCALES PROPIOS' AS CANAL,
                           B.COD_ARTICU,
                           CAST(CASE WHEN B.T_COMP LIKE 'NC%' THEN -B.CANTIDAD ELSE B.CANTIDAD END AS FLOAT) AS CANTIDAD,
                           CAST(CASE WHEN A.MON_CTE = 0 THEN (CASE WHEN B.T_COMP LIKE 'NC%' THEN -B.IMP_NETO_P ELSE B.IMP_NETO_P END) * A.COTIZ
                                     ELSE (CASE WHEN B.T_COMP LIKE 'NC%' THEN -B.IMP_NETO_P ELSE B.IMP_NETO_P END) END AS FLOAT) AS IMPORTE
                    FROM [XL-LAKERBIS].LOCALES_LAKERS.DBO.CTA02 A
                    INNER JOIN [XL-LAKERBIS].LOCALES_LAKERS.DBO.CTA03 B ON A.FECHA_EMIS = B.FECHA_MOV AND A.N_COMP = B.N_COMP AND A.T_COMP = B.T_COMP
                    WHERE A.ESTADO <> 'ANU' AND A.FECHA_EMIS BETWEEN ? AND ? AND A.NRO_SUCURS != '1'

                    UNION ALL
                    SELECT A.FECHA_MOV AS FECHA,
                           CASE WHEN A.COD_PRO_CL LIKE 'MA%' THEN 'MAYORISTAS'
                                WHEN A.COD_PRO_CL LIKE 'FR%' THEN 'FRANQUICIAS'
                           END AS CANAL,
                           B.COD_ARTICU,
                           B.CANTIDAD,
                           B.CANTIDAD * B.PRECIO_REM AS IMPORTE
                    FROM dbo.STA14 A
                    INNER JOIN dbo.STA20 B ON A.TCOMP_IN_S = B.TCOMP_IN_S AND A.NCOMP_IN_S = B.NCOMP_IN_S
                    WHERE A.T_COMP = 'REM' AND A.FECHA_MOV BETWEEN ? AND ?
                      AND dbo.FN_GC_ES_REMITO_SIN_FACTURA_599(A.N_COMP) = 1 AND B.PROMOCION != 1
                ) X
                INNER JOIN dbo.RO_V_MAESTRO_ARTICULOS_RUBRO_CATEGORIA_ORIGEN F ON X.COD_ARTICU = F.COD_ARTICU
                WHERE F.RUBRO IS NOT NULL
                  AND F.RUBRO NOT IN ('RECUPEROS', 'PRORRATEABLES', 'SIN RUBRO', 'PACKAGING', '_KITS')
                  AND (F.CATEGORIA_PADRE IS NULL OR F.CATEGORIA_PADRE <> 'REMERA')
                  AND (X.COD_ARTICU LIKE 'X%' OR (F.RUBRO = 'SINTETICOS OUTLET' AND X.COD_ARTICU LIKE 'O%' AND X.COD_ARTICU NOT LIKE 'OU%'))
                GROUP BY YEAR(X.FECHA), MONTH(X.FECHA), X.CANAL, TRIM(F.RUBRO),
                         TRIM(CASE WHEN F.CATEGORIA_PADRE IS NULL OR F.CATEGORIA_PADRE = '' THEN F.RUBRO ELSE F.CATEGORIA_PADRE END)
            ";

            $paramsSql = [
                $desdeNormalizado, $hastaNormalizado,
                $desdeNormalizado, $hastaNormalizado,
                $desdeNormalizado, $hastaNormalizado,
                $desdeNormalizado, $hastaNormalizado
            ];
            $stmt = sqlsrv_query($cidCentral, $sql, $paramsSql);

            if ($stmt === false) {
                $err = print_r(sqlsrv_errors(), true);
                sqlsrv_close($cidCentral);
                throw new Exception("Error al consultar ventas reales por canal/categoría: " . $err);
            }

            // Acumular Venta en USD (convertida por el dólar oficial BCRA de cada mes) y Unidades reales
            // tanto acumuladas de la temporada como abiertas por mes y por rubro+mes
            $ventasUSD = [];
            $ventasMesUSD = [];
            $ventasRubroMes = [];
            $anioPorMes = [];

            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $canal = trim($row['CANAL'] ?? '');
                if (!in_array($canal, ['LOCALES PROPIOS', 'FRANQUICIAS', 'MAYORISTAS', 'ECOMMERCE'], true)) continue;

                $rubro = trim($row['RUBRO'] ?? '');
                $cat = trim($row['CATEGORIA_PADRE'] ?? '');
                if ($rubro === '' || $cat === '') continue;

                $anioRow = (int)$row['ANIO'];
                $mesRow = (int)$row['MES'];
                $ym = "$anioRow-$mesRow";
                $anioPorMes[$mesRow] = $anioRow;

                $tcMes = $tasasMes[$ym] ?? $tcFallback;
                if ($tcMes <= 0) $tcMes = $tcFallback;

                $unidades = (float)($row['UNIDADES'] ?? 0);
                $vARS = (float)($row['VENTA_ARS'] ?? 0);
                $vUSD = $vARS / $tcMes;

                $clave = "$rubro|$cat";
                if (!isset($ventasUSD[$clave][$canal])) {
                    $ventasUSD[$clave][$canal] = ['unidades' => 0.0, 'venta_usd' => 0.0];
                }
                $ventasUSD[$clave][$canal]['unidades'] += $unidades;
                $ventasUSD[$clave][$canal]['venta_usd'] += $vUSD;

                if (!isset($ventasMesUSD[$clave][$canal][$mesRow])) {
                    $ventasMesUSD[$clave][$canal][$mesRow] = ['unidades' => 0.0, 'venta_usd' => 0.0];
                }
                $ventasMesUSD[$clave][$canal][$mesRow]['unidades'] += $unidades;
                $ventasMesUSD[$clave][$canal][$mesRow]['venta_usd'] += $vUSD;

                $vcParam = (float)($paramsActuales[$clave]['vcosto'] ?? 0);
                if ($vcParam > 0 && $unidades > 0) {
                    if (!isset($ventasRubroMes[$rubro][$canal][$mesRow])) {
                        $ventasRubroMes[$rubro][$canal][$mesRow] = ['unidades' => 0.0, 'venta_usd' => 0.0, 'costo_u' => 0.0];
                    }
                    $ventasRubroMes[$rubro][$canal][$mesRow]['unidades'] += $unidades;
                    $ventasRubroMes[$rubro][$canal][$mesRow]['venta_usd'] += $vUSD;
                    $ventasRubroMes[$rubro][$canal][$mesRow]['costo_u'] += ($unidades * $vcParam);
                }
            }
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($cidCentral);

            if (empty($ventasUSD)) {
                throw new Exception("No se encontraron ventas reales para el período seleccionado ($desdeNormalizado a $hastaNormalizado).");
            }

            $canalesMap = [
                'LOCALES PROPIOS' => 'markup_locales_propios',
                'FRANQUICIAS' => 'markup_franquicias',
                'MAYORISTAS' => 'markup_mayoristas',
                'ECOMMERCE' => 'markup_ecommerce'
            ];

            $minUnidades = 5;
            $minMarkupValido = 0.50;
            $maxMarkupValido = 8.00;

            $filasMap = [];
            $rubroMarkupsValidos = [];
            $categoriasActualizadas = 0;
            $omitidasPorOutlier = 0;

            // 1. Calcular nuevo Mark-Up promedio estacional (respaldo en FP_T_COSTOS_PARAMETROS)
            foreach ($paramsActuales as $clave => $item) {
                $rubro = trim($item['rubro']);
                $cat = trim($item['categoria_padre']);
                if ($cat === '') continue; // Omitir filas huérfanas sin categoría padre

                $vcosto = (float)$item['vcosto'];
                $filaNueva = [
                    'rubro' => $rubro,
                    'categoria_padre' => $cat,
                    'costo_prom' => (float)$item['costo_prom'],
                    'inc_fob' => (float)$item['inc_fob'],
                    'vcosto' => $vcosto,
                    'markup_locales_propios' => (float)$item['markup_locales_propios'],
                    'markup_franquicias' => (float)$item['markup_franquicias'],
                    'markup_mayoristas' => (float)$item['markup_mayoristas'],
                    'markup_ecommerce' => (float)$item['markup_ecommerce']
                ];

                $tuvoActualizacion = false;
                $datosCat = $ventasUSD[$clave] ?? null;

                foreach ($canalesMap as $canalNombre => $campoMarkup) {
                    $d = $datosCat[$canalNombre] ?? null;
                    if ($d && $vcosto > 0 && $d['unidades'] >= $minUnidades && $d['venta_usd'] > 0) {
                        $precioRealUSD = $d['venta_usd'] / $d['unidades'];
                        $mkCalculado = round($precioRealUSD / $vcosto, 2);

                        if ($mkCalculado >= $minMarkupValido && $mkCalculado <= $maxMarkupValido) {
                            $filaNueva[$campoMarkup] = $mkCalculado;
                            $tuvoActualizacion = true;
                        } else {
                            $omitidasPorOutlier++;
                        }
                    }

                    // Acumular markups sanos por rubro para poder sanear anomalías heredadas (> 8x)
                    $mkFinal = $filaNueva[$campoMarkup];
                    if ($mkFinal >= $minMarkupValido && $mkFinal <= $maxMarkupValido) {
                        $rubroMarkupsValidos[$rubro][$campoMarkup][] = $mkFinal;
                    }
                }

                if ($tuvoActualizacion) {
                    $categoriasActualizadas++;
                }
                $filasMap[$clave] = $filaNueva;
            }

            // 2. Sanear cualquier markup heredado extremo (> 8.0x, ej: 25.44x en LLAVERO) usando el promedio sano del rubro
            $anomaliasSaneadas = 0;
            foreach ($filasMap as $clave => &$fila) {
                $rubro = $fila['rubro'];
                foreach ($canalesMap as $campoMarkup) {
                    if ($fila[$campoMarkup] > $maxMarkupValido) {
                        $listaRubro = $rubroMarkupsValidos[$rubro][$campoMarkup] ?? [];
                        if (!empty($listaRubro)) {
                            $fila[$campoMarkup] = round(array_sum($listaRubro) / count($listaRubro), 2);
                        } else {
                            $fila[$campoMarkup] = $maxMarkupValido;
                        }
                        $anomaliasSaneadas++;
                    }
                }
            }
            unset($fila);

            $filasParaGuardar = array_values($filasMap);

            // Guardar respaldo estacional en FP_T_COSTOS_PARAMETROS
            $resGuardar = $this->guardarParametrosGlobales($filasParaGuardar);
            if ($resGuardar !== true) {
                throw new Exception($resGuardar['mensaje'] ?? 'Error al guardar los markups calculados en la base de datos.');
            }

            // 3. Construir y guardar la matriz mensual (mes a mes del año anterior) con cascada de 4 niveles
            $anioDesde = (int)date('Y', strtotime($desdeNormalizado));
            $anioHasta = (int)date('Y', strtotime($hastaNormalizado));
            $filasMensuales = [];
            $desgloseOrigen = ['CAT_MES' => 0, 'RUBRO_MES' => 0, 'CAT_TEMP' => 0, 'BASE' => 0];

            foreach ($filasMap as $clave => $filaTemp) {
                $rubro = $filaTemp['rubro'];
                $cat = $filaTemp['categoria_padre'];
                $vcosto = (float)$filaTemp['vcosto'];

                foreach ($canalesMap as $canalNombre => $campoMarkup) {
                    $mkBaseTemp = (float)$filaTemp[$campoMarkup];
                    $dTemp = $ventasUSD[$clave][$canalNombre] ?? null;
                    $tuvoTemp = ($dTemp && $vcosto > 0 && $dTemp['unidades'] >= $minUnidades && $dTemp['venta_usd'] > 0);

                    foreach ($mesesTemporada as $mesNum) {
                        $anioMesDef = ($temporadaNorm === 'VERANO' && $mesNum === 1) ? $anioHasta : $anioDesde;
                        $anioBase = $anioPorMes[$mesNum] ?? $anioMesDef;

                        $mkMes = null;
                        $origen = 'BASE';
                        $uBase = 0;
                        $usdBase = 0.0;

                        // Nivel 1: Categoría + Canal + Mes homólogo
                        $dm = $ventasMesUSD[$clave][$canalNombre][$mesNum] ?? null;
                        if ($dm && $vcosto > 0 && $dm['unidades'] >= $minUnidades && $dm['venta_usd'] > 0) {
                            $cand = round(($dm['venta_usd'] / $dm['unidades']) / $vcosto, 2);
                            if ($cand >= $minMarkupValido && $cand <= $maxMarkupValido) {
                                $mkMes = $cand;
                                $origen = 'CAT_MES';
                                $uBase = (int)round($dm['unidades']);
                                $usdBase = round($dm['venta_usd'], 2);
                            }
                        }

                        // Nivel 2: Rubro + Canal + Mes homólogo
                        if ($mkMes === null) {
                            $rm = $ventasRubroMes[$rubro][$canalNombre][$mesNum] ?? null;
                            if ($rm && $rm['unidades'] >= $minUnidades && $rm['costo_u'] > 0 && $rm['venta_usd'] > 0) {
                                $cand = round($rm['venta_usd'] / $rm['costo_u'], 2);
                                if ($cand >= $minMarkupValido && $cand <= $maxMarkupValido) {
                                    $mkMes = $cand;
                                    $origen = 'RUBRO_MES';
                                    $uBase = (int)round($rm['unidades']);
                                    $usdBase = round($rm['venta_usd'], 2);
                                }
                            }
                        }

                        // Nivel 3 / 4: Temporada completa o parámetro base
                        if ($mkMes === null) {
                            $mkMes = $mkBaseTemp;
                            $origen = $tuvoTemp ? 'CAT_TEMP' : 'BASE';
                            if ($tuvoTemp) {
                                $uBase = (int)round($dTemp['unidades']);
                                $usdBase = round($dTemp['venta_usd'], 2);
                            }
                        }

                        $desgloseOrigen[$origen] = ($desgloseOrigen[$origen] ?? 0) + 1;
                        $filasMensuales[] = [
                            'temporada' => $temporadaNorm,
                            'rubro' => $rubro,
                            'categoria_padre' => $cat,
                            'canal' => $canalNombre,
                            'mes' => $mesNum,
                            'anio_base' => $anioBase,
                            'markup' => $mkMes,
                            'origen' => $origen,
                            'unidades_base' => $uBase,
                            'venta_usd_base' => $usdBase
                        ];
                    }
                }
            }

            $resMensual = $this->guardarMarkupsMensualesPorTemporada($temporadaNorm, $filasMensuales);
            if ($resMensual !== true) {
                throw new Exception($resMensual['mensaje'] ?? 'Error al guardar los markups mensuales por temporada.');
            }

            $textoPeriodo = date('d/m/Y', strtotime($desdeNormalizado)) . ' al ' . date('d/m/Y', strtotime($hastaNormalizado));

            return [
                'success' => true,
                'temporada' => $temporadaNorm,
                'total_actualizados' => $categoriasActualizadas,
                'total_celdas_mensuales' => count($filasMensuales),
                'desglose_origen' => $desgloseOrigen,
                'anomalias_saneadas' => $anomaliasSaneadas,
                'omitidas_por_outlier' => $omitidasPorOutlier,
                'periodo_desde' => $desdeNormalizado,
                'periodo_hasta' => $hastaNormalizado,
                'periodo_formateado' => $textoPeriodo,
                'message' => "Mark-Up mensual ($temporadaNorm) calibrado contra la temporada $textoPeriodo (" . count($filasMensuales) . " celdas mes/canal)."
            ];
        } catch (Exception $e) {
            error_log("Error en CostoProyeccion::sincronizarMarkupReal: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Guarda ediciones manuales de mark-ups mensuales desde el modal de Parámetros Globales.
     * Solo actualiza/inserta las celdas modificadas o faltantes, preservando origen y métricas base de las demás.
     */
    public function guardarMarkupsMensualesManual($temporada, $markupsPorClave) {
        try {
            if (!$this->cid_sistemas) {
                throw new Exception("Error de conexión a la base de datos");
            }
            if (!is_array($markupsPorClave) || empty($markupsPorClave)) {
                return true;
            }

            $infoTemp = self::resolverUltimaTemporadaCerrada($temporada);
            $temporadaNorm = $infoTemp['temporada'];
            $anioDesde = (int)date('Y', strtotime($infoTemp['desde']));
            $anioHasta = (int)date('Y', strtotime($infoTemp['hasta']));

            $existentes = $this->obtenerMarkupsMensualesPorTemporada($temporadaNorm)['por_clave'] ?? [];

            if (sqlsrv_begin_transaction($this->cid_sistemas) === false) {
                throw new Exception("Error al iniciar transacción manual mensual: " . print_r(sqlsrv_errors(), true));
            }

            $sqlUpd = "UPDATE dbo.FP_T_COSTOS_MARKUP_MENSUAL
                       SET markup = ?, origen = ?, fecha_actualizacion = GETDATE()
                       WHERE temporada = ? AND rubro = ? AND categoria_padre = ? AND canal = ? AND mes = ?";
            $sqlIns = "INSERT INTO dbo.FP_T_COSTOS_MARKUP_MENSUAL
                       (temporada, rubro, categoria_padre, canal, mes, anio_base, markup, origen, unidades_base, venta_usd_base, fecha_actualizacion)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, GETDATE())";

            foreach ($markupsPorClave as $clave => $canales) {
                $partes = explode('|', (string)$clave, 2);
                if (count($partes) !== 2) continue;
                $rubro = trim($partes[0]);
                $cat = trim($partes[1]);
                if ($rubro === '' || $cat === '' || !is_array($canales)) continue;

                foreach ($canales as $canal => $meses) {
                    if (!is_array($meses)) continue;
                    foreach ($meses as $mesKey => $info) {
                        $mesNum = (int)$mesKey;
                        if ($mesNum < 1 || $mesNum > 12) continue;

                        $mkNuevo = is_array($info) ? round((float)($info['markup'] ?? 0), 2) : round((float)$info, 2);
                        $prev = $existentes[$clave][$canal][$mesNum] ?? $existentes[$clave][$canal][(string)$mesNum] ?? null;

                        if ($prev !== null) {
                            $mkPrev = round((float)($prev['markup'] ?? 0), 2);
                            if (abs($mkNuevo - $mkPrev) < 0.005) {
                                continue; // Sin cambios: preservar origen y timestamps reales
                            }
                            $origen = 'MANUAL';
                            $st = sqlsrv_query($this->cid_sistemas, $sqlUpd, [
                                $mkNuevo, $origen, $temporadaNorm, $rubro, $cat, $canal, $mesNum
                            ]);
                        } else {
                            $anioDef = ($temporadaNorm === 'VERANO' && $mesNum === 1) ? $anioHasta : $anioDesde;
                            $origen = is_array($info) && !empty($info['origen']) ? (string)$info['origen'] : 'MANUAL';
                            $st = sqlsrv_query($this->cid_sistemas, $sqlIns, [
                                $temporadaNorm, $rubro, $cat, $canal, $mesNum, $anioDef, $mkNuevo, $origen
                            ]);
                        }

                        if ($st === false) {
                            sqlsrv_rollback($this->cid_sistemas);
                            throw new Exception("Error al guardar markup mensual ($clave / $canal / mes $mesNum): " . print_r(sqlsrv_errors(), true));
                        }
                        sqlsrv_free_stmt($st);
                    }
                }
            }

            sqlsrv_commit($this->cid_sistemas);
            return true;
        } catch (Exception $e) {
            error_log("Error en CostoProyeccion::guardarMarkupsMensualesManual: " . $e->getMessage());
            return ['error' => true, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Guarda de forma atómica los mark-ups mensuales para una temporada en FP_T_COSTOS_MARKUP_MENSUAL.
     */
    private function guardarMarkupsMensualesPorTemporada($temporada, $filasMensuales) {
        try {
            if (!$this->cid_sistemas) {
                throw new Exception("Error de conexión a la base de datos");
            }
            if (sqlsrv_begin_transaction($this->cid_sistemas) === false) {
                throw new Exception("Error al iniciar transacción mensual: " . print_r(sqlsrv_errors(), true));
            }

            $delStmt = sqlsrv_query($this->cid_sistemas, "DELETE FROM dbo.FP_T_COSTOS_MARKUP_MENSUAL WHERE temporada = ?", [$temporada]);
            if ($delStmt === false) {
                sqlsrv_rollback($this->cid_sistemas);
                throw new Exception("Error al limpiar markups mensuales: " . print_r(sqlsrv_errors(), true));
            }
            sqlsrv_free_stmt($delStmt);

            $sqlIns = "
                INSERT INTO dbo.FP_T_COSTOS_MARKUP_MENSUAL
                    (temporada, rubro, categoria_padre, canal, mes, anio_base, markup, origen, unidades_base, venta_usd_base, fecha_actualizacion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())
            ";
            foreach ($filasMensuales as $fm) {
                $st = sqlsrv_query($this->cid_sistemas, $sqlIns, [
                    $fm['temporada'], $fm['rubro'], $fm['categoria_padre'], $fm['canal'],
                    (int)$fm['mes'], (int)$fm['anio_base'], (float)$fm['markup'],
                    $fm['origen'], (int)$fm['unidades_base'], (float)$fm['venta_usd_base']
                ]);
                if ($st === false) {
                    sqlsrv_rollback($this->cid_sistemas);
                    throw new Exception("Error al insertar markup mensual: " . print_r(sqlsrv_errors(), true));
                }
                sqlsrv_free_stmt($st);
            }

            sqlsrv_commit($this->cid_sistemas);
            return true;
        } catch (Exception $e) {
            error_log("Error en CostoProyeccion::guardarMarkupsMensualesPorTemporada: " . $e->getMessage());
            return ['error' => true, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Obtiene el mapa de mark-ups mensuales guardados para una temporada ('INVIERNO' | 'VERANO').
     */
    public function obtenerMarkupsMensualesPorTemporada($temporada) {
        $resultado = ['por_clave' => [], 'meta' => null];
        try {
            if (!$this->cid_sistemas) return $resultado;
            $temporadaNorm = (strtoupper(trim((string)$temporada)) === 'INVIERNO') ? 'INVIERNO' : 'VERANO';

            $chk = sqlsrv_query($this->cid_sistemas, "SELECT OBJECT_ID('dbo.FP_T_COSTOS_MARKUP_MENSUAL', 'U') AS id");
            $exists = $chk && ($rChk = sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC)) && !empty($rChk['id']);
            if ($chk) sqlsrv_free_stmt($chk);
            if (!$exists) return $resultado;

            $sql = "SELECT rubro, categoria_padre, canal, mes, anio_base, markup, origen, fecha_actualizacion
                    FROM dbo.FP_T_COSTOS_MARKUP_MENSUAL
                    WHERE temporada = ?";
            $stmt = sqlsrv_query($this->cid_sistemas, $sql, [$temporadaNorm]);
            if ($stmt === false) return $resultado;

            $fechaMax = null;
            $anioMin = null;
            $anioMax = null;
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $clave = strtoupper(trim($r['rubro'])) . '|' . strtoupper(trim($r['categoria_padre']));
                $canal = strtoupper(trim($r['canal']));
                $mes = (int)$r['mes'];
                $anio = (int)$r['anio_base'];
                $resultado['por_clave'][$clave][$canal][$mes] = [
                    'markup' => (float)$r['markup'],
                    'origen' => $r['origen'],
                    'anio_base' => $anio
                ];
                if ($anioMin === null || $anio < $anioMin) $anioMin = $anio;
                if ($anioMax === null || $anio > $anioMax) $anioMax = $anio;
                if ($r['fecha_actualizacion'] instanceof DateTime) {
                    $fStr = $r['fecha_actualizacion']->format('d/m/Y H:i');
                    if ($fechaMax === null || $fStr > $fechaMax) $fechaMax = $fStr;
                }
            }
            sqlsrv_free_stmt($stmt);

            if (!empty($resultado['por_clave'])) {
                $resultado['meta'] = [
                    'temporada' => $temporadaNorm,
                    'anio_base' => ($anioMin === $anioMax) ? (string)$anioMin : "$anioMin-$anioMax",
                    'fecha_actualizacion' => $fechaMax
                ];
            }
            return $resultado;
        } catch (Exception $e) {
            error_log("Error en CostoProyeccion::obtenerMarkupsMensualesPorTemporada: " . $e->getMessage());
            return $resultado;
        }
    }

    /**
     * Obtener parámetros globales de costo por rubro y categoría
     */
    public function obtenerParametrosGlobales() {
        try {
            if (!$this->cid_sistemas) {
                return [];
            }

            $sql = "SELECT * FROM dbo.FP_T_COSTOS_PARAMETROS ORDER BY rubro, categoria_padre";
            $stmt = sqlsrv_query($this->cid_sistemas, $sql);
            
            if ($stmt === false) {
                throw new Exception("Error al obtener parámetros globales: " . print_r(sqlsrv_errors(), true));
            }
            
            $params = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $clave = trim($row['rubro']) . '|' . trim($row['categoria_padre']);
                $params[$clave] = [
                    'rubro' => trim($row['rubro']),
                    'categoria_padre' => trim($row['categoria_padre']),
                    'costo_prom' => (float)$row['costo_prom'],
                    'inc_fob' => (float)$row['inc_fob'],
                    'vcosto' => (float)$row['vcosto'],
                    'markup_locales_propios' => isset($row['markup_locales_propios']) ? (float)$row['markup_locales_propios'] : 0.0,
                    'markup_franquicias' => isset($row['markup_franquicias']) ? (float)$row['markup_franquicias'] : 0.0,
                    'markup_mayoristas' => isset($row['markup_mayoristas']) ? (float)$row['markup_mayoristas'] : 0.0,
                    'markup_ecommerce' => isset($row['markup_ecommerce']) ? (float)$row['markup_ecommerce'] : 0.0
                ];
            }
            sqlsrv_free_stmt($stmt);
            return $params;
        } catch (Exception $e) {
            error_log("Error en CostoProyeccion::obtenerParametrosGlobales: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Guardar/Actualizar parámetros globales de costo
     */
    public function guardarParametrosGlobales($filas) {
        try {
            if (!$this->cid_sistemas) {
                throw new Exception("Error de conexión a la base de datos");
            }

            if (sqlsrv_begin_transaction($this->cid_sistemas) === false) {
                throw new Exception("Error al iniciar transacción: " . print_r(sqlsrv_errors(), true));
            }

            $sqlUpsert = "
                MERGE INTO dbo.FP_T_COSTOS_PARAMETROS AS target
                USING (SELECT ? AS rubro, ? AS categoria_padre) AS source
                ON (target.rubro = source.rubro AND target.categoria_padre = source.categoria_padre)
                WHEN MATCHED THEN
                    UPDATE SET 
                        target.costo_prom = ?, 
                        target.inc_fob = ?, 
                        target.vcosto = ?,
                        target.markup_locales_propios = ?,
                        target.markup_franquicias = ?,
                        target.markup_mayoristas = ?,
                        target.markup_ecommerce = ?
                WHEN NOT MATCHED THEN
                    INSERT (rubro, categoria_padre, costo_prom, inc_fob, vcosto, markup_locales_propios, markup_franquicias, markup_mayoristas, markup_ecommerce)
                    VALUES (source.rubro, source.categoria_padre, ?, ?, ?, ?, ?, ?, ?);
            ";

            foreach ($filas as $fila) {
                $rubro = trim($fila['rubro'] ?? '');
                $categoria = trim($fila['categoria_padre'] ?? '');
                $costo_prom = (float)($fila['costo_prom'] ?? 0);
                $inc_fob = (float)($fila['inc_fob'] ?? 0);
                $vcosto = $costo_prom * (1 + $inc_fob / 100);
                
                $markup_lp = (float)($fila['markup_locales_propios'] ?? 0);
                $markup_fr = (float)($fila['markup_franquicias'] ?? 0);
                $markup_my = (float)($fila['markup_mayoristas'] ?? 0);
                $markup_ec = (float)($fila['markup_ecommerce'] ?? 0);

                $params = [
                    $rubro, $categoria,
                    // UPDATE SET
                    $costo_prom, $inc_fob, $vcosto,
                    $markup_lp, $markup_fr, $markup_my, $markup_ec,
                    // INSERT VALUES
                    $costo_prom, $inc_fob, $vcosto,
                    $markup_lp, $markup_fr, $markup_my, $markup_ec
                ];

                $stmt = sqlsrv_query($this->cid_sistemas, $sqlUpsert, $params);
                if ($stmt === false) {
                    sqlsrv_rollback($this->cid_sistemas);
                    throw new Exception("Error al guardar parámetro global: " . print_r(sqlsrv_errors(), true));
                }
                sqlsrv_free_stmt($stmt);
            }

            sqlsrv_commit($this->cid_sistemas);
            return true;
        } catch (Exception $e) {
            error_log("Error en CostoProyeccion::guardarParametrosGlobales: " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    public function __destruct() {
        if ($this->cid_sistemas) {
            sqlsrv_close($this->cid_sistemas);
        }
    }
}

