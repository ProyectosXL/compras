
/**
 * Gestor de Compras Pendientes
 * Maneja la funcionalidad específica de la solapa de compras pendientes
 */
class ComprasManager {
    constructor() {
        this.datos = [];
        this.datosFiltrados = [];
        this.filtros = {
            proveedor: '',
            rubro: '',
            fecha_desde: '',
            fecha_hasta: '',
            temporada: ''
        };
        this.timeoutBusqueda = null;
        this.inicializar();
    }

    inicializar() {
        this.configurarEventos();
        this.cargarProveedores();
        this.cargarRubros();
        this.configurarFechasPorDefecto();
    }

    configurarEventos() {
        // Evento de búsqueda
        const searchInput = document.getElementById('search-compras-detalle');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.busquedaInstantanea(e.target.value);
            });
        }

        // Eventos de filtros
        const filtroProveedor = document.getElementById('filtro-proveedor');
        if (filtroProveedor) {
            filtroProveedor.addEventListener('change', (e) => {
                this.filtros.proveedor = e.target.value;
                this.aplicarFiltros();
            });
        }

        const filtroRubro = document.getElementById('filtro-rubro');
        if (filtroRubro) {
            filtroRubro.addEventListener('change', (e) => {
                this.filtros.rubro = e.target.value;
                this.aplicarFiltros();
            });
        }

        const filtroTemporada = document.getElementById('filtro-temporada');
        if (filtroTemporada) {
            filtroTemporada.addEventListener('change', (e) => {
                this.filtros.temporada = e.target.value;
                this.aplicarFiltros();
            });
        }

        // Eventos de fechas
        const fechaDesde = document.getElementById('fecha-desde');
        if (fechaDesde) {
            fechaDesde.addEventListener('change', (e) => {
                this.filtros.fecha_desde = e.target.value;
                this.aplicarFiltros();
            });
        }

        const fechaHasta = document.getElementById('fecha-hasta');
        if (fechaHasta) {
            fechaHasta.addEventListener('change', (e) => {
                this.filtros.fecha_hasta = e.target.value;
                this.aplicarFiltros();
            });
        }
    }

    configurarFechasPorDefecto() {
        const fechaDesde = document.getElementById('fecha-desde');
        const fechaHasta = document.getElementById('fecha-hasta');
        
        if (fechaDesde && fechaHasta) {
            const hoy = new Date();
            const hace30Dias = new Date();
            hace30Dias.setDate(hoy.getDate() - 30);
            
            fechaDesde.value = hace30Dias.toISOString().split('T')[0];
            fechaHasta.value = hoy.toISOString().split('T')[0];
            
            this.filtros.fecha_desde = fechaDesde.value;
            this.filtros.fecha_hasta = fechaHasta.value;
        }
    }

    async cargarDatos() {
        try {
            UIUtils.mostrarLoading(true);
            
            const response = await APIClient.get('compras-detalle', this.filtros);
            
            if (response.success) {
                this.datos = response.data;
                this.datosFiltrados = [...this.datos];
                this.renderizarTabla();
                this.actualizarContadores();
                this.mostrarResumen();
                UIUtils.mostrarAlerta(`${response.total_registros} compras cargadas`, 'success');
            } else {
                throw new Error(response.message);
            }
        } catch (error) {
            console.error('Error cargando compras:', error);
            UIUtils.mostrarAlerta('Error al cargar compras: ' + error.message, 'danger');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    async cargarProveedores() {
        try {
            const response = await APIClient.get('proveedores-compras');
            
            if (response.success) {
                const select = document.getElementById('filtro-proveedor');
                if (select) {
                    // Limpiar opciones existentes (mantener "Todos")
                    select.innerHTML = '<option value="">Todos los proveedores</option>';
                    
                    response.data.forEach(proveedor => {
                        const option = document.createElement('option');
                        option.value = proveedor.NOM_PROVEE;
                        option.textContent = proveedor.NOM_PROVEE;
                        select.appendChild(option);
                    });
                }
            }
        } catch (error) {
            console.error('Error cargando proveedores:', error);
        }
    }

    async cargarRubros() {
        try {
            const response = await APIClient.get('rubros-compras');
            
            if (response.success) {
                const select = document.getElementById('filtro-rubro');
                if (select) {
                    // Limpiar opciones existentes (mantener "Todos")
                    select.innerHTML = '<option value="">Todos los rubros</option>';
                    
                    response.data.forEach(rubro => {
                        const option = document.createElement('option');
                        option.value = rubro.RUBRO;
                        option.textContent = rubro.RUBRO;
                        select.appendChild(option);
                    });
                }
            }
        } catch (error) {
            console.error('Error cargando rubros:', error);
        }
    }

    busquedaInstantanea(termino) {
        // Cancelar búsqueda anterior
        if (this.timeoutBusqueda) {
            clearTimeout(this.timeoutBusqueda);
        }

        // Configurar nueva búsqueda con delay
        this.timeoutBusqueda = setTimeout(() => {
            this.realizarBusqueda(termino);
        }, 300);
    }

    realizarBusqueda(termino) {
        if (!termino || termino.length < 2) {
            this.datosFiltrados = [...this.datos];
            this.renderizarTabla();
            this.actualizarContadores();
            return;
        }

        const terminoLower = termino.toLowerCase();
        
        this.datosFiltrados = this.datos.filter(item => {
            return (
                (item.N_ORDEN_CO && item.N_ORDEN_CO.toString().toLowerCase().includes(terminoLower)) ||
                (item.NOM_PROVEE && item.NOM_PROVEE.toLowerCase().includes(terminoLower)) ||
                (item.COD_ARTICU && item.COD_ARTICU.toLowerCase().includes(terminoLower)) ||
                (item.DESCRIPCIO && item.DESCRIPCIO.toLowerCase().includes(terminoLower)) ||
                (item.RUBRO && item.RUBRO.toLowerCase().includes(terminoLower)) ||
                (item.CATEGORIA_PADRE && item.CATEGORIA_PADRE.toLowerCase().includes(terminoLower))
            );
        });

        this.renderizarTabla();
        this.actualizarContadores();
    }

    aplicarFiltros() {
        let datosFiltrados = [...this.datos];

        // Aplicar filtro de proveedor
        if (this.filtros.proveedor) {
            datosFiltrados = datosFiltrados.filter(item => 
                item.NOM_PROVEE && item.NOM_PROVEE.includes(this.filtros.proveedor)
            );
        }

        // Aplicar filtro de rubro
        if (this.filtros.rubro) {
            datosFiltrados = datosFiltrados.filter(item => 
                item.RUBRO && item.RUBRO.includes(this.filtros.rubro)
            );
        }

        // Aplicar filtro de temporada
        if (this.filtros.temporada) {
            datosFiltrados = datosFiltrados.filter(item => {
                switch (this.filtros.temporada) {
                    case 'verano':
                        return item.VERANO > 0;
                    case 'invierno':
                        return item.INVIERNO > 0;
                    case 'atemporal':
                        return item.ATEMPORAL > 0;
                    default:
                        return true;
                }
            });
        }

        // Aplicar filtros de fecha
        if (this.filtros.fecha_desde) {
            datosFiltrados = datosFiltrados.filter(item => 
                item.FEC_EMISIO >= this.filtros.fecha_desde
            );
        }

        if (this.filtros.fecha_hasta) {
            datosFiltrados = datosFiltrados.filter(item => 
                item.FEC_EMISIO <= this.filtros.fecha_hasta
            );
        }

        this.datosFiltrados = datosFiltrados;
        this.renderizarTabla();
        this.actualizarContadores();
        this.mostrarResumen();
    }

    renderizarTabla() {
        const tbody = document.getElementById('tbody-compras-detalle');
        if (!tbody) return;

        if (this.datosFiltrados.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="12" class="text-center text-muted py-4">
                        <i class="fas fa-info-circle"></i>
                        No hay datos disponibles con los filtros aplicados
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        this.datosFiltrados.forEach((item, index) => {
            const fechaFormateada = this.formatearFecha(item.FEC_EMISIO);
            const claseFilaAlternada = index % 2 === 0 ? '' : 'table-secondary';
            
            html += `
                <tr class="fila-datos ${claseFilaAlternada}" data-index="${index}">
                    <td class="text-center">${fechaFormateada}</td>
                    <td class="text-center">${item.N_ORDEN_CO || ''}</td>
                    <td>${item.NOM_PROVEE || ''}</td>
                    <td class="text-center">${item.COD_ARTICU || ''}</td>
                    <td>${item.DESCRIPCIO || ''}</td>
                    <td>${item.RUBRO || ''}</td>
                    <td>${item.CATEGORIA_PADRE || ''}</td>
                    <td class="text-end valor-warning">${FormatoUtils.formatearNumero(item.VERANO)}</td>
                    <td class="text-end valor-info">${FormatoUtils.formatearNumero(item.INVIERNO)}</td>
                    <td class="text-end valor-success">${FormatoUtils.formatearNumero(item.ATEMPORAL)}</td>
                    <td class="text-end valor-primary fw-bold">${FormatoUtils.formatearNumero(item.TOTAL)}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary" onclick="ComprasManager.verDetalleOrden('${item.N_ORDEN_CO}')" title="Ver detalle">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    formatearFecha(fecha) {
        if (!fecha) return '';
        
        try {
            const fechaObj = new Date(fecha);
            return fechaObj.toLocaleDateString('es-AR', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            });
        } catch (error) {
            return fecha;
        }
    }

    actualizarContadores() {
        const contador = document.getElementById('count-compras-detalle');
        if (contador) {
            contador.textContent = `${this.datosFiltrados.length} registros`;
            contador.classList.add('actualizado');
            setTimeout(() => contador.classList.remove('actualizado'), 500);
        }
    }

    async mostrarResumen() {
        try {
            const response = await APIClient.get('resumen-compras', this.filtros);
            
            if (response.success) {
                const resumen = response.data;
                
                // Actualizar totales en el resumen
                document.getElementById('total-verano').textContent = FormatoUtils.formatearNumero(resumen.total_verano);
                document.getElementById('total-invierno').textContent = FormatoUtils.formatearNumero(resumen.total_invierno);
                document.getElementById('total-atemporal').textContent = FormatoUtils.formatearNumero(resumen.total_atemporal);
                document.getElementById('total-general').textContent = FormatoUtils.formatearNumero(resumen.total_general);
                
                // Mostrar el resumen
                const resumenContainer = document.getElementById('resumen-compras-detalle');
                if (resumenContainer) {
                    resumenContainer.classList.remove('d-none');
                }
            }
        } catch (error) {
            console.error('Error obteniendo resumen:', error);
        }
    }

    limpiarFiltros() {
        // Limpiar filtros
        this.filtros = {
            proveedor: '',
            rubro: '',
            fecha_desde: '',
            fecha_hasta: '',
            temporada: ''
        };

        // Limpiar formulario
        document.getElementById('search-compras-detalle').value = '';
        document.getElementById('filtro-proveedor').value = '';
        document.getElementById('filtro-rubro').value = '';
        document.getElementById('filtro-temporada').value = '';
        document.getElementById('fecha-desde').value = '';
        document.getElementById('fecha-hasta').value = '';

        // Recargar datos
        this.datosFiltrados = [...this.datos];
        this.renderizarTabla();
        this.actualizarContadores();
        this.mostrarResumen();
        
        UIUtils.mostrarAlerta('Filtros limpiados', 'info');
    }

    async exportarExcel() {
        try {
            UIUtils.mostrarLoading(true);
            
            const params = new URLSearchParams({
                accion: 'exportar',
                solapa: 'compras-detalle',
                ...this.filtros
            });

            window.location.href = `api.php?${params.toString()}`;
            
            UIUtils.mostrarAlerta('Exportación iniciada', 'success');
        } catch (error) {
            console.error('Error exportando:', error);
            UIUtils.mostrarAlerta('Error al exportar: ' + error.message, 'danger');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    verDetalleOrden(numeroOrden) {
        // Mostrar modal o ventana con detalle de la orden
        UIUtils.mostrarAlerta(`Detalle de orden ${numeroOrden} - Funcionalidad en desarrollo`, 'info');
    }

    // Método estático para uso global
    static async cargarDatos() {
        if (window.comprasManager) {
            await window.comprasManager.cargarDatos();
        }
    }

    static limpiarFiltros() {
        if (window.comprasManager) {
            window.comprasManager.limpiarFiltros();
        }
    }

    static async exportarExcel() {
        if (window.comprasManager) {
            await window.comprasManager.exportarExcel();
        }
    }

    static verDetalleOrden(numeroOrden) {
        if (window.comprasManager) {
            window.comprasManager.verDetalleOrden(numeroOrden);
        }
    }
}

// Inicializar el gestor cuando se carga el DOM
document.addEventListener('DOMContentLoaded', function() {
    // Solo inicializar si estamos en la página de presupuestos
    if (document.getElementById('search-compras-detalle')) {
        window.comprasManager = new ComprasManager();
        console.log('✅ ComprasManager inicializado');
    }
});

// Funciones globales para compatibilidad
function buscarComprasDetalle() {
    if (window.comprasManager) {
        const input = document.getElementById('search-compras-detalle');
        if (input) {
            window.comprasManager.busquedaInstantanea(input.value);
        }
    }
}

function filtrarPorProveedor() {
    if (window.comprasManager) {
        const select = document.getElementById('filtro-proveedor');
        if (select) {
            window.comprasManager.filtros.proveedor = select.value;
            window.comprasManager.aplicarFiltros();
        }
    }
}

function filtrarPorRubro() {
    if (window.comprasManager) {
        const select = document.getElementById('filtro-rubro');
        if (select) {
            window.comprasManager.filtros.rubro = select.value;
            window.comprasManager.aplicarFiltros();
        }
    }
}

function filtrarPorTemporada() {
    if (window.comprasManager) {
        const select = document.getElementById('filtro-temporada');
        if (select) {
            window.comprasManager.filtros.temporada = select.value;
            window.comprasManager.aplicarFiltros();
        }
    }
}

function filtrarPorFecha() {
    if (window.comprasManager) {
        const fechaDesde = document.getElementById('fecha-desde').value;
        const fechaHasta = document.getElementById('fecha-hasta').value;
        
        window.comprasManager.filtros.fecha_desde = fechaDesde;
        window.comprasManager.filtros.fecha_hasta = fechaHasta;
        window.comprasManager.aplicarFiltros();
    }
}