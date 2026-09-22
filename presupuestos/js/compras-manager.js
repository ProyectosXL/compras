
// Gestor de Compras Pendientes
// Archivo: presupuestos/js/compras-manager.js

class ComprasManager {
    static datos = [];
    static datosFiltrados = [];
    static filtrosActivos = {};
    static timeoutBusqueda = null;

    /**
     * Cargar datos de compras pendientes - CORREGIDA
     */
    static async cargarDatos() {
        try {
            UIUtils.mostrarLoading(true);
            
            // Llamar a la API para obtener compras pendientes
            const response = await APIClient.llamarAPI('compras-detalle');
            
            if (response.success && response.data) {
                ComprasManager.datos = Array.isArray(response.data) ? response.data : [];
                ComprasManager.datosFiltrados = [...ComprasManager.datos];
                
                console.log(`Datos cargados: ${ComprasManager.datos.length} registros`);
                
                // Renderizar tabla y actualizar UI
                ComprasManager.renderizarTabla();
                ComprasManager.actualizarContadores();
                ComprasManager.mostrarResumen();
                
                // Cargar filtros DESPUÉS de tener los datos
                await ComprasManager.cargarFiltros();
                
                UIUtils.mostrarAlerta(
                    `${ComprasManager.datos.length} registros de compras cargados correctamente`,
                    'success'
                );
            } else {
                throw new Error(response.message || 'No se recibieron datos válidos');
            }
        } catch (error) {
            console.error('Error cargando compras:', error);
            
            // Inicializar arrays vacíos para evitar errores
            ComprasManager.datos = [];
            ComprasManager.datosFiltrados = [];
            
            UIUtils.mostrarAlerta(
                'Error al cargar datos de compras: ' + error.message,
                'error'
            );
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    /**
     * Renderizar tabla de compras
     */
    static renderizarTabla() {
        const tbody = document.getElementById('tbody-compras-detalle');
        if (!tbody) return;

        if (ComprasManager.datosFiltrados.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="12" class="text-center text-muted py-4">
                        <i class="fas fa-inbox"></i><br>
                        No hay registros para mostrar
                    </td>
                </tr>
            `;
            return;
        }

        const html = ComprasManager.datosFiltrados.map(item => {
            const total = (item.VERANO || 0) + (item.INVIERNO || 0) + (item.ATEMPORAL || 0);
            
            return `
                <tr class="fila-datos">
                    <td class="text-center">${item.FEC_EMISIO || ''}</td>
                    <td class="text-center">
                        <strong>${item.N_ORDEN_CO || ''}</strong>
                    </td>
                    <td>${item.NOM_PROVEE || ''}</td>
                    <td class="text-center">
                        <code>${item.COD_ARTICU || ''}</code>
                    </td>
                    <td>${item.DESCRIPCIO || ''}</td>
                    <td>
                        <span class="badge bg-secondary">${item.RUBRO || ''}</span>
                    </td>
                    <td>${item.CATEGORIA_PADRE || ''}</td>
                    <td class="text-end ${FormatoUtils.obtenerClaseValor(item.VERANO)}">
                        ${FormatoUtils.formatearNumero(item.VERANO || 0)}
                    </td>
                    <td class="text-end ${FormatoUtils.obtenerClaseValor(item.INVIERNO)}">
                        ${FormatoUtils.formatearNumero(item.INVIERNO || 0)}
                    </td>
                    <td class="text-end ${FormatoUtils.obtenerClaseValor(item.ATEMPORAL)}">
                        ${FormatoUtils.formatearNumero(item.ATEMPORAL || 0)}
                    </td>
                    <td class="text-end ${FormatoUtils.obtenerClaseValor(total)}">
                        <strong>${FormatoUtils.formatearNumero(total)}</strong>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-info" 
                                onclick="ComprasManager.verDetalle('${item.N_ORDEN_CO}')"
                                title="Ver detalle">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.innerHTML = html;
    }

    /**
     * Actualizar contadores
     */
    static actualizarContadores() {
        const contador = document.getElementById('count-compras-detalle');
        if (contador) {
            contador.textContent = `${FormatoUtils.formatearNumero(ComprasManager.datosFiltrados.length)} registros`;
            contador.classList.add('actualizado');
            setTimeout(() => contador.classList.remove('actualizado'), 500);
        }
    }

    /**
     * Cargar filtros (proveedores y rubros) - CORREGIDA
     */
    static async cargarFiltros() {
        try {
            // Verificar que hay datos cargados
            if (!ComprasManager.datos || ComprasManager.datos.length === 0) {
                console.warn('No hay datos para generar filtros');
                return;
            }

            // Cargar proveedores únicos
            const proveedores = [...new Set(
                ComprasManager.datos
                    .map(item => item.NOM_PROVEE)
                    .filter(p => p && p.trim() !== '')
            )].sort();
            
            const selectProveedor = document.getElementById('filtro-proveedor');
            if (selectProveedor) {
                selectProveedor.innerHTML = '<option value="">Todos los proveedores</option>';
                proveedores.forEach(proveedor => {
                    const option = document.createElement('option');
                    option.value = proveedor;
                    option.textContent = proveedor;
                    selectProveedor.appendChild(option);
                });
                console.log(`Cargados ${proveedores.length} proveedores`);
            }

            // Cargar rubros únicos
            const rubros = [...new Set(
                ComprasManager.datos
                    .map(item => item.RUBRO)
                    .filter(r => r && r.trim() !== '')
            )].sort();
            
            const selectRubro = document.getElementById('filtro-rubro');
            if (selectRubro) {
                selectRubro.innerHTML = '<option value="">Todos los rubros</option>';
                rubros.forEach(rubro => {
                    const option = document.createElement('option');
                    option.value = rubro;
                    option.textContent = rubro;
                    selectRubro.appendChild(option);
                });
                console.log(`Cargados ${rubros.length} rubros`);
            }
            
        } catch (error) {
            console.error('Error cargando filtros:', error);
            UIUtils.mostrarAlerta('Error al cargar filtros de búsqueda', 'warning');
        }
    }

    /**
     * Mostrar resumen de totales (ahora en la parte superior)
     */
    static mostrarResumen() {
        const totales = ComprasManager.datosFiltrados.reduce((acc, item) => {
            acc.verano += item.VERANO || 0;
            acc.invierno += item.INVIERNO || 0;
            acc.atemporal += item.ATEMPORAL || 0;
            return acc;
        }, { verano: 0, invierno: 0, atemporal: 0 });

        const totalGeneral = totales.verano + totales.invierno + totales.atemporal;

        // Actualizar badges en la parte superior
        const badgeVerano = document.getElementById('badge-total-verano');
        const badgeInvierno = document.getElementById('badge-total-invierno');
        const badgeAtemporal = document.getElementById('badge-total-atemporal');
        const badgeGeneral = document.getElementById('badge-total-general');

        if (badgeVerano) badgeVerano.textContent = FormatoUtils.formatearNumero(totales.verano);
        if (badgeInvierno) badgeInvierno.textContent = FormatoUtils.formatearNumero(totales.invierno);
        if (badgeAtemporal) badgeAtemporal.textContent = FormatoUtils.formatearNumero(totales.atemporal);
        if (badgeGeneral) badgeGeneral.textContent = FormatoUtils.formatearNumero(totalGeneral);

        // Mostrar el contenedor de resumen
        const contenedorResumen = document.getElementById('resumen-compras-superior');
        if (contenedorResumen) {
            contenedorResumen.classList.remove('d-none');
            // Al aparecer la barra, la tabla de abajo tiene menos alto disponible.
            if (window.ajustarAltura) window.ajustarAltura();
        }
    }

    /**
     * Búsqueda instantánea
     */
    static buscarInstantanea() {
        const input = document.getElementById('search-compras-detalle');
        if (!input) return;

        const termino = input.value.toLowerCase().trim();
        
        if (termino.length === 0) {
            ComprasManager.datosFiltrados = [...ComprasManager.datos];
        } else {
            ComprasManager.datosFiltrados = ComprasManager.datos.filter(item => {
                return (
                    (item.N_ORDEN_CO && item.N_ORDEN_CO.toLowerCase().includes(termino)) ||
                    (item.NOM_PROVEE && item.NOM_PROVEE.toLowerCase().includes(termino)) ||
                    (item.COD_ARTICU && item.COD_ARTICU.toLowerCase().includes(termino)) ||
                    (item.DESCRIPCIO && item.DESCRIPCIO.toLowerCase().includes(termino)) ||
                    (item.RUBRO && item.RUBRO.toLowerCase().includes(termino)) ||
                    (item.CATEGORIA_PADRE && item.CATEGORIA_PADRE.toLowerCase().includes(termino))
                );
            });
        }

        ComprasManager.aplicarFiltros();
    }

    /**
     * Filtrar por proveedor
     */
    static filtrarPorProveedor() {
        const select = document.getElementById('filtro-proveedor');
        if (!select) return;

        ComprasManager.filtrosActivos.proveedor = select.value;
        ComprasManager.aplicarFiltros();
    }

    /**
     * Filtrar por rubro
     */
    static filtrarPorRubro() {
        const select = document.getElementById('filtro-rubro');
        if (!select) return;

        ComprasManager.filtrosActivos.rubro = select.value;
        ComprasManager.aplicarFiltros();
    }

    /**
     * Filtrar por fecha
     */
    static filtrarPorFecha() {
        const fechaDesde = document.getElementById('fecha-desde').value;
        const fechaHasta = document.getElementById('fecha-hasta').value;

        ComprasManager.filtrosActivos.fechaDesde = fechaDesde;
        ComprasManager.filtrosActivos.fechaHasta = fechaHasta;
        ComprasManager.aplicarFiltros();
    }

    /**
     * Filtrar por temporada
     */
    static filtrarPorTemporada() {
        const select = document.getElementById('filtro-temporada');
        if (!select) return;

        ComprasManager.filtrosActivos.temporada = select.value;
        ComprasManager.aplicarFiltros();
    }

    /**
     * Aplicar todos los filtros
     */
    static aplicarFiltros() {
        let datos = [...ComprasManager.datos];

        // Aplicar filtro de búsqueda
        const termino = document.getElementById('search-compras-detalle').value.toLowerCase().trim();
        if (termino.length > 0) {
            datos = datos.filter(item => {
                return (
                    (item.N_ORDEN_CO && item.N_ORDEN_CO.toLowerCase().includes(termino)) ||
                    (item.NOM_PROVEE && item.NOM_PROVEE.toLowerCase().includes(termino)) ||
                    (item.COD_ARTICU && item.COD_ARTICU.toLowerCase().includes(termino)) ||
                    (item.DESCRIPCIO && item.DESCRIPCIO.toLowerCase().includes(termino)) ||
                    (item.RUBRO && item.RUBRO.toLowerCase().includes(termino)) ||
                    (item.CATEGORIA_PADRE && item.CATEGORIA_PADRE.toLowerCase().includes(termino))
                );
            });
        }

        // Aplicar filtro de proveedor
        if (ComprasManager.filtrosActivos.proveedor) {
            datos = datos.filter(item => 
                item.NOM_PROVEE === ComprasManager.filtrosActivos.proveedor
            );
        }

        // Aplicar filtro de rubro
        if (ComprasManager.filtrosActivos.rubro) {
            datos = datos.filter(item => 
                item.RUBRO === ComprasManager.filtrosActivos.rubro
            );
        }

        // Aplicar filtro de fecha
        if (ComprasManager.filtrosActivos.fechaDesde) {
            datos = datos.filter(item => 
                item.FEC_EMISIO >= ComprasManager.filtrosActivos.fechaDesde
            );
        }

        if (ComprasManager.filtrosActivos.fechaHasta) {
            datos = datos.filter(item => 
                item.FEC_EMISIO <= ComprasManager.filtrosActivos.fechaHasta
            );
        }

        // Aplicar filtro de temporada
        if (ComprasManager.filtrosActivos.temporada) {
            datos = datos.filter(item => {
                switch (ComprasManager.filtrosActivos.temporada) {
                    case 'verano':
                        return (item.VERANO || 0) > 0;
                    case 'invierno':
                        return (item.INVIERNO || 0) > 0;
                    case 'atemporal':
                        return (item.ATEMPORAL || 0) > 0;
                    default:
                        return true;
                }
            });
        }

        ComprasManager.datosFiltrados = datos;
        ComprasManager.renderizarTabla();
        ComprasManager.actualizarContadores();
        ComprasManager.mostrarResumen();
    }

    /**
     * Limpiar todos los filtros
     */
    static limpiarFiltros() {
        // Limpiar inputs
        document.getElementById('search-compras-detalle').value = '';
        document.getElementById('filtro-proveedor').value = '';
        document.getElementById('filtro-rubro').value = '';
        document.getElementById('fecha-desde').value = '';
        document.getElementById('fecha-hasta').value = '';
        document.getElementById('filtro-temporada').value = '';

        // Limpiar filtros activos
        ComprasManager.filtrosActivos = {};

        // Mostrar todos los datos
        ComprasManager.datosFiltrados = [...ComprasManager.datos];
        ComprasManager.renderizarTabla();
        ComprasManager.actualizarContadores();
        ComprasManager.mostrarResumen();

        UIUtils.mostrarAlerta('Filtros limpiados', 'info', 2000);
    }

    /**
     * Ver detalle de una orden de compra
     */
    static verDetalle(numeroOrden) {
        const items = ComprasManager.datos.filter(item => item.N_ORDEN_CO === numeroOrden);
        
        if (items.length === 0) {
            UIUtils.mostrarAlerta('No se encontraron detalles para esta orden', 'warning');
            return;
        }

        const modalHTML = `
            <div class="modal fade" id="modal-detalle-compra" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-shopping-cart me-2"></i>
                                Detalle Orden de Compra: ${numeroOrden}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Proveedor:</strong> ${items[0].NOM_PROVEE || 'N/A'}
                                </div>
                                <div class="col-md-6">
                                    <strong>Fecha:</strong> ${items[0].FEC_EMISIO || 'N/A'}
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Código</th>
                                            <th>Descripción</th>
                                            <th>Rubro</th>
                                            <th class="text-end">Verano</th>
                                            <th class="text-end">Invierno</th>
                                            <th class="text-end">Atemporal</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${items.map(item => {
                                            const total = (item.VERANO || 0) + (item.INVIERNO || 0) + (item.ATEMPORAL || 0);
                                            return `
                                                <tr>
                                                    <td><code>${item.COD_ARTICU || ''}</code></td>
                                                    <td>${item.DESCRIPCIO || ''}</td>
                                                    <td><span class="badge bg-secondary">${item.RUBRO || ''}</span></td>
                                                    <td class="text-end ${FormatoUtils.obtenerClaseValor(item.VERANO)}">
                                                        ${FormatoUtils.formatearNumero(item.VERANO || 0)}
                                                    </td>
                                                    <td class="text-end ${FormatoUtils.obtenerClaseValor(item.INVIERNO)}">
                                                        ${FormatoUtils.formatearNumero(item.INVIERNO || 0)}
                                                    </td>
                                                    <td class="text-end ${FormatoUtils.obtenerClaseValor(item.ATEMPORAL)}">
                                                        ${FormatoUtils.formatearNumero(item.ATEMPORAL || 0)}
                                                    </td>
                                                    <td class="text-end ${FormatoUtils.obtenerClaseValor(total)}">
                                                        <strong>${FormatoUtils.formatearNumero(total)}</strong>
                                                    </td>
                                                </tr>
                                            `;
                                        }).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remover modal anterior si existe
        const modalAnterior = document.getElementById('modal-detalle-compra');
        if (modalAnterior) {
            modalAnterior.remove();
        }

        // Agregar nuevo modal
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('modal-detalle-compra'));
        modal.show();

        // Limpiar modal al cerrar
        document.getElementById('modal-detalle-compra').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    /**
     * Exportar a Excel usando el nuevo ExcelExporter
     */
    static async exportarExcel() {
        try {
            if (ComprasManager.datosFiltrados.length === 0) {
                UIUtils.mostrarAlerta('No hay datos para exportar', 'warning');
                return;
            }

            UIUtils.mostrarAlerta('Generando archivo Excel...', 'info', 2000);
            
            // Usar el exportador unificado
            await ExcelExporter.exportarSolapa('compras-detalle');
            
        } catch (error) {
            console.error('Error exportando Excel:', error);
            UIUtils.mostrarAlerta('Error al exportar Excel: ' + error.message, 'error');
        }
    }

    /**
     * Obtener estadísticas de los datos
     */
    static obtenerEstadisticas() {
        const stats = {
            totalRegistros: ComprasManager.datos.length,
            totalFiltrados: ComprasManager.datosFiltrados.length,
            proveedoresUnicos: new Set(ComprasManager.datos.map(item => item.NOM_PROVEE)).size,
            rubrosUnicos: new Set(ComprasManager.datos.map(item => item.RUBRO)).size,
            ordenesUnicas: new Set(ComprasManager.datos.map(item => item.N_ORDEN_CO)).size,
            totales: ComprasManager.datos.reduce((acc, item) => {
                acc.verano += item.VERANO || 0;
                acc.invierno += item.INVIERNO || 0;
                acc.atemporal += item.ATEMPORAL || 0;
                return acc;
            }, { verano: 0, invierno: 0, atemporal: 0 })
        };

        stats.totalGeneral = stats.totales.verano + stats.totales.invierno + stats.totales.atemporal;

        return stats;
    }

    /**
     * Función de debug para verificar datos
     */
    static debug() {
        console.log('=== DEBUG COMPRAS MANAGER ===');
        console.log('Datos:', ComprasManager.datos);
        console.log('Datos filtrados:', ComprasManager.datosFiltrados);
        console.log('Total datos:', ComprasManager.datos?.length || 0);
        console.log('Primer elemento:', ComprasManager.datos?.[0]);
        
        // Verificar elementos DOM
        const elementos = [
            'filtro-proveedor',
            'filtro-rubro', 
            'search-compras-detalle',
            'tbody-compras-detalle'
        ];
        
        elementos.forEach(id => {
            const elemento = document.getElementById(id);
            console.log(`Elemento ${id}:`, elemento ? 'EXISTE' : 'NO EXISTE');
        });
    }
}


// Funciones globales para compatibilidad con HTML onclick
function buscarComprasDetalle() {
    if (ComprasManager.timeoutBusqueda) {
        clearTimeout(ComprasManager.timeoutBusqueda);
    }
    
    ComprasManager.timeoutBusqueda = setTimeout(() => {
        ComprasManager.buscarInstantanea();
    }, 300);
}

function filtrarPorProveedor() {
    ComprasManager.filtrarPorProveedor();
}

function filtrarPorRubro() {
    ComprasManager.filtrarPorRubro();
}

function filtrarPorFecha() {
    ComprasManager.filtrarPorFecha();
}

function filtrarPorTemporada() {
    ComprasManager.filtrarPorTemporada();
}

// Hacer disponible globalmente
window.ComprasManager = ComprasManager;