
<?php
class Compras {

    private $cid_central;
    private $nameServer;

    function __construct(){
        // Determinar el país desde la sesión o parámetro
        $this->nameServer = $this->determinarBaseDatos();
        
        require_once __DIR__.'/../../Class/conexion.php';
        $conexion = new Conexion();
        $this->cid_central = $conexion->conectar($this->nameServer);
    }

    /**
     * Determinar qué base de datos usar según el país seleccionado
     */
    private function determinarBaseDatos() {
        // Iniciar sesión si no está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $pais = 'argentina'; // Por defecto
        
        // Verificar en orden de prioridad
        if (isset($_GET['pais'])) {
            $pais = strtolower($_GET['pais']);
        } elseif (isset($_POST['pais'])) {
            $pais = strtolower($_POST['pais']);
        } elseif (isset($_SESSION['pais_seleccionado'])) {
            $pais = strtolower($_SESSION['pais_seleccionado']);
        }
        
        // Log para debug
        error_log("Compras - País determinado: $pais");
        
        // Determinar el nombre del servidor para compras
        switch ($pais) {
            case 'uruguay':
            case 'uy':
                return 'uy';
            case 'argentina':
            case 'ar':
            default:
                return 'central';
        }
    }

    /**
     * Cambiar país dinámicamente
     */
    public function cambiarPais($pais) {
        $this->nameServer = $this->determinarBaseDatos();
        
        // Reconectar con la nueva base
        require_once __DIR__.'/../../Class/conexion.php';
        $conexion = new Conexion();
        
        // Cerrar conexión anterior si existe
        if ($this->cid_central) {
            sqlsrv_close($this->cid_central);
        }
        
        $this->cid_central = $conexion->conectar($this->nameServer);
        
        // Guardar en sesión
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['pais_seleccionado'] = strtolower($pais);
        
        return $this->cid_central !== false;
    }

    /**
     * Obtener país actual
     */
    public function obtenerPaisActual() {
        return $this->nameServer === 'uy' ? 'uruguay' : 'argentina';
    }

    /**
     * Obtener información de la conexión actual
     */
    public function obtenerInfoConexion() {
        return [
            'pais' => $this->obtenerPaisActual(),
            'servidor' => $this->nameServer,
            'conectado' => $this->cid_central !== false,
            'modulo' => 'compras'
        ];
    }

    private function getArray($sql){
        try {
            if (!$this->cid_central) {
                throw new Exception("Error de conexión a la base de datos {$this->nameServer}");
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
            error_log("Error en getArray Compras ({$this->nameServer}): " . $e->getMessage());
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
                throw new Exception("Error de conexión a la base de datos {$this->nameServer}");
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
                $errorMessage = "Error al ejecutar consulta en {$this->nameServer}: ";
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

            // Log para debug
            error_log("Compras obtenidas desde {$this->nameServer}: " . count($resultados) . " registros");

            return $resultados;

        } catch (Exception $e) {
            error_log("Error en obtenerComprasDetalle ({$this->nameServer}): " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage(),
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer
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
                throw new Exception("Error de conexión a la base de datos {$this->nameServer}");
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
                $errorMessage = "Error al ejecutar búsqueda en {$this->nameServer}: ";
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
            
            // Log para debug
            error_log("Búsqueda compras desde {$this->nameServer}: " . count($resultados) . " registros para término: $termino");
            
            return $resultados;

        } catch (Exception $e) {
            error_log("Error en buscarComprasDetalle ({$this->nameServer}): " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage(),
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer
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
            
            $resultado = $this->getArray($sql);
            
            // Log para debug
            error_log("Proveedores obtenidos desde {$this->nameServer}: " . count($resultado) . " proveedores");
            
            return $resultado;
        } catch (Exception $e) {
            error_log("Error en obtenerProveedores ({$this->nameServer}): " . $e->getMessage());
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
            
            $resultado = $this->getArray($sql);
            
            // Log para debug
            error_log("Rubros obtenidos desde {$this->nameServer}: " . count($resultado) . " rubros");
            
            return $resultado;
        } catch (Exception $e) {
            error_log("Error en obtenerRubros ({$this->nameServer}): " . $e->getMessage());
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
                throw new Exception("Error al obtener resumen desde {$this->nameServer}: " . print_r(sqlsrv_errors(), true));
            }

            $resultado = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);

            // Convertir valores numéricos
            if ($resultado) {
                $resultado['total_verano'] = (float)($resultado['total_verano'] ?? 0);
                $resultado['total_invierno'] = (float)($resultado['total_invierno'] ?? 0);
                $resultado['total_atemporal'] = (float)($resultado['total_atemporal'] ?? 0);
                $resultado['total_general'] = (float)($resultado['total_general'] ?? 0);
                
                // Agregar info del país/servidor
                $resultado['pais'] = $this->obtenerPaisActual();
                $resultado['servidor'] = $this->nameServer;
            }

            // Log para debug
            error_log("Resumen compras desde {$this->nameServer}: " . ($resultado['total_registros'] ?? 0) . " registros totales");

            return $resultado ?: [];

        } catch (Exception $e) {
            error_log("Error en obtenerResumenCompras ({$this->nameServer}): " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage(),
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer
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
                    'mensaje' => "No se pudo establecer conexión con la base de datos {$this->nameServer}",
                    'pais' => $this->obtenerPaisActual(),
                    'servidor' => $this->nameServer,
                    'modulo' => 'compras'
                ];
            }

            // Realizar una consulta simple para probar la conexión
            $sql = "SELECT GETDATE() as fecha_actual, @@SERVERNAME as servidor, DB_NAME() as base_datos";
            $stmt = sqlsrv_query($this->cid_central, $sql);
            
            if ($stmt === false) {
                return [
                    'conexion' => false,
                    'mensaje' => "Error al ejecutar consulta de prueba en {$this->nameServer}: " . print_r(sqlsrv_errors(), true),
                    'pais' => $this->obtenerPaisActual(),
                    'servidor' => $this->nameServer,
                    'modulo' => 'compras'
                ];
            }

            $resultado = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);

            return [
                'conexion' => true,
                'mensaje' => "Conexión exitosa a {$this->nameServer} (módulo compras)",
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer,
                'modulo' => 'compras',
                'datos' => $resultado
            ];

        } catch (Exception $e) {
            return [
                'conexion' => false,
                'mensaje' => "Error en prueba de conexión ({$this->nameServer}): " . $e->getMessage(),
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer,
                'modulo' => 'compras'
            ];
        }
    }

    /**
     * Método estático para cambiar país desde el frontend
     */
    public static function cambiarPaisStatic($pais) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['pais_seleccionado'] = strtolower($pais);
        
        $servidor = $pais === 'uruguay' ? 'uy' : 'central';
        
        return [
            'success' => true,
            'mensaje' => "País cambiado a " . ($pais === 'uruguay' ? 'Uruguay' : 'Argentina') . " (módulo compras)",
            'pais' => $pais,
            'servidor' => $servidor,
            'modulo' => 'compras'
        ];
    }

    /**
     * Obtener estadísticas detalladas por país
     */
    public function obtenerEstadisticasPorPais() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_ordenes,
                        COUNT(DISTINCT NOM_PROVEE) as proveedores_activos,
                        COUNT(DISTINCT RUBRO) as rubros_activos,
                        AVG(VERANO + INVIERNO + ATEMPORAL) as promedio_orden,
                        MAX(VERANO + INVIERNO + ATEMPORAL) as orden_maxima,
                        MIN(VERANO + INVIERNO + ATEMPORAL) as orden_minima
                    FROM RO_V_COMPRAS_PEND_PRESUPUESTO_COMPRAS_DET
                    WHERE (VERANO + INVIERNO + ATEMPORAL) > 0";

            $stmt = sqlsrv_query($this->cid_central, $sql);
            
            if ($stmt === false) {
                throw new Exception("Error obteniendo estadísticas: " . print_r(sqlsrv_errors(), true));
            }

            $resultado = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);

            if ($resultado) {
                $resultado['pais'] = $this->obtenerPaisActual();
                $resultado['servidor'] = $this->nameServer;
                $resultado['timestamp'] = date('Y-m-d H:i:s');
                $resultado['promedio_orden'] = round((float)($resultado['promedio_orden'] ?? 0), 2);
                $resultado['orden_maxima'] = (float)($resultado['orden_maxima'] ?? 0);
                $resultado['orden_minima'] = (float)($resultado['orden_minima'] ?? 0);
            }

            return $resultado ?: [];

        } catch (Exception $e) {
            error_log("Error en obtenerEstadisticasPorPais ({$this->nameServer}): " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage(),
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer
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