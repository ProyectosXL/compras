
<?php
class Ventas {

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
        error_log("Ventas - País determinado: $pais");
        
        // Determinar el nombre del servidor para ventas (usa apps como presupuesto)
        switch ($pais) {
            case 'uruguay':
            case 'uy':
                return 'apps_power_uy';
            case 'argentina':
            case 'ar':
            default:
                return 'apps_power';
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
        if ($this->cid_apps) {
            sqlsrv_close($this->cid_apps);
        }
        
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
            'conectado' => $this->cid_apps !== false,
            'modulo' => 'ventas'
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
            error_log("Error en getArray Ventas ({$this->nameServer}): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene las ventas de los últimos 6 meses con variación
     */
    public function obtenerVentas6Meses($filtros = []){
        try {
            if (!$this->cid_apps) {
                throw new Exception("Error de conexión a la base de datos {$this->nameServer}");
            }

            $sql = "EXEC RO_SP_VENTAS_6_MESES_CON_VARIACION";
            
            $stmt = sqlsrv_query($this->cid_apps, $sql);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $errorMessage = "Error al ejecutar procedimiento almacenado en {$this->nameServer}: ";
                foreach($errors as $error) {
                    $errorMessage .= $error['message'] . " ";
                }
                throw new Exception($errorMessage);
            }

            $datos = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $datos[] = $row;
            }

            sqlsrv_free_stmt($stmt);
            
            if (empty($datos)) {
                error_log("Advertencia: No se obtuvieron datos de ventas desde {$this->nameServer}");
                return [];
            }
            
            // Log para debug
            error_log("Ventas 6 meses obtenidas desde {$this->nameServer}: " . count($datos) . " registros");
            
            return $datos;

        } catch (Exception $e) {
            error_log("Error en obtenerVentas6Meses ({$this->nameServer}): " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => 'Error al ejecutar consulta de ventas desde ' . $this->nameServer . ': ' . $e->getMessage(),
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer
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

            // Log para debug
            error_log("Búsqueda ventas desde {$this->nameServer}: " . count($resultados) . " registros para término: $termino");

            return array_values($resultados);

        } catch (Exception $e) {
            error_log("Error en buscarVentas6Meses ({$this->nameServer}): " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage(),
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer
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
            
            $resultado = array_map(function($rubro) {
                return ['RUBRO' => $rubro];
            }, $rubros);

            // Log para debug
            error_log("Rubros ventas obtenidos desde {$this->nameServer}: " . count($resultado) . " rubros");
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("Error en obtenerRubrosVentas ({$this->nameServer}): " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage(),
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer
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
            
            $resultado = array_map(function($categoria) {
                return ['CATEGORIA_PADRE' => $categoria];
            }, $categorias);

            // Log para debug
            error_log("Categorías ventas obtenidas desde {$this->nameServer}: " . count($resultado) . " categorías");
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("Error en obtenerCategoriasVentas ({$this->nameServer}): " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage(),
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer
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
                'items_estables' => 0,
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer
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

            // Log para debug
            error_log("Resumen ventas desde {$this->nameServer}: {$resumen['total_registros']} registros, variación: {$resumen['variacion_promedio']}%");

            return $resumen;

        } catch (Exception $e) {
            error_log("Error en obtenerResumenVentas ({$this->nameServer}): " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage(),
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer
            ];
        }
    }

    /**
     * Obtener estadísticas de evolución mensual
     */
    public function obtenerEvolucionMensual($rubro = null, $categoria = null) {
        try {
            $datos = $this->obtenerVentas6Meses();
            
            if (isset($datos['error'])) {
                return $datos;
            }

            // Filtrar por rubro y categoría si se especifican
            if ($rubro || $categoria) {
                $datos = array_filter($datos, function($item) use ($rubro, $categoria) {
                    $matchRubro = !$rubro || (isset($item['RUBRO']) && $item['RUBRO'] === $rubro);
                    $matchCategoria = !$categoria || (isset($item['CATEGORIA_PADRE']) && $item['CATEGORIA_PADRE'] === $categoria);
                    return $matchRubro && $matchCategoria;
                });
            }

            $evolucion = [];
            $mesesTotales = [];

            foreach ($datos as $item) {
                foreach ($item as $columna => $valor) {
                    // Buscar columnas de meses: VTA_MM_YYYY
                    if (preg_match('/^VTA_(\d{1,2})_(\d{4})$/', $columna, $matches)) {
                        $mes = (int)$matches[1];
                        $ano = (int)$matches[2];
                        $fecha = "$ano-" . str_pad($mes, 2, '0', STR_PAD_LEFT);
                        
                        if (!isset($mesesTotales[$fecha])) {
                            $mesesTotales[$fecha] = 0;
                        }
                        
                        $mesesTotales[$fecha] += (float)($valor ?? 0);
                    }
                }
            }

            // Ordenar por fecha
            ksort($mesesTotales);

            foreach ($mesesTotales as $fecha => $total) {
                $evolucion[] = [
                    'fecha' => $fecha,
                    'total_ventas' => round($total, 2),
                    'mes_nombre' => $this->formatearFechaMes($fecha)
                ];
            }

            return [
                'evolucion' => $evolucion,
                'total_meses' => count($evolucion),
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer,
                'filtros' => [
                    'rubro' => $rubro,
                    'categoria' => $categoria
                ]
            ];

        } catch (Exception $e) {
            error_log("Error en obtenerEvolucionMensual ({$this->nameServer}): " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage(),
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer
            ];
        }
    }

    /**
     * Formatear fecha de mes para mostrar
     */
    private function formatearFechaMes($fecha) {
        $meses = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
        ];
        
        $partes = explode('-', $fecha);
        if (count($partes) === 2) {
            $ano = $partes[0];
            $mes = $partes[1];
            return ($meses[$mes] ?? $mes) . ' ' . $ano;
        }
        
        return $fecha;
    }

    /**
     * Obtener comparativa entre países (requiere instancia de ambas bases)
     */
    public static function obtenerComparativaPaises($rubro = null, $categoria = null) {
        try {
            // Crear instancias para ambos países
            $ventasAR = new Ventas();
            $ventasUY = new Ventas();
            
            // Forzar conexiones específicas
            $ventasAR->nameServer = 'apps';
            $ventasUY->nameServer = 'apps_uy';
            
            // Obtener datos de ambos países
            $datosAR = $ventasAR->obtenerVentas6Meses();
            $datosUY = $ventasUY->obtenerVentas6Meses();
            
            $resumenAR = $ventasAR->obtenerResumenVentas();
            $resumenUY = $ventasUY->obtenerResumenVentas();
            
            return [
                'argentina' => [
                    'datos' => $datosAR,
                    'resumen' => $resumenAR,
                    'total_registros' => count($datosAR)
                ],
                'uruguay' => [
                    'datos' => $datosUY,
                    'resumen' => $resumenUY,
                    'total_registros' => count($datosUY)
                ],
                'comparativa' => [
                    'diferencia_ventas_actuales' => ($resumenAR['ventas_actuales_total'] ?? 0) - ($resumenUY['ventas_actuales_total'] ?? 0),
                    'diferencia_variacion' => ($resumenAR['variacion_promedio'] ?? 0) - ($resumenUY['variacion_promedio'] ?? 0),
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Error en obtenerComparativaPaises: " . $e->getMessage());
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
                    'mensaje' => "No se pudo establecer conexión con la base de datos {$this->nameServer}",
                    'pais' => $this->obtenerPaisActual(),
                    'servidor' => $this->nameServer,
                    'modulo' => 'ventas'
                ];
            }

            $sql = "SELECT GETDATE() as fecha_actual, @@SERVERNAME as servidor, DB_NAME() as base_datos";
            $stmt = sqlsrv_query($this->cid_apps, $sql);
            
            if ($stmt === false) {
                return [
                    'conexion' => false,
                    'mensaje' => "Error al ejecutar consulta de prueba en {$this->nameServer}: " . print_r(sqlsrv_errors(), true),
                    'pais' => $this->obtenerPaisActual(),
                    'servidor' => $this->nameServer,
                    'modulo' => 'ventas'
                ];
            }

            $resultado = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);

            return [
                'conexion' => true,
                'mensaje' => "Conexión exitosa a {$this->nameServer} (módulo ventas)",
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer,
                'modulo' => 'ventas',
                'datos' => $resultado
            ];

        } catch (Exception $e) {
            return [
                'conexion' => false,
                'mensaje' => "Error en prueba de conexión ({$this->nameServer}): " . $e->getMessage(),
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer,
                'modulo' => 'ventas'
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
        
        $servidor = $pais === 'uruguay' ? 'apps_uy' : 'apps';
        
        return [
            'success' => true,
            'mensaje' => "País cambiado a " . ($pais === 'uruguay' ? 'Uruguay' : 'Argentina') . " (módulo ventas)",
            'pais' => $pais,
            'servidor' => $servidor,
            'modulo' => 'ventas'
        ];
    }

    /**
     * Obtener estadísticas detalladas por país
     */
    public function obtenerEstadisticasPorPais() {
        try {
            $datos = $this->obtenerVentas6Meses();
            
            if (isset($datos['error']) || empty($datos)) {
                return [
                    'error' => true,
                    'mensaje' => 'No hay datos disponibles para estadísticas',
                    'pais' => $this->obtenerPaisActual(),
                    'servidor' => $this->nameServer
                ];
            }

            $estadisticas = [
                'total_items' => count($datos),
                'rubros_unicos' => count(array_unique(array_column($datos, 'RUBRO'))),
                'categorias_unicas' => count(array_unique(array_column($datos, 'CATEGORIA_PADRE'))),
                'ventas_totales_actuales' => array_sum(array_column($datos, 'VTA_ULT_60_DIAS')),
                'ventas_totales_anteriores' => array_sum(array_column($datos, 'VTA_ULT_60_DIAS_ANO_ANT')),
                'indices_variacion' => [
                    'promedio' => 0,
                    'minimo' => PHP_FLOAT_MAX,
                    'maximo' => PHP_FLOAT_MIN
                ],
                'pais' => $this->obtenerPaisActual(),
                'servidor' => $this->nameServer,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            // Calcular estadísticas de índices
            $indices = array_column($datos, 'INDICE_VARIACION');
            $indices = array_filter($indices, 'is_numeric');
            
            if (!empty($indices)) {
                $estadisticas['indices_variacion']['promedio'] = round(array_sum($indices) / count($indices), 3);
                $estadisticas['indices_variacion']['minimo'] = round(min($indices), 3);
                $estadisticas['indices_variacion']['maximo'] = round(max($indices), 3);
            }

            // Calcular variación general
            if ($estadisticas['ventas_totales_anteriores'] > 0) {
                $estadisticas['variacion_general_porcentaje'] = round(
                    (($estadisticas['ventas_totales_actuales'] - $estadisticas['ventas_totales_anteriores']) / 
                     $estadisticas['ventas_totales_anteriores']) * 100, 
                    2
                );
            } else {
                $estadisticas['variacion_general_porcentaje'] = 0;
            }

            return $estadisticas;

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
        if ($this->cid_apps) {
            sqlsrv_close($this->cid_apps);
        }
    }
}
?>