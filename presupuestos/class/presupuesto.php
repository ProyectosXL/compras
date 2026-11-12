
<?php
class Presupuesto {

    private $cid_apps;
    private $nameServer;

    function __construct(){
        // Determinar el país desde la sesión o parámetro
        $this->nameServer = $this->determinarBaseDatos();
        
        require_once __DIR__.'/../../Class/conexion.php';
        $conexion = new Conexion();
        $this->cid_apps = $conexion->conectar($this->nameServer);
    }

    /**
     * País de la solicitud en curso: ?pais, POST pais o el de la sesión, en ese orden.
     *
     * Es la ÚNICA regla que decide si una solicitud es de Argentina o de Uruguay, y
     * de ella depende todo lo que es solo de Argentina (parámetros, cobertura nueva,
     * venta editable). Antes vivía solo dentro de determinarBaseDatos() y el resto
     * del código no tenía cómo preguntarla.
     *
     * @return string 'argentina' o 'uruguay'
     */
    public static function paisDeLaSolicitud() {
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

        return in_array($pais, ['uruguay', 'uy'], true) ? 'uruguay' : 'argentina';
    }

    /**
     * Determinar qué base de datos usar según el país seleccionado
     */
    private function determinarBaseDatos() {
        return self::paisDeLaSolicitud() === 'uruguay' ? 'apps_power_uy' : 'apps_power';
    }

    /**
     * Cambiar país dinámicamente
     */
    public function cambiarPais($pais) {
        $nuevaBase = $this->determinarBaseDatos();
        if ($this->nameServer === $nuevaBase && $this->cid_apps) {
            return true;
        }
        
        $this->nameServer = $nuevaBase;
        
        // Reconectar con la nueva base
        require_once __DIR__.'/../../Class/conexion.php';
        $conexion = new Conexion();
        
        $this->cid_apps = $conexion->conectar($this->nameServer);
        
        // Guardar en sesión
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['pais_seleccionado'] = strtolower($pais);
        
        return $this->cid_apps !== false;
    }

    /**
     * Obtener país actual
     */
    public function obtenerPaisActual() {
        return $this->nameServer === 'apps_power_uy' ? 'uruguay' : 'argentina';
    }

    /**
     * Obtener información de la conexión actual
     */
    public function obtenerInfoConexion() {
        return [
            'pais' => $this->obtenerPaisActual(),
            'servidor' => $this->nameServer,
            'conectado' => $this->cid_apps !== false
        ];
    }

    private function getArray($sql){
        try {
            if (!$this->cid_apps) {
                throw new Exception("Error de conexión a la base de datos {$this->nameServer}");
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
            error_log("Error en getArray ({$this->nameServer}): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener datos del presupuesto de compras
     * @return array Datos del presupuesto de compras o array vacío en caso de error
     */
    public function obtenerPresupuestoCompras(){
        try {
            if (!$this->cid_apps) {
                throw new Exception("Error de conexión a la base de datos {$this->nameServer}");
            }

            // Ejecutar el procedimiento almacenado
            $sql = "SELECT * FROM RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS
                    ORDER BY RUBRO, CATEGORIA_PADRE";
            
            $stmt = sqlsrv_query($this->cid_apps, $sql);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $errorMessage = "Error al ejecutar procedimiento almacenado en {$this->nameServer}: ";
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

            // Log para debug
            error_log("Presupuesto obtenido desde {$this->nameServer}: " . count($resultados) . " registros");

            return $resultados;

        } catch (Exception $e) {
            error_log("Error en obtenerPresupuestoCompras ({$this->nameServer}): " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage(),
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer
            ];
        }
    }

    /**
     * Fecha de la última corrida del SP y si la tabla trae lo vendido de la
     * temporada en curso. Es lo único de la tabla que necesita recalcular una fila
     * (los días transcurridos de la cobertura salen de acá).
     *
     * @return array ['ult' => DateTime|null, 'hay_vendido' => bool]
     */
    public function obtenerUltActualizacion() {
        $vacio = ['ult' => null, 'hay_vendido' => false];
        if (!$this->cid_apps) {
            return $vacio;
        }
        $stmt = sqlsrv_query($this->cid_apps,
            "SELECT MAX(ULT_ACTUALIZACION) AS ult,
                    CASE WHEN COL_LENGTH('dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS', 'VTA_TEMPORADA_EN_CURSO') IS NULL
                         THEN 0 ELSE 1 END AS hay_vendido
             FROM RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS");
        if ($stmt === false) {
            return $vacio;
        }
        $fila = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return ['ult' => $fila['ult'] ?? null, 'hay_vendido' => (bool)($fila['hay_vendido'] ?? 0)];
    }

    /**
     * Función de prueba para verificar la conexión
     * @return array Resultado de la prueba de conexión
     */
    public function probarConexion(){
        try {
            if (!$this->cid_apps) {
                return [
                    'conexion' => false,
                    'mensaje' => "No se pudo establecer conexión con la base de datos {$this->nameServer}",
                    'pais' => $this->obtenerPaisActual(),
                    'servidor' => $this->nameServer
                ];
            }

            // Realizar una consulta simple para probar la conexión
            $sql = "SELECT GETDATE() as fecha_actual, @@SERVERNAME as servidor, DB_NAME() as base_datos";
            $stmt = sqlsrv_query($this->cid_apps, $sql);
            
            if ($stmt === false) {
                return [
                    'conexion' => false,
                    'mensaje' => "Error al ejecutar consulta de prueba en {$this->nameServer}: " . print_r(sqlsrv_errors(), true),
                    'pais' => $this->obtenerPaisActual(),
                    'servidor' => $this->nameServer
                ];
            }

            $resultado = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);

            return [
                'conexion' => true,
                'mensaje' => "Conexión exitosa a {$this->nameServer}",
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer,
                'datos' => $resultado
            ];

        } catch (Exception $e) {
            return [
                'conexion' => false,
                'mensaje' => "Error en prueba de conexión ({$this->nameServer}): " . $e->getMessage(),
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer
            ];
        }
    }

    /**
     * Obtiene información de las temporadas actuales
     * (Tabla utilizada por el procedimiento almacenado)
     * @return array Temporadas actuales
     */
    public function obtenerTemporadasActuales(){
        try {
            $sql = "SELECT * FROM dbo.RO_TABLA_TEMPORADAS_ACTUALES ORDER BY TEMPORADA";
            return $this->getArray($sql);
        } catch (Exception $e) {
            error_log("Error en obtenerTemporadasActuales ({$this->nameServer}): " . $e->getMessage());
            return [];
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
        
        return [
            'success' => true,
            'mensaje' => "País cambiado a " . ($pais === 'uruguay' ? 'Uruguay' : 'Argentina'),
            'pais' => $pais,
            'servidor' => $pais === 'uruguay' ? 'apps_uy' : 'apps'
        ];
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