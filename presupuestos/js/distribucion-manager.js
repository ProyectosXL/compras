// Gestor de la solapa Distribución por Canal
// Archivo: presupuestos/js/distribucion-manager.js

class DistribucionManager {
    static datos = [];
    static datosFiltrados = [];
    static canales = [];
    static meses = [];
    static cargado = false;
    
    // Propiedades para Paso 2 (Costos)
    static pasoActivo = 1;
    static datosCostos = [];
    static mesesCostos = [];
    static mesesCostosClaves = [];
    static periodosVersiones = {};

    static inicializarFiltrosFechas() {
        const desdeInput = document.getElementById('filtro-desde-distribucion');
        const hastaInput = document.getElementById('filtro-hasta-distribucion');
        const temporadaSelect = document.getElementById('filtro-temporada-distribucion');
        
        if (desdeInput && hastaInput && (!desdeInput.value || !hastaInput.value)) {
            const hoy = new Date();
            
            // Desde: hace 6 meses (primer día de ese mes)
            const fechaDesdeObj = new Date(hoy.getFullYear(), hoy.getMonth() - 6, 1);
            const yyyyD = fechaDesdeObj.getFullYear();
            const mmD = String(fechaDesdeObj.getMonth() + 1).padStart(2, '0');
            desdeInput.value = `${yyyyD}-${mmD}-01`;
            
            // Hasta: último día del mes anterior (ya cerrado)
            const fechaHastaObj = new Date(hoy.getFullYear(), hoy.getMonth(), 0);
            const yyyyH = fechaHastaObj.getFullYear();
            const mmH = String(fechaHastaObj.getMonth() + 1).padStart(2, '0');
            const ddH = String(fechaHastaObj.getDate()).padStart(2, '0');
            hastaInput.value = `${yyyyH}-${mmH}-${ddH}`;
        }

        // Inicializar temporada por defecto según el estado de la aplicación
        if (temporadaSelect && !temporadaSelect.dataset.inicializado) {
            const temporadaInfo = window.presupuestoApp?.temporadaInfo;
            let defaultTemporada = 'VERANO';
            if (temporadaInfo && temporadaInfo.temporada_actual && temporadaInfo.temporada_actual.temporada) {
                defaultTemporada = temporadaInfo.temporada_actual.temporada.toUpperCase().includes('VERANO') ? 'VERANO' : 'INVIERNO';
            }
            temporadaSelect.value = defaultTemporada;
            temporadaSelect.dataset.inicializado = 'true';
        }
    }

    static async inicializarVersiones(resetSelection = false) {
        try {
            const temporadaSelect = document.getElementById('filtro-temporada-distribucion');
            const temporada = temporadaSelect ? temporadaSelect.value : 'VERANO';
            const versionSelect = document.getElementById('filtro-version-distribucion');
            
            if (!versionSelect) return;

            if (resetSelection) {
                versionSelect.value = 'Por defecto';
            }

            const response = await APIClient.obtenerVersionesGuardadas(temporada);
            console.log('Versiones recibidas para temporada:', temporada, response); // Debug
            if (response.success && response.versiones) {
                DistribucionManager.periodosVersiones = {};
                const activeVer = versionSelect.value || 'Por defecto';
                let selectHtml = '';
                
                response.versiones.forEach(v => {
                    const nombre = typeof v === 'object' ? v.nombre : v;
                    const periodo = typeof v === 'object' ? v.periodo : '';
                    DistribucionManager.periodosVersiones[nombre] = periodo;
                    
                    selectHtml += `<option value="${nombre}" ${nombre === activeVer ? 'selected' : ''}>Versión: ${nombre}</option>`;
                });
                
                const tieneActive = typeof response.versiones[0] === 'object'
                    ? response.versiones.some(v => v.nombre === activeVer)
                    : response.versiones.includes(activeVer);
                if (!tieneActive) {
                    selectHtml += `<option value="${activeVer}" selected>Versión: ${activeVer}</option>`;
                }
                versionSelect.innerHTML = selectHtml;
            }
        } catch (error) {
            console.error('Error al inicializar versiones:', error);
        }
    }

    static async cargarDatos() {
        try {
            UIUtils.mostrarLoading(true);

            // Asegurar que los filtros estén inicializados
            DistribucionManager.inicializarFiltrosFechas();

            // Obtener fechas del filtro dinámico
            const desdeInput = document.getElementById('filtro-desde-distribucion');
            const hastaInput = document.getElementById('filtro-hasta-distribucion');
            
            const fechaDesde = desdeInput ? desdeInput.value : '';
            const fechaHasta = hastaInput ? hastaInput.value : '';

            // Determinar temporada del selector
            const temporadaSelect = document.getElementById('filtro-temporada-distribucion');
            let temporada = 'VERANO';
            
            if (temporadaSelect) {
                if (!temporadaSelect.value) {
                    const temporadaInfo = window.presupuestoApp?.temporadaInfo;
                    let defaultTemporada = 'VERANO';
                    if (temporadaInfo && temporadaInfo.temporada_actual && temporadaInfo.temporada_actual.temporada) {
                        defaultTemporada = temporadaInfo.temporada_actual.temporada.toUpperCase().includes('VERANO') ? 'VERANO' : 'INVIERNO';
                    }
                    temporadaSelect.value = defaultTemporada;
                }
                temporada = temporadaSelect.value;
            } else {
                const temporadaInfo = window.presupuestoApp?.temporadaInfo;
                let defaultTemporada = 'VERANO';
                if (temporadaInfo && temporadaInfo.temporada_actual && temporadaInfo.temporada_actual.temporada) {
                    defaultTemporada = temporadaInfo.temporada_actual.temporada.toUpperCase().includes('VERANO') ? 'VERANO' : 'INVIERNO';
                }
                temporada = defaultTemporada;
            }
            
            // Obtener versión seleccionada
            const versionSelect = document.getElementById('filtro-version-distribucion');
            const version = versionSelect ? versionSelect.value : 'Por defecto';
            
            // Obtener proyecciones en memoria desde la solapa activa
            const solapaOrigen = temporada.toLowerCase(); // 'verano' o 'invierno'
            const proyeccionesMemoria = window.presupuestoApp?.datos?.[solapaOrigen] || [];

            // Llamar API
            const response = await APIClient.obtenerDistribucionPorCanal(temporada, fechaDesde, fechaHasta, proyeccionesMemoria, version);

            if (response.success && response.data) {
                DistribucionManager.datos = response.data;
                DistribucionManager.datosFiltrados = [...response.data];
                DistribucionManager.canales = response.canales || [];
                DistribucionManager.meses = response.meses || [];
                DistribucionManager.cargado = true;

                // Cargar versiones en el dropdown
                if (versionSelect && response.versiones) {
                    DistribucionManager.periodosVersiones = {};
                    const activeVer = response.nombre_distribucion || 'Por defecto';
                    let selectHtml = '';
                    
                    response.versiones.forEach(v => {
                        const nombre = typeof v === 'object' ? v.nombre : v;
                        const periodo = typeof v === 'object' ? v.periodo : '';
                        DistribucionManager.periodosVersiones[nombre] = periodo;
                        
                        selectHtml += `<option value="${nombre}" ${nombre === activeVer ? 'selected' : ''}>Versión: ${nombre}</option>`;
                    });
                    
                    const tieneActive = typeof response.versiones[0] === 'object'
                        ? response.versiones.some(v => v.nombre === activeVer)
                        : response.versiones.includes(activeVer);
                    if (!tieneActive) {
                        selectHtml += `<option value="${activeVer}" selected>Versión: ${activeVer}</option>`;
                    }
                    
                    versionSelect.innerHTML = selectHtml;
                }

                // Cargar filtros dinámicos
                DistribucionManager.cargarFiltros();

                // Renderizar
                DistribucionManager.renderizarTabla();
                DistribucionManager.actualizarIndicadores();

                UIUtils.mostrarAlerta('Distribución cargada correctamente', 'success');
            } else {
                throw new Error(response.message || 'No se recibieron datos válidos');
            }
        } catch (error) {
            console.error('Error cargando distribución:', error);
            UIUtils.mostrarAlerta('Error cargando distribución: ' + error.message, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    static renderizarTabla() {
        const tbody = document.getElementById('tbody-distribucion');
        if (!tbody) return;

        if (DistribucionManager.datosFiltrados.length === 0) {
            const cols = 10 + (DistribucionManager.meses ? DistribucionManager.meses.length : 0);
            tbody.innerHTML = `
                <tr>
                    <td colspan="${cols}" class="text-center text-muted py-4">
                        <i class="fas fa-inbox mb-2"></i><br>
                        No hay registros para mostrar
                    </td>
                </tr>
            `;
            return;
        }

        // Renderizar cabecera de meses con los porcentajes reales sumados en base a la información mostrada
        const theadRow = document.getElementById('thead-distribucion-row');
        if (theadRow && DistribucionManager.meses.length > 0) {
            // Calcular sumas mensuales de las ventas históricas de lo que se va a mostrar
            let totalMensual = {};
            let totalVentasHistoricasPeriodo = 0;
            
            DistribucionManager.meses.forEach(m => {
                totalMensual[m] = 0;
            });
            
            DistribucionManager.datosFiltrados.forEach(item => {
                if (item.DISTRIBUCION_MENSUAL) {
                    Object.keys(item.DISTRIBUCION_MENSUAL).forEach(m => {
                        const unidades = item.DISTRIBUCION_MENSUAL[m].unidades || 0;
                        totalMensual[m] += unidades;
                        totalVentasHistoricasPeriodo += unidades;
                    });
                }
            });

            let baseHeaderHtml = `
                <th>Rubro</th>
                <th>Categoría</th>
                <th class="text-center bg-secondary text-white">Venta Proyectada</th>
                <th class="text-center bg-dark text-white">Venta Histórica Total</th>
                <th class="text-center bg-info text-dark">Canal</th>
                <th class="text-center">Venta del Canal</th>
                <th class="text-center bg-warning text-dark" style="width: 120px;">Participación %</th>
                <th class="text-center bg-success text-white">Venta Distribuida</th>
            `;
            
            DistribucionManager.meses.forEach(mes => {
                const porc = totalVentasHistoricasPeriodo !== 0 ? Math.round((totalMensual[mes] / totalVentasHistoricasPeriodo) * 100) : 0;
                baseHeaderHtml += `
                    <th class="text-center bg-info text-dark" style="min-width: 90px;">
                        ${mes}<br>
                        <span class="badge bg-light text-dark fs-8">${porc}%</span>
                    </th>`;
            });
            theadRow.innerHTML = baseHeaderHtml;
        }

        // Generar filas con subtotales por categoría
        let html = '';
        let rubroAnterior = '';
        let catAnterior = '';
        let grupoActual = [];

        const generarSubtotal = (grupo) => {
            if (grupo.length === 0) return '';
            const first = grupo[0];
            const safeRubro = first.RUBRO.replace(/[^a-zA-Z0-9]/g, '');
            const safeCat = first.CATEGORIA_PADRE.replace(/[^a-zA-Z0-9]/g, '');
            
            let sumVentaCanal = 0;
            let sumVentaDist = 0;
            const sumMeses = {};
            DistribucionManager.meses.forEach(m => sumMeses[m] = 0);

            grupo.forEach(item => {
                sumVentaCanal += item.VENTA_CANAL || 0;
                sumVentaDist  += item.COMPRA_DISTRIBUIDA || 0;
                if (item.DISTRIBUCION_MENSUAL) {
                    DistribucionManager.meses.forEach(m => {
                        sumMeses[m] += (item.DISTRIBUCION_MENSUAL[m]?.unidades || 0);
                    });
                }
            });

            let mesesSubtotalHtml = '';
            DistribucionManager.meses.forEach(m => {
                mesesSubtotalHtml += `<td class="text-end fw-bold font-monospace" style="background:#e8f5e9; font-size:0.85rem;">${FormatoUtils.formatearNumero(sumMeses[m])}</td>`;
            });

            return `
                <tr id="subtotal-${safeRubro}-${safeCat}" style="background: linear-gradient(90deg,#e3f2fd 0%,#f1f8e9 100%); border-top: 2px solid #90caf9; border-bottom: 2px solid #90caf9;">
                    <td colspan="2" class="fw-bold text-primary" style="font-size:0.85rem; padding: 4px 8px;">
                        <i class="fas fa-sigma me-1 text-primary" style="font-size:0.75rem;"></i>
                        SUBTOTAL &nbsp;<span class="text-muted fw-normal">${first.RUBRO} · ${first.CATEGORIA_PADRE}</span>
                    </td>
                    <td class="text-end fw-bold font-monospace" style="background:#e3f2fd; font-size:0.85rem;">${FormatoUtils.formatearNumero(first.COMPRA_PROYECTADA)}</td>
                    <td class="text-end fw-bold font-monospace" style="background:#e3f2fd; font-size:0.85rem;">${FormatoUtils.formatearNumero(first.VENTA_HISTORICA_TOTAL)}</td>
                    <td style="background:#e3f2fd;"></td>
                    <td class="text-end fw-bold font-monospace" style="background:#e3f2fd; font-size:0.85rem;">${FormatoUtils.formatearNumero(sumVentaCanal)}</td>
                    <td style="background:#e3f2fd;"></td>
                    <td class="text-end fw-bold font-monospace" style="background:#c8e6c9; font-size:0.85rem; color:#1b5e20;" id="subtotal-val-${safeRubro}-${safeCat}">${FormatoUtils.formatearNumero(sumVentaDist)}</td>
                    ${mesesSubtotalHtml}
                </tr>`;
        };

        DistribucionManager.datosFiltrados.forEach((item, index) => {
            const esNuevoGrupo = (item.RUBRO !== rubroAnterior || item.CATEGORIA_PADRE !== catAnterior);

            // Si empieza un nuevo grupo, volcar el subtotal del anterior
            if (esNuevoGrupo && grupoActual.length > 0) {
                html += generarSubtotal(grupoActual);
                grupoActual = [];
            }

            const styleFila = esNuevoGrupo ? 'border-top: 2px solid #ccc;' : '';

            const originalIndex = DistribucionManager.datos.findIndex(d =>
                d.RUBRO === item.RUBRO &&
                d.CATEGORIA_PADRE === item.CATEGORIA_PADRE &&
                d.CANAL === item.CANAL
            );

            let mesesHtml = '';
            if (DistribucionManager.meses.length > 0 && item.DISTRIBUCION_MENSUAL) {
                DistribucionManager.meses.forEach(m => {
                    const infoMes = item.DISTRIBUCION_MENSUAL[m] || { unidades: 0, porcentaje: 0 };
                    mesesHtml += `
                        <td class="text-end font-monospace" style="vertical-align: middle;" id="mes-${originalIndex}-${m}">
                            ${FormatoUtils.formatearNumero(infoMes.unidades)}<br>
                            <small class="text-muted">${infoMes.porcentaje}%</small>
                        </td>`;
                });
            }

            let canalHtml = item.CANAL;
            let rowClass = '';

            if (item.CANAL === 'LOCALES PROPIOS' && item.SUCURSALES_DETALLE && item.SUCURSALES_DETALLE.length > 0) {
                canalHtml = `<span class="toggle-sucursales fw-bold" style="cursor: pointer; color: #0d6efd;" onclick="DistribucionManager.toggleSucursales(${originalIndex}, this)">
                                <i class="fas fa-chevron-right me-2"></i>LOCALES PROPIOS
                             </span>`;
                rowClass = 'locales-propios-row';
            }

            const inputStyle = item.MODIFICADO ? 'background-color: #fff9c4; font-weight: bold; border: 1px solid #ffc107;' : '';
            const indicatorHtml = item.MODIFICADO 
                ? `<br><span class="badge bg-warning text-dark fs-8 mt-1" title="Valor original: ${item.PARTICIPACION_ORIGINAL}%"><i class="fas fa-history me-1"></i>Orig: ${item.PARTICIPACION_ORIGINAL}%</span>` 
                : '';

            html += `
                <tr style="${styleFila}" data-group="${item.RUBRO}|${item.CATEGORIA_PADRE}" class="${rowClass}" id="row-distrib-${originalIndex}">
                    <td style="vertical-align: middle;">${esNuevoGrupo ? `<strong>${item.RUBRO}</strong>` : `<span class="text-muted">${item.RUBRO}</span>`}</td>
                    <td style="vertical-align: middle;">${esNuevoGrupo ? item.CATEGORIA_PADRE : `<span class="text-muted">${item.CATEGORIA_PADRE}</span>`}</td>
                    <td class="text-end bg-light-yellow fw-bold" style="vertical-align: middle;" data-campo="compra-proyectada">${esNuevoGrupo ? FormatoUtils.formatearNumero(item.COMPRA_PROYECTADA) : ''}</td>
                    <td class="text-end" style="vertical-align: middle;" data-campo="venta-historica-total">${esNuevoGrupo ? FormatoUtils.formatearNumero(item.VENTA_HISTORICA_TOTAL) : ''}</td>
                    <td class="bg-light-blue font-monospace" style="vertical-align: middle;">${canalHtml}</td>
                    <td class="text-end" style="vertical-align: middle;">${FormatoUtils.formatearNumero(item.VENTA_CANAL)}</td>
                    <td class="text-center font-monospace" style="vertical-align: middle;">
                        <input type="number" step="0.01" class="form-control form-control-sm text-end input-participacion" 
                                value="${item.PARTICIPACION}" 
                                style="width: 90px; display: inline-block; ${inputStyle}"
                                oninput="DistribucionManager.actualizarParticipacion(${originalIndex}, this.value)">
                        ${indicatorHtml}
                    </td>
                    <td class="text-end fw-bold bg-light-green" style="vertical-align: middle;" id="distribucion-${originalIndex}">${FormatoUtils.formatearNumero(item.COMPRA_DISTRIBUIDA)}</td>
                    ${mesesHtml}
                </tr>
            `;

            grupoActual.push(item);
            rubroAnterior = item.RUBRO;
            catAnterior = item.CATEGORIA_PADRE;
        });

        // Subtotal del último grupo
        if (grupoActual.length > 0) {
            html += generarSubtotal(grupoActual);
        }

        tbody.innerHTML = html;
        DistribucionManager.validarGrupos();
    }

    static toggleSucursales(index, element) {
        const item = DistribucionManager.datos[index];
        const row = document.getElementById(`row-distrib-${index}`);
        if (!row || !item.SUCURSALES_DETALLE) return;

        const icon = element.querySelector('i');
        const isExpanded = icon.classList.contains('fa-chevron-down');

        if (isExpanded) {
            // Colapsar sucursales
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-right');
            
            let nextRow = row.nextElementSibling;
            while (nextRow && nextRow.classList.contains(`child-row-${index}`)) {
                const toRemove = nextRow;
                nextRow = nextRow.nextElementSibling;
                toRemove.remove();
            }
        } else {
            // Expandir sucursales
            icon.classList.remove('fa-chevron-right');
            icon.classList.add('fa-chevron-down');

            let childHtml = '';
            item.SUCURSALES_DETALLE.forEach(suc => {
                let mesesSucHtml = '';
                DistribucionManager.meses.forEach(m => {
                    const infoMes = suc.MESES[m] || { unidades: 0, porcentaje: 0 };
                    mesesSucHtml += `
                        <td class="text-end font-monospace text-muted" style="font-size: 0.85rem; padding-top: 4px; padding-bottom: 4px;">
                            ${FormatoUtils.formatearNumero(infoMes.unidades)}
                        </td>`;
                });

                childHtml += `
                    <tr class="table-light child-row-${index} bg-light-subtle" style="font-size: 0.85rem; border-left: 4px solid #0d6efd; vertical-align: middle;">
                        <td class="text-muted"></td>
                        <td class="text-muted"></td>
                        <td></td>
                        <td></td>
                        <td class="ps-4 text-primary font-monospace" style="padding-top: 4px; padding-bottom: 4px;">
                            <i class="fas fa-store me-1 text-muted"></i>${suc.SUCURSAL}
                        </td>
                        <td class="text-end text-muted font-monospace" style="padding-top: 4px; padding-bottom: 4px;">
                            ${FormatoUtils.formatearNumero(suc.VENTA_HISTORICA)}
                        </td>
                        <td class="text-end text-muted font-monospace" style="padding-top: 4px; padding-bottom: 4px;">
                            ${suc.PARTICIPACION}%
                        </td>
                        <td></td>
                        ${mesesSucHtml}
                    </tr>
                `;
            });

            row.insertAdjacentHTML('afterend', childHtml);
        }
    }

    static actualizarParticipacion(index, value) {
        const val = parseFloat(value) || 0;
        const item = DistribucionManager.datos[index];
        
        // 1. Guardar el nuevo valor de participación y marcar si cambió
        item.PARTICIPACION = val;
        item.MODIFICADO = (Math.abs(val - item.PARTICIPACION_ORIGINAL) > 0.01);
        
        // 2. Calcular nueva venta distribuida
        item.COMPRA_DISTRIBUIDA = Math.round(item.COMPRA_PROYECTADA * (val / 100));
        item.DISTRIBUCION_FINAL = item.COMPRA_DISTRIBUIDA;
        
        // 3. Actualizar la celda de compra distribuida en el DOM
        const cellDist = document.getElementById(`distribucion-${index}`);
        if (cellDist) {
            cellDist.textContent = FormatoUtils.formatearNumero(item.COMPRA_DISTRIBUIDA);
        }
        
        // 4. Distribuir a los meses correspondientes en base a la estacionalidad (%)
        if (item.DISTRIBUCION_MENSUAL) {
            let sumMeses = 0;
            let maxP = -1;
            let mesMax = null;
            
            Object.keys(item.DISTRIBUCION_MENSUAL).forEach(m => {
                const p = (item.DISTRIBUCION_MENSUAL[m].porcentaje || 0) / 100;
                if (p > maxP) {
                    maxP = p;
                    mesMax = m;
                }
                const unidades = Math.round(item.COMPRA_DISTRIBUIDA * p);
                item.DISTRIBUCION_MENSUAL[m].unidades = unidades;
                sumMeses += unidades;
            });
            
            if (item.COMPRA_DISTRIBUIDA !== sumMeses && mesMax !== null) {
                const diff = item.COMPRA_DISTRIBUIDA - sumMeses;
                item.DISTRIBUCION_MENSUAL[mesMax].unidades += diff;
            }
            
            // Actualizar celdas mensuales en el DOM
            Object.keys(item.DISTRIBUCION_MENSUAL).forEach(m => {
                const cellMes = document.getElementById(`mes-${index}-${m}`);
                if (cellMes) {
                    cellMes.innerHTML = `${FormatoUtils.formatearNumero(item.DISTRIBUCION_MENSUAL[m].unidades)}<br><small class="text-muted">${item.DISTRIBUCION_MENSUAL[m].porcentaje}%</small>`;
                }
            });
        }
        
        // 5. Actualizar el subtotal de este grupo
        DistribucionManager.actualizarSubtotalGrupo(item.RUBRO, item.CATEGORIA_PADRE);
        
        // 6. Actualizar indicadores superiores
        DistribucionManager.actualizarIndicadores();
        
        // 7. Validar si los totales del grupo cierran
        DistribucionManager.validarGrupos();
    }

    static actualizarSubtotalGrupo(rubro, categoria) {
        const grupo = DistribucionManager.datos.filter(d => 
            d.RUBRO === rubro && d.CATEGORIA_PADRE === categoria
        );
        
        if (grupo.length === 0) return;
        
        const first = grupo[0];
        let sumVentaCanal = 0;
        let sumVentaDist = 0;
        const sumMeses = {};
        DistribucionManager.meses.forEach(m => sumMeses[m] = 0);

        grupo.forEach(item => {
            sumVentaCanal += item.VENTA_CANAL || 0;
            sumVentaDist  += item.COMPRA_DISTRIBUIDA || 0;
            if (item.DISTRIBUCION_MENSUAL) {
                DistribucionManager.meses.forEach(m => {
                    sumMeses[m] += (item.DISTRIBUCION_MENSUAL[m]?.unidades || 0);
                });
            }
        });

        const safeRubro = rubro.replace(/[^a-zA-Z0-9]/g, '');
        const safeCat = categoria.replace(/[^a-zA-Z0-9]/g, '');
        const subtotalRow = document.getElementById(`subtotal-${safeRubro}-${safeCat}`);
        
        if (subtotalRow) {
            let mesesSubtotalHtml = '';
            DistribucionManager.meses.forEach(m => {
                mesesSubtotalHtml += `<td class="text-end fw-bold font-monospace" style="background:#e8f5e9; font-size:0.85rem;">${FormatoUtils.formatearNumero(sumMeses[m])}</td>`;
            });
            
            subtotalRow.innerHTML = `
                <td colspan="2" class="fw-bold text-primary" style="font-size:0.85rem; padding: 4px 8px;">
                    <i class="fas fa-sigma me-1 text-primary" style="font-size:0.75rem;"></i>
                    SUBTOTAL &nbsp;<span class="text-muted fw-normal">${first.RUBRO} · ${first.CATEGORIA_PADRE}</span>
                </td>
                <td class="text-end fw-bold font-monospace" style="background:#e3f2fd; font-size:0.85rem;">${FormatoUtils.formatearNumero(first.COMPRA_PROYECTADA)}</td>
                <td class="text-end fw-bold font-monospace" style="background:#e3f2fd; font-size:0.85rem;">${FormatoUtils.formatearNumero(first.VENTA_HISTORICA_TOTAL)}</td>
                <td style="background:#e3f2fd;"></td>
                <td class="text-end fw-bold font-monospace" style="background:#e3f2fd; font-size:0.85rem;">${FormatoUtils.formatearNumero(sumVentaCanal)}</td>
                <td style="background:#e3f2fd;"></td>
                <td class="text-end fw-bold font-monospace" style="background:#c8e6c9; font-size:0.85rem; color:#1b5e20;" id="subtotal-val-${safeRubro}-${safeCat}">${FormatoUtils.formatearNumero(sumVentaDist)}</td>
                ${mesesSubtotalHtml}
            `;
        }
    }

    static validarGrupos() {
        const filas = document.querySelectorAll('#tbody-distribucion tr');
        const grupos = {};

        // Agrupar filas del DOM por clave de grupo
        filas.forEach(fila => {
            const key = fila.getAttribute('data-group');
            if (!key) return;

            if (!grupos[key]) {
                grupos[key] = {
                    filas: [],
                    compraProyectada: 0,
                    sumaFinal: 0
                };
            }

            grupos[key].filas.push(fila);

            // Obtener compra proyectada y final
            const compraCell = fila.querySelector('[data-campo="compra-proyectada"]');
            if (compraCell && compraCell.textContent) {
                grupos[key].compraProyectada = parseInt(compraCell.textContent.replace(/\./g, '')) || 0;
            }

            const distCell = fila.querySelector('[id^="distribucion-"]');
            if (distCell) {
                grupos[key].sumaFinal += parseInt(distCell.textContent.replace(/\./g, '')) || 0;
            }
        });

        // Validar e iluminar si hay errores
        Object.keys(grupos).forEach(key => {
            const g = grupos[key];
            const coincide = g.compraProyectada === g.sumaFinal;

            g.filas.forEach(fila => {
                const distCell = fila.querySelector('[id^="distribucion-"]');
                if (distCell) {
                    if (coincide) {
                        distCell.classList.remove('bg-danger', 'text-white');
                        distCell.classList.add('text-success');
                    } else {
                        distCell.classList.remove('text-success');
                        distCell.classList.add('bg-danger', 'text-white');
                    }
                }
            });
        });
    }

    static actualizarIndicadores() {
        // Calcular sumas
        const rubrosUnicos = new Set();
        const categoriasUnicas = new Set();
        let totalCompra = 0;
        let totalDistribuido = 0;

        // Sumar compra proyectada solo una vez por grupo rubro|categoria
        const gruposVistos = new Set();

        DistribucionManager.datos.forEach(item => {
            rubrosUnicos.add(item.RUBRO);
            categoriasUnicas.add(item.RUBRO + '|' + item.CATEGORIA_PADRE);
            totalDistribuido += item.DISTRIBUCION_FINAL;

            const grupoClave = item.RUBRO + '|' + item.CATEGORIA_PADRE;
            if (!gruposVistos.has(grupoClave)) {
                gruposVistos.add(grupoClave);
                totalCompra += item.COMPRA_PROYECTADA;
            }
        });

        // Escribir en DOM
        const format = FormatoUtils.formatearNumero;
        document.getElementById('card-compra-total').textContent = format(totalCompra);
        document.getElementById('card-total-distribuido').textContent = format(totalDistribuido);
        document.getElementById('card-cant-canales').textContent = DistribucionManager.canales.length;
        document.getElementById('card-cant-rubros').textContent = rubrosUnicos.size;
        document.getElementById('card-cant-categorias').textContent = categoriasUnicas.size;
        document.getElementById('card-ult-act').textContent = new Date().toLocaleTimeString();
        
        // Coincide total?
        const cardTotal = document.getElementById('card-total-distribuido');
        if (totalCompra === totalDistribuido) {
            cardTotal.className = 'badge bg-success fs-5 fw-bold';
        } else {
            cardTotal.className = 'badge bg-danger fs-5 fw-bold';
        }
    }

    static cargarFiltros() {
        const rubroSel = document.getElementById('filtro-rubro-distribucion');
        const catSel = document.getElementById('filtro-categoria-distribucion');
        const canalSel = document.getElementById('filtro-canal-distribucion');

        if (!rubroSel || !catSel || !canalSel) return;

        // Limpiar
        rubroSel.innerHTML = '<option value="">Todos los rubros</option>';
        catSel.innerHTML = '<option value="">Todas las categorías</option>';
        canalSel.innerHTML = '<option value="">Todos los canales</option>';

        const rubros = new Set();
        const categorias = new Set();

        DistribucionManager.datos.forEach(item => {
            rubros.add(item.RUBRO);
            categorias.add(item.CATEGORIA_PADRE);
        });

        // Rubros
        Array.from(rubros).sort().forEach(r => {
            rubroSel.innerHTML += `<option value="${r}">${r}</option>`;
        });

        // Categorías
        Array.from(categorias).sort().forEach(c => {
            catSel.innerHTML += `<option value="${c}">${c}</option>`;
        });

        // Canales
        DistribucionManager.canales.sort().forEach(c => {
            canalSel.innerHTML += `<option value="${c}">${c}</option>`;
        });
    }

    static filtrarDatos() {
        const query = document.getElementById('search-distribucion').value.toLowerCase().trim();
        const rubro = document.getElementById('filtro-rubro-distribucion').value;
        const categoria = document.getElementById('filtro-categoria-distribucion').value;
        const canal = document.getElementById('filtro-canal-distribucion').value;

        DistribucionManager.datosFiltrados = DistribucionManager.datos.filter(item => {
            const matchesQuery = !query || 
                                 item.RUBRO.toLowerCase().includes(query) || 
                                 item.CATEGORIA_PADRE.toLowerCase().includes(query);
            const matchesRubro = !rubro || item.RUBRO === rubro;
            const matchesCat = !categoria || item.CATEGORIA_PADRE === categoria;
            const matchesCanal = !canal || item.CANAL === canal;

            return matchesQuery && matchesRubro && matchesCat && matchesCanal;
        });

        DistribucionManager.renderizarTabla();
        document.getElementById('count-distribucion').textContent = `${DistribucionManager.datosFiltrados.length} registros`;
    }

    static limpiarFiltros() {
        document.getElementById('search-distribucion').value = '';
        document.getElementById('filtro-rubro-distribucion').value = '';
        document.getElementById('filtro-categoria-distribucion').value = '';
        document.getElementById('filtro-canal-distribucion').value = '';
        
        DistribucionManager.datosFiltrados = [...DistribucionManager.datos];
        DistribucionManager.renderizarTabla();
        document.getElementById('count-distribucion').textContent = `${DistribucionManager.datosFiltrados.length} registros`;
    }

    static restaurarAutomatico() {
        DistribucionManager.datos.forEach(item => {
            item.AJUSTE_MANUAL = 0;
            item.DISTRIBUCION_FINAL = item.COMPRA_DISTRIBUIDA;
        });
        DistribucionManager.renderizarTabla();
        DistribucionManager.actualizarIndicadores();
        UIUtils.mostrarAlerta('Cálculos automáticos restaurados', 'info');
    }

    static async guardarDistribucion() {
        // Verificar si la suma de todas las distribuciones finales coincide con las compras proyectadas
        let totalProy = 0;
        let totalFinal = 0;
        const gruposInvalidos = [];

        // Validar por grupo rubro|categoria
        const agrupado = {};
        DistribucionManager.datos.forEach(item => {
            const key = item.RUBRO + '|' + item.CATEGORIA_PADRE;
            if (!agrupado[key]) {
                agrupado[key] = {
                    compraProyectada: item.COMPRA_PROYECTADA,
                    sumaFinal: 0
                };
            }
            agrupado[key].sumaFinal += item.DISTRIBUCION_FINAL;
        });

        Object.keys(agrupado).forEach(key => {
            const g = agrupado[key];
            totalProy += g.compraProyectada;
            totalFinal += g.sumaFinal;

            if (g.compraProyectada !== g.sumaFinal) {
                gruposInvalidos.push(key);
            }
        });

        if (gruposInvalidos.length > 0) {
            UIUtils.mostrarAlerta(
                `No se puede guardar: La suma distribuida no coincide con la Compra Proyectada para ${gruposInvalidos.length} rubros/categorías.`,
                'error'
            );
            return;
        }

        const currentVer = document.getElementById('filtro-version-distribucion')?.value || 'Por defecto';
        const nombre = await UIUtils.solicitarTexto('Guardar Versión', 'Ingrese el nombre para esta versión de la distribución:', currentVer);
        if (nombre === null) return; // Cancelado por el usuario
        const nombreLimpio = nombre.trim() || 'Por defecto';

        try {
            UIUtils.mostrarLoading(true);
            
            // Mapear datos a guardar
            const filas = DistribucionManager.datos.map(d => ({
                pais: d.PAIS,
                temporada: d.TEMPORADA,
                rubro: d.RUBRO,
                categoria_padre: d.CATEGORIA_PADRE,
                canal: d.CANAL,
                compra_proyectada: d.COMPRA_PROYECTADA,
                venta_historica_canal: d.VENTA_CANAL,
                participacion_porcentaje: d.PARTICIPACION,
                compra_distribuida: d.COMPRA_DISTRIBUIDA,
                ajuste_manual: 0,
                distribucion_final: d.COMPRA_DISTRIBUIDA,
                periodo_analisis: d.PERIODO_ANALISIS,
                nombre_distribucion: nombreLimpio
            }));

            const response = await APIClient.guardarDistribucion(filas);
            if (response.success) {
                UIUtils.mostrarAlerta('Distribución guardada correctamente', 'success');
                
                // Actualizar la lista en el dropdown de versiones
                const versionSelect = document.getElementById('filtro-version-distribucion');
                if (versionSelect) {
                    versionSelect.value = nombreLimpio;
                }
                DistribucionManager.cargarDatos();
            } else {
                throw new Error(response.message);
            }
        } catch (error) {
            console.error('Error al guardar distribución:', error);
            UIUtils.mostrarAlerta('Error al guardar: ' + error.message, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    static cambiarVersion(value) {
        // 1. Buscar periodo guardado de esta versión
        const periodo = DistribucionManager.periodosVersiones?.[value];
        if (periodo && periodo.includes(' a ')) {
            const partes = periodo.split(' a ');
            const desdeInput = document.getElementById('filtro-desde-distribucion');
            const hastaInput = document.getElementById('filtro-hasta-distribucion');
            if (desdeInput && hastaInput) {
                desdeInput.value = partes[0].trim();
                hastaInput.value = partes[1].trim();
            }
        }
    }

    static irAPaso(paso) {
        DistribucionManager.pasoActivo = paso;

        // 1. Quitar activo de todos los botones y paneles
        document.querySelectorAll('.stepper-container .step-item').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelectorAll('#distribucion .step-pane').forEach(pane => {
            pane.classList.remove('active');
        });

        // 2. Activar el botón y panel seleccionado
        const activeBtn = document.getElementById(`btn-step-${paso}`);
        if (activeBtn) activeBtn.classList.add('active');

        const activePane = document.getElementById(`paso-${paso}`);
        if (activePane) activePane.classList.add('active');

        // 3. Mostrar/ocultar selector de canal según el paso
        const colCanal = document.getElementById('col-filtro-canal');
        if (colCanal) {
            colCanal.style.display = (paso === 2) ? 'none' : 'block';
        }
    }

    static ejecutarCargar() {
        if (DistribucionManager.pasoActivo === 2) {
            DistribucionManager.cargarCostos();
        } else {
            DistribucionManager.cargarDatos();
        }
    }

    static ejecutarGuardar() {
        if (DistribucionManager.pasoActivo === 2) {
            DistribucionManager.guardarCostos();
        } else {
            DistribucionManager.guardarDistribucion();
        }
    }

    static ejecutarExcel() {
        if (DistribucionManager.pasoActivo === 2) {
            DistribucionManager.exportarExcelCostos();
        } else {
            DistribucionManager.exportarExcel();
        }
    }

    static async cargarCostos() {
        try {
            UIUtils.mostrarLoading(true);

            const temporadaSelect = document.getElementById('filtro-temporada-distribucion');
            const temporada = temporadaSelect ? temporadaSelect.value : 'VERANO';
            const versionSelect = document.getElementById('filtro-version-distribucion');
            const version = versionSelect ? versionSelect.value : 'Por defecto';

            console.log('=== CARGAR COSTOS ===');
            console.log('Temporada:', temporada, '| Version:', version);

            const response = await APIClient.obtenerDatosCosto(temporada, version);

            console.log('Response recibida:', JSON.stringify(response?.data?.[0]));

            if (response.success && response.data) {
                DistribucionManager.datosCostos = response.data;
                DistribucionManager.mesesCostos = response.meses || [];
                DistribucionManager.mesesCostosClaves = response.meses_claves || [];

                console.log('Primera fila COSTO_PROM:', response.data[0]?.COSTO_PROM, '| INC_FOB:', response.data[0]?.INC_FOB);
                
                DistribucionManager.renderizarTablaCostos();
                UIUtils.mostrarAlerta('Costos de proyección cargados', 'success');
            } else {
                throw new Error(response.message || 'No se recibieron datos de costos válidos');
            }
        } catch (error) {
            console.error('Error cargando costos:', error);
            UIUtils.mostrarAlerta('Error cargando costos: ' + error.message, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    static renderizarTablaCostos() {
        const headRow = document.getElementById('thead-costos-row');
        const tbody = document.getElementById('tbody-costos');
        
        if (!headRow || !tbody) return;

        // 1. Reconstruir cabeceras
        let headHtml = `
            <th>Rubro</th>
            <th>Categoría</th>
            <th class="text-center bg-secondary text-white">Venta Proyectada</th>
            <th class="text-center bg-info text-dark" style="width: 120px;">Costo Prom</th>
            <th class="text-center bg-warning text-dark" style="width: 120px;">Inc Fob %</th>
            <th class="text-center bg-success text-white" style="width: 120px;">Vcosto</th>
        `;

        DistribucionManager.mesesCostos.forEach(m => {
            headHtml += `<th class="text-center bg-dark text-white">${m}</th>`;
        });
        headHtml += `<th class="text-center bg-primary text-white">Costo Total</th>`;
        headRow.innerHTML = headHtml;

        // 2. Renderizar filas de datos
        if (DistribucionManager.datosCostos.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${7 + DistribucionManager.mesesCostos.length}" class="text-center text-muted py-4">
                        No hay datos cargados para esta versión.
                    </td>
                </tr>`;
            return;
        }

        let bodyHtml = '';
        DistribucionManager.datosCostos.forEach((row, index) => {
            // Calcular Vcosto
            const vcosto = row.COSTO_PROM * (1 + row.INC_FOB / 100);
            row.VCOSTO = vcosto;

            let mesesHtml = '';
            let costoTotal = 0;

            DistribucionManager.mesesCostosClaves.forEach(m => {
                const unidades = row.MESES_UNIDADES[m] || 0;
                const costoMes = vcosto * unidades;
                costoTotal += costoMes;
                mesesHtml += `<td class="text-end font-monospace" id="costo-mes-${index}-${m}" style="font-size:0.85rem;">${FormatoUtils.formatearNumero(Math.round(costoMes))}</td>`;
            });

            bodyHtml += `
                <tr style="vertical-align: middle;">
                    <td class="fw-bold">${row.RUBRO}</td>
                    <td>${row.CATEGORIA_PADRE}</td>
                    <td class="text-end font-monospace fw-bold" style="background:#f8f9fa;">${FormatoUtils.formatearNumero(row.COMPRA_DISTRIBUIDA)}</td>
                    <td>
                        <input type="number" class="form-control form-control-sm text-end font-monospace border-primary-subtle" 
                            step="any" value="${(row.COSTO_PROM != null && row.COSTO_PROM !== '') ? row.COSTO_PROM : ''}" 
                            oninput="DistribucionManager.actualizarCostoFila(${index}, 'COSTO_PROM', this.value)" 
                            style="width: 110px; display: inline-block;">
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm text-end font-monospace border-warning-subtle" 
                            step="any" value="${(row.INC_FOB != null && row.INC_FOB !== '') ? row.INC_FOB : ''}" 
                            oninput="DistribucionManager.actualizarCostoFila(${index}, 'INC_FOB', this.value)" 
                            style="width: 110px; display: inline-block;">
                    </td>
                    <td class="text-end font-monospace fw-bold text-success" id="vcosto-${index}">${row.VCOSTO.toFixed(2)}</td>
                    ${mesesHtml}
                    <td class="text-end font-monospace fw-bold bg-primary bg-opacity-10" id="costo-total-${index}">${FormatoUtils.formatearNumero(Math.round(costoTotal))}</td>
                </tr>
            `;
        });

        tbody.innerHTML = bodyHtml;
    }

    static actualizarCostoFila(index, campo, valor) {
        const val = parseFloat(valor) || 0;
        const row = DistribucionManager.datosCostos[index];
        row[campo] = val;

        // Recalcular Vcosto
        const vcosto = row.COSTO_PROM * (1 + row.INC_FOB / 100);
        row.VCOSTO = vcosto;

        // Actualizar Vcosto en DOM
        const vcostoCell = document.getElementById(`vcosto-${index}`);
        if (vcostoCell) vcostoCell.textContent = vcosto.toFixed(2);

        // Recalcular meses de costo en DOM
        let costoTotal = 0;
        DistribucionManager.mesesCostosClaves.forEach(m => {
            const unidades = row.MESES_UNIDADES[m] || 0;
            const costoMes = vcosto * unidades;
            costoTotal += costoMes;

            const cellMes = document.getElementById(`costo-mes-${index}-${m}`);
            if (cellMes) cellMes.textContent = FormatoUtils.formatearNumero(Math.round(costoMes));
        });

        // Actualizar costo total en DOM
        const totalCell = document.getElementById(`costo-total-${index}`);
        if (totalCell) totalCell.textContent = FormatoUtils.formatearNumero(Math.round(costoTotal));
    }

    static async guardarCostos() {
        try {
            UIUtils.mostrarLoading(true);

            const response = await APIClient.guardarCostos(DistribucionManager.datosCostos);
            if (response.success) {
                UIUtils.mostrarAlerta('Costos guardados correctamente. Recargando...', 'success');
                // Recargar desde la base para confirmar lo guardado
                await DistribucionManager.cargarCostos();
            } else {
                throw new Error(response.message);
            }
        } catch (error) {
            console.error('Error al guardar costos:', error);
            UIUtils.mostrarAlerta('Error al guardar costos: ' + error.message, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    static exportarExcelCostos() {
        if (DistribucionManager.datosCostos.length === 0) {
            UIUtils.mostrarAlerta('No hay costos para exportar', 'warning');
            return;
        }

        let csvContent = "\uFEFF"; // BOM para acentos
        let cabecera = "Rubro;Categoría;Venta Proyectada;Costo Prom;Inc Fob %;Vcosto";
        
        DistribucionManager.mesesCostos.forEach(m => {
            cabecera += `;${m}`;
        });
        cabecera += ";Costo Total";
        csvContent += cabecera + "\n";

        DistribucionManager.datosCostos.forEach(row => {
            const vcosto = row.COSTO_PROM * (1 + row.INC_FOB / 100);
            let linea = [
                row.RUBRO,
                row.CATEGORIA_PADRE,
                row.COMPRA_DISTRIBUIDA,
                row.COSTO_PROM,
                row.INC_FOB,
                vcosto.toFixed(2)
            ];

            let costoTotal = 0;
            DistribucionManager.mesesCostosClaves.forEach(m => {
                const unidades = row.MESES_UNIDADES[m] || 0;
                const costoMes = vcosto * unidades;
                costoTotal += costoMes;
                linea.push(Math.round(costoMes));
            });
            linea.push(Math.round(costoTotal));

            const lineaCsv = linea.map(val => `"${String(val).replace(/"/g, '""')}"`).join(';');
            csvContent += lineaCsv + "\n";
        });

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", `Costos_Proyeccion_${new Date().toISOString().slice(0,10)}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    static exportarExcel() {
        if (DistribucionManager.datosFiltrados.length === 0) {
            UIUtils.mostrarAlerta('No hay datos para exportar', 'warning');
            return;
        }
        
        let csvContent = "\uFEFF"; // BOM para acentos
        
        // Cabeceras base
        let cabecera = "Rubro;Categoría;Venta Proyectada;Venta Histórica Total;Canal;Venta Canal;Participación %;Venta Distribuida";
        
        // Agregar cabeceras de meses si existen
        if (DistribucionManager.meses.length > 0) {
            DistribucionManager.meses.forEach(m => {
                cabecera += `;${m} (Cant);${m} (%)`;
            });
        }
        csvContent += cabecera + "\n";

        DistribucionManager.datosFiltrados.forEach(item => {
            let linea = [
                item.RUBRO,
                item.CATEGORIA_PADRE,
                item.COMPRA_PROYECTADA,
                item.VENTA_HISTORICA_TOTAL,
                item.CANAL,
                item.VENTA_CANAL,
                item.PARTICIPACION,
                item.COMPRA_DISTRIBUIDA
            ];
            
            // Agregar valores de meses si existen
            if (DistribucionManager.meses.length > 0 && item.DISTRIBUCION_MENSUAL) {
                DistribucionManager.meses.forEach(m => {
                    const infoMes = item.DISTRIBUCION_MENSUAL[m] || { unidades: 0, porcentaje: 0 };
                    linea.push(infoMes.unidades);
                    linea.push(`${infoMes.porcentaje}%`);
                });
            }

            const lineaCsv = linea.map(val => `"${String(val).replace(/"/g, '""')}"`).join(';');
            csvContent += lineaCsv + "\n";
        });

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", `Distribucion_por_Canal_${new Date().toISOString().slice(0,10)}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    static buscarDatos() {
        DistribucionManager.filtrarDatos();
    }
}
