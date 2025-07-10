
<?php
class Compras {

    private $cid_central;

    function __construct(){
        require_once __DIR__.'/../../Class/conexion.php';
        $conexion = new Conexion();
        $this->cid_central = $conexion->conectar('central');
    }

    private function getArray($sql){
        try {
            if (!$this->cid_central) {
                throw new Exception("Error de conexión a la base de datos central");
            }

            $stmt = sqlsrv_query($this->cid_central, $sql);
            
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
            error_log("Error en getArray Compras: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el detalle de compras pendientes
     * @return array Datos de compras pendientes o array vacío en caso de error
     */
    public function obtenerComprasDetalle($filtros = []){
        try {
            if (!$this->cid_central) {
                throw new Exception("Error de conexión a la base de datos central");
            }

            // Construir consulta con filtros
            $sql = "SELECT * FROM RO_V_COMPRAS_PEND_PRESUPUESTO_COMPRAS_DET";
            $whereConditions = [];
            $params = [];

            // Aplicar filtros
            if (!empty($filtros['proveedor'])) {
                $whereConditions[] = "NOM_PROVEE LIKE ?";
                $params[] = '%' . $filtros['proveedor'] . '%';
            }

            if (!empty($filtros['rubro'])) {
                $whereConditions[] = "RUBRO LIKE ?";
                $params[] = '%' . $filtros['rubro'] . '%';
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "FEC_EMISIO >= ?";
                $params[] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "FEC_EMISIO <= ?";
                $params[] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['temporada'])) {
                switch ($filtros['temporada']) {
                    case 'verano':
                        $whereConditions[] = "VERANO > 0";
                        break;
                    case 'invierno':
                        $whereConditions[] = "INVIERNO > 0";
                        break;
                    case 'atemporal':
                        $whereConditions[] = "ATEMPORAL > 0";
                        break;
                }
            }

            // Agregar WHERE si hay condiciones
            if (!empty($whereConditions)) {
                $sql .= " WHERE " . implode(" AND ", $whereConditions);
            }

            // Ordenar por fecha y número de orden
            $sql .= " ORDER BY FEC_EMISIO DESC, N_ORDEN_CO DESC";

            // Ejecutar consulta
            if (!empty($params)) {
                $stmt = sqlsrv_query($this->cid_central, $sql, $params);
            } else {
                $stmt = sqlsrv_query($this->cid_central, $sql);
            }
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $errorMessage = "Error al ejecutar consulta: ";
                foreach($errors as $error) {
                    $errorMessage .= $error['message'] . " ";
                }
                throw new Exception($errorMessage);
            }

            $resultados = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                // Formatear fecha si es DateTime
                if (isset($row['FEC_EMISIO']) && $row['FEC_EMISIO'] instanceof DateTime) {
                    $row['FEC_EMISIO'] = $row['FEC_EMISIO']->format('Y-m-d');
                }
                
                // Convertir valores numéricos
                $row['VERANO'] = (float)($row['VERANO'] ?? 0);
                $row['INVIERNO'] = (float)($row['INVIERNO'] ?? 0);
                $row['ATEMPORAL'] = (float)($row['ATEMPORAL'] ?? 0);
                
                $resultados[] = $row;
            }

            // Liberar recursos
            sqlsrv_free_stmt($stmt);

            return $resultados;

        } catch (Exception $e) {
            error_log("Error en obtenerComprasDetalle: " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    /**
     * Buscar en compras detalle por término
     * @param string $termino Término de búsqueda
     * @param array $filtros Filtros adicionales
     * @return array Resultados de búsqueda
     */
    public function buscarComprasDetalle($termino, $filtros = []){
        try {
            if (!$this->cid_central) {
                throw new Exception("Error de conexión a la base de datos central");
            }

            // Construir consulta de búsqueda
            $sql = "SELECT * FROM RO_V_COMPRAS_PEND_PRESUPUESTO_COMPRAS_DET WHERE (
                        N_ORDEN_CO LIKE ? OR 
                        NOM_PROVEE LIKE ? OR 
                        COD_ARTICU LIKE ? OR 
                        DESCRIPCIO LIKE ? OR 
                        RUBRO LIKE ? OR 
                        CATEGORIA_PADRE LIKE ?
                    )";

            $params = [
                '%' . $termino . '%',
                '%' . $termino . '%',
                '%' . $termino . '%',
                '%' . $termino . '%',
                '%' . $termino . '%',
                '%' . $termino . '%'
            ];

            // Aplicar filtros adicionales
            if (!empty($filtros['proveedor'])) {
                $sql .= " AND NOM_PROVEE LIKE ?";
                $params[] = '%' . $filtros['proveedor'] . '%';
            }

            if (!empty($filtros['rubro'])) {
                $sql .= " AND RUBRO LIKE ?";
                $params[] = '%' . $filtros['rubro'] . '%';
            }

            if (!empty($filtros['fecha_desde'])) {
                $sql .= " AND FEC_EMISIO >= ?";
                $params[] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $sql .= " AND FEC_EMISIO <= ?";
                $params[] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['temporada'])) {
                switch ($filtros['temporada']) {
                    case 'verano':
                        $sql .= " AND VERANO > 0";
                        break;
                    case 'invierno':
                        $sql .= " AND INVIERNO > 0";
                        break;
                    case 'atemporal':
                        $sql .= " AND ATEMPORAL > 0";
                        break;
                }
            }

            $sql .= " ORDER BY FEC_EMISIO DESC, N_ORDEN_CO DESC";

            $stmt = sqlsrv_query($this->cid_central, $sql, $params);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $errorMessage = "Error al ejecutar búsqueda: ";
                foreach($errors as $error) {
                    $errorMessage .= $error['message'] . " ";
                }
                throw new Exception($errorMessage);
            }

            $resultados = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                // Formatear fecha si es DateTime
                if (isset($row['FEC_EMISIO']) && $row['FEC_EMISIO'] instanceof DateTime) {
                    $row['FEC_EMISIO'] = $row['FEC_EMISIO']->format('Y-m-d');
                }
                
                // Convertir valores numéricos
                $row['VERANO'] = (float)($row['VERANO'] ?? 0);
                $row['INVIERNO'] = (float)($row['INVIERNO'] ?? 0);
                $row['ATEMPORAL'] = (float)($row['ATEMPORAL'] ?? 0);
                
                $resultados[] = $row;
            }

            sqlsrv_free_stmt($stmt);
            return $resultados;

        } catch (Exception $e) {
            error_log("Error en buscarComprasDetalle: " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener proveedores únicos
     * @return array Lista de proveedores
     */
    public function obtenerProveedores(){
        try {
            $sql = "SELECT DISTINCT NOM_PROVEE 
                    FROM RO_V_COMPRAS_PEND_PRESUPUESTO_COMPRAS_DET 
                    WHERE NOM_PROVEE IS NOT NULL 
                    ORDER BY NOM_PROVEE";
            
            return $this->getArray($sql);
        } catch (Exception $e) {
            error_log("Error en obtenerProveedores: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener rubros únicos
     * @return array Lista de rubros
     */
    public function obtenerRubros(){
        try {
            $sql = "SELECT DISTINCT RUBRO 
                    FROM RO_V_COMPRAS_PEND_PRESUPUESTO_COMPRAS_DET 
                    WHERE RUBRO IS NOT NULL 
                    ORDER BY RUBRO";
            
            return $this->getArray($sql);
        } catch (Exception $e) {
            error_log("Error en obtenerRubros: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener resumen de compras por temporada
     * @param array $filtros Filtros aplicados
     * @return array Resumen de totales
     */
    public function obtenerResumenCompras($filtros = []){
        try {
            $sql = "SELECT 
                        COUNT(*) as total_registros,
                        COUNT(DISTINCT N_ORDEN_CO) as ordenes_unicas,
                        COUNT(DISTINCT NOM_PROVEE) as proveedores_unicos,
                        COUNT(DISTINCT COD_ARTICU) as articulos_unicos,
                        SUM(VERANO) as total_verano,
                        SUM(INVIERNO) as total_invierno,
                        SUM(ATEMPORAL) as total_atemporal,
                        SUM(VERANO + INVIERNO + ATEMPORAL) as total_general
                    FROM RO_V_COMPRAS_PEND_PRESUPUESTO_COMPRAS_DET";

            $whereConditions = [];
            $params = [];

            // Aplicar filtros
            if (!empty($filtros['proveedor'])) {
                $whereConditions[] = "NOM_PROVEE LIKE ?";
                $params[] = '%' . $filtros['proveedor'] . '%';
            }

            if (!empty($filtros['rubro'])) {
                $whereConditions[] = "RUBRO LIKE ?";
                $params[] = '%' . $filtros['rubro'] . '%';
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "FEC_EMISIO >= ?";
                $params[] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "FEC_EMISIO <= ?";
                $params[] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['temporada'])) {
                switch ($filtros['temporada']) {
                    case 'verano':
                        $whereConditions[] = "VERANO > 0";
                        break;
                    case 'invierno':
                        $whereConditions[] = "INVIERNO > 0";
                        break;
                    case 'atemporal':
                        $whereConditions[] = "ATEMPORAL > 0";
                        break;
                }
            }

            // Agregar WHERE si hay condiciones
            if (!empty($whereConditions)) {
                $sql .= " WHERE " . implode(" AND ", $whereConditions);
            }

            // Ejecutar consulta
            if (!empty($params)) {
                $stmt = sqlsrv_query($this->cid_central, $sql, $params);
            } else {
                $stmt = sqlsrv_query($this->cid_central, $sql);
            }
            
            if ($stmt === false) {
                throw new Exception("Error al obtener resumen: " . print_r(sqlsrv_errors(), true));
            }

            $resultado = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);

            // Convertir valores numéricos
            if ($resultado) {
                $resultado['total_verano'] = (float)($resultado['total_verano'] ?? 0);
                $resultado['total_invierno'] = (float)($resultado['total_invierno'] ?? 0);
                $resultado['total_atemporal'] = (float)($resultado['total_atemporal'] ?? 0);
                $resultado['total_general'] = (float)($resultado['total_general'] ?? 0);
            }

            return $resultado ?: [];

        } catch (Exception $e) {
            error_log("Error en obtenerResumenCompras: " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    /**
     * Función de prueba para verificar la conexión
     * @return array Resultado de la prueba de conexión
     */
    public function probarConexion(){
        try {
            if (!$this->cid_central) {
                return [
                    'conexion' => false,
                    'mensaje' => 'No se pudo establecer conexión con la base de datos central'
                ];
            }

            // Realizar una consulta simple para probar la conexión
            $sql = "SELECT GETDATE() as fecha_actual, @@SERVERNAME as servidor";
            $stmt = sqlsrv_query($this->cid_central, $sql);
            
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
     * Destructor para cerrar la conexión
     */
    public function __destruct(){
        if ($this->cid_central) {
            sqlsrv_close($this->cid_central);
        }
    }
}
?>