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

            // 1. COLUMNAS DINÁMICAS (historial de temporadas anteriores)
            //
            // Se piden a la MISMA función que usan el encabezado y las filas de datos.
            // Antes se deducían acá con una lista negra propia, así que cada columna
            // numérica nueva del registro se colaba como si fuera una temporada: la
            // fila de totales terminaba con más celdas que columnas y todo el bloque
            // del historial quedaba corrido, mostrando los componentes del stock bajo
            // los encabezados de los años.
            const clavesDinamicas = TablaRendererUtils.extraerColumnasVentasHistoricas(datos);

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
     * Venta anterior de una fila: la misma que muestra la columna de arriba.
     *
     * Se delega en TablaRendererUtils para que el total sea la suma exacta de lo
     * que se ve. Acá había una cuarta búsqueda propia, con el reloj del navegador
     * y una lista de claves que no incluía la que manda el servidor: en los rubros
     * cuya última venta es de una temporada más vieja sumaba 0 mientras la columna
     * mostraba otro número.
     */
    static obtenerValorVentaAnterior(item, temporada) {
        return TablaRendererUtils.buscarVentaHistoricaCorrecta(item, temporada);
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

        const neto = TotalesCompra.formatearNumero(totales.totalCompraProyectada);
        const periodos = (typeof TemporadaServidor !== 'undefined')
            ? TemporadaServidor.periodos(solapa) : null;
        const objetivo = periodos && periodos.objetivo ? periodos.objetivo.codigo : null;

        container.innerHTML = UIUtils.resumenSuperior([
            { label: 'Rubro / categoría', valor: totales.totalRegistros },
            objetivo
                ? { label: 'Temporada objetivo', valor: objetivo,
                    ayuda: 'Temporada que esta compra tiene que cubrir: '
                         + periodos.objetivo.desde + ' a ' + periodos.objetivo.hasta }
                : null,
            { label: 'Con faltante', valor: totales.itemsNegativos, tono: 'alerta',
              ayuda: 'Rubro/categoría cuya compra proyectada da negativa, es decir que '
                   + 'el stock proyectado no alcanza a cubrir la venta proyectada.' },
            // Se aclara que NO compensa con los excedentes, y se muestra el neto al
            // lado: los dos números se parecen y antes convivían sin explicación,
            // uno acá arriba y el otro en la fila de totales de la tabla.
            { label: 'Unidades a comprar', valor: TotalesCompra.formatearNumero(totales.totalNegativo),
              tono: 'alerta', fin: true,
              ayuda: 'Suma de los faltantes únicamente. No se compensa con los rubros '
                   + 'que tienen excedente: el neto de la columna Compra Proyectada es ' + neto + '.' },
            { label: 'Neto de la columna', valor: neto,
              ayuda: 'Faltantes menos excedentes. Es el total que muestra la fila TOTALES '
                   + 'al pie de la tabla.' }
        ].filter(Boolean));

        container.classList.remove('d-none');
        // La barra acaba de aparecer: la tabla de abajo tiene menos alto disponible.
        if (window.ajustarAltura) window.ajustarAltura();

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
        // El acento de color lo pone la clase modificadora, no un style inline:
        // así todas las solapas comparten el mismo componente.
        resumenContainer.className = `resumen-superior resumen-superior--${solapa} d-none`;

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