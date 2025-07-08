
// Renderizador de tablas para las diferentes solapas
// Archivo: presupuestos/js/tabla-renderer.js

class TablaRenderer {
    
    /**
     * NUEVO: Formatear número para input (usar punto como decimal)
     */
    static formatearNumeroParaInput(numero) {
        if (numero === null || numero === undefined || isNaN(numero)) return '1.0';
        return parseFloat(numero).toFixed(4);
    }

    /**
     * Renderizar tabla de Compra Proyectada Verano
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

        // Agregar columnas de ventas históricas si existen
        TablaRenderer.agregarColumnasVentasHistoricas('verano', datos);
        
        // Actualizar headers dinámicos si hay etiquetas
        if (etiquetas) {
            TablaRenderer.actualizarHeadersEtiquetas('verano', etiquetas);
        }
    }

    /**
     * Renderizar tabla de Compra Proyectada Invierno
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

        // Agregar columnas de ventas históricas si existen
        TablaRenderer.agregarColumnasVentasHistoricas('invierno', datos);
        
        // Actualizar headers dinámicos si hay etiquetas
        if (etiquetas) {
            TablaRenderer.actualizarHeadersEtiquetas('invierno', etiquetas);
        }
    }

    /**
     * Renderizar tabla de Stock Proyectado
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
        
        datos.forEach(item => {
            const tr = TablaRenderer.crearFilaStock(item);
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
     * Obtener número de columnas originales por solapa
     */
    static obtenerColumnasOriginales(solapa) {
        switch (solapa) {
            case 'verano':
            case 'invierno':
                return 7; // Rubro, Categoría, Stock Proy, Índice, Venta Ver, Venta Inv, Compra Proy
            case 'stock':
                return 9; // Rubro, Categoría, Stock, Stock Guardar, Compras Ver, Compras Inv, Compras Atemp, Stock Cob, Stock Proy
            default:
                return 7;
        }
    }

    /**
     * Crear fila para tabla de verano (CORREGIDO - Formateo de números)
     */
    static crearFilaVerano(item, index) {
        const tr = document.createElement('tr');
        tr.className = 'fila-datos';
        
        const stockProyectado = FormatoUtils.formatearNumero(item.STOCK_PROYECTADO || 0);
        const indiceVariacion = parseFloat(item.INDICE_VARIACION || 1.0);
        const ventaProyVerano = FormatoUtils.formatearNumero(item.VENTA_PROY_VERANO || 0);
        const ventaProyInvierno = FormatoUtils.formatearNumero(item.VENTA_PROY_INVIERNO || 0);
        const compraProyectada = item.COMPRA_PROYECTADA || 0;
        
        // Determinar clase para compra proyectada
        const claseCompra = FormatoUtils.obtenerClaseValor(compraProyectada);
        
        tr.innerHTML = `
            <td class="fw-medium">${item.RUBRO || ''}</td>
            <td>${item.CATEGORIA_PADRE || ''}</td>
            <td class="text-end">${stockProyectado}</td>
            <td class="text-center editable-cell" onclick="editarIndice('${TablaRenderer.escaparComillas(item.RUBRO)}', '${TablaRenderer.escaparComillas(item.CATEGORIA_PADRE)}', ${indiceVariacion}, 'verano', ${index})">
                <input type="number" class="indice-input" value="${TablaRenderer.formatearNumeroParaInput(indiceVariacion)}" 
                       step="0.0001" min="0" max="10" readonly>
            </td>
            <td class="text-end valor-positivo">${ventaProyVerano}</td>
            <td class="text-end valor-positivo">${ventaProyInvierno}</td>
            <td class="text-end ${claseCompra}">${FormatoUtils.formatearNumero(compraProyectada)}</td>
        `;
        
        return tr;
    }

    /**
     * Crear fila para tabla de invierno (CORREGIDO - Formateo de números)
     */
    static crearFilaInvierno(item, index) {
        const tr = document.createElement('tr');
        tr.className = 'fila-datos';
        
        const stockProyectado = FormatoUtils.formatearNumero(item.STOCK_PROYECTADO || 0);
        const indiceVariacion = parseFloat(item.INDICE_VARIACION || 1.0);
        const ventaProyVerano = FormatoUtils.formatearNumero(item.VENTA_PROY_VERANO || 0);
        const ventaProyInvierno = FormatoUtils.formatearNumero(item.VENTA_PROY_INVIERNO || 0);
        const compraProyectada = item.COMPRA_PROYECTADA || 0;
        
        // Determinar clase para compra proyectada
        const claseCompra = FormatoUtils.obtenerClaseValor(compraProyectada);
        
        tr.innerHTML = `
            <td class="fw-medium">${item.RUBRO || ''}</td>
            <td>${item.CATEGORIA_PADRE || ''}</td>
            <td class="text-end">${stockProyectado}</td>
            <td class="text-center editable-cell" onclick="editarIndice('${TablaRenderer.escaparComillas(item.RUBRO)}', '${TablaRenderer.escaparComillas(item.CATEGORIA_PADRE)}', ${indiceVariacion}, 'invierno', ${index})">
                <input type="number" class="indice-input" value="${TablaRenderer.formatearNumeroParaInput(indiceVariacion)}" 
                       step="0.0001" min="0" max="10" readonly>
            </td>
            <td class="text-end valor-positivo">${ventaProyVerano}</td>
            <td class="text-end valor-positivo">${ventaProyInvierno}</td>
            <td class="text-end ${claseCompra}">${FormatoUtils.formatearNumero(compraProyectada)}</td>
        `;
        
        return tr;
    }

    /**
     * Crear fila para tabla de stock
     */
    static crearFilaStock(item) {
        const tr = document.createElement('tr');
        tr.className = 'fila-datos';
        
        const stock = FormatoUtils.formatearNumero(item.STOCK || 0);
        const stockGuardar = FormatoUtils.formatearNumero(item.STOCK_GUARDAR || 0);
        const comprasVerano = FormatoUtils.formatearNumero(item.COMPRAS_VERANO || 0);
        const comprasInvierno = FormatoUtils.formatearNumero(item.COMPRAS_INVIERNO || 0);
        const comprasAtemporal = FormatoUtils.formatearNumero(item.COMPRAS_ATEMPORAL || 0);
        const stockCobertura = FormatoUtils.formatearNumero(item.STOCK_COBERTURA || 0);
        const stockProyectado = item.STOCK_PROYECTADO || 0;
        
        // Determinar clase para stock proyectado
        const claseStock = FormatoUtils.obtenerClaseValor(stockProyectado);
        
        tr.innerHTML = `
            <td class="fw-medium">${item.RUBRO || ''}</td>
            <td>${item.CATEGORIA_PADRE || ''}</td>
            <td class="text-end">${stock}</td>
            <td class="text-end">${stockGuardar}</td>
            <td class="text-end">${comprasVerano}</td>
            <td class="text-end">${comprasInvierno}</td>
            <td class="text-end">${comprasAtemporal}</td>
            <td class="text-end">${stockCobertura}</td>
            <td class="text-end ${claseStock} fw-bold">${FormatoUtils.formatearNumero(stockProyectado)}</td>
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
     * Agregar columnas de ventas históricas dinámicamente (MEJORADO - SIN DUPLICADOS)
     */
    static agregarColumnasVentasHistoricas(solapa, datos) {
        if (!datos || datos.length === 0) return;
        
        const tabla = document.getElementById(`tabla-${solapa}`);
        
        // Verificar si ya se procesaron las columnas para evitar duplicados
        if (tabla.getAttribute('data-columnas-procesadas') === 'true') {
            return;
        }
        
        const primeraFila = datos[0];
        const columnasVenta = TablaRenderer.extraerColumnasVenta(primeraFila);
        
        if (columnasVenta.length === 0) {
            tabla.setAttribute('data-columnas-procesadas', 'true');
            return;
        }
        
        // Ordenar columnas cronológicamente
        const columnasOrdenadas = TablaRenderer.ordenarColumnasVenta(columnasVenta);
        
        // Agregar headers
        TablaRenderer.agregarHeadersVentas(solapa, columnasOrdenadas);
        
        // Agregar datos a las filas
        TablaRenderer.agregarDatosVentas(solapa, datos, columnasOrdenadas);
        
        // Marcar tabla como procesada
        tabla.setAttribute('data-columnas-procesadas', 'true');
    }

    /**
     * Extraer columnas de venta de los datos (MEJORADO)
     */
    static extraerColumnasVenta(primeraFila) {
        const columnasVenta = [];
        const columnasExcluidas = [
            'RUBRO', 'CATEGORIA_PADRE', 'STOCK_PROYECTADO', 'INDICE_VARIACION',
            'VENTA_PROY_VERANO', 'VENTA_PROY_INVIERNO', 'COMPRA_PROYECTADA',
            'CANT_STOCK', 'CANT_STOCK_GUARDAR', 'CANT_PEND_OC_VERANO', 
            'CANT_PEND_OC_INVIERNO', 'CANT_PEND_OC_ATEMPORAL', 'STOCK_COBERTURA',
            'STOCK', 'STOCK_GUARDAR', 'COMPRAS_VERANO', 'COMPRAS_INVIERNO', 
            'COMPRAS_ATEMPORAL', 'STOCK_PROYECTADO'
        ];
        
        Object.keys(primeraFila).forEach(columna => {
            // Verificar si es columna de venta histórica
            if (!columnasExcluidas.includes(columna) && 
                (columna.includes('VERANO') || columna.includes('INVIERNO') || 
                 columna.includes('VTA_'))) {
                columnasVenta.push(columna);
            }
        });
        
        // Eliminar duplicados usando Set
        return [...new Set(columnasVenta)];
    }

    /**
     * Ordenar columnas de venta cronológicamente (MEJORADO)
     */
    static ordenarColumnasVenta(columnas) {
        return columnas.sort((a, b) => {
            // Extraer información de temporada y año
            const extraerInfo = (columna) => {
                let match = columna.match(/(VERANO|INVIERNO)[\s_]*(\d{2})/i);
                if (match) {
                    return {
                        temporada: match[1].toUpperCase(),
                        ano: parseInt(match[2])
                    };
                }
                
                // Fallback para otros formatos
                if (columna.includes('VERANO')) {
                    return { temporada: 'VERANO', ano: 25 };
                } else if (columna.includes('INVIERNO')) {
                    return { temporada: 'INVIERNO', ano: 25 };
                }
                
                return { temporada: 'OTROS', ano: 0 };
            };
            
            const infoA = extraerInfo(a);
            const infoB = extraerInfo(b);
            
            // Primero ordenar por año
            if (infoA.ano !== infoB.ano) {
                return infoA.ano - infoB.ano;
            }
            
            // Si el año es igual, verano va antes que invierno
            if (infoA.temporada !== infoB.temporada) {
                if (infoA.temporada === 'VERANO' && infoB.temporada === 'INVIERNO') return -1;
                if (infoA.temporada === 'INVIERNO' && infoB.temporada === 'VERANO') return 1;
            }
            
            // Si todo es igual, orden alfabético
            return a.localeCompare(b);
        });
    }

    /**
     * Agregar headers de ventas (MEJORADO - SIN DUPLICADOS)
     */
    static agregarHeadersVentas(solapa, columnasOrdenadas) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        const thead = tabla.querySelector('thead tr');
        const columnasOriginales = TablaRenderer.obtenerColumnasOriginales(solapa);
        
        // Verificar que no tengamos más columnas de las originales
        if (thead.children.length > columnasOriginales) {
            return; // Ya se agregaron las columnas
        }
        
        columnasOrdenadas.forEach((columna) => {
            const th = document.createElement('th');
            th.className = 'text-center bg-light';
            th.textContent = TablaRenderer.formatearNombreColumna(columna);
            th.setAttribute('data-columna-dinamica', 'true');
            thead.appendChild(th);
        });
    }

    /**
     * Agregar datos de ventas a las filas (MEJORADO)
     */
    static agregarDatosVentas(solapa, datos, columnasOrdenadas) {
        const filas = document.querySelectorAll(`#tbody-${solapa} .fila-datos`);
        
        datos.forEach((item, rowIndex) => {
            if (filas[rowIndex]) {
                const fila = filas[rowIndex];
                const columnasOriginales = TablaRenderer.obtenerColumnasOriginales(solapa);
                
                // Verificar que no tengamos más columnas de las originales
                if (fila.children.length > columnasOriginales) {
                    return; // Ya se agregaron las columnas
                }
                
                columnasOrdenadas.forEach((columna) => {
                    const td = document.createElement('td');
                    td.className = 'text-end text-muted';
                    td.setAttribute('data-columna-dinamica', 'true');
                    td.textContent = FormatoUtils.formatearNumero(item[columna] || 0);
                    fila.appendChild(td);
                });
            }
        });
    }

    /**
     * Formatear nombre de columna para mostrar (MEJORADO)
     */
    static formatearNombreColumna(columna) {
        // Reemplazar formatos comunes
        let nombre = columna
            .replace(/VTA_/g, '')
            .replace(/_/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
        
        // Capitalizar primera letra de cada palabra
        return nombre.split(' ').map(palabra => 
            palabra.charAt(0).toUpperCase() + palabra.slice(1).toLowerCase()
        ).join(' ');
    }

    /**
     * Actualizar headers con etiquetas dinámicas
     */
    static actualizarHeadersEtiquetas(solapa, etiquetas) {
        if (!etiquetas) return;
        
        const headerVerano = document.getElementById(`header-venta-${solapa === 'verano' ? 'verano' : 'verano-inv'}`);
        const headerInvierno = document.getElementById(`header-venta-${solapa === 'verano' ? 'invierno' : 'invierno-inv'}`);
        
        if (headerVerano && etiquetas.verano) {
            headerVerano.textContent = etiquetas.verano;
        }
        
        if (headerInvierno && etiquetas.invierno) {
            headerInvierno.textContent = etiquetas.invierno;
        }
    }

    /**
     * Actualizar una fila específica con nuevos cálculos
     */
    static actualizarFilaCalculos(solapa, index, datosActualizados) {
        const tabla = document.getElementById(`tbody-${solapa}`);
        const fila = tabla.children[index];
        
        if (!fila || !datosActualizados) return;
        
        // Actualizar valores calculados en la fila
        if (solapa === 'verano' || solapa === 'invierno') {
            const celdas = fila.children;
            
            // Venta Proyectada Verano (columna 4)
            if (celdas[4]) {
                celdas[4].textContent = FormatoUtils.formatearNumero(datosActualizados.VENTA_PROY_VERANO || 0);
            }
            
            // Venta Proyectada Invierno (columna 5)
            if (celdas[5]) {
                celdas[5].textContent = FormatoUtils.formatearNumero(datosActualizados.VENTA_PROY_INVIERNO || 0);
            }
            
            // Compra Proyectada (columna 6)
            if (celdas[6]) {
                const compraProyectada = datosActualizados.COMPRA_PROYECTADA || 0;
                celdas[6].textContent = FormatoUtils.formatearNumero(compraProyectada);
                celdas[6].className = `text-end ${FormatoUtils.obtenerClaseValor(compraProyectada)}`;
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
     * Ordenar tabla por columna
     */
    static ordenarTabla(solapa, columnaIndex, direccion = 'asc') {
        const tabla = document.getElementById(`tabla-${solapa}`);
        const tbody = tabla.querySelector('tbody');
        const filas = Array.from(tbody.querySelectorAll('.fila-datos'));
        
        filas.sort((a, b) => {
            const valorA = a.children[columnaIndex]?.textContent || '';
            const valorB = b.children[columnaIndex]?.textContent || '';
            
            // Intentar comparar como números primero
            const numA = parseFloat(valorA.replace(/[^\d.-]/g, ''));
            const numB = parseFloat(valorB.replace(/[^\d.-]/g, ''));
            
            let resultado;
            if (!isNaN(numA) && !isNaN(numB)) {
                resultado = numA - numB;
            } else {
                resultado = valorA.localeCompare(valorB);
            }
            
            return direccion === 'desc' ? -resultado : resultado;
        });
        
        // Limpiar tbody y volver a insertar filas ordenadas
        tbody.innerHTML = '';
        filas.forEach(fila => tbody.appendChild(fila));
        
        // Actualizar indicadores de ordenamiento en headers
        TablaRenderer.actualizarIndicadoresOrden(solapa, columnaIndex, direccion);
    }

    /**
     * Actualizar indicadores de ordenamiento en headers
     */
    static actualizarIndicadoresOrden(solapa, columnaIndex, direccion) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        const headers = tabla.querySelectorAll('thead th');
        
        // Limpiar indicadores previos
        headers.forEach(th => {
            th.classList.remove('sorted-asc', 'sorted-desc');
            const icon = th.querySelector('.sort-icon');
            if (icon) icon.remove();
        });
        
        // Agregar nuevo indicador
        if (headers[columnaIndex]) {
            const th = headers[columnaIndex];
            th.classList.add(`sorted-${direccion}`);
            
            const icon = document.createElement('i');
            icon.className = `fas fa-sort-${direccion === 'asc' ? 'up' : 'down'} sort-icon ms-1`;
            th.appendChild(icon);
        }
    }

    /**
     * Agregar funcionalidad de ordenamiento clickeable a headers
     */
    static agregarOrdenamientoClickeable(solapa) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        const headers = tabla.querySelectorAll('thead th');
        
        headers.forEach((th, index) => {
            // Solo agregar ordenamiento a columnas que no son dinámicas
            if (!th.getAttribute('data-columna-dinamica')) {
                th.style.cursor = 'pointer';
                th.setAttribute('title', 'Click para ordenar');
                
                th.addEventListener('click', () => {
                    const currentDirection = th.classList.contains('sorted-asc') ? 'desc' : 'asc';
                    TablaRenderer.ordenarTabla(solapa, index, currentDirection);
                });
            }
        });
    }

    /**
     * Escapar comillas para uso en atributos HTML
     */
    static escaparComillas(texto) {
        if (!texto) return '';
        return texto.replace(/'/g, "\\'").replace(/"/g, '\\"');
    }

    /**
     * Obtener datos de una fila específica
     */
    static obtenerDatosFila(solapa, index) {
        const tabla = document.getElementById(`tbody-${solapa}`);
        const fila = tabla.children[index];
        
        if (!fila) return null;
        
        const celdas = fila.children;
        return {
            rubro: celdas[0]?.textContent || '',
            categoria: celdas[1]?.textContent || '',
            stockProyectado: TablaRenderer.parsearNumero(celdas[2]?.textContent),
            indiceVariacion: TablaRenderer.parsearNumero(celdas[3]?.querySelector('input')?.value),
            ventaVerano: TablaRenderer.parsearNumero(celdas[4]?.textContent),
            ventaInvierno: TablaRenderer.parsearNumero(celdas[5]?.textContent),
            compraProyectada: TablaRenderer.parsearNumero(celdas[6]?.textContent)
        };
    }

    /**
     * Parsear número de texto formateado
     */
    static parsearNumero(texto) {
        if (!texto) return 0;
        return parseFloat(texto.replace(/[^\d.-]/g, '')) || 0;
    }

    /**
     * Agregar tooltips informativos
     */
    static agregarTooltips(solapa) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        
        // Tooltips para celdas editables
        const celdasEditables = tabla.querySelectorAll('.editable-cell');
        celdasEditables.forEach(celda => {
            celda.setAttribute('title', 'Click para editar el índice de variación');
            celda.setAttribute('data-bs-toggle', 'tooltip');
        });
        
        // Tooltips para valores de compra proyectada
        const celdasCompra = tabla.querySelectorAll('.valor-positivo, .valor-negativo');
        celdasCompra.forEach(celda => {
            const valor = TablaRenderer.parsearNumero(celda.textContent);
            if (valor > 0) {
                celda.setAttribute('title', 'Compra necesaria: ' + FormatoUtils.formatearNumero(valor) + ' unidades');
            } else if (valor < 0) {
                celda.setAttribute('title', 'Exceso de stock: ' + FormatoUtils.formatearNumero(Math.abs(valor)) + ' unidades');
            }
        });
        
        // Inicializar tooltips de Bootstrap
        if (window.bootstrap && bootstrap.Tooltip) {
            const tooltipTriggerList = tabla.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltipTriggerList.forEach(tooltipTriggerEl => {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    }

    /**
     * Exportar datos de tabla visible como CSV (funcionalidad auxiliar)
     */
    static exportarTablaCSV(solapa) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        const filas = tabla.querySelectorAll('tr');
        
        let csvContent = '';
        
        filas.forEach(fila => {
            const celdas = fila.querySelectorAll('th, td');
            const textoFila = Array.from(celdas).map(celda => {
                let texto = celda.textContent.trim();
                // Escapar comillas y comas
                if (texto.includes(',') || texto.includes('"')) {
                    texto = '"' + texto.replace(/"/g, '""') + '"';
                }
                return texto;
            }).join(',');
            csvContent += textoFila + '\n';
        });
        
        // Descargar archivo
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', `tabla_${solapa}_${new Date().toISOString().slice(0, 10)}.csv`);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Inicializar funcionalidades de tabla
     */
    static inicializarTabla(solapa) {
        TablaRenderer.agregarOrdenamientoClickeable(solapa);
        TablaRenderer.agregarTooltips(solapa);
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
}

// Inicializar tablas cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar funcionalidades para todas las tablas
    ['verano', 'invierno', 'stock'].forEach(solapa => {
        TablaRenderer.inicializarTabla(solapa);
    });
});