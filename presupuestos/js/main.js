
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
            ultimaActualizacion: null
        };
        this.init();
    }

    /**
     * Inicializar la aplicación
     */
    init() {
        this.actualizarUltimaActualizacion();
        this.configurarEventListeners();
        this.configurarIntervalos();
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

        // Event listener para cambios de visibilidad (optimización)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pausarActualizaciones();
            } else {
                this.reanudarActualizaciones();
            }
        });
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
     * Configurar intervalos de actualización
     */
    configurarIntervalos() {
        // Actualizar hora cada minuto
        setInterval(() => this.actualizarUltimaActualizacion(), 60000);
        
        // Limpiar cache cada 10 minutos
        setInterval(() => this.limpiarCacheExpirado(), 10 * 60 * 1000);
    }

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
            
            // Cargar datos base primero
            const datosBase = await APIClient.obtenerDatosBase();
            
            if (datosBase.success) {
                this.temporadaInfo = datosBase.info_temporada;
                UIUtils.mostrarInfoTemporada(datosBase.info_temporada);
                
                // Cargar datos de todas las solapas
                await this.cargarDatosSolapas();
                
                UIUtils.mostrarTabsContainer(true);
                document.getElementById('btn-export-completo').style.display = 'block';
                
                this.estado.ultimaActualizacion = new Date();
                UIUtils.mostrarAlerta('Datos cargados correctamente', 'success');
                
                // Guardar timestamp de última carga
                StorageUtils.guardar('ultima_carga', Date.now(), 24 * 60 * 60 * 1000);
                
            } else {
                throw new Error(datosBase.message);
            }
            
        } catch (error) {
            console.error('Error cargando datos:', error);
            UIUtils.mostrarAlerta('Error al cargar datos: ' + error.message, 'error');
            this.manejarErrorCarga(error);
        } finally {
            this.estado.cargando = false;
            UIUtils.mostrarLoading(false);
        }
    }

    /**
     * Cargar datos de todas las solapas
     */
    async cargarDatosSolapas() {
        try {
            // Cargar en paralelo
            const [verano, invierno, stock] = await Promise.all([
                APIClient.obtenerCompraVerano(),
                APIClient.obtenerCompraInvierno(),
                APIClient.obtenerStockProyectado()
            ]);

            if (verano.success) {
                this.datos.verano = verano.data;
                TablaRenderer.renderizarTablaVerano(verano.data, verano.etiquetas);
                UIUtils.actualizarContador('count-verano', verano.data.length);
            }

            if (invierno.success) {
                this.datos.invierno = invierno.data;
                TablaRenderer.renderizarTablaInvierno(invierno.data, invierno.etiquetas);
                UIUtils.actualizarContador('count-invierno', invierno.data.length);
            }

            if (stock.success) {
                this.datos.stock = stock.data;
                TablaRenderer.renderizarTablaStock(stock.data);
                UIUtils.actualizarContador('count-stock', stock.data.length);
            }

        } catch (error) {
            console.error('Error cargando solapas:', error);
            throw error;
        }
    }

    /**
     * Cargar datos de solapa específica (lazy loading)
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
                        TablaRenderer.renderizarTablaVerano(response.data, response.etiquetas);
                        UIUtils.actualizarContador('count-verano', response.data.length);
                    }
                    break;
                    
                case 'invierno':
                    response = await APIClient.obtenerCompraInvierno();
                    if (response.success) {
                        this.datos.invierno = response.data;
                        TablaRenderer.renderizarTablaInvierno(response.data, response.etiquetas);
                        UIUtils.actualizarContador('count-invierno', response.data.length);
                    }
                    break;
                    
                case 'stock':
                    response = await APIClient.obtenerStockProyectado();
                    if (response.success) {
                        this.datos.stock = response.data;
                        TablaRenderer.renderizarTablaStock(response.data);
                        UIUtils.actualizarContador('count-stock', response.data.length);
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
        
        // Limpiar búsqueda anterior si es necesaria
        this.limpiarBusquedaSiEsNecesario(solapa);
    }

    /**
     * Buscar datos
     */
    async buscarDatos(solapa) {
        const termino = document.getElementById(`search-${solapa}`).value;
        
        if (BusquedaManager.timeoutBusqueda) {
            clearTimeout(BusquedaManager.timeoutBusqueda);
        }

        BusquedaManager.timeoutBusqueda = setTimeout(async () => {
            if (termino.length >= 2) {
                try {
                    const response = await APIClient.buscarDatos(termino, solapa);
                    
                    if (response.success) {
                        switch (solapa) {
                            case 'verano':
                                TablaRenderer.renderizarTablaVerano(response.data);
                                break;
                            case 'invierno':
                                TablaRenderer.renderizarTablaInvierno(response.data);
                                break;
                            case 'stock':
                                TablaRenderer.renderizarTablaStock(response.data);
                                break;
                        }
                        UIUtils.actualizarContador(`count-${solapa}`, response.data.length);
                        
                        // Guardar término de búsqueda
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
     * Resetear búsqueda
     */
    resetearBusqueda(solapa) {
        switch (solapa) {
            case 'verano':
                TablaRenderer.renderizarTablaVerano(this.datos.verano);
                UIUtils.actualizarContador('count-verano', this.datos.verano.length);
                break;
            case 'invierno':
                TablaRenderer.renderizarTablaInvierno(this.datos.invierno);
                UIUtils.actualizarContador('count-invierno', this.datos.invierno.length);
                break;
            case 'stock':
                TablaRenderer.renderizarTablaStock(this.datos.stock);
                UIUtils.actualizarContador('count-stock', this.datos.stock.length);
                break;
        }
        
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
     * Actualizar última actualización
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
            
            TablaRenderer.renderizarTablaVerano(this.datos.verano);
            TablaRenderer.renderizarTablaInvierno(this.datos.invierno);
            TablaRenderer.renderizarTablaStock(this.datos.stock);
            
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
        UIUtils.mostrarAlerta('No se pudieron cargar los datos. Verifique su conexión.', 'error');
        
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
     * Pausar actualizaciones automáticas
     */
    pausarActualizaciones() {
        console.log('Pausando actualizaciones automáticas');
    }

    /**
     * Reanudar actualizaciones automáticas
     */
    reanudarActualizaciones() {
        console.log('Reanudando actualizaciones automáticas');
    }

    /**
     * Limpiar cache expirado
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
     * Diagnóstico del sistema
     */
    diagnostico() {
        const info = {
            estado: this.estado,
            datos_cargados: Object.keys(this.datos).map(k => ({ solapa: k, registros: this.datos[k].length })),
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
            }
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
    window.presupuestoApp.buscarDatos(solapa);
}

function exportarExcel(solapa) {
    window.presupuestoApp.exportarExcel(solapa);
}

function editarIndice(rubro, categoria, indiceActual, solapa, index) {
    IndiceEditor.editarIndice(rubro, categoria, indiceActual, solapa, index);
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
    UIUtils.mostrarAlerta('Cache limpiado', 'info');
}

function reiniciarAplicacion() {
    if (confirm('¿Está seguro que desea reiniciar la aplicación? Se perderán los datos no guardados.')) {
        location.reload();
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('Presupuesto App iniciado correctamente');
    
    // Mostrar información de versión en consola
    console.log('%c Sistema de Presupuesto de Compras v2.0 ', 'background: #0d6efd; color: white; font-size: 14px; padding: 5px 10px; border-radius: 3px;');
    console.log('Tipo "diagnosticoSistema()" para ver información del sistema');
});