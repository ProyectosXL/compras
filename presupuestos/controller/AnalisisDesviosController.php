<?php

class AnalisisDesviosController {
    private $distribucion;
    private $presupuesto;

    public function __construct() {
        require_once __DIR__ . '/../class/distribucion.php';
        require_once __DIR__ . '/../class/presupuesto.php';
        require_once __DIR__ . '/../class/CostoProyeccion.php';

        $this->distribucion = new Distribucion();
        $this->presupuesto = new Presupuesto();
    }

    /**
     * Obtiene los desvíos presupuestado vs real
     */
    public function obtenerDesvios() {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $pais = $_GET['pais'] ?? $_SESSION['pais_seleccionado'] ?? 'argentina';
            $temporada = $_GET['temporada'] ?? 'VERANO';
            $nombreDistribucion = $_GET['nombre_distribucion'] ?? 'Por defecto';

            $this->presupuesto->cambiarPais($pais);

            // 1. Obtener datos presupuestados guardados desde la tabla consolidada
            $guardadosConsolidados = $this->distribucion->obtenerVersionConsolidada($pais, $temporada, $nombreDistribucion);
            
            // Fallback a distribución básica si la tabla consolidada aún no tiene esta versión
            if (empty($guardadosConsolidados)) {
                $guardadosConsolidados = $this->distribucion->obtenerDistribucionGuardada($pais, $temporada, $nombreDistribucion);
            }

            if (empty($guardadosConsolidados)) {
                $this->jsonResponse([
                    'success' => true,
                    'desvios' => [],
                    'meses' => [],
                    'mensaje' => 'No hay distribución guardada para la versión seleccionada.'
                ]);
                return;
            }

            // 2. Extraer rango de fechas del período de análisis original
            $fechaDesde = '';
            $fechaHasta = '';
            $periodo = $guardadosConsolidados[0]['periodo_analisis'] ?? '';
            if (strpos($periodo, ' a ') !== false) {
                $partes = explode(' a ', $periodo);
                $fechaDesde = trim($partes[0]);
                $fechaHasta = trim($partes[1]);
            }

            if (empty($fechaDesde) || empty($fechaHasta)) {
                $fechaDesde = date('Y-m-01', strtotime('-6 months'));
                $fechaHasta = date('Y-m-t', strtotime('-1 months'));
            }

            // 3. Trasladar el rango de fechas 1 año hacia adelante para buscar la venta real del periodo proyectado
            $startReal = new DateTime($fechaDesde);
            $startReal->modify('+1 year');
            $fechaDesdeReal = $startReal->format('Y-m-d');

            $endReal = new DateTime($fechaHasta);
            $endReal->modify('+1 year');
            $fechaHastaReal = $endReal->format('Y-m-d');

            // 4. Determinar meses proyectados/reales cronológicos
            $mesesPeriodo = [];
            $traducciones = [
                'Jan' => 'Ene', 'Feb' => 'Feb', 'Mar' => 'Mar',
                'Apr' => 'Abr', 'May' => 'May', 'Jun' => 'Jun',
                'Jul' => 'Jul', 'Aug' => 'Ago', 'Sep' => 'Sep',
                'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Dic'
            ];

            $temp = clone $startReal;
            $tempEnd = clone $endReal;
            $temp->modify('first day of this month');
            $tempEnd->modify('first day of this month');

            while ($temp <= $tempEnd) {
                $mNum = (int)$temp->format('n');
                $yNum = (int)$temp->format('Y');
                $clave = "$mNum-$yNum";
                
                $engMonth = $temp->format('M');
                $espMonth = $traducciones[$engMonth] ?? $engMonth;
                $label = $espMonth . ' ' . $temp->format('y');

                $mesesPeriodo[$clave] = $label;
                $temp->modify('+1 month');
            }

            // 5. Obtener ventas reales en ese rango real trasladado
            $ventasRealesTodas = $this->distribucion->obtenerTodasLasVentasPorCanal($fechaDesdeReal, $fechaHastaReal);

            // 6. Obtener costos promedio globales como fallback
            $costosModel = new CostoProyeccion();
            $costosGuardados = $costosModel->obtenerParametrosGlobales();

            // 7. Consolidar datos
            $desviosCanales = [];
            $canales = ['LOCALES PROPIOS', 'FRANQUICIAS', 'MAYORISTAS', 'ECOMMERCE'];
            foreach ($canales as $c) {
                $desviosCanales[$c] = [
                    'canal' => $c,
                    'unidades_presupuesto' => 0,
                    'unidades_real' => 0,
                    'facturacion_usd_presupuesto' => 0.0,
                    'facturacion_usd_real' => 0.0,
                    'meses' => array_fill_keys(array_values($mesesPeriodo), [
                        'unidades_presupuesto' => 0,
                        'unidades_real' => 0,
                        'facturacion_usd_presupuesto' => 0.0,
                        'facturacion_usd_real' => 0.0
                    ]),
                    'sucursales' => []
                ];
            }

            foreach ($guardadosConsolidados as $g) {
                $rubro = trim($g['rubro']);
                $categoria = trim($g['categoria_padre']);
                $canal = trim($g['canal']);
                $canalUpper = strtoupper($canal);

                if (!isset($desviosCanales[$canalUpper])) {
                    continue;
                }

                // Obtener valor unitario guardado en consolidado o calcularlo de fallback
                $vventaUnit = isset($g['vventa_unit_usd']) && (float)$g['vventa_unit_usd'] > 0 
                    ? (float)$g['vventa_unit_usd'] 
                    : 0.0;

                if ($vventaUnit <= 0) {
                    $paramClave = "$rubro|$categoria";
                    $costoInfo = $costosGuardados[$paramClave] ?? ['costo_prom' => 0.0, 'inc_fob' => 0.0];
                    $vcosto = $costoInfo['costo_prom'] * (1 + ($costoInfo['inc_fob'] ?? 0.0) / 100);

                    $markup = 0.0;
                    if (strpos($canalUpper, 'LOCAL') !== false) $markup = $costoInfo['markup_locales_propios'] ?? 0.0;
                    elseif (strpos($canalUpper, 'FRANQ') !== false) $markup = $costoInfo['markup_franquicias'] ?? 0.0;
                    elseif (strpos($canalUpper, 'MAYOR') !== false) $markup = $costoInfo['markup_mayoristas'] ?? 0.0;
                    elseif (strpos($canalUpper, 'ECOM') !== false || strpos($canalUpper, 'WEB') !== false) $markup = $costoInfo['markup_ecommerce'] ?? 0.0;
                    $vventaUnit = $vcosto * $markup;
                }

                // A. Consolidar Presupuestado
                $unidadesP = (int)(isset($g['unidades_presupuestadas']) ? $g['unidades_presupuestadas'] : ($g['compra_distribuida'] ?? 0));
                $facturacionP = isset($g['facturacion_presupuestada_usd']) && (float)$g['facturacion_presupuestada_usd'] >= 0 
                    ? (float)$g['facturacion_presupuestada_usd'] 
                    : ($unidadesP * $vventaUnit);

                if ($facturacionP == 0 && $unidadesP > 0 && $vventaUnit > 0) {
                    $facturacionP = $unidadesP * $vventaUnit;
                }

                $desviosCanales[$canalUpper]['unidades_presupuesto'] += $unidadesP;
                $desviosCanales[$canalUpper]['facturacion_usd_presupuesto'] += $facturacionP;

                $mesesPresupuestoJson = $g['distribucion_mensual_json'] ?? null;
                $mesesPresupuesto = !empty($mesesPresupuestoJson) ? json_decode($mesesPresupuestoJson, true) : [];

                foreach ($mesesPeriodo as $mClave => $mLabel) {
                    $uPres = isset($mesesPresupuesto[$mLabel]) ? (int)$mesesPresupuesto[$mLabel]['unidades'] : 0;
                    $desviosCanales[$canalUpper]['meses'][$mLabel]['unidades_presupuesto'] += $uPres;
                    $desviosCanales[$canalUpper]['meses'][$mLabel]['facturacion_usd_presupuesto'] += ($uPres * $vventaUnit);
                }

                // B. Consolidar Realidad
                $ventasCanalObj = $ventasRealesTodas[$rubro][$categoria] ?? [];
                $ventasCanalReal = [];
                foreach ($ventasCanalObj as $canalClave => $cData) {
                    if (trim($canalClave) === $canal) {
                        $ventasCanalReal = $cData;
                        break;
                    }
                }

                $unidadesRealCanal = 0;
                foreach ($mesesPeriodo as $mClave => $mLabel) {
                    $uReal = isset($ventasCanalReal['meses'][$mClave]) ? (int)$ventasCanalReal['meses'][$mClave] : 0;
                    $desviosCanales[$canalUpper]['meses'][$mLabel]['unidades_real'] += $uReal;
                    $desviosCanales[$canalUpper]['meses'][$mLabel]['facturacion_usd_real'] += ($uReal * $vventaUnit);
                    $unidadesRealCanal += $uReal;
                }
                $desviosCanales[$canalUpper]['unidades_real'] += $unidadesRealCanal;
                $desviosCanales[$canalUpper]['facturacion_usd_real'] += ($unidadesRealCanal * $vventaUnit);

                // C. Si es LOCALES PROPIOS, desglosar sucursales
                if ($canalUpper === 'LOCALES PROPIOS') {
                    // Obtener sucursales reales (incluye 'sucursales' con total y meses)
                    $sucursalesReales = isset($ventasCanalReal['sucursales']) ? $ventasCanalReal['sucursales'] : [];

                    // Calcular venta real total de Locales Propios en el periodo para prorratear presupuesto por sucursal
                    $ventaTotalLocalesReal = 0;
                    foreach ($sucursalesReales as $sData) {
                        $ventaTotalLocalesReal += (int)($sData['total'] ?? 0);
                    }

                    $todasSucursales = array_keys($sucursalesReales);

                    foreach ($todasSucursales as $sucName) {
                        $sucName = trim($sucName);
                        if (empty($sucName)) continue;

                        if (!isset($desviosCanales['LOCALES PROPIOS']['sucursales'][$sucName])) {
                            $desviosCanales['LOCALES PROPIOS']['sucursales'][$sucName] = [
                                'sucursal' => $sucName,
                                'unidades_presupuesto' => 0,
                                'unidades_real' => 0,
                                'facturacion_usd_presupuesto' => 0.0,
                                'facturacion_usd_real' => 0.0,
                                'meses' => array_fill_keys(array_values($mesesPeriodo), [
                                    'unidades_presupuesto' => 0,
                                    'unidades_real' => 0,
                                    'facturacion_usd_presupuesto' => 0.0,
                                    'facturacion_usd_real' => 0.0
                                ])
                            ];
                        }

                        $sucRealData = $sucursalesReales[$sucName] ?? null;
                        $sucRealTotal = (int)($sucRealData['total'] ?? 0);
                        $participacionSuc = $ventaTotalLocalesReal > 0 ? ($sucRealTotal / $ventaTotalLocalesReal) : (1 / max(1, count($todasSucursales)));

                        // Presupuesto Prorrateado por Sucursal
                        $sucTotalUnidadesP = 0;
                        foreach ($mesesPeriodo as $mClave => $mLabel) {
                            $uPresMesCanal = isset($mesesPresupuesto[$mLabel]) ? (int)$mesesPresupuesto[$mLabel]['unidades'] : 0;
                            $sucUPres = (int)round($uPresMesCanal * $participacionSuc);

                            $desviosCanales['LOCALES PROPIOS']['sucursales'][$sucName]['meses'][$mLabel]['unidades_presupuesto'] += $sucUPres;
                            $desviosCanales['LOCALES PROPIOS']['sucursales'][$sucName]['meses'][$mLabel]['facturacion_usd_presupuesto'] += ($sucUPres * $vventaUnit);
                            $sucTotalUnidadesP += $sucUPres;
                        }
                        $desviosCanales['LOCALES PROPIOS']['sucursales'][$sucName]['unidades_presupuesto'] += $sucTotalUnidadesP;
                        $desviosCanales['LOCALES PROPIOS']['sucursales'][$sucName]['facturacion_usd_presupuesto'] += ($sucTotalUnidadesP * $vventaUnit);

                        // Real Sucursal
                        $sucTotalUnidadesR = 0;
                        foreach ($mesesPeriodo as $mClave => $mLabel) {
                            $sucUReal = isset($sucRealData['meses'][$mClave]) ? (int)$sucRealData['meses'][$mClave] : 0;
                            $desviosCanales['LOCALES PROPIOS']['sucursales'][$sucName]['meses'][$mLabel]['unidades_real'] += $sucUReal;
                            $desviosCanales['LOCALES PROPIOS']['sucursales'][$sucName]['meses'][$mLabel]['facturacion_usd_real'] += ($sucUReal * $vventaUnit);
                            $sucTotalUnidadesR += $sucUReal;
                        }
                        $desviosCanales['LOCALES PROPIOS']['sucursales'][$sucName]['unidades_real'] += $sucTotalUnidadesR;
                        $desviosCanales['LOCALES PROPIOS']['sucursales'][$sucName]['facturacion_usd_real'] += ($sucTotalUnidadesR * $vventaUnit);
                    }
                }
            }

            // Aplanar las sucursales de Locales Propios para devolver una lista
            if (isset($desviosCanales['LOCALES PROPIOS'])) {
                $listaSucursales = array_values($desviosCanales['LOCALES PROPIOS']['sucursales']);
                usort($listaSucursales, function($a, $b) {
                    return $b['facturacion_usd_real'] <=> $a['facturacion_usd_real'];
                });
                $desviosCanales['LOCALES PROPIOS']['sucursales'] = $listaSucursales;
            }

            $this->jsonResponse([
                'success' => true,
                'desvios' => array_values($desviosCanales),
                'meses' => array_values($mesesPeriodo),
                'meses_claves' => array_keys($mesesPeriodo),
                'fecha_desde_real' => $fechaDesdeReal,
                'fecha_hasta_real' => $fechaHastaReal
            ]);

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
