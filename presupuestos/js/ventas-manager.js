
// Gestor de Ventas de 6 Meses
// Archivo: presupuestos/js/ventas-manager.js

class VentasManager {
    static datos = [];
    static datosFiltrados = [];
    static filtrosActivos = {};
    static timeoutBusqueda = null;

    /**
     * Cargar datos de ventas de 6 meses - CORREGIDA
     */
    static async cargarDatos() {
        try {
            UIUtils.mostrarLoading(true);
            
            // Llamar a la API para obtener ventas de 6 meses
            const response = await APIClient.obtenerVentas6Meses();
            
            if (response.success && response.data) {
                VentasManager.datos = Array.isArray(response.data) ? response.data : [];
                VentasManager.datosFiltrados = [...VentasManager.datos];
                
                console.log(`Datos de ventas cargados: ${VentasManager.datos.length} registros`);
                
                // Renderizar tabla y actualizar UI
                VentasManager.renderizarTabla();
                VentasManager.actualizarContadores();
                VentasManager.cargarFiltros();
                VentasManager.mostrarResumen();
                
                UIUtils.mostrarAlerta(
                    `${VentasManager.datos.length} registros de ventas cargados correctamente`,
                    'success'
                );
            } else {
                throw new Error(response.message || 'No se recibieron datos válidos');
            }
        } catch (error) {
            console.error('Error cargando ventas:', error);
            
            // Inicializar arrays vacíos para evitar errores
            VentasManager.datos = [];
            VentasManager.datosFiltrados = [];
            
            UIUtils.mostrarAlerta(
                'Error al cargar datos de ventas: ' + error.message,
                'error'
            );
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    /**
     * Renderizar tabla de ventas con headers dinámicos
     */
    static renderizarTabla() {
        const tbody = document.getElementById('tbody-ventas-6-meses');
        const thead = document.querySelector('#tabla-ventas-6-meses thead tr');
        
        if (!tbody) return;

        if (VentasManager.datosFiltrados.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="20" class="text-center text-muted py-4">
                        <i class="fas fa-inbox"></i><br>
                        No hay registros para mostrar
                    </td>
                </tr>
            `;
            return;
        }

        // Generar headers dinámicos en la primera carga
        if (VentasManager.datos.length > 0 && !thead.dataset.headersGenerados) {
            VentasManager.generarHeadersDinamicos();
            thead.dataset.headersGenerados = 'true';
        }

        // Extraer columnas de meses del primer registro
        const columnasMeses = VentasManager.datos.length > 0 ? 
            VentasManager.extraerColumnasMeses(VentasManager.datos[0]) : [];

        const html = VentasManager.datosFiltrados.map(item => {
            const indiceVariacion = parseFloat(item.INDICE_VARIACION || 1);
            const claseIndice = VentasManager.obtenerClaseIndice(indiceVariacion);
            
            // Generar celdas de meses dinámicamente
            const cellsMeses = columnasMeses.map(col => 
                `<td class="text-end">${FormatoUtils.formatearNumero(item[col.campo] || 0)}</td>`
            ).join('');
            
            return `
                <tr class="fila-datos">
                    <td><strong>${item.RUBRO || ''}</strong></td>
                    <td>${item.CATEGORIA_PADRE || ''}</td>
                    <td class="text-end ${FormatoUtils.obtenerClaseValor(item.VTA_ULT_60_DIAS)}">
                        <strong>${FormatoUtils.formatearNumero(item.VTA_ULT_60_DIAS || 0)}</strong>
                    </td>
                    <td class="text-end ${FormatoUtils.obtenerClaseValor(item.VTA_ULT_60_DIAS_ANO_ANT)}">
                        ${FormatoUtils.formatearNumero(item.VTA_ULT_60_DIAS_ANO_ANT || 0)}
                    </td>
                    <td class="text-center ${claseIndice}">
                        <span class="badge ${VentasManager.obtenerBadgeIndice(indiceVariacion)}">
                            ${indiceVariacion.toFixed(2)}
                        </span>
                    </td>
                    ${cellsMeses}
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-info" 
                                onclick="VentasManager.verDetalle('${item.RUBRO}', '${item.CATEGORIA_PADRE}')"
                                title="Ver detalle">
                            <i class="fas fa-chart-line"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.innerHTML = html;
    }

    /**
     * NUEVA: Generar headers dinámicos basados en los datos
     */
    static generarHeadersDinamicos() {
        const thead = document.querySelector('#tabla-ventas-6-meses thead tr');
        if (!thead || VentasManager.datos.length === 0) return;

        // Extraer columnas de meses del primer registro
        const columnasMeses = VentasManager.extraerColumnasMeses(VentasManager.datos[0]);
        
        // Headers fijos
        const headersFijos = `
            <th>Rubro</th>
            <th>Categoría</th>
            <th class="text-center bg-primary text-white">Ventas<br>Últimos 60 días</th>
            <th class="text-center bg-secondary text-white">Ventas<br>Año Anterior</th>
            <th class="text-center bg-warning text-dark">Índice<br>Variación</th>
        `;
        
        // Headers dinámicos de meses
        const headersMeses = columnasMeses.map(col => 
            `<th class="text-center bg-info text-white">${col.nombre}</th>`
        ).join('');
        
        // Header de acciones
        const headerAcciones = `<th class="text-center">Acciones</th>`;
        
        // Actualizar thead completo
        thead.innerHTML = headersFijos + headersMeses + headerAcciones;
        
        console.log(`✅ Headers dinámicos generados para ${columnasMeses.length} meses`);
    }

    /**
     * Extraer columnas de meses dinámicamente - MEJORADA
     */
    static extraerColumnasMeses(item) {
        const columnas = [];
        
        Object.keys(item).forEach(key => {
            // Buscar patrones: VTA_MM_YYYY
            if (key.startsWith('VTA_') && key.match(/^VTA_\d{1,2}_\d{4}$/)) {
                const partes = key.split('_');
                if (partes.length === 3) {
                    const mes = parseInt(partes[1]);
                    const ano = parseInt(partes[2]);
                    
                    // Validar mes y año
                    if (mes >= 1 && mes <= 12 && ano >= 2020 && ano <= 2030) {
                        columnas.push({
                            campo: key,
                            mes: mes,
                            ano: ano,
                            nombre: VentasManager.formatearNombreMes(mes, ano),
                            fecha: new Date(ano, mes - 1, 1) // Para ordenamiento
                        });
                    }
                }
            }
        });
        
        // Ordenar cronológicamente (del más antiguo al más reciente)
        return columnas.sort((a, b) => a.fecha - b.fecha);
    }

    /**
     * Formatear nombre del mes
     */
    static formatearNombreMes(mes, ano) {
        const meses = [
            'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
            'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'
        ];
        return `${meses[mes - 1]} ${ano}`;
    }

    /**
     * Obtener clase CSS para el índice
     */
    static obtenerClaseIndice(indice) {
        if (indice > 1.2) return 'text-success';
        if (indice < 0.8) return 'text-danger';
        return 'text-warning';
    }

    /**
     * Obtener badge para el índice
     */
    static obtenerBadgeIndice(indice) {
        if (indice > 1.2) return 'bg-success';
        if (indice < 0.8) return 'bg-danger';
        return 'bg-warning text-dark';
    }

    /**
     * Actualizar contadores
     */
    static actualizarContadores() {
        const contador = document.getElementById('count-ventas-6-meses');
        if (contador) {
            contador.textContent = `${FormatoUtils.formatearNumero(VentasManager.datosFiltrados.length)} registros`;
            contador.classList.add('actualizado');
            setTimeout(() => contador.classList.remove('actualizado'), 500);
        }
    }

    /**
     * Cargar filtros (rubros y categorías)
     */
    static async cargarFiltros() {
        try {
            if (!VentasManager.datos || VentasManager.datos.length === 0) {
                console.warn('No hay datos para generar filtros');
                return;
            }

            // Cargar rubros únicos
            const rubros = [...new Set(
                VentasManager.datos
                    .map(item => item.RUBRO)
                    .filter(r => r && r.trim() !== '')
            )].sort();
            
            const selectRubro = document.getElementById('filtro-rubro-ventas');
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

            // Cargar categorías únicas
            const categorias = [...new Set(
                VentasManager.datos
                    .map(item => item.CATEGORIA_PADRE)
                    .filter(c => c && c.trim() !== '')
            )].sort();
            
            const selectCategoria = document.getElementById('filtro-categoria-ventas');
            if (selectCategoria) {
                selectCategoria.innerHTML = '<option value="">Todas las categorías</option>';
                categorias.forEach(categoria => {
                    const option = document.createElement('option');
                    option.value = categoria;
                    option.textContent = categoria;
                    selectCategoria.appendChild(option);
                });
                console.log(`Cargadas ${categorias.length} categorías`);
            }
            
        } catch (error) {
            console.error('Error cargando filtros:', error);
            UIUtils.mostrarAlerta('Error al cargar filtros de búsqueda', 'warning');
        }
    }

    /**
     * Mostrar resumen de ventas
     */
    static mostrarResumen() {
        const totales = VentasManager.datosFiltrados.reduce((acc, item) => {
            acc.ventasActuales += item.VTA_ULT_60_DIAS || 0;
            acc.ventasAnteriores += item.VTA_ULT_60_DIAS_ANO_ANT || 0;
            
            const indice = parseFloat(item.INDICE_VARIACION || 1);
            if (indice > 1.2) acc.mejorados++;
            else if (indice < 0.8) acc.empeorados++;
            else acc.estables++;
            
            return acc;
        }, { 
            ventasActuales: 0, 
            ventasAnteriores: 0, 
            mejorados: 0, 
            empeorados: 0, 
            estables: 0 
        });

        const variacionTotal = totales.ventasAnteriores > 0 
            ? ((totales.ventasActuales - totales.ventasAnteriores) / totales.ventasAnteriores) * 100
            : 0;

        // Actualizar badges en la parte superior
        const badgeActuales = document.getElementById('badge-ventas-actuales');
        const badgeAnteriores = document.getElementById('badge-ventas-anteriores');
        const badgeVariacion = document.getElementById('badge-variacion-total');
        const badgeEstadisticas = document.getElementById('badge-estadisticas');

        if (badgeActuales) badgeActuales.textContent = FormatoUtils.formatearNumero(totales.ventasActuales);
        if (badgeAnteriores) badgeAnteriores.textContent = FormatoUtils.formatearNumero(totales.ventasAnteriores);
        if (badgeVariacion) {
            badgeVariacion.textContent = `${variacionTotal > 0 ? '+' : ''}${variacionTotal.toFixed(1)}%`;
            badgeVariacion.className = `badge fs-6 ${variacionTotal > 0 ? 'bg-success' : variacionTotal < 0 ? 'bg-danger' : 'bg-warning text-dark'}`;
        }
        if (badgeEstadisticas) badgeEstadisticas.textContent = `↗${totales.mejorados} | →${totales.estables} | ↘${totales.empeorados}`;

        // Mostrar el contenedor de resumen
        const contenedorResumen = document.getElementById('resumen-ventas-superior');
        if (contenedorResumen) {
            contenedorResumen.classList.remove('d-none');
        }
    }

    /**
     * Búsqueda instantánea
     */
    static buscarInstantanea() {
        const input = document.getElementById('search-ventas-6-meses');
        if (!input) return;

        const termino = input.value.toLowerCase().trim();
        
        if (termino.length === 0) {
            VentasManager.datosFiltrados = [...VentasManager.datos];
        } else {
            VentasManager.datosFiltrados = VentasManager.datos.filter(item => {
                return (
                    (item.RUBRO && item.RUBRO.toLowerCase().includes(termino)) ||
                    (item.CATEGORIA_PADRE && item.CATEGORIA_PADRE.toLowerCase().includes(termino))
                );
            });
        }

        VentasManager.aplicarFiltros();
    }

    /**
     * Filtrar por rubro
     */
    static filtrarPorRubro() {
        const select = document.getElementById('filtro-rubro-ventas');
        if (!select) return;

        VentasManager.filtrosActivos.rubro = select.value;
        VentasManager.aplicarFiltros();
    }

    /**
     * Filtrar por categoría
     */
    static filtrarPorCategoria() {
        const select = document.getElementById('filtro-categoria-ventas');
        if (!select) return;

        VentasManager.filtrosActivos.categoria = select.value;
        VentasManager.aplicarFiltros();
    }

    /**
     * Aplicar todos los filtros
     */
    static aplicarFiltros() {
        let datos = [...VentasManager.datos];

        // Aplicar filtro de búsqueda
        const termino = document.getElementById('search-ventas-6-meses').value.toLowerCase().trim();
        if (termino.length > 0) {
            datos = datos.filter(item => {
                return (
                    (item.RUBRO && item.RUBRO.toLowerCase().includes(termino)) ||
                    (item.CATEGORIA_PADRE && item.CATEGORIA_PADRE.toLowerCase().includes(termino))
                );
            });
        }

        // Aplicar filtro de rubro
        if (VentasManager.filtrosActivos.rubro) {
            datos = datos.filter(item => 
                item.RUBRO === VentasManager.filtrosActivos.rubro
            );
        }

        // Aplicar filtro de categoría
        if (VentasManager.filtrosActivos.categoria) {
            datos = datos.filter(item => 
                item.CATEGORIA_PADRE === VentasManager.filtrosActivos.categoria
            );
        }

        VentasManager.datosFiltrados = datos;
        VentasManager.renderizarTabla();
        VentasManager.actualizarContadores();
        VentasManager.mostrarResumen();
    }

    /**
     * Limpiar todos los filtros
     */
    static limpiarFiltros() {
        // Limpiar inputs
        document.getElementById('search-ventas-6-meses').value = '';
        document.getElementById('filtro-rubro-ventas').value = '';
        document.getElementById('filtro-categoria-ventas').value = '';

        // Limpiar filtros activos
        VentasManager.filtrosActivos = {};

        // Mostrar todos los datos
        VentasManager.datosFiltrados = [...VentasManager.datos];
        VentasManager.renderizarTabla();
        VentasManager.actualizarContadores();
        VentasManager.mostrarResumen();

        UIUtils.mostrarAlerta('Filtros limpiados', 'info', 2000);
    }

    /**
     * Ver detalle de ventas
     */
    static verDetalle(rubro, categoria) {
        const item = VentasManager.datos.find(x => x.RUBRO === rubro && x.CATEGORIA_PADRE === categoria);
        
        if (!item) {
            UIUtils.mostrarAlerta('No se encontraron datos para este item', 'warning');
            return;
        }

        const columnasMeses = VentasManager.extraerColumnasMeses(item);
        const indiceVariacion = parseFloat(item.INDICE_VARIACION || 1);
        
        const modalHTML = `
            <div class="modal fade" id="modal-detalle-ventas" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-chart-line me-2"></i>
                                Detalle de Ventas: ${rubro} - ${categoria}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <strong>Ventas Últimos 60 días:</strong>
                                    <div class="h4 text-primary">${FormatoUtils.formatearNumero(item.VTA_ULT_60_DIAS || 0)}</div>
                                </div>
                                <div class="col-md-4">
                                    <strong>Mismo período año anterior:</strong>
                                    <div class="h4 text-secondary">${FormatoUtils.formatearNumero(item.VTA_ULT_60_DIAS_ANO_ANT || 0)}</div>
                                </div>
                                <div class="col-md-4">
                                    <strong>Índice de variación:</strong>
                                    <div class="h4">
                                        <span class="badge ${VentasManager.obtenerBadgeIndice(indiceVariacion)} fs-5">
                                            ${indiceVariacion.toFixed(2)}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <h6>Evolución por meses:</h6>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Mes</th>
                                            <th class="text-end">Ventas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${columnasMeses.map(col => `
                                            <tr>
                                                <td>${col.nombre}</td>
                                                <td class="text-end">${FormatoUtils.formatearNumero(item[col.campo] || 0)}</td>
                                            </tr>
                                        `).join('')}
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
        const modalAnterior = document.getElementById('modal-detalle-ventas');
        if (modalAnterior) {
            modalAnterior.remove();
        }

        // Agregar nuevo modal
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('modal-detalle-ventas'));
        modal.show();

        // Limpiar modal al cerrar
        document.getElementById('modal-detalle-ventas').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    /**
     * Exportar a Excel
     */
    static async exportarExcel() {
        try {
            if (VentasManager.datosFiltrados.length === 0) {
                UIUtils.mostrarAlerta('No hay datos para exportar', 'warning');
                return;
            }

            UIUtils.mostrarAlerta('Generando archivo Excel...', 'info', 2000);
            
            // Usar el exportador unificado
            await ExcelExporter.exportarSolapa('ventas-6-meses');
            
        } catch (error) {
            console.error('Error exportando Excel:', error);
            UIUtils.mostrarAlerta('Error al exportar Excel: ' + error.message, 'error');
        }
    }

    /**
     * Función de debug
     */
    static debug() {
        console.log('=== DEBUG VENTAS MANAGER ===');
        console.log('Datos:', VentasManager.datos);
        console.log('Datos filtrados:', VentasManager.datosFiltrados);
        console.log('Total datos:', VentasManager.datos?.length || 0);
        console.log('Primer elemento:', VentasManager.datos?.[0]);
        
        if (VentasManager.datos.length > 0) {
            const columnas = VentasManager.extraerColumnasMeses(VentasManager.datos[0]);
            console.log('Columnas de meses:', columnas);
        }
    }

    /**
     * Debug específico para columnas dinámicas
     */
    static debugColumnasDinamicas() {
        console.group('🔍 DEBUG COLUMNAS DINÁMICAS');
        
        if (VentasManager.datos.length > 0) {
            const primer = VentasManager.datos[0];
            
            console.log('Primer registro completo:', primer);
            console.log('Todas las claves:', Object.keys(primer));
            
            // Filtrar solo columnas VTA_
            const columnasVTA = Object.keys(primer).filter(key => key.startsWith('VTA_'));
            console.log('Columnas VTA encontradas:', columnasVTA);
            
            // Extraer y mostrar columnas de meses
            const columnasMeses = VentasManager.extraerColumnasMeses(primer);
            console.log('Columnas de meses procesadas:', columnasMeses);
            
            // Mostrar tabla de headers que se generarían
            console.table(columnasMeses.map(col => ({
                Campo: col.campo,
                Nombre: col.nombre,
                Mes: col.mes,
                Año: col.ano,
                Valor: primer[col.campo]
            })));
            
        } else {
            console.log('No hay datos para analizar');
        }
        
        console.groupEnd();
    }
}

// Funciones globales para compatibilidad con HTML onclick
function buscarVentas6Meses() {
    if (VentasManager.timeoutBusqueda) {
        clearTimeout(VentasManager.timeoutBusqueda);
    }
    
    VentasManager.timeoutBusqueda = setTimeout(() => {
        VentasManager.buscarInstantanea();
    }, 300);
}

function filtrarPorRubroVentas() {
    VentasManager.filtrarPorRubro();
}

function filtrarPorCategoriaVentas() {
    VentasManager.filtrarPorCategoria();
}

// Hacer disponible globalmente
window.VentasManager = VentasManager;