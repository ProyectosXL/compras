// Pantalla de Parámetros (solo Argentina).
// Archivo: presupuestos/js/parametros-manager.js
//
// Arma el modal #modalParametros con lo que devuelve la acción 'parametros'. Cada
// cambio se manda al servidor en el momento (un parámetro, una fila o un valor de
// lista) y el servidor devuelve el estado completo, con el "Modificado por" ya
// actualizado. No hay un "Guardar todo": un cambio a medio escribir en otra pestaña
// no tiene que viajar junto con el que se confirmó.

const ParametrosManager = {

    estado: null,
    modal: null,

    GENERALES: [
        { clave: 'COBERTURA_INDICE_DEFECTO', etiqueta: 'Índice de cobertura por defecto', unidad: 'meses', paso: 0.01 },
        { clave: 'COBERTURA_DIAS_POR_MES', etiqueta: 'Días por mes de cobertura', unidad: 'días', paso: 1 },
        { clave: 'COBERTURA_DIAS_MINIMOS_VENDIDO', etiqueta: 'Días mínimos transcurridos para usar lo vendido de la temporada en curso', unidad: 'días', paso: 1 },
        { clave: 'COBERTURA_INDICE_MIN', etiqueta: 'Índice de cobertura: mínimo al editar', unidad: 'meses', paso: 0.01 },
        { clave: 'COBERTURA_INDICE_MAX', etiqueta: 'Índice de cobertura: máximo al editar', unidad: 'meses', paso: 0.01 },
        { clave: 'VARIACION_VENTANA_DIAS', etiqueta: 'Índice de variación: ventana de días', unidad: 'días', paso: 1, delSp: true },
        { clave: 'VARIACION_INDICE_MIN', etiqueta: 'Índice de variación: mínimo al editar', unidad: '', paso: 0.01 },
        { clave: 'VARIACION_INDICE_MAX', etiqueta: 'Índice de variación: máximo al editar', unidad: '', paso: 0.01 }
    ],

    // Valores de fábrica: se muestran (de solo lectura) cuando falta el script 08.
    DEFECTOS: {
        COBERTURA_INDICE_DEFECTO: '1.50', COBERTURA_DIAS_POR_MES: '30', COBERTURA_DIAS_MINIMOS_VENDIDO: '30',
        COBERTURA_INDICE_MIN: '0', COBERTURA_INDICE_MAX: '12', VARIACION_VENTANA_DIAS: '60',
        VARIACION_INDICE_MIN: '0', VARIACION_INDICE_MAX: '10'
    },
    LISTAS_DEFECTO: {
        RUBRO: ['PACKAGING', '_KITS'], CATEGORIA: ['REMERA'], ARTICULO: ['XV00LV00A0001'],
        CANAL_VENTA: ['LOCALES PROPIOS', 'CENTRAL'], CANAL_INDICE_LP: ['LOCALES PROPIOS'],
        RUBRO_SIN_COBERTURA: ['CALZADOS', 'CAMPERAS']
    },

    LISTAS_SP: [
        { tipo: 'RUBRO', titulo: 'Rubros excluidos', sugerir: 'rubros' },
        { tipo: 'CATEGORIA', titulo: 'Categorías excluidas', sugerir: 'categorias' },
        { tipo: 'ARTICULO', titulo: 'Artículos excluidos' },
        { tipo: 'CANAL_VENTA', titulo: 'Canales de venta para el cálculo' },
        { tipo: 'CANAL_INDICE_LP', titulo: 'Canales del índice Locales Propios' }
    ],

    esUruguay() {
        return (StorageUtils.obtener('pais_seleccionado') || 'argentina') === 'uruguay';
    },

    /** Habilita o deshabilita el botón ⚙ según el país. */
    actualizarBoton(pais) {
        const btn = document.getElementById('btn-parametros');
        if (!btn) return;
        const uy = pais === 'uruguay';
        btn.disabled = uy;
        btn.title = uy ? 'Parámetros: disponible solo para Argentina' : 'Parámetros';
    },

    async abrir() {
        if (ParametrosManager.esUruguay()) {
            UIUtils.mostrarAlerta('Los parámetros son solo de Argentina.', 'info');
            return;
        }
        const el = document.getElementById('modalParametros');
        if (!ParametrosManager.modal) {
            ParametrosManager.modal = new bootstrap.Modal(el);
            document.getElementById('parametros-btn-recargar').addEventListener('click', () => {
                ParametrosManager.modal.hide();
                cargarDatos();
            });
        }
        document.getElementById('parametros-aviso-recarga').classList.add('d-none');

        UIUtils.mostrarLoading(true);
        try {
            const r = await APIClient.llamarAPI('parametros', {}, 'GET');
            if (!r.success) throw new Error(r.message);
            ParametrosManager.estado = r;
            ParametrosManager.renderizar();
            ParametrosManager.modal.show();
        } catch (error) {
            UIUtils.mostrarAlerta('No se pudieron leer los parámetros: ' + error.message, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    },

    async enviar(accion, cuerpo, { afectaCarga = true } = {}) {
        try {
            const r = await APIClient.llamarAPI(accion, {}, 'POST', cuerpo);
            if (!r.success) throw new Error(r.message);
            ParametrosManager.estado = r;
            ParametrosManager.renderizar();
            if (afectaCarga) document.getElementById('parametros-aviso-recarga').classList.remove('d-none');
            UIUtils.mostrarAlerta('Parámetro guardado', 'success', 2000);
        } catch (error) {
            UIUtils.mostrarAlerta(error.message, 'error');
        }
    },

    // ------------------------------------------------------------------
    // Render
    // ------------------------------------------------------------------

    renderizar() {
        const e = ParametrosManager.estado || {};
        document.getElementById('parametros-sin-tablas').classList.toggle('d-none', !!e.tablas);
        ParametrosManager.renderizarGenerales();
        ParametrosManager.renderizarCobertura();
        ParametrosManager.renderizarExclusiones();
        ParametrosManager.renderizarHistorial();
    },

    esc(t) {
        return FiltroMultiple.escapar(t);
    },

    modificado(fila) {
        if (!fila || !fila.fecha_modif) return '';
        return `<span class="text-muted small">Modificado por ${ParametrosManager.esc(fila.usuario_modif || '—')} el ${ParametrosManager.fechaCorta(fila.fecha_modif)}</span>`;
    },

    fechaCorta(iso) {
        // 'YYYY-MM-DD HH:MM' -> 'dd/mm hh:mm'
        const m = String(iso || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
        return m ? `${m[3]}/${m[2]} ${m[4]}:${m[5]}` : (iso || '');
    },

    /** Valores de lista activos, de la tabla o de fábrica. */
    valoresLista(tipo) {
        const e = ParametrosManager.estado || {};
        if (!e.tablas) {
            return (ParametrosManager.LISTAS_DEFECTO[tipo] || []).map(v => ({ id: null, valor: v }));
        }
        return (e.listas || []).filter(l => l.tipo === tipo && parseInt(l.activo, 10) === 1);
    },

    /** Rubros y categorías que hay en los datos cargados, para los combos. */
    universo() {
        const datos = (window.presupuestoApp && window.presupuestoApp.datos && window.presupuestoApp.datos.verano) || [];
        const porRubro = new Map();
        datos.forEach(d => {
            if (!d.RUBRO) return;
            if (!porRubro.has(d.RUBRO)) porRubro.set(d.RUBRO, new Set());
            if (d.CATEGORIA_PADRE) porRubro.get(d.RUBRO).add(d.CATEGORIA_PADRE);
        });
        return porRubro;
    },

    renderizarGenerales() {
        const e = ParametrosManager.estado || {};
        const esc = ParametrosManager.esc;
        const porClave = {};
        (e.generales || []).forEach(g => { porClave[g.clave] = g; });
        const soloLectura = !e.tablas;

        const filas = ParametrosManager.GENERALES.map(def => {
            const fila = porClave[def.clave];
            const valor = fila ? fila.valor : ParametrosManager.DEFECTOS[def.clave];
            return `<tr>
                <td>${esc(def.etiqueta)}${def.delSp ? ' <span class="badge bg-secondary-subtle text-dark" title="Lo usa el proceso de datos (SP): se aplica en su próxima ejecución">SP</span>' : ''}</td>
                <td style="width:160px">
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control text-end" data-clave="${def.clave}" value="${esc(valor)}"
                               step="${def.paso}" ${soloLectura ? 'disabled' : ''}>
                        ${def.unidad ? `<span class="input-group-text">${def.unidad}</span>` : ''}
                    </div>
                </td>
                <td style="width:90px">${soloLectura ? '' : `<button class="btn btn-sm btn-outline-primary" data-guardar="${def.clave}">Guardar</button>`}</td>
                <td>${ParametrosManager.modificado(fila)}</td>
            </tr>`;
        }).join('');

        const pane = document.getElementById('param-pane-generales');
        pane.innerHTML = `
            <table class="table table-sm align-middle">
                <tbody>${filas}</tbody>
            </table>
            <h6 class="fw-bold mt-3">Rubros sin cobertura</h6>
            <p class="small text-muted mb-2">Su índice de cobertura por defecto es 0. No es un 0 fijo: un índice en la pestaña
               "Cobertura por rubro / categoría" o una edición en pantalla lo pisan.</p>
            ${ParametrosManager.htmlLista('RUBRO_SIN_COBERTURA', 'rubros')}`;

        pane.querySelectorAll('[data-guardar]').forEach(btn => btn.addEventListener('click', () => {
            const clave = btn.dataset.guardar;
            const valor = pane.querySelector(`input[data-clave="${clave}"]`).value;
            const def = ParametrosManager.GENERALES.find(g => g.clave === clave);
            ParametrosManager.enviar('guardar-parametro', { clave, valor: parseFloat(valor) }, { afectaCarga: !def.delSp });
        }));
        ParametrosManager.conectarLista(pane, true);
    },

    /** Chips de una lista + campo para agregar. */
    htmlLista(tipo, sugerir) {
        const e = ParametrosManager.estado || {};
        const esc = ParametrosManager.esc;
        const valores = ParametrosManager.valoresLista(tipo);
        const fila = (e.listas || []).filter(l => l.tipo === tipo)
            .sort((a, b) => String(b.fecha_modif).localeCompare(String(a.fecha_modif)))[0];

        let opciones = '';
        if (sugerir) {
            const u = ParametrosManager.universo();
            const lista = sugerir === 'rubros'
                ? [...u.keys()]
                : [...new Set([].concat(...[...u.values()].map(s => [...s])))];
            opciones = `<datalist id="dl-param-${tipo}">${lista.sort().map(v => `<option value="${esc(v)}">`).join('')}</datalist>`;
        }

        return `<div class="param-lista mb-1" data-tipo="${tipo}">
            ${valores.map(v => `<span class="badge rounded-pill bg-light text-dark border me-1 mb-1 py-2 px-3">
                ${esc(v.valor)}
                ${e.tablas ? `<button type="button" class="btn-close ms-1" style="font-size:.55rem" data-quitar="${v.id}" title="Quitar"></button>` : ''}
            </span>`).join('') || '<span class="text-muted small">Vacía</span>'}
        </div>
        ${e.tablas ? `<div class="input-group input-group-sm mb-1" style="max-width:360px">
            <input type="text" class="form-control" data-agregar-tipo="${tipo}" placeholder="Agregar..." ${sugerir ? `list="dl-param-${tipo}"` : ''}>
            <button class="btn btn-outline-primary" data-agregar="${tipo}"><i class="fas fa-plus"></i></button>
        </div>${opciones}` : ''}
        <div class="mb-3">${ParametrosManager.modificado(fila)}</div>`;
    },

    conectarLista(pane, afectaCarga) {
        pane.querySelectorAll('[data-quitar]').forEach(btn => btn.addEventListener('click', () => {
            ParametrosManager.enviar('quitar-lista-parametro', { id: parseInt(btn.dataset.quitar, 10) }, { afectaCarga });
        }));
        pane.querySelectorAll('[data-agregar]').forEach(btn => btn.addEventListener('click', () => {
            const tipo = btn.dataset.agregar;
            const input = pane.querySelector(`input[data-agregar-tipo="${tipo}"]`);
            const valor = (input.value || '').trim();
            if (!valor) return;
            ParametrosManager.enviar('agregar-lista-parametro', { tipo, valor }, { afectaCarga });
        }));
    },

    renderizarCobertura() {
        const e = ParametrosManager.estado || {};
        const esc = ParametrosManager.esc;
        const pane = document.getElementById('param-pane-cobertura');
        const u = ParametrosManager.universo();
        const rubros = [...u.keys()].sort();

        const filas = (e.cobertura || []).map(c => `<tr>
            <td>${esc(c.rubro)}</td>
            <td>${c.categoria ? esc(c.categoria) : '<span class="text-muted fst-italic">(todo el rubro)</span>'}</td>
            <td class="text-end">${parseFloat(c.indice_cobertura).toFixed(2)}</td>
            <td>${ParametrosManager.modificado(c)}</td>
            <td class="text-end text-nowrap">
                <button class="btn btn-sm btn-outline-secondary" data-editar='${esc(JSON.stringify({ r: c.rubro, c: c.categoria || '', i: c.indice_cobertura }))}' title="Editar"><i class="fas fa-pen"></i></button>
                <button class="btn btn-sm btn-outline-danger" data-borrar="${c.id}" title="Quitar"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`).join('');

        pane.innerHTML = `
            <p class="small text-muted">Índice de cobertura por defecto distinto para un rubro entero o una categoría puntual.
               Prioridad: <strong>categoría &gt; rubro &gt; rubros sin cobertura (0) &gt; general</strong>.
               Una edición en pantalla pisa a todos.</p>
            ${e.tablas ? `<div class="row g-2 align-items-end mb-3">
                <div class="col-md-4"><label class="form-label small mb-0">Rubro</label>
                    <select class="form-select form-select-sm" id="param-cob-rubro">
                        <option value="">Elegir...</option>${rubros.map(r => `<option>${esc(r)}</option>`).join('')}
                    </select></div>
                <div class="col-md-4"><label class="form-label small mb-0">Categoría</label>
                    <select class="form-select form-select-sm" id="param-cob-categoria"><option value="">(todo el rubro)</option></select></div>
                <div class="col-md-2"><label class="form-label small mb-0">Índice (meses)</label>
                    <input type="number" step="0.01" class="form-control form-control-sm text-end" id="param-cob-indice"></div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" id="param-cob-guardar">Guardar</button></div>
            </div>` : ''}
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light"><tr><th>Rubro</th><th>Categoría</th><th class="text-end">Índice</th><th></th><th></th></tr></thead>
                <tbody>${filas || '<tr><td colspan="5" class="text-muted text-center py-3">Sin índices propios: todos usan el general.</td></tr>'}</tbody>
            </table>`;

        if (!e.tablas) return;

        const selRubro = pane.querySelector('#param-cob-rubro');
        const selCat = pane.querySelector('#param-cob-categoria');
        const llenarCategorias = (elegida = '') => {
            const cats = [...(u.get(selRubro.value) || [])].sort();
            selCat.innerHTML = '<option value="">(todo el rubro)</option>' + cats.map(c => `<option ${c === elegida ? 'selected' : ''}>${esc(c)}</option>`).join('');
        };
        selRubro.addEventListener('change', () => llenarCategorias());

        pane.querySelector('#param-cob-guardar').addEventListener('click', () => {
            if (!selRubro.value) { UIUtils.mostrarAlerta('Elegí un rubro.', 'warning'); return; }
            ParametrosManager.enviar('guardar-cobertura-parametro', {
                rubro: selRubro.value, categoria: selCat.value, indice: parseFloat(pane.querySelector('#param-cob-indice').value)
            });
        });
        pane.querySelectorAll('[data-editar]').forEach(btn => btn.addEventListener('click', () => {
            const d = JSON.parse(btn.dataset.editar);
            if (![...selRubro.options].some(o => o.value === d.r)) selRubro.add(new Option(d.r, d.r));
            selRubro.value = d.r;
            llenarCategorias(d.c);
            if (d.c && selCat.value !== d.c) { selCat.add(new Option(d.c, d.c)); selCat.value = d.c; }
            pane.querySelector('#param-cob-indice').value = parseFloat(d.i).toFixed(2);
        }));
        pane.querySelectorAll('[data-borrar]').forEach(btn => btn.addEventListener('click', () => {
            if (confirm('¿Quitar este índice? La categoría vuelve a usar el del rubro o el general.')) {
                ParametrosManager.enviar('borrar-cobertura-parametro', { id: parseInt(btn.dataset.borrar, 10) });
            }
        }));
    },

    renderizarExclusiones() {
        const e = ParametrosManager.estado || {};
        const pane = document.getElementById('param-pane-exclusiones');
        const ult = e.ult_actualizacion ? ParametrosManager.fechaCorta(e.ult_actualizacion) : '—';

        pane.innerHTML = `
            <div class="alert alert-warning py-2 small">
                <i class="fas fa-clock me-1"></i>
                Estos cambios se aplican la próxima vez que se ejecute el proceso de datos
                (última actualización: <strong>${ult}</strong>).
            </div>
            <p class="small text-muted">La regla de outlets (se excluyen los rubros con OUTLET salvo SINTETICOS OUTLET, con
               artículos que empiezan con O y no con OU) es fija y no se configura desde acá.</p>
            ${ParametrosManager.LISTAS_SP.map(l => `<h6 class="fw-bold mt-3">${l.titulo}</h6>${ParametrosManager.htmlLista(l.tipo, l.sugerir)}`).join('')}`;
        // Las exclusiones las usa el SP: recargar los datos no cambia nada hasta que corra.
        ParametrosManager.conectarLista(pane, false);
    },

    renderizarHistorial() {
        const e = ParametrosManager.estado || {};
        const esc = ParametrosManager.esc;
        const filas = (e.historial || []).map(h => `<tr>
            <td class="text-nowrap small">${ParametrosManager.fechaCorta(h.fecha)}</td>
            <td class="small">${esc(h.usuario || '—')}</td>
            <td class="small">${esc(h.tabla)}</td>
            <td class="small">${esc(h.clave)}</td>
            <td class="small">${esc(h.accion)}</td>
            <td class="small text-end">${esc(h.valor_anterior ?? '')}</td>
            <td class="small text-end">${esc(h.valor_nuevo ?? '')}</td>
        </tr>`).join('');
        document.getElementById('param-pane-historial').innerHTML = `
            <table class="table table-sm table-striped align-middle">
                <thead class="table-light"><tr><th>Fecha</th><th>Usuario</th><th>Tabla</th><th>Clave</th><th>Acción</th>
                    <th class="text-end">Anterior</th><th class="text-end">Nuevo</th></tr></thead>
                <tbody>${filas || '<tr><td colspan="7" class="text-muted text-center py-3">Sin cambios registrados.</td></tr>'}</tbody>
            </table>`;
    }
};

window.ParametrosManager = ParametrosManager;

// main.js aplica el país antes de que este archivo cargue: acá se ajusta el botón
// con el país que ya quedó elegido.
document.addEventListener('DOMContentLoaded', () => {
    ParametrosManager.actualizarBoton(StorageUtils.obtener('pais_seleccionado') || 'argentina');
});
