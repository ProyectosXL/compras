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
                $rubroClave = trim($rubro);
                $categoriaClave = trim($categoria);

                // Se utiliza el valor correspondiente de la Venta Proyectada según temporada
                $compraProyectada = (strtoupper($temporadaFiltro) === 'VERANO') ? (int)$registro['VENTA_PROY_VERANO'] : (int)$registro['VENTA_PROY_INVIERNO'];

                // Si existe un valor guardado previamente en la versión para este grupo (rubro / categoría), respetarlo
                $compraProyectadaGuardada = null;
                foreach ($canales as $canal) {
                    $claveGuardado = $rubroClave . '|' . $categoriaClave . '|' . trim($canal);
                    if (isset($mapeoGuardados[$claveGuardado]) && isset($mapeoGuardados[$claveGuardado]['compra_proyectada'])) {
                        $compraProyectadaGuardada = (int)$mapeoGuardados[$claveGuardado]['compra_proyectada'];
                        break;
                    }
                }

                if ($compraProyectadaGuardada !== null) {
                    $compraProyectada = $compraProyectadaGuardada;
                }

                if ($compraProyectada < 0) {
                    $compraProyectada = 0;
                }

                // Buscar ventas por canal en nuestro mapa en memoria
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
                
                // 2. Calcular los porcentajes históricos agregados (como fallback)
                $porcentajesMesGlobal = [];
                foreach ($mesesTemporada as $mNum => $mName) {
                    $porcentajesMesGlobal[$mNum] = $ventaTotalTemporada > 0 ? ($ventasPorMes[$mNum] / $ventaTotalTemporada) : 0;
                }
                
                if ($ventaTotalTemporada == 0) {
                    foreach ($mesesTemporada as $mNum => $mName) {
                        $porcentajesMesGlobal[$mNum] = 1 / 6;
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
                        'participacion_original' => round($participacionOriginal * 100, 0)
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
                    $claveGuardado = $rubroClave . '|' . $categoriaClave . '|' . trim($canal);
                    $partOriginal = $distribucionPorCanal[$canal]['participacion_original'];
                    $partActual = $partOriginal;
                    
                    $savedMonthlyJson = null;
                    if (isset($mapeoGuardados[$claveGuardado])) {
                        $partActual = round((float)$mapeoGuardados[$claveGuardado]['participacion_porcentaje'], 0);
                        $savedMonthlyJson = $mapeoGuardados[$claveGuardado]['distribucion_mensual_json'] ?? null;
                    }
                    
                    $sumaParticipacion += $partActual;
                    $filasCanal[$canal] = [
                        'participacion_original' => $partOriginal,
                        'participacion' => $partActual,
                        'modificado' => (abs($partActual - $partOriginal) > 0.01),
                        'distribucion_mensual_json' => $savedMonthlyJson
                    ];
                }

                // Calcular las unidades distribuidas por canal en base a su participación (%) o valor guardado
                $sumaUnidadesDistribuidas = 0;
                $tieneDistribucionGuardada = false;
                foreach ($canales as $canal) {
                    $claveGuardado = $rubroClave . '|' . $categoriaClave . '|' . trim($canal);
                    $part = $filasCanal[$canal]['participacion'];
                    
                    if (isset($mapeoGuardados[$claveGuardado]) && isset($mapeoGuardados[$claveGuardado]['distribucion_final'])) {
                        $unidadesDist = (int)$mapeoGuardados[$claveGuardado]['distribucion_final'];
                        $tieneDistribucionGuardada = true;
                    } elseif (isset($mapeoGuardados[$claveGuardado]) && isset($mapeoGuardados[$claveGuardado]['compra_distribuida'])) {
                        $unidadesDist = (int)$mapeoGuardados[$claveGuardado]['compra_distribuida'];
                        $tieneDistribucionGuardada = true;
                    } else {
                        $unidadesDist = (int)round($compraProyectada * ($part / 100));
                    }

                    $filasCanal[$canal]['venta_distribuida'] = $unidadesDist;
                    $sumaUnidadesDistribuidas += $unidadesDist;
                }

                // Si no hay distribución guardada previamente y las participaciones suman 100% (o muy cerca), ajustar diferencia de redondeo en el canal más fuerte
                if (!$tieneDistribucionGuardada && abs($sumaParticipacion - 100.0) < 0.1 && $canalMasFuerte !== null) {
                    $diferencia = $compraProyectada - $sumaUnidadesDistribuidas;
                    $filasCanal[$canalMasFuerte]['venta_distribuida'] += $diferencia;
                }

                // Generar filas finales
                foreach ($canales as $canal) {
                    $partOriginal = $filasCanal[$canal]['participacion_original'];
                    $partActual = $filasCanal[$canal]['participacion'];
                    $esModificado = $filasCanal[$canal]['modificado'];
                    $distFinal = $filasCanal[$canal]['venta_distribuida'];
                    $savedMonthlyJson = $filasCanal[$canal]['distribucion_mensual_json'];

                    // Calcular estacionalidad de manera específica para este canal (CORRECCIÓN)
                    $canalClave = trim($canal);
                    $ventaCanalTotal = isset($ventasCanalTotales[$canalClave]) ? $ventasCanalTotales[$canalClave] : 0;
                    $porcentajesMesCanal = [];
                    foreach ($mesesTemporada as $mClave => $mLabel) {
                        $vMesCanal = isset($ventasCanal[$canalClave]['meses'][$mClave]) ? $ventasCanal[$canalClave]['meses'][$mClave] : 0;
                        $porcentajesMesCanal[$mClave] = $ventaCanalTotal > 0 
                            ? ($vMesCanal / $ventaCanalTotal) 
                            : $porcentajesMesGlobal[$mClave];
                    }

                    // Distribuir la VENTA PROYECTADA / DISTRIBUCION_FINAL de este canal en base a la estacionalidad (%)
                    $mesesDistribuidos = [];
                    $sumaMesesDistribuidos = 0;
                    $mesMayorPorcentaje = null;
                    $maxP = -1;
                    
                    $savedMonthly = null;
                    if (!empty($savedMonthlyJson)) {
                        $savedMonthly = json_decode($savedMonthlyJson, true);
                    }
                    
                    foreach ($mesesTemporada as $mClave => $mLabel) {
                        if ($savedMonthly && isset($savedMonthly[$mLabel])) {
                            $p = (float)$savedMonthly[$mLabel]['porcentaje'] / 100;
                            $unidadesProyectadas = (int)$savedMonthly[$mLabel]['unidades'];
                        } else {
                            $p = $porcentajesMesCanal[$mClave];
                            $unidadesProyectadas = (int)round($distFinal * $p);
                        }

                        if ($p > $maxP) {
                            $maxP = $p;
                            $mesMayorPorcentaje = $mLabel;
                        }
                        
                        $mesesDistribuidos[$mLabel] = [
                            'unidades' => $unidadesProyectadas,
                            'porcentaje' => round($p * 100, 0)
                        ];
                        $sumaMesesDistribuidos += $unidadesProyectadas;
                    }
                    
                    // Ajustar diferencias de redondeo con el mes de mayor porcentaje (solo si no es override guardado)
                    if (!$savedMonthly && $distFinal !== $sumaMesesDistribuidos && $mesMayorPorcentaje !== null) {
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
                                $p = $porcentajesMesCanal[$mClave];
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
            file_put_contents(__DIR__ . '/../version_debug.log', date('Y-m-d H:i:s') . " - PAIS: $pais, TEMPORADA: $temporada, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'CLI') . ", VERSIONES: " . json_encode($versiones) . "\n", FILE_APPEND);
            
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

            // 2. Obtener costos guardados (Parámetros globales)
            $costosModel = new CostoProyeccion();
            $costosGuardados = $costosModel->obtenerParametrosGlobales();

            // Si no hay datos del Paso 1, devolver vacío
            if (empty($guardados)) {
                $this->jsonResponse([
                    'success' => true,
                    'data' => [],
                    'meses' => [],
                    'meses_claves' => []
                ]);
                return;
            }

            // 3. Extraer fechas del período de análisis del Paso 1
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
            $ventasHistoricasTodas = $this->distribucion->obtenerTodasLasVentasPorCanal($fechaDesde, $fechaHasta);

            // 6. Agrupar por rubro, categoría y canal desde el Paso 1
            $agrupados = [];
            foreach ($guardados as $g) {
                $rubro = trim($g['rubro']);
                $categoria = trim($g['categoria_padre']);
                $canal = trim($g['canal']);
                $clave = $rubro . '|' . $categoria . '|' . $canal;

                if (!isset($agrupados[$clave])) {
                    $agrupados[$clave] = [
                        'RUBRO' => $rubro,
                        'CATEGORIA_PADRE' => $categoria,
                        'CANAL' => $canal,
                        'COMPRA_PROYECTADA' => (int)$g['compra_proyectada'],
                        'COMPRA_DISTRIBUIDA' => 0,
                        'MESES_UNIDADES' => array_fill_keys(array_keys($mesesPeriodo), 0)
                    ];
                }

                $distFinal = (int)$g['compra_distribuida'];
                $agrupados[$clave]['COMPRA_DISTRIBUIDA'] = $distFinal;

                $savedMonthlyJson = $g['distribucion_mensual_json'] ?? null;
                $savedMonthly = !empty($savedMonthlyJson) ? json_decode($savedMonthlyJson, true) : null;
                
                if ($savedMonthly) {
                    foreach ($savedMonthly as $mLabel => $mInfo) {
                        foreach ($mesesPeriodo as $mClave => $mName) {
                            if ($mName === $mLabel) {
                                $agrupados[$clave]['MESES_UNIDADES'][$mClave] = (int)$mInfo['unidades'];
                                break;
                            }
                        }
                    }
                } else {
                    $ventasCanalObj = $ventasHistoricasTodas[$rubro][$categoria] ?? [];
                    $ventasCanal = [];
                    foreach ($ventasCanalObj as $cData) {
                        if (trim($cData['canal'] ?? '') === $canal) {
                            $ventasCanal = $cData;
                            break;
                        }
                    }

                    $ventasCanalTotales = array_fill_keys(array_keys($mesesPeriodo), 0);
                    if (!empty($ventasCanal)) {
                        foreach (array_keys($mesesPeriodo) as $m) {
                            if (isset($ventasCanal['meses'][$m])) {
                                $ventasCanalTotales[$m] = $ventasCanal['meses'][$m];
                            }
                        }
                    }
                    
                    $ventaTotal = array_sum($ventasCanalTotales);
                    foreach (array_keys($mesesPeriodo) as $m) {
                        $p = $ventaTotal > 0 ? ($ventasCanalTotales[$m] / $ventaTotal) * 100 : (100 / count($mesesPeriodo));
                        $agrupados[$clave]['MESES_UNIDADES'][$m] += round($distFinal * ($p / 100));
                    }
                }
            }

            // 7. Mezclar y preparar respuesta final
            $resultado = [];
            foreach ($agrupados as $clave => $item) {
                $paramClave = $item['RUBRO'] . '|' . $item['CATEGORIA_PADRE'];
                $costoInfo = $costosGuardados[$paramClave] ?? ['costo_prom' => 0.0, 'inc_fob' => 0.0, 'vcosto' => 0.0];

                $resultado[] = [
                    'RUBRO' => $item['RUBRO'],
                    'CATEGORIA_PADRE' => $item['CATEGORIA_PADRE'],
                    'CANAL' => $item['CANAL'],
                    'COMPRA_PROYECTADA' => $item['COMPRA_PROYECTADA'],
                    'COMPRA_DISTRIBUIDA' => $item['COMPRA_DISTRIBUIDA'],
                    'COSTO_PROM' => $costoInfo['costo_prom'],
                    'INC_FOB' => $costoInfo['inc_fob'],
                    'VCOSTO' => $costoInfo['vcosto'],
                    'MARKUP_LOCALES_PROPIOS' => $costoInfo['markup_locales_propios'] ?? 0.0,
                    'MARKUP_FRANQUICIAS' => $costoInfo['markup_franquicias'] ?? 0.0,
                    'MARKUP_MAYORISTAS' => $costoInfo['markup_mayoristas'] ?? 0.0,
                    'MARKUP_ECOMMERCE' => $costoInfo['markup_ecommerce'] ?? 0.0,
                    'MESES_UNIDADES' => $item['MESES_UNIDADES'],
                    'PAIS' => $pais,
                    'TEMPORADA' => $temporada,
                    'NOMBRE_DISTRIBUCION' => $nombreDistribucion
                ];
            }

            // Ordenar los resultados para que sigan exactamente el mismo orden de canales que el Paso 1:
            // LOCALES PROPIOS, FRANQUICIAS, MAYORISTAS, ECOMMERCE
            $ordenCanales = ['LOCALES PROPIOS' => 0, 'FRANQUICIAS' => 1, 'MAYORISTAS' => 2, 'ECOMMERCE' => 3];
            usort($resultado, function($a, $b) use ($ordenCanales) {
                if ($a['RUBRO'] !== $b['RUBRO']) {
                    return strcmp($a['RUBRO'], $b['RUBRO']);
                }
                if ($a['CATEGORIA_PADRE'] !== $b['CATEGORIA_PADRE']) {
                    return strcmp($a['CATEGORIA_PADRE'], $b['CATEGORIA_PADRE']);
                }
                $posA = $ordenCanales[trim($a['CANAL'])] ?? 99;
                $posB = $ordenCanales[trim($b['CANAL'])] ?? 99;
                return $posA <=> $posB;
            });

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
     * Obtener todos los parámetros globales de costo
     */
    public function obtenerParametrosGlobales() {
        try {
            $costosModel = new CostoProyeccion();
            $params = $costosModel->obtenerParametrosGlobales();
            
            $this->jsonResponse([
                'success' => true,
                'parametros' => array_values($params)
            ]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Guardar parámetros globales de costo
     */
    public function guardarParametrosGlobales() {
        try {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);

            if (!isset($data['parametros']) || !is_array($data['parametros'])) {
                $this->jsonResponse(['success' => false, 'message' => 'Datos de parámetros inválidos'], 400);
                return;
            }

            $costosModel = new CostoProyeccion();
            $res = $costosModel->guardarParametrosGlobales($data['parametros']);
            
            if ($res === true) {
                $this->jsonResponse(['success' => true, 'message' => 'Parámetros guardados con éxito']);
            } else {
                $this->jsonResponse(['success' => false, 'message' => $res['mensaje'] ?? 'Error desconocido'], 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Guardar costos de proyección (Mantenido por compatibilidad de firma o auditoría)
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

    /**
     * Obtener tasas de tipo de cambio:
     * 1. Dólar Futuro ROFEX (dbo.FP_DOLAR_FUTURO_ROFEX) para proyecciones.
     * 2. Dólar oficial BCRA histórico como fallback.
     */
    public function obtenerTipoCambio() {
        try {
            require_once __DIR__ . '/../class/DolarFuturoService.php';
            $rofexService = new DolarFuturoService();
            
            // 1. Intentar actualizar desde API y obtener cotizaciones guardadas en dbo.FP_DOLAR_FUTURO_ROFEX
            $rofexService->actualizarDesdeAPI();
            $tasasFuturo = $rofexService->obtenerCotizacionesGuardadas();

            // 2. Cargar histórico del BCRA para meses no cubiertos por futuros
            $anioActual = (int)date('Y');
            $anioDesde = $anioActual - 2;
            $anioHasta = $anioActual + 2;
            
            $conn = new Conexion();
            $cid = $conn->conectar('apps');
            
            $tasasBcra = [];
            if ($cid) {
                $sql = "
                    SELECT d.Año, d.Mes, d.Vendedor as Valor
                    FROM dolar_oficial_bcra d
                    INNER JOIN (
                        SELECT Año, Mes, MAX(Fecha) as FechaMax
                        FROM dolar_oficial_bcra
                        WHERE Año BETWEEN ? AND ?
                        GROUP BY Año, Mes
                    ) cierre ON d.Año = cierre.Año AND d.Mes = cierre.Mes AND d.Fecha = cierre.FechaMax
                    ORDER BY d.Año, d.Mes
                ";
                
                $params = [$anioDesde, $anioHasta];
                $stmt = sqlsrv_query($cid, $sql, $params);
                
                if ($stmt !== false) {
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        $clave = $row['Mes'] . '-' . $row['Año'];
                        $tasasBcra[$clave] = (float)$row['Valor'];
                    }
                    sqlsrv_free_stmt($stmt);
                }
            }
            
            // Mezclar: prioridad Dólar Futuro ROFEX, fallback BCRA histórico
            $tasasCombinadas = array_merge($tasasBcra, $tasasFuturo);

            $this->jsonResponse([
                'success' => true,
                'tasas' => $tasasCombinadas,
                'tasas_futuro' => $tasasFuturo,
                'tasas_bcra' => $tasasBcra
            ]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar una versión guardada
     */
    public function eliminarVersion() {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            $pais = $data['pais'] ?? $_SESSION['pais_seleccionado'] ?? 'argentina';
            $temporada = $data['temporada'] ?? 'VERANO';
            $nombreVersion = $data['nombre_version'] ?? '';

            if (empty($nombreVersion)) {
                $this->jsonResponse(['success' => false, 'message' => 'Debe especificar el nombre de la versión.'], 400);
                return;
            }

            $res = $this->distribucion->eliminarDistribucion($pais, $temporada, $nombreVersion);
            
            if ($res['success']) {
                $this->jsonResponse($res);
            } else {
                $this->jsonResponse($res, 400);
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
