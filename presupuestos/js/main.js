
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
                input.addEventListener('input', () => {
                    if (input.value === '') {
                        this.resetearBusqueda(solapa);
                    }
                });

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
            if (titulo) titulo.textContent = 'Sistema de Presupuesto de Compras - Uruguay';
            if (label) label.innerHTML = '<i class="fas fa-flag me-1"></i>Uruguay';
            if (switchPais) switchPais.checked = true;
        } else {
            if (titulo) titulo.textContent = 'Sistema de Presupuesto de Compras - Argentina';
            if (label) label.innerHTML = '<i class="fas fa-flag me-1"></i>Argentina';
            if (switchPais) switchPais.checked = false;
        }
        
        // Guardar selección
        StorageUtils.guardar('pais_seleccionado', pais, 30 * 24 * 60 * 60 * 1000); // 30 días
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
                
                UIUtils.mostrarTabsContainer(true);
                
                // CORREGIDO: Verificar que el elemento existe antes de modificarlo
                const btnExport = document.getElementById('btn-export-completo-header');
                if (btnExport) {
                    btnExport.style.display = 'block';
                }
                
                this.estado.ultimaActualizacion = new Date();
                UIUtils.mostrarAlerta('Datos cargados correctamente', 'success');
                
                // Guardar timestamp de última carga
                StorageUtils.guardar('ultima_carga', Date.now(), 24 * 60 * 60 * 1000);
                
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
                    this.datos.verano = verano.data;
                    this.renderizarSolapaSegura('verano', verano.data, verano.etiquetas);
                    this.cargarFiltrosPresupuesto('verano', verano.data);
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
                    this.datos.invierno = invierno.data;
                    this.renderizarSolapaSegura('invierno', invierno.data, invierno.etiquetas);
                    this.cargarFiltrosPresupuesto('invierno', invierno.data);
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
                this.cargarFiltrosPresupuesto('stock', stock.data);
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

    } catch (error) {
        console.error('Error cargando solapas:', error);
        throw error;
    }
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
        // Si ya están cargados, no recargar
        if (this.datos[solapa].length > 0) {
            return;
        }

        try {
            let response;
            
            switch (solapa) {
                case 'verano':
                    response = await APIClient.obtenerCompraVerano();
                    if (response.success) {
                        this.datos.verano = response.data;
                        this.renderizarSolapaSegura('verano', response.data, response.etiquetas);
                        UIUtils.actualizarContador('count-verano', response.data.length);
                    }
                    break;
                    
                case 'invierno':
                    response = await APIClient.obtenerCompraInvierno();
                    if (response.success) {
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
            await APIClient.exportarExcel(solapa);
            UIUtils.mostrarAlerta(`Exportando ${solapa} a Excel...`, 'info');
            
            // Registrar estadística de exportación
            this.registrarEstadistica('exportacion', { solapa, timestamp: Date.now() });
            
        } catch (error) {
            console.error('Error exportando:', error);
            UIUtils.mostrarAlerta('Error al exportar: ' + error.message, 'error');
        }
    }

    /**
     * Actualizar última actualización (SOLO MANUAL)
     */
    actualizarUltimaActualizacion() {
        const ahora = new Date();
        const hora = ahora.toLocaleTimeString('es-AR', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        const elemento = document.getElementById('ultima-actualizacion');
        if (elemento) {
            elemento.textContent = hora;
        }
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
        if (preferencias.ultima_solapa) {
            setTimeout(() => {
                const tab = document.querySelector(`[data-bs-target="#${preferencias.ultima_solapa}"]`);
                if (tab) tab.click();
            }, 1000);
        }

        if (Date.now() - preferencias.timestamp < 60 * 60 * 1000) {
            Object.keys(preferencias.busquedas || {}).forEach(solapa => {
                const input = document.getElementById(`search-${solapa}`);
                if (input && preferencias.busquedas[solapa]) {
                    input.value = preferencias.busquedas[solapa];
                }
            });
        }
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

    /**
     * Cargar filtros para solapas de presupuesto - CORREGIDO CON REINTENTOS
     */
    cargarFiltrosPresupuesto(solapa, datos) {
        // Función interna para intentar cargar
        const intentarCargar = (intento = 1) => {
            try {
                if (!datos || datos.length === 0) {
                    console.warn(`No hay datos para filtros en ${solapa}`);
                    return;
                }
                
                console.log(`🔄 Intento ${intento}: Cargando filtros para ${solapa} con ${datos.length} registros`);
                
                // Verificar que el tab pane existe
                const tabPane = document.getElementById(solapa);
                if (!tabPane) {
                    console.error(`❌ Tab pane ${solapa} no encontrado`);
                    if (intento < 3) {
                        setTimeout(() => intentarCargar(intento + 1), 1000);
                    }
                    return;
                }
                
                // Cargar rubros únicos
                const rubros = [...new Set(
                    datos.map(item => item.RUBRO).filter(r => r && r.trim() !== '')
                )].sort();
                
                const selectRubro = document.getElementById(`filtro-rubro-${solapa}`);
                if (selectRubro) {
                    selectRubro.innerHTML = '<option value="">Todos los rubros</option>';
                    rubros.forEach(rubro => {
                        const option = document.createElement('option');
                        option.value = rubro;
                        option.textContent = rubro;
                        selectRubro.appendChild(option);
                    });
                    console.log(`✅ Cargados ${rubros.length} rubros para ${solapa}`);
                } else {
                    console.error(`❌ Select de rubros no encontrado: filtro-rubro-${solapa}`);
                    if (intento < 3) {
                        setTimeout(() => intentarCargar(intento + 1), 1000);
                        return;
                    }
                }
                
                // Cargar categorías únicas
                const categorias = [...new Set(
                    datos.map(item => item.CATEGORIA_PADRE || item.CATEGORIA).filter(c => c && c.trim() !== '')
                )].sort();
                
                const selectCategoria = document.getElementById(`filtro-categoria-${solapa}`);
                if (selectCategoria) {
                    selectCategoria.innerHTML = '<option value="">Todas las categorías</option>';
                    categorias.forEach(categoria => {
                        const option = document.createElement('option');
                        option.value = categoria;
                        option.textContent = categoria;
                        selectCategoria.appendChild(option);
                    });
                    console.log(`✅ Cargadas ${categorias.length} categorías para ${solapa}`);
                } else {
                    console.error(`❌ Select de categorías no encontrado: filtro-categoria-${solapa}`);
                    if (intento < 3) {
                        setTimeout(() => intentarCargar(intento + 1), 1000);
                    }
                }
                
            } catch (error) {
                console.error(`Error cargando filtros para ${solapa} (intento ${intento}):`, error);
                if (intento < 3) {
                    setTimeout(() => intentarCargar(intento + 1), 1000);
                }
            }
        };
        
        // Iniciar con delay inicial
        setTimeout(() => intentarCargar(1), 500);
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
 * Filtrar por rubro usando el nuevo sistema persistente
 */
async function filtrarPorRubroPresupuesto(solapa) {
    const select = document.getElementById(`filtro-rubro-${solapa}`);
    if (!select) return;
    
    const rubro = select.value;
    
    // Usar el nuevo sistema de filtros persistentes
    if (typeof FiltrosManager !== 'undefined') {
        await FiltrosManager.aplicarFiltroRubro(rubro, solapa);
    } else {
        // Fallback al sistema anterior
        if (!rubro) {
            window.presupuestoApp.resetearBusqueda(solapa);
            return;
        }
        
        try {
            const response = await APIClient.filtrarPorRubro(rubro, solapa);
            if (response.success) {
                window.presupuestoApp.renderizarSolapaSegura(solapa, response.data);
                UIUtils.actualizarContador(`count-${solapa}`, response.data.length);
                window.presupuestoApp.notificarCambioFiltros(solapa, response.data);
                UIUtils.mostrarAlerta(`Filtrado por rubro: ${rubro}`, 'info', 2000);
            }
        } catch (error) {
            console.error('Error filtrando por rubro:', error);
            UIUtils.mostrarAlerta('Error al filtrar por rubro', 'error');
        }
    }
}

/**
 * Filtrar por categoría usando el nuevo sistema persistente
 */
async function filtrarPorCategoriaPresupuesto(solapa) {
    const select = document.getElementById(`filtro-categoria-${solapa}`);
    if (!select) return;
    
    const categoria = select.value;
    
    // Usar el nuevo sistema de filtros persistentes
    if (typeof FiltrosManager !== 'undefined') {
        await FiltrosManager.aplicarFiltroCategoria(categoria, solapa);
    } else {
        // Fallback al sistema anterior
        if (!categoria) {
            window.presupuestoApp.resetearBusqueda(solapa);
            return;
        }
        
        try {
            const datos = window.presupuestoApp.getDatos(solapa);
            const datosFiltrados = datos.filter(item => 
                item.CATEGORIA_PADRE === categoria || item.CATEGORIA === categoria
            );
            
            window.presupuestoApp.renderizarSolapaSegura(solapa, datosFiltrados);
            UIUtils.actualizarContador(`count-${solapa}`, datosFiltrados.length);
            window.presupuestoApp.notificarCambioFiltros(solapa, datosFiltrados);
            UIUtils.mostrarAlerta(`Filtrado por categoría: ${categoria}`, 'info', 2000);
        } catch (error) {
            console.error('Error filtrando por categoría:', error);
            UIUtils.mostrarAlerta('Error al filtrar por categoría', 'error');
        }
    }
}

/**
 * Limpiar filtros usando el nuevo sistema persistente
 */
function limpiarFiltrosPresupuesto(solapa) {
    // Usar el nuevo sistema de filtros persistentes
    if (typeof FiltrosManager !== 'undefined') {
        FiltrosManager.limpiarFiltros();
    } else {
        // Fallback al sistema anterior
        document.getElementById(`search-${solapa}`).value = '';
        document.getElementById(`filtro-rubro-${solapa}`).value = '';
        document.getElementById(`filtro-categoria-${solapa}`).value = '';
        
        window.presupuestoApp.resetearBusqueda(solapa);
        UIUtils.mostrarAlerta('Filtros limpiados', 'info', 2000);
    }
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

function agregarBotonLimpiarFiltrosPersistentes() {
    const exportButtons = document.querySelector('.export-buttons');
    if (exportButtons && !document.getElementById('btn-limpiar-filtros-persistentes')) {
        const btnLimpiar = document.createElement('button');
        btnLimpiar.id = 'btn-limpiar-filtros-persistentes';
        btnLimpiar.className = 'btn btn-outline-warning btn-sm';
        btnLimpiar.innerHTML = '<i class="fas fa-filter me-1"></i> Limpiar Filtros';
        btnLimpiar.onclick = () => {
            if (typeof FiltrosManager !== 'undefined') {
                FiltrosManager.limpiarFiltros();
            }
        };
        btnLimpiar.title = 'Limpiar todos los filtros persistentes';
        
        exportButtons.insertBefore(btnLimpiar, exportButtons.lastElementChild);
        console.log('✅ Botón de limpiar filtros persistentes agregado');
    }
}

// Hacer funciones disponibles globalmente
window.mostrarEstadoFiltros = mostrarEstadoFiltros;
window.agregarBotonLimpiarFiltrosPersistentes = agregarBotonLimpiarFiltrosPersistentes;

// Configurar cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    configurarFixAgresivo();
    setTimeout(() => {
    agregarBotonLimpiarFiltrosPersistentes();
    }, 2000);
        
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