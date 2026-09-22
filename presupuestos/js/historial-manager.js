// Gestor para guardar y ver el historial de presupuestos
// Archivo: presupuestos/js/historial-manager.js

class HistorialManager {

    /**
     * Inicializa el gestor, añadiendo event listeners delegados.
     */
    static init() {
        document.body.addEventListener('click', (event) => {
            const target = event.target.closest('button');
            if (!target) return;

            if (target.id === 'btn-guardar-verano' || target.id === 'btn-guardar-invierno') {
                const temporada = target.dataset.temporada;
                if (temporada) {
                    HistorialManager.guardarPresupuesto(temporada);
                }
            }

            if (target.id === 'btn-buscar-historial') {
                HistorialManager.buscarHistorial();
            }

            if (target.id === 'btn-cargar-versiones') {
                HistorialManager.cargarVersiones();
            }

            if (target.dataset.accionVersion === 'marcar-oficial') {
                HistorialManager.marcarOficial(parseInt(target.dataset.idCabecera, 10));
            }
        });
    }

    /**
     * Lista las versiones guardadas (cabeceras), que es donde se marca la oficial.
     */
    static async cargarVersiones() {
        UIUtils.mostrarLoading(true);
        try {
            const respuesta = await APIClient.llamarAPI('versiones-presupuesto', {}, 'GET');
            if (!respuesta.success) {
                throw new Error(respuesta.message || 'No se pudieron leer las versiones.');
            }
            HistorialManager.renderizarVersiones(respuesta.data, respuesta.sin_cabecera, respuesta.message);

            // Si el panel está colapsado, abrirlo: cargar y no ver nada sería raro.
            const panel = document.getElementById('panel-versiones');
            if (panel && !panel.classList.contains('show')) {
                bootstrap.Collapse.getOrCreateInstance(panel).show();
            }
        } catch (error) {
            console.error('Error al listar versiones:', error);
            UIUtils.mostrarAlerta(`Error: ${error.message}`, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    static renderizarVersiones(versiones, sinCabecera, mensaje) {
        const tbody = document.getElementById('tbody-versiones');
        const contador = document.getElementById('count-versiones');
        if (!tbody) return;

        if (sinCabecera) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-warning py-3">
                <i class="fas fa-exclamation-triangle me-1"></i>${mensaje}</td></tr>`;
            if (contador) contador.textContent = '0 versiones';
            return;
        }

        if (!versiones || versiones.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-3">
                No hay versiones guardadas.</td></tr>`;
            if (contador) contador.textContent = '0 versiones';
            return;
        }

        tbody.innerHTML = versiones.map(v => {
            const oficial = parseInt(v.es_oficial, 10) === 1;
            const completa = parseInt(v.es_completa, 10) === 1;

            // Una parcial no puede ser oficial: en vez de dejar el botón y que el
            // servidor lo rechace, se explica por qué no se puede.
            const accion = oficial
                ? '<span class="text-success small"><i class="fas fa-check me-1"></i>Vigente</span>'
                : (completa
                    ? `<button class="btn btn-outline-success btn-sm"
                               data-accion-version="marcar-oficial" data-id-cabecera="${v.id}">
                         <i class="fas fa-flag me-1"></i>Marcar oficial
                       </button>`
                    : '<span class="text-muted small" title="Solo una versión completa puede ser oficial">Parcial</span>');

            return `
                <tr class="${oficial ? 'table-success' : ''}">
                    <td class="small">${(v.fecha_guardado || '').substring(0, 16)}</td>
                    <td class="small">${v.nombre_presupuesto || ''}</td>
                    <td><span class="badge bg-secondary">${v.solapa || ''}</span></td>
                    <td><span class="badge bg-info text-dark"
                              title="${v.temporada_objetivo_desde || ''} a ${v.temporada_objetivo_hasta || ''}">
                            ${v.temporada_objetivo || 's/d'}</span></td>
                    <td class="text-center small">
                        ${completa
                            ? '<span class="badge bg-primary">Completa</span>'
                            : `<span class="badge bg-warning text-dark">Parcial ${v.filas_guardadas}/${v.filas_totales || '?'}</span>`}
                    </td>
                    <td class="text-center">${oficial ? '<i class="fas fa-flag text-success"></i>' : ''}</td>
                    <td class="small">${v.oficial_usuario || (oficial ? 'sin usuario' : '')}
                        ${oficial && v.oficial_fecha ? '<br><span class="text-muted">' + v.oficial_fecha.substring(0, 16) + '</span>' : ''}</td>
                    <td class="text-end">${accion}</td>
                </tr>`;
        }).join('');

        if (contador) contador.textContent = `${versiones.length} versiones`;
    }

    /**
     * Marca una versión como oficial.
     *
     * Dos llamadas: la primera no escribe y devuelve cuál versión se va a
     * desmarcar, para poder confirmarlo con el dato real del servidor y no con
     * lo que el navegador tenía en pantalla.
     */
    static async marcarOficial(idCabecera) {
        if (!idCabecera) return;

        UIUtils.mostrarLoading(true);
        try {
            const previa = await APIClient.llamarAPI('marcar-oficial', {}, 'POST', {
                id_cabecera: idCabecera
            });

            if (!previa.success) {
                throw new Error(previa.message || 'No se pudo marcar la versión.');
            }
            if (previa.sin_cambios) {
                UIUtils.mostrarAlerta(previa.message, 'info');
                return;
            }

            UIUtils.mostrarLoading(false);

            const detalle = previa.reemplaza
                ? `Se va a desmarcar <strong>${previa.reemplaza.nombre}</strong> `
                  + `(guardada el ${previa.reemplaza.fecha_guardado}), que hoy es la oficial de `
                  + `<strong>${previa.version.temporada_objetivo}</strong>.`
                : `No hay ninguna versión oficial de <strong>${previa.version.temporada_objetivo}</strong> todavía.`;

            const confirmado = await UIUtils.confirmarAccion(
                'Marcar versión oficial',
                `<p>${detalle}</p>
                 <p class="mb-0">Pasa a ser oficial: <strong>${previa.version.nombre}</strong></p>`,
                'success'
            );

            if (!confirmado) return;

            UIUtils.mostrarLoading(true);
            const respuesta = await APIClient.llamarAPI('marcar-oficial', {}, 'POST', {
                id_cabecera: idCabecera,
                confirmado: true
            });

            if (!respuesta.success) {
                throw new Error(respuesta.message || 'No se pudo marcar la versión.');
            }

            UIUtils.mostrarAlerta(respuesta.message, 'success');
            await HistorialManager.cargarVersiones();

        } catch (error) {
            console.error('Error al marcar versión oficial:', error);
            UIUtils.mostrarAlerta(`Error: ${error.message}`, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    /**
     * Guarda el estado actual del presupuesto proyectado.
     * @param {string} temporada La temporada a guardar ('verano' o 'invierno').
     */
    /**
     * Guarda una versión del presupuesto proyectado.
     *
     * POR DEFECTO SE GUARDA EL PRESUPUESTO COMPLETO, ignorando los filtros de la
     * vista. Antes se guardaba siempre lo filtrado y nada lo decía: las cinco
     * versiones que existen en la base son parciales sin haberlo elegido, y una
     * parcial no sirve para que el cashflow proyecte la compra de una temporada.
     *
     * El guardado parcial se mantiene como opción explícita porque está
     * documentado en la ayuda como una forma de sacar una foto de lo que se está
     * mirando, pero ahora hay que elegirlo, queda marcado como parcial y no puede
     * marcarse como oficial.
     */
    static async guardarPresupuesto(temporada) {
        if (!['verano', 'invierno'].includes(temporada)) {
            UIUtils.mostrarAlerta('Temporada no válida para guardar.', 'error');
            return;
        }

        try {
            const datosOriginales = window.presupuestoApp.getDatos(temporada);
            if (!datosOriginales || datosOriginales.length === 0) {
                throw new Error('No hay datos cargados para guardar.');
            }

            const datosFiltrados = FiltrosManager.filtrarDatos(datosOriginales);
            const hayFiltros = datosFiltrados.length < datosOriginales.length;

            let datosAGuardar = datosOriginales;

            if (hayFiltros) {
                const opcion = await HistorialManager.preguntarAlcance(
                    datosOriginales.length, datosFiltrados.length);

                if (opcion === 'cancelar') return;

                if (opcion === 'filtrado') {
                    if (datosFiltrados.length === 0) {
                        throw new Error('No hay filas visibles para guardar.');
                    }
                    datosAGuardar = datosFiltrados;
                }
            }

            UIUtils.mostrarLoading(true);

            const ahora = new Date();
            const pad = (num) => num.toString().padStart(2, '0');
            const fecha = `${ahora.getFullYear()}-${pad(ahora.getMonth() + 1)}-${pad(ahora.getDate())}`;
            const hora = `${pad(ahora.getHours())}-${pad(ahora.getMinutes())}`;
            const nombrePresupuesto = `Presupuesto_${fecha}_${hora}_${temporada}`;

            const filasParaGuardar = datosAGuardar.map(item => ({
                rubro: item.RUBRO,
                categoria_padre: item.CATEGORIA_PADRE,
                stock_proyectado: item.STOCK_PROYECTADO || 0,
                indice_variacion_original: TablaRendererUtils.obtenerIndiceOriginal(item),
                indice_verano_variacion: parseFloat(item.INDICE_VARIACION || 1.0),
                venta_verano_anterior: TablaRendererUtils.buscarVentaHistoricaCorrecta(item, 'VERANO'),
                venta_proyectada_verano: item.VENTA_PROY_VERANO || 0,
                indice_invierno_variacion: parseFloat(item.INDICE_VARIACION_INVIERNO || item.INDICE_VARIACION || 1.0),
                venta_invierno_anterior: TablaRendererUtils.buscarVentaHistoricaCorrecta(item, 'INVIERNO'),
                venta_proyectada_invierno: item.VENTA_PROY_INVIERNO || 0,
                compra_proyectada: item.COMPRA_PROYECTADA || 0,

                // Componentes del stock proyectado. Las OC pendientes entran al
                // stock, así que la compra proyectada es NETA de lo ya pedido:
                // sin guardarlas, la versión no se puede releer más adelante.
                cant_stock: item.CANT_STOCK,
                cant_stock_guardar: item.CANT_STOCK_GUARDAR,
                cant_pend_oc_verano: item.CANT_PEND_OC_VERANO,
                cant_pend_oc_invierno: item.CANT_PEND_OC_INVIERNO,
                cant_pend_oc_atemporal: item.CANT_PEND_OC_ATEMPORAL,
                stock_cobertura: item.STOCK_COBERTURA,

                // De qué temporada salió cada base. Varía por fila.
                temporada_base_verano: item.TEMPORADA_BASE_VERANO || null,
                temporada_base_invierno: item.TEMPORADA_BASE_INVIERNO || null
            }));

            // La fecha y el país los pone el servidor: la fecha del navegador es
            // contra la que se prorratean los restos de temporada y una máquina
            // desfasada dejaba una versión que no se podía reproducir.
            const payload = {
                nombre_presupuesto: nombrePresupuesto,
                temporada: temporada,
                filas: filasParaGuardar,
                filas_totales: datosOriginales.length
            };

            const respuesta = await APIClient.llamarAPI('guardar-presupuesto', {}, 'POST', payload);

            if (respuesta.success) {
                UIUtils.mostrarAlerta(respuesta.message, 'success');
            } else {
                throw new Error(respuesta.message || 'Ocurrió un error desconocido al guardar.');
            }

        } catch (error) {
            console.error('Error al guardar el presupuesto:', error);
            UIUtils.mostrarAlerta(`Error: ${error.message}`, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    /**
     * Pregunta si guardar todo o solo lo filtrado. Solo aparece si hay filtros:
     * sin filtros no hay nada que elegir.
     * @returns {Promise<'completo'|'filtrado'|'cancelar'>}
     */
    static preguntarAlcance(total, filtradas) {
        return new Promise((resolve) => {
            const id = 'modal-alcance-' + Date.now();
            const div = document.createElement('div');
            div.innerHTML = `
                <div class="modal fade" id="${id}" tabindex="-1">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-save me-2"></i>Qué se guarda</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <p>Tenés filtros aplicados: se ven <strong>${filtradas}</strong>
                           de <strong>${total}</strong> rubro/categoría.</p>
                        <div class="alert alert-warning small mb-0">
                          <i class="fas fa-exclamation-triangle me-1"></i>
                          Una versión parcial queda marcada como tal y <strong>no puede
                          marcarse como oficial</strong>.
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-accion="cancelar">Cancelar</button>
                        <button type="button" class="btn btn-outline-warning" data-accion="filtrado">
                          Solo lo filtrado (${filtradas})
                        </button>
                        <button type="button" class="btn btn-primary" data-accion="completo">
                          Guardar completo (${total})
                        </button>
                      </div>
                    </div>
                  </div>
                </div>`;
            document.body.appendChild(div);

            const el = document.getElementById(id);
            const modal = new bootstrap.Modal(el);
            let elegido = 'cancelar';

            el.querySelectorAll('[data-accion]').forEach(btn => {
                btn.addEventListener('click', () => {
                    elegido = btn.dataset.accion;
                    modal.hide();
                });
            });

            el.addEventListener('hidden.bs.modal', () => {
                div.remove();
                resolve(elegido);
            });

            modal.show();
        });
    }

    /**
     * Busca en el historial de presupuestos y renderiza los resultados.
     */
    static async buscarHistorial() {
        UIUtils.mostrarLoading(true);
        try {
            const filtros = {
                termino: document.getElementById('search-historial').value,
                rubro: document.getElementById('filtro-rubro-historial').value,
                categoria: document.getElementById('filtro-categoria-historial').value,
                fecha_desde: document.getElementById('filtro-fecha-desde-historial').value,
                fecha_hasta: document.getElementById('filtro-fecha-hasta-historial').value
            };

            const respuesta = await APIClient.llamarAPI('buscar-historial', {}, 'POST', filtros);

            if (respuesta.success) {
                TablaRenderer.renderizarTablaHistorial(respuesta.data);
                UIUtils.mostrarAlerta(`${respuesta.data.length} registros de historial encontrados.`, 'success');
            } else {
                throw new Error(respuesta.message || 'Error al buscar en el historial.');
            }

        } catch (error) {
            console.error('Error al buscar historial:', error);
            UIUtils.mostrarAlerta(`Error: ${error.message}`, 'error');
            const tbody = document.getElementById('tbody-historial');
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${error.message}</td></tr>`;
            }
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }
}

// Inicializar el gestor cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    HistorialManager.init();
});
