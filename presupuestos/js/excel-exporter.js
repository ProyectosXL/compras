// Exportador Excel Unificado usando SheetJS (Con soporte de Filtros)
// Archivo: presupuestos/js/excel-exporter.js

class ExcelExporter {
    
    /**
     * Exportar datos a Excel usando SheetJS
     */
    static async exportarExcel(datos, nombreHoja = 'Datos', nombreArchivo = null) {
        try {
            if (!datos || datos.length === 0) {
                throw new Error('No hay datos para exportar');
            }

            // Cargar SheetJS desde CDN si no está disponible
            if (typeof XLSX === 'undefined') {
                await ExcelExporter.cargarSheetJS();
            }

            // Crear workbook
            const wb = XLSX.utils.book_new();
            
            // Crear worksheet desde datos
            const ws = XLSX.utils.json_to_sheet(datos);
            
            // Ajustar anchos de columnas
            ExcelExporter.ajustarAnchoColumnas(ws, datos);
            
            // Agregar worksheet al workbook
            XLSX.utils.book_append_sheet(wb, ws, nombreHoja);
            
            // Generar nombre de archivo
            const archivo = nombreArchivo || `${nombreHoja}_${ExcelExporter.obtenerFechaHora()}.xlsx`;
            
            // Exportar archivo
            XLSX.writeFile(wb, archivo);
            
            console.log('✅ Excel exportado:', archivo);
            return true;
            
        } catch (error) {
            console.error('Error exportando Excel:', error);
            throw error;
        }
    }

    /**
     * Exportar múltiples hojas en un solo archivo
     */
    static async exportarMultiplesHojas(hojas, nombreArchivo = null) {
        try {
            if (typeof XLSX === 'undefined') {
                await ExcelExporter.cargarSheetJS();
            }

            const wb = XLSX.utils.book_new();
            
            Object.entries(hojas).forEach(([nombreHoja, datos]) => {
                if (datos && datos.length > 0) {
                    const ws = XLSX.utils.json_to_sheet(datos);
                    ExcelExporter.aplicarFormatosHoja(ws, datos, nombreHoja);
                    ExcelExporter.ajustarAnchoColumnas(ws, datos);
                    XLSX.utils.book_append_sheet(wb, ws, nombreHoja);
                } else {
                    const ws = XLSX.utils.json_to_sheet([{ 'Sin datos': 'No hay información disponible' }]);
                    XLSX.utils.book_append_sheet(wb, ws, nombreHoja);
                }
            });
            
            const archivo = nombreArchivo || `presupuesto_completo_${ExcelExporter.obtenerFechaHora()}.xlsx`;
            XLSX.writeFile(wb, archivo);
            
            console.log('✅ Excel múltiples hojas exportado:', archivo);
            return true;
            
        } catch (error) {
            console.error('Error exportando Excel múltiples hojas:', error);
            throw error;
        }
    }

    static async cargarSheetJS() {
        return new Promise((resolve, reject) => {
            if (typeof XLSX !== 'undefined') {
                resolve();
                return;
            }
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
            script.onload = () => { console.log('✅ SheetJS cargado'); resolve(); };
            script.onerror = () => { reject(new Error('Error cargando SheetJS')); };
            document.head.appendChild(script);
        });
    }

    static aplicarFormatosHoja(worksheet, datos, nombreHoja) {
        if (!datos || datos.length === 0 || !worksheet) return;

        const columnas = Object.keys(datos[0]);
        const esUSD = nombreHoja.toUpperCase().includes('USD');
        const formatoMoneda = esUSD ? '"U$D "#,##0' : '"$ "#,##0';
        const formatoEntero = '#,##0';

        const range = XLSX.utils.decode_range(worksheet['!ref']);

        for (let R = range.s.r + 1; R <= range.e.r; ++R) {
            columnas.forEach((colNombre, C) => {
                const cellRef = XLSX.utils.encode_cell({ r: R, c: C });
                const cell = worksheet[cellRef];
                if (!cell || typeof cell.v !== 'number') return;

                const colUpper = colNombre.toUpperCase();
                if (colUpper.includes('MARK-UP') || colUpper.includes('MARKUP')) {
                    cell.z = '0.00';
                } else if (colUpper.includes('VCOSTO') || colUpper.includes('COSTO BASE')) {
                    cell.z = esUSD ? '"U$D "#,##0.00' : '"$ "#,##0.00';
                } else if (colUpper.includes('UNIDADES') || colUpper.includes('(U.)')) {
                    cell.z = formatoEntero;
                } else if (
                    colUpper.includes('TOTAL') || 
                    colUpper.includes('$') || 
                    colUpper.includes('USD') || 
                    colUpper.includes('VENTA') || 
                    colUpper.includes('COSTO') ||
                    /^[A-Za-z]{3}\s\d{2}/.test(colNombre) // Columnas tipo "Ago 26", "Sep 26"
                ) {
                    cell.z = formatoMoneda;
                }
            });
        }
    }

    static ajustarAnchoColumnas(worksheet, datos) {
        if (!datos || datos.length === 0) return;
        const columnas = Object.keys(datos[0]);
        const anchos = columnas.map(columna => {
            let maxAncho = columna.length;
            // Muestreamos solo las primeras 50 filas para no lentificar
            const muestra = datos.slice(0, 50);
            muestra.forEach(fila => {
                const valor = fila[columna];
                if (valor != null) {
                    const longitud = valor.toString().length;
                    if (longitud > maxAncho) maxAncho = longitud;
                }
            });
            return { wch: Math.min(Math.max(maxAncho, 10), 50) };
        });
        worksheet['!cols'] = anchos;
    }

    static obtenerFechaHora() {
        const ahora = new Date();
        return ahora.toISOString().slice(0, 19).replace(/[T:]/g, '-');
    }

    /**
     * Preparar datos tomando lo FILTRADO si existe
     */
    static obtenerDatosFiltradosOCompletos(solapa) {
        // Prioridad 1: Datos filtrados por el usuario
        if (typeof TotalesCompra !== 'undefined' && TotalesCompra.datosFiltrados[solapa] && TotalesCompra.datosFiltrados[solapa].length > 0) {
            console.log(`📊 Exportando datos FILTRADOS de ${solapa} (${TotalesCompra.datosFiltrados[solapa].length} registros)`);
            return TotalesCompra.datosFiltrados[solapa];
        }
        
        // Prioridad 2: Datos completos de la app
        const app = window.presupuestoApp;
        if (app && app.datos && app.datos[solapa]) {
            console.log(`📊 Exportando datos COMPLETOS de ${solapa} (Fallback)`);
            return app.datos[solapa];
        }
        
        return [];
    }

    static prepararDatosPresupuesto(datos, solapa) {
        if (!datos || datos.length === 0) return [];

        return datos.map(item => {
            // Campos base
            const resultado = {
                'Rubro': item.RUBRO || '',
                'Categoría': item.CATEGORIA || item.CATEGORIA_PADRE || '',
            };

            if (solapa === 'stock') {
                resultado['Stock Actual'] = item.STOCK || item.CANT_STOCK || 0;
                resultado['Stock a Guardar'] = item.STOCK_GUARDAR || item.CANT_STOCK_GUARDAR || 0;
                resultado['Compras Verano'] = item.COMPRAS_VERANO || item.CANT_PEND_OC_VERANO || 0;
                resultado['Compras Invierno'] = item.COMPRAS_INVIERNO || item.CANT_PEND_OC_INVIERNO || 0;
                resultado['Compras Atemporal'] = item.COMPRAS_ATEMPORAL || item.CANT_PEND_OC_ATEMPORAL || 0;
                resultado['Stock Cobertura'] = item.STOCK_COBERTURA || 0;
                resultado['Stock Proyectado'] = item.STOCK_PROYECTADO || 0;
            } else {
                // Verano / Invierno
                resultado['Stock Proyectado'] = item.STOCK_PROYECTADO || 0;
                
                // Índices
                resultado['Índice Var. Original'] = parseFloat(item.INDICE_VAR_ORIGINAL || item.INDICE_ORIGINAL || 1).toFixed(2);
                
                // Campos editables (usamos el valor actual en memoria)
                resultado['Índice Ver. Variación'] = parseFloat(item.INDICE_VARIACION || 1).toFixed(2);
                
                // Ventas anteriores (lógica robusta)
                // Los encabezados llevan el período que cubre cada columna, igual que la
                // pantalla: exportado sin eso, el Excel perdía de qué temporada hablaba.
                const periodos = ExcelExporter.periodosDeSolapa(solapa);

                resultado['Venta Ver. Anterior'] = ExcelExporter.buscarVentaHistorica(item, 'VERANO');
                resultado[`Proy. Verano (${periodos.verano})`] = item.VENTA_PROY_VERANO || 0;

                resultado['Índice Inv. Variación'] = parseFloat(item.INDICE_VARIACION_INVIERNO || item.INDICE_VARIACION || 1).toFixed(2);
                resultado['Venta Inv. Anterior'] = ExcelExporter.buscarVentaHistorica(item, 'INVIERNO');
                resultado[`Proy. Invierno (${periodos.invierno})`] = item.VENTA_PROY_INVIERNO || 0;
                
                resultado['Compra Proyectada'] = item.COMPRA_PROYECTADA || 0;
                
                // Agregar columnas dinámicas (Historial de años al final)
                // Excluimos las columnas que ya agregamos manualmente o que son de sistema
                const columnasIgnorar = [
                    'ID', 'RUBRO', 'CATEGORIA', 'CATEGORIA_PADRE', 'DESCRIPCION',
                    'STOCK_PROYECTADO', 'STOCK_ACTUAL', 'INDICE_VAR_ORIGINAL', 'INDICE_VARIACION',
                    'INDICE_VARIACION_INVIERNO', 'COMPRA_PROYECTADA', 'VENTA_PROY_VERANO', 'VENTA_PROY_INVIERNO',
                    'VTA_VERANO_ACTUAL', 'VTA_INVIERNO_ACTUAL',
                    // Bases del cálculo, ya exportadas como "Venta Ver./Inv. Anterior".
                    'VENTA_VERANO_ANTERIOR', 'VENTA_INVIERNO_ANTERIOR'
                ];

                Object.keys(item).forEach(key => {
                    // Si es una columna de historial (contiene VERANO, INVIERNO o VTA_)
                    if (!columnasIgnorar.includes(key) && 
                        (key.includes('VERANO') || key.includes('INVIERNO') || key.includes('VTA_')) &&
                        !key.startsWith('PROY') &&
                        !isNaN(parseFloat(item[key]))) {
                        
                        // Misma etiqueta que la tabla HTML: la traduce el servidor
                        // (VTA_VERANO_26 -> "VER 25-26"). Antes acá se repetía a mano el
                        // parche de restarle 1 al año del verano, y el invierno quedaba
                        // con otro formato, así que el Excel y la pantalla no coincidían.
                        resultado[TemporadaServidor.etiquetaHistorica(key)] = item[key];
                    }
                });
            }
            return resultado;
        });
    }

    /**
     * Etiquetas de los períodos proyectados de una solapa, tomadas del servidor.
     * Si todavía no llegaron, se cae a un rótulo genérico antes que inventar una
     * temporada que podría no ser la que está sumada en la celda.
     */
    static periodosDeSolapa(solapa) {
        const p = (typeof TemporadaServidor !== 'undefined')
            ? TemporadaServidor.periodos(solapa === 'invierno' ? 'invierno' : 'verano')
            : null;

        return {
            verano: p ? p.verano.etiqueta : 'período no disponible',
            invierno: p ? p.invierno.etiqueta : 'período no disponible'
        };
    }

    static buscarVentaHistorica(item, temporada) {
        // Misma lógica que TotalesCompra para consistencia
        const anoActual = new Date().getFullYear().toString().substr(-2);
        const anoAnterior = (parseInt(anoActual) - 1).toString();
        
        const posibles = temporada === 'VERANO' 
            ? ['VENTA_VER_ANT', 'VTA_VERANO_ANT', `VTA_VERANO_${anoActual}`, `VTA_VERANO_${anoAnterior}`]
            : ['VENTA_INV_ANT', 'VTA_INVIERNO_ANT', `VTA_INVIERNO_${anoActual}`, `VTA_INVIERNO_${anoAnterior}`];

        for (const k of posibles) {
            if (item[k] !== undefined) return parseFloat(item[k]);
        }
        
        // Búsqueda genérica
        const patron = temporada === 'VERANO' ? 'VTA_VERANO_' : 'VTA_INVIERNO_';
        const keys = Object.keys(item).filter(k => k.startsWith(patron));
        if (keys.length > 0) return parseFloat(item[keys[0]]);
        
        return 0;
    }

    static prepararDatosCompras(datos) {
        return datos.map(item => ({
            'Fecha': item.FEC_EMISIO || '',
            'Orden': item.N_ORDEN_CO || '',
            'Proveedor': item.NOM_PROVEE || '',
            'Artículo': item.COD_ARTICU || '',
            'Descripción': item.DESCRIPCIO || '',
            'Rubro': item.RUBRO || '',
            'Categoría': item.CATEGORIA_PADRE || '',
            'Verano': item.VERANO || 0,
            'Invierno': item.INVIERNO || 0,
            'Atemporal': item.ATEMPORAL || 0,
            'Total': (item.VERANO || 0) + (item.INVIERNO || 0) + (item.ATEMPORAL || 0)
        }));
    }

    static prepararDatosVentas(datos) {
        return datos.map(item => {
            const res = {
                'Rubro': item.RUBRO,
                'Categoría': item.CATEGORIA_PADRE,
                'Venta 60d': item.VTA_ULT_60_DIAS,
                'Venta Año Ant': item.VTA_ULT_60_DIAS_ANO_ANT,
                'Variación %': item.VARIACION_PORCENTUAL
            };
            // Agregar meses dinámicos
            Object.keys(item).forEach(k => {
                if (k.startsWith('VTA_') && k.includes('_20')) {
                    res[k.replace('VTA_', '')] = item[k];
                }
            });
            return res;
        });
    }

    /**
     * Exportar solapa individual (Punto de entrada principal)
     */
    static async exportarSolapa(solapa) {
        try {
            let datosCrudos = [];
            let datosPreparados = [];
            let nombreHoja = '';
            let nombreArchivo = '';

            switch (solapa) {
                case 'verano':
                    datosCrudos = ExcelExporter.obtenerDatosFiltradosOCompletos('verano');
                    datosPreparados = ExcelExporter.prepararDatosPresupuesto(datosCrudos, 'verano');
                    nombreHoja = 'Compra Verano';
                    nombreArchivo = `compra_verano_${ExcelExporter.obtenerFechaHora()}.xlsx`;
                    break;
                
                case 'invierno':
                    datosCrudos = ExcelExporter.obtenerDatosFiltradosOCompletos('invierno');
                    datosPreparados = ExcelExporter.prepararDatosPresupuesto(datosCrudos, 'invierno');
                    nombreHoja = 'Compra Invierno';
                    nombreArchivo = `compra_invierno_${ExcelExporter.obtenerFechaHora()}.xlsx`;
                    break;
                
                case 'stock':
                    datosCrudos = ExcelExporter.obtenerDatosFiltradosOCompletos('stock');
                    datosPreparados = ExcelExporter.prepararDatosPresupuesto(datosCrudos, 'stock');
                    nombreHoja = 'Stock Proyectado';
                    nombreArchivo = `stock_proyectado_${ExcelExporter.obtenerFechaHora()}.xlsx`;
                    break;
                
                case 'compras-detalle':
                    if (typeof ComprasManager !== 'undefined') {
                        datosCrudos = ComprasManager.datosFiltrados || ComprasManager.datos;
                        datosPreparados = ExcelExporter.prepararDatosCompras(datosCrudos);
                        nombreHoja = 'Compras Detalle';
                        nombreArchivo = `compras_detalle_${ExcelExporter.obtenerFechaHora()}.xlsx`;
                    }
                    break;

                case 'ventas-6-meses':
                    if (typeof VentasManager !== 'undefined') {
                        datosCrudos = VentasManager.datosFiltrados || VentasManager.datos;
                        datosPreparados = ExcelExporter.prepararDatosVentas(datosCrudos);
                        nombreHoja = 'Ventas 6 Meses';
                        nombreArchivo = `ventas_6meses_${ExcelExporter.obtenerFechaHora()}.xlsx`;
                    }
                    break;

                case 'contenedores':
                    // La exportación de contenedores se delega a ContenedoresManager.exportarExcel()
                    // que maneja los modos de agrupación (detalle/rubro/proveedor/contenedor)
                    if (typeof ContenedoresManager !== 'undefined') {
                        await ContenedoresManager.exportarExcel();
                    }
                    return; // Sale sin pasar por ExcelExporter.exportarExcel() abajo
            }

            if (!datosPreparados || datosPreparados.length === 0) {
                throw new Error('No hay datos visibles para exportar en ' + solapa);
            }

            await ExcelExporter.exportarExcel(datosPreparados, nombreHoja, nombreArchivo);
            
            if (typeof UIUtils !== 'undefined') {
                UIUtils.mostrarAlerta(`Excel exportado: ${datosPreparados.length} registros`, 'success');
            }

        } catch (error) {
            console.error('Error exportando solapa:', error);
            if (typeof UIUtils !== 'undefined') {
                UIUtils.mostrarAlerta('Error al exportar: ' + error.message, 'error');
            }
        }
    }
}

// Hacer disponible globalmente
window.ExcelExporter = ExcelExporter;