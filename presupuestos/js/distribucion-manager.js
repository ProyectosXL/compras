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

    // Moneda: ARS o USD (Default USD)
    static modoMoneda = 'USD';
    static tipoCambio = {}; // { '1-2025': 1250.0, '2-2025': 1280.0, ... }

    // Mapa mes label (ES) -> número de mes
    static MESES_NUM = {
        'Ene': 1, 'Feb': 2, 'Mar': 3, 'Abr': 4, 'May': 5, 'Jun': 6,
        'Jul': 7, 'Ago': 8, 'Sep': 9, 'Oct': 10, 'Nov': 11, 'Dic': 12
    };

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
                <th class="text-center bg-secondary text-white">Venta Proyect</th>
                <th class="text-center bg-dark text-white">Venta Histórica Total</th>
                <th class="text-center bg-info text-dark">Canal</th>
                <th class="text-center">Venta del Canal</th>
                <th class="text-center bg-warning text-dark" style="width: 100px;">Participación %</th>
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
            baseHeaderHtml += `<th class="text-center bg-dark text-white" style="width: 80px;">Total %</th>`;
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
            let sumParticipacion = 0;
            const sumMeses = {};
            DistribucionManager.meses.forEach(m => sumMeses[m] = 0);

            grupo.forEach(item => {
                sumVentaCanal += item.VENTA_CANAL || 0;
                sumVentaDist  += item.COMPRA_DISTRIBUIDA || 0;
                sumParticipacion += parseFloat(item.PARTICIPACION) || 0;
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

            const is100 = Math.abs(sumParticipacion - 100) < 0.01;
            const partStyle = is100 ? 'color: #198754; font-weight: bold;' : 'color: #dc3545; background-color: #f8d7da; font-weight: bold; border: 1px solid #f5c2c7;';

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
                    <td class="text-end font-monospace" style="${partStyle} font-size:0.85rem;" id="subtotal-part-${safeRubro}-${safeCat}">${Math.round(sumParticipacion)}%</td>
                    <td class="text-end fw-bold font-monospace" style="background:#c8e6c9; font-size:0.85rem; color:#1b5e20;" id="subtotal-val-${safeRubro}-${safeCat}">${FormatoUtils.formatearNumero(sumVentaDist)}</td>
                    ${mesesSubtotalHtml}
                    <td style="background:#e8f5e9;"></td>
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
            let sumPorc = 0;
            if (DistribucionManager.meses.length > 0 && item.DISTRIBUCION_MENSUAL) {
                DistribucionManager.meses.forEach(m => {
                    const infoMes = item.DISTRIBUCION_MENSUAL[m] || { unidades: 0, porcentaje: 0 };
                    sumPorc += parseFloat(infoMes.porcentaje) || 0;
                    mesesHtml += `
                        <td class="text-end font-monospace" style="vertical-align: middle;" id="mes-${originalIndex}-${m}">
                            <span class="d-block fw-bold">${FormatoUtils.formatearNumero(infoMes.unidades)}</span>
                            <div class="d-flex align-items-center justify-content-end mt-1">
                                <input type="number" step="1" class="text-end p-0 pe-1 input-mes-porc" 
                                       value="${Math.round(infoMes.porcentaje)}" 
                                       style="max-width: 45px; font-size: 0.75rem; border: none; border-bottom: 1px dashed #ccc; background: transparent; text-align: right;"
                                       oninput="DistribucionManager.actualizarPorcentajeMes(${originalIndex}, '${m}', this.value)">
                                <span style="font-size: 0.7rem; color: #777; margin-left: 2px;">%</span>
                            </div>
                        </td>`;
                });
            }

            const is100 = Math.abs(sumPorc - 100) < 0.05;
            const sumPorcBadgeClass = is100 ? 'bg-success' : 'bg-danger';
            mesesHtml += `
                <td class="text-center font-monospace" style="vertical-align: middle;" id="mes-total-${originalIndex}">
                    <span class="badge ${sumPorcBadgeClass} fs-8" id="mes-total-val-${originalIndex}">${Math.round(sumPorc)}%</span>
                </td>`;

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
                ? `<br><span class="badge bg-warning text-dark fs-8 mt-1" title="Valor original: ${Math.round(item.PARTICIPACION_ORIGINAL)}%"><i class="fas fa-history me-1"></i>Orig: ${Math.round(item.PARTICIPACION_ORIGINAL)}%</span>` 
                : '';

            const inputVentaProyHtml = esNuevoGrupo 
                ? `<div class="d-flex align-items-center justify-content-end gap-1">
                    <input type="number" step="1" class="form-control form-control-sm text-end input-compra-proyectada fw-bold" 
                           value="${item.COMPRA_PROYECTADA}" 
                           style="max-width: 100px; display: inline-block; background-color: #fffde7; border: 1px solid #ffe082;"
                           oninput="DistribucionManager.actualizarCompraProyectada('${item.RUBRO.replace(/'/g, "\\'")}', '${item.CATEGORIA_PADRE.replace(/'/g, "\\'")}', this.value)">
                   </div>`
                : '';

            html += `
                <tr style="${styleFila}" data-group="${item.RUBRO}|${item.CATEGORIA_PADRE}" class="${rowClass}" id="row-distrib-${originalIndex}">
                    <td style="vertical-align: middle;">${esNuevoGrupo ? `<strong>${item.RUBRO}</strong>` : `<span class="text-muted">${item.RUBRO}</span>`}</td>
                    <td style="vertical-align: middle;">${esNuevoGrupo ? item.CATEGORIA_PADRE : `<span class="text-muted">${item.CATEGORIA_PADRE}</span>`}</td>
                    <td class="text-end bg-light-yellow fw-bold" style="vertical-align: middle;" data-campo="compra-proyectada">${inputVentaProyHtml}</td>
                    <td class="text-end" style="vertical-align: middle;" data-campo="venta-historica-total">${esNuevoGrupo ? FormatoUtils.formatearNumero(item.VENTA_HISTORICA_TOTAL) : ''}</td>
                    <td class="bg-light-blue font-monospace" style="vertical-align: middle;">${canalHtml}</td>
                    <td class="text-end" style="vertical-align: middle;">${FormatoUtils.formatearNumero(item.VENTA_CANAL)}</td>
                    <td class="text-center font-monospace" style="vertical-align: middle;">
                        <input type="number" step="1" class="form-control form-control-sm text-end input-participacion" 
                                value="${Math.round(item.PARTICIPACION)}" 
                                style="width: 70px; display: inline-block; ${inputStyle}"
                                oninput="DistribucionManager.actualizarParticipacion(${originalIndex}, this.value)">
                        ${indicatorHtml}
                    </td>
                    <td class="text-end fw-bold bg-light-green" style="vertical-align: middle;" id="distribucion-${originalIndex}">${FormatoUtils.formatearNumero(item.COMPRA_DISTRIBUIDA)}</td>
                    ${mesesHtml}
                </tr>`;
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

        // Validar filas mensuales tras renderizar
        DistribucionManager.datosFiltrados.forEach((item) => {
            const originalIndex = DistribucionManager.datos.findIndex(d =>
                d.RUBRO === item.RUBRO &&
                d.CATEGORIA_PADRE === item.CATEGORIA_PADRE &&
                d.CANAL === item.CANAL
            );
            if (originalIndex !== -1) {
                DistribucionManager.validarFilaMensual(originalIndex);
            }
        });

        // Reconciliar de forma automática las diferencias por redondeo en los grupos que sumen 100%
        const gruposUnicos = new Set();
        DistribucionManager.datosFiltrados.forEach(item => {
            gruposUnicos.add(`${item.RUBRO}|${item.CATEGORIA_PADRE}`);
        });
        gruposUnicos.forEach(clave => {
            const parts = clave.split('|');
            DistribucionManager.ajustarRedondeoGrupo(parts[0], parts[1]);
        });
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

    static ajustarRedondeoGrupo(rubro, categoria) {
        // Encontrar todos los items de este grupo
        const grupo = DistribucionManager.datos.filter(d => 
            d.RUBRO === rubro && d.CATEGORIA_PADRE === categoria
        );
        if (grupo.length === 0) return;

        // Sumar participación
        let sumPart = 0;
        grupo.forEach(item => sumPart += parseFloat(item.PARTICIPACION) || 0);

        // Si la participación suma exactamente 100% (o muy cercano), corregimos diferencias de redondeo
        if (Math.abs(sumPart - 100) < 0.01) {
            const compraProyectada = grupo[0].COMPRA_PROYECTADA;
            let sumDist = 0;
            let maxPart = -1;
            let itemMax = null;

            grupo.forEach(item => {
                // Calcular distribución base redondeada
                const dist = Math.round(compraProyectada * (item.PARTICIPACION / 100));
                item.COMPRA_DISTRIBUIDA = dist;
                item.DISTRIBUCION_FINAL = dist;
                sumDist += dist;

                if (item.PARTICIPACION > maxPart) {
                    maxPart = item.PARTICIPACION;
                    itemMax = item;
                }
            });

            // Ajustar diferencia de unidades en el canal con mayor participación
            if (sumDist !== compraProyectada && itemMax !== null) {
                const diff = compraProyectada - sumDist;
                itemMax.COMPRA_DISTRIBUIDA += diff;
                itemMax.DISTRIBUCION_FINAL = itemMax.COMPRA_DISTRIBUIDA;
            }

            // Actualizar DOM e índices mensuales para todos los elementos del grupo
            grupo.forEach(item => {
                const idx = DistribucionManager.datos.indexOf(item);
                const cellDist = document.getElementById(`distribucion-${idx}`);
                if (cellDist) {
                    cellDist.textContent = FormatoUtils.formatearNumero(item.COMPRA_DISTRIBUIDA);
                }
                
                // Recalcular meses correspondientes con sus redondeos mensuales internos
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
                    
                    // Actualizar celdas de mes en el DOM
                    Object.keys(item.DISTRIBUCION_MENSUAL).forEach(m => {
                        const cellMes = document.getElementById(`mes-${idx}-${m}`);
                        if (cellMes) {
                            const valCell = cellMes.querySelector('.fw-bold');
                            if (valCell) {
                                valCell.textContent = FormatoUtils.formatearNumero(item.DISTRIBUCION_MENSUAL[m].unidades);
                            }
                        }
                    });
                }
            });
        }
    }

    static actualizarParticipacion(index, value) {
        const val = parseFloat(value) || 0;
        const item = DistribucionManager.datos[index];
        
        // 1. Guardar el nuevo valor de participación y marcar si cambió
        item.PARTICIPACION = val;
        item.MODIFICADO = (Math.abs(val - item.PARTICIPACION_ORIGINAL) > 0.01);

        // 2. Intentar ajustar redondeo del grupo de forma balanceada si la participación suma 100%
        let sumPart = 0;
        const grupo = DistribucionManager.datos.filter(d => 
            d.RUBRO === item.RUBRO && d.CATEGORIA_PADRE === item.CATEGORIA_PADRE
        );
        grupo.forEach(g => sumPart += parseFloat(g.PARTICIPACION) || 0);

        if (Math.abs(sumPart - 100) < 0.01) {
            DistribucionManager.ajustarRedondeoGrupo(item.RUBRO, item.CATEGORIA_PADRE);
        } else {
            // Si no suma 100%, realizar el cálculo matemático directo para el elemento modificado
            item.COMPRA_DISTRIBUIDA = Math.round(item.COMPRA_PROYECTADA * (val / 100));
            item.DISTRIBUCION_FINAL = item.COMPRA_DISTRIBUIDA;
            
            const cellDist = document.getElementById(`distribucion-${index}`);
            if (cellDist) {
                cellDist.textContent = FormatoUtils.formatearNumero(item.COMPRA_DISTRIBUIDA);
            }
            
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
                
                Object.keys(item.DISTRIBUCION_MENSUAL).forEach(m => {
                    const cellMes = document.getElementById(`mes-${index}-${m}`);
                    if (cellMes) {
                        const valCell = cellMes.querySelector('.fw-bold');
                        if (valCell) {
                            valCell.textContent = FormatoUtils.formatearNumero(item.DISTRIBUCION_MENSUAL[m].unidades);
                        }
                    }
                });
            }
        }
        
        // 3. Actualizar subtotal, indicadores generales y validaciones
        DistribucionManager.actualizarSubtotalGrupo(item.RUBRO, item.CATEGORIA_PADRE);
        DistribucionManager.actualizarIndicadores();
        DistribucionManager.validarGrupos();
        DistribucionManager.validarFilaMensual(index);
    }

    /**
     * Permite modificar en tiempo real la Venta Proyectada (Compra Proyectada) para un rubro/categoría
     * Recalcula la distribución final por canal y por mes al instante.
     */
    static actualizarCompraProyectada(rubro, categoria, value) {
        const nuevaCompraProyectada = parseInt(value, 10) || 0;
        
        // 1. Buscar todos los ítems del grupo (rubro + categoría)
        const grupo = DistribucionManager.datos.filter(d => 
            d.RUBRO === rubro && d.CATEGORIA_PADRE === categoria
        );

        if (grupo.length === 0) return;

        // 2. Actualizar COMPRA_PROYECTADA en todos los elementos del grupo
        grupo.forEach(item => {
            item.COMPRA_PROYECTADA = nuevaCompraProyectada;
        });

        // 3. Recalcular distribución de los canales del grupo
        DistribucionManager.ajustarRedondeoGrupo(rubro, categoria);

        // 4. Actualizar subtotal y validaciones en la interfaz
        DistribucionManager.actualizarSubtotalGrupo(rubro, categoria);
        DistribucionManager.actualizarIndicadores();
        DistribucionManager.validarGrupos();
    }

    static actualizarPorcentajeMes(index, mes, value) {
        const val = parseFloat(value) || 0;
        const item = DistribucionManager.datos[index];
        
        if (item.DISTRIBUCION_MENSUAL && item.DISTRIBUCION_MENSUAL[mes]) {
            item.DISTRIBUCION_MENSUAL[mes].porcentaje = val;
            
            // Recalcular unidades para este mes
            item.DISTRIBUCION_MENSUAL[mes].unidades = Math.round(item.COMPRA_DISTRIBUIDA * (val / 100));
            
            // Actualizar la celda en el DOM
            const cellMes = document.getElementById(`mes-${index}-${mes}`);
            if (cellMes) {
                const valCell = cellMes.querySelector('.fw-bold');
                if (valCell) {
                    valCell.textContent = FormatoUtils.formatearNumero(item.DISTRIBUCION_MENSUAL[mes].unidades);
                }
            }
            
            // Validar la fila
            DistribucionManager.validarFilaMensual(index);
            
            // Actualizar subtotal
            DistribucionManager.actualizarSubtotalGrupo(item.RUBRO, item.CATEGORIA_PADRE);
        }
    }

    static validarFilaMensual(index) {
        const item = DistribucionManager.datos[index];
        if (!item.DISTRIBUCION_MENSUAL) return true;
        
        let sumPorc = 0;
        Object.keys(item.DISTRIBUCION_MENSUAL).forEach(m => {
            sumPorc += parseFloat(item.DISTRIBUCION_MENSUAL[m].porcentaje) || 0;
        });
        
        const is100 = Math.abs(sumPorc - 100) < 0.05;
        const row = document.getElementById(`row-distrib-${index}`);
        
        if (row) {
            const inputs = row.querySelectorAll('.input-mes-porc');
            inputs.forEach(input => {
                if (is100) {
                    input.style.border = 'none';
                    input.style.borderBottom = '1px dashed #ccc';
                    input.style.color = '#555';
                    input.style.backgroundColor = 'transparent';
                } else {
                    input.style.border = '1px solid #dc3545';
                    input.style.color = '#dc3545';
                    input.style.backgroundColor = '#f8d7da';
                }
            });
        }

        const totalCellVal = document.getElementById(`mes-total-val-${index}`);
        if (totalCellVal) {
            totalCellVal.textContent = `${Math.round(sumPorc)}%`;
            if (is100) {
                totalCellVal.className = 'badge bg-success fs-8';
            } else {
                totalCellVal.className = 'badge bg-danger fs-8';
            }
        }
        
        return is100;
    }

    static actualizarSubtotalGrupo(rubro, categoria) {
        const grupo = DistribucionManager.datos.filter(d => 
            d.RUBRO === rubro && d.CATEGORIA_PADRE === categoria
        );
        
        if (grupo.length === 0) return;
        
        const first = grupo[0];
        let sumVentaCanal = 0;
        let sumVentaDist = 0;
        let sumParticipacion = 0;
        const sumMeses = {};
        DistribucionManager.meses.forEach(m => sumMeses[m] = 0);

        grupo.forEach(item => {
            sumVentaCanal += item.VENTA_CANAL || 0;
            sumVentaDist  += item.COMPRA_DISTRIBUIDA || 0;
            sumParticipacion += parseFloat(item.PARTICIPACION) || 0;
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
            
            const is100 = Math.abs(sumParticipacion - 100) < 0.01;
            const partStyle = is100 ? 'color: #198754; font-weight: bold;' : 'color: #dc3545; background-color: #f8d7da; font-weight: bold; border: 1px solid #f5c2c7;';

            subtotalRow.innerHTML = `
                <td colspan="2" class="fw-bold text-primary" style="font-size:0.85rem; padding: 4px 8px;">
                    <i class="fas fa-sigma me-1 text-primary" style="font-size:0.75rem;"></i>
                    SUBTOTAL &nbsp;<span class="text-muted fw-normal">${first.RUBRO} · ${first.CATEGORIA_PADRE}</span>
                </td>
                <td class="text-end fw-bold font-monospace" style="background:#e3f2fd; font-size:0.85rem;">${FormatoUtils.formatearNumero(first.COMPRA_PROYECTADA)}</td>
                <td class="text-end fw-bold font-monospace" style="background:#e3f2fd; font-size:0.85rem;">${FormatoUtils.formatearNumero(first.VENTA_HISTORICA_TOTAL)}</td>
                <td style="background:#e3f2fd;"></td>
                <td class="text-end fw-bold font-monospace" style="background:#e3f2fd; font-size:0.85rem;">${FormatoUtils.formatearNumero(sumVentaCanal)}</td>
                <td class="text-end font-monospace" style="${partStyle} font-size:0.85rem;" id="subtotal-part-${safeRubro}-${safeCat}">${Math.round(sumParticipacion)}%</td>
                <td class="text-end fw-bold font-monospace" style="background:#c8e6c9; font-size:0.85rem; color:#1b5e20;" id="subtotal-val-${safeRubro}-${safeCat}">${FormatoUtils.formatearNumero(sumVentaDist)}</td>
                ${mesesSubtotalHtml}
                <td style="background:#e8f5e9;"></td>
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
            if (compraCell) {
                const inputProy = compraCell.querySelector('input');
                if (inputProy) {
                    grupos[key].compraProyectada = parseInt(inputProy.value, 10) || 0;
                } else if (compraCell.textContent) {
                    grupos[key].compraProyectada = parseInt(compraCell.textContent.replace(/\./g, ''), 10) || 0;
                }
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
        // Usar los datos filtrados actualmente visibles (o todos si no hay filtro)
        const listaDatos = (DistribucionManager.datosFiltrados && DistribucionManager.datosFiltrados.length > 0)
            ? DistribucionManager.datosFiltrados
            : DistribucionManager.datos;

        const rubrosUnicos = new Set();
        const categoriasUnicas = new Set();
        let totalCompra = 0;
        let totalVentaHistorica = 0;

        // Sumas por Canal en Paso 1 (Venta Histórica por Canal)
        const ventaPorCanal = {
            'LOCALES PROPIOS': 0,
            'FRANQUICIAS': 0,
            'MAYORISTAS': 0,
            'ECOMMERCE': 0
        };

        // Sumar compra proyectada y venta histórica solo una vez por grupo rubro|categoria
        const gruposVistos = new Set();

        listaDatos.forEach(item => {
            rubrosUnicos.add(item.RUBRO);
            categoriasUnicas.add(item.RUBRO + '|' + item.CATEGORIA_PADRE);

            // Sumar venta distribuida por canal
            const canalNorm = (item.CANAL || '').toUpperCase().trim();
            const ventaDist = Number(item.COMPRA_DISTRIBUIDA ?? item.DISTRIBUCION_FINAL ?? 0) || 0;
            if (canalNorm.includes('LOCAL')) {
                ventaPorCanal['LOCALES PROPIOS'] += ventaDist;
            } else if (canalNorm.includes('FRANQ')) {
                ventaPorCanal['FRANQUICIAS'] += ventaDist;
            } else if (canalNorm.includes('MAYOR')) {
                ventaPorCanal['MAYORISTAS'] += ventaDist;
            } else if (canalNorm.includes('ECOM') || canalNorm.includes('WEB')) {
                ventaPorCanal['ECOMMERCE'] += ventaDist;
            }

            const grupoClave = item.RUBRO + '|' + item.CATEGORIA_PADRE;
            if (!gruposVistos.has(grupoClave)) {
                gruposVistos.add(grupoClave);
                totalCompra += (item.COMPRA_PROYECTADA || 0);
                totalVentaHistorica += (item.VENTA_HISTORICA_TOTAL || 0);
            }
        });

        // Escribir en DOM
        const format = FormatoUtils.formatearNumero;
        const cardCompra = document.getElementById('card-compra-total');
        if (cardCompra) cardCompra.textContent = format(totalCompra);

        const cardTotal = document.getElementById('card-total-distribuido');
        if (cardTotal) {
            cardTotal.textContent = format(totalVentaHistorica);
            cardTotal.className = 'badge bg-primary fs-5 fw-bold';
        }

        const cardRubros = document.getElementById('card-cant-rubros');
        if (cardRubros) cardRubros.textContent = rubrosUnicos.size;

        const cardCats = document.getElementById('card-cant-categorias');
        if (cardCats) cardCats.textContent = categoriasUnicas.size;

        const cardUltAct = document.getElementById('card-ult-act');
        if (cardUltAct) cardUltAct.textContent = new Date().toLocaleTimeString();

        // Actualizar indicadores por canal
        const containerCanales = document.getElementById('indicadores-canales-paso1');
        if (containerCanales) {
            containerCanales.innerHTML = `
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 font-monospace" style="font-size:0.75rem;" title="Locales Propios">LP: ${format(ventaPorCanal['LOCALES PROPIOS'])}</span>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 font-monospace" style="font-size:0.75rem;" title="Franquicias">FR: ${format(ventaPorCanal['FRANQUICIAS'])}</span>
                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1 font-monospace" style="font-size:0.75rem;" title="Mayoristas">MA: ${format(ventaPorCanal['MAYORISTAS'])}</span>
                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1 font-monospace" style="font-size:0.75rem;" title="Ecommerce">EC: ${format(ventaPorCanal['ECOMMERCE'])}</span>
            `;
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

        const fuenteDatos = DistribucionManager.datos.length > 0 
            ? DistribucionManager.datos 
            : DistribucionManager.datosCostos;

        fuenteDatos.forEach(item => {
            if (item.RUBRO) rubros.add(item.RUBRO);
            if (item.CATEGORIA_PADRE) categorias.add(item.CATEGORIA_PADRE);
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
        // Si está activo el paso 2 (costos), filtrar esa tabla
        if (DistribucionManager.pasoActivo === 2) {
            DistribucionManager.filtrarDatosCostos();
            return;
        }

        const query = document.getElementById('search-distribucion').value.toLowerCase().trim();
        const rubro = document.getElementById('filtro-rubro-distribucion').value;
        const categoria = document.getElementById('filtro-categoria-distribucion').value;
        const canal = document.getElementById('filtro-canal-distribucion').value;

        DistribucionManager.datosFiltrados = DistribucionManager.datos.filter(item => {
            const rubroStr = (item.RUBRO || '').toLowerCase();
            const catStr = (item.CATEGORIA_PADRE || '').toLowerCase();
            const matchesQuery = !query || rubroStr.includes(query) || catStr.includes(query);
            const matchesRubro = !rubro || (item.RUBRO || '') === rubro;
            const matchesCat = !categoria || (item.CATEGORIA_PADRE || '') === categoria;
            const matchesCanal = !canal || (item.CANAL || '') === canal;

            return matchesQuery && matchesRubro && matchesCat && matchesCanal;
        });

        DistribucionManager.renderizarTabla();
        DistribucionManager.actualizarIndicadores();
        document.getElementById('count-distribucion').textContent = `${DistribucionManager.datosFiltrados.length} registros`;
    }

    /**
     * Filtrar la tabla de costos (Paso 2)
     */
    static filtrarDatosCostos() {
        const query = document.getElementById('search-distribucion').value.toLowerCase().trim();
        const rubro = document.getElementById('filtro-rubro-distribucion').value;
        const categoria = document.getElementById('filtro-categoria-distribucion').value;

        const filtrados = DistribucionManager.datosCostos.filter(item => {
            const rubroStr = (item.RUBRO || '').toLowerCase();
            const catStr = (item.CATEGORIA_PADRE || '').toLowerCase();
            const matchesQuery = !query || rubroStr.includes(query) || catStr.includes(query);
            const matchesRubro = !rubro || (item.RUBRO || '') === rubro;
            const matchesCat = !categoria || (item.CATEGORIA_PADRE || '') === categoria;
            return matchesQuery && matchesRubro && matchesCat;
        });

        const countEl = document.getElementById('count-distribucion');
        if (countEl) countEl.textContent = `${filtrados.length} registros`;

        DistribucionManager.renderizarTablaCostos(filtrados);
    }

    static limpiarFiltros() {
        document.getElementById('search-distribucion').value = '';
        document.getElementById('filtro-rubro-distribucion').value = '';
        document.getElementById('filtro-categoria-distribucion').value = '';
        document.getElementById('filtro-canal-distribucion').value = '';
        
        if (DistribucionManager.pasoActivo === 2) {
            const countEl = document.getElementById('count-distribucion');
            if (countEl) countEl.textContent = `${DistribucionManager.datosCostos.length} registros`;
            DistribucionManager.renderizarTablaCostos(DistribucionManager.datosCostos);
        } else {
            DistribucionManager.datosFiltrados = [...DistribucionManager.datos];
            DistribucionManager.renderizarTabla();
            DistribucionManager.actualizarIndicadores();
            document.getElementById('count-distribucion').textContent = `${DistribucionManager.datos.length} registros`;
        }
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
        if (!DistribucionManager.datos || DistribucionManager.datos.length === 0) {
            UIUtils.mostrarLoading(true);
            await DistribucionManager.cargarDatos();
            UIUtils.mostrarLoading(false);
        }

        if (!DistribucionManager.datos || DistribucionManager.datos.length === 0) {
            UIUtils.mostrarAlerta('No hay datos de distribución cargados para guardar.', 'warning');
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
                nombre_distribucion: nombreLimpio,
                distribucion_mensual_json: JSON.stringify(d.DISTRIBUCION_MENSUAL)
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

    /**
     * Eliminar la versión actualmente seleccionada en el combo
     */
    static async eliminarVersionSeleccionada() {
        const selectVersion = document.getElementById('filtro-version-distribucion');
        if (!selectVersion) return;

        const versionSeleccionada = selectVersion.value;
        if (!versionSeleccionada || versionSeleccionada === 'Por defecto') {
            UIUtils.mostrarAlerta('La versión "Por defecto" no se puede eliminar. Seleccione una versión guardada.', 'warning');
            return;
        }

        const confirmacion = await UIUtils.confirmarAccion(
            '¿Eliminar versión?',
            `¿Está seguro de que desea eliminar la versión guardada <strong>"${versionSeleccionada}"</strong>?<br><small class="text-danger">Esta acción eliminará de forma permanente sus datos de distribución.</small>`,
            'danger'
        );

        if (!confirmacion) return;

        try {
            UIUtils.mostrarLoading(true);

            const temporadaSelect = document.getElementById('filtro-temporada-distribucion');
            const temporada = temporadaSelect ? temporadaSelect.value : 'VERANO';

            const response = await APIClient.eliminarVersion(versionSeleccionada, temporada);

            if (response.success) {
                UIUtils.mostrarAlerta(`Versión "${versionSeleccionada}" eliminada correctamente.`, 'success');
                // Re-inicializar lista de versiones y volver a 'Por defecto'
                await DistribucionManager.inicializarVersiones(true);
                selectVersion.value = 'Por defecto';
                DistribucionManager.ejecutarCargar();
            } else {
                throw new Error(response.message || 'No se pudo eliminar la versión');
            }
        } catch (error) {
            console.error('Error al eliminar versión:', error);
            UIUtils.mostrarAlerta('Error al eliminar la versión: ' + error.message, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
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

        // 3. Mostrar/ocultar selector de canal y switch de moneda según el paso
        const colCanal = document.getElementById('col-filtro-canal');
        if (colCanal) {
            colCanal.style.display = (paso === 1) ? 'block' : 'none';
        }

        const colMoneda = document.getElementById('col-switch-moneda');
        if (colMoneda) {
            colMoneda.style.display = (paso === 2 || paso === 3) ? 'block' : 'none';
        }

        const btnParametros = document.getElementById('btn-costos-parametros');
        if (btnParametros) {
            btnParametros.style.display = (paso === 2) ? 'inline-block' : 'none';
        }

        // Mostrar botón Guardar en el paso 1 y 2 (el paso 3 es solo lectura/análisis de desvíos)
        const btnGuardar = document.querySelector('button[onclick="DistribucionManager.ejecutarGuardar()"]');
        if (btnGuardar) {
            btnGuardar.style.display = (paso === 1 || paso === 2) ? 'inline-block' : 'none';
        }

        // Paso 3 es de lectura y análisis de desvíos, requiere hacer clic en Cargar / Recalcular
    }

    static ejecutarCargar() {
        if (DistribucionManager.pasoActivo === 2) {
            DistribucionManager.cargarCostos();
        } else if (DistribucionManager.pasoActivo === 3) {
            DistribucionManager.cargarPaso3();
        } else {
            DistribucionManager.cargarDatos();
        }
    }

    static ejecutarGuardar() {
        if (DistribucionManager.pasoActivo === 2 || DistribucionManager.pasoActivo === 1) {
            DistribucionManager.guardarDistribucion();
        }
    }

    static ejecutarExcel() {
        if (DistribucionManager.pasoActivo === 2) {
            DistribucionManager.exportarExcelCostos();
        } else if (DistribucionManager.pasoActivo === 3) {
            UIUtils.mostrarAlerta('La exportación de desvíos debe hacerse desde el Excel de Venta Comercial completo.', 'info');
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

                // Asegurar carga de tipo de cambio si estamos en USD
                if (DistribucionManager.modoMoneda === 'USD' && Object.keys(DistribucionManager.tipoCambio).length === 0) {
                    const tcResponse = await APIClient.obtenerTipoCambio();
                    if (tcResponse.success) {
                        DistribucionManager.tipoCambio = tcResponse.tasas || {};
                    }
                }

                // Cargar filtros con los rubros y categorías del costo
                DistribucionManager.cargarFiltros();
                
                DistribucionManager.renderizarTablaCostos();
                DistribucionManager.actualizarVisualizacionMoneda();
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

    static renderizarTablaCostos(datosParam = null) {
        const headRowCostos = document.getElementById('thead-costos-row');
        const tbodyCostos = document.getElementById('tbody-costos');
        const headRowMarkup = document.getElementById('thead-markup-row');
        const tbodyMarkup = document.getElementById('tbody-markup');
        
        if (!headRowCostos || !tbodyCostos) return;

        // Usar datos filtrados si se proveen, si no todos los datos
        const datos = datosParam !== null ? datosParam : DistribucionManager.datosCostos;

        // Configuración de conversión (Fórmula: USD es base, ARS es USD * Tasa)
        const esARS = DistribucionManager.modoMoneda === 'ARS';
        const tasaPromedio = esARS ? DistribucionManager.tasaPromedioPeriodo() : 1;
        const sufMoneda = esARS ? ' ($)' : ' (U$D)';

        // Helper para convertir y formatear dinero
        const fmtDinero = (v, tasa = null) => {
            const t = tasa !== null ? tasa : tasaPromedio;
            const valorFinal = esARS ? (v * t) : v;
            return FormatoUtils.formatearNumero(Math.round(valorFinal));
        };

        // 1. Reconstruir cabeceras
        let headHtml = `
            <th>Rubro</th>
            <th>Categoría</th>
            <th class="bg-light-blue font-monospace">Canal</th>
            <th class="text-end bg-secondary text-white" style="width: 150px;">Venta Proyectada (U.)</th>
            <th class="text-end bg-success text-white" style="width: 140px;">Vcosto${sufMoneda}</th>
        `;

        DistribucionManager.mesesCostos.forEach(m => {
            const tasaMes = esARS ? DistribucionManager.tasaParaMes(m) : 1;
            headHtml += `
                <th class="text-end bg-dark text-white" style="min-width: 90px;">
                    ${m}${esARS ? '<br><small style="font-size:0.65rem;opacity:0.8;">TC $' + Math.round(tasaMes) + '</small>' : ''}
                </th>`;
        });
        
        let headHtmlCostos = headHtml + `<th class="text-end bg-primary text-white" style="width: 150px;">Costo Total${sufMoneda}</th>`;
        let headHtmlMarkup = headHtml + `<th class="text-end bg-warning text-dark" style="width: 150px;">Venta Total${sufMoneda}</th>`;
        
        if (headRowCostos) headRowCostos.innerHTML = headHtmlCostos;
        if (headRowMarkup) headRowMarkup.innerHTML = headHtmlMarkup;

        // 2. Renderizar filas de datos
        if (datos.length === 0) {
            const emptyHtml = `
                <tr>
                    <td colspan="${6 + DistribucionManager.mesesCostos.length}" class="text-center text-muted py-4">
                        No hay datos cargados para esta versión.
                    </td>
                </tr>`;
            if (tbodyCostos) tbodyCostos.innerHTML = emptyHtml;
            if (tbodyMarkup) tbodyMarkup.innerHTML = emptyHtml;
            return;
        }

        // Helper para generar fila de subtotal de costos por rubro/categoría
        const generarSubtotalCostos = (grupo) => {
            const first = grupo[0];
            const safeRubro = first.RUBRO.replace(/[^a-zA-Z0-9]/g, '');
            const safeCat = first.CATEGORIA_PADRE.replace(/[^a-zA-Z0-9]/g, '');

            let sumCompraDist = 0;
            let sumCostoTotalUSD = 0;
            const sumMesesUSD = {};
            DistribucionManager.mesesCostosClaves.forEach(m => sumMesesUSD[m] = 0);

            grupo.forEach(item => {
                const vcosto = item.COSTO_PROM * (1 + item.INC_FOB / 100);
                sumCompraDist += item.COMPRA_DISTRIBUIDA || 0;

                DistribucionManager.mesesCostosClaves.forEach(m => {
                    const unidades = item.MESES_UNIDADES[m] || 0;
                    sumMesesUSD[m] += (vcosto * unidades);
                    sumCostoTotalUSD += (vcosto * unidades);
                });
            });

            // Formatear meses de subtotal
            let mesesSubtotalHtml = '';
            DistribucionManager.mesesCostosClaves.forEach((m, mIdx) => {
                const labelMes = DistribucionManager.mesesCostos[mIdx];
                const tasaMes = esARS ? DistribucionManager.tasaParaMes(labelMes) : 1;
                mesesSubtotalHtml += `<td class="text-end fw-bold font-monospace" style="background:#e8f5e9; font-size:0.85rem;">${fmtDinero(sumMesesUSD[m], tasaMes)}</td>`;
            });

            const vcostoGrupo = first.COSTO_PROM * (1 + first.INC_FOB / 100);
            const vcostoGrupoFormatted = esARS ? (vcostoGrupo * tasaPromedio).toFixed(0) : vcostoGrupo.toFixed(2);
            const vcostoColor = vcostoGrupo > 0 ? 'text-success fw-bold' : 'text-muted';

            return `
                <tr id="subtotal-costos-${safeRubro}-${safeCat}" style="background: linear-gradient(90deg,#e3f2fd 0%,#f1f8e9 100%); border-top: 2px solid #90caf9; border-bottom: 2px solid #90caf9; vertical-align: middle;">
                    <td colspan="3" class="fw-bold text-primary" style="font-size:0.85rem; padding: 6px 8px;">
                        <i class="fas fa-sigma me-1 text-primary" style="font-size:0.75rem;"></i>
                        SUBTOTAL &nbsp;<span class="text-muted fw-normal">${first.RUBRO} · ${first.CATEGORIA_PADRE}</span>
                    </td>
                    <td class="text-end fw-bold font-monospace" style="background:#e3f2fd; font-size:0.85rem;">${FormatoUtils.formatearNumero(sumCompraDist)}</td>
                    <td class="text-end font-monospace ${vcostoColor}" style="background:#e3f2fd; font-size:0.85rem;">
                        ${vcostoGrupo > 0 ? (esARS ? '$' : 'U$D') + ' ' + vcostoGrupoFormatted : ''}
                    </td>
                    ${mesesSubtotalHtml}
                    <td class="text-end fw-bold font-monospace bg-primary bg-opacity-10" style="font-size:0.85rem; color:#0d6efd;">${fmtDinero(sumCostoTotalUSD)}</td>
                </tr>
            `;
        };

        // Helper para obtener el markup correspondiente al canal de la fila
        const obtenerMarkupFila = (row) => DistribucionManager.obtenerMarkupFila(row);

        // Helper para generar fila de subtotal de markup por rubro/categoría
        const generarSubtotalMarkup = (grupo) => {
            const first = grupo[0];
            const safeRubro = first.RUBRO.replace(/[^a-zA-Z0-9]/g, '');
            const safeCat = first.CATEGORIA_PADRE.replace(/[^a-zA-Z0-9]/g, '');

            let sumCompraDist = 0;
            let sumMarkupTotalUSD = 0;
            const sumMesesUSD = {};
            DistribucionManager.mesesCostosClaves.forEach(m => sumMesesUSD[m] = 0);

            grupo.forEach(item => {
                const vcosto = item.COSTO_PROM * (1 + item.INC_FOB / 100);
                const markup = DistribucionManager.obtenerMarkupFila(item);
                sumCompraDist += item.COMPRA_DISTRIBUIDA || 0;

                DistribucionManager.mesesCostosClaves.forEach(m => {
                    const unidades = item.MESES_UNIDADES[m] || 0;
                    // Formula: unidades * vcosto * markup
                    const valorMarkupUSD = unidades * vcosto * markup;
                    sumMesesUSD[m] += valorMarkupUSD;
                    sumMarkupTotalUSD += valorMarkupUSD;
                });
            });

            // Formatear meses de subtotal
            let mesesSubtotalHtml = '';
            DistribucionManager.mesesCostosClaves.forEach((m, mIdx) => {
                const labelMes = DistribucionManager.mesesCostos[mIdx];
                const tasaMes = esARS ? DistribucionManager.tasaParaMes(labelMes) : 1;
                mesesSubtotalHtml += `<td class="text-end fw-bold font-monospace" style="background:#fdf6e2; font-size:0.85rem;">${fmtDinero(sumMesesUSD[m], tasaMes)}</td>`;
            });

            const vcostoGrupo = first.COSTO_PROM * (1 + first.INC_FOB / 100);
            const vcostoGrupoFormatted = esARS ? (vcostoGrupo * tasaPromedio).toFixed(0) : vcostoGrupo.toFixed(2);
            const vcostoColor = vcostoGrupo > 0 ? 'text-success fw-bold' : 'text-muted';

            return `
                <tr id="subtotal-markup-${safeRubro}-${safeCat}" style="background: linear-gradient(90deg,#fffde7 0%,#f1f8e9 100%); border-top: 2px solid #ffcc80; border-bottom: 2px solid #ffcc80; vertical-align: middle;">
                    <td colspan="3" class="fw-bold text-warning-emphasis" style="font-size:0.85rem; padding: 6px 8px;">
                        <i class="fas fa-sigma me-1 text-warning" style="font-size:0.75rem;"></i>
                        SUBTOTAL &nbsp;<span class="text-muted fw-normal">${first.RUBRO} · ${first.CATEGORIA_PADRE}</span>
                    </td>
                    <td class="text-end fw-bold font-monospace" style="background:#fffde7; font-size:0.85rem;">${FormatoUtils.formatearNumero(sumCompraDist)}</td>
                    <td class="text-end font-monospace ${vcostoColor}" style="background:#fffde7; font-size:0.85rem;">
                        ${vcostoGrupo > 0 ? (esARS ? '$' : 'U$D') + ' ' + vcostoGrupoFormatted : ''}
                    </td>
                    ${mesesSubtotalHtml}
                    <td class="text-end fw-bold font-monospace bg-warning bg-opacity-10" style="font-size:0.85rem; color:#ff9800;">${fmtDinero(sumMarkupTotalUSD)}</td>
                </tr>
            `;
        };

        let htmlCostos = '';
        let htmlMarkup = '';
        let rubroAnterior = '';
        let catAnterior = '';
        let grupoActual = [];

        datos.forEach((row, index) => {
            const esNuevoGrupo = (row.RUBRO !== rubroAnterior || row.CATEGORIA_PADRE !== catAnterior);
            if (esNuevoGrupo && grupoActual.length > 0) {
                htmlCostos += generarSubtotalCostos(grupoActual);
                htmlMarkup += generarSubtotalMarkup(grupoActual);
                grupoActual = [];
            }

            // Calcular Vcosto (Base en USD)
            const vcosto = row.COSTO_PROM * (1 + row.INC_FOB / 100);
            row.VCOSTO = vcosto;

            const markup = DistribucionManager.obtenerMarkupFila(row);

            let mesesHtmlCostos = '';
            let mesesHtmlMarkup = '';
            let costoTotalUSD = 0;
            let markupTotalUSD = 0;

            DistribucionManager.mesesCostosClaves.forEach((m, mIdx) => {
                const labelMes = DistribucionManager.mesesCostos[mIdx];
                const unidades = row.MESES_UNIDADES[m] || 0;
                
                // Costo mensual base en USD
                const costoMesUSD = vcosto * unidades;
                costoTotalUSD += costoMesUSD;

                // Markup mensual base en USD
                const markupMesUSD = vcosto * unidades * markup;
                markupTotalUSD += markupMesUSD;
                
                const tasaMes = esARS ? DistribucionManager.tasaParaMes(labelMes) : 1;
                mesesHtmlCostos += `<td class="text-end font-monospace" style="font-size:0.85rem;">${fmtDinero(costoMesUSD, tasaMes)}</td>`;
                mesesHtmlMarkup += `<td class="text-end font-monospace" style="font-size:0.85rem;">${fmtDinero(markupMesUSD, tasaMes)}</td>`;
            });

            const vcostoValorFormatted = esARS ? (vcosto * tasaPromedio).toFixed(0) : vcosto.toFixed(2);
            const vcostoColor = vcosto > 0 ? 'text-success fw-bold' : 'text-muted';

            // Para limpiar celdas repetidas en visualización
            const rubroText = esNuevoGrupo ? `<strong>${row.RUBRO}</strong>` : `<span class="text-muted">${row.RUBRO}</span>`;
            const catText = esNuevoGrupo ? row.CATEGORIA_PADRE : `<span class="text-muted">${row.CATEGORIA_PADRE}</span>`;

            // Construir fila de Costos
            htmlCostos += `
                <tr style="vertical-align: middle;">
                    <td>${rubroText}</td>
                    <td>${catText}</td>
                    <td class="bg-light-blue font-monospace">${row.CANAL}</td>
                    <td class="text-end font-monospace fw-bold" style="background:#f8f9fa;">${FormatoUtils.formatearNumero(row.COMPRA_DISTRIBUIDA)}</td>
                    <td class="text-end font-monospace ${vcostoColor}">
                        ${vcosto > 0 ? (esARS ? '$' : 'U$D') + ' ' + vcostoValorFormatted : '<span class="badge bg-secondary fs-8">Sin param.</span>'}
                    </td>
                    ${mesesHtmlCostos}
                    <td class="text-end font-monospace fw-bold bg-primary bg-opacity-10">${fmtDinero(costoTotalUSD)}</td>
                </tr>
            `;

            // Construir fila de Markup
            htmlMarkup += `
                <tr style="vertical-align: middle;">
                    <td>${rubroText}</td>
                    <td>${catText}</td>
                    <td class="bg-light-blue font-monospace">${row.CANAL} <span class="badge bg-secondary font-monospace fs-9 ms-1" style="font-weight:normal;">x${markup.toFixed(1)}</span></td>
                    <td class="text-end font-monospace fw-bold" style="background:#f8f9fa;">${FormatoUtils.formatearNumero(row.COMPRA_DISTRIBUIDA)}</td>
                    <td class="text-end font-monospace ${vcostoColor}">
                        ${vcosto > 0 ? (esARS ? '$' : 'U$D') + ' ' + vcostoValorFormatted : '<span class="badge bg-secondary fs-8">Sin param.</span>'}
                    </td>
                    ${mesesHtmlMarkup}
                    <td class="text-end font-monospace fw-bold bg-warning bg-opacity-10">${fmtDinero(markupTotalUSD)}</td>
                </tr>
            `;

            grupoActual.push(row);
            rubroAnterior = row.RUBRO;
            catAnterior = row.CATEGORIA_PADRE;
        });

        if (grupoActual.length > 0) {
            htmlCostos += generarSubtotalCostos(grupoActual);
            htmlMarkup += generarSubtotalMarkup(grupoActual);
        }

        // --- CÁLCULO DE TOTALES GENERALES Y POR CANAL ---
        let totalGeneralVentaProyectada = 0;
        let totalGeneralCostoUSD = 0;
        let totalGeneralVentaUSD = 0;
        const totalMensualCostoUSD = {};
        const totalMensualVentaUSD = {};
        
        // Sumas por Canal
        const costoPorCanal = { 'LOCALES PROPIOS': 0, 'FRANQUICIAS': 0, 'MAYORISTAS': 0, 'ECOMMERCE': 0 };
        const ventaPorCanal = { 'LOCALES PROPIOS': 0, 'FRANQUICIAS': 0, 'MAYORISTAS': 0, 'ECOMMERCE': 0 };

        DistribucionManager.mesesCostosClaves.forEach(m => {
            totalMensualCostoUSD[m] = 0;
            totalMensualVentaUSD[m] = 0;
        });

        datos.forEach(row => {
            const vcosto = row.COSTO_PROM * (1 + row.INC_FOB / 100);
            const markup = DistribucionManager.obtenerMarkupFila(row);
            const canalNormalizado = (row.CANAL || '').toUpperCase().trim();
            
            totalGeneralVentaProyectada += row.COMPRA_DISTRIBUIDA || 0;

            DistribucionManager.mesesCostosClaves.forEach(m => {
                const unidades = row.MESES_UNIDADES[m] || 0;
                
                const costoMes = vcosto * unidades;
                const ventaMes = vcosto * unidades * markup;

                totalMensualCostoUSD[m] += costoMes;
                totalMensualVentaUSD[m] += ventaMes;

                totalGeneralCostoUSD += costoMes;
                totalGeneralVentaUSD += ventaMes;

                // Sumar por canal
                if (canalNormalizado.includes('LOCAL')) {
                    costoPorCanal['LOCALES PROPIOS'] += costoMes;
                    ventaPorCanal['LOCALES PROPIOS'] += ventaMes;
                } else if (canalNormalizado.includes('FRANQ')) {
                    costoPorCanal['FRANQUICIAS'] += costoMes;
                    ventaPorCanal['FRANQUICIAS'] += ventaMes;
                } else if (canalNormalizado.includes('MAYOR')) {
                    costoPorCanal['MAYORISTAS'] += costoMes;
                    ventaPorCanal['MAYORISTAS'] += ventaMes;
                } else if (canalNormalizado.includes('ECOM') || canalNormalizado.includes('WEB')) {
                    costoPorCanal['ECOMMERCE'] += costoMes;
                    ventaPorCanal['ECOMMERCE'] += ventaMes;
                }
            });
        });

        // Generar fila HTML de Total General de Costos
        let mesesTotalCostosHtml = '';
        DistribucionManager.mesesCostosClaves.forEach((m, mIdx) => {
            const labelMes = DistribucionManager.mesesCostos[mIdx];
            const tasaMes = esARS ? DistribucionManager.tasaParaMes(labelMes) : 1;
            mesesTotalCostosHtml += `<td class="text-end fw-bold font-monospace bg-dark text-white" style="font-size:0.85rem;">${fmtDinero(totalMensualCostoUSD[m], tasaMes)}</td>`;
        });

        const totalCostosRowHtml = `
            <tr style="border-top: 3px double #000; border-bottom: 3px double #000; vertical-align: middle;" class="table-dark">
                <td colspan="3" class="fw-bold text-uppercase" style="font-size:0.85rem; padding: 8px;">
                    <i class="fas fa-calculator me-1"></i>TOTAL GENERAL
                </td>
                <td class="text-end fw-bold font-monospace bg-secondary text-white" style="font-size:0.85rem;">${FormatoUtils.formatearNumero(totalGeneralVentaProyectada)}</td>
                <td class="bg-dark"></td>
                ${mesesTotalCostosHtml}
                <td class="text-end fw-bold font-monospace bg-primary text-white" style="font-size:0.85rem;">${fmtDinero(totalGeneralCostoUSD)}</td>
            </tr>
        `;
        htmlCostos += totalCostosRowHtml;

        // Generar fila HTML de Total General de Venta (Mark-up)
        let mesesTotalVentaHtml = '';
        DistribucionManager.mesesCostosClaves.forEach((m, mIdx) => {
            const labelMes = DistribucionManager.mesesCostos[mIdx];
            const tasaMes = esARS ? DistribucionManager.tasaParaMes(labelMes) : 1;
            mesesTotalVentaHtml += `<td class="text-end fw-bold font-monospace bg-dark text-white" style="font-size:0.85rem;">${fmtDinero(totalMensualVentaUSD[m], tasaMes)}</td>`;
        });

        const totalVentaRowHtml = `
            <tr style="border-top: 3px double #000; border-bottom: 3px double #000; vertical-align: middle;" class="table-dark">
                <td colspan="3" class="fw-bold text-uppercase" style="font-size:0.85rem; padding: 8px;">
                    <i class="fas fa-calculator me-1"></i>TOTAL GENERAL
                </td>
                <td class="text-end fw-bold font-monospace bg-secondary text-white" style="font-size:0.85rem;">${FormatoUtils.formatearNumero(totalGeneralVentaProyectada)}</td>
                <td class="bg-dark"></td>
                ${mesesTotalVentaHtml}
                <td class="text-end fw-bold font-monospace bg-warning text-dark" style="font-size:0.85rem;">${fmtDinero(totalGeneralVentaUSD)}</td>
            </tr>
        `;
        htmlMarkup += totalVentaRowHtml;

        if (tbodyCostos) tbodyCostos.innerHTML = htmlCostos;
        if (tbodyMarkup) tbodyMarkup.innerHTML = htmlMarkup;

        // --- RENDERIZAR TABLA DE ANÁLISIS 2B (LOCALES PROPIOS POR SUCURSAL) ---
        DistribucionManager.renderizarTablaAnalisis2B();

        // --- RENDERIZAR PEQUEÑOS INDICADORES DE TOTAL POR CANAL EN CABECERAS ---
        const badgeClassCanal = {
            'LOCALES PROPIOS': 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25',
            'FRANQUICIAS': 'bg-success bg-opacity-10 text-success border border-success border-opacity-25',
            'MAYORISTAS': 'bg-info bg-opacity-10 text-info border border-info border-opacity-25',
            'ECOMMERCE': 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25'
        };

        const renderHeaderIndicators = (containerId, totalsMap, totalGeneralVal) => {
            const container = document.getElementById(containerId);
            if (!container) return;

            let htmlBadges = '';
            
            // Primero, agregar el Total Empresa
            if (totalGeneralVal > 0) {
                const formattedG = fmtDinero(totalGeneralVal);
                const labelSuf = esARS ? '$' : 'U$D';
                htmlBadges += `
                    <span class="badge bg-dark border border-secondary px-2 py-1 font-monospace me-1" style="font-size: 0.72rem; font-weight: bold; background-color: #212529 !important;" title="Total Empresa">
                        Empresa: ${labelSuf} ${formattedG}
                    </span>
                `;
            }

            Object.keys(totalsMap).forEach(canal => {
                const totalVal = totalsMap[canal];
                if (totalVal > 0) {
                    const esUSD = DistribucionManager.modoMoneda === 'USD';
                    const formatted = fmtDinero(totalVal);
                    const labelSuf = esARS ? '$' : 'U$D';
                    
                    let shortLabel = canal;
                    if (canal === 'LOCALES PROPIOS') shortLabel = 'LP';
                    if (canal === 'FRANQUICIAS') shortLabel = 'FR';
                    if (canal === 'MAYORISTAS') shortLabel = 'MA';
                    if (canal === 'ECOMMERCE') shortLabel = 'EC';

                    htmlBadges += `
                        <span class="badge ${badgeClassCanal[canal] || 'bg-secondary'} px-2 py-1 font-monospace" style="font-size: 0.72rem; font-weight: 500;" title="${canal}">
                            ${shortLabel}: ${labelSuf} ${formatted}
                        </span>
                    `;
                }
            });
            container.innerHTML = htmlBadges;
        };

        renderHeaderIndicators('indicadores-costo-totales-header', costoPorCanal, totalGeneralCostoUSD);
        renderHeaderIndicators('indicadores-venta-totales-header', ventaPorCanal, totalGeneralVentaUSD);
    }

    // Propiedad para guardar parámetros cargados en modal
    static parametrosGlobalesModal = [];

    /**
     * Cargar y abrir el Modal de Parámetros Globales
     */
    static async abrirModalParametros() {
        try {
            UIUtils.mostrarLoading(true);
            const response = await APIClient.obtenerParametrosCostos();
            if (response.success) {
                // PRIMERO: cargar combinaciones desde los parámetros globales guardados en la BD
                // (disponibles siempre, sin necesidad de tener una versión cargada)
                const combinaciones = {};
                const mapaParams = {};
                if (response.parametros) {
                    response.parametros.forEach(p => {
                        const clave = `${p.rubro}|${p.categoria_padre}`;
                        mapaParams[clave] = p;
                        combinaciones[clave] = {
                            rubro: p.rubro,
                            categoria_padre: p.categoria_padre
                        };
                    });
                }

                // LUEGO: agregar nuevas combinaciones desde los datos cargados de la versión actual
                // (añade rubros/categorías nuevas que aún no están guardadas globalmente)
                DistribucionManager.datosCostos.forEach(d => {
                    const clave = `${d.RUBRO}|${d.CATEGORIA_PADRE}`;
                    if (!combinaciones[clave]) {
                        combinaciones[clave] = {
                            rubro: d.RUBRO,
                            categoria_padre: d.CATEGORIA_PADRE
                        };
                    }
                });

                // Generar lista final de parámetros para el modal
                DistribucionManager.parametrosGlobalesModal = Object.keys(combinaciones).map(clave => {
                    const comb = combinaciones[clave];
                    const paramExistente = mapaParams[clave];
                    return {
                        rubro: comb.rubro,
                        categoria_padre: comb.categoria_padre,
                        costo_prom: paramExistente ? paramExistente.costo_prom : 0,
                        inc_fob: paramExistente ? paramExistente.inc_fob : 0,
                        vcosto: paramExistente ? paramExistente.vcosto : 0,
                        markup_locales_propios: paramExistente ? (paramExistente.markup_locales_propios || 0) : 0,
                        markup_franquicias: paramExistente ? (paramExistente.markup_franquicias || 0) : 0,
                        markup_mayoristas: paramExistente ? (paramExistente.markup_mayoristas || 0) : 0,
                        markup_ecommerce: paramExistente ? (paramExistente.markup_ecommerce || 0) : 0
                    };
                });

                // Renderizar el modal
                DistribucionManager.renderizarParametrosModal();
                
                // Mostrar modal
                const modalEl = document.getElementById('modal-parametros-costos');
                if (modalEl) {
                    // Mover al body si no está allí para evitar conflictos de z-index con el backdrop de Bootstrap
                    if (modalEl.parentNode !== document.body) {
                        document.body.appendChild(modalEl);
                    }
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            } else {
                throw new Error(response.message);
            }
        } catch (e) {
            console.error(e);
            UIUtils.mostrarAlerta('Error al obtener parámetros de costos: ' + e.message, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    /**
     * Renderiza las filas en el modal de parámetros (Costos y Mark-Up por Canal)
     */
    static renderizarParametrosModal(lista = null) {
        const tbodyCostos = document.getElementById('tbody-parametros-globales');
        const tbodyMarkup = document.getElementById('tbody-parametros-markup');
        if (!tbodyCostos || !tbodyMarkup) return;

        const items = lista !== null ? lista : DistribucionManager.parametrosGlobalesModal;

        if (items.length === 0) {
            const emptyCostos = `<tr><td colspan="5" class="text-center text-muted py-3">No hay combinaciones para mostrar</td></tr>`;
            const emptyMarkup = `<tr><td colspan="6" class="text-center text-muted py-3">No hay combinaciones para mostrar</td></tr>`;
            tbodyCostos.innerHTML = emptyCostos;
            tbodyMarkup.innerHTML = emptyMarkup;
            return;
        }

        let htmlCostos = '';
        let htmlMarkup = '';

        items.forEach((item, index) => {
            // Buscar índice en el array general
            const idxOriginal = DistribucionManager.parametrosGlobalesModal.findIndex(p =>
                p.rubro === item.rubro && p.categoria_padre === item.categoria_padre
            );

            const vcosto = item.costo_prom * (1 + item.inc_fob / 100);

            // Tab 1: Costos
            htmlCostos += `
                <tr class="fila-param-modal" data-rubro="${item.rubro.toLowerCase()}" data-categoria="${item.categoria_padre.toLowerCase()}">
                    <td class="fw-bold">${item.rubro}</td>
                    <td>${item.categoria_padre}</td>
                    <td>
                        <input type="number" step="any" class="form-control form-control-sm text-end font-monospace border-primary-subtle"
                               value="${item.costo_prom !== 0 ? item.costo_prom : ''}" placeholder="0.00"
                               oninput="DistribucionManager.actualizarValorParametroModal(${idxOriginal}, 'costo_prom', this.value, this)">
                    </td>
                    <td>
                        <input type="number" step="any" class="form-control form-control-sm text-end font-monospace border-warning-subtle"
                               value="${item.inc_fob !== 0 ? item.inc_fob : ''}" placeholder="0 %"
                               oninput="DistribucionManager.actualizarValorParametroModal(${idxOriginal}, 'inc_fob', this.value, this)">
                    </td>
                    <td class="text-end font-monospace fw-bold text-success" id="modal-vcosto-${idxOriginal}">
                        U$D ${vcosto.toFixed(2)}
                    </td>
                </tr>`;

            // Tab 2: Mark-Up
            htmlMarkup += `
                <tr class="fila-param-modal" data-rubro="${item.rubro.toLowerCase()}" data-categoria="${item.categoria_padre.toLowerCase()}">
                    <td class="fw-bold">${item.rubro}</td>
                    <td>${item.categoria_padre}</td>
                    <td>
                        <input type="number" step="any" class="form-control form-control-sm text-end font-monospace"
                               value="${item.markup_locales_propios !== 0 ? item.markup_locales_propios : ''}" placeholder="0.0"
                               oninput="DistribucionManager.actualizarValorParametroModal(${idxOriginal}, 'markup_locales_propios', this.value, this)">
                    </td>
                    <td>
                        <input type="number" step="any" class="form-control form-control-sm text-end font-monospace"
                               value="${item.markup_franquicias !== 0 ? item.markup_franquicias : ''}" placeholder="0.0"
                               oninput="DistribucionManager.actualizarValorParametroModal(${idxOriginal}, 'markup_franquicias', this.value, this)">
                    </td>
                    <td>
                        <input type="number" step="any" class="form-control form-control-sm text-end font-monospace"
                               value="${item.markup_mayoristas !== 0 ? item.markup_mayoristas : ''}" placeholder="0.0"
                               oninput="DistribucionManager.actualizarValorParametroModal(${idxOriginal}, 'markup_mayoristas', this.value, this)">
                    </td>
                    <td>
                        <input type="number" step="any" class="form-control form-control-sm text-end font-monospace"
                               value="${item.markup_ecommerce !== 0 ? item.markup_ecommerce : ''}" placeholder="0.0"
                               oninput="DistribucionManager.actualizarValorParametroModal(${idxOriginal}, 'markup_ecommerce', this.value, this)">
                    </td>
                </tr>`;
        });

        tbodyCostos.innerHTML = htmlCostos;
        tbodyMarkup.innerHTML = htmlMarkup;
    }

    /**
     * Actualiza el valor del parámetro en memoria al escribir en el modal
     */
    static actualizarValorParametroModal(index, campo, valor, element) {
        const val = parseFloat(valor) || 0;
        const item = DistribucionManager.parametrosGlobalesModal[index];
        if (item) {
            item[campo] = val;
            const vcosto = item.costo_prom * (1 + item.inc_fob / 100);
            item.vcosto = vcosto;

            const vcostoCell = document.getElementById(`modal-vcosto-${index}`);
            if (vcostoCell) {
                vcostoCell.textContent = `U$D ${vcosto.toFixed(2)}`;
            }
        }
    }

    /**
     * Filtrar dinámicamente ambas pestañas del modal usando clases compartidas
     */
    static filtrarParametrosModal(query) {
        const q = query.toLowerCase().trim();
        const rows = document.querySelectorAll('.fila-param-modal');
        rows.forEach(row => {
            const rubro = row.getAttribute('data-rubro') || '';
            const cat = row.getAttribute('data-categoria') || '';
            if (!q || rubro.includes(q) || cat.includes(q)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }


    /**
     * Toma el valor del primer registro para el campo especificado y lo replica en todos los demás registros
     * del listado de parámetros globales del modal.
     */
    static replicarPrimerValorModal(campo) {
        const items = DistribucionManager.parametrosGlobalesModal;
        if (!items || items.length === 0) return;

        // Obtenemos el valor de la primera fila
        const primerValor = items[0][campo] || 0;

        // Replicamos a todas las filas
        items.forEach((item, index) => {
            item[campo] = primerValor;
            if (campo === 'costo_prom' || campo === 'inc_fob') {
                item.vcosto = item.costo_prom * (1 + item.inc_fob / 100);
            }
        });

        // Re-renderizamos para actualizar visualmente todos los inputs y vcostos
        const queryBusqueda = document.getElementById('buscar-parametro-modal')?.value || '';
        if (queryBusqueda.trim() !== '') {
            // Si hay búsqueda activa, filtramos para no perder el estado visual
            DistribucionManager.renderizarParametrosModal();
            DistribucionManager.filtrarParametrosModal(queryBusqueda);
        } else {
            DistribucionManager.renderizarParametrosModal();
        }

        UIUtils.mostrarAlerta(`Se replicó el valor ${primerValor} en todas las filas para ${campo}`, 'success');
    }

    /**
     * Guardar los parámetros globales editados
     */
    static async guardarParametrosModal() {
        try {
            UIUtils.mostrarLoading(true);
            const response = await APIClient.guardarParametrosCostos(DistribucionManager.parametrosGlobalesModal);
            if (response.success) {
                UIUtils.mostrarAlerta('Parámetros globales guardados correctamente', 'success');
                
                // Ocultar modal
                const modalEl = document.getElementById('modal-parametros-costos');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }

                // Recargar costos para recalcular tabla
                await DistribucionManager.cargarCostos();
            } else {
                throw new Error(response.message);
            }
        } catch (e) {
            console.error(e);
            UIUtils.mostrarAlerta('Error al guardar parámetros globales: ' + e.message, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
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

    /**
     * Abrir modal de selección de reporte Excel en Paso 2
     */
    static exportarExcelCostos() {
        if (DistribucionManager.datosCostos.length === 0) {
            UIUtils.mostrarAlerta('No hay datos de costos cargados para exportar', 'warning');
            return;
        }

        const modalEl = document.getElementById('modal-reporte-excel-seleccion');
        if (modalEl) {
            // Mover al body si no está allí para evitar conflictos de z-index y apilamiento con el backdrop
            if (modalEl.parentNode !== document.body) {
                document.body.appendChild(modalEl);
            }
            let modal = bootstrap.Modal.getInstance(modalEl);
            if (!modal) {
                modal = new bootstrap.Modal(modalEl);
            }
            modal.show();
        }
    }

    /**
     * Helper para cerrar de forma limpia el modal de Excel y eliminar backdrops huérfanos
     */
    static cerrarModalExcel() {
        const modalEl = document.getElementById('modal-reporte-excel-seleccion');
        if (modalEl) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            }
        }
        // Limpiar cualquier backdrop que pudiera quedar en el DOM
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }

    /**
     * REPORTE 1: Excel de Venta Comercial
     * Muestra únicamente la Tabla de Venta comercial por canal proyectado por mes.
     * Genera 2 hojas:
     * 1) "Ventas ($)": Venta comercial en ARS con TC por mes.
     * 2) "Unidades por Canal": Unidades proyectadas mes a mes por canal, aperturando Locales Propios por cada sucursal/local.
     */
    static async descargarExcelVentasComercial() {
        try {
            DistribucionManager.cerrarModalExcel();
            UIUtils.mostrarLoading(true);

            // Si los datos de Paso 1 no fueron cargados aún en esta sesión, cargarlos previamente
            if (!DistribucionManager.datos || DistribucionManager.datos.length === 0) {
                await DistribucionManager.cargarDatos();
            }

            const datosPaso1 = DistribucionManager.datos || [];
            const datosPaso2 = DistribucionManager.datosCostos || [];

            // 1. Pestaña de Ventas en Pesos ($)
            const obtenerFilasVentaPesos = () => {
                const filas = [];

                datosPaso2.forEach(row => {
                    const vcosto = row.COSTO_PROM * (1 + row.INC_FOB / 100);
                    const markup = DistribucionManager.obtenerMarkupFila(row);
                    const vventaUnit = vcosto * markup;
                    const canalNormalizado = (row.CANAL || '').toUpperCase().trim();

                    const itemsPaso1 = datosPaso1.filter(p1 => 
                        (p1.RUBRO || '').trim().toUpperCase() === (row.RUBRO || '').trim().toUpperCase() && 
                        (p1.CATEGORIA_PADRE || '').trim().toUpperCase() === (row.CATEGORIA_PADRE || '').trim().toUpperCase() && 
                        (p1.CANAL || '').trim().toUpperCase() === (row.CANAL || '').trim().toUpperCase()
                    );

                    let sucursalesConsolidadas = [];
                    if (canalNormalizado.includes('LOCAL') && itemsPaso1.length > 0) {
                        itemsPaso1.forEach(p1 => {
                            if (p1.SUCURSALES_DETALLE && p1.SUCURSALES_DETALLE.length > 0) {
                                p1.SUCURSALES_DETALLE.forEach(suc => {
                                    sucursalesConsolidadas.push(suc);
                                });
                            }
                        });
                    }

                    if (sucursalesConsolidadas.length > 0) {
                        sucursalesConsolidadas.forEach(suc => {
                            let unidadesTotalSucursal = 0;
                            DistribucionManager.mesesCostosClaves.forEach((m, mIdx) => {
                                const labelMes = DistribucionManager.mesesCostos[mIdx];
                                const unidadesMesSuc = suc.MESES && suc.MESES[labelMes] ? (suc.MESES[labelMes].unidades || 0) : 0;
                                unidadesTotalSucursal += unidadesMesSuc;
                            });

                            const filaObj = {
                                'Rubro': row.RUBRO,
                                'Categoría': row.CATEGORIA_PADRE,
                                'Canal / Sucursal': `${row.CANAL} - ${suc.SUCURSAL}`,
                                'Venta Proyectada (U.)': unidadesTotalSucursal
                            };

                            let ventaTotalFilaUSD = 0;
                            let ventaTotalFilaARS = 0;

                            DistribucionManager.mesesCostosClaves.forEach((m, mIdx) => {
                                const labelMes = DistribucionManager.mesesCostos[mIdx];
                                const tasaMes = DistribucionManager.tasaParaMes(labelMes);
                                const unidadesSuc = suc.MESES && suc.MESES[labelMes] ? (suc.MESES[labelMes].unidades || 0) : 0;

                                const valorVentaMesUSD = vventaUnit * unidadesSuc;
                                const valorVentaMesARS = valorVentaMesUSD * tasaMes;

                                filaObj[`${labelMes} (TC $${Math.round(tasaMes)})`] = Math.round(valorVentaMesARS);
                                ventaTotalFilaUSD += valorVentaMesUSD;
                                ventaTotalFilaARS += valorVentaMesARS;
                            });

                            const tcPromedioFila = ventaTotalFilaUSD !== 0 ? (ventaTotalFilaARS / ventaTotalFilaUSD) : DistribucionManager.tasaPromedioPeriodo();
                            filaObj['TC Prom. Fila'] = parseFloat(tcPromedioFila.toFixed(2));
                            filaObj['Venta Total ($)'] = Math.round(ventaTotalFilaARS);

                            filas.push(filaObj);
                        });
                    } else {
                        const filaObj = {
                            'Rubro': row.RUBRO,
                            'Categoría': row.CATEGORIA_PADRE,
                            'Canal / Sucursal': row.CANAL,
                            'Venta Proyectada (U.)': row.COMPRA_DISTRIBUIDA || 0
                        };

                        let ventaTotalFilaUSD = 0;
                        let ventaTotalFilaARS = 0;

                        DistribucionManager.mesesCostosClaves.forEach((m, mIdx) => {
                            const labelMes = DistribucionManager.mesesCostos[mIdx];
                            const tasaMes = DistribucionManager.tasaParaMes(labelMes);
                            const unidadesMes = row.MESES_UNIDADES[m] || 0;

                            const valorVentaMesUSD = vventaUnit * unidadesMes;
                            const valorVentaMesARS = valorVentaMesUSD * tasaMes;

                            filaObj[`${labelMes} (TC $${Math.round(tasaMes)})`] = Math.round(valorVentaMesARS);
                            ventaTotalFilaUSD += valorVentaMesUSD;
                            ventaTotalFilaARS += valorVentaMesARS;
                        });

                        const tcPromedioFila = ventaTotalFilaUSD !== 0 ? (ventaTotalFilaARS / ventaTotalFilaUSD) : DistribucionManager.tasaPromedioPeriodo();
                        filaObj['TC Prom. Fila'] = parseFloat(tcPromedioFila.toFixed(2));
                        filaObj['Venta Total ($)'] = Math.round(ventaTotalFilaARS);

                        filas.push(filaObj);
                    }
                });

                return filas;
            };

            // 2. Pestaña de Unidades por Canal (con aperturas de Locales Propios por sucursal)
            const obtenerFilasUnidadesPorCanal = () => {
                const filas = [];

                datosPaso2.forEach(row => {
                    const canalNormalizado = (row.CANAL || '').toUpperCase().trim();

                    const itemsPaso1 = datosPaso1.filter(p1 => 
                        (p1.RUBRO || '').trim().toUpperCase() === (row.RUBRO || '').trim().toUpperCase() && 
                        (p1.CATEGORIA_PADRE || '').trim().toUpperCase() === (row.CATEGORIA_PADRE || '').trim().toUpperCase() && 
                        (p1.CANAL || '').trim().toUpperCase() === (row.CANAL || '').trim().toUpperCase()
                    );

                    let sucursalesConsolidadas = [];
                    if (canalNormalizado.includes('LOCAL') && itemsPaso1.length > 0) {
                        itemsPaso1.forEach(p1 => {
                            if (p1.SUCURSALES_DETALLE && p1.SUCURSALES_DETALLE.length > 0) {
                                p1.SUCURSALES_DETALLE.forEach(suc => {
                                    sucursalesConsolidadas.push(suc);
                                });
                            }
                        });
                    }

                    if (sucursalesConsolidadas.length > 0) {
                        sucursalesConsolidadas.forEach(suc => {
                            let sumaUnidadesSucursal = 0;

                            const filaObj = {
                                'Rubro': row.RUBRO,
                                'Categoría': row.CATEGORIA_PADRE,
                                'Canal / Sucursal': `${row.CANAL} - ${suc.SUCURSAL}`
                            };

                            DistribucionManager.mesesCostosClaves.forEach((m, mIdx) => {
                                const labelMes = DistribucionManager.mesesCostos[mIdx];
                                const unidadesSuc = suc.MESES && suc.MESES[labelMes] ? (suc.MESES[labelMes].unidades || 0) : 0;
                                filaObj[labelMes] = unidadesSuc;
                                sumaUnidadesSucursal += unidadesSuc;
                            });

                            filaObj['Total Unidades'] = sumaUnidadesSucursal;
                            filas.push(filaObj);
                        });
                    } else {
                        let sumaUnidadesCanal = 0;

                        const filaObj = {
                            'Rubro': row.RUBRO,
                            'Categoría': row.CATEGORIA_PADRE,
                            'Canal / Sucursal': row.CANAL
                        };

                        DistribucionManager.mesesCostosClaves.forEach((m, mIdx) => {
                            const labelMes = DistribucionManager.mesesCostos[mIdx];
                            const unidadesMes = row.MESES_UNIDADES[m] || 0;
                            filaObj[labelMes] = unidadesMes;
                            sumaUnidadesCanal += unidadesMes;
                        });

                        filaObj['Total Unidades'] = sumaUnidadesCanal;
                        filas.push(filaObj);
                    }
                });

                return filas;
            };

            const hojas = {
                'Ventas ($)': obtenerFilasVentaPesos(),
                'Unidades por Canal': obtenerFilasUnidadesPorCanal()
            };

            await ExcelExporter.exportarMultiplesHojas(hojas, `Reporte_Ventas_Comercial_${new Date().toISOString().slice(0,10)}.xlsx`);
            UIUtils.mostrarAlerta('Excel de Venta Comercial generado correctamente', 'success');
        } catch (e) {
            console.error('Error generando Excel de Venta:', e);
            UIUtils.mostrarAlerta('Error generando Excel de Venta: ' + e.message, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    /**
     * REPORTE 2: Excel Financiero
     * Apertura completa por Rubro, Categoría y Canal.
     * Incluye tanto la Tabla de Costos como la Tabla de Ventas.
     * Genera 4 hojas: "Costos ($)", "Ventas ($)", "Costos (USD)", "Ventas (USD)"
     */
    static async descargarExcelFinanciero() {
        try {
            DistribucionManager.cerrarModalExcel();
            UIUtils.mostrarLoading(true);

            const datosPaso2 = DistribucionManager.datosCostos || [];

            const obtenerFilasFinanciero = (tipoReporte, modoMoneda) => {
                const esARS = modoMoneda === 'ARS';
                const esVenta = tipoReporte === 'VENTA';
                const filas = [];

                datosPaso2.forEach(row => {
                    const vcosto = row.COSTO_PROM * (1 + row.INC_FOB / 100);
                    const markup = DistribucionManager.obtenerMarkupFila(row);
                    const multiplicador = esVenta ? (vcosto * markup) : vcosto;

                    const filaObj = {
                        'Rubro': row.RUBRO,
                        'Categoría': row.CATEGORIA_PADRE,
                        'Canal': row.CANAL,
                        'Venta Proyectada (U.)': row.COMPRA_DISTRIBUIDA,
                        'Vcosto Base (U$D)': parseFloat(vcosto.toFixed(2))
                    };

                    if (esVenta) {
                        filaObj['Mark-Up (x)'] = parseFloat(markup.toFixed(2));
                    }

                    let acumuladoTotalUSD = 0;
                    let acumuladoTotalARS = 0;

                    DistribucionManager.mesesCostosClaves.forEach((m, mIdx) => {
                        const labelMes = DistribucionManager.mesesCostos[mIdx];
                        const tasaMes = DistribucionManager.tasaParaMes(labelMes);
                        const unidadesMes = row.MESES_UNIDADES[m] || 0;

                        const valorMesUSD = multiplicador * unidadesMes;
                        const valorMesARS = valorMesUSD * tasaMes;

                        const colHeader = esARS ? `${labelMes} (TC $${Math.round(tasaMes)})` : labelMes;
                        filaObj[colHeader] = esARS ? Math.round(valorMesARS) : Math.round(valorMesUSD);

                        acumuladoTotalUSD += valorMesUSD;
                        acumuladoTotalARS += valorMesARS;
                    });

                    const tcPromedioFila = acumuladoTotalUSD !== 0 ? (acumuladoTotalARS / acumuladoTotalUSD) : DistribucionManager.tasaPromedioPeriodo();
                    if (esARS) {
                        filaObj['TC Prom. Fila'] = parseFloat(tcPromedioFila.toFixed(2));
                        const labelColTotal = esVenta ? 'Venta Total ($)' : 'Costo Total ($)';
                        filaObj[labelColTotal] = Math.round(acumuladoTotalARS);
                    } else {
                        const labelColTotal = esVenta ? 'Venta Total (U$D)' : 'Costo Total (U$D)';
                        filaObj[labelColTotal] = Math.round(acumuladoTotalUSD);
                    }

                    filas.push(filaObj);
                });

                return filas;
            };

            const hojas = {
                'Costos ($)': obtenerFilasFinanciero('COSTO', 'ARS'),
                'Ventas ($)': obtenerFilasFinanciero('VENTA', 'ARS'),
                'Costos (USD)': obtenerFilasFinanciero('COSTO', 'USD'),
                'Ventas (USD)': obtenerFilasFinanciero('VENTA', 'USD')
            };

            await ExcelExporter.exportarMultiplesHojas(hojas, `Reporte_Presupuesto_Financiero_${new Date().toISOString().slice(0,10)}.xlsx`);
            UIUtils.mostrarAlerta('Excel Financiero generado correctamente', 'success');
        } catch (e) {
            console.error('Error generando Excel Financiero:', e);
            UIUtils.mostrarAlerta('Error generando Excel Financiero: ' + e.message, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
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

    /**
     * Parsear el label de mes (e.g. "Ene 27") y devolver { mes: 1, anio: 2027 }
     */
    static parsearMesAnio(label) {
        const parts = label.trim().split(/\s+/);
        if (parts.length < 2) return null;
        const mesStr = parts[0];
        const anioSuffix = parseInt(parts[1], 10);
        // Determinar el año completo (2000+xx)
        const anio = anioSuffix < 50 ? 2000 + anioSuffix : 1900 + anioSuffix;
        const mes = DistribucionManager.MESES_NUM[mesStr];
        if (!mes) return null;
        return { mes, anio };
    }

    /**
     * Obtener la tasa de cambio para un mes mostrado.
     * Busca primero la cotización del contrato futuro directo de ese mes y año (Ej: "8-2026" para "Ago 26").
     * Si no existe cotización futura para esa fecha, usa como fallback la cotización histórica del mismo mes del año anterior.
     */
    static tasaParaMes(label) {
        const parsed = DistribucionManager.parsearMesAnio(label);
        if (!parsed) return 1;

        // 1. Buscar cotización futura exacta para ese mes y año (Ej: "8-2026" o "1-2027")
        const claveDirecta = `${parsed.mes}-${parsed.anio}`;
        if (DistribucionManager.tipoCambio[claveDirecta] && DistribucionManager.tipoCambio[claveDirecta] > 0) {
            return DistribucionManager.tipoCambio[claveDirecta];
        }

        // 2. Fallback: buscar cotización histórica del mismo mes del año anterior
        const claveAnterior = `${parsed.mes}-${parsed.anio - 1}`;
        const tasa = DistribucionManager.tipoCambio[claveAnterior];
        return tasa && tasa > 0 ? tasa : 1;
    }

    /**
     * Convertir un valor ARS a USD si el modo es USD, o devolver el valor original.
     * Para valores agregados (sin mes específico), usa el promedio de tasas del período.
     */
    static convertirValor(valor, tasaOverride = null) {
        if (DistribucionManager.modoMoneda === 'ARS') return valor;
        const tasa = tasaOverride !== null ? tasaOverride : DistribucionManager.tasaPromedioPeriodo();
        return tasa > 0 ? valor / tasa : valor;
    }

    /**
     * Promedio de tasas del período de meses mostrados
     * Usa meses de Step 1, Step 2 o Step 3 según disponibilidad y paso activo.
     */
    static tasaPromedioPeriodo() {
        let mesesRef = [];
        if (DistribucionManager.pasoActivo === 3 && DistribucionManager.mesesDesvios.length > 0) {
            mesesRef = DistribucionManager.mesesDesvios;
        } else if (DistribucionManager.meses.length > 0) {
            mesesRef = DistribucionManager.meses;
        } else {
            mesesRef = DistribucionManager.mesesCostos;
        }

        if (!mesesRef || mesesRef.length === 0) return 1;
        let sum = 0, count = 0;
        mesesRef.forEach(m => {
            const tasa = DistribucionManager.tasaParaMes(m);
            if (tasa > 1) { sum += tasa; count++; }
        });
        return count > 0 ? sum / count : 1;
    }

    /**
     * Toggle entre ARS y USD. Carga tasas si es necesario.
     */
    static async toggleMoneda(modo) {
        if (DistribucionManager.modoMoneda === modo) return;

        if (modo === 'USD' && Object.keys(DistribucionManager.tipoCambio).length === 0) {
            try {
                UIUtils.mostrarLoading(true);
                const response = await APIClient.obtenerTipoCambio();
                if (response.success) {
                    DistribucionManager.tipoCambio = response.tasas || {};
                } else {
                    UIUtils.mostrarAlerta('No se pudieron cargar las tasas de cambio: ' + (response.message || ''), 'error');
                    UIUtils.mostrarLoading(false);
                    return;
                }
            } catch (e) {
                UIUtils.mostrarAlerta('Error al cargar tasas de cambio: ' + e.message, 'error');
                UIUtils.mostrarLoading(false);
                return;
            } finally {
                UIUtils.mostrarLoading(false);
            }
        }

        DistribucionManager.modoMoneda = modo;
        DistribucionManager.actualizarVisualizacionMoneda();

        // Re-renderizar la tabla que corresponda al paso activo
        if (DistribucionManager.pasoActivo === 2) {
            DistribucionManager.filtrarDatosCostos(); // Aplica filtros actuales
        } else if (DistribucionManager.pasoActivo === 3) {
            DistribucionManager.renderizarDesviosPaso3();
            DistribucionManager.inicializarGraficosPaso3();
        } else {
            DistribucionManager.renderizarTabla();
        }
    }

    /**
     * Actualiza el estado visual de los botones del switch de moneda
     */
    static actualizarVisualizacionMoneda() {
        const modo = DistribucionManager.modoMoneda;
        const btnArs = document.getElementById('btn-moneda-ars');
        const btnUsd = document.getElementById('btn-moneda-usd');
        const labelTC = document.getElementById('label-tipo-cambio');

        if (btnArs && btnUsd) {
            if (modo === 'USD') {
                btnArs.className = 'btn btn-outline-primary btn-sm';
                btnUsd.className = 'btn btn-success btn-sm';
                if (labelTC) {
                    const tasaRef = DistribucionManager.tasaPromedioPeriodo();
                    labelTC.textContent = `(TC prom: $${tasaRef.toFixed(0)})`;
                    labelTC.classList.remove('d-none');
                }
            } else {
                btnArs.className = 'btn btn-primary active btn-sm';
                btnUsd.className = 'btn btn-outline-success btn-sm';
                if (labelTC) {
                    labelTC.textContent = '';
                    labelTC.classList.add('d-none');
                }
            }
        }
    }

    /**
     * Alterna la visibilidad (colapsar/expandir) de los contenedores de tablas en el paso 2
     */
    static toggleCollapseTabla(containerId, btn) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const isCollapsed = container.style.display === 'none';
        if (isCollapsed) {
            container.style.display = 'block';
            btn.innerHTML = `<i class="fas fa-chevron-up me-1"></i>Colapsar`;
        } else {
            container.style.display = 'none';
            btn.innerHTML = `<i class="fas fa-chevron-down me-1"></i>Expandir`;
        }
    }

    /**
     * Helper estático para obtener el markup correspondiente al canal de la fila
     */
    static obtenerMarkupFila(row) {
        const canal = (row.CANAL || '').toUpperCase();
        if (canal.includes('LOCAL')) return row.MARKUP_LOCALES_PROPIOS || 0.0;
        if (canal.includes('FRANQ')) return row.MARKUP_FRANQUICIAS || 0.0;
        if (canal.includes('MAYOR')) return row.MARKUP_MAYORISTAS || 0.0;
        if (canal.includes('ECOM') || canal.includes('WEB')) return row.MARKUP_ECOMMERCE || 0.0;
        return 0.0;
    }

    /**
     * Maneja el cambio entre las sub-pestañas 2A (Gestión) y 2B (Análisis Locales Propios)
     */
    static async cambiarSubtabPaso2(subtab) {
        if (subtab === '2B') {
            await DistribucionManager.renderizarTablaAnalisis2B();
        }
    }

    /**
     * Renderiza la tabla de Análisis 2B: Presupuesto de Venta de Locales Propios desglosado por Sucursal / Local
     * Nivel 1 (Principal): Sucursal (Summarizado con total por mes)
     * Nivel 2 (Desplegable): Rubros y Categorías que pertenecen a dicha sucursal
     */
    static async renderizarTablaAnalisis2B() {
        const theadRow = document.getElementById('thead-analisis-locales-row');
        const tbody = document.getElementById('tbody-analisis-locales');
        if (!theadRow || !tbody) return;

        // Si los datos del Paso 2 no están cargados, cargarlos
        if (!DistribucionManager.datosCostos || DistribucionManager.datosCostos.length === 0) {
            await DistribucionManager.cargarCostos();
        }

        const esARS = DistribucionManager.modoMoneda === 'ARS';
        const tasaPromedio = esARS ? DistribucionManager.tasaPromedioPeriodo() : 1;
        const sufMoneda = esARS ? ' ($)' : ' (U$D)';

        const fmtDinero = (v, tasa = null) => {
            const t = tasa !== null ? tasa : tasaPromedio;
            const valorFinal = esARS ? (v * t) : v;
            return FormatoUtils.formatearNumero(Math.round(valorFinal));
        };

        // 1. Reconstruir cabecera con meses (Sucursal | Rubro | Categoría | ...)
        let headHtml = `
            <th style="min-width:200px;">Sucursal / Local Propio</th>
            <th>Rubro</th>
            <th>Categoría</th>
            <th class="text-end bg-secondary text-white" style="width: 140px;">Venta Proyectada (U.)</th>
        `;

        DistribucionManager.mesesCostos.forEach(m => {
            const tasaMes = esARS ? DistribucionManager.tasaParaMes(m) : 1;
            headHtml += `
                <th class="text-end bg-dark text-white" style="min-width: 90px;">
                    ${m}${esARS ? '<br><small style="font-size:0.65rem;opacity:0.8;">TC $' + Math.round(tasaMes) + '</small>' : ''}
                </th>`;
        });
        headHtml += `<th class="text-end bg-warning text-dark" style="width: 150px;">Venta Total${sufMoneda}</th>`;
        theadRow.innerHTML = headHtml;

        const datosPaso1 = DistribucionManager.datos || [];
        const datosPaso2 = DistribucionManager.datosCostos || [];

        // Filtrar datosPaso2 solo para Locales Propios
        const datosLocalesPropios = datosPaso2.filter(row => (row.CANAL || '').toUpperCase().includes('LOCAL'));

        if (datosLocalesPropios.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${5 + DistribucionManager.mesesCostos.length}" class="text-center text-muted py-4">
                        <i class="fas fa-store-slash mb-2"></i><br>
                        No hay datos cargados para el canal Locales Propios
                    </td>
                </tr>`;
            return;
        }

        // Agrupar por SUCURSAL / LOCAL
        const mapaSucursales = {}; // { 'ALTO PALERMO': { sucursal: 'ALTO PALERMO', totalUnidades: 0, totalVentaUSD: 0, mesesUSD: {}, items: [] } }

        datosLocalesPropios.forEach(row => {
            const vcosto = row.COSTO_PROM * (1 + row.INC_FOB / 100);
            const markup = DistribucionManager.obtenerMarkupFila(row);
            const vventaUnit = vcosto * markup;

            // Buscar coincidencia en Paso 1 para traer las sucursales
            const itemPaso1 = datosPaso1.find(p1 =>
                (p1.RUBRO || '').trim().toUpperCase() === (row.RUBRO || '').trim().toUpperCase() &&
                (p1.CATEGORIA_PADRE || '').trim().toUpperCase() === (row.CATEGORIA_PADRE || '').trim().toUpperCase() &&
                (p1.CANAL || '').trim().toUpperCase().includes('LOCAL')
            );

            const sucursales = (itemPaso1 && itemPaso1.SUCURSALES_DETALLE && itemPaso1.SUCURSALES_DETALLE.length > 0)
                ? itemPaso1.SUCURSALES_DETALLE
                : [];

            if (sucursales.length > 0) {
                // Para garantizar coincidencia exacta con 2A (que multiplica directamente row.MESES_UNIDADES[m] * vventaUnit),
                // calculamos primero la venta mensual exacta de esta categoría para Locales Propios
                const ventaCategoriaMesUSD = {};
                DistribucionManager.mesesCostosClaves.forEach((m, mIdx) => {
                    const unidadesTotalCatMes = row.MESES_UNIDADES[m] || 0;
                    ventaCategoriaMesUSD[m] = vventaUnit * unidadesTotalCatMes;
                });

                sucursales.forEach(suc => {
                    const nombreSuc = suc.SUCURSAL || 'LOCAL SIN NOMBRE';
                    const partSucDecimal = (parseFloat(suc.PARTICIPACION) || 0) / 100;

                    if (!mapaSucursales[nombreSuc]) {
                        mapaSucursales[nombreSuc] = {
                            sucursal: nombreSuc,
                            participacionPromedio: suc.PARTICIPACION || 0,
                            totalUnidades: 0,
                            totalVentaUSD: 0,
                            mesesUSD: {},
                            items: []
                        };
                        DistribucionManager.mesesCostosClaves.forEach(m => mapaSucursales[nombreSuc].mesesUSD[m] = 0);
                    }

                    let itemUnidadesTotal = 0;
                    let itemVentaTotalUSD = 0;
                    const itemMesesUSD = {};

                    DistribucionManager.mesesCostosClaves.forEach((m, mIdx) => {
                        const labelMes = DistribucionManager.mesesCostos[mIdx];
                        
                        // Si la sucursal tiene unidades explícitas guardadas, usarlas; de lo contrario prorratear de row.MESES_UNIDADES[m]
                        const unidadesSucMes = (suc.MESES && suc.MESES[labelMes] && suc.MESES[labelMes].unidades !== undefined)
                            ? (suc.MESES[labelMes].unidades || 0)
                            : (row.MESES_UNIDADES[m] || 0) * partSucDecimal;

                        // La venta en USD se prorratea del total exacto de la categoría en 2A para prevenir descalces por redondeo
                        const ventaSucMesUSD = (suc.MESES && suc.MESES[labelMes] && suc.MESES[labelMes].unidades !== undefined)
                            ? vventaUnit * unidadesSucMes
                            : ventaCategoriaMesUSD[m] * partSucDecimal;

                        itemUnidadesTotal += unidadesSucMes;
                        itemVentaTotalUSD += ventaSucMesUSD;
                        itemMesesUSD[m] = ventaSucMesUSD;

                        mapaSucursales[nombreSuc].mesesUSD[m] += ventaSucMesUSD;
                    });

                    mapaSucursales[nombreSuc].totalUnidades += itemUnidadesTotal;
                    mapaSucursales[nombreSuc].totalVentaUSD += itemVentaTotalUSD;

                    mapaSucursales[nombreSuc].items.push({
                        rubro: row.RUBRO,
                        categoria: row.CATEGORIA_PADRE,
                        vcosto: vcosto,
                        unidadesTotal: itemUnidadesTotal,
                        ventaTotalUSD: itemVentaTotalUSD,
                        mesesUSD: itemMesesUSD
                    });
                });
            }
        });

        const listaSucursales = Object.values(mapaSucursales);
        // Ordenar sucursales por mayor venta total acumulada
        listaSucursales.sort((a, b) => b.totalVentaUSD - a.totalVentaUSD);

        if (listaSucursales.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${5 + DistribucionManager.mesesCostos.length}" class="text-center text-muted py-4">
                        <i class="fas fa-info-circle mb-2"></i><br>
                        No se encontraron sucursales desglosadas para Locales Propios
                    </td>
                </tr>`;
            return;
        }

        // Si hay sucursales agrupadas, ajustar el gran total mensual sumando directamente desde los totales exactos de cada categoría en 2A
        const totalExactoLocalesPropiosMesUSD = {};
        let totalExactoLocalesPropiosVentaUSD = 0;
        let totalExactoLocalesPropiosUnidades = 0;

        DistribucionManager.mesesCostosClaves.forEach(m => totalExactoLocalesPropiosMesUSD[m] = 0);

        datosLocalesPropios.forEach(row => {
            const vcosto = row.COSTO_PROM * (1 + row.INC_FOB / 100);
            const markup = DistribucionManager.obtenerMarkupFila(row);
            const vventaUnit = vcosto * markup;
            totalExactoLocalesPropiosUnidades += (row.COMPRA_DISTRIBUIDA || 0);

            DistribucionManager.mesesCostosClaves.forEach(m => {
                const unidadesCatMes = row.MESES_UNIDADES[m] || 0;
                const vUSD = vventaUnit * unidadesCatMes;
                totalExactoLocalesPropiosMesUSD[m] += vUSD;
                totalExactoLocalesPropiosVentaUSD += vUSD;
            });
        });

        let htmlBody = '';
        let totalEmpresaUnidades = 0;
        let totalEmpresaVentaUSD = 0;
        const totalEmpresaMesesUSD = {};
        DistribucionManager.mesesCostosClaves.forEach(m => totalEmpresaMesesUSD[m] = 0);

        listaSucursales.forEach((sucData, sucIdx) => {
            totalEmpresaUnidades += sucData.totalUnidades;
            totalEmpresaVentaUSD += sucData.totalVentaUSD;

            let mesesSucRowHtml = '';
            DistribucionManager.mesesCostosClaves.forEach((m, mIdx) => {
                const labelMes = DistribucionManager.mesesCostos[mIdx];
                const tasaMes = esARS ? DistribucionManager.tasaParaMes(labelMes) : 1;
                const mUSD = sucData.mesesUSD[m] || 0;
                totalEmpresaMesesUSD[m] += mUSD;

                mesesSucRowHtml += `<td class="text-end font-monospace fw-bold">${fmtDinero(mUSD, tasaMes)}</td>`;
            });

            // Fila Nivel 1: SUCURSAL / LOCAL PROPIO (Consolidadora)
            htmlBody += `
                <tr id="row-suc-2b-${sucIdx}" class="table-group-header align-middle" style="background:#eef2f7; border-top: 2px solid #90caf9;">
                    <td class="fw-bold text-primary fs-6">
                        <button class="btn btn-xs btn-primary me-2 py-0 px-2 rounded-circle" onclick="DistribucionManager.toggleSucursales2B(${sucIdx}, this)">
                            <i class="fas fa-chevron-right" style="font-size:0.75rem;"></i>
                        </button>
                        <i class="fas fa-store me-1 text-warning"></i>${sucData.sucursal}
                        <small class="text-muted font-monospace fw-normal ms-1">(${sucData.items.length} categorías)</small>
                    </td>
                    <td class="text-muted font-monospace fs-7">Venta Sucursal</td>
                    <td class="text-muted font-monospace fs-7">Consolidado</td>
                    <td class="text-end font-monospace fw-bold bg-secondary bg-opacity-10 fs-6">${FormatoUtils.formatearNumero(sucData.totalUnidades)}</td>
                    ${mesesSucRowHtml}
                    <td class="text-end font-monospace fw-bold text-success bg-success bg-opacity-10 fs-6">${fmtDinero(sucData.totalVentaUSD)}</td>
                </tr>`;

            // Filas Nivel 2: Rubros y Categorías pertenecientes a la Sucursal
            sucData.items.forEach(item => {
                let mesesItemHtml = '';
                DistribucionManager.mesesCostosClaves.forEach((m, mIdx) => {
                    const labelMes = DistribucionManager.mesesCostos[mIdx];
                    const tasaMes = esARS ? DistribucionManager.tasaParaMes(labelMes) : 1;
                    const itemMUSD = item.mesesUSD[m] || 0;
                    mesesItemHtml += `<td class="text-end font-monospace text-muted" style="font-size:0.85rem;">${fmtDinero(itemMUSD, tasaMes)}</td>`;
                });

                htmlBody += `
                    <tr class="table-light child-2b-row-${sucIdx} bg-light-subtle" style="display: none; font-size: 0.85rem; border-left: 4px solid #ffc107; vertical-align: middle;">
                        <td class="ps-4 text-muted font-monospace"><i class="fas fa-level-up-alt fa-rotate-90 me-2 text-warning"></i>${sucData.sucursal}</td>
                        <td class="fw-bold text-dark">${item.rubro}</td>
                        <td class="text-secondary fw-semibold">${item.categoria}</td>
                        <td class="text-end font-monospace text-muted">${FormatoUtils.formatearNumero(item.unidadesTotal)}</td>
                        ${mesesItemHtml}
                        <td class="text-end font-monospace text-success fw-bold" style="background:#e8f5e9;">${fmtDinero(item.ventaTotalUSD)}</td>
                    </tr>`;
            });
        });

        // Fila Total General para 2B (coincidente al 100% con 2A)
        let mesesTotalEmpresaHtml = '';
        DistribucionManager.mesesCostosClaves.forEach((m, mIdx) => {
            const labelMes = DistribucionManager.mesesCostos[mIdx];
            const tasaMes = esARS ? DistribucionManager.tasaParaMes(labelMes) : 1;
            mesesTotalEmpresaHtml += `<td class="text-end fw-bold font-monospace bg-dark text-white" style="font-size:0.85rem;">${fmtDinero(totalExactoLocalesPropiosMesUSD[m], tasaMes)}</td>`;
        });

        const totalAnalisisRowHtml = `
            <tr style="border-top: 3px double #000; border-bottom: 3px double #000; vertical-align: middle;" class="table-dark">
                <td colspan="3" class="fw-bold text-uppercase" style="font-size:0.85rem; padding: 8px;">
                    <i class="fas fa-calculator me-1"></i>TOTAL LOCALES PROPIOS (${listaSucursales.length} LOCALES)
                </td>
                <td class="text-end fw-bold font-monospace bg-secondary text-white" style="font-size:0.85rem;">${FormatoUtils.formatearNumero(totalExactoLocalesPropiosUnidades)}</td>
                ${mesesTotalEmpresaHtml}
                <td class="text-end fw-bold font-monospace bg-warning text-dark" style="font-size:0.85rem;">${fmtDinero(totalExactoLocalesPropiosVentaUSD)}</td>
            </tr>
        `;
        htmlBody += totalAnalisisRowHtml;

        tbody.innerHTML = htmlBody;

        // Actualizar badge total en header
        const headerIndicator = document.getElementById('indicador-locales-propios-totales-header');
        if (headerIndicator) {
            headerIndicator.innerHTML = `
                <span class="badge bg-warning text-dark fs-6 font-monospace shadow-sm">
                    Total Locales: ${fmtDinero(totalExactoLocalesPropiosVentaUSD)}
                </span>
            `;
        }
    }

    /**
     * Alterna la expansión de sucursales para una fila específica en la tabla de Análisis 2B
     */
    static toggleSucursales2B(index, btn) {
        const icon = btn.querySelector('i');
        const childRows = document.querySelectorAll(`.child-2b-row-${index}`);
        const isCollapsed = icon.classList.contains('fa-chevron-right');

        if (isCollapsed) {
            icon.classList.remove('fa-chevron-right');
            icon.classList.add('fa-chevron-down');
            childRows.forEach(row => row.style.display = '');
        } else {
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-right');
            childRows.forEach(row => row.style.display = 'none');
        }
    }

    /**
     * Expande o colapsa todas las sucursales en la pestaña de Análisis 2B
     */
    static toggleExpandirTodasSucursales2B(btn) {
        const isExpanding = btn.innerHTML.includes('Expandir');
        const allChildRows = document.querySelectorAll('#tbody-analisis-locales tr[class*="child-2b-row-"]');
        const allIcons = document.querySelectorAll('#tbody-analisis-locales button i.fa-chevron-right, #tbody-analisis-locales button i.fa-chevron-down');

        if (isExpanding) {
            btn.innerHTML = `<i class="fas fa-compress-alt me-1"></i>Colapsar Todo`;
            allChildRows.forEach(r => r.style.display = '');
            allIcons.forEach(i => { i.classList.remove('fa-chevron-right'); i.classList.add('fa-chevron-down'); });
        } else {
            btn.innerHTML = `<i class="fas fa-expand-alt me-1"></i>Expandir Todo`;
            allChildRows.forEach(r => r.style.display = 'none');
            allIcons.forEach(i => { i.classList.remove('fa-chevron-down'); i.classList.add('fa-chevron-right'); });
        }
    }

    // --- PROPIEDADES Y MÉTODOS PARA PASO 3 (DESVÍOS) ---
    static datosDesvios = [];
    static mesesDesvios = [];
    static filtroCanalActivoPaso3 = null;
    static chartCanalesInstancia = null;
    static chartMensualInstancia = null;

    static async cargarPaso3() {
        try {
            UIUtils.mostrarLoading(true);
            const tbody = document.getElementById('tbody-desvios-paso3');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                            Cargando y comparando desvíos contra venta real...
                        </td>
                    </tr>`;
            }

            const temporadaSelect = document.getElementById('filtro-temporada-distribucion');
            const temporada = temporadaSelect ? temporadaSelect.value : 'VERANO';
            const versionSelect = document.getElementById('filtro-version-distribucion');
            const version = versionSelect ? versionSelect.value : 'Por defecto';

            // Asegurar tipo de cambio
            if (Object.keys(DistribucionManager.tipoCambio).length === 0) {
                const tcResponse = await APIClient.obtenerTipoCambio();
                if (tcResponse.success) {
                    DistribucionManager.tipoCambio = tcResponse.tasas || {};
                }
            }

            const response = await APIClient.obtenerDesvios(temporada, version);

            if (response.success && response.desvios) {
                DistribucionManager.datosDesvios = response.desvios;
                DistribucionManager.mesesDesvios = response.meses || [];
                DistribucionManager.renderizarDesviosPaso3();
                DistribucionManager.inicializarGraficosPaso3();
                DistribucionManager.actualizarVisualizacionMoneda();
                UIUtils.mostrarAlerta('Desvíos y ventas reales cargados con éxito.', 'success');
            } else {
                throw new Error(response.message || 'No se pudieron recuperar datos de desvíos.');
            }
        } catch (error) {
            console.error('Error cargando Paso 3:', error);
            UIUtils.mostrarAlerta('Error al obtener desvíos: ' + error.message, 'error');
            const tbody = document.getElementById('tbody-desvios-paso3');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-danger py-4">
                            <i class="fas fa-exclamation-triangle me-2"></i> Error: ${error.message}
                        </td>
                    </tr>`;
            }
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    static renderizarDesviosPaso3() {
        const tbody = document.getElementById('tbody-desvios-paso3');
        if (!tbody) return;

        const esARS = DistribucionManager.modoMoneda === 'ARS';
        const tasaPromedio = esARS ? DistribucionManager.tasaPromedioPeriodo() : 1.0;
        const sufMoneda = esARS ? 'ARS' : 'USD';

        // Si no hay datos cargados, mostrar aviso en el tbody
        if (!DistribucionManager.datosDesvios || DistribucionManager.datosDesvios.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        <i class="fas fa-exclamation-circle mb-2" style="font-size: 1.5rem;"></i><br>
                        Primero debe guardar una versión en el <strong>Paso 1</strong> para poder comparar sus desvíos.
                    </td>
                </tr>`;
            return;
        }

        // Actualizar labels en th de la tabla
        const thFactPres = document.getElementById('th-fact-pres');
        const thFactReal = document.getElementById('th-fact-real');
        const thFactDesv = document.getElementById('th-th-fact-desv');
        if (thFactPres) thFactPres.textContent = `Fact. Presupuesto (${esARS ? '$' : 'U$D'})`;
        if (thFactReal) thFactReal.textContent = `Fact. Real (${esARS ? '$' : 'U$D'})`;

        // Actualizar etiquetas KPI
        document.querySelectorAll('.moneda-label').forEach(el => el.textContent = esARS ? '($)' : '(U$D)');

        let totalUnidadesP = 0;
        let totalUnidadesR = 0;
        let totalFactP = 0.0;
        let totalFactR = 0.0;

        // Calcular totales de cabecera generales de la Empresa
        DistribucionManager.datosDesvios.forEach(d => {
            totalUnidadesP += d.unidades_presupuesto || 0;
            totalUnidadesR += d.unidades_real || 0;
            totalFactP += d.facturacion_usd_presupuesto || 0.0;
            totalFactR += d.facturacion_usd_real || 0.0;
        });

        const totalUnidadesDesvio = totalUnidadesR - totalUnidadesP;
        const totalUnidadesDesvioPct = totalUnidadesP > 0 ? (totalUnidadesDesvio / totalUnidadesP) * 100 : 0;

        const totalFactDesvio = totalFactR - totalFactP;
        const totalFactDesvioPct = totalFactP > 0 ? (totalFactDesvio / totalFactP) * 100 : 0;

        // Escribir KPIs
        const kpiUP = document.getElementById('kpi-unidades-presupuesto');
        const kpiUR = document.getElementById('kpi-unidades-real');
        const kpiUD = document.getElementById('kpi-unidades-desvio');
        const kpiUDP = document.getElementById('kpi-unidades-desvio-pct');
        const kpiFD = document.getElementById('kpi-facturacion-desvio');
        const kpiFDP = document.getElementById('kpi-facturacion-desvio-pct');

        if (kpiUP) kpiUP.textContent = FormatoUtils.formatearNumero(totalUnidadesP);
        if (kpiUR) kpiUR.textContent = FormatoUtils.formatearNumero(totalUnidadesR);
        
        if (kpiUD) {
            kpiUD.textContent = (totalUnidadesDesvio > 0 ? '+' : '') + FormatoUtils.formatearNumero(totalUnidadesDesvio);
            kpiUDP.textContent = (totalUnidadesDesvio > 0 ? '+' : '') + totalUnidadesDesvioPct.toFixed(1) + '%';

            const card = document.getElementById('card-kpi-desvio-unidades');
            const icon = document.getElementById('icon-kpi-desvio-unidades');
            if (card && icon) {
                if (totalUnidadesDesvio >= 0) {
                    card.style.background = 'linear-gradient(135deg, #d4fc79 0%, #96e6a1 100%)';
                    card.className = 'card border-0 shadow-sm p-3 h-100 text-dark';
                    icon.className = 'fas fa-arrow-up text-success';
                } else {
                    card.style.background = 'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)';
                    card.className = 'card border-0 shadow-sm p-3 h-100 text-dark';
                    icon.className = 'fas fa-arrow-down text-danger';
                }
            }
        }

        if (kpiFD) {
            const valFactDesv = esARS ? (totalFactDesvio * tasaPromedio) : totalFactDesvio;
            kpiFD.textContent = (valFactDesv > 0 ? '+' : '') + FormatoUtils.formatearNumero(Math.round(valFactDesv));
            kpiFDP.textContent = (valFactDesv > 0 ? '+' : '') + totalFactDesvioPct.toFixed(1) + '%';

            const card = document.getElementById('card-kpi-desvio-facturacion');
            const icon = document.getElementById('icon-kpi-desvio-facturacion');
            if (card && icon) {
                if (totalFactDesvio >= 0) {
                    card.style.background = 'linear-gradient(135deg, #d4fc79 0%, #96e6a1 100%)';
                    card.className = 'card border-0 shadow-sm p-3 h-100 text-dark';
                    icon.className = 'fas fa-arrow-up text-success';
                } else {
                    card.style.background = 'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)';
                    card.className = 'card border-0 shadow-sm p-3 h-100 text-dark';
                    icon.className = 'fas fa-arrow-down text-danger';
                }
            }
        }

        // Renderizar Filas
        let html = '';
        DistribucionManager.datosDesvios.forEach((d, idx) => {
            const uP = d.unidades_presupuesto || 0;
            const uR = d.unidades_real || 0;
            const uDesv = uR - uP;
            const uDesvPct = uP > 0 ? (uDesv / uP) * 100 : 0;

            const fP = esARS ? (d.facturacion_usd_presupuesto * tasaPromedio) : d.facturacion_usd_presupuesto;
            const fR = esARS ? (d.facturacion_usd_real * tasaPromedio) : d.facturacion_usd_real;
            const fDesv = fR - fP;
            const fDesvPct = fP > 0 ? (fDesv / fP) * 100 : 0;

            const colorU = uDesv >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold';
            const colorF = fDesv >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold';

            const representsLocalesPropios = d.canal === 'LOCALES PROPIOS';
            const collapseBtn = representsLocalesPropios
                ? `<button class="btn btn-link btn-xs p-0 me-2 text-dark" onclick="DistribucionManager.toggleDesgloseSucursalesPaso3(this)">
                       <i class="fas fa-chevron-right"></i>
                   </button>`
                : '';

            const activeRowStyle = DistribucionManager.filtroCanalActivoPaso3 === d.canal ? 'table-primary font-weight-bold' : '';

            html += `
                <tr class="main-canal-row ${activeRowStyle}" style="cursor: pointer;" onclick="DistribucionManager.filtrarCanalPaso3('${d.canal}', event)">
                    <td>
                        <div class="d-flex align-items-center">
                            ${collapseBtn}
                            <span class="fw-bold">${d.canal}</span>
                        </div>
                    </td>
                    <td class="text-end">${FormatoUtils.formatearNumero(uP)}</td>
                    <td class="text-end">${FormatoUtils.formatearNumero(uR)}</td>
                    <td class="text-end ${colorU}">${uDesv >= 0 ? '+' : ''}${FormatoUtils.formatearNumero(uDesv)}</td>
                    <td class="text-end ${colorU}">${uDesv >= 0 ? '+' : ''}${uDesvPct.toFixed(1)}%</td>
                    <td class="text-end">${FormatoUtils.formatearNumero(Math.round(fP))}</td>
                    <td class="text-end">${FormatoUtils.formatearNumero(Math.round(fR))}</td>
                    <td class="text-end ${colorF}">${fDesv >= 0 ? '+' : ''}${FormatoUtils.formatearNumero(Math.round(fDesv))}</td>
                </tr>
            `;

            // Agregar filas del desglose de sucursales si es Locales Propios
            if (representsLocalesPropios && d.sucursales && d.sucursales.length > 0) {
                d.sucursales.forEach(suc => {
                    const sUP = suc.unidades_presupuesto || 0;
                    const sUR = suc.unidades_real || 0;
                    const sUDesv = sUR - sUP;
                    const sUDesvPct = sUP > 0 ? (sUDesv / sUP) * 100 : 0;

                    const sFP = esARS ? (suc.facturacion_usd_presupuesto * tasaPromedio) : suc.facturacion_usd_presupuesto;
                    const sFR = esARS ? (suc.facturacion_usd_real * tasaPromedio) : suc.facturacion_usd_real;
                    const sFDesv = sFR - sFP;

                    const sColorU = sUDesv >= 0 ? 'text-success' : 'text-danger';
                    const sColorF = sFDesv >= 0 ? 'text-success' : 'text-danger';

                    html += `
                        <tr class="child-sucursal-row d-none bg-light text-muted" style="font-size:0.82rem;">
                            <td class="ps-5">
                                <i class="fas fa-store me-2 text-secondary"></i>${suc.sucursal}
                            </td>
                            <td class="text-end">${FormatoUtils.formatearNumero(sUP)}</td>
                            <td class="text-end">${FormatoUtils.formatearNumero(sUR)}</td>
                            <td class="text-end ${sColorU}">${sUDesv >= 0 ? '+' : ''}${FormatoUtils.formatearNumero(sUDesv)}</td>
                            <td class="text-end ${sColorU}">${sUDesv >= 0 ? '+' : ''}${sUDesvPct.toFixed(1)}%</td>
                            <td class="text-end">${FormatoUtils.formatearNumero(Math.round(sFP))}</td>
                            <td class="text-end">${FormatoUtils.formatearNumero(Math.round(sFR))}</td>
                            <td class="text-end ${sColorF}">${sFDesv >= 0 ? '+' : ''}${FormatoUtils.formatearNumero(Math.round(sFDesv))}</td>
                        </tr>
                    `;
                });
            }
        });

        tbody.innerHTML = html;
    }

    static toggleDesgloseSucursalesPaso3(btn) {
        // Prevenir filtrado de canal al colapsar/expandir sucursales
        if (event) event.stopPropagation();

        const icon = btn.querySelector('i');
        const mainRow = btn.closest('tr');
        
        // Buscar todas las filas hijas consecutivas hasta encontrar la siguiente main row
        let sibling = mainRow.nextElementSibling;
        const isCollapsed = icon.classList.contains('fa-chevron-right');

        if (isCollapsed) {
            icon.className = 'fas fa-chevron-down';
            while (sibling && sibling.classList.contains('child-sucursal-row')) {
                sibling.classList.remove('d-none');
                sibling = sibling.nextElementSibling;
            }
        } else {
            icon.className = 'fas fa-chevron-right';
            while (sibling && sibling.classList.contains('child-sucursal-row')) {
                sibling.classList.add('d-none');
                sibling = sibling.nextElementSibling;
            }
        }
    }

    static filtrarCanalPaso3(canal, event) {
        if (event) event.stopPropagation();

        const btnLimpiar = document.getElementById('btn-limpiar-filtro-canal-paso3');

        if (DistribucionManager.filtroCanalActivoPaso3 === canal) {
            DistribucionManager.filtroCanalActivoPaso3 = null;
            if (btnLimpiar) btnLimpiar.style.display = 'none';
        } else {
            DistribucionManager.filtroCanalActivoPaso3 = canal;
            if (btnLimpiar) btnLimpiar.style.display = 'inline-block';
        }

        DistribucionManager.renderizarDesviosPaso3();
        DistribucionManager.inicializarGraficosPaso3();
    }

    static limpiarFiltroCanalPaso3() {
        DistribucionManager.filtroCanalActivoPaso3 = null;
        const btnLimpiar = document.getElementById('btn-limpiar-filtro-canal-paso3');
        if (btnLimpiar) btnLimpiar.style.display = 'none';
        
        DistribucionManager.renderizarDesviosPaso3();
        DistribucionManager.inicializarGraficosPaso3();
    }

    static inicializarGraficosPaso3() {
        const esARS = DistribucionManager.modoMoneda === 'ARS';
        const tasaProm = esARS ? DistribucionManager.tasaPromedioPeriodo() : 1.0;
        const labelMoneda = esARS ? 'ARS' : 'USD';

        // 1. Gráfico de Barras: Presupuesto vs Real por Canal
        const ctxCanales = document.getElementById('chart-desvio-canales');
        if (ctxCanales) {
            if (DistribucionManager.chartCanalesInstancia) {
                DistribucionManager.chartCanalesInstancia.destroy();
            }

            const labels = [];
            const dataP = [];
            const dataR = [];

            DistribucionManager.datosDesvios.forEach(d => {
                labels.push(d.canal);
                const valP = esARS ? (d.facturacion_usd_presupuesto * tasaProm) : d.facturacion_usd_presupuesto;
                const valR = esARS ? (d.facturacion_usd_real * tasaProm) : d.facturacion_usd_real;
                dataP.push(Math.round(valP));
                dataR.push(Math.round(valR));
            });

            DistribucionManager.chartCanalesInstancia = new Chart(ctxCanales, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: `Presupuestado (${labelMoneda})`,
                            data: dataP,
                            backgroundColor: '#2a5298',
                            borderRadius: 4
                        },
                        {
                            label: `Realidad (${labelMoneda})`,
                            data: dataR,
                            backgroundColor: '#96e6a1',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return FormatoUtils.formatearNumero(value);
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + FormatoUtils.formatearNumero(context.raw);
                                }
                            }
                        }
                    }
                }
            });
        }

        // 2. Gráfico Mensual Comparativo (Evolución)
        const ctxMensual = document.getElementById('chart-desvio-mensual');
        const badgeEvol = document.getElementById('chart-evolucion-badge');
        if (ctxMensual) {
            if (DistribucionManager.chartMensualInstancia) {
                DistribucionManager.chartMensualInstancia.destroy();
            }

            const dataP = DistribucionManager.mesesDesvios.map(() => 0.0);
            const dataR = DistribucionManager.mesesDesvios.map(() => 0.0);

            if (DistribucionManager.filtroCanalActivoPaso3) {
                if (badgeEvol) badgeEvol.textContent = DistribucionManager.filtroCanalActivoPaso3;
                const canalData = DistribucionManager.datosDesvios.find(d => d.canal === DistribucionManager.filtroCanalActivoPaso3);
                if (canalData && canalData.meses) {
                    DistribucionManager.mesesDesvios.forEach((mLabel, mIdx) => {
                        const mInfo = canalData.meses[mLabel] || { facturacion_usd_presupuesto: 0.0, facturacion_usd_real: 0.0 };
                        dataP[mIdx] = Math.round(esARS ? (mInfo.facturacion_usd_presupuesto * tasaProm) : mInfo.facturacion_usd_presupuesto);
                        dataR[mIdx] = Math.round(esARS ? (mInfo.facturacion_usd_real * tasaProm) : mInfo.facturacion_usd_real);
                    });
                }
            } else {
                if (badgeEvol) badgeEvol.textContent = 'Empresa';
                // Sumar todos los canales para cada mes
                DistribucionManager.mesesDesvios.forEach((mLabel, mIdx) => {
                    let sumP = 0.0;
                    let sumR = 0.0;
                    DistribucionManager.datosDesvios.forEach(d => {
                        const mInfo = d.meses ? d.meses[mLabel] : null;
                        if (mInfo) {
                            sumP += mInfo.facturacion_usd_presupuesto || 0.0;
                            sumR += mInfo.facturacion_usd_real || 0.0;
                        }
                    });
                    dataP[mIdx] = Math.round(esARS ? (sumP * tasaProm) : sumP);
                    dataR[mIdx] = Math.round(esARS ? (sumR * tasaProm) : sumR);
                });
            }

            DistribucionManager.chartMensualInstancia = new Chart(ctxMensual, {
                type: 'line',
                data: {
                    labels: DistribucionManager.mesesDesvios,
                    datasets: [
                        {
                            label: `Presupuestado (${labelMoneda})`,
                            data: dataP,
                            borderColor: '#2a5298',
                            backgroundColor: 'rgba(42, 82, 152, 0.1)',
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: `Realidad (${labelMoneda})`,
                            data: dataR,
                            borderColor: '#2ecc71',
                            backgroundColor: 'rgba(46, 204, 113, 0.1)',
                            fill: true,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return FormatoUtils.formatearNumero(value);
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + FormatoUtils.formatearNumero(context.raw);
                                }
                            }
                        }
                    }
                }
            });
        }
    }
}
