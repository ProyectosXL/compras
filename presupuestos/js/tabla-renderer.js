
// Renderizador de tablas para las diferentes solapas - CORREGIDO
// Archivo: presupuestos/js/tabla-renderer.js

class TablaRenderer {
    
    /**
     * NUEVO: Formatear número para input (usar punto como decimal)
     */
    static formatearNumeroParaInput(numero) {
        if (numero === null || numero === undefined || isNaN(numero)) return '1.00';
        return parseFloat(numero).toFixed(2); // 2 decimales para índices
    }

    /**
     * Renderizar tabla de Compra Proyectada Verano - CORREGIDO
     */
    static renderizarTablaVerano(datos, etiquetas = null) {
        const tbody = document.getElementById('tbody-verano');
        
        if (!datos || datos.length === 0) {
            TablaRenderer.mostrarTablaVacia(tbody, 7, 'No hay datos disponibles para la proyección de verano');
            return;
        }

        // Limpiar tabla antes de renderizar
        TablaRenderer.limpiarTabla('verano');
        tbody.innerHTML = '';
        
        datos.forEach((item, index) => {
            const tr = TablaRenderer.crearFilaVerano(item, index);
            tbody.appendChild(tr);
        });

        // ELIMINADO: No agregar columnas históricas duplicadas
        // TablaRenderer.agregarColumnasVentasHistoricas('verano', datos);
        
        // Actualizar headers dinámicos si hay etiquetas
        if (etiquetas) {
            TablaRenderer.actualizarHeadersEtiquetas('verano', etiquetas);
        }
    }

    /**
     * Renderizar tabla de Compra Proyectada Invierno - CORREGIDO
     */
    static renderizarTablaInvierno(datos, etiquetas = null) {
        const tbody = document.getElementById('tbody-invierno');
        
        if (!datos || datos.length === 0) {
            TablaRenderer.mostrarTablaVacia(tbody, 7, 'No hay datos disponibles para la proyección de invierno');
            return;
        }

        // Limpiar tabla antes de renderizar
        TablaRenderer.limpiarTabla('invierno');
        tbody.innerHTML = '';
        
        datos.forEach((item, index) => {
            const tr = TablaRenderer.crearFilaInvierno(item, index);
            tbody.appendChild(tr);
        });

        // ELIMINADO: No agregar columnas históricas duplicadas
        // TablaRenderer.agregarColumnasVentasHistoricas('invierno', datos);
        
        // Actualizar headers dinámicos si hay etiquetas
        if (etiquetas) {
            TablaRenderer.actualizarHeadersEtiquetas('invierno', etiquetas);
        }
    }

    /**
     * Renderizar tabla de Stock Proyectado - CORREGIDO
     */
    static renderizarTablaStock(datos) {
        const tbody = document.getElementById('tbody-stock');
        
        if (!datos || datos.length === 0) {
            TablaRenderer.mostrarTablaVacia(tbody, 9, 'No hay datos disponibles para el stock proyectado');
            return;
        }

        // Limpiar tabla antes de renderizar
        TablaRenderer.limpiarTabla('stock');
        tbody.innerHTML = '';
        
        datos.forEach((item, index) => {
            const tr = TablaRenderer.crearFilaStock(item, index);
            tbody.appendChild(tr);
        });
    }

    /**
     * Limpiar tabla y resetear headers a estado original
     */
    static limpiarTabla(solapa) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        const thead = tabla.querySelector('thead tr');
        
        // Remover columnas dinámicas agregadas previamente
        const columnasOriginales = TablaRenderer.obtenerColumnasOriginales(solapa);
        
        // Limpiar thead manteniendo solo columnas originales
        while (thead.children.length > columnasOriginales) {
            thead.removeChild(thead.lastChild);
        }
        
        // Marcar tabla como limpia
        tabla.setAttribute('data-limpia', 'true');
    }

    /**
     * ACTUALIZADO: Obtener número de columnas originales
     */
    static obtenerColumnasOriginales(solapa) {
        switch (solapa) {
            case 'verano':
            case 'invierno':
                return 9; // Rubro, Categoría, Stock, Índice, Venta Hist Ver, Venta Proy Ver, Venta Hist Inv, Venta Proy Inv, Compra Proy
            case 'stock':
                return 9; // Original del stock
            default:
                return 9;
        }
    }

    /**
     * CORREGIDO: Crear fila para tabla de verano
     */
    static crearFilaVerano(item, index) {
        const tr = document.createElement('tr');
        tr.className = 'fila-datos';
        
        const stockProyectado = FormatoUtils.formatearNumero(item.STOCK_PROYECTADO || 0);
        const indiceVariacion = parseFloat(item.INDICE_VARIACION || 1.0);
        const ventaProyVerano = FormatoUtils.formatearNumero(item.VENTA_PROY_VERANO || 0);
        const ventaProyInvierno = FormatoUtils.formatearNumero(item.VENTA_PROY_INVIERNO || 0);
        const compraProyectada = item.COMPRA_PROYECTADA || 0;
        
        // CORREGIDO: Buscar ventas históricas CORRECTAS (temporada anterior)
        const ventaVeranoAnterior = TablaRenderer.buscarVentaHistoricaCorrecta(item, 'VERANO');
        const ventaInviernoAnterior = TablaRenderer.buscarVentaHistoricaCorrecta(item, 'INVIERNO');
        
        // Determinar clase para compra proyectada
        const claseCompra = FormatoUtils.obtenerClaseValor(compraProyectada);
        
        tr.innerHTML = `
            <td class="fw-medium">${item.RUBRO || ''}</td>
            <td>${item.CATEGORIA_PADRE || ''}</td>
            <td class="text-end">${stockProyectado}</td>
            <td class="text-center editable-cell" onclick="editarIndice('${TablaRenderer.escaparComillas(item.RUBRO)}', '${TablaRenderer.escaparComillas(item.CATEGORIA_PADRE)}', ${indiceVariacion}, 'verano', ${index})">
                <input type="number" class="indice-input" value="${indiceVariacion.toFixed(2)}" 
                    step="0.01" min="0" max="10" readonly>
            </td>
            <td class="text-end bg-info-subtle" title="Venta histórica verano anterior">${FormatoUtils.formatearNumero(ventaVeranoAnterior)}</td>
            <td class="text-end bg-primary-subtle text-primary-emphasis fw-bold" title="Venta proyectada verano">${ventaProyVerano}</td>
            <td class="text-end bg-info-subtle" title="Venta histórica invierno anterior">${FormatoUtils.formatearNumero(ventaInviernoAnterior)}</td>
            <td class="text-end bg-primary-subtle text-primary-emphasis fw-bold" title="Venta proyectada invierno">${ventaProyInvierno}</td>
            <td class="text-end bg-success-subtle text-success-emphasis fw-bold ${claseCompra}" title="Compra proyectada">${FormatoUtils.formatearNumero(compraProyectada)}</td>
        `;
        
        return tr;
    }

    /**
     * CORREGIDO: Crear fila para tabla de invierno
     */
    static crearFilaInvierno(item, index) {
        const tr = document.createElement('tr');
        tr.className = 'fila-datos';
        
        const stockProyectado = FormatoUtils.formatearNumero(item.STOCK_PROYECTADO || 0);
        const indiceVariacion = parseFloat(item.INDICE_VARIACION || 1.0);
        const ventaProyVerano = FormatoUtils.formatearNumero(item.VENTA_PROY_VERANO || 0);
        const ventaProyInvierno = FormatoUtils.formatearNumero(item.VENTA_PROY_INVIERNO || 0);
        const compraProyectada = item.COMPRA_PROYECTADA || 0;
        
        // CORREGIDO: Buscar ventas históricas CORRECTAS (temporada anterior)
        const ventaVeranoAnterior = TablaRenderer.buscarVentaHistoricaCorrecta(item, 'VERANO');
        const ventaInviernoAnterior = TablaRenderer.buscarVentaHistoricaCorrecta(item, 'INVIERNO');
        
        // Determinar clase para compra proyectada
        const claseCompra = FormatoUtils.obtenerClaseValor(compraProyectada);
        
        tr.innerHTML = `
            <td class="fw-medium">${item.RUBRO || ''}</td>
            <td>${item.CATEGORIA_PADRE || ''}</td>
            <td class="text-end">${stockProyectado}</td>
            <td class="text-center editable-cell" onclick="editarIndice('${TablaRenderer.escaparComillas(item.RUBRO)}', '${TablaRenderer.escaparComillas(item.CATEGORIA_PADRE)}', ${indiceVariacion}, 'invierno', ${index})">
                <input type="number" class="indice-input" value="${indiceVariacion.toFixed(2)}" 
                    step="0.01" min="0" max="10" readonly>
            </td>
            <td class="text-end bg-info-subtle" title="Venta histórica verano anterior">${FormatoUtils.formatearNumero(ventaVeranoAnterior)}</td>
            <td class="text-end bg-primary-subtle text-primary-emphasis fw-bold" title="Venta proyectada verano">${ventaProyVerano}</td>
            <td class="text-end bg-info-subtle" title="Venta histórica invierno anterior">${FormatoUtils.formatearNumero(ventaInviernoAnterior)}</td>
            <td class="text-end bg-primary-subtle text-primary-emphasis fw-bold" title="Venta proyectada invierno">${ventaProyInvierno}</td>
            <td class="text-end bg-success-subtle text-success-emphasis fw-bold ${claseCompra}" title="Compra proyectada">${FormatoUtils.formatearNumero(compraProyectada)}</td>
        `;
        
        return tr;
    }

    /**
     * CORREGIDO: Buscar venta histórica de la temporada ANTERIOR correcta
     */
    static buscarVentaHistoricaCorrecta(item, temporada) {
        const anoActual = new Date().getFullYear() % 100;
        
        if (temporada === 'VERANO') {
            // Para VERANO, buscar formato XX-XX del año anterior
            // Si estamos en 2025, buscar VERANO 24-25, VERANO 23-24, etc.
            for (let i = 1; i <= 3; i++) {
                let anoInicialAnterior = (anoActual - i) < 0 ? (anoActual - i + 100) : (anoActual - i);
                let anoFinalAnterior = (anoActual - i + 1) < 0 ? (anoActual - i + 1 + 100) : (anoActual - i + 1);
                
                const anoInicialStr = anoInicialAnterior.toString().padStart(2, '0');
                const anoFinalStr = anoFinalAnterior.toString().padStart(2, '0');
                
                const posiblesColumnas = [
                    `VERANO ${anoInicialStr}-${anoFinalStr}`,
                    `VERANO ${anoInicialStr}`,
                    `VERANO_${anoInicialStr}`,
                    `VERANO${anoInicialStr}`,
                    `VTA_VERANO_${anoInicialStr}`,
                    `VTA_VERANO${anoInicialStr}`
                ];
                
                for (const columna of posiblesColumnas) {
                    if (item[columna] && !isNaN(item[columna]) && item[columna] > 0) {
                        return parseFloat(item[columna]);
                    }
                }
            }
        } else {
            // Para INVIERNO, buscar año anterior simple
            for (let i = 1; i <= 3; i++) {
                let anoObjetivo = anoActual - i;
                if (anoObjetivo < 0) anoObjetivo += 100;
                
                const anoStr = anoObjetivo.toString().padStart(2, '0');
                
                const posiblesColumnas = [
                    `INVIERNO ${anoStr}`,
                    `INVIERNO_${anoStr}`,
                    `INVIERNO${anoStr}`,
                    `VTA_INVIERNO_${anoStr}`,
                    `VTA_INVIERNO${anoStr}`
                ];
                
                for (const columna of posiblesColumnas) {
                    if (item[columna] && !isNaN(item[columna]) && item[columna] > 0) {
                        return parseFloat(item[columna]);
                    }
                }
            }
        }
        
        return 0;
    }

    /**
     * CORREGIDO: Crear fila para tabla de stock
     */
    static crearFilaStock(item, index) {
        const tr = document.createElement('tr');
        tr.className = 'fila-datos';
        
        const stockProyectado = FormatoUtils.formatearNumero(item.STOCK_PROYECTADO || 0);
        const indiceVariacion = parseFloat(item.INDICE_VARIACION || 1.0);
        const ventaProyVerano = FormatoUtils.formatearNumero(item.VENTA_PROY_VERANO || 0);
        const ventaProyInvierno = FormatoUtils.formatearNumero(item.VENTA_PROY_INVIERNO || 0);
        const compraProyectada = item.COMPRA_PROYECTADA || 0;
        
        // CORREGIDO: Buscar ventas históricas CORRECTAS (temporada anterior)
        const ventaVeranoAnterior = TablaRenderer.buscarVentaHistoricaCorrecta(item, 'VERANO');
        const ventaInviernoAnterior = TablaRenderer.buscarVentaHistoricaCorrecta(item, 'INVIERNO');
        
        // Determinar clase para compra proyectada
        const claseCompra = FormatoUtils.obtenerClaseValor(compraProyectada);
        
        tr.innerHTML = `
            <td class="fw-medium">${item.RUBRO || ''}</td>
            <td>${item.CATEGORIA_PADRE || ''}</td>
            <td class="text-end">${stockProyectado}</td>
            <td class="text-center editable-cell" onclick="editarIndice('${TablaRenderer.escaparComillas(item.RUBRO)}', '${TablaRenderer.escaparComillas(item.CATEGORIA_PADRE)}', ${indiceVariacion}, 'stock', ${index})">
                <input type="number" class="indice-input" value="${indiceVariacion.toFixed(2)}" 
                    step="0.01" min="0" max="10" readonly>
            </td>
            <td class="text-end bg-info-subtle" title="Venta histórica verano anterior">${FormatoUtils.formatearNumero(ventaVeranoAnterior)}</td>
            <td class="text-end bg-primary-subtle text-primary-emphasis fw-bold" title="Venta proyectada verano">${ventaProyVerano}</td>
            <td class="text-end bg-info-subtle" title="Venta histórica invierno anterior">${FormatoUtils.formatearNumero(ventaInviernoAnterior)}</td>
            <td class="text-end bg-primary-subtle text-primary-emphasis fw-bold" title="Venta proyectada invierno">${ventaProyInvierno}</td>
            <td class="text-end bg-success-subtle text-success-emphasis fw-bold ${claseCompra}" title="Compra proyectada">${FormatoUtils.formatearNumero(compraProyectada)}</td>
        `;
        
        return tr;
    }

    /**
     * Mostrar tabla vacía con mensaje
     */
    static mostrarTablaVacia(tbody, colspan, mensaje) {
        tbody.innerHTML = `
            <tr>
                <td colspan="${colspan}" class="text-center text-muted py-4">
                    <i class="fas fa-inbox"></i>
                    ${mensaje}
                </td>
            </tr>
        `;
    }

    /**
     * CORREGIDO: Actualizar headers con etiquetas dinámicas
     */
    static actualizarHeadersEtiquetas(solapa, etiquetas) {
        if (!etiquetas) return;
        
        // CORRECCIÓN: Actualizar los headers de venta proyectada con títulos visibles
        const headerVentaVerano = document.querySelector(`#tabla-${solapa} .header-venta-proyectada:nth-of-type(1)`);
        const headerVentaInvierno = document.querySelector(`#tabla-${solapa} .header-venta-proyectada:nth-of-type(2)`);
        
        if (headerVentaVerano && etiquetas.verano) {
            headerVentaVerano.textContent = etiquetas.verano;
            headerVentaVerano.setAttribute('title', etiquetas.verano);
        }
        
        if (headerVentaInvierno && etiquetas.invierno) {
            headerVentaInvierno.textContent = etiquetas.invierno;
            headerVentaInvierno.setAttribute('title', etiquetas.invierno);
        }
        
        // CORRECCIÓN: También actualizar por IDs específicos si existen
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

    /**
     * Actualizar una fila específica con nuevos cálculos
     */
    static actualizarFilaCalculos(solapa, index, datosActualizados) {
        const tabla = document.getElementById(`tbody-${solapa}`);
        const fila = tabla.children[index];
        
        if (!fila || !datosActualizados) return;
        
        // Actualizar valores calculados en la fila
        if (solapa === 'verano' || solapa === 'invierno' || solapa === 'stock') {
            const celdas = fila.children;
            
            // Venta Proyectada Verano (columna 5)
            if (celdas[5]) {
                celdas[5].textContent = FormatoUtils.formatearNumero(datosActualizados.VENTA_PROY_VERANO || 0);
            }
            
            // Venta Proyectada Invierno (columna 7)
            if (celdas[7]) {
                celdas[7].textContent = FormatoUtils.formatearNumero(datosActualizados.VENTA_PROY_INVIERNO || 0);
            }
            
            // Compra Proyectada (columna 8)
            if (celdas[8]) {
                const compraProyectada = datosActualizados.COMPRA_PROYECTADA || 0;
                celdas[8].textContent = FormatoUtils.formatearNumero(compraProyectada);
                celdas[8].className = `text-end bg-success-subtle text-success-emphasis fw-bold ${FormatoUtils.obtenerClaseValor(compraProyectada)}`;
            }
        }
    }

    /**
     * Resaltar fila durante edición
     */
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
     * Aplicar filtros visuales a las filas (OPTIMIZADO PARA BÚSQUEDA RÁPIDA)
     */
    static aplicarFiltroVisual(solapa, termino = '') {
        const tabla = document.getElementById(`tbody-${solapa}`);
        const filas = tabla.querySelectorAll('.fila-datos');
        
        if (!termino || termino.length < 2) {
            // Mostrar todas las filas
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

    /**
     * Escapar comillas para uso en atributos HTML
     */
    static escaparComillas(texto) {
        if (!texto) return '';
        return texto.replace(/'/g, "\\'").replace(/"/g, '\\"');
    }

    /**
     * Resetear estado de tabla para nueva carga
     */
    static resetearEstadoTabla(solapa) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        if (tabla) {
            tabla.removeAttribute('data-columnas-procesadas');
            tabla.removeAttribute('data-limpia');
            
            // Remover atributos de columnas dinámicas
            const headersDinamicos = tabla.querySelectorAll('th[data-columna-dinamica]');
            headersDinamicos.forEach(th => th.remove());
            
            const celdasDinamicas = tabla.querySelectorAll('td[data-columna-dinamica]');
            celdasDinamicas.forEach(td => td.remove());
        }
    }

    /**
     * Obtener información de estado de la tabla
     */
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

    // RESTO DE MÉTODOS MANTENIDOS SIN CAMBIOS...
    static inicializarTabla(solapa) {
        // Inicialización básica
    }

    static ordenarTabla(solapa, columnaIndex, direccion = 'asc') {
        // Funcionalidad de ordenamiento
    }

    static obtenerDatosFila(solapa, index) {
        // Obtener datos de fila
    }

    static parsearNumero(texto) {
        if (!texto) return 0;
        return parseFloat(texto.replace(/[^\d.-]/g, '')) || 0;
    }
}

// Inicializar tablas cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar funcionalidades para todas las tablas
    ['verano', 'invierno', 'stock'].forEach(solapa => {
        TablaRenderer.inicializarTabla(solapa);
    });
});