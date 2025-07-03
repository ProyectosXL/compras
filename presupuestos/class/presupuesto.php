
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

 }

?>