// Retomar una versión guardada en una solapa de compra.
// Archivo: presupuestos/js/retomar-version.js
//
// Retomar NO vuelve a mostrar la foto vieja: aplica los índices editables y las
// notas de esa versión sobre los datos de HOY (stock, ventas, OC) y el servidor
// recalcula venta, compra y tramos (compra-con-indices). Las filas que quedan con un
// índice distinto del original de hoy se marcan como editadas, igual que una
// edición a mano, y guardar después crea siempre una versión NUEVA que recuerda de
// cuál salió (id_version_origen).

const RetomarVersion = {

    // Versión retomada por solapa: { id, nombre, comentario, anteriorTe } o null.
    _activa: { verano: null, invierno: null },
    // Si hubo ediciones (índices o notas) desde la última carga, retomada o guardado.
    _cambios: { verano: false, invierno: false },

    init() {
        ['verano', 'invierno'].forEach(solapa => {
            const boton = document.getElementById(`btn-retomar-${solapa}`);
            if (boton) boton.addEventListener('click', () => RetomarVersion.abrir(solapa));
        });
    },

    versionActiva(solapa) {
        return RetomarVersion._activa[solapa] || null;
    },

    marcarCambios(solapa) {
        if (solapa in RetomarVersion._cambios) RetomarVersion._cambios[solapa] = true;
    },

    limpiarCambios(solapa) {
        if (solapa in RetomarVersion._cambios) RetomarVersion._cambios[solapa] = false;
    },

    /** Al recargar todos los datos se pierde lo retomado: se saca el banner. */
    olvidar(solapa) {
        RetomarVersion._activa[solapa] = null;
        RetomarVersion._cambios[solapa] = false;
        RetomarVersion.pintarBanner(solapa);
    },

    // ------------------------------------------------------------------
    // Elegir la versión
    // ------------------------------------------------------------------

    async abrir(solapa) {
        if (!(window.presupuestoApp.getDatos(solapa) || []).length) {
            UIUtils.mostrarAlerta('Primero cargá los datos.', 'warning');
            return;
        }

        if (RetomarVersion._cambios[solapa]) {
            const seguir = await UIUtils.confirmarAccion(
                'Hay cambios sin guardar',
                '<p>Editaste índices o notas en esta solapa y no los guardaste.</p>'
                + '<p class="mb-0">Si retomás otra versión, <strong>esos cambios se pierden</strong>.</p>',
                'warning'
            );
            if (!seguir) return;
        }

        UIUtils.mostrarLoading(true);
        let versiones;
        try {
            const r = await APIClient.llamarAPI('versiones-presupuesto', { solapa }, 'GET');
            if (!r.success) throw new Error(r.message || 'No se pudieron leer las versiones.');
            // Sin cabecera el servidor no filtra por solapa: se filtra acá también.
            versiones = (r.data || []).filter(v => (v.solapa || '').toLowerCase() === solapa);
        } catch (error) {
            UIUtils.mostrarAlerta(`Error: ${error.message}`, 'error');
            return;
        } finally {
            UIUtils.mostrarLoading(false);
        }

        const elegida = await RetomarVersion.elegir(solapa, versiones);
        if (elegida) await RetomarVersion.retomar(solapa, elegida);
    },

    /** Modal con las versiones guardadas desde esta misma solapa. */
    elegir(solapa, versiones) {
        return new Promise(resolve => {
            const esc = HistorialManager.escaparHtml;
            const filas = versiones.length
                ? versiones.map((v, i) => {
                    const oficial = parseInt(v.es_oficial, 10) === 1;
                    const parcial = v.es_completa !== null && v.es_completa !== undefined && parseInt(v.es_completa, 10) === 0;
                    return `<tr>
                        <td class="small text-nowrap">${FormatoUtils.formatearFechaHora(v.fecha_guardado)}</td>
                        <td class="small">${esc(v.nombre_presupuesto)}
                            ${oficial ? ' <i class="fas fa-flag text-success" title="Versión oficial"></i>' : ''}
                            ${parcial ? ' <span class="badge bg-warning text-dark">parcial</span>' : ''}</td>
                        <td class="small text-muted">${esc(v.comentario || '')}</td>
                        <td class="text-end"><button class="btn btn-sm btn-primary" data-indice="${i}">Retomar</button></td>
                    </tr>`;
                }).join('')
                : '<tr><td colspan="4" class="text-center text-muted py-3">No hay versiones guardadas desde esta solapa.</td></tr>';

            const id = 'modal-retomar-' + Date.now();
            const div = document.createElement('div');
            div.innerHTML = `
                <div class="modal fade" id="${id}" tabindex="-1">
                  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-code-branch me-2"></i>Retomar versión · ${solapa}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <p class="small text-muted">Se aplican los índices y las notas de la versión sobre los
                           datos de <strong>hoy</strong> (stock, ventas y OC actuales) y se recalcula todo.
                           La versión retomada no se modifica: guardar crea una nueva.</p>
                        <table class="table table-sm table-hover mb-0">
                          <thead><tr><th>Guardada</th><th>Nombre</th><th>Comentario</th><th></th></tr></thead>
                          <tbody>${filas}</tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>`;
            document.body.appendChild(div);

            const el = document.getElementById(id);
            const modal = new bootstrap.Modal(el);
            let elegida = null;
            el.querySelectorAll('[data-indice]').forEach(b => b.addEventListener('click', () => {
                elegida = versiones[+b.dataset.indice];
                modal.hide();
            }));
            el.addEventListener('hidden.bs.modal', () => { div.remove(); resolve(elegida); });
            modal.show();
        });
    },

    // ------------------------------------------------------------------
    // Retomar y descartar
    // ------------------------------------------------------------------

    async retomar(solapa, v) {
        UIUtils.mostrarLoading(true);
        try {
            const parametros = v.id ? { id_cabecera: v.id } : { nombre_presupuesto: v.nombre_presupuesto };
            const version = await APIClient.llamarAPI('version-para-retomar', parametros, 'GET');
            if (!version.success) throw new Error(version.message || 'No se pudo leer la versión.');

            const calculo = await APIClient.llamarAPI('compra-con-indices', {}, 'POST', {
                solapa,
                indices: version.filas
            });
            if (!calculo.success) throw new Error(calculo.message || 'No se pudo recalcular la solapa.');

            const notas = {};
            version.filas.forEach(f => { notas[`${f.rubro}|${f.categoria_padre}`] = f.nota || null; });
            RetomarVersion.cargarSolapa(solapa, calculo, notas);

            const teHoy = (calculo.data || []).some(d => d.TE_DISPONIBLE);
            RetomarVersion._activa[solapa] = {
                id: version.version.id,
                nombre: version.version.nombre,
                comentario: version.version.comentario,
                // El aviso solo tiene sentido donde hoy SÍ hay índice TE: en Uruguay
                // los índices se cargan en el LP, que es lo mismo que se había editado.
                anteriorTe: teHoy && version.version.anterior_a_te
            };
            RetomarVersion._cambios[solapa] = false;
            RetomarVersion.pintarBanner(solapa);

            UIUtils.mostrarLoading(false);
            await RetomarVersion.avisar(solapa, calculo.faltantes || []);
            UIUtils.mostrarAlerta(`Trabajando sobre "${version.version.nombre}".`, 'success');

        } catch (error) {
            console.error('Error al retomar la versión:', error);
            UIUtils.mostrarAlerta(`Error: ${error.message}`, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    },

    async descartar(solapa) {
        const activa = RetomarVersion._activa[solapa];
        const seguir = await UIUtils.confirmarAccion(
            'Volver a los índices originales',
            `<p>Se deja de trabajar sobre <strong>${HistorialManager.escaparHtml(activa ? activa.nombre : '')}</strong>
               y la solapa vuelve a los índices de hoy, sin notas.</p>`
            + (RetomarVersion._cambios[solapa] ? '<p class="mb-0 text-danger">Los cambios sin guardar se pierden.</p>' : ''),
            'warning'
        );
        if (!seguir) return;

        UIUtils.mostrarLoading(true);
        try {
            const calculo = solapa === 'invierno'
                ? await APIClient.obtenerCompraInvierno()
                : await APIClient.obtenerCompraVerano();
            if (!calculo.success) throw new Error(calculo.message || 'No se pudieron recargar los datos.');

            RetomarVersion.cargarSolapa(solapa, calculo, {});
            RetomarVersion.olvidar(solapa);
            UIUtils.mostrarAlerta('Se volvió a los índices originales.', 'info');
        } catch (error) {
            UIUtils.mostrarAlerta(`Error: ${error.message}`, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    },

    /**
     * Reemplaza los datos de la solapa por los recalculados y la vuelve a dibujar,
     * con las marcas de "editado" y las notas.
     */
    cargarSolapa(solapa, calculo, notas) {
        const app = window.presupuestoApp;
        const datos = calculo.data || [];

        app.prepararOriginales(datos);
        datos.forEach(item => {
            ['verano', 'invierno'].forEach(temporada => {
                const actual = parseFloat(temporada === 'invierno' ? item.INDICE_VARIACION_INVIERNO : item.INDICE_VARIACION);
                const original = item._indicesOriginales[temporada];
                if (Math.abs(actual - original) > 0.001) {
                    item._indicesEditados = item._indicesEditados || {};
                    item._indicesEditados[temporada] = { valorOriginal: original, valorActual: actual };
                }
            });
            const nota = notas[`${item.RUBRO}|${item.CATEGORIA_PADRE}`];
            item.NOTA = nota || null;
        });

        app.datos[solapa] = datos;
        TemporadaServidor.registrarEtiquetasHistoricas(calculo.etiquetas_historicas);

        TablaRenderer.resetearEstadoTabla(solapa);
        app.estado.tablasLimpias[solapa] = false;
        app.renderizarSolapaSegura(solapa, datos, calculo.etiquetas);
        UIUtils.actualizarHeadersDinamicos(app.temporadaInfo);
        UIUtils.actualizarContador(`count-${solapa}`, datos.length);
        TotalesCompra.actualizarDatos(solapa, datos);

        // Los filtros que estaban aplicados se respetan: se vuelve a filtrar sobre
        // los datos nuevos (y eso recalcula los totales de lo visible).
        if (typeof FiltrosManager !== 'undefined') {
            FiltrosManager.aplicarFiltrosASolapa(solapa);
        }
    },

    /** Avisos al retomar: versión anterior al índice TE y filas que ya no existen. */
    async avisar(solapa, faltantes) {
        const activa = RetomarVersion._activa[solapa];
        const partes = [];

        if (activa && activa.anteriorTe) {
            partes.push(`<div class="alert alert-warning small">
                <i class="fas fa-exclamation-triangle me-1"></i>
                Esta versión es <strong>anterior al índice Total Empresa</strong>: sus índices se habían
                armado sobre la base Locales Propios. Se cargaron en las columnas Total Empresa tal cual;
                revisalos antes de guardar.</div>`);
        }

        if (faltantes.length) {
            const lista = faltantes.slice(0, 50)
                .map(f => `<li>${HistorialManager.escaparHtml(f.rubro)} · ${HistorialManager.escaparHtml(f.categoria_padre)}</li>`)
                .join('');
            const mas = faltantes.length > 50 ? `<li class="fst-italic">y ${faltantes.length - 50} más</li>` : '';
            partes.push(`<p class="small mb-1"><strong>${faltantes.length}</strong> fila(s) de la versión
                ya no existen en los datos de hoy y quedaron afuera:</p>
                <ul class="small mb-2">${lista}${mas}</ul>`);
        }

        if (partes.length) {
            partes.push('<p class="small text-muted mb-0">Las filas que hoy existen y no estaban en la versión quedan con su índice de hoy.</p>');
            await UIUtils.informar('Versión retomada', partes.join(''), 'warning');
        }
    },

    // ------------------------------------------------------------------
    // Banner
    // ------------------------------------------------------------------

    pintarBanner(solapa) {
        const panel = document.getElementById(solapa);
        if (!panel) return;

        let banner = document.getElementById(`banner-retomada-${solapa}`);
        const activa = RetomarVersion._activa[solapa];

        if (!activa) {
            if (banner) banner.remove();
            if (window.ajustarAltura) window.ajustarAltura();
            return;
        }

        if (!banner) {
            banner = document.createElement('div');
            banner.id = `banner-retomada-${solapa}`;
            banner.className = 'alert alert-info d-flex align-items-center justify-content-between py-1 px-2 mb-2 small';
            const tabla = panel.querySelector('.table-responsive');
            tabla.parentNode.insertBefore(banner, tabla);
        }

        const esc = HistorialManager.escaparHtml;
        banner.innerHTML = `
            <span>
                <i class="fas fa-code-branch me-1"></i>
                Trabajando sobre: <strong>${esc(activa.nombre)}</strong>
                ${activa.comentario ? ` <i class="fas fa-comment-dots ms-1" title="${esc(activa.comentario)}"></i>` : ''}
                ${activa.anteriorTe ? ' <span class="badge bg-warning text-dark ms-1" title="Sus índices se armaron sobre la base Locales Propios">anterior al índice TE</span>' : ''}
                <span class="text-muted ms-2">Guardar crea una versión nueva.</span>
            </span>
            <button type="button" class="btn btn-outline-secondary btn-sm py-0" data-accion="descartar">
                <i class="fas fa-undo me-1"></i>Descartar y volver a los originales
            </button>`;
        banner.querySelector('[data-accion="descartar"]').addEventListener('click', () => RetomarVersion.descartar(solapa));
        if (window.ajustarAltura) window.ajustarAltura();
    }
};

document.addEventListener('DOMContentLoaded', () => RetomarVersion.init());
window.RetomarVersion = RetomarVersion;
