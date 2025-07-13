
<?php
class Ventas {

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
            error_log("Error en getArray Ventas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene las ventas de los últimos 6 meses con variación - TEMPORAL CON MESES DINÁMICOS
     */
    public function obtenerVentas6Meses($filtros = []){
        try {
            // Calcular los últimos 6 meses (excluyendo el actual)
            $mesesColumnas = [];
            for ($i = 6; $i >= 1; $i--) {
                $fecha = new DateTime();
                $fecha->modify("-{$i} months");
                $mes = $fecha->format('n'); // Mes sin ceros iniciales
                $ano = $fecha->format('Y');
                $mesesColumnas["VTA_{$mes}_{$ano}"] = rand(10, 100);
            }
            
            // DATOS DE PRUEBA TEMPORAL con columnas dinámicas
            $datosBase = [
                [
                    'RUBRO' => 'ACCESORIOS DE VINILICO',
                    'CATEGORIA_PADRE' => 'TARJETERO',
                    'VTA_ULT_60_DIAS' => 150,
                    'VTA_ULT_60_DIAS_ANO_ANT' => 120,
                    'INDICE_VARIACION' => 1.25
                ],
                [
                    'RUBRO' => 'BILLETERAS DE VINILICO',
                    'CATEGORIA_PADRE' => 'BILLETERA',
                    'VTA_ULT_60_DIAS' => 89,
                    'VTA_ULT_60_DIAS_ANO_ANT' => 110,
                    'INDICE_VARIACION' => 0.81
                ],
                [
                    'RUBRO' => 'MOCHILAS',
                    'CATEGORIA_PADRE' => 'MOCHILA URBANA',
                    'VTA_ULT_60_DIAS' => 200,
                    'VTA_ULT_60_DIAS_ANO_ANT' => 185,
                    'INDICE_VARIACION' => 1.08
                ],
                [
                    'RUBRO' => 'CARTERAS DE VINILICO',
                    'CATEGORIA_PADRE' => 'CARTERA',
                    'VTA_ULT_60_DIAS' => 75,
                    'VTA_ULT_60_DIAS_ANO_ANT' => 95,
                    'INDICE_VARIACION' => 0.79
                ],
                [
                    'RUBRO' => 'RIÑONERAS',
                    'CATEGORIA_PADRE' => 'RIÑONERA',
                    'VTA_ULT_60_DIAS' => 130,
                    'VTA_ULT_60_DIAS_ANO_ANT' => 85,
                    'INDICE_VARIACION' => 1.53
                ]
            ];
            
            // Agregar columnas dinámicas de meses a cada registro
            $datosTemporales = [];
            foreach ($datosBase as $registro) {
                // Agregar columnas de meses con valores aleatorios variados
                $registroConMeses = array_merge($registro, []);
                
                foreach ($mesesColumnas as $columna => $valorBase) {
                    // Variar los valores según el índice de variación del producto
                    $factor = $registro['INDICE_VARIACION'];
                    $variacion = rand(-20, 20) / 100; // ±20%
                    $registroConMeses[$columna] = max(0, round($valorBase * $factor * (1 + $variacion)));
                }
                
                $datosTemporales[] = $registroConMeses;
            }

            error_log("Columnas generadas: " . implode(', ', array_keys($mesesColumnas)));
            
            return $datosTemporales;

        } catch (Exception $e) {
            error_log("Error en obtenerVentas6Meses: " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    /**
     * Buscar en ventas por término
     * @param string $termino Término de búsqueda
     * @param array $filtros Filtros adicionales
     * @return array Resultados de búsqueda
     */
    public function buscarVentas6Meses($termino, $filtros = []){
        try {
            // Primero obtener todos los datos
            $datos = $this->obtenerVentas6Meses($filtros);
            
            if (isset($datos['error'])) {
                return $datos;
            }

            // Filtrar por término de búsqueda
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
     * @return array Lista de rubros
     */
    public function obtenerRubrosVentas(){
        try {
            $sql = "SELECT DISTINCT RUBRO 
                    FROM (
                        EXEC RO_SP_VENTAS_6_MESES_CON_VARIACION
                    ) AS ventas
                    WHERE RUBRO IS NOT NULL 
                    ORDER BY RUBRO";
            
            // Como no podemos usar el SP en una subconsulta, obtenemos todos los datos y extraemos rubros
            $datos = $this->obtenerVentas6Meses();
            if (isset($datos['error'])) {
                return $datos;
            }

            $rubros = [];
            foreach ($datos as $item) {
                if (!empty($item['RUBRO']) && !in_array($item['RUBRO'], $rubros)) {
                    $rubros[] = $item['RUBRO'];
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
     * @return array Lista de categorías
     */
    public function obtenerCategoriasVentas(){
        try {
            // Obtener todos los datos y extraer categorías
            $datos = $this->obtenerVentas6Meses();
            if (isset($datos['error'])) {
                return $datos;
            }

            $categorias = [];
            foreach ($datos as $item) {
                if (!empty($item['CATEGORIA_PADRE']) && !in_array($item['CATEGORIA_PADRE'], $categorias)) {
                    $categorias[] = $item['CATEGORIA_PADRE'];
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
     * @param array $filtros Filtros aplicados
     * @return array Resumen de totales
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
                $resumen['ventas_actuales_total'] += $item['VTA_ULT_60_DIAS'] ?? 0;
                $resumen['ventas_anteriores_total'] += $item['VTA_ULT_60_DIAS_ANO_ANT'] ?? 0;
                
                $indice = $item['INDICE_VARIACION'] ?? 1;
                if ($indice > 1.2) {
                    $resumen['items_mejorados']++;
                } elseif ($indice < 0.8) {
                    $resumen['items_empeorados']++;
                } else {
                    $resumen['items_estables']++;
                }
            }

            // Calcular variación promedio
            if ($resumen['ventas_anteriores_total'] > 0) {
                $resumen['variacion_promedio'] = (($resumen['ventas_actuales_total'] - $resumen['ventas_anteriores_total']) / $resumen['ventas_anteriores_total']) * 100;
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
     * Aplicar filtros a los datos
     * @param array $datos Datos originales
     * @param array $filtros Filtros a aplicar
     * @return array Datos filtrados
     */
    private function aplicarFiltros($datos, $filtros) {
        $resultado = $datos;

        if (!empty($filtros['rubro'])) {
            $resultado = array_filter($resultado, function($item) use ($filtros) {
                return stripos($item['RUBRO'] ?? '', $filtros['rubro']) !== false;
            });
        }

        if (!empty($filtros['categoria'])) {
            $resultado = array_filter($resultado, function($item) use ($filtros) {
                return stripos($item['CATEGORIA_PADRE'] ?? '', $filtros['categoria']) !== false;
            });
        }

        return array_values($resultado);
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