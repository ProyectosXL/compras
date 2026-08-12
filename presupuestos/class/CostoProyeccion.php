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

