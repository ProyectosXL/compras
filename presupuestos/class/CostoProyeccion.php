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

                // Conservar inc_fob y markups existentes si los tiene
                $existente = $paramsActuales[$clave] ?? null;
                $incFob = $existente ? (float)$existente['inc_fob'] : 0.0;
                $vcosto = round($costoUSD * (1 + $incFob / 100), 2);

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

