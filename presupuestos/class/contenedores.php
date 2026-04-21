
<?php
class Contenedores {

    private $cid_central;
    private $nameServer;

    function __construct(){
        $this->nameServer = $this->determinarBaseDatos();

        require_once __DIR__.'/../../Class/conexion.php';
        $conexion = new Conexion();
        $this->cid_central = $conexion->conectar($this->nameServer);
    }

    /**
     * Determinar qué base de datos usar según el país seleccionado
     */
    private function determinarBaseDatos() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $pais = 'argentina'; // Por defecto

        if (isset($_GET['pais'])) {
            $pais = strtolower($_GET['pais']);
        } elseif (isset($_POST['pais'])) {
            $pais = strtolower($_POST['pais']);
        } elseif (isset($_SESSION['pais_seleccionado'])) {
            $pais = strtolower($_SESSION['pais_seleccionado']);
        }

        error_log("Contenedores - País determinado: $pais");

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
     * Obtener país actual
     */
    public function obtenerPaisActual() {
        return $this->nameServer === 'uy' ? 'uruguay' : 'argentina';
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
            error_log("Error en getArray Contenedores ({$this->nameServer}): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el detalle de contenedores (OC Pendientes enriquecidas con datos de importación)
     * @param array $filtros Filtros opcionales
     * @return array Datos de contenedores o array vacío en caso de error
     */
    public function obtenerContenedoresDetalle($filtros = []){
        try {
            if (!$this->cid_central) {
                throw new Exception("Error de conexión a la base de datos {$this->nameServer}");
            }

            $sql = "SELECT
                        ISNULL(NULLIF(LTRIM(RTRIM(cpa.OBSERVACIO)), ''), 'Sin asignar') AS CONTENEDOR,
                        oc.NOM_PROVEE,
                        oc.RUBRO,
                        SUM(oc.VERANO) AS VERANO,
                        SUM(oc.INVIERNO) AS INVIERNO,
                        SUM(oc.VERANO + oc.INVIERNO) AS TOTAL
                    FROM RO_V_COMPRAS_PEND_PRESUPUESTO_COMPRAS_DET oc
                    LEFT JOIN CPA35 cpa
                        ON oc.N_ORDEN_CO = cpa.N_ORDEN_CO";

            $whereConditions = [];
            $params = [];

            if (!empty($filtros['proveedor'])) {
                $whereConditions[] = "oc.NOM_PROVEE LIKE ?";
                $params[] = '%' . $filtros['proveedor'] . '%';
            }

            if (!empty($filtros['rubro'])) {
                $whereConditions[] = "oc.RUBRO LIKE ?";
                $params[] = '%' . $filtros['rubro'] . '%';
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "oc.FEC_EMISIO >= ?";
                $params[] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "oc.FEC_EMISIO <= ?";
                $params[] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['temporada'])) {
                switch ($filtros['temporada']) {
                    case 'verano':
                        $whereConditions[] = "oc.VERANO > 0";
                        break;
                    case 'invierno':
                        $whereConditions[] = "oc.INVIERNO > 0";
                        break;
                }
            }

            if (!empty($whereConditions)) {
                $sql .= " WHERE " . implode(" AND ", $whereConditions);
            }

            $sql .= " GROUP BY cpa.OBSERVACIO, oc.NOM_PROVEE, oc.RUBRO";
            $sql .= " ORDER BY cpa.OBSERVACIO, oc.NOM_PROVEE, oc.RUBRO";

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
                $row['VERANO']  = (float)($row['VERANO']  ?? 0);
                $row['INVIERNO'] = (float)($row['INVIERNO'] ?? 0);
                $row['TOTAL']   = (float)($row['TOTAL']   ?? 0);
                $resultados[] = $row;
            }

            sqlsrv_free_stmt($stmt);

            error_log("Contenedores obtenidos desde {$this->nameServer}: " . count($resultados) . " registros");

            return $resultados;

        } catch (Exception $e) {
            error_log("Error en obtenerContenedoresDetalle ({$this->nameServer}): " . $e->getMessage());
            return [
                'error'    => true,
                'mensaje'  => $e->getMessage(),
                'pais'     => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer
            ];
        }
    }

    /**
     * Obtener proveedores únicos (desde la vista de OC pendientes)
     */
    public function obtenerProveedores(){
        try {
            $sql = "SELECT DISTINCT NOM_PROVEE
                    FROM RO_V_COMPRAS_PEND_PRESUPUESTO_COMPRAS_DET
                    WHERE NOM_PROVEE IS NOT NULL
                    ORDER BY NOM_PROVEE";

            $resultado = $this->getArray($sql);

            error_log("Proveedores (contenedores) obtenidos desde {$this->nameServer}: " . count($resultado));

            return $resultado;
        } catch (Exception $e) {
            error_log("Error en obtenerProveedores Contenedores ({$this->nameServer}): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener rubros únicos (desde la vista de OC pendientes)
     */
    public function obtenerRubros(){
        try {
            $sql = "SELECT DISTINCT RUBRO
                    FROM RO_V_COMPRAS_PEND_PRESUPUESTO_COMPRAS_DET
                    WHERE RUBRO IS NOT NULL
                    ORDER BY RUBRO";

            $resultado = $this->getArray($sql);

            error_log("Rubros (contenedores) obtenidos desde {$this->nameServer}: " . count($resultado));

            return $resultado;
        } catch (Exception $e) {
            error_log("Error en obtenerRubros Contenedores ({$this->nameServer}): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Función de prueba para verificar la conexión
     */
    public function probarConexion(){
        try {
            if (!$this->cid_central) {
                return [
                    'conexion' => false,
                    'mensaje'  => "No se pudo establecer conexión con la base de datos {$this->nameServer}",
                    'pais'     => $this->obtenerPaisActual(),
                    'servidor' => $this->nameServer,
                    'modulo'   => 'contenedores'
                ];
            }

            $sql  = "SELECT GETDATE() as fecha_actual, @@SERVERNAME as servidor, DB_NAME() as base_datos";
            $stmt = sqlsrv_query($this->cid_central, $sql);

            if ($stmt === false) {
                return [
                    'conexion' => false,
                    'mensaje'  => "Error al ejecutar consulta de prueba en {$this->nameServer}: " . print_r(sqlsrv_errors(), true),
                    'pais'     => $this->obtenerPaisActual(),
                    'servidor' => $this->nameServer,
                    'modulo'   => 'contenedores'
                ];
            }

            $resultado = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);

            return [
                'conexion' => true,
                'mensaje'  => "Conexión exitosa a {$this->nameServer} (módulo contenedores)",
                'pais'     => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer,
                'modulo'   => 'contenedores',
                'datos'    => $resultado
            ];

        } catch (Exception $e) {
            return [
                'conexion' => false,
                'mensaje'  => "Error en prueba de conexión ({$this->nameServer}): " . $e->getMessage(),
                'pais'     => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer,
                'modulo'   => 'contenedores'
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
