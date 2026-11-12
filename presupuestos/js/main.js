
// Sistema de Presupuesto de Compras - JavaScript Principal
// Archivo: presupuestos/js/main.js

/**
 * Clase principal que coordina todas las funcionalidades
 */
class PresupuestoApp {
    constructor() {
        this.apiUrl = 'api.php';
        this.datos = {
            verano: [],
            invierno: [],
            stock: []
        };
        this.temporadaInfo = null;
        this.estado = {
            cargando: false,
            inicializado: false,
            ultimaActualizacion: null,
            tablasLimpias: {
                verano: false,
                invierno: false,
                stock: false
            }
        };
        this.init();
    }

    /**
     * Inicializar la aplicación
     */
    init() {
        this.actualizarUltimaActualizacion();
        this.configurarEventListeners();
        this.configurarSwitchPais();
        this.cargarPreferenciasUsuario();
        this.estado.inicializado = true;
        console.log('PresupuestoApp inicializado correctamente');
    }

    /**
     * Configurar event listeners
     */
    configurarEventListeners() {
        // Event listeners para tabs
        document.addEventListener('DOMContentLoaded', () => {
            this.configurarTabs();
            this.configurarBusqueda();
            this.configurarShortcuts();
        });

        // Event listener para cierre de ventana
        window.addEventListener('beforeunload', () => {
            this.guardarPreferenciasUsuario();
        });

        // ELIMINADO: Event listener para cambios de visibilidad - Sin auto-updates
    }

    /**
     * Configurar tabs
     */
    configurarTabs() {
        const tabs = document.querySelectorAll('#presupuestoTabs button[data-bs-toggle="tab"]');
        tabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', (event) => {
                const targetId = event.target.getAttribute('data-bs-target');
                const solapa = targetId.replace('#', '');
                this.onTabChange(solapa);
            });
        });
    }

    /**
     * Configurar búsqueda en inputs
     */
    configurarBusqueda() {
        ['verano', 'invierno', 'stock'].forEach(solapa => {
            const input = document.getElementById(`search-${solapa}`);
            if (input) {
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.buscarDatos(solapa);
                    }
                });
            }
        });
    }

    /**
     * Configurar shortcuts de teclado
     */
    configurarShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl + L para cargar datos
            if (e.ctrlKey && e.key === 'l') {
                e.preventDefault();
                this.cargarDatos();
            }
            
            // Ctrl + E para exportar completo
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                this.exportarExcel('completo');
            }
            
            // F5 para recargar datos (en lugar de página)
            if (e.key === 'F5') {
                e.preventDefault();
                this.cargarDatos();
            }
        });
    }

    /**
     * Configurar switch de país
     */
    configurarSwitchPais() {
        // Cargar país guardado
        const paisGuardado = StorageUtils.obtener('pais_seleccionado') || 'argentina';
        this.aplicarConfiguracionPais(paisGuardado);
        
        // Configurar el switch según el país guardado
        const switchPais = document.getElementById('country-switch');
        if (switchPais) {
            switchPais.checked = paisGuardado === 'uruguay';
        }
    }

    /**
     * Aplicar configuración de país
     */
    aplicarConfiguracionPais(pais) {
        const titulo = document.getElementById('titulo-sistema');
        const label = document.getElementById('country-label');
        const switchPais = document.getElementById('country-switch');
        
        if (pais === 'uruguay') {
            if (titulo) titulo.textContent = 'Presupuesto de Compras – Uruguay';
            if (label) label.innerHTML = '<i class="fas fa-flag me-1"></i>Uruguay';
            if (switchPais) switchPais.checked = true;
        } else {
            if (titulo) titulo.textContent = 'Presupuesto de Compras – Argentina';
            if (label) label.innerHTML = '<i class="fas fa-flag me-1"></i>Argentina';
            if (switchPais) switchPais.checked = false;
        }
        
        // Guardar selección
        StorageUtils.guardar('pais_seleccionado', pais, 30 * 24 * 60 * 60 * 1000); // 30 días

        // Parámetros es solo de Argentina: con Uruguay el botón queda deshabilitado.
        if (typeof ParametrosManager !== 'undefined') ParametrosManager.actualizarBoton(pais);
    }

    /**
     * ELIMINADO: configurarIntervalos() - Sin actualizaciones automáticas
     */

    /**
     * Cargar todos los datos
     */

    async cargarDatos() {
        if (this.estado.cargando) {
            UIUtils.mostrarAlerta('Ya se están cargando los datos...', 'warning');
            return;
        }

        try {
            this.estado.cargando = true;
            UIUtils.mostrarLoading(true);
            UIUtils.mostrarTabsContainer(false);
            
            // IMPORTANTE: Resetear estado de tablas antes de cargar nuevos datos
            this.resetearEstadoTablas();
            
            // Cargar datos base primero
            const datosBase = await APIClient.obtenerDatosBase();
            
            if (datosBase.success) {
                this.temporadaInfo = datosBase.info_temporada;
                UIUtils.mostrarInfoTemporada(datosBase.info_temporada);
                
                // Cargar datos de todas las solapas
                await this.cargarDatosSolapas();

                // Recargar todo deja las solapas con los índices de hoy: si había una
                // versión retomada, ya no se está trabajando sobre ella.
                if (typeof RetomarVersion !== 'undefined') {
                    RetomarVersion.olvidar('verano');
                    RetomarVersion.olvidar('invierno');
                }

                // Rotular de nuevo los encabezados: recién ahora se sabe qué columnas
                // históricas trajo el SP, que es lo que nombra las columnas "anterior".
                UIUtils.actualizarHeadersDinamicos(this.temporadaInfo);

                UIUtils.mostrarTabsContainer(true);
                
                this.estado.ultimaActualizacion = new Date();
                UIUtils.mostrarAlerta('Datos cargados correctamente', 'success');
                
                // Guardar timestamp de última carga
                StorageUtils.guardar('ultima_carga', Date.now(), 24 * 60 * 60 * 1000);
                
                // Si la solapa activa es distribución, inicializar filtros de fechas
                const activeTabButton = document.querySelector('#presupuestoTabs button.active');
                if (activeTabButton && activeTabButton.id === 'distribucion-tab' && typeof DistribucionManager !== 'undefined') {
                    setTimeout(() => {
                        DistribucionManager.inicializarFiltrosFechas();
                    }, 100);
                }
            } else {
                throw new Error(datosBase.message);
            }
            
        } catch (error) {
            console.error('Error cargando datos:', error);
            this.manejarErrorCarga(error);
        } finally {
            this.estado.cargando = false;
            UIUtils.mostrarLoading(false);
        }
    }

    /**
     * NUEVO: Resetear estado de todas las tablas antes de cargar nuevos datos
     */
    resetearEstadoTablas() {
        ['verano', 'invierno', 'stock'].forEach(solapa => {
            TablaRenderer.resetearEstadoTabla(solapa);
            this.estado.tablasLimpias[solapa] = false;
        });
    }

    /**
     * Cargar datos de todas las solapas (MEJORADO - Con manejo de errores individual)
     */
    async cargarDatosSolapas() {
        try {
            // Cargar secuencialmente para mejor control de errores
            console.log('Cargando datos de verano...');
            try {
                const verano = await APIClient.obtenerCompraVerano();
                if (verano.success) {
                    this.prepararOriginales(verano.data || []);
                    this.datos.verano = verano.data;
                    // Las etiquetas históricas y el orden de las columnas los define el
                    // servidor; el front no vuelve a deducirlos del nombre de la columna.
                    TemporadaServidor.registrarEtiquetasHistoricas(verano.etiquetas_historicas);
                    this.columnasHistoricas = verano.columnas_venta || [];
                    this.renderizarSolapaSegura('verano', verano.data, verano.etiquetas);
                    UIUtils.actualizarContador('count-verano', verano.data.length);
                    
                    // AGREGAR ESTA LÍNEA - Actualizar totales de compra proyectada
                    TotalesCompra.actualizarDatos('verano', verano.data);
                    
                    console.log('✓ Datos de verano cargados');
                } else {
                    throw new Error(verano.message);
                }
            } catch (error) {
                console.error('Error cargando verano:', error);
                throw new Error('Error en compra verano: ' + error.message);
            }

            console.log('Cargando datos de invierno...');
            try {
                const invierno = await APIClient.obtenerCompraInvierno();
                if (invierno.success) {
                    this.prepararOriginales(invierno.data || []);
                    this.datos.invierno = invierno.data;
                    TemporadaServidor.registrarEtiquetasHistoricas(invierno.etiquetas_historicas);
                    this.columnasHistoricas = invierno.columnas_venta || this.columnasHistoricas;
                    this.renderizarSolapaSegura('invierno', invierno.data, invierno.etiquetas);
                    UIUtils.actualizarContador('count-invierno', invierno.data.length);
                    
                    // AGREGAR ESTA LÍNEA - Actualizar totales de compra proyectada
                    TotalesCompra.actualizarDatos('invierno', invierno.data);
                    
                    console.log('✓ Datos de invierno cargados');
                } else {
                    throw new Error(invierno.message);
                }
            } catch (error) {
                console.error('Error cargando invierno:', error);
                throw new Error('Error en compra invierno: ' + error.message);
            }

            console.log('Cargando datos de stock...');
        try {
            const stock = await APIClient.obtenerStockProyectado();
            if (stock.success) {
                this.datos.stock = stock.data;
                this.renderizarSolapaSegura('stock', stock.data);
                UIUtils.actualizarContador('count-stock', stock.data.length);
                
                // AGREGAR ESTA LÍNEA - Actualizar totales de stock
                if (typeof TotalesStock !== 'undefined') {
                    TotalesStock.actualizarDatos(stock.data);
                }
                
                console.log('✓ Datos de stock cargados');
            } else {
                throw new Error(stock.message);
            }
        } catch (error) {
            console.error('Error cargando stock:', error);
            throw new Error('Error en stock proyectado: ' + error.message);
        }

        // Opciones de Rubro/Categoría con las tres solapas juntas, y se vuelve a
        // filtrar: lo que el usuario había destildado sigue destildado sobre los
        // datos nuevos, y lo nuevo entra tildado.
        if (typeof FiltrosManager !== 'undefined') {
            FiltrosManager.cargarOpciones([this.datos.verano, this.datos.invierno, this.datos.stock]);
            FiltrosManager.propagarFiltros();
        }

        // Argentina: la solapa Stock muestra la cobertura de la solapa elegida (Verano
        // por defecto). En Uruguay solo esconde la columna y el selector.
        if (typeof StockSincronizado !== 'undefined') StockSincronizado.refrescar();

    } catch (error) {
        console.error('Error cargando solapas:', error);
        throw error;
    }
}

    /**
     * Deja en cada fila de una solapa de compra el índice ORIGINAL de cada temporada:
     * contra él se marca una fila como editada y a él vuelve "restaurar".
     *
     * El original es el del SP para el índice editable (el TE, o el LP si no hay TE),
     * y lo manda el servidor en INDICE_VAR_ORIGINAL. No se toma del valor editable
     * porque una versión retomada lo trae ya editado. Antes este bloque estaba copiado
     * cuatro veces en este archivo.
     */
    prepararOriginales(datos) {
        (datos || []).forEach(item => {
            const original = parseFloat(item.INDICE_VAR_ORIGINAL ?? item.INDICE_ORIGINAL ?? item.INDICE_VARIACION ?? 1.0);
            item.INDICE_VAR_ORIGINAL = original;
            item.INDICE_ORIGINAL = original;
            item._indiceOriginal = original;
            if (!item._indicesOriginales) {
                item._indicesOriginales = { verano: original, invierno: original };
            }

            // Argentina: el índice de cobertura "original" es el que corresponde a la fila
            // según Parámetros (no 1,50 fijo), y la cobertura la recalcula el servidor.
            if (item.CALCULO_AR) {
                item._factorCoberturaOriginal = parseFloat(item.INDICE_COBERTURA_DEFECTO ?? 0);
                return;
            }

            // Cobertura de stock y factor
            const stockCob = parseFloat(item.STOCK_COBERTURA || 0);
            if (item._stockCoberturaOriginal === undefined) {
                item._stockCoberturaOriginal = stockCob;
                item._baseCoberturaMensual = stockCob > 0 ? (stockCob / 1.5) : 0;
                const factorDefecto = stockCob > 0 ? 1.5 : 0.0;
                item._factorCoberturaOriginal = factorDefecto;
                item.INDICE_COBERTURA = item.INDICE_COBERTURA !== undefined ? parseFloat(item.INDICE_COBERTURA) : factorDefecto;
            }
        });
    }

    /**
     * NUEVO: Renderizar solapa de forma segura evitando duplicados
     */
    renderizarSolapaSegura(solapa, datos, etiquetas = null) {
        try {
            // Verificar si la tabla ya está limpia para esta carga
            if (!this.estado.tablasLimpias[solapa]) {
                TablaRenderer.resetearEstadoTabla(solapa);
                this.estado.tablasLimpias[solapa] = true;
            }

            switch (solapa) {
                case 'verano':
                    TablaRenderer.renderizarTablaVerano(datos, etiquetas);
                    break;
                case 'invierno':
                    TablaRenderer.renderizarTablaInvierno(datos, etiquetas);
                    break;
                case 'stock':
                    TablaRenderer.renderizarTablaStock(datos);
                    break;
            }

        } catch (error) {
            console.error(`Error renderizando solapa ${solapa}:`, error);
            UIUtils.mostrarAlerta(`Error renderizando ${solapa}: ${error.message}`, 'warning');
        }
    }

    /**
     * Cargar datos de solapa específica (lazy loading) - MEJORADO
     */
    async cargarDatosSolapa(solapa) {
        // Solapas con gestores propios se cargan bajo demanda, no aquí.
        if (solapa === 'historial' || solapa === 'compras-detalle' || solapa === 'ventas-6-meses' || solapa === 'distribucion') {
            return;
        }

        // Si ya están cargados, no recargar
        if (this.datos[solapa] && this.datos[solapa].length > 0) {
            return;
        }

        try {
            let response;
            
            switch (solapa) {
                case 'verano':
                    response = await APIClient.obtenerCompraVerano();
                    if (response.success) {
                        this.prepararOriginales(response.data || []);
                        this.datos.verano = response.data;
                        this.renderizarSolapaSegura('verano', response.data, response.etiquetas);
                        UIUtils.actualizarContador('count-verano', response.data.length);
                    }
                    break;
                    
                case 'invierno':
                    response = await APIClient.obtenerCompraInvierno();
                    if (response.success) {
                        this.prepararOriginales(response.data || []);
                        this.datos.invierno = response.data;
                        this.renderizarSolapaSegura('invierno', response.data, response.etiquetas);
                        UIUtils.actualizarContador('count-invierno', response.data.length);
                    }
                    break;
                    
                case 'stock':
                response = await APIClient.obtenerStockProyectado();
                if (response.success) {
                    this.datos.stock = response.data;
                    this.renderizarSolapaSegura('stock', response.data);
                    UIUtils.actualizarContador('count-stock', response.data.length);
                    
                    // AGREGAR - Actualizar totales de stock
                    if (typeof TotalesStock !== 'undefined') {
                        TotalesStock.actualizarDatos(response.data);
                    }
                }
                break;
        }
        
    } catch (error) {
        console.error(`Error en lazy loading de ${solapa}:`, error);
    }
}

    /**
     * Manejar cambio de tab
     */
    onTabChange(solapa) {
        // Cargar datos si no están cargados (lazy loading)
        this.cargarDatosSolapa(solapa);
        
        // Inicializar filtros de fechas y versiones en distribución si se selecciona
        if (solapa === 'distribucion' && typeof DistribucionManager !== 'undefined') {
            DistribucionManager.inicializarFiltrosFechas();
            DistribucionManager.inicializarVersionesOficiales();
            DistribucionManager.inicializarVersiones();
        }

        // Actualizar preferencias
        this.guardarPreferencia('ultima_solapa', solapa);
        
        // Si es una solapa de presupuesto y hay filtros persistentes, aplicarlos
        if (['verano', 'invierno', 'stock'].includes(solapa) && typeof FiltrosManager !== 'undefined') {
            setTimeout(() => {
                FiltrosManager.aplicarFiltrosASolapa(solapa);
            }, 200);
        } else {
            // Limpiar búsqueda anterior si es necesaria (sistema anterior)
            this.limpiarBusquedaSiEsNecesario(solapa);
        }
    }
    
    /**
     * MODIFICAR: notificarCambioFiltros para incluir stock
     */
    notificarCambioFiltros(solapa, datosFiltrados) {
        if (['verano', 'invierno'].includes(solapa) && typeof TotalesCompra !== 'undefined') {
            console.log(`🔄 Notificando cambio de filtros en ${solapa}:`, datosFiltrados.length, 'registros');
            TotalesCompra.aplicarFiltros(solapa, datosFiltrados);
        }
        
        // AGREGAR ESTA SECCIÓN - Para stock proyectado
        if (solapa === 'stock' && typeof TotalesStock !== 'undefined') {
            console.log(`🔄 Notificando cambio de filtros en stock:`, datosFiltrados.length, 'registros');
            TotalesStock.aplicarFiltros(datosFiltrados);
        }
    }

    /**
     * MODIFICAR: Función de búsqueda para notificar filtros
     */
    async buscarDatos(solapa) {
        const termino = document.getElementById(`search-${solapa}`).value;
        
        // Si tenemos FiltrosManager, usarlo para solapas de presupuesto
        if (typeof FiltrosManager !== 'undefined' && ['verano', 'invierno', 'stock'].includes(solapa)) {
            FiltrosManager.actualizarFiltro('termino', termino);
            return;
        }
        
        // Fallback al sistema anterior para otras solapas
        if (BusquedaManager.timeoutBusqueda) {
            clearTimeout(BusquedaManager.timeoutBusqueda);
        }

        BusquedaManager.timeoutBusqueda = setTimeout(async () => {
            if (termino.length >= 2) {
                try {
                    const response = await APIClient.buscarDatos(termino, solapa);
                    
                    if (response.success) {
                        TablaRenderer.resetearEstadoTabla(solapa);
                        this.renderizarSolapaSegura(solapa, response.data);
                        UIUtils.actualizarContador(`count-${solapa}`, response.data.length);
                        this.notificarCambioFiltros(solapa, response.data);
                        this.guardarPreferencia(`busqueda_${solapa}`, termino);
                    }
                } catch (error) {
                    console.error('Error en búsqueda:', error);
                    UIUtils.mostrarAlerta('Error en búsqueda: ' + error.message, 'error');
                }
            } else if (termino.length === 0) {
                this.resetearBusqueda(solapa);
            }
        }, 500);
    }

    /**
     * MODIFICAR: Resetear búsqueda para notificar filtros
     */
    resetearBusqueda(solapa) {
        // Resetear estado de tabla antes de mostrar datos originales
        TablaRenderer.resetearEstadoTabla(solapa);
        
        this.renderizarSolapaSegura(solapa, this.datos[solapa]);
        UIUtils.actualizarContador(`count-${solapa}`, this.datos[solapa].length);
        
        // AGREGAR: Notificar reseteo de filtros (todos los datos)
        this.notificarCambioFiltros(solapa, this.datos[solapa]);
        
        // Limpiar preferencia de búsqueda
        this.guardarPreferencia(`busqueda_${solapa}`, '');
    }

    /**
     * Exportar a Excel
     */
    async exportarExcel(solapa) {
        try {
            UIUtils.mostrarAlerta(`Generando Excel de ${solapa}...`, 'info');
            
            // Usamos el exportador que soporta filtros
            if (typeof ExcelExporter !== 'undefined') {
                // Llamamos al método específico para solapas
                await ExcelExporter.exportarSolapa(solapa);
                this.registrarEstadistica('exportacion', { solapa, timestamp: Date.now() });
            } else {
                throw new Error('El módulo ExcelExporter no está cargado');
            }
            
        } catch (error) {
            console.error('Error exportando:', error);
            UIUtils.mostrarAlerta('Error al exportar: ' + error.message, 'error');
        }
    }

    /**
     * Actualizar última actualización - valor real viene de la BD vía mostrarInfoTemporada()
     */
    actualizarUltimaActualizacion() {
        // El valor se muestra desde RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS.ULT_ACTUALIZACION
        // a través de UIUtils.mostrarInfoTemporada(). No se sobreescribe con la hora local.
    }

    /**
     * Obtener datos actuales
     */
    getDatos(solapa = null) {
        if (solapa) {
            return this.datos[solapa] || [];
        }
        return this.datos;
    }

    /**
     * Actualizar datos en memoria
     */
    actualizarDatos(solapa, datos) {
        if (this.datos.hasOwnProperty(solapa)) {
            this.datos[solapa] = datos;
            this.guardarEnCache();
        }
    }

    /**
     * Obtener información de temporada
     */
    getTemporadaInfo() {
        return this.temporadaInfo;
    }

    /**
     * Obtener estado de la aplicación
     */
    getEstado() {
        return this.estado;
    }

    /**
     * Manejar error de carga
     */
    manejarErrorCarga(error) {
        const datosCache = StorageUtils.obtener('datos_cache');
        if (datosCache) {
            UIUtils.mostrarAlerta('Cargando datos desde cache local...', 'warning');
            this.cargarDatosDesdeCache(datosCache);
        } else {
            this.mostrarOpcionesRecuperacion();
        }
    }

    /**
     * Cargar datos desde cache
     */
    cargarDatosDesdeCache(datosCache) {
        try {
            this.datos = datosCache.datos || { verano: [], invierno: [], stock: [] };
            this.temporadaInfo = datosCache.temporadaInfo;
            
            // Resetear estado antes de cargar desde cache
            this.resetearEstadoTablas();
            
            this.renderizarSolapaSegura('verano', this.datos.verano);
            this.renderizarSolapaSegura('invierno', this.datos.invierno);
            this.renderizarSolapaSegura('stock', this.datos.stock);
            
            UIUtils.mostrarTabsContainer(true);
            UIUtils.mostrarInfoTemporada(this.temporadaInfo);
            
        } catch (error) {
            console.error('Error cargando desde cache:', error);
        }
    }

    /**
     * Mostrar opciones de recuperación
     */
    mostrarOpcionesRecuperacion() {
        // UIUtils.mostrarAlerta('No se pudieron cargar los datos. Verifique su conexión.', 'error');
        
        setTimeout(() => {
            const container = document.querySelector('.alert-container .alert:last-child');
            if (container) {
                const btnReintento = document.createElement('button');
                btnReintento.className = 'btn btn-sm btn-outline-primary ms-2';
                btnReintento.innerHTML = '<i class="fas fa-redo"></i> Reintentar';
                btnReintento.onclick = () => this.cargarDatos();
                container.appendChild(btnReintento);
            }
        }, 100);
    }

    /**
     * ELIMINADO: pausarActualizaciones() y reanudarActualizaciones() - Sin auto-updates
     */

    /**
     * Limpiar cache expirado (SOLO MANUAL)
     */
    limpiarCacheExpirado() {
        APIClient.limpiarCache();
        StorageUtils.limpiarExpirados();
    }

    /**
     * Limpiar búsqueda si es necesario
     */
    limpiarBusquedaSiEsNecesario(solapa) {
        const input = document.getElementById(`search-${solapa}`);
        if (input && input.value.length > 0) {
            setTimeout(() => this.buscarDatos(solapa), 100);
        }
    }

    /**
     * Guardar en cache
     */
    guardarEnCache() {
        const datosCache = {
            datos: this.datos,
            temporadaInfo: this.temporadaInfo,
            timestamp: Date.now()
        };
        StorageUtils.guardar('datos_cache', datosCache, 60 * 60 * 1000);
    }

    /**
     * Cargar preferencias de usuario
     */
    cargarPreferenciasUsuario() {
        const preferencias = StorageUtils.obtener('preferencias_usuario');
        if (preferencias) {
            this.aplicarPreferencias(preferencias);
        }
    }

    /**
     * Guardar preferencias de usuario
     */
    guardarPreferenciasUsuario() {
        const preferencias = {
            ultima_solapa: document.querySelector('.nav-link.active')?.getAttribute('data-bs-target')?.replace('#', ''),
            busquedas: {
                verano: document.getElementById('search-verano')?.value || '',
                invierno: document.getElementById('search-invierno')?.value || '',
                stock: document.getElementById('search-stock')?.value || ''
            },
            timestamp: Date.now()
        };
        
        StorageUtils.guardar('preferencias_usuario', preferencias, 24 * 60 * 60 * 1000);
    }

    /**
     * Guardar preferencia específica
     */
    guardarPreferencia(clave, valor) {
        const preferencias = StorageUtils.obtener('preferencias_usuario') || {};
        preferencias[clave] = valor;
        preferencias.timestamp = Date.now();
        StorageUtils.guardar('preferencias_usuario', preferencias, 24 * 60 * 60 * 1000);
    }

    /**
     * Aplicar preferencias
     */
    aplicarPreferencias(preferencias) {
        // ELIMINADO: No restaurar la última solapa al recargar para evitar lentitud y concurrencia.
        // Siempre inicia en la primera solapa por defecto (Compra Proyectada Verano).

        // Tampoco se restaura el texto de los buscadores: cada carga de página arranca
        // sin filtros (ver filtros-manager.js), y un buscador relleno a escondidas
        // recortaba la tabla igual que el filtro persistente que se sacó.
    }

    /**
     * Registrar estadística de uso
     */
    registrarEstadistica(tipo, datos) {
        const estadisticas = StorageUtils.obtener('estadisticas_uso') || [];
        estadisticas.push({
            tipo: tipo,
            datos: datos,
            timestamp: Date.now()
        });
        
        if (estadisticas.length > 100) {
            estadisticas.splice(0, estadisticas.length - 100);
        }
        
        StorageUtils.guardar('estadisticas_uso', estadisticas, 7 * 24 * 60 * 60 * 1000);
    }

    /**
     * Obtener estadísticas de uso
     */
    obtenerEstadisticasUso() {
        return StorageUtils.obtener('estadisticas_uso') || [];
    }

    /**
     * Diagnóstico del sistema (MEJORADO)
     */
    diagnostico() {
        const info = {
            estado: this.estado,
            datos_cargados: Object.keys(this.datos).map(k => ({ 
                solapa: k, 
                registros: this.datos[k].length,
                estado_tabla: TablaRenderer.obtenerEstadoTabla ? TablaRenderer.obtenerEstadoTabla(k) : 'N/A'
            })),
            temporada: this.temporadaInfo,
            cache: APIClient.obtenerEstadisticasCache(),
            storage: {
                usado: StorageUtils.obtenerTamaño(),
                elementos_expirados: StorageUtils.limpiarExpirados()
            },
            navegador: {
                userAgent: navigator.userAgent,
                idioma: navigator.language,
                plataforma: navigator.platform
            },
            auto_updates: 'DISABLED' // Confirmación de que están deshabilitadas
        };
        
        console.table(info);
        return info;
    }

}

// Crear instancia global
window.presupuestoApp = new PresupuestoApp();

// Funciones globales para compatibilidad con HTML
function cargarDatos() {
    window.presupuestoApp.cargarDatos();
}

function buscarDatos(solapa) {
    // Usar FiltrosManager para solapas de presupuesto si está disponible
    if (typeof FiltrosManager !== 'undefined' && ['verano', 'invierno', 'stock'].includes(solapa)) {
        const input = document.getElementById(`search-${solapa}`);
        if (input) {
            FiltrosManager.actualizarFiltro('termino', input.value);
        }
    } else {
        // Fallback para otras solapas
        if (BusquedaManager && BusquedaManager.busquedaInstantanea) {
            const input = document.getElementById(`search-${solapa}`);
            if (input) {
                BusquedaManager.busquedaInstantanea(solapa, input.value);
            }
        } else {
            window.presupuestoApp.buscarDatos(solapa);
        }
    }
}

function exportarExcel(solapa) {
    window.presupuestoApp.exportarExcel(solapa);
}

function editarIndice(rubro, categoria, indiceActual, solapa, index, temporada = 'verano') {
    console.log('🚀 Función global editarIndice llamada con temporada:', temporada);
    IndiceEditor.editarIndice(rubro, categoria, indiceActual, solapa, index, temporada);
}

function guardarNuevoIndice() {
    IndiceEditor.guardarNuevoIndice();
}

// Funciones de diagnóstico globales
function diagnosticoSistema() {
    return window.presupuestoApp.diagnostico();
}

function limpiarCache() {
    APIClient.limpiarCache();
    StorageUtils.limpiarExpirados();
    
    // NUEVO: También resetear estado de tablas
    ['verano', 'invierno', 'stock'].forEach(solapa => {
        TablaRenderer.resetearEstadoTabla(solapa);
    });
    
    UIUtils.mostrarAlerta('Cache y estado de tablas limpiado', 'info');
}

function reiniciarAplicacion() {
    if (confirm('¿Está seguro que desea reiniciar la aplicación? Se perderán los datos no guardados.')) {
        location.reload();
    }
}

// NUEVA: Función para forzar limpieza de tablas
function limpiarTablas() {
    ['verano', 'invierno', 'stock'].forEach(solapa => {
        TablaRenderer.resetearEstadoTabla(solapa);
        window.presupuestoApp.estado.tablasLimpias[solapa] = false;
    });
    UIUtils.mostrarAlerta('Estado de tablas reseteado', 'info');
}

// NUEVO: Función para actualizar manualmente la hora
function actualizarHora() {
    window.presupuestoApp.actualizarUltimaActualizacion();
    UIUtils.mostrarAlerta('Hora actualizada', 'info', 1000);
}

/**
 * Limpiar filtros: todo tildado y buscador vacío en Verano, Invierno y Stock
 * (los filtros son compartidos entre las tres).
 */
function limpiarFiltrosPresupuesto(solapa) {
    FiltrosManager.limpiarFiltros();
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('Presupuesto App iniciado correctamente');
    
    // Mostrar información de versión en consola
    console.log('%c Sistema de Presupuesto de Compras v2.1 - SIN AUTO-UPDATES ', 'background: #0d6efd; color: white; font-size: 14px; padding: 5px 10px; border-radius: 3px;');
    console.log('Tipo "diagnosticoSistema()" para ver información del sistema');
    console.log('Funciones disponibles: limpiarCache(), limpiarTablas(), reiniciarAplicacion(), actualizarHora()');
    console.log('✓ Actualizaciones automáticas DESHABILITADAS');
});


// Fix definitivo para forzar altura máxima en solapa ventas 6 meses

function aplicarFixAlturaDefinitivo() {
    const tabla = document.querySelector('#ventas-6-meses .table-responsive');
    if (!tabla) {
        console.log('❌ Tabla no encontrada');
        return;
    }
    
    console.log('🔧 Aplicando fix de altura definitivo...');
    
    // Remover cualquier estilo previo que pueda interferir
    tabla.removeAttribute('style');
    
    // Aplicar estilos directamente con !important simulado
    const estilos = {
        maxHeight: '400px',
        height: 'auto',
        overflowY: 'auto',
        overflowX: 'auto',
        display: 'block',
        width: '100%'
    };
    
    Object.entries(estilos).forEach(([prop, value]) => {
        tabla.style.setProperty(prop, value, 'important');
    });
    
    // Verificar inmediatamente
    setTimeout(() => {
        console.log('📊 Verificación inmediata:');
        console.log('  Max Height aplicado:', tabla.style.maxHeight);
        console.log('  Altura visible:', tabla.clientHeight);
        console.log('  Altura total:', tabla.scrollHeight);
        console.log('  ¿Debería tener scroll ahora?:', tabla.scrollHeight > tabla.clientHeight);
        
        if (tabla.scrollHeight > tabla.clientHeight) {
            console.log('✅ ¡SCROLL FUNCIONANDO!');
        } else {
            console.log('❌ Aún no funciona, probando altura más pequeña...');
            tabla.style.setProperty('maxHeight', '300px', 'important');
            
            setTimeout(() => {
                console.log('📊 Con altura 300px:');
                console.log('  Altura visible:', tabla.clientHeight);
                console.log('  Altura total:', tabla.scrollHeight);
                console.log('  ¿Funciona ahora?:', tabla.scrollHeight > tabla.clientHeight);
            }, 50);
        }
    }, 50);
}

// Override más agresivo del renderizado
function configurarFixAgresivo() {
    if (typeof VentasManager !== 'undefined') {
        const originalRenderizar = VentasManager.renderizarTabla;
        
        VentasManager.renderizarTabla = function() {
            // Ejecutar el renderizado original
            originalRenderizar.call(this);
            
            // Aplicar fix inmediatamente después
            setTimeout(() => {
                aplicarFixAlturaDefinitivo();
            }, 10);
            
            // Y también después de un delay para estar seguros
            setTimeout(() => {
                aplicarFixAlturaDefinitivo();
            }, 100);
        };
        
        // También interceptar la función de cargar datos
        const originalCargar = VentasManager.cargarDatos;
        
        VentasManager.cargarDatos = async function() {
            const resultado = await originalCargar.call(this);
            
            // Aplicar fix después de cargar
            setTimeout(() => {
                aplicarFixAlturaDefinitivo();
            }, 200);
            
            return resultado;
        };
        
        console.log('🔧 Override agresivo configurado');
    }
}

// Aplicar via CSS también
function aplicarFixCSS() {
    // Crear estilo CSS dinámico
    const styleElement = document.createElement('style');
    styleElement.id = 'ventas-scroll-fix';
    styleElement.textContent = `
        #ventas-6-meses .table-responsive {
            max-height: 450px !important;
            overflow-y: auto !important;
            overflow-x: auto !important;
            display: block !important;
        }
    `;
    
    // Remover estilo previo si existe
    const existingStyle = document.getElementById('ventas-scroll-fix');
    if (existingStyle) {
        existingStyle.remove();
    }
    
    // Agregar al head
    document.head.appendChild(styleElement);
    console.log('🎨 CSS fix aplicado');
}

// Fix completo que combina todo
function fixCompletoScrollVentas() {
    console.log('🚀 Ejecutando fix completo...');
    
    // 1. Aplicar CSS
    aplicarFixCSS();
    
    // 2. Aplicar JavaScript
    setTimeout(() => {
        aplicarFixAlturaDefinitivo();
    }, 50);
    
    // 3. Verificar resultado
    setTimeout(() => {
        const tabla = document.querySelector('#ventas-6-meses .table-responsive');
        if (tabla) {
            console.log('📊 RESULTADO FINAL:');
            console.log('  Altura visible:', tabla.clientHeight);
            console.log('  Altura total:', tabla.scrollHeight);
            console.log('  Scroll funciona:', tabla.scrollHeight > tabla.clientHeight);
            console.log('  Max height aplicado:', getComputedStyle(tabla).maxHeight);
            
            if (tabla.scrollHeight > tabla.clientHeight) {
                console.log('🎉 ¡ÉXITO! El scroll debería funcionar ahora');
            } else {
                console.log('🔧 Intentando con altura aún más pequeña...');
                tabla.style.setProperty('maxHeight', '200px', 'important');
            }
        }
    }, 200);
}

function mostrarEstadoFiltros() {
    if (typeof FiltrosManager !== 'undefined') {
        const estado = FiltrosManager.obtenerEstadoFiltros();
        console.log('📊 Estado de filtros persistentes:');
        console.table(estado);
        
        if (estado.activos) {
            UIUtils.mostrarAlerta(
                `Filtros activos: ${estado.cantidad} filtro(s) aplicado(s)`,
                'info',
                3000
            );
        } else {
            UIUtils.mostrarAlerta('No hay filtros activos', 'info', 2000);
        }
        
        return estado;
    } else {
        console.warn('FiltrosManager no está disponible');
        return null;
    }
}

// Hacer funciones disponibles globalmente
window.mostrarEstadoFiltros = mostrarEstadoFiltros;

// Configurar cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    configurarFixAgresivo();
    // "Limpiar filtros" ya no se inyecta en el encabezado: está en el menú
    // "Más acciones" de cada solapa (components/barra-solapa.php).
        
    // Aplicar fix cuando se active la solapa
    const ventasTab = document.getElementById('ventas-6-meses-tab');
    if (ventasTab) {
        ventasTab.addEventListener('shown.bs.tab', function() {
            setTimeout(fixCompletoScrollVentas, 100);
        });
    }
});

// Hacer funciones disponibles globalmente
window.aplicarFixAlturaDefinitivo = aplicarFixAlturaDefinitivo;
window.fixCompletoScrollVentas = fixCompletoScrollVentas;

console.log('🔧 Fix de altura definitivo cargado');
console.log('📝 Ejecuta: fixCompletoScrollVentas()');

// Función global para el switch
async function cambiarPais() {
    try {
        const switchPais = document.getElementById('country-switch');
        const pais = switchPais.checked ? 'uruguay' : 'argentina';
        
        // Llamar a la API para cambiar el país
        const response = await APIClient.llamarAPI('cambiar_pais', { pais: pais });
        
        if (response.success) {
            // Aplicar cambios en el frontend
            window.presupuestoApp.aplicarConfiguracionPais(pais);
            
            // Mostrar alerta de cambio
            UIUtils.mostrarAlerta(
                `País cambiado a ${pais === 'uruguay' ? 'Uruguay' : 'Argentina'}. Los datos se cargarán desde la base correspondiente.`,
                'success',
                3000
            );
            
            // Limpiar cache para forzar recarga desde nueva base
            APIClient.limpiarCache();
        } else {
            throw new Error(response.message || 'Error cambiando país');
        }
        
    } catch (error) {
        console.error('Error cambiando país:', error);
        UIUtils.mostrarAlerta('Error al cambiar país: ' + error.message, 'error');
        
        // Revertir el switch si hay error
        const switchPais = document.getElementById('country-switch');
        if (switchPais) {
            switchPais.checked = !switchPais.checked;
        }
    }

    // Función global para mostrar historial de índices
    function mostrarHistorialIndices() {
        IndiceEditor.mostrarHistorialCambios();
    }

    window.mostrarHistorialIndices = mostrarHistorialIndices;

}

// Función temporal para inspeccionar onclick de celdas editables
function inspeccionarOnclickCeldas() {
    const celdasEditables = document.querySelectorAll('.editable-cell');
    
    console.group('🔍 INSPECCIÓN ONCLICK CELDAS EDITABLES');
    celdasEditables.forEach((celda, index) => {
        const fila = celda.closest('tr');
        const posicionEnFila = Array.from(fila.children).indexOf(celda);
        const onclick = celda.getAttribute('onclick');
        
        console.log(`Celda ${index}:`);
        console.log(`  Posición en fila: ${posicionEnFila}`);
        console.log(`  Onclick: ${onclick}`);
        
        // Extraer temporada del onclick
        const matchTemporada = onclick?.match(/'(verano|invierno)'\)$/);
        const temporada = matchTemporada ? matchTemporada[1] : 'NO ENCONTRADA';
        console.log(`  Temporada detectada: ${temporada}`);
        console.log('---');
    });
    console.groupEnd();
}

// Hacer disponible globalmente
window.inspeccionarOnclickCeldas = inspeccionarOnclickCeldas;