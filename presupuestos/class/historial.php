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
        return $pais === 'uruguay' ? 'uy' : 'central';
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
    public function guardarPresupuesto($nombrePresupuesto, $temporada, $pais, $filas) {
        if (!$this->cid) {
            return ['success' => false, 'message' => 'Error de conexión a la base de datos.'];
        }

        try {
            // Iniciar transacción
            if (sqlsrv_begin_transaction($this->cid) === false) {
                 throw new Exception("No se pudo iniciar la transacción: " . print_r(sqlsrv_errors(), true));
            }

            $sql = "INSERT INTO RO.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO (
                        nombre_presupuesto, fecha_guardado, temporada, pais, rubro,
                        categoria_padre, stock_proyectado, indice_variacion_original,
                        indice_verano_variacion, venta_verano_anterior, venta_proyectada_verano,
                        indice_invierno_variacion, venta_invierno_anterior, venta_proyectada_invierno,
                        compra_proyectada
                    ) VALUES (?, GETDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $registrosGuardados = 0;
            foreach ($filas as $fila) {
                $params = [
                    $nombrePresupuesto,
                    $temporada,
                    $pais,
                    $fila['rubro'] ?? null,
                    $fila['categoria_padre'] ?? null,
                    $fila['stock_proyectado'] ?? 0,
                    $fila['indice_variacion_original'] ?? 0,
                    $fila['indice_verano_variacion'] ?? 0,
                    $fila['venta_verano_anterior'] ?? 0,
                    $fila['venta_proyectada_verano'] ?? 0,
                    $fila['indice_invierno_variacion'] ?? 0,
                    $fila['venta_invierno_anterior'] ?? 0,
                    $fila['venta_proyectada_invierno'] ?? 0,
                    $fila['compra_proyectada'] ?? 0
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

    public function __destruct() {
        if ($this->cid) {
            sqlsrv_close($this->cid);
        }
    }
}
