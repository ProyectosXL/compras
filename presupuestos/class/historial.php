<?php
require_once __DIR__.'/../../class/conexion.php';

class Historial {
    private $cid;
    private $nameServer;

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

    /**
     * Guarda un presupuesto proyectado en la base de datos.
     *
     * @param string $nombrePresupuesto El nombre único para el presupuesto.
     * @param string $temporada 'verano' o 'invierno'.
     * @param string $pais 'argentina' o 'uruguay'.
     * @param array $filas Los datos de las filas a guardar.
     * @return array Resultado de la operación.
     */
    public function guardarPresupuesto($nombrePresupuesto, $temporada, $pais, $filas, $fechaGuardado) {
        if (!$this->cid) {
            return ['success' => false, 'message' => 'Error de conexión a la base de datos.'];
        }

        try {
            // Iniciar transacción
            if (sqlsrv_begin_transaction($this->cid) === false) {
                 throw new Exception("No se pudo iniciar la transacción: " . print_r(sqlsrv_errors(), true));
            }

            $sql = "INSERT INTO RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO (
                        nombre_presupuesto, fecha_guardado, temporada, pais, rubro,
                        categoria_padre, stock_proyectado, indice_variacion_original,
                        indice_verano_variacion, venta_verano_anterior, venta_proyectada_verano,
                        indice_invierno_variacion, venta_invierno_anterior, venta_proyectada_invierno,
                        compra_proyectada
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $registrosGuardados = 0;
            foreach ($filas as $fila) {
                $params = [
                    $nombrePresupuesto,
                    $fechaGuardado,
                    $temporada,
                    $pais,
                    $fila['rubro'] ?? null,
                    $fila['categoria_padre'] ?? null,
                    // Cast quantity columns to int
                    (int)($fila['stock_proyectado'] ?? 0),
                    // Decimal columns
                    (float)($fila['indice_variacion_original'] ?? 0),
                    (float)($fila['indice_verano_variacion'] ?? 0),
                    // Cast quantity columns to int
                    (int)($fila['venta_verano_anterior'] ?? 0),
                    (int)($fila['venta_proyectada_verano'] ?? 0),
                    // Decimal column
                    (float)($fila['indice_invierno_variacion'] ?? 0),
                    // Cast quantity columns to int
                    (int)($fila['venta_invierno_anterior'] ?? 0),
                    (int)($fila['venta_proyectada_invierno'] ?? 0),
                    (int)($fila['compra_proyectada'] ?? 0)
                ];

                $stmt = sqlsrv_query($this->cid, $sql, $params);
                if ($stmt === false) {
                    throw new Exception("Error al insertar fila: " . print_r(sqlsrv_errors(), true));
                }
                $registrosGuardados += sqlsrv_rows_affected($stmt);
                sqlsrv_free_stmt($stmt);
            }

            // Confirmar transacción
            sqlsrv_commit($this->cid);

            return [
                'success' => true,
                'message' => "Presupuesto guardado correctamente con el nombre '{$nombrePresupuesto}'.",
                'registros_guardados' => $registrosGuardados
            ];

        } catch (Exception $e) {
            // Revertir transacción en caso de error
            if ($this->cid) {
                sqlsrv_rollback($this->cid);
            }
            error_log("Error en guardarPresupuesto: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al guardar el presupuesto: ' . $e->getMessage()];
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
            $sql = "SELECT * FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO";
            $where = [];
            $params = [];

            // Filtro por término de búsqueda (en rubro y categoría)
            if (!empty($filtros['termino'])) {
                $where[] = "(rubro LIKE ? OR categoria_padre LIKE ?)";
                $params[] = '%' . $filtros['termino'] . '%';
                $params[] = '%' . $filtros['termino'] . '%';
            }

            // Filtro por rubro específico
            if (!empty($filtros['rubro'])) {
                $where[] = "rubro = ?";
                $params[] = $filtros['rubro'];
            }

            // Filtro por categoría específica
            if (!empty($filtros['categoria'])) {
                $where[] = "categoria_padre = ?";
                $params[] = $filtros['categoria'];
            }

            // Filtro por rango de fechas
            if (!empty($filtros['fecha_desde'])) {
                $where[] = "fecha_guardado >= ?";
                $params[] = $filtros['fecha_desde'];
            }
            if (!empty($filtros['fecha_hasta'])) {
                // Agregamos un día para incluir todo el día de la fecha hasta
                $fechaHasta = new DateTime($filtros['fecha_hasta']);
                $fechaHasta->modify('+1 day');
                $where[] = "fecha_guardado < ?";
                $params[] = $fechaHasta->format('Y-m-d');
            }

            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }

            $sql .= " ORDER BY fecha_guardado DESC";

            $stmt = sqlsrv_query($this->cid, $sql, $params);
            if ($stmt === false) {
                throw new Exception("Error al buscar en el historial: " . print_r(sqlsrv_errors(), true));
            }

            $resultados = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $resultados[] = $row;
            }
            sqlsrv_free_stmt($stmt);

            return ['success' => true, 'data' => $resultados];

        } catch (Exception $e) {
            error_log("Error en buscarHistorial: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al buscar en el historial: ' . $e->getMessage()];
        }
    }

    public function __destruct() {
        if ($this->cid) {
            sqlsrv_close($this->cid);
        }
    }
}
