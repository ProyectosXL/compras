
// Filtros compartidos entre las solapas Verano, Invierno y Stock Proyectado.
// Archivo: presupuestos/js/filtros-manager.js
//
// Texto del buscador + Rubros y Categorías con selección múltiple (FiltroMultiple).
// Las tres solapas comparten UN estado: filtrar en Verano deja filtrado Invierno y
// Stock, como ya pasaba con los <select>.
//
// Los filtros NO se guardan entre recargas de página. Antes vivían 24 h en
// localStorage ('filtros_persistentes') y al volver al otro día la tabla aparecía
// recortada sin que nadie recordara por qué: los totales no cerraban con lo que la
// persona esperaba ver. Ahora cada carga de página arranca con todo tildado y el
// buscador vacío; dentro de la misma sesión de página sí se mantienen.

class FiltrosManager {
    static filtrosActivos = {
        termino: ''
    };

    static solapasPresupuesto = ['verano', 'invierno', 'stock'];
    static timeoutActualizacion = null;

    // Estado único de rubros/categorías, con un par de desplegables por solapa.
    static seleccion = new FiltroMultiple({
        onCambio: () => FiltrosManager.programarPropagacion()
    });

    static montado = false;

    /**
     * Inicializar el gestor de filtros
     */
    static init() {
        // La clave vieja se borra para que un filtro de otra sesión no reaparezca.
        StorageUtils.eliminar('filtros_persistentes');

        FiltrosManager.montarDesplegables();
        FiltrosManager.configurarEventListeners();
        console.log('✅ FiltrosManager inicializado');
    }

    static montarDesplegables() {
        if (FiltrosManager.montado) return;
        FiltrosManager.solapasPresupuesto.forEach(solapa => {
            FiltrosManager.seleccion.montar(
                document.getElementById(`filtro-rubro-${solapa}`),
                document.getElementById(`filtro-categoria-${solapa}`)
            );
        });
        FiltrosManager.montado = true;
    }

    /**
     * Configurar event listeners para filtros
     */
    static configurarEventListeners() {
        FiltrosManager.solapasPresupuesto.forEach(solapa => {
            const inputBusqueda = document.getElementById(`search-${solapa}`);
            if (inputBusqueda) {
                inputBusqueda.addEventListener('input', (e) => {
                    FiltrosManager.actualizarFiltro('termino', e.target.value);
                });
            }
        });
    }

    /**
     * Rubros y categorías de una carga de datos. Lo que aparece por primera vez entra
     * tildado; lo que ya estaba conserva su marca.
     *
     * @param {Array<Array>} conjuntos filas de cada solapa (verano, invierno, stock)
     */
    static cargarOpciones(conjuntos) {
        FiltrosManager.montarDesplegables();
        FiltrosManager.seleccion.iniciarCarga();
        (conjuntos || []).forEach(datos => FiltrosManager.seleccion.agregarDatos(datos));
        FiltrosManager.seleccion.finalizarCarga();
    }

    /**
     * Actualizar un filtro específico y propagarlo
     */
    static actualizarFiltro(tipoFiltro, valor) {
        FiltrosManager.filtrosActivos[tipoFiltro] = valor;
        FiltrosManager.programarPropagacion();
    }

    static programarPropagacion() {
        // Debounce: al tipear o tildar varias casillas seguidas se dibuja una sola vez.
        if (FiltrosManager.timeoutActualizacion) {
            clearTimeout(FiltrosManager.timeoutActualizacion);
        }
        FiltrosManager.timeoutActualizacion = setTimeout(() => {
            FiltrosManager.propagarFiltros();
        }, 250);
    }

    /**
     * Propagar filtros a todas las solapas de presupuesto
     */
    static propagarFiltros() {
        FiltrosManager.solapasPresupuesto.forEach(solapa => {
            FiltrosManager.sincronizarInputsSolapa(solapa);
            FiltrosManager.aplicarFiltrosASolapa(solapa);
        });
    }

    /**
     * Sincronizar el buscador de una solapa con el texto activo. Los desplegables
     * no hace falta: los repinta el propio FiltroMultiple.
     */
    static sincronizarInputsSolapa(solapa) {
        const { termino } = FiltrosManager.filtrosActivos;
        const inputBusqueda = document.getElementById(`search-${solapa}`);
        if (inputBusqueda && inputBusqueda.value !== termino) {
            inputBusqueda.value = termino;
        }
    }

    /**
     * Aplicar filtros a una solapa específica
     */
    static aplicarFiltrosASolapa(solapa) {
        try {
            const app = window.presupuestoApp;
            if (!app || !app.datos || !app.datos[solapa]) {
                return;
            }

            const datosOriginales = app.datos[solapa];
            if (datosOriginales.length === 0) {
                return;
            }

            const datosFiltrados = FiltrosManager.filtrarDatos(datosOriginales);

            if (datosFiltrados.length === 0 && FiltrosManager.seleccion.nadaTildado()) {
                // Sin nada tildado no es un error ni "no hay datos": es una elección del
                // usuario, y se dice así para que no piense que la carga falló.
                const tbody = document.getElementById(`tbody-${solapa}`);
                const columnas = document.querySelectorAll(`#tabla-${solapa} thead tr:last-child th`).length || 10;
                TablaRendererUtils.mostrarTablaVacia(tbody, columnas, 'No hay rubros/categorías seleccionados');
            } else {
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
            }

            UIUtils.actualizarContador(`count-${solapa}`, datosFiltrados.length);

            if (app.notificarCambioFiltros) {
                app.notificarCambioFiltros(solapa, datosFiltrados);
            }

            FiltrosManager.mostrarIndicadorFiltros(solapa, FiltrosManager.obtenerEstadoFiltros().activos);

        } catch (error) {
            console.error(`Error aplicando filtros a ${solapa}:`, error);
        }
    }

    /**
     * Filtrar datos según los filtros activos.
     * Una fila se muestra si su rubro está tildado, su par rubro+categoría está
     * tildado y coincide con el texto del buscador.
     */
    static filtrarDatos(datos) {
        const termino = (FiltrosManager.filtrosActivos.termino || '').trim().toLowerCase();

        return (datos || []).filter(item => {
            if (termino) {
                const coincideTermino = [
                    item.RUBRO,
                    item.CATEGORIA,
                    item.CATEGORIA_PADRE,
                    item.DESCRIPCION
                ].some(campo => campo && campo.toString().toLowerCase().includes(termino));

                if (!coincideTermino) return false;
            }

            return FiltrosManager.seleccion.pasa(item);
        });
    }

    /**
     * Indicador "F" en la pestaña: solo si hay algo destildado o texto en el buscador.
     */
    static mostrarIndicadorFiltros(solapa, tieneFiltros) {
        const tab = document.querySelector(`#${solapa}-tab`);
        if (!tab) return;

        const indicadorAnterior = tab.querySelector('.filtro-activo-indicator');
        if (indicadorAnterior) {
            indicadorAnterior.remove();
        }

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
     * Limpiar filtros = todo tildado + buscador vacío, en las tres solapas.
     */
    static limpiarFiltros() {
        FiltrosManager.filtrosActivos = { termino: '' };
        FiltrosManager.seleccion.tildarTodo({ notificar: false });

        FiltrosManager.solapasPresupuesto.forEach(solapa => {
            const inputBusqueda = document.getElementById(`search-${solapa}`);
            if (inputBusqueda) inputBusqueda.value = '';
            FiltrosManager.mostrarIndicadorFiltros(solapa, false);
        });

        FiltrosManager.propagarFiltros();

        UIUtils.mostrarAlerta('Filtros limpiados en todas las solapas', 'info', 2000);
    }

    /**
     * Obtener estado actual de filtros
     */
    static obtenerEstadoFiltros() {
        const hayTexto = (FiltrosManager.filtrosActivos.termino || '').trim().length > 0;
        const hayDestildados = FiltrosManager.seleccion.hayDestildados();
        const r = FiltrosManager.seleccion.conteoRubros();
        const c = FiltrosManager.seleccion.conteoCategorias();

        return {
            filtros: {
                termino: FiltrosManager.filtrosActivos.termino,
                rubros: `${r.tildados}/${r.total}`,
                categorias: `${c.tildados}/${c.total}`
            },
            activos: hayTexto || hayDestildados,
            cantidad: (hayTexto ? 1 : 0) + (r.tildados < r.total ? 1 : 0) + (c.tildados < c.total ? 1 : 0)
        };
    }

    /**
     * Debug y diagnóstico
     */
    static debug() {
        console.group('🔍 DEBUG FILTROS MANAGER');
        console.log('Estado:', FiltrosManager.obtenerEstadoFiltros());
        console.groupEnd();
    }
}

// Hacer disponible globalmente
window.FiltrosManager = FiltrosManager;

// Funciones globales para compatibilidad
window.limpiarFiltrosPersistentes = () => FiltrosManager.limpiarFiltros();
window.debugFiltros = () => FiltrosManager.debug();

document.addEventListener('DOMContentLoaded', function() {
    FiltrosManager.init();
});
