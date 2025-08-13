
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
            console.error('Solapa no válida:', solapa);
            return;
        }

        TotalesCompra.datos[solapa] = Array.isArray(datos) ? datos : [];
        TotalesCompra.datosFiltrados[solapa] = [...TotalesCompra.datos[solapa]];
        
        console.log(`Datos ${solapa} actualizados:`, TotalesCompra.datos[solapa].length, 'registros');
        
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
     * Calcular totales de compra proyectada (MODIFICADA)
     */
    static calcularTotales(solapa) {
        try {
            const datos = TotalesCompra.datosFiltrados[solapa] || [];
            
            console.log(`📊 Calculando totales para ${solapa}:`, datos.length, 'registros');
            
            if (datos.length === 0) {
                TotalesCompra.ocultarTotales(solapa);
                return;
            }

            const totales = datos.reduce((acc, item) => {
                const compraProyectada = TotalesCompra.obtenerCompraProyectada(item);
                const stockProyectado = parseFloat(item.STOCK_PROYECTADO || 0);
                const ventaVeranoAnt = TotalesCompra.obtenerVentaAnterior(item, 'VERANO');
                const ventaInviernoAnt = TotalesCompra.obtenerVentaAnterior(item, 'INVIERNO');
                const ventaVeranoProy = parseFloat(item.VENTA_PROY_VERANO || 0);
                const ventaInviernoProy = parseFloat(item.VENTA_PROY_INVIERNO || 0);

                acc.totalRegistros++;
                acc.totalStockProyectado += stockProyectado;
                acc.totalVentaVeranoAnt += ventaVeranoAnt;
                acc.totalVentaInviernoAnt += ventaInviernoAnt;
                acc.totalVentaVeranoProy += ventaVeranoProy;
                acc.totalVentaInviernoProy += ventaInviernoProy;
                acc.totalCompraProyectada += compraProyectada;

                if (compraProyectada < 0) {
                    acc.totalNegativo += Math.abs(compraProyectada);
                    acc.itemsNegativos++;
                } else if (compraProyectada > 0) {
                    acc.itemsPositivos++;
                }

                return acc;
            }, {
                totalRegistros: 0,
                totalStockProyectado: 0,
                totalVentaVeranoAnt: 0,
                totalVentaInviernoAnt: 0,
                totalVentaVeranoProy: 0,
                totalVentaInviernoProy: 0,
                totalCompraProyectada: 0,
                totalNegativo: 0,
                itemsNegativos: 0,
                itemsPositivos: 0
            });

            console.log(`🎯 ${solapa} - Totales calculados:`, totales);
            
            // Mostrar resumen superior
            TotalesCompra.mostrarResumenSuperior(solapa, totales);
            
            // Agregar fila de totales
            TotalesCompra.agregarFilaTotales(solapa, totales);
            
        } catch (error) {
            console.error('Error calculando totales:', error);
            TotalesCompra.ocultarTotales(solapa);
        }
    }

    /**
     * Obtener el valor de compra proyectada de un item (MEJORADA)
     */
    static obtenerCompraProyectada(item) {
        // Buscar diferentes posibles nombres de columna
        const posiblesCampos = [
            'COMPRA_PROYECTADA',
            'Compra Proyectada',
            'compra_proyectada',
            'COMPRA_PROY',
            'COMPRA'
        ];

        for (const campo of posiblesCampos) {
            if (item.hasOwnProperty(campo)) {
                const valor = parseFloat(item[campo]) || 0;
                console.log(`Campo encontrado: ${campo} = ${valor}`);
                return valor;
            }
        }

        // Si no encuentra el campo específico, buscar el último campo numérico
        const keys = Object.keys(item);
        
        // Buscar desde el final hacia atrás
        for (let i = keys.length - 1; i >= 0; i--) {
            const key = keys[i];
            const valor = item[key];
            
            // Verificar si es numérico y no es un campo de identificación
            if (!isNaN(parseFloat(valor)) && 
                !key.includes('INDICE') && 
                !key.includes('STOCK') &&
                !key.includes('VENTA') &&
                !key.includes('VTA') &&
                key !== 'CATEGORIA' &&
                key !== 'RUBRO') {
                
                console.log(`Último campo numérico encontrado: ${key} = ${valor}`);
                return parseFloat(valor) || 0;
            }
        }

        console.warn('No se pudo encontrar campo de compra proyectada en:', Object.keys(item));
        return 0;
    }

    /**
     * Mostrar el total en la interfaz
     */
    static mostrarTotal(solapa, total, cantidad) {
        const containerId = `total-compra-${solapa}`;
        let container = document.getElementById(containerId);

        // Crear el contenedor si no existe
        if (!container) {
            TotalesCompra.crearContainerTotal(solapa);
            container = document.getElementById(containerId);
        }

        if (!container) {
            console.warn('No se pudo crear el contenedor de totales para:', solapa);
            return;
        }

        // Formatear el total
        const totalFormateado = TotalesCompra.formatearNumero(total);
        const icono = solapa === 'verano' ? 'fa-sun' : 'fa-snowflake';
        const color = solapa === 'verano' ? 'warning' : 'info';

        // Actualizar contenido
        container.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas ${icono} me-2"></i>
                    <strong>Total Compra Proyectada:</strong>
                </div>
                <div>
                    <span class="badge bg-${color} fs-6 me-2">${cantidad} items</span>
                    <span class="badge bg-danger fs-5">${totalFormateado}</span>
                </div>
            </div>
        `;

        // Mostrar el contenedor
        container.classList.remove('d-none');
        
        // Animación de actualización
        container.classList.add('actualizado');
        setTimeout(() => container.classList.remove('actualizado'), 500);
    }

    /**
     * Crear el contenedor de totales en la interfaz
     */
    static crearContainerTotal(solapa) {
        const tabPane = document.getElementById(solapa);
        if (!tabPane) {
            console.error('No se encontró la solapa:', solapa);
            return;
        }

        // Buscar el search-container para insertar después
        const searchContainer = tabPane.querySelector('.search-container');
        if (!searchContainer) {
            console.error('No se encontró search-container en:', solapa);
            return;
        }

        // Crear el contenedor de totales
        const totalContainer = document.createElement('div');
        totalContainer.id = `total-compra-${solapa}`;
        totalContainer.className = 'bg-light p-2 border-bottom d-none total-compra-container';
        totalContainer.style.borderLeft = '4px solid #dc3545';

        // Insertar después del search-container
        searchContainer.parentNode.insertBefore(totalContainer, searchContainer.nextSibling);
        
        console.log('Contenedor de totales creado para:', solapa);
    }

    /**
     * Formatear números para mostrar
     */
    static formatearNumero(numero) {
        if (typeof FormatoUtils !== 'undefined' && FormatoUtils.formatearNumero) {
            return FormatoUtils.formatearNumero(numero);
        }
        
        // Fallback si FormatoUtils no está disponible
        return new Intl.NumberFormat('es-AR').format(numero);
    }

    /**
     * Manejar cambios de índice (cuando se edita un índice) - CORREGIDA PARA FILTROS
     */
    static onIndiceActualizado(solapa, rubro, categoria, nuevoIndice) {
        try {
            console.log(`🔄 Índice actualizado en ${solapa}:`, rubro, categoria, nuevoIndice);
            
            // Obtener datos actualizados directamente del PresupuestoApp
            const datosActualizados = window.presupuestoApp?.datos?.[solapa] || [];
            
            if (datosActualizados.length === 0) {
                console.warn('No hay datos disponibles para actualizar totales');
                return;
            }
            
            // CORRECCIÓN: Verificar si hay filtros activos antes de aplicar
            let datosFiltrados = [...datosActualizados];
            
            // Verificar filtros persistentes primero
            if (typeof FiltrosManager !== 'undefined') {
                const estadoFiltros = FiltrosManager.obtenerEstadoFiltros();
                if (estadoFiltros.activos) {
                    // Aplicar filtros persistentes
                    datosFiltrados = FiltrosManager.filtrarDatos(datosActualizados);
                    console.log(`📊 Filtros persistentes aplicados: ${datosActualizados.length} -> ${datosFiltrados.length} registros`);
                }
            } else {
                // Fallback: verificar filtros individuales en los selectores
                const selectRubro = document.getElementById(`filtro-rubro-${solapa}`);
                const selectCategoria = document.getElementById(`filtro-categoria-${solapa}`);
                const inputBusqueda = document.getElementById(`search-${solapa}`);
                
                // Aplicar filtro de rubro
                if (selectRubro && selectRubro.value.trim().length > 0) {
                    const rubroFiltro = selectRubro.value.trim();
                    datosFiltrados = datosFiltrados.filter(item => item.RUBRO === rubroFiltro);
                    console.log(`📊 Filtro rubro aplicado (${rubroFiltro}): ${datosFiltrados.length} registros`);
                }
                
                // Aplicar filtro de categoría
                if (selectCategoria && selectCategoria.value.trim().length > 0) {
                    const categoriaFiltro = selectCategoria.value.trim();
                    datosFiltrados = datosFiltrados.filter(item => 
                        item.CATEGORIA_PADRE === categoriaFiltro || item.CATEGORIA === categoriaFiltro
                    );
                    console.log(`📊 Filtro categoría aplicado (${categoriaFiltro}): ${datosFiltrados.length} registros`);
                }
                
                // Aplicar filtro de búsqueda
                if (inputBusqueda && inputBusqueda.value.trim().length > 0) {
                    const termino = inputBusqueda.value.trim().toLowerCase();
                    datosFiltrados = datosFiltrados.filter(item => {
                        return TotalesCompra.cumpleFiltro(item, termino);
                    });
                    console.log(`📊 Filtro búsqueda aplicado (${termino}): ${datosFiltrados.length} registros`);
                }
            }
            
            // Actualizar datos filtrados con los valores aplicando los filtros actuales
            TotalesCompra.datosFiltrados[solapa] = datosFiltrados;
            
            // Recalcular totales solo con los datos filtrados
            TotalesCompra.calcularTotales(solapa);
            
            // Mostrar notificación
            if (typeof UIUtils !== 'undefined') {
                const mensaje = datosFiltrados.length < datosActualizados.length 
                    ? `Total actualizado (${datosFiltrados.length} registros filtrados)`
                    : 'Total actualizado por cambio de índice';
                UIUtils.mostrarAlerta(mensaje, 'info', 2000);
            }
            
        } catch (error) {
            console.error('Error manejando actualización de índice:', error);
        }
}

    /**
     * Obtener el valor original de compra (antes del índice)
     */
    static obtenerCompraOriginal(item) {
        // Intentar obtener el valor base sin índice aplicado
        const posiblesCampos = [
            'COMPRA_BASE',
            'COMPRA_ORIGINAL',
            'VENTA_PROYECTADA', // A veces la compra se basa en la venta proyectada
            'STOCK_OBJETIVO'
        ];

        for (const campo of posiblesCampos) {
            if (item.hasOwnProperty(campo)) {
                return parseFloat(item[campo]) || 0;
            }
        }

        // Si no hay campo base, usar el actual dividido por el índice
        const compraActual = TotalesCompra.obtenerCompraProyectada(item);
        const indice = parseFloat(item.INDICE_VARIACION || item.INDICE || 1);
        
        return compraActual / indice;
    }

    /**
     * Actualizar el valor de compra proyectada en un item
     */
    static actualizarCompraProyectada(item, nuevoValor) {
        const posiblesCampos = [
            'COMPRA_PROYECTADA',
            'Compra Proyectada',
            'compra_proyectada'
        ];

        // Intentar actualizar en el campo conocido
        for (const campo of posiblesCampos) {
            if (item.hasOwnProperty(campo)) {
                item[campo] = nuevoValor;
                return;
            }
        }

        // Si no existe, crear el campo
        item['COMPRA_PROYECTADA'] = nuevoValor;
    }

    /**
     * Limpiar totales (MODIFICADA)
     */
    static limpiarTotales(solapa = null) {
        const solapas = solapa ? [solapa] : ['verano', 'invierno'];
        
        solapas.forEach(s => {
            // Limpiar contenedor antiguo
            const container = document.getElementById(`total-compra-${s}`);
            if (container) {
                container.classList.add('d-none');
                container.innerHTML = '';
            }
            
            // Limpiar nuevo contenedor superior
            const containerSuperior = document.getElementById(`total-compra-${s}-superior`);
            if (containerSuperior) {
                containerSuperior.classList.add('d-none');
                containerSuperior.innerHTML = '';
            }
            
            // Limpiar fila de totales
            const filaTotal = document.querySelector(`#tbody-${s} .fila-totales-compra`);
            if (filaTotal) {
                filaTotal.remove();
            }
            
            TotalesCompra.datos[s] = [];
            TotalesCompra.datosFiltrados[s] = [];
        });
    }

    /**
     * Obtener estadísticas de totales
     */
    static obtenerEstadisticas(solapa) {
        const datos = TotalesCompra.datosFiltrados[solapa] || [];
        
        let totalPositivo = 0;
        let totalNegativo = 0;
        let itemsPositivos = 0;
        let itemsNegativos = 0;

        datos.forEach(item => {
            const compra = TotalesCompra.obtenerCompraProyectada(item);
            if (compra > 0) {
                totalPositivo += compra;
                itemsPositivos++;
            } else if (compra < 0) {
                totalNegativo += Math.abs(compra);
                itemsNegativos++;
            }
        });

        return {
            totalPositivo,
            totalNegativo,
            itemsPositivos,
            itemsNegativos,
            totalItems: datos.length
        };
    }

    /**
     * NUEVA: Verificar si un item cumple con el filtro de búsqueda
     */
    static cumpleFiltro(item, termino) {
        const campos = ['RUBRO', 'CATEGORIA', 'CATEGORIA_PADRE', 'DESCRIPCION'];
        
        return campos.some(campo => {
            const valor = item[campo];
            return valor && valor.toString().toLowerCase().includes(termino);
        });
    }

    /**
     * Debug y diagnóstico
     */
    static debug(solapa = null) {
        const solapas = solapa ? [solapa] : ['verano', 'invierno'];
        
        console.log('=== DEBUG TOTALES COMPRA ===');
        
        solapas.forEach(s => {
            console.log(`\n--- ${s.toUpperCase()} ---`);
            console.log('Datos totales:', TotalesCompra.datos[s]?.length || 0);
            console.log('Datos filtrados:', TotalesCompra.datosFiltrados[s]?.length || 0);
            
            if (TotalesCompra.datosFiltrados[s]?.length > 0) {
                const primer = TotalesCompra.datosFiltrados[s][0];
                console.log('Primer item:', primer);
                console.log('Compra proyectada:', TotalesCompra.obtenerCompraProyectada(primer));
                console.log('Campos disponibles:', Object.keys(primer));
            }
            
            const stats = TotalesCompra.obtenerEstadisticas(s);
            console.log('Estadísticas:', stats);
        });
    }

    /**
     * Debug mejorado para ver estructura de datos
     */
    static debugDetallado(solapa = 'verano') {
        console.group(`🔍 DEBUG DETALLADO - ${solapa.toUpperCase()}`);
        
        const datos = TotalesCompra.datosFiltrados[solapa] || [];
        
        console.log('Total registros:', datos.length);
        
        if (datos.length > 0) {
            const primer = datos[0];
            console.log('Estructura del primer registro:');
            console.table(primer);
            
            console.log('Campos disponibles:', Object.keys(primer));
            
            // Buscar campos numéricos
            const camposNumericos = Object.keys(primer).filter(key => {
                const valor = primer[key];
                return !isNaN(parseFloat(valor)) && isFinite(valor);
            });
            
            console.log('Campos numéricos encontrados:', camposNumericos);
            
            // Probar detectar compra proyectada en varios registros
            console.log('🔍 Detección de compra proyectada:');
            datos.slice(0, 5).forEach((item, index) => {
                const compra = TotalesCompra.obtenerCompraProyectada(item);
                console.log(`Item ${index}: ${item.RUBRO} -> ${compra}`);
            });
        }
        
        console.groupEnd();
    }

    /**
     * Mostrar resumen en la parte superior (NUEVA FUNCIÓN)
     */
    static mostrarResumenSuperior(solapa, totales) {
        const containerId = `total-compra-${solapa}-superior`;
        let container = document.getElementById(containerId);

        // Crear el contenedor si no existe
        if (!container) {
            TotalesCompra.crearContainerResumenSuperior(solapa);
            container = document.getElementById(containerId);
        }

        if (!container) {
            console.warn('No se pudo crear el contenedor de resumen superior para:', solapa);
            return;
        }

        const icono = solapa === 'verano' ? 'fa-sun' : 'fa-snowflake';
        const colorPrimario = solapa === 'verano' ? 'warning' : 'info';

        // Actualizar contenido
        container.innerHTML = `
            <div class="row text-center">
                <div class="col-4">
                    <small class="text-muted d-block">
                        <i class="fas ${icono} me-1"></i>
                        Total Registros
                    </small>
                    <span class="badge bg-${colorPrimario} fs-6">${totales.totalRegistros}</span>
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

        // Mostrar el contenedor
        container.classList.remove('d-none');
        
        // Animación de actualización
        container.classList.add('actualizado');
        setTimeout(() => container.classList.remove('actualizado'), 500);
    }

    /**
     * Crear contenedor de resumen superior (NUEVA FUNCIÓN)
     */
    static crearContainerResumenSuperior(solapa) {
        const tabPane = document.getElementById(solapa);
        if (!tabPane) {
            console.error('No se encontró la solapa:', solapa);
            return;
        }

        // Buscar el search-container para insertar después
        const searchContainer = tabPane.querySelector('.search-container');
        if (!searchContainer) {
            console.error('No se encontró search-container en:', solapa);
            return;
        }

        // Crear el contenedor de resumen
        const resumenContainer = document.createElement('div');
        resumenContainer.id = `total-compra-${solapa}-superior`;
        resumenContainer.className = 'bg-light p-2 border-bottom d-none total-compra-container';
        resumenContainer.style.borderLeft = solapa === 'verano' ? '4px solid #ffc107' : '4px solid #0dcaf0';

        // Insertar después del search-container
        searchContainer.parentNode.insertBefore(resumenContainer, searchContainer.nextSibling);
        
        console.log('Contenedor de resumen superior creado para:', solapa);
    }

    /**
     * Agregar fila de totales al final de la tabla (NUEVA FUNCIÓN)
     */
    static agregarFilaTotales(solapa, totales) {
        const tbody = document.getElementById(`tbody-${solapa}`);
        if (!tbody) return;

        // Remover fila de totales anterior si existe
        const filaAnterior = tbody.querySelector('.fila-totales-compra');
        if (filaAnterior) {
            filaAnterior.remove();
        }

        // Crear nueva fila de totales
        const filaTotales = document.createElement('tr');
        filaTotales.className = 'fila-totales-compra table-warning fw-bold';
        filaTotales.style.borderTop = '3px solid #ffc107';
        
        const colorBadge = solapa === 'verano' ? 'warning' : 'info';
        
        filaTotales.innerHTML = `
            <td class="fw-bold text-uppercase">
                <i class="fas ${solapa === 'verano' ? 'fa-sun' : 'fa-snowflake'} me-2"></i>
                TOTALES
            </td>
            <td class="text-center">
                <span class="badge bg-${colorBadge}">${totales.totalRegistros} items</span>
            </td>
            <td class="text-end bg-info-subtle fw-bold" title="Total stock proyectado">
                ${TotalesCompra.formatearNumero(totales.totalStockProyectado)}
            </td>
            <td class="text-center">-</td>
            <td class="text-center">-</td>
            <td class="text-end bg-info-subtle fw-bold" title="Total ventas anteriores verano">
                ${TotalesCompra.formatearNumero(totales.totalVentaVeranoAnt)}
            </td>
            <td class="text-end bg-primary-subtle fw-bold" title="Total ventas proyectadas verano">
                ${TotalesCompra.formatearNumero(totales.totalVentaVeranoProy)}
            </td>
            <td class="text-center">-</td>
            <td class="text-end bg-info-subtle fw-bold" title="Total ventas anteriores invierno">
                ${TotalesCompra.formatearNumero(totales.totalVentaInviernoAnt)}
            </td>
            <td class="text-end bg-primary-subtle fw-bold" title="Total ventas proyectadas invierno">
                ${TotalesCompra.formatearNumero(totales.totalVentaInviernoProy)}
            </td>
            <td class="text-end ${totales.totalCompraProyectada < 0 ? 'bg-danger-subtle text-danger-emphasis' : 'bg-success-subtle text-success-emphasis'} fw-bold" title="Total compra proyectada">
                ${TotalesCompra.formatearNumero(totales.totalCompraProyectada)}
            </td>
        `;

        tbody.appendChild(filaTotales);
    }

    /**
     * Obtener venta anterior por temporada (NUEVA FUNCIÓN)
     */
    static obtenerVentaAnterior(item, temporada) {
        const anoActual = new Date().getFullYear() % 100;
        
        if (temporada === 'VERANO') {
            const posiblesColumnas = [
                `VTA_VERANO_${anoActual}`,
                `VTA_VERANO_${anoActual - 1}`,
                'VERANO 24-25',
                'VERANO 23-24'
            ];
            
            for (const columna of posiblesColumnas) {
                if (item[columna] && !isNaN(item[columna]) && parseFloat(item[columna]) > 0) {
                    return parseFloat(item[columna]);
                }
            }
        } else if (temporada === 'INVIERNO') {
            const posiblesColumnas = [
                `VTA_INVIERNO_${anoActual}`,
                `INVIERNO ${anoActual}`,
                `VTA_INVIERNO_${anoActual - 1}`,
                `INVIERNO ${anoActual - 1}`
            ];
            
            for (const columna of posiblesColumnas) {
                if (item[columna] && !isNaN(item[columna]) && parseFloat(item[columna]) > 0) {
                    return parseFloat(item[columna]);
                }
            }
        }
        
        return 0;
    }

    /**
     * Ocultar totales (NUEVA FUNCIÓN)
     */
    static ocultarTotales(solapa) {
        const containerSuperior = document.getElementById(`total-compra-${solapa}-superior`);
        if (containerSuperior) {
            containerSuperior.classList.add('d-none');
        }
        
        const filaTotal = document.querySelector(`#tbody-${solapa} .fila-totales-compra`);
        if (filaTotal) {
            filaTotal.remove();
        }
    }

}

// Hacer disponible globalmente
window.TotalesCompra = TotalesCompra;

// Funciones globales para compatibilidad
window.actualizarTotalesCompra = function(solapa, datos) {
    TotalesCompra.actualizarDatos(solapa, datos);
};

window.aplicarFiltrosTotales = function(solapa, datosFiltrados) {
    TotalesCompra.aplicarFiltros(solapa, datosFiltrados);
};

window.onIndiceActualizado = function(solapa, rubro, categoria, nuevoIndice) {
    TotalesCompra.onIndiceActualizado(solapa, rubro, categoria, nuevoIndice);
};

