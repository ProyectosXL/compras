// Ordenar y ocultar columnas en las tablas del módulo.
// Archivo: presupuestos/js/tabla-columnas.js
//
// Trabaja sobre el DOM ya dibujado y NO sobre los datos: cada solapa tiene su propio
// renderer (TablaRenderer, ComprasManager, ContenedoresManager, VentasManager,
// HistorialManager) y meterse en cada uno habría sido escribir cinco veces lo mismo.
// Un MutationObserver vuelve a aplicar el orden y las columnas ocultas cada vez que
// la tabla se redibuja (al filtrar, buscar, editar un índice o cambiar de modo).
//
// Por eso nada de lo que identifica una fila puede depender de su posición: el
// editor de índices, las marcas de "editado" y el recálculo de tramos buscan la
// fila por rubro + categoría (data-rubro / data-categoria), y los totales salen de
// los datos, no de las filas.
//
// No toca el Excel: el exportador arma la hoja desde los datos y lleva siempre todas
// las columnas, ocultas o no.

const TablaColumnas = {

    // Tablas que se ordenan y cuyas columnas se pueden ocultar. "Proceso para
    // Presupuesto" (distribución) queda afuera a pedido. `barra` es dónde va el botón
    // "Columnas"; `ocultasPorDefecto`, lo que arranca oculto la primera vez.
    TABLAS: [
        { id: 'tabla-verano', barra: '#verano .export-buttons', ocultasPorDefecto: ['indice-original'] },
        { id: 'tabla-invierno', barra: '#invierno .export-buttons', ocultasPorDefecto: ['indice-original'] },
        { id: 'tabla-stock', barra: '#stock .export-buttons' },
        { id: 'tabla-compras-detalle', barra: '#compras-detalle .export-buttons' },
        { id: 'tabla-contenedores', barra: '#contenedores .export-buttons' },
        { id: 'tabla-ventas-6-meses', barra: '#ventas-6-meses .export-buttons' },
        { id: 'tabla-historial', barra: '#btn-limpiar-historial', despues: true },
        { id: 'tabla-versiones', barra: '#btn-cargar-versiones', antes: true }
    ],

    // Estado por tabla: { col, dir: 'asc'|'desc' } o null, y el observer.
    _orden: {},
    _observers: {},

    init() {
        TablaColumnas.TABLAS.forEach(cfg => {
            const tabla = document.getElementById(cfg.id);
            if (!tabla) return;

            TablaColumnas.agregarBoton(cfg);

            tabla.querySelector('thead')?.addEventListener('click', (e) => {
                const th = e.target.closest('th');
                // Los clics sobre controles dentro del encabezado (íconos de ayuda,
                // botones) no ordenan.
                if (!th || e.target.closest('button, input, select, a')) return;
                TablaColumnas.alternarOrden(cfg.id, th);
            });

            const observer = new MutationObserver(() => TablaColumnas.programarAplicar(cfg.id));
            TablaColumnas._observers[cfg.id] = observer;
            TablaColumnas.observar(cfg.id);
            TablaColumnas.aplicar(cfg.id);
        });
    },

    observar(id) {
        const tabla = document.getElementById(id);
        const observer = TablaColumnas._observers[id];
        if (!tabla || !observer) return;
        // tbody: solo filas agregadas o reemplazadas. thead: con subtree, porque hay
        // encabezados que se reescriben por dentro (períodos, modos de contenedores).
        const tbody = tabla.querySelector('tbody');
        const thead = tabla.querySelector('thead');
        if (tbody) observer.observe(tbody, { childList: true });
        if (thead) observer.observe(thead, { childList: true, subtree: true });
    },

    _pendientes: {},
    programarAplicar(id) {
        if (TablaColumnas._pendientes[id]) return;
        TablaColumnas._pendientes[id] = setTimeout(() => {
            delete TablaColumnas._pendientes[id];
            TablaColumnas.aplicar(id);
        }, 0);
    },

    // ------------------------------------------------------------------
    // Identidad de las columnas
    // ------------------------------------------------------------------

    encabezados(tabla) {
        const fila = tabla.querySelector('thead tr:last-child');
        return fila ? Array.from(fila.children) : [];
    },

    /**
     * Identificador estable de una columna. Las solapas de compra lo traen en
     * data-col; en las demás se deriva del texto del encabezado, que es lo único
     * estable entre redibujos (y entre los modos de Contenedores).
     */
    idColumna(th) {
        if (th.dataset.col) return th.dataset.col;
        const texto = TablaColumnas.textoEncabezado(th);
        return 'txt:' + texto.toLowerCase();
    },

    textoEncabezado(th) {
        const copia = th.cloneNode(true);
        copia.querySelectorAll('.orden-indicador').forEach(e => e.remove());
        return copia.textContent.replace(/\s+/g, ' ').trim();
    },

    // ------------------------------------------------------------------
    // Aplicar estado
    // ------------------------------------------------------------------

    /** Vuelve a poner columnas ocultas, orden e indicadores después de un redibujo. */
    aplicar(id) {
        const tabla = document.getElementById(id);
        const observer = TablaColumnas._observers[id];
        if (!tabla) return;

        // Sin observar mientras se reordena: si no, mover filas dispararía otra vuelta.
        if (observer) observer.disconnect();
        try {
            TablaColumnas.aplicarOcultas(tabla, id);
            TablaColumnas.aplicarOrden(tabla, id);
            TablaColumnas.pintarIndicadores(tabla, id);
        } finally {
            TablaColumnas.observar(id);
        }
    },

    ocultas(id) {
        const guardadas = StorageUtils.obtener(`columnas_ocultas_${id}`);
        if (Array.isArray(guardadas)) return guardadas;
        const cfg = TablaColumnas.TABLAS.find(t => t.id === id);
        return (cfg && cfg.ocultasPorDefecto) ? [...cfg.ocultasPorDefecto] : [];
    },

    guardarOcultas(id, lista) {
        // Sin vencimiento: es una preferencia de este navegador, como la vista de OC.
        StorageUtils.guardar(`columnas_ocultas_${id}`, lista);
    },

    aplicarOcultas(tabla, id) {
        const ocultas = new Set(TablaColumnas.ocultas(id));
        const encabezados = TablaColumnas.encabezados(tabla);
        const indicesOcultos = new Set();

        encabezados.forEach((th, i) => {
            const oculta = ocultas.has(TablaColumnas.idColumna(th));
            th.classList.toggle('col-oculta', oculta);
            if (oculta) indicesOcultos.add(i);
        });

        // Las celdas se ubican por posición dentro de la fila, que en estas tablas
        // coincide siempre con la del encabezado. Se saltean las filas de una sola
        // celda con colspan (mensajes de "sin datos").
        tabla.querySelectorAll('tbody tr').forEach(tr => {
            if (tr.children.length !== encabezados.length) return;
            Array.from(tr.children).forEach((td, i) => td.classList.toggle('col-oculta', indicesOcultos.has(i)));
        });
    },

    // ------------------------------------------------------------------
    // Orden
    // ------------------------------------------------------------------

    alternarOrden(id, th) {
        const col = TablaColumnas.idColumna(th);
        const actual = TablaColumnas._orden[id];

        // asc -> desc -> orden original
        let siguiente;
        if (!actual || actual.col !== col) siguiente = { col, dir: 'asc' };
        else if (actual.dir === 'asc') siguiente = { col, dir: 'desc' };
        else siguiente = null;

        TablaColumnas._orden[id] = siguiente;
        TablaColumnas.aplicar(id);
    },

    /** Filas que se quedan siempre al final: totales y mensajes de "sin datos". */
    esFilaFija(tr) {
        if (tr.matches('.fila-totales, .fila-totales-compra, .fila-totales-historial, [data-fila-fija]')) return true;
        return tr.children.length === 1 && tr.children[0].hasAttribute('colspan');
    },

    aplicarOrden(tabla, id) {
        const tbody = tabla.querySelector('tbody');
        if (!tbody) return;

        const filas = Array.from(tbody.children);
        const datos = filas.filter(tr => !TablaColumnas.esFilaFija(tr));
        const fijas = filas.filter(tr => TablaColumnas.esFilaFija(tr));

        // El orden original se anota la primera vez que se ve cada fila: después de
        // un redibujo las filas son nuevas y su orden de llegada ES el original.
        let siguiente = datos.reduce((max, tr) =>
            tr.dataset.ordenOriginal !== undefined ? Math.max(max, +tr.dataset.ordenOriginal + 1) : max, 0);
        datos.forEach(tr => {
            if (tr.dataset.ordenOriginal === undefined) tr.dataset.ordenOriginal = siguiente++;
        });

        const orden = TablaColumnas._orden[id];
        let indice = -1;
        if (orden) {
            indice = TablaColumnas.encabezados(tabla).findIndex(th => TablaColumnas.idColumna(th) === orden.col);
            // La columna ordenada ya no existe (otro modo, otra temporada): vuelve al original.
            if (indice === -1) TablaColumnas._orden[id] = null;
        }

        const original = (a, b) => (+a.dataset.ordenOriginal) - (+b.dataset.ordenOriginal);
        let comparar = original;

        if (indice !== -1) {
            const signo = orden.dir === 'desc' ? -1 : 1;
            comparar = (a, b) => {
                const va = TablaColumnas.valorCelda(a.children[indice]);
                const vb = TablaColumnas.valorCelda(b.children[indice]);
                // Las celdas vacías o con "—" van siempre al final, en los dos sentidos.
                if (va === null && vb === null) return original(a, b);
                if (va === null) return 1;
                if (vb === null) return -1;
                const r = (typeof va === 'number' && typeof vb === 'number')
                    ? va - vb
                    : String(va).localeCompare(String(vb), 'es', { numeric: true, sensitivity: 'base' });
                return r !== 0 ? signo * r : original(a, b);
            };
        }

        const ordenadas = [...datos].sort(comparar);
        // Solo se mueve el DOM si el orden cambió: reinsertar filas idénticas pierde
        // el foco y el scroll sin necesidad.
        const cambio = ordenadas.some((tr, i) => tr !== datos[i]) || fijas.some((tr, i) => filas[datos.length + i] !== tr);
        if (cambio) {
            [...ordenadas, ...fijas].forEach(tr => tbody.appendChild(tr));
        }
    },

    /**
     * Valor de una celda para ordenar. Prioriza data-valor (el número crudo que
     * dejan las solapas de compra); si no, interpreta el texto: fechas dd/mm/aaaa,
     * números con separador de miles argentino y, si no es nada de eso, texto.
     */
    valorCelda(td) {
        if (!td) return null;
        if (td.dataset.valor !== undefined && td.dataset.valor !== '') {
            const n = parseFloat(td.dataset.valor);
            return isNaN(n) ? null : n;
        }

        const input = td.querySelector('input');
        const texto = (input ? input.value : td.textContent).replace(/\s+/g, ' ').trim();
        if (texto === '' || texto === '—' || texto === '-') return null;

        const fecha = texto.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2}))?/);
        if (fecha) {
            return +(fecha[3] + fecha[2] + fecha[1] + (fecha[4] || '00') + (fecha[5] || '00'));
        }

        const limpio = texto.replace(/[$%\s]/g, '');
        // 1.234.567 o 1.234,5: separador de miles argentino.
        if (/^-?\d{1,3}(\.\d{3})+(,\d+)?$/.test(limpio)) {
            return parseFloat(limpio.replace(/\./g, '').replace(',', '.'));
        }
        // 1234, 1234,5 o 1.05 (los índices se muestran con punto decimal).
        if (/^-?\d+([.,]\d+)?$/.test(limpio)) {
            return parseFloat(limpio.replace(',', '.'));
        }
        return texto;
    },

    pintarIndicadores(tabla, id) {
        const orden = TablaColumnas._orden[id];
        TablaColumnas.encabezados(tabla).forEach(th => {
            th.classList.add('th-ordenable');
            let indicador = th.querySelector('.orden-indicador');
            const activa = orden && TablaColumnas.idColumna(th) === orden.col;

            if (!activa) {
                if (indicador) indicador.remove();
                th.removeAttribute('aria-sort');
                return;
            }
            if (!indicador) {
                indicador = document.createElement('span');
                indicador.className = 'orden-indicador';
                th.appendChild(indicador);
            }
            indicador.textContent = orden.dir === 'asc' ? ' ▲' : ' ▼';
            th.setAttribute('aria-sort', orden.dir === 'asc' ? 'ascending' : 'descending');
        });
    },

    // ------------------------------------------------------------------
    // Botón "Columnas"
    // ------------------------------------------------------------------

    agregarBoton(cfg) {
        const ancla = document.querySelector(cfg.barra);
        if (!ancla || document.getElementById(`btn-columnas-${cfg.id}`)) return;

        const grupo = document.createElement('div');
        grupo.className = 'dropdown d-inline-block';
        grupo.innerHTML = `
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
                    id="btn-columnas-${cfg.id}" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                    title="Elegir qué columnas se ven. El Excel lleva siempre todas.">
                <i class="fas fa-columns me-1"></i> Columnas
            </button>
            <div class="dropdown-menu dropdown-menu-end p-2 menu-columnas"></div>`;

        if (cfg.antes) ancla.before(grupo);
        else if (cfg.despues) ancla.after(grupo);
        else ancla.prepend(grupo);

        // El checklist se arma al abrir y no una vez: las columnas dinámicas (tramos,
        // años históricos, meses) recién existen después de cargar los datos.
        grupo.querySelector('button').addEventListener('show.bs.dropdown', () =>
            TablaColumnas.armarMenu(cfg.id, grupo.querySelector('.menu-columnas')));
    },

    armarMenu(id, menu) {
        const tabla = document.getElementById(id);
        if (!tabla) return;

        const ocultas = new Set(TablaColumnas.ocultas(id));
        const vistas = new Set();
        const items = TablaColumnas.encabezados(tabla)
            .filter(th => {
                // Las que están ocultas por otra razón (la vista de OC agrupada o
                // abierta) no se listan: tildarlas no las mostraría.
                if (th.classList.contains('col-oculta')) return true;
                return getComputedStyle(th).display !== 'none';
            })
            .map(th => ({ col: TablaColumnas.idColumna(th), texto: TablaColumnas.textoEncabezado(th) || '(sin título)' }))
            .filter(i => !vistas.has(i.col) && vistas.add(i.col));

        menu.innerHTML = `
            <button type="button" class="btn btn-link btn-sm p-0 mb-1" data-accion="todas">Mostrar todas</button>
            <div class="menu-columnas__lista">
                ${items.map((i, n) => `
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="col-${id}-${n}"
                               data-col="${i.col.replace(/"/g, '&quot;')}" ${ocultas.has(i.col) ? '' : 'checked'}>
                        <label class="form-check-label small text-nowrap" for="col-${id}-${n}">${i.texto}</label>
                    </div>`).join('')}
            </div>`;

        menu.querySelector('[data-accion="todas"]').addEventListener('click', () => {
            TablaColumnas.guardarOcultas(id, []);
            menu.querySelectorAll('input[type="checkbox"]').forEach(c => { c.checked = true; });
            TablaColumnas.aplicar(id);
        });

        menu.querySelectorAll('input[type="checkbox"]').forEach(check => {
            check.addEventListener('change', () => {
                const lista = new Set(TablaColumnas.ocultas(id));
                if (check.checked) lista.delete(check.dataset.col);
                else lista.add(check.dataset.col);
                TablaColumnas.guardarOcultas(id, [...lista]);
                TablaColumnas.aplicar(id);
            });
        });
    }
};

document.addEventListener('DOMContentLoaded', () => TablaColumnas.init());
window.TablaColumnas = TablaColumnas;
