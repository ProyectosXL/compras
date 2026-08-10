<?php

class DistribucionController {
    private $distribucion;
    private $presupuesto;
    private $procesador;

    public function __construct() {
        require_once __DIR__ . '/../class/distribucion.php';
        require_once __DIR__ . '/../class/presupuesto.php';
        require_once __DIR__ . '/../class/PresupuestoCalculos.php';
        require_once __DIR__ . '/../class/CostoProyeccion.php';
        require_once __DIR__ . '/ProcesadorDatos.php';

        $this->distribucion = new Distribucion();
        $this->presupuesto = new Presupuesto();
        $this->procesador = new ProcesadorDatos();
    }

    /**
     * Obtener distribución por canal
     */
    public function obtenerDistribucionPorCanal() {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $pais = $_GET['pais'] ?? $_SESSION['pais_seleccionado'] ?? 'argentina';
            $temporadaFiltro = $_GET['temporada'] ?? ''; // e.g. VERANO or INVIERNO
            
            // Cargar fechas del filtro dinámico. Por defecto, de hace 6 meses cerrados a hoy
            $fechaDesde = $_GET['fecha_desde'] ?? '';
            $fechaHasta = $_GET['fecha_hasta'] ?? '';
            
            if (empty($fechaDesde)) {
                $fechaDesde = date('Y-m-01', strtotime('-6 months'));
            }
            if (empty($fechaHasta)) {
                $fechaHasta = date('Y-m-t', strtotime('-1 months')); // Último día del mes anterior (cerrado)
            }

            // Asegurar que la conexión a la base de presupuesto esté establecida para el país seleccionado
            $this->presupuesto->cambiarPais($pais);

            // Determinar temporada si no se especifica
            if (empty($temporadaFiltro)) {
                $infoTemp = PresupuestoCalculos::obtenerTemporadaActual();
                $temporadaFiltro = $infoTemp['temporada'];
            }

            $datosBase = [];
            $rawBody = file_get_contents('php://input');
            $postData = json_decode($rawBody, true);
            $proyecciones = $postData['proyecciones'] ?? null;
            
            if ($proyecciones && is_array($proyecciones)) {
                $datosBase = $proyecciones;
            } else {
                $datosBase = $this->presupuesto->obtenerPresupuestoCompras();
                if (isset($datosBase['error'])) {
                    $this->jsonResponse(['success' => false, 'message' => $datosBase['mensaje']], 500);
                    return;
                }
            }

            // Procesar compras proyectadas basadas en la temporada
            $comprasProcesadas = [];
            if (strtoupper($temporadaFiltro) === 'VERANO') {
                $comprasProcesadas = $this->procesador->procesarDatosCompraVerano($datosBase);
            } else {
                $comprasProcesadas = $this->procesador->procesarDatosCompraInvierno($datosBase);
            }

            // Obtener canales dinámicos
            $canales = $this->distribucion->obtenerCanales();

            // Obtener todos los históricos en una sola consulta usando el rango de fechas dinámico
            $ventasHistoricasTodas = $this->distribucion->obtenerTodasLasVentasPorCanal($fechaDesde, $fechaHasta);

            // Obtener nombre de distribución
            $nombreDistribucion = $postData['nombre_distribucion'] ?? $_GET['nombre_distribucion'] ?? 'Por defecto';

            // Obtener distribución guardada previa para restaurar ajustes manuales si existen
            $guardados = $this->distribucion->obtenerDistribucionGuardada($pais, $temporadaFiltro, $nombreDistribucion);
            $mapeoGuardados = [];
            foreach ($guardados as $g) {
                $clave = trim($g['rubro']) . '|' . trim($g['categoria_padre']) . '|' . trim($g['canal']);
                $mapeoGuardados[$clave] = $g;
            }

            // Determinar los meses del período de análisis de manera dinámica y cronológica
            $mesesPeriodo = [];
            $start = new DateTime($fechaDesde);
            $start->modify('first day of this month');
            $end = new DateTime($fechaHasta);
            $end->modify('first day of this month');
            
            $traducciones = [
                'Jan' => 'Ene', 'Feb' => 'Feb', 'Mar' => 'Mar',
                'Apr' => 'Abr', 'May' => 'May', 'Jun' => 'Jun',
                'Jul' => 'Jul', 'Aug' => 'Ago', 'Sep' => 'Sep',
                'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Dic'
            ];
            
            $temp = clone $start;
            while ($temp <= $end) {
                $mNum = (int)$temp->format('n');
                $yNum = (int)$temp->format('Y');
                $clave = "$mNum-$yNum";
                
                $engMonth = $temp->format('M');
                $espMonth = $traducciones[$engMonth] ?? $engMonth;
                
                // Generar etiqueta del año siguiente para representar el periodo de proyección
                $tempPlus1 = clone $temp;
                $tempPlus1->modify('+1 year');
                $label = $espMonth . ' ' . $tempPlus1->format('y'); // e.g. "Ene 27"
                
                $mesesPeriodo[$clave] = $label;
                $temp->modify('+1 month');
            }

            $mesesTemporada = $mesesPeriodo;

            // Para cada registro de compra proyectada, cruzar con histórico de ventas por canal
            $resultado = [];
            foreach ($comprasProcesadas as $registro) {
                $rubro = $registro['RUBRO'];
                $categoria = $registro['CATEGORIA_PADRE'];
                // Se utiliza el valor de la Venta Proyectada Verano (VENTA_PROY_VERANO) para realizar la distribución
                $compraProyectada = (int)$registro['VENTA_PROY_VERANO'];

                // Buscar ventas por canal en nuestro mapa en memoria
                $rubroClave = trim($rubro);
                $categoriaClave = trim($categoria);
                $ventasCanal = isset($ventasHistoricasTodas[$rubroClave][$categoriaClave]) 
                    ? $ventasHistoricasTodas[$rubroClave][$categoriaClave] 
                    : [];
                
                // 1. Calcular las ventas totales por mes para la temporada entera (sumando todos los canales)
                $ventasPorMes = array_fill_keys(array_keys($mesesTemporada), 0);
                $ventasCanalTotales = array_fill_keys($canales, 0);
                
                foreach ($canales as $canal) {
                    $canalClave = trim($canal);
                    if (isset($ventasCanal[$canalClave])) {
                        $ventasCanalTotales[$canalClave] = $ventasCanal[$canalClave]['total'];
                        foreach (array_keys($mesesTemporada) as $m) {
                            $ventasPorMes[$m] += isset($ventasCanal[$canalClave]['meses'][$m]) 
                                ? $ventasCanal[$canalClave]['meses'][$m] 
                                : 0;
                        }
                    }
                }
                
                $ventaTotalTemporada = array_sum($ventasPorMes);
                
                // 2. Calcular los porcentajes históricos de cada mes para este rubro/categoría
                $porcentajesMes = [];
                foreach ($mesesTemporada as $mNum => $mName) {
                    $porcentajesMes[$mNum] = $ventaTotalTemporada > 0 ? ($ventasPorMes[$mNum] / $ventaTotalTemporada) : 0;
                }
                
                // Si no hay ventas en la temporada, repartir equitativamente (1/6)
                if ($ventaTotalTemporada == 0) {
                    foreach ($mesesTemporada as $mNum => $mName) {
                        $porcentajesMes[$mNum] = 1 / 6;
                    }
                }

                // Calcular venta histórica total
                $ventaTotal = array_sum($ventasCanalTotales);

                // Distribuir proporcionalmente por canal (inicial para calcular participación original)
                $distribucionPorCanal = [];
                $canalMasFuerte = null;
                $maxVenta = -1;

                foreach ($canales as $canal) {
                    $canalClave = trim($canal);
                    $ventaCanal = isset($ventasCanalTotales[$canalClave]) ? $ventasCanalTotales[$canalClave] : 0;
                    $participacionOriginal = $ventaTotal > 0 ? ($ventaCanal / $ventaTotal) : 0;

                    // Si no hay ventas históricas, repartir equitativamente
                    if ($ventaTotal == 0) {
                        $participacionOriginal = 1 / count($canales);
                    }

                    $distribucionPorCanal[$canal] = [
                        'venta_canal' => $ventaCanal,
                        'participacion_original' => round($participacionOriginal * 100, 2)
                    ];

                    if ($ventaCanal > $maxVenta) {
                        $maxVenta = $ventaCanal;
                        $canalMasFuerte = $canal;
                    }
                }

                // Generar una fila para cada canal, cargando participaciones guardadas si existen
                $filasCanal = [];
                $sumaParticipacion = 0;
                
                foreach ($canales as $canal) {
                    $claveGuardado = trim($rubro) . '|' . trim($categoria) . '|' . trim($canal);
                    $partOriginal = $distribucionPorCanal[$canal]['participacion_original'];
                    $partActual = $partOriginal;
                    
                    if (isset($mapeoGuardados[$claveGuardado])) {
                        $partActual = round((float)$mapeoGuardados[$claveGuardado]['participacion_porcentaje'], 2);
                    }
                    
                    $sumaParticipacion += $partActual;
                    $filasCanal[$canal] = [
                        'participacion_original' => $partOriginal,
                        'participacion' => $partActual,
                        'modificado' => (abs($partActual - $partOriginal) > 0.01)
                    ];
                }

                // Calcular las unidades distribuidas por canal en base a su participación (%)
                $sumaUnidadesDistribuidas = 0;
                foreach ($canales as $canal) {
                    $part = $filasCanal[$canal]['participacion'];
                    $unidadesDist = (int)round($compraProyectada * ($part / 100));
                    $filasCanal[$canal]['venta_distribuida'] = $unidadesDist;
                    $sumaUnidadesDistribuidas += $unidadesDist;
                }

                // Si las participaciones suman 100% (o muy cerca), ajustar diferencia de redondeo en el canal más fuerte
                if (abs($sumaParticipacion - 100.0) < 0.1 && $canalMasFuerte !== null) {
                    $diferencia = $compraProyectada - $sumaUnidadesDistribuidas;
                    $filasCanal[$canalMasFuerte]['venta_distribuida'] += $diferencia;
                }

                // Generar filas finales
                foreach ($canales as $canal) {
                    $partOriginal = $filasCanal[$canal]['participacion_original'];
                    $partActual = $filasCanal[$canal]['participacion'];
                    $esModificado = $filasCanal[$canal]['modificado'];
                    $distFinal = $filasCanal[$canal]['venta_distribuida'];

                    // Distribuir la VENTA PROYECTADA / DISTRIBUCION_FINAL de este canal en base a la estacionalidad (%)
                    $mesesDistribuidos = [];
                    $sumaMesesDistribuidos = 0;
                    $mesMayorPorcentaje = null;
                    $maxP = -1;
                    
                    foreach ($mesesTemporada as $mClave => $mLabel) {
                        $p = $porcentajesMes[$mClave];
                        if ($p > $maxP) {
                            $maxP = $p;
                            $mesMayorPorcentaje = $mLabel;
                        }
                        
                        $unidadesProyectadas = (int)round($distFinal * $p);
                        $mesesDistribuidos[$mLabel] = [
                            'unidades' => $unidadesProyectadas,
                            'porcentaje' => round($p * 100, 2)
                        ];
                        $sumaMesesDistribuidos += $unidadesProyectadas;
                    }
                    
                    // Ajustar diferencias de redondeo con el mes de mayor porcentaje
                    if ($distFinal !== $sumaMesesDistribuidos && $mesMayorPorcentaje !== null) {
                        $diferenciaMes = $distFinal - $sumaMesesDistribuidos;
                        $mesesDistribuidos[$mesMayorPorcentaje]['unidades'] += $diferenciaMes;
                    }

                    // Recopilar información detallada de sucursales para LOCALES PROPIOS
                    $sucursalesDetalle = [];
                    $canalClave = trim($canal);
                    if ($canalClave === 'LOCALES PROPIOS' && isset($ventasCanal[$canalClave]['sucursales'])) {
                        foreach ($ventasCanal[$canalClave]['sucursales'] as $sucName => $sucData) {
                            $sucVentaTotal = $sucData['total'];
                            // Participación de la sucursal respecto a la venta del canal completo
                            $ventaCanalTotal = $distribucionPorCanal[$canal]['venta_canal'];
                            $sucParticipacion = $ventaCanalTotal > 0 ? ($sucVentaTotal / $ventaCanalTotal) : 0;
                            
                            $sucFinalTotal = (int)round($distFinal * $sucParticipacion);
                            $sucMeses = [];
                            $sucSumaMeses = 0;
                            $sucMesMayorP = null;
                            $sucMaxP = -1;
                            
                            foreach ($mesesTemporada as $mClave => $mLabel) {
                                $p = $porcentajesMes[$mClave];
                                if ($p > $sucMaxP) {
                                    $sucMaxP = $p;
                                    $sucMesMayorP = $mLabel;
                                }
                                
                                $sucUnidadesProyectadas = (int)round($sucFinalTotal * $p);
                                $sucMeses[$mLabel] = [
                                    'unidades' => $sucUnidadesProyectadas,
                                    'porcentaje' => round($p * 100, 2)
                                ];
                                $sucSumaMeses += $sucUnidadesProyectadas;
                            }
                            
                            if ($sucFinalTotal !== $sucSumaMeses && $sucMesMayorP !== null) {
                                $sucDiferenciaMes = $sucFinalTotal - $sucSumaMeses;
                                $sucMeses[$sucMesMayorP]['unidades'] += $sucDiferenciaMes;
                            }
                            
                            $sucursalesDetalle[] = [
                                'SUCURSAL' => $sucName,
                                'VENTA_HISTORICA' => $sucVentaTotal,
                                'PARTICIPACION' => round($sucParticipacion * 100, 2),
                                'MESES' => $sucMeses
                            ];
                        }
                        
                        // Ordenar sucursales por mayor venta
                        usort($sucursalesDetalle, function($a, $b) {
                            return $b['VENTA_HISTORICA'] <=> $a['VENTA_HISTORICA'];
                        });
                    }

                    $resultado[] = [
                        'RUBRO' => $rubro,
                        'CATEGORIA_PADRE' => $categoria,
                        'COMPRA_PROYECTADA' => $compraProyectada,
                        'VENTA_HISTORICA_TOTAL' => $ventaTotal,
                        'CANAL' => $canal,
                        'VENTA_CANAL' => $distribucionPorCanal[$canal]['venta_canal'],
                        'PARTICIPACION' => $partActual,
                        'PARTICIPACION_ORIGINAL' => $partOriginal,
                        'MODIFICADO' => $esModificado,
                        'COMPRA_DISTRIBUIDA' => $distFinal,
                        'DISTRIBUCION_FINAL' => $distFinal,
                        'PAIS' => $pais,
                        'TEMPORADA' => $temporadaFiltro,
                        'PERIODO_ANALISIS' => $fechaDesde . ' a ' . $fechaHasta,
                        'DISTRIBUCION_MENSUAL' => $mesesDistribuidos,
                        'SUCURSALES_DETALLE' => $sucursalesDetalle
                    ];
                }
            }

            $versiones = $this->distribucion->obtenerNombresDistribuciones($pais, $temporadaFiltro);

            $this->jsonResponse([
                'success' => true,
                'data' => $resultado,
                'canales' => $canales,
                'meses' => array_values($mesesTemporada),
                'total_registros' => count($resultado),
                'versiones' => $versiones,
                'nombre_distribucion' => $nombreDistribucion
            ]);

        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Guardar distribución
     */
    public function guardarDistribucion() {
        try {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);

            if (!isset($data['filas']) || !is_array($data['filas'])) {
                $this->jsonResponse(['success' => false, 'message' => 'Datos inválidos'], 400);
                return;
            }

            $res = $this->distribucion->guardarDistribucion($data['filas']);
            
            if ($res === true) {
                $this->jsonResponse(['success' => true, 'message' => 'Distribución guardada correctamente']);
            } else {
                $this->jsonResponse(['success' => false, 'message' => $res['mensaje'] ?? 'Error al guardar'], 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener lista de canales dinámicos
     */
    public function obtenerCanales() {
        try {
            $canales = $this->distribucion->obtenerCanales();
            $this->jsonResponse(['success' => true, 'data' => $canales]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener lista de versiones guardadas por país y temporada
     */
    public function obtenerVersiones() {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $pais = $_GET['pais'] ?? $_SESSION['pais_seleccionado'] ?? 'argentina';
            $temporada = $_GET['temporada'] ?? 'VERANO';
            
            $this->presupuesto->cambiarPais($pais);
            $versiones = $this->distribucion->obtenerNombresDistribuciones($pais, $temporada);
            
            $this->jsonResponse([
                'success' => true,
                'versiones' => $versiones
            ]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener datos de costos de proyección agrupados por rubro y categoría
     */
    public function obtenerCostosProyeccion() {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $pais = $_GET['pais'] ?? $_SESSION['pais_seleccionado'] ?? 'argentina';
            $temporada = $_GET['temporada'] ?? 'VERANO';
            $nombreDistribucion = $_GET['nombre_distribucion'] ?? 'Por defecto';

            $this->presupuesto->cambiarPais($pais);

            // 1. Obtener filas guardadas de distribución (Paso 1)
            $guardados = $this->distribucion->obtenerDistribucionGuardada($pais, $temporada, $nombreDistribucion);

            // 2. Obtener costos guardados (Paso 2)
            $costosModel = new CostoProyeccion();
            $costosGuardados = $costosModel->obtenerCostosGuardados($pais, $temporada, $nombreDistribucion);

            // Si no hay datos del Paso 1 NI del Paso 2, devolver vacío
            if (empty($guardados) && empty($costosGuardados)) {
                $this->jsonResponse([
                    'success' => true,
                    'data' => [],
                    'meses' => [],
                    'meses_claves' => []
                ]);
                return;
            }

            // 3. Extraer fechas del período de análisis del Paso 1 (si existe)
            $fechaDesde = '';
            $fechaHasta = '';
            if (!empty($guardados)) {
                $periodo = $guardados[0]['periodo_analisis'] ?? '';
                if (strpos($periodo, ' a ') !== false) {
                    $partes = explode(' a ', $periodo);
                    $fechaDesde = trim($partes[0]);
                    $fechaHasta = trim($partes[1]);
                }
            }

            // Usar rango de los filtros GET como fallback
            if (empty($fechaDesde) || empty($fechaHasta)) {
                $fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01', strtotime('-6 months'));
                $fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-t', strtotime('-1 months'));
            }

            // 4. Determinar meses proyectados del período
            $mesesPeriodo = [];
            $start = new DateTime($fechaDesde);
            $start->modify('first day of this month');
            $end = new DateTime($fechaHasta);
            $end->modify('first day of this month');
            
            $traducciones = [
                'Jan' => 'Ene', 'Feb' => 'Feb', 'Mar' => 'Mar',
                'Apr' => 'Abr', 'May' => 'May', 'Jun' => 'Jun',
                'Jul' => 'Jul', 'Aug' => 'Ago', 'Sep' => 'Sep',
                'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Dic'
            ];

            $temp = clone $start;
            while ($temp <= $end) {
                $mNum = (int)$temp->format('n');
                $yNum = (int)$temp->format('Y');
                $mesClave = "$mNum-$yNum";
                
                $proyStart = clone $temp;
                $proyStart->modify('+1 year');
                $mesNombre = $proyStart->format('M');
                $añoNombre = $proyStart->format('y');
                $label = ($traducciones[$mesNombre] ?? $mesNombre) . ' ' . $añoNombre;
                
                $mesesPeriodo[$mesClave] = $label;
                $temp->modify('+1 month');
            }

            // 5. Obtener todos los históricos para calcular estacionalidad
            $ventasHistoricasTodas = !empty($guardados) 
                ? $this->distribucion->obtenerTodasLasVentasPorCanal($fechaDesde, $fechaHasta)
                : [];

            // 6. Agrupar por rubro y categoría desde el Paso 1 (si hay)
            $agrupados = [];
            foreach ($guardados as $g) {
                $rubro = trim($g['rubro']);
                $categoria = trim($g['categoria_padre']);
                $clave = $rubro . '|' . $categoria;

                if (!isset($agrupados[$clave])) {
                    $agrupados[$clave] = [
                        'RUBRO' => $rubro,
                        'CATEGORIA_PADRE' => $categoria,
                        'COMPRA_PROYECTADA' => 0,
                        'COMPRA_DISTRIBUIDA' => 0,
                        'MESES_UNIDADES' => array_fill_keys(array_keys($mesesPeriodo), 0)
                    ];
                }

                $distFinal = (int)$g['compra_distribuida'];
                $agrupados[$clave]['COMPRA_PROYECTADA'] += (int)$g['compra_proyectada'];
                $agrupados[$clave]['COMPRA_DISTRIBUIDA'] += $distFinal;

                $ventasCanal = $ventasHistoricasTodas[$rubro][$categoria] ?? [];
                $ventasCanalTotales = array_fill_keys(array_keys($mesesPeriodo), 0);
                foreach (array_keys($mesesPeriodo) as $m) {
                    foreach ($ventasCanal as $canalData) {
                        if (isset($canalData['meses'][$m])) {
                            $ventasCanalTotales[$m] += $canalData['meses'][$m];
                        }
                    }
                }
                
                $ventaTotal = array_sum($ventasCanalTotales);
                foreach (array_keys($mesesPeriodo) as $m) {
                    $p = $ventaTotal > 0 ? ($ventasCanalTotales[$m] / $ventaTotal) * 100 : (100 / count($mesesPeriodo));
                    $agrupados[$clave]['MESES_UNIDADES'][$m] += round($distFinal * ($p / 100));
                }
            }

            // 7. Agregar rubros/categorías desde costos guardados que no estén en el Paso 1
            foreach ($costosGuardados as $clave => $costoInfo) {
                if (!isset($agrupados[$clave])) {
                    list($rubro, $categoria) = explode('|', $clave, 2);
                    $agrupados[$clave] = [
                        'RUBRO' => $rubro,
                        'CATEGORIA_PADRE' => $categoria,
                        'COMPRA_PROYECTADA' => 0,
                        'COMPRA_DISTRIBUIDA' => 0,
                        'MESES_UNIDADES' => array_fill_keys(array_keys($mesesPeriodo), 0)
                    ];
                }
            }

            // 8. Mezclar y preparar respuesta final
            $resultado = [];
            foreach ($agrupados as $clave => $item) {
                $costoInfo = $costosGuardados[$clave] ?? ['costo_prom' => 0.0, 'inc_fob' => 0.0, 'vcosto' => 0.0];

                $resultado[] = [
                    'RUBRO' => $item['RUBRO'],
                    'CATEGORIA_PADRE' => $item['CATEGORIA_PADRE'],
                    'COMPRA_PROYECTADA' => $item['COMPRA_PROYECTADA'],
                    'COMPRA_DISTRIBUIDA' => $item['COMPRA_DISTRIBUIDA'],
                    'COSTO_PROM' => $costoInfo['costo_prom'],
                    'INC_FOB' => $costoInfo['inc_fob'],
                    'VCOSTO' => $costoInfo['vcosto'],
                    'MESES_UNIDADES' => $item['MESES_UNIDADES'],
                    'PAIS' => $pais,
                    'TEMPORADA' => $temporada,
                    'NOMBRE_DISTRIBUCION' => $nombreDistribucion
                ];
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $resultado,
                'meses' => array_values($mesesPeriodo),
                'meses_claves' => array_keys($mesesPeriodo)
            ]);

        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Guardar costos de proyección
     */
    public function guardarCostos() {
        try {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);

            if (!isset($data['filas']) || !is_array($data['filas'])) {
                $this->jsonResponse(['success' => false, 'message' => 'Datos inválidos'], 400);
                return;
            }

            $costosModel = new CostoProyeccion();
            $res = $costosModel->guardarCostos($data['filas']);
            
            if ($res === true) {
                $this->jsonResponse(['success' => true, 'message' => 'Costos guardados correctamente']);
            } else {
                $this->jsonResponse(['success' => false, 'message' => $res['mensaje'] ?? 'Error al guardar costos'], 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function jsonResponse($data, $httpCode = 200) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
?>
