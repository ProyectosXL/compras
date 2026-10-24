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

            // Tramos de la solapa, para sumar la compra de cada uno. Mismo origen que
            // usan el encabezado y las filas, así que la fila de totales no puede
            // quedar corrida respecto de las columnas que suma.
            const tramos = TablaRendererUtils.tramosDeSolapa(datos);

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
                dinamicos: {},
                // Ventas de 60 días para el índice general de cada universo. `hay`
                // distingue "suma 0" de "la base no trae el dato" (Uruguay, o antes
                // del script 06): lo primero es un índice indefinido, lo segundo
                // directamente no existe.
                ventas60: { lp: 0, lpAnt: 0, te: 0, teAnt: 0, hayLp: false, hayTe: false },
                // Indexado por `orden` y no por código de temporada: el orden es la
                // posición de la columna, y es lo que mantiene alineada la fila de
                // totales aunque dos tramos compartieran código.
                tramos: {}
            };

            clavesDinamicas.forEach(k => acumuladorInicial.dinamicos[k] = 0);
            tramos.forEach(t => acumuladorInicial.tramos[t.orden] = 0);

            // 3. REDUCE PARA SUMAR TODO
            const totales = datos.reduce((acc, item) => {
                acc.totalRegistros++;

                acc.totalStockProyectado += parseFloat(item.STOCK_PROYECTADO || 0);
                acc.totalCompraProyectada += TotalesCompra.obtenerCompraProyectada(item);
                acc.totalVentaVeranoAnt += TotalesCompra.obtenerValorVentaAnterior(item, 'VERANO');
                acc.totalVentaInviernoAnt += TotalesCompra.obtenerValorVentaAnterior(item, 'INVIERNO');
                acc.totalVentaVeranoProy += TotalesCompra.obtenerValorProyeccion(item, 'VERANO');
                acc.totalVentaInviernoProy += TotalesCompra.obtenerValorProyeccion(item, 'INVIERNO');

                if (item.VTA_60D_LP !== null && item.VTA_60D_LP !== undefined) {
                    acc.ventas60.hayLp = true;
                    acc.ventas60.lp += parseFloat(item.VTA_60D_LP || 0);
                    acc.ventas60.lpAnt += parseFloat(item.VTA_60D_ANT_LP || 0);
                }
                if (item.VTA_60D_TE !== null && item.VTA_60D_TE !== undefined) {
                    acc.ventas60.hayTe = true;
                    acc.ventas60.te += parseFloat(item.VTA_60D_TE || 0);
                    acc.ventas60.teAnt += parseFloat(item.VTA_60D_ANT_TE || 0);
                }

                // Sumar Dinámicos
                clavesDinamicas.forEach(k => {
                    acc.dinamicos[k] += parseFloat(item[k] || 0);
                });

                (item.TRAMOS || []).forEach(t => {
                    if (acc.tramos[t.orden] !== undefined) {
                        acc.tramos[t.orden] += parseFloat(t.compra || 0);
                    }
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
            TotalesCompra.mostrarResumenSuperior(solapa, totales, tramos);
            TotalesCompra.agregarFilaTotales(solapa, totales, clavesDinamicas, tramos);
            
        } catch (error) {
            console.error('Error calculando totales:', error);
            TotalesCompra.ocultarTotales(solapa);
        }
    }

    /**
     * Índice general de un universo sobre las filas sumadas: SUM(venta 60 días) /
     * SUM(venta mismos 60 días del año anterior).
     *
     * Es ponderado por venta, no un promedio de índices, y sale de la venta real: no
     * cambia al editar el índice de una fila. Por eso sirve de referencia contra la
     * cual comparar lo que se editó.
     *
     * @param {string} tipo 'lp' o 'te'
     * @returns {{valor: number|null, texto: string, ayuda: string}}
     */
    static indiceGeneral(totales, tipo) {
        const v = totales.ventas60;
        const nombre = tipo === 'te' ? 'Total Empresa' : 'Locales Propios';
        const universo = tipo === 'te' ? 'Locales Propios + Central' : 'solo Locales Propios';
        const hay = tipo === 'te' ? v.hayTe : v.hayLp;
        const actual = tipo === 'te' ? v.te : v.lp;
        const anterior = tipo === 'te' ? v.teAnt : v.lpAnt;

        if (!hay) {
            return {
                valor: null, texto: '—',
                ayuda: `Índice general ${nombre}: esta base no trae las ventas de 60 días con las que se `
                     + 'calcula (Uruguay, o falta correr el script 06).'
            };
        }

        const formula = `Índice general ${nombre} (${universo}), sobre las filas visibles:\n`
            + `venta últimos 60 días / venta mismos 60 días del año anterior\n`
            + `= ${TotalesCompra.formatearNumero(actual)} / ${TotalesCompra.formatearNumero(anterior)}\n`
            + 'Ponderado por venta, no es un promedio de índices. No cambia al editar índices.';

        if (anterior === 0) {
            return { valor: null, texto: '—', ayuda: formula + '\nSin venta el año anterior: no se puede calcular.' };
        }

        const valor = actual / anterior;
        return { valor, texto: valor.toFixed(2), ayuda: formula };
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

    static mostrarResumenSuperior(solapa, totales, tramos = []) {
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
            // Un solo ítem con los dos índices generales, para no sumar dos casilleros
            // a una barra que ya es ancha. Solo si la base trae las ventas de 60 días.
            (totales.ventas60.hayLp || totales.ventas60.hayTe)
                ? { label: 'Índice general',
                    valor: `LP ${TotalesCompra.indiceGeneral(totales, 'lp').texto} · TE ${TotalesCompra.indiceGeneral(totales, 'te').texto}`,
                    ayuda: TotalesCompra.indiceGeneral(totales, 'lp').ayuda + '\n\n'
                         + TotalesCompra.indiceGeneral(totales, 'te').ayuda }
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
                   + 'al pie de la tabla.' },

            // La apertura por tramo, al lado del total. "Unidades a comprar" es la suma
            // de TODOS los tramos, incluido el que ya no se puede comprar, así que sin
            // esta apertura el número de arriba no se puede repartir entre contenedores.
            ...tramos.map(t => {
                const valor = TotalesCompra.formatearNumero(totales.tramos[t.orden] || 0);
                const nombre = (t.es_resto ? 'Resto ' : '') + t.temporada_codigo;

                if (!t.es_comprable) {
                    return { label: 'Sin cubrir ' + nombre, valor: valor, tono: 'alerta',
                             ayuda: 'Venta de la temporada en curso que el stock no alcanza a cubrir. '
                                  + 'Está dentro de "Unidades a comprar" pero NO es mercadería a comprar: '
                                  + 'un contenedor nuevo ya no llega a tiempo.' };
                }
                return { label: (t.es_objetivo ? '★ ' : '') + 'Compra ' + nombre, valor: valor,
                         ayuda: t.es_objetivo
                            ? 'Temporada objetivo de esta solapa: es lo que esta versión le aporta al cashflow.'
                            : 'Tramo intermedio. Queda como control: esa temporada la aporta su propia versión oficial.' };
            })
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

    /**
     * Fila TOTALES al pie de la tabla.
     *
     * Se arma recorriendo los encabezados (data-col) y no con una lista fija de
     * celdas. La lista fija tenía que coincidir a mano, posición por posición, con
     * las columnas de la tabla: cada columna nueva corría la fila entera y dejaba
     * los totales debajo de otro encabezado. Así, una columna sin total muestra "-"
     * y la fila no puede quedar corrida.
     */
    static agregarFilaTotales(solapa, totales, clavesDinamicas = [], tramos = []) {
        const tbody = document.getElementById(`tbody-${solapa}`);
        const tabla = document.getElementById(`tabla-${solapa}`);
        if (!tbody || !tabla) return;

        const filaAnterior = tbody.querySelector('.fila-totales-compra');
        if (filaAnterior) filaAnterior.remove();

        const filaTotales = document.createElement('tr');
        filaTotales.className = 'fila-totales-compra table-warning fw-bold sticky-bottom';
        filaTotales.style.borderTop = '3px solid #333';
        filaTotales.style.boxShadow = '0 -2px 5px rgba(0,0,0,0.1)';

        const numero = (valor, extra = '') =>
            `<td class="text-end text-primary fw-bold" style="font-size:1.1em"${extra}>${TotalesCompra.formatearNumero(valor)}</td>`;

        const celdas = {
            'rubro': '<td class="fw-bold text-uppercase bg-warning"><i class="fas fa-calculator me-2"></i>TOTALES</td>',
            'categoria': `<td class="text-center bg-warning"><span class="badge bg-dark text-white">${totales.totalRegistros} ITEMS</span></td>`,
            'stock-proyectado': numero(totales.totalStockProyectado),
            'venta-ver-ant': numero(totales.totalVentaVeranoAnt),
            'venta-proy-ver': numero(totales.totalVentaVeranoProy),
            'venta-inv-ant': numero(totales.totalVentaInviernoAnt),
            'venta-proy-inv': numero(totales.totalVentaInviernoProy),
            'compra-proyectada': `<td class="text-end ${totales.totalCompraProyectada < 0 ? 'text-danger' : 'text-success'} fw-bold" style="font-size: 1.2em; background-color: rgba(255,255,255,0.5);">
                ${TotalesCompra.formatearNumero(totales.totalCompraProyectada)}
            </td>`
        };

        // Índice general debajo de cada columna de índice: el LP bajo las LP y el TE
        // bajo las TE. Es el mismo número para verano e invierno porque el SP da un
        // solo índice por fila.
        ['lp', 'te'].forEach(tipo => {
            const general = TotalesCompra.indiceGeneral(totales, tipo);
            const td = `<td class="text-center fw-bold ${general.valor === null ? 'text-muted' : 'text-dark'}"`
                     + ` title="${general.ayuda.replace(/"/g, '&quot;')}">`
                     + `<span class="d-block small fw-normal text-muted">General</span>${general.texto}</td>`;
            celdas[`indice-ver-${tipo}`] = td;
            celdas[`indice-inv-${tipo}`] = td;
        });

        // Compra por tramo. El tramo no comprable se muestra en rojo: no es parte de
        // lo que hay que comprar, así que sumarlo con los otros daría un total que no existe.
        tramos.forEach(tramo => {
            const valor = totales.tramos[tramo.orden] || 0;
            const clase = !tramo.es_comprable
                ? 'text-danger'
                : (tramo.es_objetivo ? 'text-success' : 'text-secondary');
            celdas['tramo-' + tramo.orden] = `<td class="text-end ${clase} fw-bold" data-tramo-orden="${tramo.orden}">`
                + `${TotalesCompra.formatearNumero(valor)}</td>`;
        });

        // Columnas dinámicas (años históricos)
        clavesDinamicas.forEach(key => {
            celdas['hist-' + key] = `<td class="text-end text-secondary">${TotalesCompra.formatearNumero(totales.dinamicos[key])}</td>`;
        });

        const encabezados = Array.from(tabla.querySelectorAll('thead tr:last-child th'));
        filaTotales.innerHTML = encabezados
            .map(th => celdas[th.dataset.col] || '<td class="text-center text-muted">-</td>')
            .join('');

        // Cada celda lleva el data-col de su columna, como las filas de datos, para
        // que ocultar una columna oculte también su total.
        Array.from(filaTotales.children).forEach((td, i) => {
            if (encabezados[i] && encabezados[i].dataset.col) td.dataset.col = encabezados[i].dataset.col;
        });

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