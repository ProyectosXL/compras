// Gestor de Totales de Compra Proyectada
// Archivo: presupuestos/js/totales-compra.js

class TotalesCompra {
    static datos = {
        verano: [],
        invierno: []
    };
    
    static datosFiltrados = {
        verano: [],
        invierno: []
    };

    /**
     * Actualizar datos para una solapa específica
     */
    static actualizarDatos(solapa, datos) {
        if (!['verano', 'invierno'].includes(solapa)) {
            return;
        }

        TotalesCompra.datos[solapa] = Array.isArray(datos) ? datos : [];
        TotalesCompra.datosFiltrados[solapa] = [...TotalesCompra.datos[solapa]];
        
        // Calcular y mostrar totales
        TotalesCompra.calcularTotales(solapa);
    }

    /**
     * Aplicar filtros a los datos
     */
    static aplicarFiltros(solapa, datosFiltrados) {
        if (!['verano', 'invierno'].includes(solapa)) return;

        TotalesCompra.datosFiltrados[solapa] = Array.isArray(datosFiltrados) ? datosFiltrados : [];
        TotalesCompra.calcularTotales(solapa);
    }

    /**
     * Calcular totales de compra proyectada (LÓGICA CORREGIDA PARA SUMAR TODO)
     */
    static calcularTotales(solapa) {
        try {
            const datos = TotalesCompra.datosFiltrados[solapa] || [];
            
            if (datos.length === 0) {
                TotalesCompra.ocultarTotales(solapa);
                return;
            }

            // 1. IDENTIFICAR COLUMNAS DINÁMICAS (Historial de años anteriores)
            const primerItem = datos[0];
            
            // Columnas que ya sumamos explícitamente o que son texto/índices
            const columnasIgnorar = [
                'RUBRO', 'CATEGORIA', 'CATEGORIA_PADRE', 'ID', 'DESCRIPCION', 
                'STOCK_PROYECTADO', 'STOCK_ACTUAL',
                'INDICE_VAR_ORIGINAL', 'INDICE_VARIACION',
                'INDICE_VER_VAR', 'INDICE_VERANO_VARIACION',
                'INDICE_INV_VAR', 'INDICE_INVIERNO_VARIACION',
                'COMPRA_PROYECTADA', 'COMPRA', 'COMPRA_PROY',
                'PROY_VER', 'VENTA_PROY_VERANO', 'PROYECCION_VERANO',
                'PROY_INV', 'VENTA_PROY_INVIERNO', 'PROYECCION_INVIERNO',
                'VENTA_VER_ANT', 'VTA_VERANO_ANT', 'VENTA_VERANO_ANTERIOR',
                'VENTA_INV_ANT', 'VTA_INVIERNO_ANT', 'VENTA_INVIERNO_ANTERIOR'
            ];

            // Detectamos claves numéricas extras (los años del historial)
            const clavesDinamicas = Object.keys(primerItem).filter(key => {
                const valor = parseFloat(primerItem[key]);
                const esVentaAnteriorOculta = (key.startsWith('VTA_') || key.startsWith('VENTA_')) && 
                                              (columnasIgnorar.includes(key));

                return !columnasIgnorar.includes(key) && 
                       !key.includes('INDICE') && 
                       !key.includes('PROY_VER_26') && 
                       !key.includes('PROY_INV_26') &&
                       !isNaN(valor) &&
                       !esVentaAnteriorOculta;
            });

            // 2. INICIALIZAR ACUMULADOR
            const acumuladorInicial = {
                totalRegistros: 0,
                totalStockProyectado: 0,
                totalVentaVeranoAnt: 0,
                totalVentaInviernoAnt: 0,
                totalVentaVeranoProy: 0,
                totalVentaInviernoProy: 0,
                totalCompraProyectada: 0,
                totalNegativo: 0,
                itemsNegativos: 0,
                itemsPositivos: 0,
                dinamicos: {} 
            };

            clavesDinamicas.forEach(k => acumuladorInicial.dinamicos[k] = 0);

            // 3. REDUCE PARA SUMAR TODO
            const totales = datos.reduce((acc, item) => {
                acc.totalRegistros++;

                acc.totalStockProyectado += parseFloat(item.STOCK_PROYECTADO || 0);
                acc.totalCompraProyectada += TotalesCompra.obtenerCompraProyectada(item);
                acc.totalVentaVeranoAnt += TotalesCompra.obtenerValorVentaAnterior(item, 'VERANO');
                acc.totalVentaInviernoAnt += TotalesCompra.obtenerValorVentaAnterior(item, 'INVIERNO');
                acc.totalVentaVeranoProy += TotalesCompra.obtenerValorProyeccion(item, 'VERANO');
                acc.totalVentaInviernoProy += TotalesCompra.obtenerValorProyeccion(item, 'INVIERNO');

                // Sumar Dinámicos
                clavesDinamicas.forEach(k => {
                    acc.dinamicos[k] += parseFloat(item[k] || 0);
                });

                const compra = TotalesCompra.obtenerCompraProyectada(item);
                if (compra < 0) {
                    acc.totalNegativo += Math.abs(compra);
                    acc.itemsNegativos++;
                } else if (compra > 0) {
                    acc.itemsPositivos++;
                }

                return acc;
            }, acumuladorInicial);

            // Mostrar resumen superior y fila inferior
            TotalesCompra.mostrarResumenSuperior(solapa, totales);
            TotalesCompra.agregarFilaTotales(solapa, totales, clavesDinamicas);
            
        } catch (error) {
            console.error('Error calculando totales:', error);
            TotalesCompra.ocultarTotales(solapa);
        }
    }

    /**
     * MÉTODO RESTAURADO: Manejar cambios de índice
     * Este es el método que faltaba y causaba el error.
     */
    static onIndiceActualizado(solapa, rubro, categoria, nuevoIndice) {
        try {
            console.log(`🔄 Recalculando totales tras edición de índice en ${solapa}`);
            
            // 1. Obtener datos actualizados de la app global (que ya tiene el cambio aplicado en memoria)
            const datosActualizados = window.presupuestoApp?.datos?.[solapa] || [];
            
            if (datosActualizados.length === 0) return;
            
            // 2. Re-aplicar filtros actuales para saber qué sumar
            let datosFiltrados = [...datosActualizados];
            
            if (typeof FiltrosManager !== 'undefined') {
                const estadoFiltros = FiltrosManager.obtenerEstadoFiltros();
                if (estadoFiltros.activos) {
                    datosFiltrados = FiltrosManager.filtrarDatos(datosActualizados);
                }
            } else {
                // Fallback si no hay FiltrosManager
                const selectRubro = document.getElementById(`filtro-rubro-${solapa}`);
                const selectCategoria = document.getElementById(`filtro-categoria-${solapa}`);
                const inputBusqueda = document.getElementById(`search-${solapa}`);
                
                if (selectRubro && selectRubro.value) {
                    datosFiltrados = datosFiltrados.filter(i => i.RUBRO === selectRubro.value);
                }
                if (selectCategoria && selectCategoria.value) {
                    datosFiltrados = datosFiltrados.filter(i => i.CATEGORIA_PADRE === selectCategoria.value);
                }
                if (inputBusqueda && inputBusqueda.value) {
                    const termino = inputBusqueda.value.toLowerCase();
                    datosFiltrados = datosFiltrados.filter(i => 
                        (i.RUBRO && i.RUBRO.toLowerCase().includes(termino)) || 
                        (i.CATEGORIA_PADRE && i.CATEGORIA_PADRE.toLowerCase().includes(termino))
                    );
                }
            }
            
            // 3. Actualizar datos filtrados internos y recalcular
            TotalesCompra.datosFiltrados[solapa] = datosFiltrados;
            TotalesCompra.calcularTotales(solapa);
            
        } catch (error) {
            console.error('Error en onIndiceActualizado:', error);
        }
    }

    /**
     * Busca inteligentemente el valor de la Venta Anterior
     */
    static obtenerValorVentaAnterior(item, temporada) {
        const anoActual = new Date().getFullYear().toString().substr(-2); 
        const anoAnterior = (parseInt(anoActual) - 1).toString();
        let posiblesKeys = [];

        if (temporada === 'VERANO') {
            posiblesKeys = [
                'VENTA_VER_ANT', 'VENTA_VERANO_ANT', 'VTA_VERANO_ANT', 'VTA_VER_ANT',
                `VTA_VERANO_${anoActual}`, `VTA_VERANO_${anoAnterior}`, 'VERANO_ANTERIOR'
            ];
        } else {
            posiblesKeys = [
                'VENTA_INV_ANT', 'VENTA_INVIERNO_ANT', 'VTA_INVIERNO_ANT', 'VTA_INV_ANT',
                `VTA_INVIERNO_${anoActual}`, `VTA_INVIERNO_${anoAnterior}`, 'INVIERNO_ANTERIOR'
            ];
        }

        for (const key of posiblesKeys) {
            if (item.hasOwnProperty(key)) return parseFloat(item[key] || 0);
        }

        // Búsqueda por patrón
        const patron = temporada === 'VERANO' ? 'VTA_VERANO_' : 'VTA_INVIERNO_';
        const keysCoincidentes = Object.keys(item).filter(k => k.startsWith(patron));
        if (keysCoincidentes.length > 0) return parseFloat(item[keysCoincidentes[0]] || 0);

        return 0;
    }

    static obtenerValorProyeccion(item, temporada) {
        let posiblesKeys = temporada === 'VERANO' 
            ? ['PROY_VER', 'VENTA_PROY_VERANO', 'PROYECCION_VERANO'] 
            : ['PROY_INV', 'VENTA_PROY_INVIERNO', 'PROYECCION_INVIERNO'];

        for (const key of posiblesKeys) {
            if (item.hasOwnProperty(key)) return parseFloat(item[key] || 0);
        }
        return 0;
    }

    static obtenerCompraProyectada(item) {
        const posiblesCampos = ['COMPRA_PROYECTADA', 'Compra Proyectada', 'compra_proyectada', 'COMPRA_PROY', 'COMPRA'];
        for (const campo of posiblesCampos) {
            if (item.hasOwnProperty(campo)) return parseFloat(item[campo]) || 0;
        }
        return 0;
    }

    static mostrarResumenSuperior(solapa, totales) {
        const containerId = `total-compra-${solapa}-superior`;
        let container = document.getElementById(containerId);

        if (!container) {
            TotalesCompra.crearContainerResumenSuperior(solapa);
            container = document.getElementById(containerId);
        }
        if (!container) return;

        const icono = solapa === 'verano' ? 'fa-sun' : 'fa-snowflake';
        const colorPrimario = solapa === 'verano' ? 'warning' : 'info';

        container.innerHTML = `
            <div class="row text-center">
                <div class="col-4">
                    <small class="text-muted d-block"><i class="fas ${icono} me-1"></i>Total Registros</small>
                    <span class="badge bg-${colorPrimario} fs-6 text-dark">${totales.totalRegistros}</span>
                </div>
                <div class="col-4">
                    <small class="text-muted d-block">Items Necesita Compra</small>
                    <span class="badge bg-danger fs-6">${totales.itemsNegativos}</span>
                </div>
                <div class="col-4">
                    <small class="text-muted d-block">Total Necesita Compra</small>
                    <span class="badge bg-danger fs-5">${TotalesCompra.formatearNumero(totales.totalNegativo)}</span>
                </div>
            </div>
        `;
        container.classList.remove('d-none');
        container.classList.add('actualizado');
        setTimeout(() => container.classList.remove('actualizado'), 500);
    }

    static crearContainerResumenSuperior(solapa) {
        const tabPane = document.getElementById(solapa);
        if (!tabPane) return;
        const searchContainer = tabPane.querySelector('.search-container');
        if (!searchContainer) return;

        const resumenContainer = document.createElement('div');
        resumenContainer.id = `total-compra-${solapa}-superior`;
        resumenContainer.className = 'bg-light p-2 border-bottom d-none total-compra-container';
        resumenContainer.style.borderLeft = solapa === 'verano' ? '4px solid #ffc107' : '4px solid #0dcaf0';

        searchContainer.parentNode.insertBefore(resumenContainer, searchContainer.nextSibling);
    }

    static agregarFilaTotales(solapa, totales, clavesDinamicas = []) {
        const tbody = document.getElementById(`tbody-${solapa}`);
        if (!tbody) return;

        const filaAnterior = tbody.querySelector('.fila-totales-compra');
        if (filaAnterior) filaAnterior.remove();

        const filaTotales = document.createElement('tr');
        filaTotales.className = 'fila-totales-compra table-warning fw-bold sticky-bottom';
        filaTotales.style.borderTop = '3px solid #333';
        filaTotales.style.boxShadow = '0 -2px 5px rgba(0,0,0,0.1)';
        
        const colorBadge = solapa === 'verano' ? 'warning' : 'info';
        
        // CONSTRUCCIÓN DEL HTML
        let html = `
            <td class="fw-bold text-uppercase bg-warning"><i class="fas fa-calculator me-2"></i>TOTALES</td>
            <td class="text-center bg-warning"><span class="badge bg-dark text-white">${totales.totalRegistros} ITEMS</span></td>
            
            <td class="text-end text-primary fw-bold" style="font-size:1.1em">${TotalesCompra.formatearNumero(totales.totalStockProyectado)}</td>
            
            <td class="text-center text-muted">-</td>
            <td class="text-center text-muted">-</td>
            
            <td class="text-end text-primary fw-bold" style="font-size:1.1em">${TotalesCompra.formatearNumero(totales.totalVentaVeranoAnt)}</td>
            <td class="text-end text-primary fw-bold" style="font-size:1.1em">${TotalesCompra.formatearNumero(totales.totalVentaVeranoProy)}</td>
            
            <td class="text-center text-muted">-</td>
            
            <td class="text-end text-primary fw-bold" style="font-size:1.1em">${TotalesCompra.formatearNumero(totales.totalVentaInviernoAnt)}</td>
            <td class="text-end text-primary fw-bold" style="font-size:1.1em">${TotalesCompra.formatearNumero(totales.totalVentaInviernoProy)}</td>
            
            <td class="text-end ${totales.totalCompraProyectada < 0 ? 'text-danger' : 'text-success'} fw-bold" style="font-size: 1.2em; background-color: rgba(255,255,255,0.5);">
                ${TotalesCompra.formatearNumero(totales.totalCompraProyectada)}
            </td>
        `;

        // Agregar columnas dinámicas (Años)
        clavesDinamicas.forEach(key => {
            const valor = totales.dinamicos[key];
            html += `<td class="text-end text-secondary">${TotalesCompra.formatearNumero(valor)}</td>`;
        });

        filaTotales.innerHTML = html;
        tbody.appendChild(filaTotales);
    }

    static ocultarTotales(solapa) {
        const containerSuperior = document.getElementById(`total-compra-${solapa}-superior`);
        if (containerSuperior) containerSuperior.classList.add('d-none');
        
        const filaTotal = document.querySelector(`#tbody-${solapa} .fila-totales-compra`);
        if (filaTotal) filaTotal.remove();
    }

    static formatearNumero(numero) {
        if (numero === undefined || numero === null || isNaN(numero)) return '0';
        if (typeof FormatoUtils !== 'undefined' && FormatoUtils.formatearNumero) {
            return FormatoUtils.formatearNumero(numero);
        }
        return new Intl.NumberFormat('es-AR').format(Math.round(numero));
    }
}

// Hacer disponible globalmente
window.TotalesCompra = TotalesCompra;

// Funciones globales de compatibilidad
window.actualizarTotalesCompra = (solapa, datos) => TotalesCompra.actualizarDatos(solapa, datos);
window.aplicarFiltrosTotales = (solapa, datos) => TotalesCompra.aplicarFiltros(solapa, datos);
window.onIndiceActualizado = (solapa, rubro, cat, indice) => TotalesCompra.onIndiceActualizado(solapa, rubro, cat, indice);