
<?php
class Ventas {

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
     * Obtiene las ventas de los últimos 6 meses con variación
     */
    public function obtenerVentas6Meses($filtros = []){
        try {
            $sql = "EXEC RO_SP_VENTAS_6_MESES_CON_VARIACION";
            $datos = $this->getArray($sql);
            
            if (empty($datos)) {
                return [];
            }
            
            return $datos;

        } catch (Exception $e) {
            error_log("Error en obtenerVentas6Meses: " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => 'Error al ejecutar consulta de ventas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Buscar en ventas por término
     */
    public function buscarVentas6Meses($termino, $filtros = []){
        try {
            $datos = $this->obtenerVentas6Meses($filtros);
            
            if (isset($datos['error'])) {
                return $datos;
            }

            $terminoLower = strtolower($termino);
            $resultados = array_filter($datos, function($item) use ($terminoLower) {
                $rubro = strtolower($item['RUBRO'] ?? '');
                $categoria = strtolower($item['CATEGORIA_PADRE'] ?? '');
                
                return (strpos($rubro, $terminoLower) !== false) || 
                       (strpos($categoria, $terminoLower) !== false);
            });

            return array_values($resultados);

        } catch (Exception $e) {
            error_log("Error en buscarVentas6Meses: " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener rubros únicos
     */
    public function obtenerRubrosVentas(){
        try {
            $datos = $this->obtenerVentas6Meses();
            if (isset($datos['error'])) {
                return $datos;
            }

            $rubros = [];
            foreach ($datos as $item) {
                $rubro = $item['RUBRO'] ?? '';
                if (!empty($rubro) && !in_array($rubro, $rubros)) {
                    $rubros[] = $rubro;
                }
            }
            
            sort($rubros);
            
            return array_map(function($rubro) {
                return ['RUBRO' => $rubro];
            }, $rubros);
            
        } catch (Exception $e) {
            error_log("Error en obtenerRubrosVentas: " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener categorías únicas
     */
    public function obtenerCategoriasVentas(){
        try {
            $datos = $this->obtenerVentas6Meses();
            if (isset($datos['error'])) {
                return $datos;
            }

            $categorias = [];
            foreach ($datos as $item) {
                $categoria = $item['CATEGORIA_PADRE'] ?? '';
                if (!empty($categoria) && !in_array($categoria, $categorias)) {
                    $categorias[] = $categoria;
                }
            }
            
            sort($categorias);
            
            return array_map(function($categoria) {
                return ['CATEGORIA_PADRE' => $categoria];
            }, $categorias);
            
        } catch (Exception $e) {
            error_log("Error en obtenerCategoriasVentas: " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener resumen de ventas
     */
    public function obtenerResumenVentas($filtros = []){
        try {
            $datos = $this->obtenerVentas6Meses($filtros);
            
            if (isset($datos['error'])) {
                return $datos;
            }

            $resumen = [
                'total_registros' => count($datos),
                'ventas_actuales_total' => 0,
                'ventas_anteriores_total' => 0,
                'variacion_promedio' => 0,
                'items_mejorados' => 0,
                'items_empeorados' => 0,
                'items_estables' => 0
            ];

            foreach ($datos as $item) {
                $resumen['ventas_actuales_total'] += (float)($item['VTA_ULT_60_DIAS'] ?? 0);
                $resumen['ventas_anteriores_total'] += (float)($item['VTA_ULT_60_DIAS_ANO_ANT'] ?? 0);
                
                $indice = (float)($item['INDICE_VARIACION'] ?? 1);
                if ($indice > 1.2) {
                    $resumen['items_mejorados']++;
                } elseif ($indice < 0.8) {
                    $resumen['items_empeorados']++;
                } else {
                    $resumen['items_estables']++;
                }
            }

            if ($resumen['ventas_anteriores_total'] > 0) {
                $resumen['variacion_promedio'] = round(
                    (($resumen['ventas_actuales_total'] - $resumen['ventas_anteriores_total']) / $resumen['ventas_anteriores_total']) * 100,
                    2
                );
            }

            return $resumen;

        } catch (Exception $e) {
            error_log("Error en obtenerResumenVentas: " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    /**
     * Función de prueba para verificar la conexión
     */
    public function probarConexion(){
        try {
            if (!$this->cid_apps) {
                return [
                    'conexion' => false,
                    'mensaje' => 'No se pudo establecer conexión con la base de datos apps'
                ];
            }

            $sql = "SELECT GETDATE() as fecha_actual, @@SERVERNAME as servidor, DB_NAME() as base_datos";
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
                'mensaje' => 'Conexión exitosa a base apps',
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
     * Destructor para cerrar la conexión
     */
    public function __destruct(){
        if ($this->cid_apps) {
            sqlsrv_close($this->cid_apps);
        }
    }
}
?>