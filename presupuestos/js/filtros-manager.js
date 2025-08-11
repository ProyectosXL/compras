
// Sistema de Filtros Persistentes entre Solapas
// Archivo: presupuestos/js/filtros-manager.js

class FiltrosManager {
    static filtrosActivos = {
        termino: '',
        rubro: '',
        categoria: ''
    };
    
    static solapasPresupuesto = ['verano', 'invierno', 'stock'];
    static timeoutActualizacion = null;

    /**
     * Inicializar el gestor de filtros
     */
    static init() {
        FiltrosManager.configurarEventListeners();
        FiltrosManager.cargarFiltrosGuardados();
        console.log('✅ FiltrosManager inicializado');
    }

    /**
     * Configurar event listeners para filtros
     */
    static configurarEventListeners() {
        // Event listeners para búsqueda
        FiltrosManager.solapasPresupuesto.forEach(solapa => {
            const inputBusqueda = document.getElementById(`search-${solapa}`);
            if (inputBusqueda) {
                inputBusqueda.addEventListener('input', (e) => {
                    FiltrosManager.actualizarFiltro('termino', e.target.value);
                });
            }

            const selectRubro = document.getElementById(`filtro-rubro-${solapa}`);
            if (selectRubro) {
                selectRubro.addEventListener('change', (e) => {
                    FiltrosManager.actualizarFiltro('rubro', e.target.value);
                });
            }

            const selectCategoria = document.getElementById(`filtro-categoria-${solapa}`);
            if (selectCategoria) {
                selectCategoria.addEventListener('change', (e) => {
                    FiltrosManager.actualizarFiltro('categoria', e.target.value);
                });
            }
        });

        // Event listener para cambio de tabs
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = document.querySelectorAll('#presupuestoTabs button[data-bs-toggle="tab"]');
            tabs.forEach(tab => {
                tab.addEventListener('shown.bs.tab', (event) => {
                    const targetId = event.target.getAttribute('data-bs-target');
                    const solapa = targetId.replace('#', '');
                    
                    if (FiltrosManager.solapasPresupuesto.includes(solapa)) {
                        setTimeout(() => {
                            FiltrosManager.aplicarFiltrosASolapa(solapa);
                        }, 100);
                    }
                });
            });
        });
    }

    /**
     * Actualizar un filtro específico y propagarlo
     */
    static actualizarFiltro(tipoFiltro, valor) {
        // Actualizar el filtro activo
        FiltrosManager.filtrosActivos[tipoFiltro] = valor;

        // Guardar en localStorage
        FiltrosManager.guardarFiltros();

        // Debounce para evitar demasiadas actualizaciones
        if (FiltrosManager.timeoutActualizacion) {
            clearTimeout(FiltrosManager.timeoutActualizacion);
        }

        FiltrosManager.timeoutActualizacion = setTimeout(() => {
            FiltrosManager.propagarFiltros();
        }, 300);
    }

    /**
     * Propagar filtros a todas las solapas de presupuesto
     */
    static propagarFiltros() {
        console.log('🔄 Propagando filtros:', FiltrosManager.filtrosActivos);

        FiltrosManager.solapasPresupuesto.forEach(solapa => {
            FiltrosManager.sincronizarInputsSolapa(solapa);
            FiltrosManager.aplicarFiltrosASolapa(solapa);
        });
    }

    /**
     * Sincronizar inputs de una solapa con los filtros activos
     */
    static sincronizarInputsSolapa(solapa) {
        const { termino, rubro, categoria } = FiltrosManager.filtrosActivos;

        // Sincronizar búsqueda
        const inputBusqueda = document.getElementById(`search-${solapa}`);
        if (inputBusqueda && inputBusqueda.value !== termino) {
            inputBusqueda.value = termino;
        }

        // Sincronizar rubro
        const selectRubro = document.getElementById(`filtro-rubro-${solapa}`);
        if (selectRubro && selectRubro.value !== rubro) {
            selectRubro.value = rubro;
        }

        // Sincronizar categoría
        const selectCategoria = document.getElementById(`filtro-categoria-${solapa}`);
        if (selectCategoria && selectCategoria.value !== categoria) {
            selectCategoria.value = categoria;
        }
    }

    /**
     * Aplicar filtros a una solapa específica
     */
    static aplicarFiltrosASolapa(solapa) {
        try {
            const app = window.presupuestoApp;
            if (!app || !app.datos || !app.datos[solapa]) {
                console.warn(`No hay datos disponibles para la solapa ${solapa}`);
                return;
            }

            const datosOriginales = app.datos[solapa];
            if (datosOriginales.length === 0) {
                console.warn(`No hay datos cargados para la solapa ${solapa}`);
                return;
            }

            // Aplicar filtros
            const datosFiltrados = FiltrosManager.filtrarDatos(datosOriginales);

            console.log(`📊 ${solapa}: ${datosOriginales.length} -> ${datosFiltrados.length} registros`);

            // Renderizar datos filtrados
            switch (solapa) {
                case 'verano':
                    TablaRenderer.renderizarTablaVerano(datosFiltrados);
                    break;
                case 'invierno':
                    TablaRenderer.renderizarTablaInvierno(datosFiltrados);
                    break;
                case 'stock':
                    TablaRenderer.renderizarTablaStock(datosFiltrados);
                    break;
            }

            // Actualizar contador
            UIUtils.actualizarContador(`count-${solapa}`, datosFiltrados.length);

            // Notificar cambios a totales
            if (app.notificarCambioFiltros) {
                app.notificarCambioFiltros(solapa, datosFiltrados);
            }

            // Mostrar indicador de filtros activos
            FiltrosManager.mostrarIndicadorFiltros(solapa, datosFiltrados.length < datosOriginales.length);

        } catch (error) {
            console.error(`Error aplicando filtros a ${solapa}:`, error);
        }
    }

    /**
     * Filtrar datos según los filtros activos
     */
    static filtrarDatos(datos) {
        const { termino, rubro, categoria } = FiltrosManager.filtrosActivos;

        return datos.filter(item => {
            // Filtro por término de búsqueda
            if (termino && termino.trim().length > 0) {
                const terminoLower = termino.toLowerCase();
                const coincideTermino = [
                    item.RUBRO,
                    item.CATEGORIA,
                    item.CATEGORIA_PADRE,
                    item.DESCRIPCION
                ].some(campo => 
                    campo && campo.toString().toLowerCase().includes(terminoLower)
                );
                
                if (!coincideTermino) return false;
            }

            // Filtro por rubro
            if (rubro && rubro.trim().length > 0) {
                if (item.RUBRO !== rubro) return false;
            }

            // Filtro por categoría
            if (categoria && categoria.trim().length > 0) {
                const categoriaItem = item.CATEGORIA_PADRE || item.CATEGORIA;
                if (categoriaItem !== categoria) return false;
            }

            return true;
        });
    }

    /**
     * Mostrar indicador visual de filtros activos
     */
    static mostrarIndicadorFiltros(solapa, tieneFiltros) {
        const tab = document.querySelector(`#${solapa}-tab`);
        if (!tab) return;

        // Remover indicador anterior
        const indicadorAnterior = tab.querySelector('.filtro-activo-indicator');
        if (indicadorAnterior) {
            indicadorAnterior.remove();
        }

        // Agregar indicador si hay filtros activos
        if (tieneFiltros) {
            const indicador = document.createElement('span');
            indicador.className = 'filtro-activo-indicator badge bg-warning text-dark ms-1';
            indicador.style.fontSize = '0.6rem';
            indicador.textContent = 'F';
            indicador.title = 'Filtros activos';
            tab.appendChild(indicador);
        }
    }

    /**
     * Limpiar todos los filtros
     */
    static limpiarFiltros() {
        FiltrosManager.filtrosActivos = {
            termino: '',
            rubro: '',
            categoria: ''
        };

        // Limpiar inputs en todas las solapas
        FiltrosManager.solapasPresupuesto.forEach(solapa => {
            const inputBusqueda = document.getElementById(`search-${solapa}`);
            const selectRubro = document.getElementById(`filtro-rubro-${solapa}`);
            const selectCategoria = document.getElementById(`filtro-categoria-${solapa}`);

            if (inputBusqueda) inputBusqueda.value = '';
            if (selectRubro) selectRubro.value = '';
            if (selectCategoria) selectCategoria.value = '';

            // Remover indicadores de filtros
            FiltrosManager.mostrarIndicadorFiltros(solapa, false);
        });

        // Guardar estado limpio
        FiltrosManager.guardarFiltros();

        // Aplicar filtros (vacíos) para mostrar todos los datos
        FiltrosManager.propagarFiltros();

        UIUtils.mostrarAlerta('Filtros limpiados en todas las solapas', 'info', 2000);
    }

    /**
     * Obtener estado actual de filtros
     */
    static obtenerEstadoFiltros() {
        const activos = Object.values(FiltrosManager.filtrosActivos).some(valor => 
            valor && valor.toString().trim().length > 0
        );

        return {
            filtros: { ...FiltrosManager.filtrosActivos },
            activos: activos,
            cantidad: Object.values(FiltrosManager.filtrosActivos).filter(valor => 
                valor && valor.toString().trim().length > 0
            ).length
        };
    }

    /**
     * Cargar filtros guardados desde localStorage
     */
    static cargarFiltrosGuardados() {
        const filtrosGuardados = StorageUtils.obtener('filtros_persistentes');
        if (filtrosGuardados) {
            FiltrosManager.filtrosActivos = {
                ...FiltrosManager.filtrosActivos,
                ...filtrosGuardados
            };
            
            // Aplicar filtros cargados después de un breve delay
            setTimeout(() => {
                FiltrosManager.propagarFiltros();
            }, 1000);
        }
    }

    /**
     * Guardar filtros en localStorage
     */
    static guardarFiltros() {
        StorageUtils.guardar('filtros_persistentes', FiltrosManager.filtrosActivos, 24 * 60 * 60 * 1000);
    }

    /**
     * Aplicar filtro específico por rubro (para compatibilidad)
     */
    static async aplicarFiltroRubro(rubro, solapa) {
        FiltrosManager.actualizarFiltro('rubro', rubro);
        
        // Esperar un momento para que se propague
        await new Promise(resolve => setTimeout(resolve, 100));
        
        UIUtils.mostrarAlerta(
            rubro ? `Filtrado por rubro: ${rubro}` : 'Filtro de rubro eliminado',
            'info',
            2000
        );
    }

    /**
     * Aplicar filtro específico por categoría (para compatibilidad)
     */
    static async aplicarFiltroCategoria(categoria, solapa) {
        FiltrosManager.actualizarFiltro('categoria', categoria);
        
        // Esperar un momento para que se propague
        await new Promise(resolve => setTimeout(resolve, 100));
        
        UIUtils.mostrarAlerta(
            categoria ? `Filtrado por categoría: ${categoria}` : 'Filtro de categoría eliminado',
            'info',
            2000
        );
    }

    /**
     * Debug y diagnóstico
     */
    static debug() {
        console.group('🔍 DEBUG FILTROS MANAGER');
        console.log('Filtros activos:', FiltrosManager.filtrosActivos);
        console.log('Estado:', FiltrosManager.obtenerEstadoFiltros());
        
        FiltrosManager.solapasPresupuesto.forEach(solapa => {
            const inputBusqueda = document.getElementById(`search-${solapa}`);
            const selectRubro = document.getElementById(`filtro-rubro-${solapa}`);
            const selectCategoria = document.getElementById(`filtro-categoria-${solapa}`);
            
            console.log(`${solapa}:`, {
                busqueda: inputBusqueda?.value || 'N/A',
                rubro: selectRubro?.value || 'N/A',
                categoria: selectCategoria?.value || 'N/A'
            });
        });
        
        console.groupEnd();
    }
}

// Hacer disponible globalmente
window.FiltrosManager = FiltrosManager;

// Funciones globales para compatibilidad
window.limpiarFiltrosPersistentes = () => FiltrosManager.limpiarFiltros();
window.debugFiltros = () => FiltrosManager.debug();

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Esperar un poco para asegurar que todos los elementos estén cargados
    setTimeout(() => {
        FiltrosManager.init();
    }, 500);
});

console.log('✅ Sistema de Filtros Persistentes cargado');