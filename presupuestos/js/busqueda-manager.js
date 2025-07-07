
// Gestor de funcionalidades de búsqueda y filtrado
// Archivo: presupuestos/js/busqueda-manager.js

class BusquedaManager {
    static timeoutBusqueda = null;
    static historialBusquedas = [];
    static filtrosActivos = {};

    /**
     * Inicializar gestor de búsqueda
     */
    static init() {
        BusquedaManager.configurarEventListeners();
        BusquedaManager.cargarHistorial();
    }

    /**
     * Configurar event listeners para búsqueda
     */
    static configurarEventListeners() {
        // Event listeners para inputs de búsqueda
        ['verano', 'invierno', 'stock'].forEach(solapa => {
            const input = document.getElementById(`search-${solapa}`);
            if (input) {
                input.addEventListener('input', BusquedaManager.debounce((e) => {
                    BusquedaManager.buscarEnSolapa(solapa, e.target.value);
                }, 300));

                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        BusquedaManager.buscarEnSolapa(solapa, e.target.value);
                    }
                    if (e.key === 'Escape') {
                        BusquedaManager.limpiarBusqueda(solapa);
                    }
                });

                input.addEventListener('focus', () => {
                    BusquedaManager.mostrarSugerencias(solapa);
                });
            }
        });
    }

    /**
     * Buscar en una solapa específica
     */
    static async buscarEnSolapa(solapa, termino) {
        try {
            termino = termino.trim();
            
            if (termino.length === 0) {
                BusquedaManager.restaurarDatosOriginales(solapa);
                return;
            }

            if (termino.length < 2) {
                UIUtils.mostrarTooltip(
                    document.getElementById(`search-${solapa}`),
                    'Ingrese al menos 2 caracteres'
                );
                return;
            }

            // Mostrar indicador de búsqueda
            BusquedaManager.mostrarIndicadorBusqueda(solapa, true);

            // Realizar búsqueda
            const response = await APIClient.buscarDatos(termino, solapa);

            if (response.success) {
                // Renderizar resultados
                BusquedaManager.renderizarResultados(solapa, response.data);
                
                // Actualizar contador
                UIUtils.actualizarContador(`count-${solapa}`, response.data.length);
                
                // Guardar en historial
                BusquedaManager.agregarAlHistorial(termino, solapa, response.data.length);
                
                // Resaltar términos encontrados
                BusquedaManager.resaltarTerminos(solapa, termino);
                
                UIUtils.mostrarAlerta(
                    `Encontrados ${response.data.length} resultados para "${termino}"`,
                    'info',
                    3000
                );
            } else {
                UIUtils.mostrarAlerta('Error en búsqueda: ' + response.message, 'error');
            }

        } catch (error) {
            console.error('Error en búsqueda:', error);
            UIUtils.mostrarAlerta('Error realizando búsqueda', 'error');
        } finally {
            BusquedaManager.mostrarIndicadorBusqueda(solapa, false);
        }
    }

    /**
     * Renderizar resultados según la solapa
     */
    static renderizarResultados(solapa, datos) {
        switch (solapa) {
            case 'verano':
                TablaRenderer.renderizarTablaVerano(datos);
                break;
            case 'invierno':
                TablaRenderer.renderizarTablaInvierno(datos);
                break;
            case 'stock':
                TablaRenderer.renderizarTablaStock(datos);
                break;
        }
    }

    /**
     * Restaurar datos originales
     */
    static restaurarDatosOriginales(solapa) {
        const app = window.presupuestoApp;
        const datosOriginales = app.getDatos(solapa);
        
        BusquedaManager.renderizarResultados(solapa, datosOriginales);
        UIUtils.actualizarContador(`count-${solapa}`, datosOriginales.length);
        BusquedaManager.limpiarResaltado(solapa);
    }

    /**
     * Limpiar búsqueda
     */
    static limpiarBusqueda(solapa) {
        const input = document.getElementById(`search-${solapa}`);
        if (input) {
            input.value = '';
            BusquedaManager.restaurarDatosOriginales(solapa);
        }
    }

    /**
     * Mostrar indicador de búsqueda
     */
    static mostrarIndicadorBusqueda(solapa, mostrar) {
        const input = document.getElementById(`search-${solapa}`);
        if (!input) return;

        const container = input.closest('.input-group');
        let spinner = container.querySelector('.search-spinner');

        if (mostrar) {
            if (!spinner) {
                spinner = document.createElement('div');
                spinner.className = 'search-spinner spinner-border spinner-border-sm text-primary ms-2';
                spinner.style.width = '1rem';
                spinner.style.height = '1rem';
                container.appendChild(spinner);
            }
            input.style.paddingRight = '3rem';
        } else {
            if (spinner) {
                spinner.remove();
            }
            input.style.paddingRight = '';
        }
    }

    /**
     * Resaltar términos de búsqueda en resultados
     */
    static resaltarTerminos(solapa, termino) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        const celdas = tabla.querySelectorAll('tbody td');
        
        celdas.forEach(celda => {
            const texto = celda.textContent;
            if (texto.toLowerCase().includes(termino.toLowerCase())) {
                const regex = new RegExp(`(${termino})`, 'gi');
                celda.innerHTML = texto.replace(regex, '<mark>$1</mark>');
            }
        });
    }

    /**
     * Limpiar resaltado
     */
    static limpiarResaltado(solapa) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        const marks = tabla.querySelectorAll('mark');
        
        marks.forEach(mark => {
            mark.outerHTML = mark.textContent;
        });
    }

    /**
     * Agregar al historial de búsquedas
     */
    static agregarAlHistorial(termino, solapa, resultados) {
        const busqueda = {
            termino: termino,
            solapa: solapa,
            resultados: resultados,
            timestamp: Date.now()
        };

        // Evitar duplicados recientes
        const existe = BusquedaManager.historialBusquedas.find(
            b => b.termino === termino && b.solapa === solapa && 
                 Date.now() - b.timestamp < 300000 // 5 minutos
        );

        if (!existe) {
            BusquedaManager.historialBusquedas.unshift(busqueda);
            
            // Mantener máximo 50 búsquedas
            if (BusquedaManager.historialBusquedas.length > 50) {
                BusquedaManager.historialBusquedas = BusquedaManager.historialBusquedas.slice(0, 50);
            }
            
            BusquedaManager.guardarHistorial();
        }
    }

    /**
     * Mostrar sugerencias de búsqueda
     */
    static mostrarSugerencias(solapa) {
        const input = document.getElementById(`search-${solapa}`);
        if (!input || input.value.length > 0) return;

        // Obtener sugerencias del historial
        const sugerencias = BusquedaManager.historialBusquedas
            .filter(b => b.solapa === solapa)
            .slice(0, 5)
            .map(b => b.termino);

        if (sugerencias.length === 0) return;

        BusquedaManager.crearDropdownSugerencias(input, sugerencias);
    }

    /**
     * Crear dropdown de sugerencias
     */
    static crearDropdownSugerencias(input, sugerencias) {
        // Remover dropdown existente
        const existente = document.getElementById('dropdown-sugerencias');
        if (existente) existente.remove();

        const dropdown = document.createElement('div');
        dropdown.id = 'dropdown-sugerencias';
        dropdown.className = 'dropdown-menu show position-absolute';
        dropdown.style.top = '100%';
        dropdown.style.left = '0';
        dropdown.style.right = '0';
        dropdown.style.zIndex = '1000';

        sugerencias.forEach(sugerencia => {
            const item = document.createElement('a');
            item.className = 'dropdown-item';
            item.href = '#';
            item.textContent = sugerencia;
            
            item.addEventListener('click', (e) => {
                e.preventDefault();
                input.value = sugerencia;
                input.dispatchEvent(new Event('input'));
                dropdown.remove();
            });
            
            dropdown.appendChild(item);
        });

        // Posicionar dropdown
        const container = input.closest('.input-group');
        container.style.position = 'relative';
        container.appendChild(dropdown);

        // Cerrar al hacer click fuera
        setTimeout(() => {
            document.addEventListener('click', function cerrarDropdown(e) {
                if (!container.contains(e.target)) {
                    dropdown.remove();
                    document.removeEventListener('click', cerrarDropdown);
                }
            });
        }, 100);
    }

    /**
     * Búsqueda avanzada con filtros
     */
    static async busquedaAvanzada(filtros) {
        try {
            const resultados = {};
            
            for (const solapa of ['verano', 'invierno', 'stock']) {
                if (filtros.solapas.includes(solapa)) {
                    const response = await APIClient.buscarDatos(filtros.termino, solapa);
                    if (response.success) {
                        resultados[solapa] = BusquedaManager.aplicarFiltrosAvanzados(response.data, filtros);
                    }
                }
            }
            
            return resultados;
        } catch (error) {
            console.error('Error en búsqueda avanzada:', error);
            throw error;
        }
    }

    /**
     * Aplicar filtros avanzados a los datos
     */
    static aplicarFiltrosAvanzados(datos, filtros) {
        return datos.filter(item => {
            // Filtro por rango de valores
            if (filtros.rangoStock && item.STOCK_PROYECTADO) {
                const stock = parseFloat(item.STOCK_PROYECTADO);
                if (stock < filtros.rangoStock.min || stock > filtros.rangoStock.max) {
                    return false;
                }
            }

            // Filtro por índice de variación
            if (filtros.rangoIndice && item.INDICE_VARIACION) {
                const indice = parseFloat(item.INDICE_VARIACION);
                if (indice < filtros.rangoIndice.min || indice > filtros.rangoIndice.max) {
                    return false;
                }
            }

            // Filtro por compra proyectada
            if (filtros.tipoCompra) {
                const compra = parseFloat(item.COMPRA_PROYECTADA || 0);
                switch (filtros.tipoCompra) {
                    case 'positiva':
                        if (compra <= 0) return false;
                        break;
                    case 'negativa':
                        if (compra >= 0) return false;
                        break;
                    case 'neutra':
                        if (compra !== 0) return false;
                        break;
                }
            }

            return true;
        });
    }

    /**
     * Exportar resultados de búsqueda
     */
    static exportarResultados(solapa, formato = 'csv') {
        const tabla = document.getElementById(`tabla-${solapa}`);
        const termino = document.getElementById(`search-${solapa}`).value;
        
        if (formato === 'csv') {
            TablaRenderer.exportarTablaCSV(solapa);
        }
        
        UIUtils.mostrarAlerta(`Resultados exportados para: "${termino}"`, 'success');
    }

    /**
     * Limpiar historial de búsquedas
     */
    static limpiarHistorial() {
        BusquedaManager.historialBusquedas = [];
        StorageUtils.eliminar('historial_busquedas');
        UIUtils.mostrarAlerta('Historial de búsquedas limpiado', 'info');
    }

    /**
     * Guardar historial en localStorage
     */
    static guardarHistorial() {
        StorageUtils.guardar('historial_busquedas', BusquedaManager.historialBusquedas, 7 * 24 * 60 * 60 * 1000); // 7 días
    }

    /**
     * Cargar historial desde localStorage
     */
    static cargarHistorial() {
        const historial = StorageUtils.obtener('historial_busquedas');
        if (historial && Array.isArray(historial)) {
            BusquedaManager.historialBusquedas = historial;
        }
    }

    /**
     * Obtener estadísticas de búsqueda
     */
    static obtenerEstadisticas() {
        const total = BusquedaManager.historialBusquedas.length;
        const ultimaSemana = BusquedaManager.historialBusquedas.filter(
            b => Date.now() - b.timestamp < 7 * 24 * 60 * 60 * 1000
        ).length;
        
        const terminosMasUsados = {};
        BusquedaManager.historialBusquedas.forEach(b => {
            terminosMasUsados[b.termino] = (terminosMasUsados[b.termino] || 0) + 1;
        });
        
        const topTerminos = Object.entries(terminosMasUsados)
            .sort(([,a], [,b]) => b - a)
            .slice(0, 5);

        return {
            total_busquedas: total,
            busquedas_semana: ultimaSemana,
            terminos_populares: topTerminos,
            promedio_resultados: BusquedaManager.historialBusquedas.reduce((acc, b) => acc + b.resultados, 0) / total || 0
        };
    }

    /**
     * Debounce helper
     */
    static debounce(func, delay) {
        let timeoutId;
        return function (...args) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => func.apply(this, args), delay);
        };
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    BusquedaManager.init();
});