
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


    /**
     * Venta anterior que se usó como base de la proyección.
     *
     * La elige el servidor y viaja en VENTA_*_ANTERIOR. Antes se buscaba acá con una
     * lista fija de las últimas dos temporadas y el reloj del navegador: cuando la
     * última venta con movimiento era más vieja (por ejemplo BILLETERAS DE CUERO,
     * con ventas solo en INV 24), la pantalla mostraba 0 mientras la proyección se
     * había calculado sobre 335. La búsqueda vieja queda como respaldo por si algún
     * camino de datos no pasa por ProcesadorDatos.
     */
    buscarVentaHistoricaCorrecta(item, temporada) {
        const campoServidor = temporada === 'VERANO' ? 'VENTA_VERANO_ANTERIOR' : 'VENTA_INVIERNO_ANTERIOR';
        if (item[campoServidor] !== undefined && item[campoServidor] !== null) {
            return parseFloat(item[campoServidor]) || 0;
        }

        const anoActual = new Date().getFullYear() % 100; // 2025 -> 25
        const mesActual = new Date().getMonth() + 1; // 1-12

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
            // CORRECCIÓN: Determinar el último invierno según el mes actual
            let anoInvierno;
            
            if (mesActual >= 8 || mesActual === 1) {
                // Estamos en verano (Ago-Ene), el último invierno fue este año
                anoInvierno = anoActual;
            } else {
                // Estamos en invierno (Feb-Jul), el último invierno completo fue el año pasado
                anoInvierno = anoActual - 1;
            }
            
            const posiblesColumnas = [
                `VTA_INVIERNO_${anoInvierno}`,      // INVIERNO del último período
                `INVIERNO ${anoInvierno}`,          // Formato alternativo
                `VTA_INVIERNO_${anoInvierno - 1}`,  // Fallback año anterior
                `INVIERNO ${anoInvierno - 1}`,      // Fallback formato alternativo
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
            'COMPRA_PROYECTADA', 'STOCK_PROYECTADO',
            // Bases del cálculo, no columnas históricas: llevan VERANO/INVIERNO en el
            // nombre y si no se excluyen aparecen como una temporada más en la tabla.
            'VENTA_VERANO_ANTERIOR', 'VENTA_INVIERNO_ANTERIOR'
        ];
        
        const columnasVenta = [];

        for (const columna of Object.keys(primeraFila)) {
            if (!columnasExcluidas.includes(columna) &&
                (columna.includes('VERANO') || columna.includes('INVIERNO') || columna.includes('VTA_'))) {
                columnasVenta.push(columna);
            }
        }

        // Orden cronológico real, no alfabético. Un sort() de texto agrupaba todos los
        // inviernos y después todos los veranos, así que las columnas no se leían como
        // una línea de tiempo. Se ordena por fecha de inicio de cada temporada, que es
        // lo único comparable entre verano e invierno.
        return columnasVenta.sort((a, b) =>
            TablaRendererUtils.inicioTemporadaColumna(a) - TablaRendererUtils.inicioTemporadaColumna(b)
        );
    },

    /**
     * Fecha de inicio (como número ordenable) de la temporada de una columna.
     * El SP numera el verano por el año en que termina: VTA_VERANO_26 empieza en
     * agosto de 2025. Las que no son de temporada van al final.
     */
    inicioTemporadaColumna(columna) {
        const m = String(columna).match(/(VERANO|INVIERNO)[\s_]*(\d{2})(?:\s*-\s*(\d{2}))?/i);
        if (!m) return Number.MAX_SAFE_INTEGER;

        const tipo = m[1].toUpperCase();
        const anoFin = 2000 + parseInt(m[3] !== undefined ? m[3] : m[2], 10);

        return tipo === 'VERANO'
            ? (anoFin - 1) * 100 + 8   // 1 de agosto del año anterior
            : anoFin * 100 + 2;        // 1 de febrero del mismo año
    },

    /**
     * Etiqueta de una columna histórica en la convención única de la app.
     *
     * Antes acá vivía un parche que a "VERANO 26" le restaba 1 y mostraba "VERANO 25":
     * acertaba el año de inicio pero dejaba el verano nombrado con un año suelto
     * —ambiguo, porque la temporada cruza dos— y no tocaba el invierno. Ahora la
     * traducción la hace el servidor (etiquetas_historicas) y acá solo se consulta,
     * para que las columnas, el encabezado, el Excel y la ayuda digan lo mismo.
     */
    formatearNombreColumna(columna) {
        return TemporadaServidor.etiquetaHistorica(columna);
    },

    // NUEVO: Obtener índice original del registro
    obtenerIndiceOriginal(item) {
        // Prioridad 1: Objeto de respaldo en memoria
        if (item._indicesOriginales && item._indicesOriginales.verano !== undefined) {
            return parseFloat(item._indicesOriginales.verano);
        }
        // Prioridad 2: Propiedad de BD o procesador
        if (item.INDICE_VAR_ORIGINAL !== undefined && item.INDICE_VAR_ORIGINAL !== null) {
            return parseFloat(item.INDICE_VAR_ORIGINAL);
        }
        if (item.INDICE_ORIGINAL !== undefined && item.INDICE_ORIGINAL !== null) {
            return parseFloat(item.INDICE_ORIGINAL);
        }
        if (item._indiceOriginal !== undefined && item._indiceOriginal !== null) {
            return parseFloat(item._indiceOriginal);
        }
        // Fallback: Si no hay índice original guardado, registrarlo ahora y usar el actual
        const orig = parseFloat(item.INDICE_VARIACION || 1.0);
        item.INDICE_VAR_ORIGINAL = orig;
        item.INDICE_ORIGINAL = orig;
        item._indiceOriginal = orig;
        return orig;
    }
};

// ========================================
// CLASE PRINCIPAL TABLARENDERER
// ========================================

class TablaRenderer {
    
    /**
     * Renderizar tabla de Compra Proyectada Verano (MODIFICADO)
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
        
        // NUEVO: Restaurar marcas de índices editados
        setTimeout(() => {
            TablaRenderer.restaurarMarcasIndicesEditados('verano', datos);
        }, 100);
        
        console.log(`✅ Tabla verano renderizada: ${datos.length} registros`);
    }

    /**
     * Renderizar tabla de Compra Proyectada Invierno (MODIFICADO)
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
        
        // NUEVO: Restaurar marcas de índices editados
        setTimeout(() => {
            TablaRenderer.restaurarMarcasIndicesEditados('invierno', datos);
        }, 100);
        
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

    /**
     * Renderiza la tabla de historial de compras proyectadas.
     * @param {Array} datos Los datos del historial a renderizar.
     */
    static renderizarTablaHistorial(datos) {
        const tbody = document.getElementById('tbody-historial');
        if (!tbody) {
            console.error('No se encontró el cuerpo de la tabla de historial (tbody-historial).');
            return;
        }

        if (!datos || datos.length === 0) {
            tbody.innerHTML = `<tr><td colspan="14" class="text-center text-muted py-4">No se encontraron resultados para los filtros aplicados.</td></tr>`;
            return;
        }

        const html = datos.map(item => {
            let fechaFormateada = 'Fecha inválida';
            if (item.fecha_guardado && item.fecha_guardado.date) {
                const [fecha, hora] = item.fecha_guardado.date.substring(0, 19).split(' ');
                const [Y, M, D] = fecha.split('-');
                const [h, m] = hora.split(':');
                fechaFormateada = `${D}/${M}/${Y} ${h}:${m}`;
            }

            return `
                <tr>
                    <td>${fechaFormateada}</td>
                    <td>${item.nombre_presupuesto || ''}</td>
                    <td><span class="badge bg-secondary">${item.temporada || ''}</span></td>
                    <td><span class="badge bg-info text-dark" title="${item.temporada_objetivo_desde ? `${UIUtils.formatearFechaCorta(item.temporada_objetivo_desde)} a ${UIUtils.formatearFechaCorta(item.temporada_objetivo_hasta)}` : ''}">${item.temporada_objetivo || 's/d'}</span></td>
                    <td>${item.rubro || ''}</td>
                    <td>${item.categoria_padre || ''}</td>
                    <td class="text-end">${FormatoUtils.formatearNumero(item.stock_proyectado)}</td>
                    <td class="text-center bg-warning-subtle">${parseFloat(item.indice_verano_variacion || 0).toFixed(2)}</td>
                    <td class="text-end">${FormatoUtils.formatearNumero(item.venta_verano_anterior)}</td>
                    <td class="text-center bg-primary-subtle">${FormatoUtils.formatearNumero(item.venta_proyectada_verano)}</td>
                    <td class="text-center bg-warning-subtle">${parseFloat(item.indice_invierno_variacion || 0).toFixed(2)}</td>
                    <td class="text-end">${FormatoUtils.formatearNumero(item.venta_invierno_anterior)}</td>
                    <td class="text-center bg-primary-subtle">${FormatoUtils.formatearNumero(item.venta_proyectada_invierno)}</td>
                    <td class="text-center bg-success-subtle"><strong>${FormatoUtils.formatearNumero(item.compra_proyectada)}</strong></td>
                </tr>
            `;
        }).join('');

        tbody.innerHTML = html;
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

    /**
     * NUEVO: Restaurar marcas de índices editados al renderizar
     */
    static restaurarMarcasIndicesEditados(solapa, datos) {
        if (!datos || datos.length === 0) return;
        
        const tbody = document.getElementById(`tbody-${solapa}`);
        if (!tbody) return;
        
        const filas = tbody.querySelectorAll('tr.fila-datos');
        
        filas.forEach((fila, indiceVisual) => {
            const rubro = fila.children[0]?.textContent?.trim();
            const categoria = fila.children[1]?.textContent?.trim();
            
            if (!rubro || !categoria) return;
            
            // Buscar el registro correspondiente
            const registro = datos.find(item => 
                item.RUBRO === rubro && 
                (item.CATEGORIA_PADRE === categoria || item.CATEGORIA === categoria)
            );
            
            if (registro && registro._indicesEditados) {
                // Restaurar marcas para cada temporada editada
                Object.entries(registro._indicesEditados).forEach(([temporada, info]) => {
                    IndiceEditor.aplicarMarcasVisualesEnFila(fila, temporada, info.valorActual);
                    console.log(`🎨 Marca de edición restaurada: ${rubro} - ${categoria} (${temporada})`);
                });
            }
        });
        
        console.log(`✅ Marcas de índices editados restauradas en solapa ${solapa}`);
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