
// Exportador Excel Unificado usando SheetJS
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
                    ExcelExporter.ajustarAnchoColumnas(ws, datos);
                    XLSX.utils.book_append_sheet(wb, ws, nombreHoja);
                } else {
                    // Crear hoja vacía
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

    /**
     * Cargar librería SheetJS desde CDN
     */
    static async cargarSheetJS() {
        return new Promise((resolve, reject) => {
            if (typeof XLSX !== 'undefined') {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
            script.onload = () => {
                console.log('✅ SheetJS cargado correctamente');
                resolve();
            };
            script.onerror = () => {
                reject(new Error('Error cargando SheetJS'));
            };
            document.head.appendChild(script);
        });
    }

    /**
     * Ajustar ancho de columnas automáticamente
     */
    static ajustarAnchoColumnas(worksheet, datos) {
        if (!datos || datos.length === 0) return;

        const columnas = Object.keys(datos[0]);
        const anchos = [];

        columnas.forEach((columna, index) => {
            // Ancho mínimo basado en el nombre de la columna
            let maxAncho = columna.length;

            // Revisar el contenido de las celdas
            datos.forEach(fila => {
                const valor = fila[columna];
                if (valor != null) {
                    const longitud = valor.toString().length;
                    if (longitud > maxAncho) {
                        maxAncho = longitud;
                    }
                }
            });

            // Limitar ancho máximo y mínimo
            anchos.push({ wch: Math.min(Math.max(maxAncho, 10), 50) });
        });

        worksheet['!cols'] = anchos;
    }

    /**
     * Obtener fecha y hora para nombres de archivo
     */
    static obtenerFechaHora() {
        const ahora = new Date();
        return ahora.toISOString().slice(0, 19).replace(/[T:]/g, '-');
    }

    /**
     * Preparar datos de compras para Excel
     */
    static prepararDatosCompras(datos) {
        return datos.map(item => {
            const total = (item.VERANO || 0) + (item.INVIERNO || 0) + (item.ATEMPORAL || 0);
            return {
                'Fecha Emisión': item.FEC_EMISIO || '',
                'N° Orden': item.N_ORDEN_CO || '',
                'Proveedor': item.NOM_PROVEE || '',
                'Código Artículo': item.COD_ARTICU || '',
                'Descripción': item.DESCRIPCIO || '',
                'Rubro': item.RUBRO || '',
                'Categoría': item.CATEGORIA_PADRE || '',
                'Verano': item.VERANO || 0,
                'Invierno': item.INVIERNO || 0,
                'Atemporal': item.ATEMPORAL || 0,
                'Total': total
            };
        });
    }

    /**
     * Preparar datos de presupuesto para Excel
     */
    static prepararDatosPresupuesto(datos, solapa) {
        if (!datos || datos.length === 0) return [];

        return datos.map(item => {
            const resultado = {
                'Rubro': item.RUBRO || '',
                'Categoría': item.CATEGORIA || item.CATEGORIA_PADRE || '',
                'Stock Proyectado': item.STOCK_PROYECTADO || 0,
                'Índice Variación': item.INDICE_VARIACION || 1
            };

            // Agregar campos específicos según la solapa
            if (solapa === 'stock') {
                resultado['Stock Actual'] = item.STOCK_ACTUAL || 0;
                resultado['Stock a Guardar'] = item.STOCK_GUARDAR || 0;
                resultado['Compras Verano'] = item.COMPRAS_VERANO || 0;
                resultado['Compras Invierno'] = item.COMPRAS_INVIERNO || 0;
                resultado['Compras Atemporal'] = item.COMPRAS_ATEMPORAL || 0;
                resultado['Stock Cobertura'] = item.STOCK_COBERTURA || 0;
            } else {
                // Para verano e invierno
                resultado['Venta Anterior Verano'] = item.VENTA_ANT_VERANO || 0;
                resultado['Venta Proyectada Verano'] = item.VENTA_PROY_VERANO || 0;
                resultado['Venta Anterior Invierno'] = item.VENTA_ANT_INVIERNO || 0;
                resultado['Venta Proyectada Invierno'] = item.VENTA_PROY_INVIERNO || 0;
                resultado['Compra Proyectada'] = item.COMPRA_PROYECTADA || 0;
            }

            return resultado;
        });
    }

    /**
     * Exportar presupuesto completo
     */
    static async exportarPresupuestoCompleto() {
        try {
            const app = window.presupuestoApp;
            
            if (!app || !app.datos) {
                throw new Error('No hay datos de presupuesto disponibles');
            }

            const hojas = {};

            // Preparar datos de cada solapa
            if (app.datos.verano && app.datos.verano.length > 0) {
                hojas['Compra Verano'] = ExcelExporter.prepararDatosPresupuesto(app.datos.verano, 'verano');
            }

            if (app.datos.invierno && app.datos.invierno.length > 0) {
                hojas['Compra Invierno'] = ExcelExporter.prepararDatosPresupuesto(app.datos.invierno, 'invierno');
            }

            if (app.datos.stock && app.datos.stock.length > 0) {
                hojas['Stock Proyectado'] = ExcelExporter.prepararDatosPresupuesto(app.datos.stock, 'stock');
            }

            // Agregar compras detalle si está disponible
            if (typeof ComprasManager !== 'undefined' && ComprasManager.datos && ComprasManager.datos.length > 0) {
                hojas['Compras Detalle'] = ExcelExporter.prepararDatosCompras(ComprasManager.datos);
            }

            if (Object.keys(hojas).length === 0) {
                throw new Error('No hay datos para exportar');
            }

            await ExcelExporter.exportarMultiplesHojas(hojas);
            
            if (typeof UIUtils !== 'undefined') {
                UIUtils.mostrarAlerta('Exportación completa realizada correctamente', 'success');
            }

        } catch (error) {
            console.error('Error en exportación completa:', error);
            if (typeof UIUtils !== 'undefined') {
                UIUtils.mostrarAlerta('Error en exportación: ' + error.message, 'error');
            }
        }
    }

    /**
     * Preparar datos de ventas para Excel
     */
    static prepararDatosVentas(datos) {
        return datos.map(item => {
            const resultado = {
                'Rubro': item.RUBRO || '',
                'Categoría': item.CATEGORIA_PADRE || '',
                'Ventas Últimos 60 días': item.VTA_ULT_60_DIAS || 0,
                'Ventas Año Anterior': item.VTA_ULT_60_DIAS_ANO_ANT || 0,
                'Índice Variación': item.INDICE_VARIACION || 1,
                'Variación %': item.VARIACION_PORCENTUAL || 0,
                'Estado': item.ESTADO || 'estable'
            };

            // Agregar columnas dinámicas de meses
            Object.keys(item).forEach(key => {
                if (key.startsWith('VTA_') && key.match(/VTA_\d+_\d{4}/)) {
                    const partes = key.split('_');
                    if (partes.length === 3) {
                        const mes = parseInt(partes[1]);
                        const ano = parseInt(partes[2]);
                        const nombreMes = VentasManager.formatearNombreMes(mes, ano);
                        resultado[nombreMes] = item[key] || 0;
                    }
                }
            });

            return resultado;
        });
    }

    /**
     * Exportar solapa individual
     */
    static async exportarSolapa(solapa) {
        try {
            const app = window.presupuestoApp;
            let datos, nombreHoja, nombreArchivo;

            switch (solapa) {
                case 'verano':
                    datos = ExcelExporter.prepararDatosPresupuesto(app.datos.verano, 'verano');
                    nombreHoja = 'Compra Verano';
                    nombreArchivo = `compra_verano_${ExcelExporter.obtenerFechaHora()}.xlsx`;
                    break;
                
                case 'invierno':
                    datos = ExcelExporter.prepararDatosPresupuesto(app.datos.invierno, 'invierno');
                    nombreHoja = 'Compra Invierno';
                    nombreArchivo = `compra_invierno_${ExcelExporter.obtenerFechaHora()}.xlsx`;
                    break;
                
                case 'stock':
                    datos = ExcelExporter.prepararDatosPresupuesto(app.datos.stock, 'stock');
                    nombreHoja = 'Stock Proyectado';
                    nombreArchivo = `stock_proyectado_${ExcelExporter.obtenerFechaHora()}.xlsx`;
                    break;
                
                case 'compras-detalle':
                    if (typeof ComprasManager === 'undefined' || !ComprasManager.datosFiltrados || ComprasManager.datosFiltrados.length === 0) {
                        throw new Error('No hay datos de compras disponibles');
                    }
                    datos = ExcelExporter.prepararDatosCompras(ComprasManager.datosFiltrados);
                    nombreHoja = 'Compras Detalle';
                    nombreArchivo = `compras_detalle_${ExcelExporter.obtenerFechaHora()}.xlsx`;
                    break;
                
                case 'ventas-6-meses':
                    if (typeof VentasManager === 'undefined' || !VentasManager.datosFiltrados || VentasManager.datosFiltrados.length === 0) {
                        throw new Error('No hay datos de ventas disponibles');
                    }
                    datos = ExcelExporter.prepararDatosVentas(VentasManager.datosFiltrados);
                    nombreHoja = 'Ventas 6 Meses';
                    nombreArchivo = `ventas_6_meses_${ExcelExporter.obtenerFechaHora()}.xlsx`;
                    break;
                
                default:
                    throw new Error('Solapa no válida: ' + solapa);
            }

            if (!datos || datos.length === 0) {
                throw new Error('No hay datos para exportar en ' + solapa);
            }

            await ExcelExporter.exportarExcel(datos, nombreHoja, nombreArchivo);
            
            if (typeof UIUtils !== 'undefined') {
                UIUtils.mostrarAlerta('Excel exportado correctamente', 'success');
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