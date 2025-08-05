
// TablaRenderer con columna de ÍNDICE ORIGINAL
// Archivo: presupuestos/js/tabla-renderer.js

// ========================================
// FUNCIONES BASE
// ========================================

const TablaRendererUtils = {
    
    mostrarTablaVacia(tbody, colspan, mensaje) {
        tbody.innerHTML = `
            <tr>
                <td colspan="${colspan}" class="text-center text-muted py-4">
                    <i class="fas fa-inbox"></i>
                    ${mensaje}
                </td>
            </tr>
        `;
    },

    escaparComillas(texto) {
        if (!texto) return '';
        return texto.replace(/'/g, "\\'").replace(/"/g, '\\"');
    },

    resetearEstadoTabla(solapa) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        if (tabla) {
            tabla.removeAttribute('data-columnas-procesadas');
            tabla.removeAttribute('data-limpia');
            
            const headersDinamicos = tabla.querySelectorAll('th[data-columna-dinamica]');
            headersDinamicos.forEach(th => th.remove());
            
            const celdasDinamicas = tabla.querySelectorAll('td[data-columna-dinamica]');
            celdasDinamicas.forEach(td => td.remove());
        }
    },

    limpiarTabla(solapa) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        if (!tabla) {
            console.warn(`Tabla ${solapa} no encontrada`);
            return;
        }
        
        const thead = tabla.querySelector('thead tr');
        if (!thead) {
            console.warn(`Header de tabla ${solapa} no encontrado`);
            return;
        }
        
        const columnasDinamicas = thead.querySelectorAll('[data-columna-dinamica]');
        columnasDinamicas.forEach(th => th.remove());
        
        tabla.removeAttribute('data-columnas-procesadas');
        tabla.setAttribute('data-limpia', 'true');
    },

    // CORRECCIÓN en presupuestos/js/tabla-renderer.js
    // Reemplazar la función buscarVentaHistoricaCorrecta en TablaRendererUtils

    buscarVentaHistoricaCorrecta(item, temporada) {
        const anoActual = new Date().getFullYear() % 100; // 2025 -> 25
        
        if (temporada === 'VERANO') {
            // Para verano, buscar el año actual o anterior
            const posiblesColumnas = [
                `VTA_VERANO_${anoActual}`,      // VERANO 25
                `VTA_VERANO_${anoActual - 1}`,  // VERANO 24
                'VERANO 24-25',
                'VERANO 23-24'
            ];
            
            for (const columna of posiblesColumnas) {
                if (item[columna] && !isNaN(item[columna]) && parseFloat(item[columna]) > 0) {
                    console.log(`✅ VERANO encontrado: ${columna} = ${item[columna]}`);
                    return parseFloat(item[columna]);
                }
            }
            
        } else if (temporada === 'INVIERNO') {
            // CORRECCIÓN: Para invierno actual (2025), necesitamos:
            // - INVIERNO 25 (1/2/2025 al 31/7/2025) - TEMPORADA ACTUAL
            // - Si no existe, usar INVIERNO 24 como fallback
            
            const posiblesColumnas = [
                `VTA_INVIERNO_${anoActual}`,      // INVIERNO 25 ← ESTA ES LA CORRECTA
                `INVIERNO ${anoActual}`,          // INVIERNO 25 (formato alternativo)
                `VTA_INVIERNO_${anoActual - 1}`,  // INVIERNO 24 (fallback)
                `INVIERNO ${anoActual - 1}`,      // INVIERNO 24 (fallback formato alternativo)
            ];
            
            for (const columna of posiblesColumnas) {
                if (item[columna] && !isNaN(item[columna]) && parseFloat(item[columna]) > 0) {
                    console.log(`✅ INVIERNO encontrado: ${columna} = ${item[columna]}`);
                    return parseFloat(item[columna]);
                }
            }
            
            // DEBUG: Mostrar todas las columnas disponibles si no encuentra
            const columnasInvierno = Object.keys(item).filter(key => 
                key.toLowerCase().includes('invierno')
            );
            console.warn(`❌ No se encontró venta INVIERNO. Columnas disponibles:`, columnasInvierno);
        }
        
        console.warn(`❌ No se encontró venta ${temporada} anterior para item:`, {
            rubro: item.RUBRO,
            categoria: item.CATEGORIA_PADRE,
            columnas_disponibles: Object.keys(item).filter(key => 
                key.toLowerCase().includes(temporada.toLowerCase())
            )
        });
        
        return 0;
    },

    extraerColumnasVentasHistoricas(datos) {
        if (!datos || datos.length === 0) return [];
        
        const primeraFila = datos[0];
        const columnasExcluidas = [
            'RUBRO', 'CATEGORIA_PADRE', 'CANT_STOCK', 'CANT_STOCK_GUARDAR',
            'CANT_PEND_OC_VERANO', 'CANT_PEND_OC_INVIERNO', 'CANT_PEND_OC_ATEMPORAL',
            'INDICE_VARIACION', 'INDICE_VARIACION_INVIERNO', 'INDICE_ORIGINAL',
            'STOCK_COBERTURA', 'VENTA_PROY_VERANO', 'VENTA_PROY_INVIERNO', 
            'COMPRA_PROYECTADA', 'STOCK_PROYECTADO'
        ];
        
        const columnasVenta = [];
        
        for (const columna of Object.keys(primeraFila)) {
            if (!columnasExcluidas.includes(columna) && 
                (columna.includes('VERANO') || columna.includes('INVIERNO') || columna.includes('VTA_'))) {
                columnasVenta.push(columna);
            }
        }
        
        return columnasVenta.sort();
    },

    formatearNombreColumna(columna) {
        return columna.replace('VTA_', '').replace(/_/g, ' ').trim();
    },

    // NUEVO: Obtener índice original del registro
    obtenerIndiceOriginal(item) {
        // Buscar el índice original en diferentes propiedades
        if (item._indiceOriginal !== undefined) {
            return parseFloat(item._indiceOriginal);
        }
        if (item.INDICE_ORIGINAL !== undefined) {
            return parseFloat(item.INDICE_ORIGINAL);
        }
        // Si no hay índice original guardado, usar el actual como original
        return parseFloat(item.INDICE_VARIACION || 1.0);
    }
};

// ========================================
// CLASE PRINCIPAL TABLARENDERER
// ========================================

class TablaRenderer {
    
    /**
     * Renderizar tabla de Compra Proyectada Verano
     */
    static renderizarTablaVerano(datos, etiquetas = null) {
        const tbody = document.getElementById('tbody-verano');
        
        if (!datos || datos.length === 0) {
            TablaRendererUtils.mostrarTablaVacia(tbody, 10, 'No hay datos disponibles para la proyección de verano');
            return;
        }

        TablaRendererUtils.limpiarTabla('verano');
        TablaRenderer.agregarColumnasVentasHistoricas('verano', datos);
        
        tbody.innerHTML = '';
        
        datos.forEach((item, index) => {
            const tr = TablaRenderer.crearFilaCompraCompleta(item, index, datos, 'verano');
            tbody.appendChild(tr);
        });
        
        if (etiquetas) {
            TablaRenderer.actualizarHeadersEtiquetas('verano', etiquetas);
        }
        
        console.log(`✅ Tabla verano renderizada: ${datos.length} registros`);
    }

    /**
     * Renderizar tabla de Compra Proyectada Invierno
     */
    static renderizarTablaInvierno(datos, etiquetas = null) {
        const tbody = document.getElementById('tbody-invierno');
        
        if (!datos || datos.length === 0) {
            TablaRendererUtils.mostrarTablaVacia(tbody, 10, 'No hay datos disponibles para la proyección de invierno');
            return;
        }

        TablaRendererUtils.limpiarTabla('invierno');
        TablaRenderer.agregarColumnasVentasHistoricas('invierno', datos);
        
        tbody.innerHTML = '';
        
        datos.forEach((item, index) => {
            const tr = TablaRenderer.crearFilaCompraCompleta(item, index, datos, 'invierno');
            tbody.appendChild(tr);
        });
        
        if (etiquetas) {
            TablaRenderer.actualizarHeadersEtiquetas('invierno', etiquetas);
        }
        
        console.log(`✅ Tabla invierno renderizada: ${datos.length} registros`);
    }

    /**
     * Renderizar tabla de Stock Proyectado (sin cambios)
     */
    static renderizarTablaStock(datos) {
        const tbody = document.getElementById('tbody-stock');
        
        if (!datos || datos.length === 0) {
            TablaRendererUtils.mostrarTablaVacia(tbody, 9, 'No hay datos disponibles para el stock proyectado');
            return;
        }

        TablaRendererUtils.limpiarTabla('stock');
        tbody.innerHTML = '';
        
        datos.forEach((item, index) => {
            const tr = TablaRenderer.crearFilaStock(item, index);
            tbody.appendChild(tr);
        });
        
        console.log(`✅ Tabla stock renderizada: ${datos.length} registros`);
    }

    /**
     * Agregar columnas de ventas históricas (solo para compras)
     */
    static agregarColumnasVentasHistoricas(solapa, datos) {
        if (!datos || datos.length === 0) return;
        
        const tabla = document.getElementById(`tabla-${solapa}`);
        const thead = tabla.querySelector('thead tr');
        
        if (tabla.getAttribute('data-columnas-procesadas') === 'true') {
            return;
        }
        
        const columnasVentasHistoricas = TablaRendererUtils.extraerColumnasVentasHistoricas(datos);
        
        if (columnasVentasHistoricas.length === 0) {
            tabla.setAttribute('data-columnas-procesadas', 'true');
            return;
        }
        
        console.log(`Agregando ${columnasVentasHistoricas.length} columnas históricas a ${solapa}`);
        
        columnasVentasHistoricas.forEach(columna => {
            const th = document.createElement('th');
            th.className = 'text-center bg-info-subtle text-dark';
            th.textContent = TablaRendererUtils.formatearNombreColumna(columna);
            th.setAttribute('data-columna-dinamica', 'true');
            th.style.fontSize = '0.7rem';
            th.style.whiteSpace = 'nowrap';
            thead.appendChild(th);
        });
        
        tabla.setAttribute('data-columnas-procesadas', 'true');
    }

    /**
     * CORREGIDO: Crear fila completa para compras con ÍNDICE ORIGINAL
     */
    static crearFilaCompraCompleta(item, index, todosLosDatos, solapa) {
        const tr = document.createElement('tr');
        tr.className = 'fila-datos';
        
        const stockProyectado = FormatoUtils.formatearNumero(item.STOCK_PROYECTADO || 0);
        const indiceOriginal = TablaRendererUtils.obtenerIndiceOriginal(item);
        const indiceVariacionVerano = parseFloat(item.INDICE_VARIACION || 1.0);
        const indiceVariacionInvierno = parseFloat(item.INDICE_VARIACION_INVIERNO || indiceVariacionVerano);
        const ventaProyVerano = FormatoUtils.formatearNumero(item.VENTA_PROY_VERANO || 0);
        const ventaProyInvierno = FormatoUtils.formatearNumero(item.VENTA_PROY_INVIERNO || 0);
        const compraProyectada = item.COMPRA_PROYECTADA || 0;
        
        const ventaVeranoAnterior = TablaRendererUtils.buscarVentaHistoricaCorrecta(item, 'VERANO');
        const ventaInviernoAnterior = TablaRendererUtils.buscarVentaHistoricaCorrecta(item, 'INVIERNO');
        
        const claseCompra = FormatoUtils.obtenerClaseValor(compraProyectada);
        
        // Crear elementos TD para los índices editables
        const celdaIndiceVerano = document.createElement('td');
        celdaIndiceVerano.className = 'text-center editable-cell';
        celdaIndiceVerano.innerHTML = `<input type="number" class="indice-input" value="${indiceVariacionVerano.toFixed(2)}" step="0.01" min="0" max="10" readonly>`;
        celdaIndiceVerano.setAttribute('data-temporada', 'verano');
        celdaIndiceVerano.setAttribute('data-indice', indiceVariacionVerano);
        
        const celdaIndiceInvierno = document.createElement('td');
        celdaIndiceInvierno.className = 'text-center editable-cell';
        celdaIndiceInvierno.innerHTML = `<input type="number" class="indice-input" value="${indiceVariacionInvierno.toFixed(2)}" step="0.01" min="0" max="10" readonly>`;
        celdaIndiceInvierno.setAttribute('data-temporada', 'invierno');
        celdaIndiceInvierno.setAttribute('data-indice', indiceVariacionInvierno);
        
        // Agregar event listeners
        celdaIndiceVerano.addEventListener('click', () => {
            console.log('🔥 CLICK VERANO - Temporada: verano');
            IndiceEditor.editarIndice(item.RUBRO, item.CATEGORIA_PADRE, indiceVariacionVerano, solapa, index, 'verano');
        });
        
        celdaIndiceInvierno.addEventListener('click', () => {
            console.log('❄️ CLICK INVIERNO - Temporada: invierno');
            IndiceEditor.editarIndice(item.RUBRO, item.CATEGORIA_PADRE, indiceVariacionInvierno, solapa, index, 'invierno');
        });
        
        // NUEVA ESTRUCTURA: Agregar ÍNDICE ORIGINAL después de STOCK PROYECTADO
        tr.innerHTML = `
            <td class="fw-medium">${item.RUBRO || ''}</td>
            <td>${item.CATEGORIA_PADRE || ''}</td>
            <td class="text-end bg-info-subtle">${stockProyectado}</td>
            <td class="text-center bg-secondary-subtle text-emphasis" title="Índice de variación original (no editable)">${indiceOriginal.toFixed(2)}</td>
            <td class="placeholder-verano"></td>
            <td class="text-end bg-info-subtle" title="Venta histórica verano anterior">${FormatoUtils.formatearNumero(ventaVeranoAnterior)}</td>
            <td class="text-end bg-primary-subtle text-primary-emphasis fw-bold" title="Venta proyectada verano">${ventaProyVerano}</td>
            <td class="placeholder-invierno"></td>
            <td class="text-end bg-info-subtle" title="Venta histórica invierno anterior">${FormatoUtils.formatearNumero(ventaInviernoAnterior)}</td>
            <td class="text-end bg-primary-subtle text-primary-emphasis fw-bold" title="Venta proyectada invierno">${ventaProyInvierno}</td>
            <td class="text-end bg-success-subtle text-success-emphasis fw-bold ${claseCompra}" title="Compra proyectada">${FormatoUtils.formatearNumero(compraProyectada)}</td>
        `;
        
        // Reemplazar placeholders con las celdas con event listeners
        const placeholderVerano = tr.querySelector('.placeholder-verano');
        const placeholderInvierno = tr.querySelector('.placeholder-invierno');
        
        placeholderVerano.replaceWith(celdaIndiceVerano);
        placeholderInvierno.replaceWith(celdaIndiceInvierno);
        
        // Agregar columnas históricas dinámicas
        const columnasHistoricas = TablaRendererUtils.extraerColumnasVentasHistoricas(todosLosDatos);
        columnasHistoricas.forEach(columna => {
            const td = document.createElement('td');
            td.className = 'text-end bg-light';
            td.setAttribute('data-columna-dinamica', 'true');
            td.style.fontSize = '0.75rem';
            
            const valor = item[columna] || 0;
            td.textContent = FormatoUtils.formatearNumero(valor);
            td.title = `${columna}: ${FormatoUtils.formatearNumero(valor)}`;
            
            tr.appendChild(td);
        });
        
        console.log(`✅ Fila creada con índice original ${indiceOriginal.toFixed(2)} para ${item.RUBRO} - ${item.CATEGORIA_PADRE}`);
        
        return tr;
    }

    /**
     * Crear fila para stock proyectado (SIN cambios)
     */
    static crearFilaStock(item, index) {
        const tr = document.createElement('tr');
        tr.className = 'fila-datos';
        
        const stock = FormatoUtils.formatearNumero(item.STOCK || item.CANT_STOCK || 0);
        const stockGuardar = FormatoUtils.formatearNumero(item.STOCK_GUARDAR || item.CANT_STOCK_GUARDAR || 0);
        const comprasVerano = FormatoUtils.formatearNumero(item.COMPRAS_VERANO || item.CANT_PEND_OC_VERANO || 0);
        const comprasInvierno = FormatoUtils.formatearNumero(item.COMPRAS_INVIERNO || item.CANT_PEND_OC_INVIERNO || 0);
        const comprasAtemporal = FormatoUtils.formatearNumero(item.COMPRAS_ATEMPORAL || item.CANT_PEND_OC_ATEMPORAL || 0);
        const stockCobertura = FormatoUtils.formatearNumero(item.STOCK_COBERTURA || 0);
        const stockProyectado = FormatoUtils.formatearNumero(item.STOCK_PROYECTADO || 0);
        
        tr.innerHTML = `
            <td class="fw-medium">${item.RUBRO || ''}</td>
            <td>${item.CATEGORIA_PADRE || ''}</td>
            <td class="text-end bg-info-subtle" title="Stock actual">${stock}</td>
            <td class="text-end bg-warning-subtle" title="Stock a guardar">${stockGuardar}</td>
            <td class="text-end bg-success-subtle" title="Compras pendientes verano">${comprasVerano}</td>
            <td class="text-end bg-success-subtle" title="Compras pendientes invierno">${comprasInvierno}</td>
            <td class="text-end bg-success-subtle" title="Compras pendientes atemporal">${comprasAtemporal}</td>
            <td class="text-end bg-danger-subtle" title="Stock cobertura">${stockCobertura}</td>
            <td class="text-end bg-primary-subtle text-primary-emphasis fw-bold" title="Stock proyectado final">${stockProyectado}</td>
        `;
        
        return tr;
    }

    /**
     * Funciones de utilidad
     */
    static resetearEstadoTabla(solapa) {
        return TablaRendererUtils.resetearEstadoTabla(solapa);
    }

    static aplicarFiltroVisual(solapa, termino = '') {
        const tabla = document.getElementById(`tbody-${solapa}`);
        const filas = tabla.querySelectorAll('.fila-datos');
        
        if (!termino || termino.length < 2) {
            filas.forEach(fila => {
                fila.style.display = '';
                fila.style.opacity = '1';
            });
            return filas.length;
        }
        
        const terminoLower = termino.toLowerCase();
        let coincidencias = 0;
        
        filas.forEach(fila => {
            const rubro = (fila.children[0]?.textContent || '').toLowerCase();
            const categoria = (fila.children[1]?.textContent || '').toLowerCase();
            
            if (rubro.includes(terminoLower) || categoria.includes(terminoLower)) {
                fila.style.display = '';
                fila.style.opacity = '1';
                coincidencias++;
            } else {
                fila.style.display = 'none';
                fila.style.opacity = '0.5';
            }
        });
        
        return coincidencias;
    }

    static resaltarFila(solapa, index, resaltar = true) {
        const tabla = document.getElementById(`tbody-${solapa}`);
        const fila = tabla.children[index];
        
        if (fila) {
            if (resaltar) {
                fila.classList.add('table-warning');
                fila.style.transform = 'scale(1.02)';
                fila.style.boxShadow = '0 4px 8px rgba(255, 193, 7, 0.3)';
            } else {
                fila.classList.remove('table-warning');
                fila.style.transform = '';
                fila.style.boxShadow = '';
            }
        }
    }

    static actualizarHeadersEtiquetas(solapa, etiquetas) {
        if (!etiquetas) return;
        
        const headersById = [
            { id: 'header-venta-verano', key: 'verano' },
            { id: 'header-venta-invierno', key: 'invierno' },
            { id: 'header-venta-verano-inv', key: 'verano' },
            { id: 'header-venta-invierno-inv', key: 'invierno' }
        ];
        
        headersById.forEach(header => {
            const element = document.getElementById(header.id);
            if (element && etiquetas[header.key]) {
                element.textContent = etiquetas[header.key];
                element.setAttribute('title', etiquetas[header.key]);
            }
        });
    }

    static obtenerEstadoTabla(solapa) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        const tbody = tabla.querySelector('tbody');
        
        return {
            columnas_procesadas: tabla.getAttribute('data-columnas-procesadas') === 'true',
            tabla_limpia: tabla.getAttribute('data-limpia') === 'true',
            total_headers: tabla.querySelectorAll('thead th').length,
            total_filas: tbody.querySelectorAll('.fila-datos').length,
            columnas_dinamicas: tabla.querySelectorAll('[data-columna-dinamica]').length
        };
    }

    // Compatibilidad
    static mostrarTablaVacia(tbody, colspan, mensaje) {
        return TablaRendererUtils.mostrarTablaVacia(tbody, colspan, mensaje);
    }

    static limpiarTabla(solapa) {
        return TablaRendererUtils.limpiarTabla(solapa);
    }

    static escaparComillas(texto) {
        return TablaRendererUtils.escaparComillas(texto);
    }

    static buscarVentaHistoricaCorrecta(item, temporada) {
        return TablaRendererUtils.buscarVentaHistoricaCorrecta(item, temporada);
    }

    /**
     * Inicialización simplificada
     */
    static init() {
        console.log('✅ TablaRenderer (con índice original) inicializado correctamente');
    }

    static diagnosticarTablas() {
        const info = {};
        
        ['verano', 'invierno', 'stock'].forEach(solapa => {
            info[solapa] = TablaRenderer.obtenerEstadoTabla(solapa);
        });
        
        console.group('📊 DIAGNÓSTICO TABLAS');
        console.table(info);
        console.groupEnd();
        
        return info;
    }

    static limpiarTodasLasTablas() {
        ['verano', 'invierno', 'stock'].forEach(solapa => {
            TablaRenderer.resetearEstadoTabla(solapa);
            TablaRenderer.limpiarTabla(solapa);
        });
        console.log('✅ Todas las tablas limpiadas');
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    TablaRenderer.init();
    
    // Funciones globales para debugging
    window.diagnosticarTablas = () => TablaRenderer.diagnosticarTablas();
    window.limpiarTodasLasTablas = () => TablaRenderer.limpiarTodasLasTablas();
    
    console.log('✅ TablaRenderer con índice original cargado');
});