
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

                // Venta real de la temporada en curso: hasta qué día llega y si quedan
                // días sin contar entre esa fecha y hoy (ver infoVentaTemporadaEnCurso).
                $infoTemporada['venta_en_curso'] = PresupuestoCalculos::infoVentaTemporadaEnCurso(
                    $datos[0]['ULT_ACTUALIZACION'] ?? null,
                    !empty($datos) && array_key_exists('VTA_TEMPORADA_EN_CURSO', $datos[0])
                );

                // Límites de edición y días de la cobertura (Parámetros). Viajan con la
                // carga para que cada edición use los mismos valores que la carga.
                require_once __DIR__ . '/../class/parametros.php';
                $infoTemporada['parametros'] = Parametros::actual()->paraCliente();

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
     * Datos para la solapa de Compra Proyectada Verano
     */
    public function obtenerCompraProyectadaVerano() {
        $this->responderCompra('verano');
    }

    /**
     * Datos para la solapa de Compra Proyectada Invierno
     */
    public function obtenerCompraProyectadaInvierno() {
        $this->responderCompra('invierno');
    }

    /**
     * Una solapa de compra recalculada con índices editables impuestos por fila.
     *
     * Es lo que usa "Retomar versión": los índices guardados se aplican sobre los
     * datos de HOY (stock, ventas, OC) y el servidor recalcula venta, compra y tramos
     * con la misma función que el render normal. Se descartó aplicarlos en el
     * navegador fila por fila: habría sido una llamada a recalcular-tramos por fila
     * y una segunda implementación de la venta proyectada.
     *
     * Body: { solapa, indices: [{rubro, categoria_padre, indice_verano, indice_invierno}] }
     */
    public function obtenerCompraConIndices() {
        $entrada = json_decode(file_get_contents('php://input'), true) ?: [];
        $solapa = (strtolower($entrada['solapa'] ?? '') === 'invierno') ? 'invierno' : 'verano';

        $indices = [];
        foreach (($entrada['indices'] ?? []) as $fila) {
            $clave = trim((string)($fila['rubro'] ?? '')) . '|' . trim((string)($fila['categoria_padre'] ?? ''));
            $indices[$clave] = $fila;
        }

        $this->responderCompra($solapa, $indices);
    }

    /**
     * Arma la respuesta de una solapa de compra. Las etiquetas dependen de la solapa:
     * la misma columna cubre distinto período en verano que en invierno.
     *
     * @param array|null $indices índices impuestos por "rubro|categoria"; las filas
     *                            de hoy que no estén ahí quedan con su índice del SP.
     */
    private function responderCompra($solapa, $indices = null) {
        try {
            $datosBase = $this->obtenerDatosValidados();
            $faltantes = [];

            if ($indices !== null) {
                $hoy = [];
                foreach ($datosBase as &$registro) {
                    $clave = trim($registro['RUBRO'] ?? '') . '|' . trim($registro['CATEGORIA_PADRE'] ?? '');
                    $hoy[$clave] = true;
                    if (!isset($indices[$clave])) {
                        continue;
                    }
                    // Validados igual que una edición manual: un índice fuera de rango
                    // en una versión vieja no puede producir una proyección absurda.
                    if (isset($indices[$clave]['indice_verano'])) {
                        $registro['_INDICE_EDITADO_VERANO'] = PresupuestoCalculos::validarIndiceVariacion($indices[$clave]['indice_verano']);
                    }
                    if (isset($indices[$clave]['indice_invierno'])) {
                        $registro['_INDICE_EDITADO_INVIERNO'] = PresupuestoCalculos::validarIndiceVariacion($indices[$clave]['indice_invierno']);
                    }
                    // Venta cargada a mano e índice de cobertura de la versión. Solo los
                    // usa el cálculo de Argentina; en Uruguay no vienen y no se leen.
                    foreach (['venta_manual_verano' => '_VENTA_MANUAL_VERANO', 'venta_manual_invierno' => '_VENTA_MANUAL_INVIERNO'] as $origen => $destino) {
                        if (isset($indices[$clave][$origen]) && $indices[$clave][$origen] !== '') {
                            $registro[$destino] = max(0, (int)$indices[$clave][$origen]);
                        }
                    }
                    if (isset($indices[$clave]['indice_cobertura']) && $indices[$clave]['indice_cobertura'] !== '') {
                        $registro['_INDICE_COBERTURA_EDITADO'] = max(0.0, (float)$indices[$clave]['indice_cobertura']);
                    }
                }
                unset($registro);

                // Filas que estaban en la versión y hoy no existen: se informan para
                // que no desaparezcan en silencio.
                foreach ($indices as $clave => $fila) {
                    if (!isset($hoy[$clave])) {
                        $faltantes[] = ['rubro' => $fila['rubro'] ?? '', 'categoria_padre' => $fila['categoria_padre'] ?? ''];
                    }
                }
            }

            $datosProcesados = $solapa === 'invierno'
                ? $this->procesador->procesarDatosCompraInvierno($datosBase)
                : $this->procesador->procesarDatosCompraVerano($datosBase);

            $columnasVenta = PresupuestoCalculos::obtenerColumnasVentas($datosBase, strtoupper($solapa));

            $this->jsonResponse([
                'success' => true,
                'message' => "Datos de compra proyectada $solapa obtenidos",
                'data' => $datosProcesados,
                'columnas_venta' => $columnasVenta,
                'etiquetas_historicas' => $this->etiquetarColumnasHistoricas($columnasVenta),
                'etiquetas' => PresupuestoCalculos::generarEtiquetasVentaProyectada(null, $solapa),
                'periodos' => PresupuestoCalculos::obtenerPeriodosProyeccion(null, $solapa),
                'total_registros' => count($datosProcesados),
                'contexto_aplicado' => $solapa,
                'faltantes' => $faltantes
            ]);

        } catch (Exception $e) {
            error_log("❌ Error en la solapa de compra $solapa: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => "Error al procesar compra $solapa: " . $e->getMessage(),
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
     * Recalcula el reparto por tramo de una sola fila.
     *
     * Lo llama el editor de índices después de cambiar un índice: la venta
     * proyectada la recalcula el navegador (fórmula simple, ya replicada y
     * verificada), pero el reparto del stock entre tramos NO se duplica allá.
     * Se le pasan los números de la fila y devuelve los tramos ya repartidos.
     */
    public function recalcularTramos() {
        try {
            $entrada = json_decode(file_get_contents('php://input'), true) ?: [];

            $solapa = (strtolower($entrada['solapa'] ?? '') === 'invierno') ? 'invierno' : 'verano';

            $indiceVerano = (float)($entrada['indice_verano'] ?? 1.0);
            $indiceInvierno = (float)($entrada['indice_invierno'] ?? $indiceVerano);

            $tramos = PresupuestoCalculos::calcularTramosDeFila(
                (float)($entrada['stock_proyectado'] ?? 0),
                (float)($entrada['venta_verano_anterior'] ?? 0),   $indiceVerano,
                (float)($entrada['venta_invierno_anterior'] ?? 0), $indiceInvierno,
                null,
                $solapa
            );

            $this->jsonResponse([
                'success' => true,
                'tramos' => $tramos
            ]);

        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al recalcular los tramos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalcula UNA fila de Argentina con PresupuestoCalculos::calcularFila(), la
     * misma función que la carga de las solapas.
     *
     * Reemplaza a recalcular-tramos en Argentina: desde que la cobertura depende de
     * la venta proyectada, editar un índice mueve también la cobertura, el stock
     * proyectado y la compra, y nada de eso se recalcula en el navegador.
     *
     * Body: { solapa, editado, entrada: {cant_*, venta_*_anterior, indice_*,
     *         venta_manual_*, indice_cobertura, vta_temporada_en_curso},
     *         dias_por_mes, dias_minimos }
     * "editado" (indice_verano, venta_verano, ...) dice qué valor tocó el usuario,
     * para validar solo ese contra el rango del índice de variación.
     */
    public function recalcularFila() {
        try {
            require_once __DIR__ . '/../class/parametros.php';
            $parametros = Parametros::actual();
            if (!$parametros->esArgentina()) {
                $this->jsonResponse(['success' => false, 'message' => 'recalcular-fila es solo de Argentina.'], 400);
            }

            $entrada = json_decode(file_get_contents('php://input'), true) ?: [];
            $solapa = (strtolower($entrada['solapa'] ?? '') === 'invierno') ? 'invierno' : 'verano';
            $e = $entrada['entrada'] ?? [];

            $numericos = ['cant_stock', 'cant_stock_guardar', 'cant_pend_oc_verano', 'cant_pend_oc_invierno',
                          'cant_pend_oc_atemporal', 'venta_verano_anterior', 'venta_invierno_anterior',
                          'indice_verano', 'indice_invierno', 'indice_cobertura'];
            foreach ($numericos as $campo) {
                $e[$campo] = (float)($e[$campo] ?? 0);
            }
            foreach (['venta_manual_verano', 'venta_manual_invierno'] as $campo) {
                $v = $e[$campo] ?? null;
                if ($v !== null && $v !== '' && (!is_numeric($v) || $v < 0)) {
                    $this->jsonResponse(['success' => false, 'message' => 'La venta tiene que ser un número mayor o igual a 0.'], 400);
                }
                $e[$campo] = ($v === null || $v === '') ? null : (int)round((float)$v);
            }
            $e['vta_temporada_en_curso'] = isset($e['vta_temporada_en_curso']) && $e['vta_temporada_en_curso'] !== null
                ? (float)$e['vta_temporada_en_curso'] : null;

            $ult = $this->presupuesto->obtenerUltActualizacion();
            $generales = [];
            if (isset($entrada['dias_por_mes']) && is_numeric($entrada['dias_por_mes'])) {
                $generales['dias_por_mes'] = (float)$entrada['dias_por_mes'];
            }
            if (isset($entrada['dias_minimos']) && is_numeric($entrada['dias_minimos'])) {
                $generales['dias_minimos'] = (int)$entrada['dias_minimos'];
            }
            $ctx = PresupuestoCalculos::contextoCalculo($solapa, null, $ult['ult'], $ult['hay_vendido'], $parametros, $generales);

            $r = PresupuestoCalculos::calcularFila($e, $ctx);

            // Rango del índice de variación (Parámetros) para el valor que se tocó. Una
            // venta cargada se valida por el índice que implica; sin base no hay índice
            // y se acepta cualquier venta >= 0.
            $editado = (string)($entrada['editado'] ?? '');
            $min = $parametros->numero('VARIACION_INDICE_MIN');
            $max = $parametros->numero('VARIACION_INDICE_MAX');
            foreach (['verano', 'invierno'] as $col) {
                $indice = null;
                if ($editado === "venta_$col" && $r["venta_manual_$col"] !== null) $indice = $r["indice_$col"];
                if ($editado === "indice_$col") $indice = $e["indice_$col"];
                if ($indice !== null && ($indice < $min - 1e-9 || $indice > $max + 1e-9)) {
                    $this->jsonResponse([
                        'success' => false,
                        'codigo' => 'fuera_de_rango',
                        'message' => sprintf('Ese valor implica un índice de %s, fuera del rango permitido (%s a %s). No se aplicó.',
                            number_format($indice, 4, ',', '.'), number_format($min, 2, ',', '.'), number_format($max, 2, ',', '.'))
                    ]);   // 200: es una validación de negocio, no un error del servidor
                }
            }

            $this->jsonResponse([
                'success' => true,
                'fila' => ProcesadorDatos::camposCalculoArgentina($r, $e)
            ]);

        } catch (Exception $ex) {
            $this->jsonResponse(['success' => false, 'message' => 'Error al recalcular la fila: ' . $ex->getMessage()], 500);
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

            // Pantalla de Parámetros (solo Argentina)
            case 'parametros':
                $this->delegarParametros($accion);
                break;

            // Versiones del presupuesto (cabecera) y trazabilidad de la oficial
            case 'versiones-presupuesto':
            case 'historial-oficial':
            case 'validar-nombre-version':
            case 'version-para-retomar':
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
            case 'obtener-periodo-default-markup':
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
            // Reparto por tramo de UNA fila, después de editar un índice.
            // Va al servidor en vez de recalcularse en el navegador porque el
            // reparto tiene una sola implementación (repartirCompraPorTramo) y
            // duplicarla en JS es exactamente lo que volvió a separar los números
            // la última vez. Es una fila por edición, así que el viaje no pesa.
            case 'recalcular-tramos':
                $this->recalcularTramos();
                break;
            // Argentina: toda la fila (venta, índices, cobertura, stock, compra y
            // tramos) después de editar un índice, una venta o la cobertura.
            case 'recalcular-fila':
                $this->recalcularFila();
                break;
            case 'guardar-parametro':
            case 'guardar-cobertura-parametro':
            case 'borrar-cobertura-parametro':
            case 'agregar-lista-parametro':
            case 'quitar-lista-parametro':
                $this->delegarParametros($accion);
                break;
            // Solapa de compra con los índices de una versión guardada ("Retomar").
            case 'compra-con-indices':
                $this->obtenerCompraConIndices();
                break;
            case 'guardar-presupuesto':
            case 'buscar-historial':
            case 'marcar-oficial':
            case 'desmarcar-oficial':
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
            case 'calcular-costo-mediana':
            case 'calcular-markup-real':
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
     * Pantalla de Parámetros: lectura y cambios con auditoría (ver ParametrosController).
     */
    private function delegarParametros($accion) {
        require_once __DIR__ . '/ParametrosController.php';
        (new ParametrosController())->procesar($accion);
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
            case 'validar-nombre-version':
                $historialController->validarNombreVersion();
                break;
            case 'version-para-retomar':
                $historialController->versionParaRetomar();
                break;
            case 'marcar-oficial':
                $historialController->marcarOficial();
                break;
            case 'desmarcar-oficial':
                $historialController->desmarcarOficial();
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
            case 'obtener-periodo-default-markup':
                $distribucionController->obtenerPeriodoDefaultMarkup();
                break;
            case 'guardar-parametros-costos':
                $distribucionController->guardarParametrosGlobales();
                break;
            case 'calcular-costo-mediana':
                $distribucionController->calcularCostoMedianaParametros();
                break;
            case 'calcular-markup-real':
                $distribucionController->calcularMarkupReal();
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
