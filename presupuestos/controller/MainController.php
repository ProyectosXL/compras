
<?php

/**
 * Controlador principal actualizado que incluye funcionalidades de compras
 */
class MainController {
    
    private $presupuesto;
    private $procesador;
    
    public function __construct() {
        require_once __DIR__ . '/../class/presupuesto.php';
        require_once __DIR__ . '/../class/PresupuestoCalculos.php';
        require_once __DIR__ . '/ProcesadorDatos.php';
        
        $this->presupuesto = new Presupuesto();
        $this->procesador = new ProcesadorDatos();
    }
    
    /**
     * Obtener datos base del presupuesto de compras
     */
    public function obtenerDatosBase() {
        try {
            $datos = $this->presupuesto->obtenerPresupuestoCompras();
            
            if (isset($datos['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $datos['mensaje'],
                    'data' => []
                ], 500);
            } else {
                // Agregar información de temporada
                $infoTemporada = PresupuestoCalculos::obtenerInfoTemporada();

                // Extraer ULT_ACTUALIZACION del primer registro de la tabla
                if (!empty($datos) && isset($datos[0]['ULT_ACTUALIZACION'])) {
                    $ultAct = $datos[0]['ULT_ACTUALIZACION'];
                    if ($ultAct instanceof DateTime) {
                        $ultAct = $ultAct->format('d/m/Y H:i');
                    }
                    $infoTemporada['ult_actualizacion'] = $ultAct;
                }

                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Datos base obtenidos correctamente',
                    'data' => $datos,
                    'info_temporada' => $infoTemporada,
                    'total_registros' => count($datos)
                ]);
            }
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }
    
    /**
     * CORREGIDO: Obtener datos para la solapa de Compra Proyectada Verano CON CONTEXTO
     */
    public function obtenerCompraProyectadaVerano() {
        try {
            $datosBase = $this->obtenerDatosValidados();
            
            // CORRECCIÓN: Pasar contexto 'verano' al procesador
            $datosProcessados = $this->procesador->procesarDatosCompraVerano($datosBase);
            
            $columnasVenta = PresupuestoCalculos::obtenerColumnasVentas($datosBase, 'VERANO');
            // Las etiquetas dependen de la solapa: la misma columna cubre distinto período
            // en verano que en invierno, así que no se pueden calcular una sola vez para las dos.
            $etiquetas = PresupuestoCalculos::generarEtiquetasVentaProyectada(null, 'verano');
            $periodos = PresupuestoCalculos::obtenerPeriodosProyeccion(null, 'verano');
            $etiquetasHistoricas = $this->etiquetarColumnasHistoricas($columnasVenta);
            
            // Log de debug
            error_log("✅ VERANO - Procesados " . count($datosProcessados) . " registros con contexto específico");
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Datos de compra proyectada verano obtenidos',
                'data' => $datosProcessados,
                'columnas_venta' => $columnasVenta,
                'etiquetas_historicas' => $etiquetasHistoricas,
                'etiquetas' => $etiquetas,
                'periodos' => $periodos,
                'total_registros' => count($datosProcessados),
                'contexto_aplicado' => 'verano'
            ]);
            
        } catch (Exception $e) {
            error_log("❌ Error en obtenerCompraProyectadaVerano: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al procesar compra verano: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }
    
    /**
     * CORREGIDO: Obtener datos para la solapa de Compra Proyectada Invierno CON CONTEXTO
     */
    public function obtenerCompraProyectadaInvierno() {
        try {
            $datosBase = $this->obtenerDatosValidados();
            
            // CORRECCIÓN: Pasar contexto 'invierno' al procesador
            $datosProcessados = $this->procesador->procesarDatosCompraInvierno($datosBase);
            
            $columnasVenta = PresupuestoCalculos::obtenerColumnasVentas($datosBase, 'INVIERNO');
            $etiquetas = PresupuestoCalculos::generarEtiquetasVentaProyectada(null, 'invierno');
            $periodos = PresupuestoCalculos::obtenerPeriodosProyeccion(null, 'invierno');
            $etiquetasHistoricas = $this->etiquetarColumnasHistoricas($columnasVenta);
            
            // Log de debug
            error_log("✅ INVIERNO - Procesados " . count($datosProcessados) . " registros con contexto específico");
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Datos de compra proyectada invierno obtenidos',
                'data' => $datosProcessados,
                'columnas_venta' => $columnasVenta,
                'etiquetas_historicas' => $etiquetasHistoricas,
                'etiquetas' => $etiquetas,
                'periodos' => $periodos,
                'total_registros' => count($datosProcessados),
                'contexto_aplicado' => 'invierno'
            ]);
            
        } catch (Exception $e) {
            error_log("❌ Error en obtenerCompraProyectadaInvierno: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al procesar compra invierno: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * NUEVO: Método para debug de temporadas desde el backend
     */
    public function obtenerInfoTemporadas() {
        try {
            $fechaActual = new DateTime();
            $temporadaActual = PresupuestoCalculos::obtenerTemporadaActual($fechaActual);
            $diasRestantes = PresupuestoCalculos::calcularDiasRestantesTemporada($fechaActual);

            $info = [
                'fecha_actual' => $fechaActual->format('Y-m-d H:i:s'),
                'temporada_actual' => $temporadaActual,
                'dias_restantes' => $diasRestantes,
                // Los períodos se informan por solapa porque la misma columna cubre
                // distinto rango en cada una; una sola etiqueta no alcanza para describirlas.
                'periodos' => [
                    'verano'   => PresupuestoCalculos::obtenerPeriodosProyeccion($fechaActual, 'verano'),
                    'invierno' => PresupuestoCalculos::obtenerPeriodosProyeccion($fechaActual, 'invierno')
                ],
                'logica_aplicada' => [
                    'verano_solapa' => $this->obtenerLogicaAplicada('verano', $temporadaActual),
                    'invierno_solapa' => $this->obtenerLogicaAplicada('invierno', $temporadaActual)
                ]
            ];
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Información de temporadas obtenida',
                'data' => $info
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error obteniendo info temporadas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mapea cada columna histórica del SP a su etiqueta en la convención de la app.
     *
     * Viaja como diccionario {columna => etiqueta} en vez de renombrar las claves de
     * los datos: el front necesita seguir accediendo por VTA_VERANO_26 para leer el
     * valor, y renombrar la clave habría roto el guardado y el Excel.
     */
    private function etiquetarColumnasHistoricas($columnas) {
        $etiquetas = [];
        foreach ($columnas as $columna) {
            $etiquetas[$columna] = PresupuestoCalculos::etiquetaColumnaHistorica($columna);
        }
        return $etiquetas;
    }

    /**
     * NUEVO: Obtener lógica aplicada para una solapa específica
     */
    private function obtenerLogicaAplicada($solapa, $temporadaActual) {
        $logica = [
            'contexto_solapa' => $solapa,
            'temporada_transitando' => $temporadaActual['temporada'],
            'calculo_verano' => '',
            'calculo_invierno' => ''
        ];
        
        if ($solapa === 'verano') {
            if ($temporadaActual['temporada'] === 'VERANO') {
                $logica['calculo_verano'] = 'Proporcional actual + Próximo completo';
                $logica['calculo_invierno'] = 'Próximo completo';
            } else if ($temporadaActual['temporada'] === 'INVIERNO') {
                $logica['calculo_verano'] = 'Próximo completo';
                $logica['calculo_invierno'] = 'Proporcional actual';
            } else {
                $logica['calculo_verano'] = 'Próximo completo';
                $logica['calculo_invierno'] = 'Próximo completo';
            }
        } else if ($solapa === 'invierno') {
            if ($temporadaActual['temporada'] === 'INVIERNO') {
                $logica['calculo_verano'] = 'Próximo completo';
                $logica['calculo_invierno'] = 'Proporcional actual + Próximo completo';
            } else if ($temporadaActual['temporada'] === 'VERANO') {
                $logica['calculo_verano'] = 'Proporcional actual';
                $logica['calculo_invierno'] = 'Próximo completo';
            } else {
                $logica['calculo_verano'] = 'Próximo completo';
                $logica['calculo_invierno'] = 'Próximo completo';
            }
        }
        
        return $logica;
    }
    
    /**
     * Obtener datos para la solapa de Stock Proyectado
     */
    public function obtenerStockProyectado() {
        try {
            $datosBase = $this->obtenerDatosValidados();
            $datosProcessados = $this->procesador->procesarDatosStockProyectado($datosBase);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Datos de stock proyectado obtenidos',
                'data' => $datosProcessados,
                'total_registros' => count($datosProcessados)
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al procesar stock proyectado: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }
    
    /**
     * Procesar solicitudes HTTP y rutear a controladores específicos
     */
    public function procesarSolicitud() {
        $metodo = $_SERVER['REQUEST_METHOD'];
        $accion = $_GET['accion'] ?? '';
        
        try {
            switch ($metodo) {
                case 'GET':
                    $this->procesarGet($accion);
                    break;
                case 'POST':
                    $this->procesarPost($accion);
                    break;
                default:
                    $this->jsonResponse([
                        'success' => false,
                        'message' => 'Método HTTP no permitido',
                        'metodos_permitidos' => ['GET', 'POST']
                    ], 405);
                    break;
            }
        } catch (Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error procesando solicitud: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * ACTUALIZADO: Procesar solicitudes GET con nuevas acciones
     */
    private function procesarGet($accion) {
        switch ($accion) {
            // Datos principales
            case 'datos-base':
                $this->obtenerDatosBase();
                break;
            case 'compra-verano':
                $this->obtenerCompraProyectadaVerano();
                break;
            case 'compra-invierno':
                $this->obtenerCompraProyectadaInvierno();
                break;
            case 'stock-proyectado':
                $this->obtenerStockProyectado();
                break;
                
            // NUEVO: Info de temporadas para debug
            case 'info-temporadas':
                $this->obtenerInfoTemporadas();
                break;

            // Versiones del presupuesto (cabecera) y trazabilidad de la oficial
            case 'versiones-presupuesto':
            case 'historial-oficial':
                $this->delegarHistorial($accion);
                break;
                
            // Compras detalle
            case 'compras-detalle':
            case 'buscar-compras':
            case 'proveedores':
            case 'rubros-compras':
            case 'resumen-compras':
                $this->delegarCompras($accion);
                break;
                
            // Funcionalidades de búsqueda
            case 'buscar':
            case 'rubros':
            case 'filtrar-rubro':
                $this->delegarBusqueda($accion);
                break;
                
            // Funcionalidades de exportación
            case 'exportar':
                $this->delegarExportacion();
                break;
                
            // Funcionalidades de índices
            case 'estadisticas-indices':
                $this->delegarIndices($accion);
                break;

            // Contenedores (OC Pendientes + Importaciones)
            case 'contenedores-detalle':
            case 'proveedores-contenedores':
            case 'rubros-contenedores':
                $this->delegarContenedores($accion);
                break;

            // Ventas 6 meses
            case 'ventas-6-meses':
            case 'buscar-ventas':
            case 'rubros-ventas':
            case 'categorias-ventas':
            case 'resumen-ventas':
                $this->delegarVentas($accion);
                break;

            // Distribución por canal
            case 'distribucion-canal':
            case 'canales-disponibles':
            case 'obtener-versiones':
            case 'costos-proyeccion':
            case 'obtener-tipo-cambio':
            case 'obtener-parametros-costos':
            case 'obtener-desvios':
                $this->delegarDistribucion($accion);
                break;
                
            default:
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Acción no válida',
                    'acciones_disponibles' => [
                        'datos-base', 'compra-verano', 'compra-invierno', 
                        'stock-proyectado', 'info-temporadas', 'compras-detalle', 
                        'buscar', 'exportar', 'rubros', 'proveedores'
                    ]
                ], 400);
                break;
        }
    }
    
    /**
     * Procesar solicitudes POST
     */
    private function procesarPost($accion) {
        switch ($accion) {
            case 'actualizar-indice':
            case 'actualizar-multiples-indices':
            case 'resetear-indices':
                $this->delegarIndices($accion);
                break;
            case 'guardar-presupuesto':
            case 'buscar-historial':
            case 'marcar-oficial':
            // No se llama 'eliminar-version' porque ese nombre ya lo usa
            // distribución para borrar sus propias versiones.
            case 'eliminar-version-presupuesto':
                $this->delegarHistorial($accion);
                break;
            case 'distribucion-canal':
            case 'canales-disponibles':
            case 'guardar-distribucion':
            case 'guardar-costos':
            case 'guardar-parametros-costos':
            case 'eliminar-version':
                $this->delegarDistribucion($accion);
                break;
            default:
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Acción POST no válida'
                ], 400);
                break;
        }
    }
    
    /**
     * Delegar funcionalidades de compras - NUEVO
     */
    private function delegarCompras($accion) {
        require_once __DIR__ . '/ComprasController.php';
        $comprasController = new ComprasController();
        
        switch ($accion) {
            case 'compras-detalle':
                $comprasController->obtenerComprasDetalle();
                break;
            case 'buscar-compras':
                $comprasController->buscarComprasDetalle();
                break;
            case 'proveedores':
                $comprasController->obtenerProveedores();
                break;
            case 'rubros-compras':
                $comprasController->obtenerRubros();
                break;
            case 'resumen-compras':
                $comprasController->obtenerResumenCompras();
                break;
        }
    }
    
    /**
     * Delegar funcionalidades de búsqueda
     */
    private function delegarBusqueda($accion) {
        require_once __DIR__ . '/BusquedaController.php';
        $busquedaController = new BusquedaController();
        
        switch ($accion) {
            case 'buscar':
                $busquedaController->buscarDatos();
                break;
            case 'rubros':
                $busquedaController->obtenerRubros();
                break;
            case 'filtrar-rubro':
                $busquedaController->filtrarPorRubro();
                break;
        }
    }
    
    /**
     * Delegar funcionalidades de exportación
     */
    private function delegarExportacion() {
        require_once __DIR__ . '/ExportacionController.php';
        $exportacionController = new ExportacionController();
        $exportacionController->exportarExcel();
    }
    
    /**
     * Delegar funcionalidades de índices
     */
    private function delegarIndices($accion) {
        require_once __DIR__ . '/IndiceController.php';
        $indiceController = new IndiceController();
        
        switch ($accion) {
            case 'actualizar-indice':
                $indiceController->actualizarIndiceVariacion();
                break;
            case 'actualizar-multiples-indices':
                $indiceController->actualizarMultiplesIndices();
                break;
            case 'resetear-indices':
                $indiceController->resetearIndices();
                break;
            case 'estadisticas-indices':
                $indiceController->obtenerEstadisticasIndices();
                break;
        }
    }
    
    /**
     * Obtener datos base con validación
     */
    private function obtenerDatosValidados() {
        $datos = $this->presupuesto->obtenerPresupuestoCompras();
        
        if (isset($datos['error'])) {
            throw new Exception($datos['mensaje']);
        }
        
        return $datos;
    }
    
    /**
     * Enviar respuesta JSON
     */
    private function jsonResponse($data, $httpCode = 200) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Delegar funcionalidades de contenedores
     */
    private function delegarContenedores($accion) {
        require_once __DIR__ . '/ContenedoresController.php';
        $contenedoresController = new ContenedoresController();

        switch ($accion) {
            case 'contenedores-detalle':
                $contenedoresController->obtenerContenedoresDetalle();
                break;
            case 'proveedores-contenedores':
                $contenedoresController->obtenerProveedores();
                break;
            case 'rubros-contenedores':
                $contenedoresController->obtenerRubros();
                break;
        }
    }

    /**
     * Delegar funcionalidades de ventas - NUEVO
     */
    private function delegarVentas($accion) {
        require_once __DIR__ . '/VentasController.php';
        $ventasController = new VentasController();
        
        switch ($accion) {
            case 'ventas-6-meses':
                $ventasController->obtenerVentas6Meses();
                break;
            case 'buscar-ventas':
                $ventasController->buscarVentas6Meses();
                break;
            case 'rubros-ventas':
                $ventasController->obtenerRubrosVentas();
                break;
            case 'categorias-ventas':
                $ventasController->obtenerCategoriasVentas();
                break;
            case 'resumen-ventas':
                $ventasController->obtenerResumenVentas();
                break;
        }
    }

    /**
     * Delegar funcionalidades de historial - NUEVO
     */
    private function delegarHistorial($accion) {
        require_once __DIR__ . '/HistorialController.php';
        $historialController = new HistorialController();

        switch ($accion) {
            case 'guardar-presupuesto':
                $historialController->guardarPresupuesto();
                break;
            case 'buscar-historial':
                $historialController->buscarHistorial();
                break;
            case 'versiones-presupuesto':
                $historialController->listarVersiones();
                break;
            case 'marcar-oficial':
                $historialController->marcarOficial();
                break;
            case 'eliminar-version-presupuesto':
                $historialController->eliminarVersion();
                break;
            case 'historial-oficial':
                $historialController->historialOficial();
                break;
        }
    }

    /**
     * Delegar funcionalidades de distribución por canal
     */
    private function delegarDistribucion($accion) {
        require_once __DIR__ . '/DistribucionController.php';
        $distribucionController = new DistribucionController();

        switch ($accion) {
            case 'distribucion-canal':
                $distribucionController->obtenerDistribucionPorCanal();
                break;
            case 'canales-disponibles':
                $distribucionController->obtenerCanales();
                break;
            case 'obtener-versiones':
                $distribucionController->obtenerVersiones();
                break;
            case 'costos-proyeccion':
                $distribucionController->obtenerCostosProyeccion();
                break;
            case 'guardar-costos':
                $distribucionController->guardarCostos();
                break;
            case 'guardar-distribucion':
                $distribucionController->guardarDistribucion();
                break;
            case 'obtener-tipo-cambio':
                $distribucionController->obtenerTipoCambio();
                break;
            case 'obtener-parametros-costos':
                $distribucionController->obtenerParametrosGlobales();
                break;
            case 'guardar-parametros-costos':
                $distribucionController->guardarParametrosGlobales();
                break;
            case 'eliminar-version':
                $distribucionController->eliminarVersion();
                break;
            case 'obtener-desvios':
                require_once __DIR__ . '/AnalisisDesviosController.php';
                $desviosController = new AnalisisDesviosController();
                $desviosController->obtenerDesvios();
                break;
        }
    }
}

// Si se accede directamente al archivo, procesar la solicitud
if (basename($_SERVER['PHP_SELF']) === 'MainController.php') {
    $controller = new MainController();
    $controller->procesarSolicitud();
}

?>