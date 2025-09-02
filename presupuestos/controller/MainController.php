
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
            $etiquetas = PresupuestoCalculos::generarEtiquetasVentaProyectada();
            
            // Log de debug
            error_log("✅ VERANO - Procesados " . count($datosProcessados) . " registros con contexto específico");
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Datos de compra proyectada verano obtenidos',
                'data' => $datosProcessados,
                'columnas_venta' => $columnasVenta,
                'etiquetas' => $etiquetas,
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
            $etiquetas = PresupuestoCalculos::generarEtiquetasVentaProyectada();
            
            // Log de debug
            error_log("✅ INVIERNO - Procesados " . count($datosProcessados) . " registros con contexto específico");
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Datos de compra proyectada invierno obtenidos',
                'data' => $datosProcessados,
                'columnas_venta' => $columnasVenta,
                'etiquetas' => $etiquetas,
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
            $etiquetas = PresupuestoCalculos::generarEtiquetasVentaProyectada($fechaActual);
            
            $info = [
                'fecha_actual' => $fechaActual->format('Y-m-d H:i:s'),
                'temporada_actual' => $temporadaActual,
                'dias_restantes' => $diasRestantes,
                'etiquetas_proyeccion' => $etiquetas,
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
        } catch (Exception $e) {
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

            // Ventas 6 meses
            case 'ventas-6-meses':
            case 'buscar-ventas':
            case 'rubros-ventas':
            case 'categorias-ventas':
            case 'resumen-ventas':
                $this->delegarVentas($accion);
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
                $this->delegarHistorial($accion);
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
        }
    }
}

// Si se accede directamente al archivo, procesar la solicitud
if (basename($_SERVER['PHP_SELF']) === 'MainController.php') {
    $controller = new MainController();
    $controller->procesarSolicitud();
}

?>