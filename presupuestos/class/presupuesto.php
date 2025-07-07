
<?php
class Presupuesto {

    private $cid_apps;

    function __construct(){
        require_once __DIR__.'/../../Class/conexion.php';
        $conexion = new Conexion();
        $this->cid_apps = $conexion->conectar('apps');
    }

    private function getArray($sql){
        try {
            if (!$this->cid_apps) {
                throw new Exception("Error de conexión a la base de datos");
            }

            $stmt = sqlsrv_query($this->cid_apps, $sql);
            
            if ($stmt === false) {
                throw new Exception("Error en la consulta: " . print_r(sqlsrv_errors(), true));
            }

            $v = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $v[] = $row;
            }

            return $v;
        }
        catch (Exception $e) {
            error_log("Error en getArray: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Ejecuta el procedimiento almacenado RO_SP_VENTAS_PRESUPUESTO_COMPRAS
     * y retorna los resultados para el cálculo de presupuesto de compras
     * 
     * @return array Datos del presupuesto de compras o array vacío en caso de error
     */
    public function obtenerPresupuestoCompras(){
        try {
            if (!$this->cid_apps) {
                throw new Exception("Error de conexión a la base de datos apps");
            }

            // Ejecutar el procedimiento almacenado
            $sql = "EXEC RO_SP_VENTAS_PRESUPUESTO_COMPRAS";
            
            $stmt = sqlsrv_query($this->cid_apps, $sql);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $errorMessage = "Error al ejecutar procedimiento almacenado: ";
                foreach($errors as $error) {
                    $errorMessage .= $error['message'] . " ";
                }
                throw new Exception($errorMessage);
            }

            $resultados = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $resultados[] = $row;
            }

            // Liberar recursos
            sqlsrv_free_stmt($stmt);

            return $resultados;

        } catch (Exception $e) {
            error_log("Error en obtenerPresupuestoCompras: " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    /**
     * Función de prueba para verificar la conexión
     * 
     * @return array Resultado de la prueba de conexión
     */
    public function probarConexion(){
        try {
            if (!$this->cid_apps) {
                return [
                    'conexion' => false,
                    'mensaje' => 'No se pudo establecer conexión con la base de datos apps'
                ];
            }

            // Realizar una consulta simple para probar la conexión
            $sql = "SELECT GETDATE() as fecha_actual, @@SERVERNAME as servidor";
            $stmt = sqlsrv_query($this->cid_apps, $sql);
            
            if ($stmt === false) {
                return [
                    'conexion' => false,
                    'mensaje' => 'Error al ejecutar consulta de prueba: ' . print_r(sqlsrv_errors(), true)
                ];
            }

            $resultado = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);

            return [
                'conexion' => true,
                'mensaje' => 'Conexión exitosa',
                'datos' => $resultado
            ];

        } catch (Exception $e) {
            return [
                'conexion' => false,
                'mensaje' => 'Error en prueba de conexión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene información de las temporadas actuales
     * (Tabla utilizada por el procedimiento almacenado)
     * 
     * @return array Temporadas actuales
     */
    public function obtenerTemporadasActuales(){
        try {
            $sql = "SELECT * FROM dbo.RO_TABLA_TEMPORADAS_ACTUALES ORDER BY TEMPORADA";
            return $this->getArray($sql);
        } catch (Exception $e) {
            error_log("Error en obtenerTemporadasActuales: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Destructor para cerrar la conexión
     */
    public function __destruct(){
        if ($this->cid_apps) {
            sqlsrv_close($this->cid_apps);
        }
    }
}
?>