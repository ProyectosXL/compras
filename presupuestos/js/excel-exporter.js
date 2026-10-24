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

        // Qué tramos llevan déficit de cobertura EN ALGUNA fila. Se decide una vez para
        // toda la hoja y no fila por fila: si la columna apareciera solo en las filas
        // que tienen déficit, quedaría ubicada donde aparece por primera vez, después
        // de las columnas de la fila anterior, y la hoja saldría con el encabezado
        // corrido respecto de los datos.
        const tramosConDeficit = new Set();
        datos.forEach(item => {
            (item.TRAMOS || []).forEach(t => {
                if (t.compra_deficit_cobertura > 0) { tramosConDeficit.add(t.orden); }
            });
        });

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

                // OC pendientes, agrupadas y abiertas: el Excel lleva todas las columnas,
                // sin importar la vista elegida en pantalla. Ya están dentro del stock
                // proyectado, por eso el encabezado lo aclara.
                const ocVer = item.CANT_PEND_OC_VERANO || 0;
                const ocInv = item.CANT_PEND_OC_INVIERNO || 0;
                const ocAtemp = item.CANT_PEND_OC_ATEMPORAL || 0;
                resultado['OC Pendientes (incl. en stock proy.)'] = ocVer + ocInv + ocAtemp;
                resultado['OC Pend. Verano'] = ocVer;
                resultado['OC Pend. Invierno'] = ocInv;
                resultado['OC Pend. Atemporal'] = ocAtemp;
                
                // Índices
                resultado['Índice Var. Original'] = parseFloat(item.INDICE_VAR_ORIGINAL || item.INDICE_ORIGINAL || 1).toFixed(2);
                
                // LP y Total Empresa, igual que la pantalla: con TE el editable es el TE y
                // el LP es el del SP; sin TE (Uruguay) el LP es el editable y no hay TE.
                const indices = ExcelExporter.indicesDeFila(item);
                resultado['Índice Ver. LP'] = indices.verano.lp;
                resultado['Índice Ver. Total Empresa'] = indices.verano.te;
                
                // Ventas anteriores (lógica robusta)
                // Los encabezados llevan el período que cubre cada columna, igual que la
                // pantalla: exportado sin eso, el Excel perdía de qué temporada hablaba.
                const periodos = ExcelExporter.periodosDeSolapa(solapa);
                const enCurso = TemporadaServidor.ventaEnCurso();

                resultado['Venta Ver. Anterior'] = ExcelExporter.buscarVentaHistorica(item, 'VERANO');
                // De qué temporada salió la base: varía por fila, así que no se puede
                // poner en el encabezado como en la pantalla.
                resultado['Temporada Base Ver.'] = item.TEMPORADA_BASE_VERANO || '';
                resultado[`Proy. Verano (${periodos.verano})`] = item.VENTA_PROY_VERANO || 0;
                if (enCurso && enCurso.tipo === 'VERANO') Object.assign(resultado, ExcelExporter.columnasTemporadaEnCurso(item, enCurso));

                resultado['Índice Inv. LP'] = indices.invierno.lp;
                resultado['Índice Inv. Total Empresa'] = indices.invierno.te;
                resultado['Venta Inv. Anterior'] = ExcelExporter.buscarVentaHistorica(item, 'INVIERNO');
                resultado['Temporada Base Inv.'] = item.TEMPORADA_BASE_INVIERNO || '';
                resultado[`Proy. Invierno (${periodos.invierno})`] = item.VENTA_PROY_INVIERNO || 0;
                if (enCurso && enCurso.tipo === 'INVIERNO') Object.assign(resultado, ExcelExporter.columnasTemporadaEnCurso(item, enCurso));
                
                resultado['Compra Proyectada'] = item.COMPRA_PROYECTADA || 0;

                // Compra abierta por tramo, pegada al total que abre. Es el dato con el
                // que el cashflow proyecta pagos: lo de cada temporada llega en
                // contenedores distintos y se paga en meses distintos, así que el total
                // solo no alcanza. El encabezado dice si el tramo es el objetivo de la
                // solapa y si es comprable, porque el resto de la temporada en curso
                // suma al total pero NO es mercadería a comprar.
                (item.TRAMOS || []).forEach(tramo => {
                    const nombre = (tramo.es_resto ? 'Resto ' : '') + tramo.temporada_codigo;
                    const etiqueta = tramo.es_comprable
                        ? (tramo.es_objetivo ? `Compra ${nombre} (OBJETIVO)` : `Compra ${nombre}`)
                        : `Sin cubrir ${nombre}`;

                    resultado[etiqueta] = tramo.compra || 0;

                    // El déficit de cobertura va aparte: es stock de seguridad sin
                    // reponer, no venta proyectada, y finanzas puede querer tratarlo
                    // distinto. La columna existe para TODAS las filas si algún rubro
                    // tiene déficit en ese tramo, para que la hoja no quede despareja.
                    if (tramosConDeficit.has(tramo.orden)) {
                        resultado[`Déficit cobertura ${nombre}`] = tramo.compra_deficit_cobertura || 0;
                    }
                });

                // Agregar columnas dinámicas (Historial de años al final)
                // Excluimos las columnas que ya agregamos manualmente o que son de sistema
                const columnasIgnorar = [
                    'ID', 'RUBRO', 'CATEGORIA', 'CATEGORIA_PADRE', 'DESCRIPCION',
                    'STOCK_PROYECTADO', 'STOCK_ACTUAL', 'INDICE_VAR_ORIGINAL', 'INDICE_VARIACION',
                    'INDICE_VARIACION_INVIERNO', 'COMPRA_PROYECTADA', 'VENTA_PROY_VERANO', 'VENTA_PROY_INVIERNO',
                    'VTA_VERANO_ACTUAL', 'VTA_INVIERNO_ACTUAL',
                    // Bases del cálculo, ya exportadas como "Venta Ver./Inv. Anterior".
                    'VENTA_VERANO_ANTERIOR', 'VENTA_INVIERNO_ANTERIOR',
                    // Temporada de la que salió cada base: se exporta aparte, más abajo.
                    'TEMPORADA_BASE_VERANO', 'TEMPORADA_BASE_INVIERNO',
                    // Componentes del stock proyectado: no son ventas históricas.
                    'CANT_STOCK', 'CANT_STOCK_GUARDAR', 'STOCK_COBERTURA',
                    'CANT_PEND_OC_VERANO', 'CANT_PEND_OC_INVIERNO', 'CANT_PEND_OC_ATEMPORAL'
                ];

                Object.keys(item).forEach(key => {
                    // Si es una columna de historial (contiene VERANO, INVIERNO o VTA_)
                    // Misma regla que la tabla: solo columnas de una temporada
                    // identificable. Con "contiene VTA_" se colaban VTA_60D_LP y compañía.
                    if (!columnasIgnorar.includes(key) &&
                        TemporadaServidor.temporadaDeColumna(key) &&
                        !key.startsWith('PROY') &&
                        !isNaN(parseFloat(item[key]))) {
                        
                        // Misma etiqueta que la tabla HTML: la traduce el servidor
                        // (VTA_VERANO_26 -> "VER 25-26"). Antes acá se repetía a mano el
                        // parche de restarle 1 al año del verano, y el invierno quedaba
                        // con otro formato, así que el Excel y la pantalla no coincidían.
                        resultado[TemporadaServidor.etiquetaHistorica(key)] = item[key];
                    }
                });

                // La nota de la fila, al final: es texto libre y en el medio cortaría
                // la lectura de los números.
                resultado['Nota'] = item.NOTA || '';
            }
            return resultado;
        });
    }

    /**
     * Vendido (real), Resto (proyectado) y Total de la temporada en curso, con los
     * mismos valores que la pantalla. El encabezado dice qué es real y qué proyectado,
     * porque en el Excel no hay colores ni tooltips que lo aclaren.
     */
    static columnasTemporadaEnCurso(item, info) {
        const vendido = TablaRendererUtils.vendidoTemporadaEnCurso(item);
        const resto = TablaRendererUtils.restoTemporadaEnCurso(item);
        const corte = info.fecha_corte ? ` al ${UIUtils.formatearFechaCorta(info.fecha_corte)}` : '';

        return {
            [`Vendido ${info.codigo} (real${corte})`]: vendido === null ? '—' : vendido,
            [`Resto ${info.codigo} (proyectado)`]: resto === null ? '—' : resto,
            [`Total ${info.codigo} (real + proyectado)`]: (vendido === null || resto === null) ? '—' : vendido + resto
        };
    }

    /**
     * Índices LP y Total Empresa de una fila, por temporada, como se ven en pantalla.
     * Sin índice TE la columna TE sale con "—", igual que en la tabla.
     */
    static indicesDeFila(item) {
        const editable = (temporada) => parseFloat(temporada === 'invierno'
            ? (item.INDICE_VARIACION_INVIERNO ?? item.INDICE_VARIACION ?? 1)
            : (item.INDICE_VARIACION ?? 1)).toFixed(2);
        const lpDelSp = parseFloat(item.INDICE_LP ?? item.INDICE_VARIACION ?? 1).toFixed(2);

        const de = (temporada) => item.TE_DISPONIBLE
            ? { lp: lpDelSp, te: editable(temporada) }
            : { lp: editable(temporada), te: '—' };

        return { verano: de('verano'), invierno: de('invierno') };
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

    /**
     * Venta anterior de una fila: la misma que muestra la pantalla y la que usó el
     * servidor para proyectar. Antes acá se buscaba por su cuenta con el año del
     * navegador y, si no encontraba, tomaba la PRIMERA columna VTA_VERANO_* del
     * registro, que es la más vieja: en esos casos el Excel mostraba otra base que
     * la pantalla.
     */
    static buscarVentaHistorica(item, temporada) {
        return TemporadaServidor.ventaAnteriorDe(item, temporada);
    }

    /**
     * Fila TOTALES de una hoja de compra, al final.
     *
     * Suma las columnas numéricas de las filas exportadas. Los índices no se suman:
     * debajo de los LP y TE va el índice general de cada universo, ponderado por
     * venta, igual que en la pantalla (TotalesCompra.indiceGeneral). Los índices
     * originales y las columnas de texto quedan vacíos.
     */
    static filaTotalesPresupuesto(filas, datos) {
        if (!filas.length) return null;

        const totales = { ventas60: { lp: 0, lpAnt: 0, te: 0, teAnt: 0, hayLp: false, hayTe: false } };
        datos.forEach(item => {
            if (item.VTA_60D_LP !== null && item.VTA_60D_LP !== undefined) {
                totales.ventas60.hayLp = true;
                totales.ventas60.lp += parseFloat(item.VTA_60D_LP || 0);
                totales.ventas60.lpAnt += parseFloat(item.VTA_60D_ANT_LP || 0);
            }
            if (item.VTA_60D_TE !== null && item.VTA_60D_TE !== undefined) {
                totales.ventas60.hayTe = true;
                totales.ventas60.te += parseFloat(item.VTA_60D_TE || 0);
                totales.ventas60.teAnt += parseFloat(item.VTA_60D_ANT_TE || 0);
            }
        });
        const general = (tipo) => {
            const g = TotalesCompra.indiceGeneral(totales, tipo);
            return g.valor === null ? '—' : `General ${g.texto}`;
        };

        const fila = {};
        Object.keys(filas[0]).forEach(col => {
            if (col === 'Rubro') { fila[col] = 'TOTALES'; return; }
            if (/^Índice .* LP$/.test(col)) { fila[col] = general('lp'); return; }
            if (/^Índice .* Total Empresa$/.test(col)) { fila[col] = general('te'); return; }
            if (col.startsWith('Índice')) { fila[col] = ''; return; }

            // Una columna se suma si todos sus valores son números: así quedan afuera
            // las de texto (temporada base, nota) y las que traen "—".
            const valores = filas.map(f => f[col]);
            fila[col] = valores.every(v => typeof v === 'number' || (v !== '' && v !== null && !isNaN(v) && typeof v !== 'boolean'))
                ? valores.reduce((a, v) => a + (parseFloat(v) || 0), 0)
                : '';
        });
        return fila;
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
                    const totalesVerano = ExcelExporter.filaTotalesPresupuesto(datosPreparados, datosCrudos);
                    if (totalesVerano) datosPreparados.push(totalesVerano);
                    nombreHoja = 'Compra Verano';
                    nombreArchivo = `compra_verano_${ExcelExporter.obtenerFechaHora()}.xlsx`;
                    break;
                
                case 'invierno':
                    datosCrudos = ExcelExporter.obtenerDatosFiltradosOCompletos('invierno');
                    datosPreparados = ExcelExporter.prepararDatosPresupuesto(datosCrudos, 'invierno');
                    const totalesInvierno = ExcelExporter.filaTotalesPresupuesto(datosPreparados, datosCrudos);
                    if (totalesInvierno) datosPreparados.push(totalesInvierno);
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