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

            if (target.id === 'btn-limpiar-historial') {
                HistorialManager.limpiarFiltros();
            }

            if (target.dataset.accionVersion === 'marcar-oficial') {
                HistorialManager.marcarOficial(parseInt(target.dataset.idCabecera, 10));
            }

            if (target.dataset.accionVersion === 'desmarcar-oficial') {
                HistorialManager.desmarcarOficial(parseInt(target.dataset.idCabecera, 10));
            }

            if (target.dataset.accionVersion === 'eliminar') {
                HistorialManager.eliminarVersion(
                    target.dataset.idCabecera ? parseInt(target.dataset.idCabecera, 10) : null,
                    target.dataset.nombre
                );
            }

            if (target.dataset.accionVersion === 'ver') {
                HistorialManager.verSoloEstaVersion(target.dataset.clave);
            }
        });

        // Cambiar el filtro de versión busca directamente: es el uso principal
        // de la solapa (mirar un presupuesto guardado), no hace falta el botón.
        document.body.addEventListener('change', (event) => {
            if (event.target && event.target.id === 'filtro-version-historial') {
                HistorialManager.buscarHistorial();
            }
        });
    }

    /** Deja el historial sin filtros y vuelve a buscar. */
    static limpiarFiltros() {
        ['search-historial', 'filtro-rubro-historial', 'filtro-categoria-historial',
         'filtro-fecha-desde-historial', 'filtro-fecha-hasta-historial',
         'filtro-version-historial'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        HistorialManager.buscarHistorial();
    }

    /** Filtra el detalle por una versión y baja a la tabla. */
    static verSoloEstaVersion(clave) {
        const select = document.getElementById('filtro-version-historial');
        if (!select) return;
        select.value = clave;
        HistorialManager.buscarHistorial();
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
            const completa = v.es_completa !== null && parseInt(v.es_completa, 10) === 1;
            const clave = v.id ? `id:${v.id}` : `nombre:${v.nombre_presupuesto}`;
            const nombre = (v.nombre_presupuesto || '').replace(/"/g, '&quot;');

            // Una parcial no puede ser oficial: en vez de dejar el botón y que el
            // servidor lo rechace, se explica por qué no se puede.
            let oficialCel;
            if (oficial) {
                // La oficial ya no se puede eliminar, así que tiene que haber una forma
                // explícita de desmarcarla: si no, queda atrapada sin salida.
                oficialCel = `<span class="text-success small d-block"><i class="fas fa-flag me-1"></i>Vigente</span>
                              <button class="btn btn-outline-secondary btn-sm mt-1"
                                      data-accion-version="desmarcar-oficial" data-id-cabecera="${v.id}"
                                      title="Dejar esta temporada sin versión vigente">
                                <i class="fas fa-flag-checkered me-1"></i>Desmarcar
                              </button>`;
            } else if (v.es_completa === null) {
                oficialCel = '<span class="text-muted small" title="Hay que correr los scripts de presupuestos/sql/">—</span>';
            } else if (completa) {
                oficialCel = `<button class="btn btn-outline-success btn-sm"
                                      data-accion-version="marcar-oficial" data-id-cabecera="${v.id}"
                                      title="Marcar como la versión vigente de esta temporada">
                                <i class="fas fa-flag"></i>
                              </button>`;
            } else {
                oficialCel = '<span class="text-muted small" title="Solo una versión completa puede ser oficial">—</span>';
            }

            let alcance;
            if (v.es_completa === null) {
                alcance = `<span class="badge bg-light text-dark">${v.filas_guardadas || v.filas_detalle} filas</span>`;
            } else if (completa) {
                alcance = '<span class="badge bg-primary">Completa</span>';
            } else {
                alcance = `<span class="badge bg-warning text-dark">Parcial ${v.filas_guardadas}/${v.filas_totales || '?'}</span>`;
            }

            return `
                <tr class="${oficial ? 'table-success' : ''}">
                    <td class="small">${FormatoUtils.formatearFechaHora(v.fecha_guardado)}</td>
                    <td class="small">${v.nombre_presupuesto || ''}</td>
                    <td><span class="badge bg-secondary">${v.solapa || ''}</span></td>
                    <td><span class="badge bg-info text-dark"
                              title="${v.temporada_objetivo_desde || ''} a ${v.temporada_objetivo_hasta || ''}">
                            ${v.temporada_objetivo || 's/d'}</span></td>
                    <td class="text-center small">${alcance}</td>
                    <td class="text-center">${oficialCel}</td>
                    <td class="small">${v.oficial_usuario || (oficial ? 'sin usuario' : '')}
                        ${oficial && v.oficial_fecha ? '<br><span class="text-muted">' + FormatoUtils.formatearFechaHora(v.oficial_fecha) + '</span>' : ''}</td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-outline-primary btn-sm me-1"
                                data-accion-version="ver" data-clave="${clave}"
                                title="Ver solo este presupuesto en el detalle de abajo">
                            <i class="fas fa-filter"></i>
                        </button>
                        <button class="btn btn-outline-danger btn-sm"
                                data-accion-version="eliminar"
                                data-id-cabecera="${v.id || ''}" data-nombre="${nombre}"
                                title="Eliminar esta versión">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
        }).join('');

        if (contador) contador.textContent = `${versiones.length} versiones`;
        HistorialManager.poblarFiltroVersiones(versiones);
    }

    /**
     * Llena el select de versiones del filtro, conservando lo que estuviera
     * elegido para que recargar la lista no descarte el filtro activo.
     */
    static poblarFiltroVersiones(versiones) {
        const select = document.getElementById('filtro-version-historial');
        if (!select) return;

        const elegido = select.value;
        select.innerHTML = '<option value="">Todos los presupuestos guardados</option>'
            + (versiones || []).map(v => {
                const clave = v.id ? `id:${v.id}` : `nombre:${v.nombre_presupuesto}`;
                const fecha = FormatoUtils.formatearFechaHora(v.fecha_guardado);
                const oficial = parseInt(v.es_oficial, 10) === 1 ? ' ★' : '';
                return `<option value="${clave}">${v.nombre_presupuesto} — ${fecha}${oficial}</option>`;
            }).join('');

        if (elegido && select.querySelector(`option[value="${CSS.escape(elegido)}"]`)) {
            select.value = elegido;
        }
    }

    /**
     * Elimina una versión guardada.
     *
     * Dos llamadas: la primera no borra y devuelve cuántas filas se llevaría y si
     * es la oficial, para confirmarlo con el dato real del servidor.
     */
    /**
     * Desmarca la versión oficial, dejando la temporada sin vigente.
     *
     * Es una decisión fuerte —el consumidor externo deja de encontrar presupuesto para
     * esa temporada— así que va en dos pasos y lo dice sin rodeos. Existe porque la
     * oficial ya no se puede eliminar: sin esto quedaría atrapada para siempre.
     */
    static async desmarcarOficial(idCabecera) {
        if (!idCabecera) return;

        UIUtils.mostrarLoading(true);
        try {
            const previa = await APIClient.llamarAPI('desmarcar-oficial', {}, 'POST', {
                id_cabecera: idCabecera
            });

            if (!previa.success) {
                throw new Error(previa.message || 'No se pudo desmarcar la versión.');
            }
            if (previa.sin_cambios) {
                UIUtils.mostrarAlerta(previa.message, 'info');
                return;
            }

            UIUtils.mostrarLoading(false);

            const confirmado = await UIUtils.confirmarAccion(
                'Desmarcar versión oficial',
                `<p><strong>${previa.version.nombre}</strong> va a dejar de ser la versión
                    oficial de <strong>${previa.version.temporada_objetivo}</strong>.</p>
                 <div class="alert alert-warning p-2 mb-0">
                   <i class="fas fa-exclamation-triangle me-1"></i>
                   Esa temporada queda <strong>sin ninguna versión vigente</strong>: los
                   sistemas que proyectan las compras del exterior van a dejar de
                   encontrarla. Queda registrado en el historial de oficiales.
                 </div>`,
                'warning'
            );

            if (!confirmado) return;

            UIUtils.mostrarLoading(true);
            const respuesta = await APIClient.llamarAPI('desmarcar-oficial', {}, 'POST', {
                id_cabecera: idCabecera,
                confirmado: true
            });

            if (!respuesta.success) {
                throw new Error(respuesta.message || 'No se pudo desmarcar la versión.');
            }

            UIUtils.mostrarAlerta(respuesta.message, 'warning');
            await HistorialManager.cargarVersiones();

        } catch (error) {
            console.error('Error al desmarcar la versión oficial:', error);
            UIUtils.mostrarAlerta(`Error: ${error.message}`, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    static async eliminarVersion(idCabecera, nombre) {
        UIUtils.mostrarLoading(true);
        try {
            const cuerpo = { id_cabecera: idCabecera || null, nombre_presupuesto: nombre || null };
            const previa = await APIClient.llamarAPI('eliminar-version-presupuesto', {}, 'POST', cuerpo);

            // El servidor puede negarse a borrar por dos motivos distintos, y cada uno
            // tiene una salida distinta: la oficial se desmarca primero, la que tiene
            // historial de marcado no se borra. Se muestran como aviso y no como error
            // rojo genérico, porque no es una falla sino una regla.
            if (!previa.success && previa.bloqueada) {
                UIUtils.mostrarLoading(false);
                await UIUtils.informar(
                    previa.bloqueada === 'oficial' ? 'No se puede eliminar la versión oficial'
                                                   : 'No se puede eliminar: tiene historial',
                    `<p>${previa.message}</p>`
                    + (previa.bloqueada === 'oficial'
                        ? '<p class="mb-0 small text-muted">Usá el botón <em>Desmarcar</em> de la '
                          + 'columna Oficial y volvé a intentarlo.</p>'
                        : '<p class="mb-0 small text-muted">El historial de quién marcó qué versión '
                          + 'como oficial es auditoría: no se borra.</p>'),
                    'warning'
                );
                return;
            }

            if (!previa.success) {
                throw new Error(previa.message || 'No se pudo eliminar la versión.');
            }

            UIUtils.mostrarLoading(false);

            const v = previa.version;

            const confirmado = await UIUtils.confirmarAccion(
                'Eliminar versión guardada',
                `<p>Se va a borrar <strong>${v.nombre}</strong>:
                    <strong>${v.filas}</strong> fila${v.filas === 1 ? '' : 's'} de detalle
                    ${v.filas_tramo ? ` y <strong>${v.filas_tramo}</strong> de compra por tramo` : ''}.</p>
                 <p class="mb-0 text-danger"><strong>No se puede deshacer.</strong></p>`,
                'danger'
            );

            if (!confirmado) return;

            UIUtils.mostrarLoading(true);
            const respuesta = await APIClient.llamarAPI('eliminar-version-presupuesto', {}, 'POST',
                Object.assign({ confirmado: true }, cuerpo));

            if (!respuesta.success) {
                throw new Error(respuesta.message || 'No se pudo eliminar la versión.');
            }

            UIUtils.mostrarAlerta(respuesta.message, 'success');

            // Si el filtro apuntaba a la versión borrada, se limpia antes de
            // recargar: si no, la tabla quedaría vacía sin explicación.
            const select = document.getElementById('filtro-version-historial');
            const clave = idCabecera ? `id:${idCabecera}` : `nombre:${nombre}`;
            if (select && select.value === clave) select.value = '';

            await HistorialManager.cargarVersiones();
            await HistorialManager.buscarHistorial();

        } catch (error) {
            console.error('Error al eliminar la versión:', error);
            UIUtils.mostrarAlerta(`Error: ${error.message}`, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }

    /**
     * Marca una versión como oficial.
     *
     * Dos llamadas: la primera no escribe y devuelve cuál versión se va a
     * desmarcar, para poder confirmarlo con el dato real del servidor y no con
     * lo que el navegador tenía en pantalla.
     */
    /**
     * Aviso de que esta versión contradice a otra oficial vigente.
     *
     * Dos versiones oficiales de temporadas distintas describen tramos que se pisan:
     * la de VER 27-28 también trae el tramo INV 27, que es el objetivo de la otra.
     * Calculadas el mismo día y sin tocar nada dan idéntico; si no dan, lo más común
     * es que se haya editado un índice en una sola de las dos solapas.
     *
     * Se avisa, no se bloquea: puede ser deliberado. Lo que no puede pasar es que el
     * cashflow reciba dos números para la misma temporada sin que nadie se entere.
     */
    static avisoDiscrepancias(discrepancias) {
        if (!discrepancias || !discrepancias.hay_diferencias) return '';

        const filas = discrepancias.detalle.map(d => {
            // Qué filas, no solo cuántas: sin el rubro concreto no hay forma de saber
            // si la diferencia es la corrección que alguien hizo a propósito o un olvido.
            const rubros = (d.filas || []).map(f => `
                <tr class="small">
                  <td class="ps-4 text-muted" colspan="2">${f.rubro || ''} · ${f.categoria_padre || ''}</td>
                  <td></td>
                  <td class="text-end text-muted">${FormatoUtils.formatearNumero(f.compra_nueva)}</td>
                  <td class="text-end text-muted">${FormatoUtils.formatearNumero(f.compra_otra)}</td>
                  <td class="text-end text-muted">${FormatoUtils.formatearNumero(f.diferencia)}</td>
                </tr>`).join('');

            const hayMas = d.filas_distintas > (d.filas || []).length
                ? `<tr class="small"><td class="ps-4 fst-italic text-muted" colspan="6">
                     y ${d.filas_distintas - d.filas.length} rubro(s) más</td></tr>`
                : '';

            return `
                <tr>
                  <td class="small fw-bold">${d.temporada}</td>
                  <td class="small">${d.nombre_otra}<br>
                      <span class="text-muted">oficial de ${d.objetivo_otra}</span></td>
                  <td class="text-end small">${d.filas_distintas} de ${d.filas_comparadas}</td>
                  <td class="text-end small">${FormatoUtils.formatearNumero(d.total_nueva)}</td>
                  <td class="text-end small">${FormatoUtils.formatearNumero(d.total_otra)}</td>
                  <td class="text-end small fw-bold">${FormatoUtils.formatearNumero(d.unidades)}</td>
                </tr>${rubros}${hayMas}`;
        }).join('');

        return `
            <div class="alert alert-warning mt-3 mb-0">
              <div class="fw-bold mb-1">
                <i class="fas fa-exclamation-triangle me-1"></i>
                Esta versión no coincide con otra oficial vigente
              </div>
              <p class="small mb-2">${discrepancias.explicacion}</p>
              <div class="table-responsive">
                <table class="table table-sm table-borderless mb-0">
                  <thead>
                    <tr class="small text-muted">
                      <th>Tramo</th><th>Contra</th><th class="text-end">Filas que difieren</th>
                      <th class="text-end">Esta</th><th class="text-end">La otra</th>
                      <th class="text-end">Diferencia</th>
                    </tr>
                  </thead>
                  <tbody>${filas}</tbody>
                </table>
              </div>
            </div>`;
    }

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
                 <p class="mb-0">Pasa a ser oficial: <strong>${previa.version.nombre}</strong></p>
                 ${HistorialManager.avisoDiscrepancias(previa.discrepancias)}`,
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
            const valor = (id) => (document.getElementById(id) || {}).value || '';

            // El select de versión guarda "id:<n>" cuando hay cabecera y
            // "nombre:<texto>" cuando no, porque las versiones viejas solo se
            // pueden identificar por nombre.
            const version = valor('filtro-version-historial');
            const filtros = {
                termino: valor('search-historial'),
                rubro: valor('filtro-rubro-historial'),
                categoria: valor('filtro-categoria-historial'),
                fecha_desde: valor('filtro-fecha-desde-historial'),
                fecha_hasta: valor('filtro-fecha-hasta-historial'),
                id_cabecera: version.startsWith('id:') ? version.slice(3) : null,
                nombre_presupuesto: version.startsWith('nombre:') ? version.slice(7) : null
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
