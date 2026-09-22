
// Gestor de Contenedores (OC Pendientes agrupadas por Contenedor + Proveedor + Rubro)
// Archivo: presupuestos/js/contenedores-manager.js

class ContenedoresManager {
    static datos = [];
    static datosFiltrados = [];
    static filtrosActivos = {};
    static timeoutBusqueda = null;
    static modoAgrupacion = 'detalle'; // 'detalle' | 'rubro' | 'proveedor' | 'contenedor'

    /**
     * Cargar datos de contenedores desde la API
     */
    static async cargarDatos() {
        try {
            UIUtils.mostrarLoading(true);

            const response = await APIClient.llamarAPI('contenedores-detalle');

            if (response.success && response.data) {
                ContenedoresManager.datos = Array.isArray(response.data) ? response.data : [];
                ContenedoresManager.datosFiltrados = [...ContenedoresManager.datos];

                console.log(`Contenedores cargados: ${ContenedoresManager.datos.length} registros`);

                ContenedoresManager.renderizarTabla();
                ContenedoresManager.actualizarContadores();
                ContenedoresManager.mostrarResumen();
                await ContenedoresManager.cargarFiltros();

                UIUtils.mostrarAlerta(
                    `${ContenedoresManager.datos.length} registros de contenedores cargados correctamente`,
                    'success'
                );
            } else {
                throw new Error(response.message || 'No se recibieron datos válidos');
            }
        } catch (error) {
            console.error('Error cargando contenedores:', error);
            ContenedoresManager.datos = [];
            ContenedoresManager.datosFiltrados = [];
            UIUtils.mostrarAlerta('Error al cargar datos de contenedores: ' + error.message, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    /**
     * Agrupar datos según el modo activo
     * @param {Array} datos - Datos a agrupar (ya filtrados)
     * @param {string} modo - 'detalle' | 'rubro' | 'proveedor' | 'contenedor'
     * @returns {Array}
     */
    static agruparDatos(datos, modo) {
        if (modo === 'detalle' || !modo) return datos;

        const campoMap = {
            rubro:      'RUBRO',
            proveedor:  'NOM_PROVEE',
            contenedor: 'CONTENEDOR'
        };

        const campo = campoMap[modo];
        if (!campo) return datos;

        const mapa = {};
        datos.forEach(item => {
            const k = item[campo] || 'Sin asignar';
            if (!mapa[k]) {
                mapa[k] = { [campo]: k, VERANO: 0, INVIERNO: 0, TOTAL: 0 };
            }
            mapa[k].VERANO   += item.VERANO   || 0;
            mapa[k].INVIERNO += item.INVIERNO || 0;
            mapa[k].TOTAL    += item.TOTAL    || 0;
        });

        return Object.values(mapa).sort((a, b) =>
            (a[campo] || '').localeCompare(b[campo] || '', 'es', { sensitivity: 'base' })
        );
    }

    /**
     * Cambiar el modo de agrupación y re-renderizar
     */
    static cambiarModo(modo) {
        ContenedoresManager.modoAgrupacion = modo;

        // Actualizar botones activos
        document.querySelectorAll('#grupo-modos-cont button').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.modo === modo);
        });

        ContenedoresManager.renderizarTabla();
        ContenedoresManager.actualizarContadores();
    }

    /**
     * Definición de columnas por modo
     */
    static _configModos() {
        return {
            detalle: {
                thead: `
                    <tr>
                        <th class="text-center">Contenedor</th>
                        <th>Proveedor</th>
                        <th>Rubro</th>
                        <th class="text-center bg-warning text-dark">Verano</th>
                        <th class="text-center bg-info text-dark">Invierno</th>
                        <th class="text-center bg-primary text-white">Total</th>
                    </tr>`,
                colspan: 6,
                fila: item => `
                    <tr class="fila-datos">
                        <td class="text-center"><strong>${item.CONTENEDOR || 'Sin asignar'}</strong></td>
                        <td>${item.NOM_PROVEE || ''}</td>
                        <td><span class="badge bg-secondary">${item.RUBRO || ''}</span></td>
                        <td class="text-end ${FormatoUtils.obtenerClaseValor(item.VERANO)}">${FormatoUtils.formatearNumero(item.VERANO || 0)}</td>
                        <td class="text-end ${FormatoUtils.obtenerClaseValor(item.INVIERNO)}">${FormatoUtils.formatearNumero(item.INVIERNO || 0)}</td>
                        <td class="text-end ${FormatoUtils.obtenerClaseValor(item.TOTAL)}"><strong>${FormatoUtils.formatearNumero(item.TOTAL || 0)}</strong></td>
                    </tr>`
            },
            rubro: {
                thead: `
                    <tr>
                        <th>Rubro</th>
                        <th class="text-center bg-warning text-dark">Verano</th>
                        <th class="text-center bg-info text-dark">Invierno</th>
                        <th class="text-center bg-primary text-white">Total</th>
                    </tr>`,
                colspan: 4,
                fila: item => `
                    <tr class="fila-datos">
                        <td><span class="badge bg-secondary">${item.RUBRO || ''}</span></td>
                        <td class="text-end ${FormatoUtils.obtenerClaseValor(item.VERANO)}">${FormatoUtils.formatearNumero(item.VERANO || 0)}</td>
                        <td class="text-end ${FormatoUtils.obtenerClaseValor(item.INVIERNO)}">${FormatoUtils.formatearNumero(item.INVIERNO || 0)}</td>
                        <td class="text-end ${FormatoUtils.obtenerClaseValor(item.TOTAL)}"><strong>${FormatoUtils.formatearNumero(item.TOTAL || 0)}</strong></td>
                    </tr>`
            },
            proveedor: {
                thead: `
                    <tr>
                        <th>Proveedor</th>
                        <th class="text-center bg-warning text-dark">Verano</th>
                        <th class="text-center bg-info text-dark">Invierno</th>
                        <th class="text-center bg-primary text-white">Total</th>
                    </tr>`,
                colspan: 4,
                fila: item => `
                    <tr class="fila-datos">
                        <td>${item.NOM_PROVEE || ''}</td>
                        <td class="text-end ${FormatoUtils.obtenerClaseValor(item.VERANO)}">${FormatoUtils.formatearNumero(item.VERANO || 0)}</td>
                        <td class="text-end ${FormatoUtils.obtenerClaseValor(item.INVIERNO)}">${FormatoUtils.formatearNumero(item.INVIERNO || 0)}</td>
                        <td class="text-end ${FormatoUtils.obtenerClaseValor(item.TOTAL)}"><strong>${FormatoUtils.formatearNumero(item.TOTAL || 0)}</strong></td>
                    </tr>`
            },
            contenedor: {
                thead: `
                    <tr>
                        <th class="text-center">Contenedor</th>
                        <th class="text-center bg-warning text-dark">Verano</th>
                        <th class="text-center bg-info text-dark">Invierno</th>
                        <th class="text-center bg-primary text-white">Total</th>
                    </tr>`,
                colspan: 4,
                fila: item => `
                    <tr class="fila-datos">
                        <td class="text-center"><strong>${item.CONTENEDOR || 'Sin asignar'}</strong></td>
                        <td class="text-end ${FormatoUtils.obtenerClaseValor(item.VERANO)}">${FormatoUtils.formatearNumero(item.VERANO || 0)}</td>
                        <td class="text-end ${FormatoUtils.obtenerClaseValor(item.INVIERNO)}">${FormatoUtils.formatearNumero(item.INVIERNO || 0)}</td>
                        <td class="text-end ${FormatoUtils.obtenerClaseValor(item.TOTAL)}"><strong>${FormatoUtils.formatearNumero(item.TOTAL || 0)}</strong></td>
                    </tr>`
            }
        };
    }

    /**
     * Renderizar tabla según modo activo
     */
    static renderizarTabla() {
        const thead = document.getElementById('thead-contenedores');
        const tbody = document.getElementById('tbody-contenedores');
        if (!tbody) return;

        const modo = ContenedoresManager.modoAgrupacion;
        const config = ContenedoresManager._configModos()[modo] || ContenedoresManager._configModos()['detalle'];
        const datosAgrupados = ContenedoresManager.agruparDatos(ContenedoresManager.datosFiltrados, modo);

        // Actualizar encabezado
        if (thead) {
            thead.innerHTML = config.thead;
        }

        if (datosAgrupados.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${config.colspan}" class="text-center text-muted py-4">
                        <i class="fas fa-inbox"></i><br>
                        No hay registros para mostrar
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = datosAgrupados.map(config.fila).join('');
    }

    /**
     * Actualizar contador mostrando registros según modo activo
     */
    static actualizarContadores() {
        const modo = ContenedoresManager.modoAgrupacion;
        const datosAgrupados = ContenedoresManager.agruparDatos(ContenedoresManager.datosFiltrados, modo);
        const contador = document.getElementById('count-contenedores');
        if (contador) {
            contador.textContent = `${FormatoUtils.formatearNumero(datosAgrupados.length)} registros`;
            contador.classList.add('actualizado');
            setTimeout(() => contador.classList.remove('actualizado'), 500);
        }
    }

    /**
     * Poblar selects de proveedor y rubro desde los datos cargados
     */
    static async cargarFiltros() {
        try {
            if (!ContenedoresManager.datos || ContenedoresManager.datos.length === 0) return;

            // Proveedores únicos
            const proveedores = [...new Set(
                ContenedoresManager.datos
                    .map(item => item.NOM_PROVEE)
                    .filter(p => p && p.trim() !== '')
            )].sort();

            const selectProveedor = document.getElementById('filtro-proveedor-cont');
            if (selectProveedor) {
                selectProveedor.innerHTML = '<option value="">Todos los proveedores</option>';
                proveedores.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p;
                    opt.textContent = p;
                    selectProveedor.appendChild(opt);
                });
            }

            // Rubros únicos
            const rubros = [...new Set(
                ContenedoresManager.datos
                    .map(item => item.RUBRO)
                    .filter(r => r && r.trim() !== '')
            )].sort();

            const selectRubro = document.getElementById('filtro-rubro-cont');
            if (selectRubro) {
                selectRubro.innerHTML = '<option value="">Todos los rubros</option>';
                rubros.forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r;
                    opt.textContent = r;
                    selectRubro.appendChild(opt);
                });
            }

            console.log(`Contenedores: ${proveedores.length} proveedores, ${rubros.length} rubros cargados`);
        } catch (error) {
            console.error('Error cargando filtros de contenedores:', error);
        }
    }

    /**
     * Mostrar resumen de totales (siempre sobre datos filtrados, antes de agrupar)
     */
    static mostrarResumen() {
        const totales = ContenedoresManager.datosFiltrados.reduce((acc, item) => {
            acc.verano   += item.VERANO   || 0;
            acc.invierno += item.INVIERNO || 0;
            return acc;
        }, { verano: 0, invierno: 0 });

        const totalGeneral = totales.verano + totales.invierno;

        const bV = document.getElementById('badge-cont-verano');
        const bI = document.getElementById('badge-cont-invierno');
        const bT = document.getElementById('badge-cont-total');

        if (bV) bV.textContent = FormatoUtils.formatearNumero(totales.verano);
        if (bI) bI.textContent = FormatoUtils.formatearNumero(totales.invierno);
        if (bT) bT.textContent = FormatoUtils.formatearNumero(totalGeneral);

        const resumen = document.getElementById('resumen-contenedores-superior');
        if (resumen) resumen.classList.remove('d-none');
        // Al aparecer la barra, la tabla de abajo tiene menos alto disponible.
        if (window.ajustarAltura) window.ajustarAltura();
    }

    /**
     * Búsqueda instantánea sobre CONTENEDOR, NOM_PROVEE y RUBRO
     */
    static buscarInstantanea() {
        const input = document.getElementById('search-contenedores');
        if (!input) return;

        const termino = input.value.toLowerCase().trim();

        if (termino.length === 0) {
            ContenedoresManager.datosFiltrados = [...ContenedoresManager.datos];
        } else {
            ContenedoresManager.datosFiltrados = ContenedoresManager.datos.filter(item =>
                (item.CONTENEDOR && item.CONTENEDOR.toLowerCase().includes(termino)) ||
                (item.NOM_PROVEE  && item.NOM_PROVEE.toLowerCase().includes(termino))  ||
                (item.RUBRO       && item.RUBRO.toLowerCase().includes(termino))
            );
        }

        ContenedoresManager.aplicarFiltros();
    }

    /**
     * Filtrar por proveedor
     */
    static filtrarPorProveedor() {
        const select = document.getElementById('filtro-proveedor-cont');
        if (!select) return;
        ContenedoresManager.filtrosActivos.proveedor = select.value;
        ContenedoresManager.aplicarFiltros();
    }

    /**
     * Filtrar por rubro
     */
    static filtrarPorRubro() {
        const select = document.getElementById('filtro-rubro-cont');
        if (!select) return;
        ContenedoresManager.filtrosActivos.rubro = select.value;
        ContenedoresManager.aplicarFiltros();
    }

    /**
     * Filtrar por fecha
     */
    static filtrarPorFecha() {
        ContenedoresManager.filtrosActivos.fechaDesde = document.getElementById('fecha-desde-cont')?.value || '';
        ContenedoresManager.filtrosActivos.fechaHasta = document.getElementById('fecha-hasta-cont')?.value || '';
        ContenedoresManager.aplicarFiltros();
    }

    /**
     * Filtrar por temporada
     */
    static filtrarPorTemporada() {
        const select = document.getElementById('filtro-temporada-cont');
        if (!select) return;
        ContenedoresManager.filtrosActivos.temporada = select.value;
        ContenedoresManager.aplicarFiltros();
    }

    /**
     * Aplicar todos los filtros activos sobre los datos en memoria
     */
    static aplicarFiltros() {
        let datos = [...ContenedoresManager.datos];

        // Búsqueda de texto
        const termino = (document.getElementById('search-contenedores')?.value || '').toLowerCase().trim();
        if (termino.length > 0) {
            datos = datos.filter(item =>
                (item.CONTENEDOR && item.CONTENEDOR.toLowerCase().includes(termino)) ||
                (item.NOM_PROVEE  && item.NOM_PROVEE.toLowerCase().includes(termino))  ||
                (item.RUBRO       && item.RUBRO.toLowerCase().includes(termino))
            );
        }

        // Filtro de proveedor
        if (ContenedoresManager.filtrosActivos.proveedor) {
            datos = datos.filter(item => item.NOM_PROVEE === ContenedoresManager.filtrosActivos.proveedor);
        }

        // Filtro de rubro
        if (ContenedoresManager.filtrosActivos.rubro) {
            datos = datos.filter(item => item.RUBRO === ContenedoresManager.filtrosActivos.rubro);
        }

        // Filtro de temporada
        if (ContenedoresManager.filtrosActivos.temporada) {
            datos = datos.filter(item => {
                switch (ContenedoresManager.filtrosActivos.temporada) {
                    case 'verano':   return (item.VERANO   || 0) > 0;
                    case 'invierno': return (item.INVIERNO || 0) > 0;
                    default:         return true;
                }
            });
        }

        ContenedoresManager.datosFiltrados = datos;
        ContenedoresManager.renderizarTabla();
        ContenedoresManager.actualizarContadores();
        ContenedoresManager.mostrarResumen();
    }

    /**
     * Limpiar todos los filtros
     */
    static limpiarFiltros() {
        ['search-contenedores', 'filtro-proveedor-cont', 'filtro-rubro-cont',
         'fecha-desde-cont', 'fecha-hasta-cont', 'filtro-temporada-cont']
            .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });

        ContenedoresManager.filtrosActivos = {};
        ContenedoresManager.datosFiltrados = [...ContenedoresManager.datos];
        ContenedoresManager.renderizarTabla();
        ContenedoresManager.actualizarContadores();
        ContenedoresManager.mostrarResumen();

        UIUtils.mostrarAlerta('Filtros limpiados', 'info', 2000);
    }

    /**
     * Preparar datos para exportar según modo activo
     */
    static prepararDatosExcel(datos) {
        const modo = ContenedoresManager.modoAgrupacion;
        return datos.map(item => {
            const res = {};
            if (modo === 'detalle') {
                res['Contenedor'] = item.CONTENEDOR || '';
                res['Proveedor']  = item.NOM_PROVEE  || '';
                res['Rubro']      = item.RUBRO       || '';
            } else if (modo === 'rubro') {
                res['Rubro'] = item.RUBRO || '';
            } else if (modo === 'proveedor') {
                res['Proveedor'] = item.NOM_PROVEE || '';
            } else if (modo === 'contenedor') {
                res['Contenedor'] = item.CONTENEDOR || '';
            }
            res['Verano']   = item.VERANO   || 0;
            res['Invierno'] = item.INVIERNO || 0;
            res['Total']    = item.TOTAL    || 0;
            return res;
        });
    }

    /**
     * Exportar a Excel usando el ExcelExporter unificado
     */
    static async exportarExcel() {
        try {
            if (ContenedoresManager.datosFiltrados.length === 0) {
                UIUtils.mostrarAlerta('No hay datos para exportar', 'warning');
                return;
            }

            UIUtils.mostrarAlerta('Generando archivo Excel...', 'info', 2000);

            const modo = ContenedoresManager.modoAgrupacion;
            const datosAgrupados = ContenedoresManager.agruparDatos(ContenedoresManager.datosFiltrados, modo);
            const datosPreparados = ContenedoresManager.prepararDatosExcel(datosAgrupados);

            const nombreHojaMap = {
                detalle:    'Contenedores',
                rubro:      'Por Rubro',
                proveedor:  'Por Proveedor',
                contenedor: 'Por Contenedor'
            };
            const nombreHoja = nombreHojaMap[modo] || 'Contenedores';
            const nombreArchivo = `contenedores_${modo}_${ExcelExporter.obtenerFechaHora()}.xlsx`;

            await ExcelExporter.exportarExcel(datosPreparados, nombreHoja, nombreArchivo);

            UIUtils.mostrarAlerta(`Excel exportado: ${datosPreparados.length} registros`, 'success');
        } catch (error) {
            console.error('Error exportando Excel de contenedores:', error);
            UIUtils.mostrarAlerta('Error al exportar Excel: ' + error.message, 'error');
        }
    }
}


// Funciones globales para compatibilidad con HTML onclick
function buscarContenedores() {
    if (ContenedoresManager.timeoutBusqueda) clearTimeout(ContenedoresManager.timeoutBusqueda);
    ContenedoresManager.timeoutBusqueda = setTimeout(() => ContenedoresManager.buscarInstantanea(), 300);
}

function filtrarPorProveedorContenedores() {
    ContenedoresManager.filtrarPorProveedor();
}

function filtrarPorRubroContenedores() {
    ContenedoresManager.filtrarPorRubro();
}

function filtrarPorFechaContenedores() {
    ContenedoresManager.filtrarPorFecha();
}

function filtrarPorTemporadaContenedores() {
    ContenedoresManager.filtrarPorTemporada();
}

// Hacer disponible globalmente
window.ContenedoresManager = ContenedoresManager;
