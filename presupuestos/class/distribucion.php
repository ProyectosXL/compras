<?php

class Distribucion {
    private $cid_power;
    private $cid_sistemas;
    private $nameServer;

    public function __construct() {
        $this->nameServer = $this->determinarBaseDatos();
        
        require_once __DIR__.'/../../Class/conexion.php';
        $conexion = new Conexion();
        
        // Conexión para obtener ventas comerciales (apps_power o apps_power_uy)
        $this->cid_power = $conexion->conectar($this->nameServer);
        
        // Conexión para guardar la distribución (sistemas)
        $this->cid_sistemas = $conexion->conectar('apps');
    }

    private function determinarBaseDatos() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $pais = 'argentina';
        if (isset($_GET['pais'])) {
            $pais = strtolower($_GET['pais']);
        } elseif (isset($_POST['pais'])) {
            $pais = strtolower($_POST['pais']);
        } elseif (isset($_SESSION['pais_seleccionado'])) {
            $pais = strtolower($_SESSION['pais_seleccionado']);
        }
        
        switch ($pais) {
            case 'uruguay':
            case 'uy':
                return 'apps_power_uy';
            case 'argentina':
            case 'ar':
            default:
                return 'apps_power';
        }
    }

    public function obtenerPaisActual() {
        return $this->nameServer === 'apps_power_uy' ? 'uruguay' : 'argentina';
    }

    /**
     * Obtener canales comerciales únicos normalizados
     */
    public function obtenerCanales() {
        return ['LOCALES PROPIOS', 'FRANQUICIAS', 'MAYORISTAS', 'ECOMMERCE'];
    }

    /**
     * Obtener todas las ventas agrupadas por rubro, categoría, mes, año y canal (Rango de fechas dinámico)
     */
    public function obtenerTodasLasVentasPorCanal($fechaDesde, $fechaHasta) {
        try {
            if (!$this->cid_power) {
                throw new Exception("Error de conexión");
            }
            
            $params = [$fechaDesde, $fechaHasta];
            $sqlDate = "WHERE v.FECHA_VENTA >= ? AND v.FECHA_VENTA <= ? AND v.CANAL IN ('CENTRAL', 'LOCALES PROPIOS')";

            $sql = "SELECT 
                        v.RUBRO,
                        v.CATEGORIA_PADRE,
                        MONTH(v.FECHA_VENTA) AS MES,
                        YEAR(v.FECHA_VENTA) AS ANIO,
                        v.DESC_SUCURSAL,
                        CASE 
                            WHEN v.CANAL = 'CENTRAL' AND v.DESC_SUCURSAL = 'MAYORISTAS' THEN 'MAYORISTAS'
                            WHEN v.CANAL = 'CENTRAL' AND v.DESC_SUCURSAL = 'FRANQUICIAS' THEN 'FRANQUICIAS'
                            WHEN v.CANAL = 'LOCALES PROPIOS' AND v.DESC_SUCURSAL = 'ECOMMERCE' THEN 'ECOMMERCE'
                            WHEN v.CANAL = 'LOCALES PROPIOS' THEN 'LOCALES PROPIOS'
                            ELSE v.CANAL
                        END AS CANAL_NORMALIZADO,
                        SUM(CAST(v.CANT_VEND AS INT)) AS VENTAS
                    FROM dbo.RO_VENTAS_COMERCIAL v
                    $sqlDate
                    GROUP BY v.RUBRO, v.CATEGORIA_PADRE, MONTH(v.FECHA_VENTA), YEAR(v.FECHA_VENTA), v.DESC_SUCURSAL,
                             CASE 
                                 WHEN v.CANAL = 'CENTRAL' AND v.DESC_SUCURSAL = 'MAYORISTAS' THEN 'MAYORISTAS'
                                 WHEN v.CANAL = 'CENTRAL' AND v.DESC_SUCURSAL = 'FRANQUICIAS' THEN 'FRANQUICIAS'
                                 WHEN v.CANAL = 'LOCALES PROPIOS' AND v.DESC_SUCURSAL = 'ECOMMERCE' THEN 'ECOMMERCE'
                                 WHEN v.CANAL = 'LOCALES PROPIOS' THEN 'LOCALES PROPIOS'
                                 ELSE v.CANAL
                             END";
            
            $stmt = sqlsrv_query($this->cid_power, $sql, $params);
            
            if ($stmt === false) {
                throw new Exception("Error al obtener todas las ventas: " . print_r(sqlsrv_errors(), true));
            }
            
            $ventasMap = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rubro = trim($row['RUBRO'] ?? '');
                $categoria = trim($row['CATEGORIA_PADRE'] ?? '');
                $mes = (int)$row['MES'];
                $anio = (int)$row['ANIO'];
                $mesAnioClave = "$mes-$anio";
                $canal = trim($row['CANAL_NORMALIZADO'] ?? '');
                $sucursal = trim($row['DESC_SUCURSAL'] ?? '');
                $ventas = (int)$row['VENTAS'];
                
                if (!isset($ventasMap[$rubro])) {
                    $ventasMap[$rubro] = [];
                }
                if (!isset($ventasMap[$rubro][$categoria])) {
                    $ventasMap[$rubro][$categoria] = [];
                }
                if (!isset($ventasMap[$rubro][$categoria][$canal])) {
                    $ventasMap[$rubro][$categoria][$canal] = [
                        'total' => 0,
                        'meses' => [],
                        'sucursales' => []
                    ];
                }
                
                if (!isset($ventasMap[$rubro][$categoria][$canal]['meses'][$mesAnioClave])) {
                    $ventasMap[$rubro][$categoria][$canal]['meses'][$mesAnioClave] = 0;
                }
                $ventasMap[$rubro][$categoria][$canal]['meses'][$mesAnioClave] += $ventas;
                $ventasMap[$rubro][$categoria][$canal]['total'] += $ventas;
                
                if (!isset($ventasMap[$rubro][$categoria][$canal]['sucursales'][$sucursal])) {
                    $ventasMap[$rubro][$categoria][$canal]['sucursales'][$sucursal] = [
                        'total' => 0,
                        'meses' => []
                    ];
                }
                
                if (!isset($ventasMap[$rubro][$categoria][$canal]['sucursales'][$sucursal]['meses'][$mesAnioClave])) {
                    $ventasMap[$rubro][$categoria][$canal]['sucursales'][$sucursal]['meses'][$mesAnioClave] = 0;
                }
                
                $ventasMap[$rubro][$categoria][$canal]['sucursales'][$sucursal]['meses'][$mesAnioClave] += $ventas;
                $ventasMap[$rubro][$categoria][$canal]['sucursales'][$sucursal]['total'] += $ventas;
            }
            sqlsrv_free_stmt($stmt);

            // Obtener sucursales específicas de FRANQUICIAS de forma directa y rápida (sin timeout)
            $sqlFranq = "SELECT 
                            v.DESC_SUCURSAL,
                            MONTH(v.FECHA_VENTA) AS MES,
                            YEAR(v.FECHA_VENTA) AS ANIO,
                            SUM(CAST(v.CANT_VEND AS INT)) AS VENTAS
                         FROM dbo.RO_VENTAS_COMERCIAL v
                         WHERE v.FECHA_VENTA >= ? AND v.FECHA_VENTA <= ? AND v.CANAL = 'FRANQUICIAS'
                         GROUP BY v.DESC_SUCURSAL, MONTH(v.FECHA_VENTA), YEAR(v.FECHA_VENTA)";
            $stmtFranq = sqlsrv_query($this->cid_power, $sqlFranq, $params);
            if ($stmtFranq !== false) {
                $franqSucursalesMap = [];
                $franqTotalGeneral = 0;
                while ($rF = sqlsrv_fetch_array($stmtFranq, SQLSRV_FETCH_ASSOC)) {
                    $suc = trim($rF['DESC_SUCURSAL'] ?? '');
                    if ($suc === '' || $suc === 'FRANQUICIAS') continue;
                    $m = (int)$rF['MES'];
                    $y = (int)$rF['ANIO'];
                    $mKey = "$m-$y";
                    $v = (int)$rF['VENTAS'];

                    if (!isset($franqSucursalesMap[$suc])) {
                        $franqSucursalesMap[$suc] = [
                            'total' => 0,
                            'meses' => []
                        ];
                    }
                    if (!isset($franqSucursalesMap[$suc]['meses'][$mKey])) {
                        $franqSucursalesMap[$suc]['meses'][$mKey] = 0;
                    }
                    $franqSucursalesMap[$suc]['meses'][$mKey] += $v;
                    $franqSucursalesMap[$suc]['total'] += $v;
                    $franqTotalGeneral += $v;
                }
                sqlsrv_free_stmt($stmtFranq);

                // Si obtuvimos sucursales de franquicias, asignarlas al canal FRANQUICIAS en cada rubro/categoría
                if (!empty($franqSucursalesMap) && $franqTotalGeneral > 0) {
                    foreach ($ventasMap as $rKey => &$cats) {
                        foreach ($cats as $cKey => &$canalesMap) {
                            if (isset($canalesMap['FRANQUICIAS'])) {
                                $vCanalTotal = $canalesMap['FRANQUICIAS']['total'];
                                $canalesMap['FRANQUICIAS']['sucursales'] = [];
                                foreach ($franqSucursalesMap as $sucName => $sucInfo) {
                                    $prop = $sucInfo['total'] / $franqTotalGeneral;
                                    $canalesMap['FRANQUICIAS']['sucursales'][$sucName] = [
                                        'total' => round($vCanalTotal * $prop),
                                        'meses' => $sucInfo['meses']
                                    ];
                                }
                            }
                        }
                    }
                    unset($cats);
                }
            }

            return $ventasMap;
        } catch (Exception $e) {
            error_log("Error en Distribucion::obtenerTodasLasVentasPorCanal: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Guardar distribución en FP_T_DISTRIBUCION_COMPRAS_CANAL
     */
    public function guardarDistribucion($filas) {
        try {
            if (!$this->cid_sistemas) {
                throw new Exception("Error de conexión a XL-APPS/sistemas");
            }

            // Iniciar transacción
            if (sqlsrv_begin_transaction($this->cid_sistemas) === false) {
                throw new Exception("Error al iniciar transacción: " . print_r(sqlsrv_errors(), true));
            }

            $fechaGuardado = date('Y-m-d H:i:s');
            
            // SQL para guardar
            $sqlInsert = "INSERT INTO dbo.FP_T_DISTRIBUCION_COMPRAS_CANAL (
                            fecha_guardado, pais, temporada, rubro, categoria_padre, canal,
                            compra_proyectada, venta_historica_canal, participacion_porcentaje,
                            compra_distribuida, ajuste_manual, distribucion_final, periodo_analisis, nombre_distribucion,
                            distribucion_mensual_json
                          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $primerFila = reset($filas);
            $paisLimpio = $primerFila['pais'] ?? 'argentina';
            $temporadaLimpia = $primerFila['temporada'] ?? 'VERANO';
            $nombreDistLimpio = !empty($primerFila['nombre_distribucion']) ? trim($primerFila['nombre_distribucion']) : 'Por defecto';

            // Limpiar la versión completa anterior de una sola vez
            $sqlDelete = "DELETE FROM dbo.FP_T_DISTRIBUCION_COMPRAS_CANAL WHERE pais = ? AND temporada = ? AND nombre_distribucion = ?";
            $deleteStmt = sqlsrv_query($this->cid_sistemas, $sqlDelete, [$paisLimpio, $temporadaLimpia, $nombreDistLimpio]);
            if ($deleteStmt !== false) {
                sqlsrv_free_stmt($deleteStmt);
            }

            foreach ($filas as $fila) {
                $nombreDist = !empty($fila['nombre_distribucion']) ? trim($fila['nombre_distribucion']) : 'Por defecto';

                // Insertar el nuevo
                $insertParams = [
                    $fechaGuardado,
                    $fila['pais'],
                    $fila['temporada'],
                    $fila['rubro'],
                    $fila['categoria_padre'],
                    $fila['canal'],
                    (int)$fila['compra_proyectada'],
                    (int)$fila['venta_historica_canal'],
                    (float)$fila['participacion_porcentaje'],
                    (int)$fila['compra_distribuida'],
                    (int)$fila['ajuste_manual'],
                    (int)$fila['distribucion_final'],
                    $fila['periodo_analisis'],
                    $nombreDist,
                    $fila['distribucion_mensual_json'] ?? null
                ];

                $insertStmt = sqlsrv_query($this->cid_sistemas, $sqlInsert, $insertParams);
                if ($insertStmt === false) {
                    sqlsrv_rollback($this->cid_sistemas);
                    throw new Exception("Error al insertar distribución: " . print_r(sqlsrv_errors(), true));
                }
                sqlsrv_free_stmt($insertStmt);
            }

            // B. Consolidar y poblar la tabla consolidada FP_T_PRESUPUESTO_VERSION_CONSOLIDADA
            require_once __DIR__ . '/CostoProyeccion.php';
            $costosModel = new CostoProyeccion();
            $costosGlobales = $costosModel->obtenerParametrosGlobales();

            $primerFila = reset($filas);
            $paisCons = $primerFila['pais'] ?? 'argentina';
            $temporadaCons = $primerFila['temporada'] ?? 'VERANO';
            $nombreCons = !empty($primerFila['nombre_distribucion']) ? trim($primerFila['nombre_distribucion']) : 'Por defecto';

            $markupsTemporadaData = (strtolower($paisCons) !== 'uruguay')
                ? $costosModel->obtenerMarkupsMensualesPorTemporada($temporadaCons)
                : ['por_clave' => []];
            $markupsMensualesCons = $markupsTemporadaData['por_clave'] ?? [];

            // Limpiar la versión consolidada anterior
            $sqlDeleteCons = "DELETE FROM dbo.FP_T_PRESUPUESTO_VERSION_CONSOLIDADA WHERE pais = ? AND temporada = ? AND nombre_version = ?";
            $delConsStmt = sqlsrv_query($this->cid_sistemas, $sqlDeleteCons, [$paisCons, $temporadaCons, $nombreCons]);
            if ($delConsStmt !== false) {
                sqlsrv_free_stmt($delConsStmt);
            }

            $sqlInsertCons = "INSERT INTO dbo.FP_T_PRESUPUESTO_VERSION_CONSOLIDADA (
                                fecha_guardado, pais, temporada, nombre_version, periodo_analisis,
                                rubro, categoria_padre, canal, sucursal, unidades_presupuestadas,
                                costo_prom, inc_fob, vcosto, markup, vventa_unit_usd,
                                facturacion_presupuestada_usd, distribucion_mensual_json
                              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            foreach ($filas as $fila) {
                $rubro = trim($fila['rubro']);
                $categoria = trim($fila['categoria_padre']);
                $canal = trim($fila['canal']);
                $canalUpper = strtoupper($canal);
                $periodo = $fila['periodo_analisis'] ?? '';
                $unidadesP = (int)($fila['distribucion_final'] ?? $fila['compra_distribuida']);
                $distMensualJson = $fila['distribucion_mensual_json'] ?? null;

                $paramClave = "$rubro|$categoria";
                $costoInfo = $costosGlobales[$paramClave] ?? ['costo_prom' => 0.0, 'inc_fob' => 0.0, 'vcosto' => 0.0];
                $costoProm = (float)($costoInfo['costo_prom'] ?? 0.0);
                $incFob = (float)($costoInfo['inc_fob'] ?? 0.0);
                $vcosto = (float)($costoInfo['vcosto'] ?? 0.0);
                if ($vcosto <= 0 && $costoProm > 0) {
                    $vcosto = $costoProm * (1 + $incFob / 100);
                }

                $markupFijo = 0.0;
                if (strpos($canalUpper, 'LOCAL') !== false) $markupFijo = (float)($costoInfo['markup_locales_propios'] ?? 0.0);
                elseif (strpos($canalUpper, 'FRANQ') !== false) $markupFijo = (float)($costoInfo['markup_franquicias'] ?? 0.0);
                elseif (strpos($canalUpper, 'MAYOR') !== false) $markupFijo = (float)($costoInfo['markup_mayoristas'] ?? 0.0);
                elseif (strpos($canalUpper, 'ECOM') !== false || strpos($canalUpper, 'WEB') !== false) $markupFijo = (float)($costoInfo['markup_ecommerce'] ?? 0.0);

                $paramClaveUpper = strtoupper($rubro) . '|' . strtoupper($categoria);
                $mkMensualCanal = $markupsMensualesCons[$paramClaveUpper][$canalUpper] ?? [];

                $facturacionUsd = 0.0;
                $mesesArr = !empty($distMensualJson) ? json_decode($distMensualJson, true) : null;
                if (is_array($mesesArr) && !empty($mesesArr) && !empty($mkMensualCanal)) {
                    $sumaUnidJson = 0;
                    foreach ($mesesArr as $kMes => $vMes) {
                        $uMes = is_array($vMes) ? (float)($vMes['unidades'] ?? 0) : (float)$vMes;
                        $numMes = CostoProyeccion::extraerNumeroMes($kMes);
                        $mkMes = ($numMes > 0 && isset($mkMensualCanal[$numMes]['markup']) && (float)$mkMensualCanal[$numMes]['markup'] > 0)
                            ? (float)$mkMensualCanal[$numMes]['markup']
                            : $markupFijo;
                        $facturacionUsd += ($uMes * $vcosto * $mkMes);
                        $sumaUnidJson += $uMes;
                    }
                    // Ajustar proporcionalmente si $unidadesP difiere por redondeo respecto a $sumaUnidJson
                    if ($sumaUnidJson > 0 && $unidadesP > 0 && abs($sumaUnidJson - $unidadesP) > 0) {
                        $facturacionUsd = $facturacionUsd * ($unidadesP / $sumaUnidJson);
                    }
                    $vventaUnitUsd = $unidadesP > 0 ? ($facturacionUsd / $unidadesP) : ($vcosto * $markupFijo);
                    $markup = ($unidadesP > 0 && $vcosto > 0) ? round($facturacionUsd / ($unidadesP * $vcosto), 4) : $markupFijo;
                } else {
                    $markup = $markupFijo;
                    $vventaUnitUsd = $vcosto * $markup;
                    $facturacionUsd = $unidadesP * $vventaUnitUsd;
                }

                $paramsCons = [
                    $fechaGuardado,
                    $paisCons,
                    $temporadaCons,
                    $nombreCons,
                    $periodo,
                    $rubro,
                    $categoria,
                    $canalUpper,
                    null, // sucursal null para canal principal
                    $unidadesP,
                    $costoProm,
                    $incFob,
                    $vcosto,
                    $markup,
                    $vventaUnitUsd,
                    $facturacionUsd,
                    $distMensualJson
                ];

                $insStmt = sqlsrv_query($this->cid_sistemas, $sqlInsertCons, $paramsCons);
                if ($insStmt === false) {
                    sqlsrv_rollback($this->cid_sistemas);
                    throw new Exception("Error al insertar consolidado de distribución: " . print_r(sqlsrv_errors(), true));
                }
                sqlsrv_free_stmt($insStmt);
            }

            sqlsrv_commit($this->cid_sistemas);
            return true;
        } catch (Exception $e) {
            error_log("Error en Distribucion::guardarDistribucion: " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener distribución previamente guardada
     */
    public function obtenerDistribucionGuardada($pais, $temporada, $nombreDistribucion = 'Por defecto') {
        try {
            if (!$this->cid_sistemas) {
                return [];
            }

            if (empty($nombreDistribucion)) {
                $nombreDistribucion = 'Por defecto';
            }

            $pais = strtolower(trim($pais));
            // Seleccionar los guardados del grupo rubro/categoría/canal para el nombre especificado
            $sql = "SELECT * FROM dbo.FP_T_DISTRIBUCION_COMPRAS_CANAL 
                    WHERE pais = ? AND temporada = ? AND nombre_distribucion = ?
                    ORDER BY rubro, categoria_padre, canal";
            
            $params = [$pais, $temporada, $nombreDistribucion];
            $stmt = sqlsrv_query($this->cid_sistemas, $sql, $params);
            
            if ($stmt === false) {
                throw new Exception("Error al obtener distribución guardada: " . print_r(sqlsrv_errors(), true));
            }
            
            $distribuciones = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $distribuciones[] = $row;
            }
            sqlsrv_free_stmt($stmt);
            return $distribuciones;
        } catch (Exception $e) {
            error_log("Error en Distribucion::obtenerDistribucionGuardada: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener versión consolidada de la tabla FP_T_PRESUPUESTO_VERSION_CONSOLIDADA
     */
    public function obtenerVersionConsolidada($pais, $temporada, $nombreVersion = 'Por defecto') {
        try {
            if (!$this->cid_sistemas) {
                return [];
            }
            if (empty($nombreVersion)) {
                $nombreVersion = 'Por defecto';
            }
            $pais = strtolower(trim($pais));
            $sql = "SELECT * FROM dbo.FP_T_PRESUPUESTO_VERSION_CONSOLIDADA 
                    WHERE pais = ? AND temporada = ? AND nombre_version = ?
                    ORDER BY rubro, categoria_padre, canal";
            $params = [$pais, $temporada, $nombreVersion];
            $stmt = sqlsrv_query($this->cid_sistemas, $sql, $params);
            if ($stmt === false) {
                return [];
            }
            $rows = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $row;
            }
            sqlsrv_free_stmt($stmt);
            return $rows;
        } catch (Exception $e) {
            error_log("Error en Distribucion::obtenerVersionConsolidada: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener nombres de las distribuciones guardadas
     */
    public function obtenerNombresDistribuciones($pais, $temporada) {
        try {
            if (!$this->cid_sistemas) {
                return [['nombre' => 'Por defecto', 'periodo' => '']];
            }

            $pais = strtolower(trim($pais));
            $sql = "SELECT nombre_distribucion, MIN(periodo_analisis) AS periodo_analisis 
                    FROM dbo.FP_T_DISTRIBUCION_COMPRAS_CANAL 
                    WHERE pais = ? AND temporada = ?
                    GROUP BY nombre_distribucion
                    ORDER BY nombre_distribucion";
            $params = [$pais, $temporada];
            $stmt = sqlsrv_query($this->cid_sistemas, $sql, $params);
            
            if ($stmt === false) {
                return [['nombre' => 'Por defecto', 'periodo' => '']];
            }

            $versiones = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                if (!empty($row['nombre_distribucion'])) {
                    $versiones[] = [
                        'nombre' => trim($row['nombre_distribucion']),
                        'periodo' => trim($row['periodo_analisis'] ?? '')
                    ];
                }
            }
            sqlsrv_free_stmt($stmt);

            if (empty($versiones)) {
                $versiones = [['nombre' => 'Por defecto', 'periodo' => '']];
            }

            return $versiones;
        } catch (Exception $e) {
            error_log("Error en Distribucion::obtenerNombresDistribuciones: " . $e->getMessage());
            return [['nombre' => 'Por defecto', 'periodo' => '']];
        }
    }

    /**
     * Eliminar una versión de distribución guardada por nombre, país y temporada
     */
    public function eliminarDistribucion($pais, $temporada, $nombreDistribucion) {
        try {
            if (!$this->cid_sistemas) {
                throw new Exception("Error de conexión a la base de datos.");
            }

            $nombre = trim($nombreDistribucion);
            if (empty($nombre) || strtolower($nombre) === 'por defecto') {
                throw new Exception("La versión 'Por defecto' no se puede eliminar.");
            }

            $pais = strtolower(trim($pais));
            $sql = "DELETE FROM dbo.FP_T_DISTRIBUCION_COMPRAS_CANAL 
                    WHERE pais = ? AND temporada = ? AND nombre_distribucion = ?";
            
            $params = [$pais, $temporada, $nombre];
            $stmt = sqlsrv_query($this->cid_sistemas, $sql, $params);

            if ($stmt === false) {
                throw new Exception("Error SQL al eliminar la versión: " . print_r(sqlsrv_errors(), true));
            }

            $filasAfectadas = sqlsrv_rows_affected($stmt);
            sqlsrv_free_stmt($stmt);

            return [
                'success' => true,
                'message' => "Versión '$nombre' eliminada correctamente ($filasAfectadas registros eliminados)."
            ];
        } catch (Exception $e) {
            error_log("Error en Distribucion::eliminarDistribucion: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener reales históricos comparativos por canal y mes (Unidades y Facturación s/IVA)
     * para los cuadros resumen de Análisis 2B.
     *
     * Usa BI_SALES_LAKERS en Argentina (misma fuente y criterio que BI Sales Gerencia:
     * unidades netas sin PACKAGING/_KITS/CONCEPTO e importe neto s/IVA total), con fallback
     * a RO_VENTAS_COMERCIAL si BI_SALES_LAKERS no existiera (ej. Uruguay).
     */
    public function obtenerRealComparativoPorCanal($fechaDesde, $fechaHasta, $tasasBcra = []) {
        $resultado = [
            'fuente' => 'BI_SALES_LAKERS',
            'por_canal' => []
        ];
        if (!$this->cid_power || empty($fechaDesde) || empty($fechaHasta)) {
            return $resultado;
        }

        // Asegurar tasas BCRA desde la conexión 'apps' ya abierta si vinieron vacías
        if (empty($tasasBcra) && $this->cid_sistemas) {
            $anioActual = (int)date('Y');
            $sqlTc = "
                SELECT YEAR(d.Fecha) AS Anio, MONTH(d.Fecha) AS Mes, d.Vendedor AS Valor
                FROM dbo.dolar_oficial_bcra d
                INNER JOIN (
                    SELECT YEAR(Fecha) AS Anio, MONTH(Fecha) AS Mes, MAX(Fecha) AS FechaMax
                    FROM dbo.dolar_oficial_bcra
                    WHERE YEAR(Fecha) BETWEEN ? AND ?
                    GROUP BY YEAR(Fecha), MONTH(Fecha)
                ) cierre ON YEAR(d.Fecha) = cierre.Anio AND MONTH(d.Fecha) = cierre.Mes AND d.Fecha = cierre.FechaMax
                ORDER BY Anio, Mes
            ";
            $stmtTc = sqlsrv_query($this->cid_sistemas, $sqlTc, [$anioActual - 2, $anioActual + 2]);
            if ($stmtTc !== false) {
                while ($rTc = sqlsrv_fetch_array($stmtTc, SQLSRV_FETCH_ASSOC)) {
                    $tasasBcra[(int)$rTc['Mes'] . '-' . (int)$rTc['Anio']] = (float)$rTc['Valor'];
                }
                sqlsrv_free_stmt($stmtTc);
            }
        }

        try {
            $stmtChk = sqlsrv_query(
                $this->cid_power,
                "SELECT CASE WHEN OBJECT_ID('dbo.BI_SALES_LAKERS', 'U') IS NOT NULL
                               OR OBJECT_ID('dbo.BI_SALES_LAKERS', 'V') IS NOT NULL
                             THEN 1 ELSE 0 END AS existe"
            );
            $usaBiSales = false;
            if ($stmtChk !== false && ($rChk = sqlsrv_fetch_array($stmtChk, SQLSRV_FETCH_ASSOC))) {
                $usaBiSales = (int)$rChk['existe'] === 1;
                sqlsrv_free_stmt($stmtChk);
            }

            if ($usaBiSales) {
                $sql = "
                    SELECT
                        UPPER(LTRIM(RTRIM(CANAL))) AS CANAL,
                        MONTH(FECHA) AS MES,
                        YEAR(FECHA) AS ANIO,
                        SUM(CASE WHEN ISNULL(RUBRO, '') NOT IN ('PACKAGING', '_KITS', 'CONCEPTO')
                                 THEN CAST(CANTIDAD AS FLOAT) ELSE 0 END) AS UNIDADES,
                        SUM(CAST(IMPORTE AS FLOAT)) AS IMPORTE_ARS
                    FROM dbo.BI_SALES_LAKERS
                    WHERE FECHA >= ? AND FECHA <= ?
                      AND CANAL IN ('LOCALES PROPIOS', 'FRANQUICIAS', 'MAYORISTAS', 'ECOMMERCE')
                    GROUP BY UPPER(LTRIM(RTRIM(CANAL))), MONTH(FECHA), YEAR(FECHA)
                ";
            } else {
                $resultado['fuente'] = 'RO_VENTAS_COMERCIAL';
                $sql = "
                    SELECT
                        CASE
                            WHEN CANAL = 'CENTRAL' AND DESC_SUCURSAL = 'MAYORISTAS' THEN 'MAYORISTAS'
                            WHEN CANAL = 'CENTRAL' AND DESC_SUCURSAL = 'FRANQUICIAS' THEN 'FRANQUICIAS'
                            WHEN CANAL = 'LOCALES PROPIOS' AND DESC_SUCURSAL = 'ECOMMERCE' THEN 'ECOMMERCE'
                            WHEN CANAL = 'LOCALES PROPIOS' THEN 'LOCALES PROPIOS'
                            ELSE UPPER(LTRIM(RTRIM(CANAL)))
                        END AS CANAL,
                        MONTH(FECHA_VENTA) AS MES,
                        YEAR(FECHA_VENTA) AS ANIO,
                        SUM(CASE WHEN ISNULL(RUBRO, '') NOT IN ('PACKAGING', '_KITS', 'CONCEPTO')
                                 THEN CAST(CANT_VEND AS FLOAT) ELSE 0 END) AS UNIDADES,
                        SUM(CAST(CANT_VEND AS FLOAT) * ISNULL(CAST(PRECIO AS FLOAT), 0)) AS IMPORTE_ARS
                    FROM dbo.RO_VENTAS_COMERCIAL
                    WHERE FECHA_VENTA >= ? AND FECHA_VENTA <= ?
                      AND CANAL IN ('CENTRAL', 'LOCALES PROPIOS')
                    GROUP BY
                        CASE
                            WHEN CANAL = 'CENTRAL' AND DESC_SUCURSAL = 'MAYORISTAS' THEN 'MAYORISTAS'
                            WHEN CANAL = 'CENTRAL' AND DESC_SUCURSAL = 'FRANQUICIAS' THEN 'FRANQUICIAS'
                            WHEN CANAL = 'LOCALES PROPIOS' AND DESC_SUCURSAL = 'ECOMMERCE' THEN 'ECOMMERCE'
                            WHEN CANAL = 'LOCALES PROPIOS' THEN 'LOCALES PROPIOS'
                            ELSE UPPER(LTRIM(RTRIM(CANAL)))
                        END,
                        MONTH(FECHA_VENTA), YEAR(FECHA_VENTA)
                ";
            }

            $stmt = sqlsrv_query($this->cid_power, $sql, [$fechaDesde, $fechaHasta]);
            if ($stmt === false) {
                error_log("Error en obtenerRealComparativoPorCanal: " . print_r(sqlsrv_errors(), true));
                return $resultado;
            }

            $tasaFallback = !empty($tasasBcra) ? (float)end($tasasBcra) : 0.0;

            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $canal = trim($row['CANAL'] ?? '');
                $mes = (int)$row['MES'];
                $anio = (int)$row['ANIO'];
                $mesClave = "$mes-$anio";

                $unidades = (float)($row['UNIDADES'] ?? 0);
                $impArs = (float)($row['IMPORTE_ARS'] ?? 0);
                $tasaMes = (float)($tasasBcra[$mesClave] ?? $tasaFallback);
                $impUsd = ($tasaMes > 1.0) ? ($impArs / $tasaMes) : null;

                if (!isset($resultado['por_canal'][$canal])) {
                    $resultado['por_canal'][$canal] = [];
                }
                $resultado['por_canal'][$canal][$mesClave] = [
                    'unidades' => round($unidades),
                    'ars' => round($impArs),
                    'usd' => $impUsd !== null ? round($impUsd, 2) : null,
                    'tasa_bcra' => $tasaMes > 1.0 ? $tasaMes : null
                ];
            }
            sqlsrv_free_stmt($stmt);
        } catch (Exception $e) {
            error_log("Error en Distribucion::obtenerRealComparativoPorCanal: " . $e->getMessage());
        }

        return $resultado;
    }

    public function __destruct() {
        if ($this->cid_power) {
            sqlsrv_close($this->cid_power);
        }
        if ($this->cid_sistemas) {
            sqlsrv_close($this->cid_sistemas);
        }
    }
}
?>
