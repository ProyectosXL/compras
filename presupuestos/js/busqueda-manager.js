
// Gestor de funcionalidades de búsqueda optimizada
// Archivo: presupuestos/js/busqueda-manager.js

class BusquedaManager {
    static timeoutBusqueda = null;
    static historialBusquedas = [];
    static filtrosActivos = {};
    static indiceBusqueda = {}; // Cache de índices para búsqueda rápida

    /**
     * Inicializar gestor de búsqueda
     */
    static init() {
        BusquedaManager.configurarEventListeners();
        BusquedaManager.cargarHistorial();
    }

    /**
     * Configurar event listeners para búsqueda OPTIMIZADA
     */
    static configurarEventListeners() {
        ['verano', 'invierno', 'stock'].forEach(solapa => {
            const input = document.getElementById(`search-${solapa}`);
            if (input) {
                // Búsqueda instantánea con debounce muy corto
                input.addEventListener('input', BusquedaManager.debounce((e) => {
                    BusquedaManager.busquedaInstantanea(solapa, e.target.value);
                }, 150)); // Reducido de 300ms a 150ms

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
     * NUEVA: Búsqueda instantánea usando filtros visuales (no API)
     */
    static busquedaInstantanea(solapa, termino) {
        const terminoLimpio = termino.trim();
        
        if (terminoLimpio.length === 0) {
            TablaRenderer.aplicarFiltroVisual(solapa, '');
            BusquedaManager.actualizarContadorBusqueda(solapa, null);
            
            // AGREGAR ESTAS LÍNEAS - Restaurar totales originales
            if (['verano', 'invierno'].includes(solapa)) {
                const datosOriginales = window.presupuestoApp?.datos?.[solapa] || [];
                TotalesCompra.aplicarFiltros(solapa, datosOriginales);
            }
            return;
        }

        if (terminoLimpio.length >= 2) {
            // Usar filtro visual para búsqueda instantánea
            const coincidencias = TablaRenderer.aplicarFiltroVisual(solapa, terminoLimpio);
            BusquedaManager.actualizarContadorBusqueda(solapa, coincidencias);
            
            // AGREGAR ESTAS LÍNEAS - Obtener datos filtrados y actualizar totales
            if (['verano', 'invierno'].includes(solapa)) {
                const datosFiltrados = BusquedaManager.obtenerDatosFiltrados(solapa, terminoLimpio);
                TotalesCompra.aplicarFiltros(solapa, datosFiltrados);
            }
            
            // Guardar término si es útil
            if (coincidencias > 0) {
                BusquedaManager.agregarAlHistorial(terminoLimpio, solapa, coincidencias, true);
            }
        }
    }

    /**
     * NUEVA: Obtener datos filtrados por término de búsqueda
     */
    static obtenerDatosFiltrados(solapa, termino) {
        try {
            const datos = window.presupuestoApp?.datos?.[solapa] || [];
            const terminoLimpio = termino.toLowerCase().trim();
            
            if (!terminoLimpio) return datos;
            
            return datos.filter(item => {
                return BusquedaManager.buscarEnCampos(item, terminoLimpio);
            });
        } catch (error) {
            console.error('Error obteniendo datos filtrados:', error);
            return [];
        }
    }

    /**
     * NUEVA: Buscar término en campos del item
     */
    static buscarEnCampos(item, termino) {
        const campos = ['RUBRO', 'CATEGORIA', 'CATEGORIA_PADRE', 'DESCRIPCION'];
        
        return campos.some(campo => {
            const valor = item[campo];
            return valor && valor.toString().toLowerCase().includes(termino);
        });
    }

        /**
     * Buscar en una solapa específica (para búsquedas complejas)
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

            // Para términos muy específicos, usar API para búsqueda completa
            if (termino.length >= 5 || termino.includes(' ')) {
                // Mostrar indicador de búsqueda
                BusquedaManager.mostrarIndicadorBusqueda(solapa, true);

                const response = await APIClient.buscarDatos(termino, solapa);

                if (response.success) {
                    // Renderizar resultados
                    BusquedaManager.renderizarResultados(solapa, response.data);
                    
                    // Actualizar contador
                    UIUtils.actualizarContador(`count-${solapa}`, response.data.length);
                    
                    // Guardar en historial
                    BusquedaManager.agregarAlHistorial(termino, solapa, response.data.length, false);
                    
                    // Resaltar términos encontrados
                    BusquedaManager.resaltarTerminos(solapa, termino);
                    
                    UIUtils.mostrarAlerta(
                        `Encontrados ${response.data.length} resultados para "${termino}"`,
                        'info',
                        2000
                    );
                } else {
                    UIUtils.mostrarAlerta('Error en búsqueda: ' + response.message, 'error');
                }

                BusquedaManager.mostrarIndicadorBusqueda(solapa, false);
            }
            
        } catch (error) {
            console.error('Error en búsqueda:', error);
            UIUtils.mostrarAlerta('Error realizando búsqueda', 'error');
            BusquedaManager.mostrarIndicadorBusqueda(solapa, false);
        }
    }

    /**
     * NUEVA: Actualizar contador específico de búsqueda
     */
    static actualizarContadorBusqueda(solapa, coincidencias) {
        const contador = document.getElementById(`count-${solapa}`);
        if (contador) {
            if (coincidencias === null) {
                // Restaurar contador original
                const app = window.presupuestoApp;
                const datosOriginales = app.getDatos(solapa);
                contador.textContent = `${FormatoUtils.formatearNumero(datosOriginales.length)} registros`;
                contador.className = 'badge bg-info fs-6';
            } else {
                // Mostrar resultados de búsqueda
                contador.textContent = `${FormatoUtils.formatearNumero(coincidencias)} de ${FormatoUtils.formatearNumero(window.presupuestoApp.getDatos(solapa).length)}`;
                contador.className = coincidencias > 0 ? 'badge bg-success fs-6' : 'badge bg-warning fs-6';
            }
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
        
        // Usar filtro visual para mostrar todo
        TablaRenderer.aplicarFiltroVisual(solapa, '');
        BusquedaManager.actualizarContadorBusqueda(solapa, null);
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
     * Agregar al historial de búsquedas (MEJORADO)
     */
    static agregarAlHistorial(termino, solapa, resultados, esInstantanea = false) {
        // No agregar búsquedas muy cortas al historial
        if (termino.length < 3) return;
        
        const busqueda = {
            termino: termino,
            solapa: solapa,
            resultados: resultados,
            instantanea: esInstantanea,
            timestamp: Date.now()
        };

        // Evitar duplicados recientes (últimos 30 segundos)
        const existe = BusquedaManager.historialBusquedas.find(
            b => b.termino === termino && b.solapa === solapa && 
                 Date.now() - b.timestamp < 30000
        );

        if (!existe) {
            BusquedaManager.historialBusquedas.unshift(busqueda);
            
            // Mantener máximo 30 búsquedas
            if (BusquedaManager.historialBusquedas.length > 30) {
                BusquedaManager.historialBusquedas = BusquedaManager.historialBusquedas.slice(0, 30);
            }
            
            BusquedaManager.guardarHistorial();
        }
    }

    /**
     * Mostrar sugerencias de búsqueda (MEJORADO)
     */
    static mostrarSugerencias(solapa) {
        const input = document.getElementById(`search-${solapa}`);
        if (!input || input.value.length > 0) return;

        // Obtener sugerencias del historial (no instantáneas)
        const sugerencias = BusquedaManager.historialBusquedas
            .filter(b => b.solapa === solapa && !b.instantanea && b.resultados > 0)
            .slice(0, 5)
            .map(b => b.termino);

        if (sugerencias.length === 0) return;

        BusquedaManager.crearDropdownSugerencias(input, [...new Set(sugerencias)]);
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
            item.innerHTML = `<i class="fas fa-search me-2"></i>${sugerencia}`;
            
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
     * NUEVA: Crear índice de búsqueda para datos cargados
     */
    static crearIndiceBusqueda(solapa, datos) {
        if (!datos || datos.length === 0) return;
        
        BusquedaManager.indiceBusqueda[solapa] = {};
        
        datos.forEach((item, index) => {
            const rubro = (item.RUBRO || '').toLowerCase();
            const categoria = (item.CATEGORIA_PADRE || '').toLowerCase();
            
            // Crear índices por palabras clave
            const palabras = [...rubro.split(' '), ...categoria.split(' ')];
            
            palabras.forEach(palabra => {
                if (palabra.length >= 2) {
                    if (!BusquedaManager.indiceBusqueda[solapa][palabra]) {
                        BusquedaManager.indiceBusqueda[solapa][palabra] = [];
                    }
                    BusquedaManager.indiceBusqueda[solapa][palabra].push(index);
                }
            });
        });
    }

    /**
     * Obtener rubros únicos para filtros
     */
    static async obtenerRubros() {
        try {
            const response = await APIClient.obtenerRubros();
            
            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.message);
            }
            
        } catch (error) {
            console.error('Error al obtener rubros:', error);
            return [];
        }
    }

    /**
     * Filtrar por rubro específico
     */
    static async filtrarPorRubro(rubro, solapa) {
        try {
            if (!rubro) {
                BusquedaManager.restaurarDatosOriginales(solapa);
                
                // AGREGAR ESTAS LÍNEAS - Restaurar totales originales
                if (['verano', 'invierno'].includes(solapa)) {
                    const datosOriginales = window.presupuestoApp?.datos?.[solapa] || [];
                    TotalesCompra.aplicarFiltros(solapa, datosOriginales);
                }
                return;
            }
            
            // Mostrar indicador
            BusquedaManager.mostrarIndicadorBusqueda(solapa, true);
            
            const response = await APIClient.filtrarPorRubro(rubro, solapa);
            
            if (response.success) {
                BusquedaManager.renderizarResultados(solapa, response.data);
                UIUtils.actualizarContador(`count-${solapa}`, response.data.length);
                
                // AGREGAR ESTAS LÍNEAS - Actualizar totales con datos filtrados
                if (['verano', 'invierno'].includes(solapa)) {
                    TotalesCompra.aplicarFiltros(solapa, response.data);
                }
                
                UIUtils.mostrarAlerta(
                    `Filtrado por rubro: ${rubro} (${response.data.length} registros)`,
                    'info',
                    2000
                );
            } else {
                UIUtils.mostrarAlerta('Error al filtrar: ' + response.message, 'error');
            }
            
        } catch (error) {
            console.error('Error filtrando por rubro:', error);
            UIUtils.mostrarAlerta('Error al filtrar por rubro', 'error');
        } finally {
            BusquedaManager.mostrarIndicadorBusqueda(solapa, false);
        }
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
        StorageUtils.guardar('historial_busquedas', BusquedaManager.historialBusquedas, 3 * 24 * 60 * 60 * 1000); // 3 días
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
        const ultimaHora = BusquedaManager.historialBusquedas.filter(
            b => Date.now() - b.timestamp < 60 * 60 * 1000
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
            busquedas_hora: ultimaHora,
            terminos_populares: topTerminos,
            promedio_resultados: total > 0 ? BusquedaManager.historialBusquedas.reduce((acc, b) => acc + b.resultados, 0) / total : 0,
            busquedas_instantaneas: BusquedaManager.historialBusquedas.filter(b => b.instantanea).length
        };
    }

    /**
     * Debounce helper (optimizado)
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