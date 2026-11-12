// Filtro múltiple de Rubro y Categoría (desplegable con casillas).
// Archivo: presupuestos/js/filtro-multiple.js
//
// Un solo componente para todas las solapas. Separa el ESTADO (qué está tildado)
// de las VISTAS (los desplegables en pantalla): Verano, Invierno y Stock comparten
// un mismo estado con tres pares de desplegables montados, mientras que Ventas y
// Distribución tienen cada una su instancia. Se descartó un estado por solapa
// sincronizado a mano: es exactamente lo que hacía el FiltrosManager viejo con los
// <select> y cada solapa nueva obligaba a repetir la sincronización.
//
// Las categorías se identifican por el PAR rubro + categoría. Hay nombres que se
// repiten entre rubros (CARTERA existe en cuero y en sintéticos) y, si se tildaran
// por nombre, destildar una en un rubro la sacaría también del otro.
//
// Uso:
//   const f = new FiltroMultiple({ onCambio: () => ... });
//   f.montar(divRubros, divCategorias);   // una o varias veces
//   f.iniciarCarga(); f.agregarDatos(filas); f.finalizarCarga();
//   filas.filter(x => f.pasa(x));

class FiltroMultiple {

    static SEP = '\u0001';

    constructor({ onCambio = null } = {}) {
        this.onCambio = onCambio;

        // Marcas de lo tildado. Sobreviven a "Cargar Datos": lo que el usuario
        // destildó sigue destildado; lo que aparece por primera vez entra tildado.
        this.marcaRubro = new Map();   // rubro -> bool
        this.marcaPar = new Map();     // rubro␁categoria -> bool

        // Universo de la carga actual. Se rearma en cada carga para que un rubro
        // que dejó de venir no siga sumando en el "(x/y)".
        this.rubros = new Set();
        this.pares = new Map();        // rubro -> Set(categoria)

        this.vistas = [];
    }

    static clavePar(rubro, categoria) {
        return `${rubro}${FiltroMultiple.SEP}${categoria}`;
    }

    static categoriaDe(item) {
        return item.CATEGORIA_PADRE || item.CATEGORIA || '';
    }

    // ------------------------------------------------------------------
    // Datos
    // ------------------------------------------------------------------

    iniciarCarga() {
        this.rubros = new Set();
        this.pares = new Map();
    }

    /** Suma filas al universo. Se puede llamar varias veces (una por solapa). */
    agregarDatos(datos) {
        (datos || []).forEach(item => {
            const rubro = (item.RUBRO || '').trim();
            const categoria = FiltroMultiple.categoriaDe(item).trim();
            if (!rubro) return;

            this.rubros.add(rubro);
            if (!this.marcaRubro.has(rubro)) this.marcaRubro.set(rubro, true);

            if (!categoria) return;
            if (!this.pares.has(rubro)) this.pares.set(rubro, new Set());
            this.pares.get(rubro).add(categoria);

            const clave = FiltroMultiple.clavePar(rubro, categoria);
            if (!this.marcaPar.has(clave)) this.marcaPar.set(clave, true);
        });
    }

    finalizarCarga() {
        this.renderizar();
    }

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    /**
     * Si una fila pasa el filtro de rubro y categoría (el texto lo filtra quien llama).
     * Una fila que no está en el universo pasa: el filtro no puede esconder algo
     * que nunca le mostró al usuario para destildar.
     */
    pasa(item) {
        const rubro = (item.RUBRO || '').trim();
        if (!this.rubros.has(rubro)) return true;
        if (!this.marcaRubro.get(rubro)) return false;

        const categoria = FiltroMultiple.categoriaDe(item).trim();
        const clave = FiltroMultiple.clavePar(rubro, categoria);
        if (!categoria || !this.marcaPar.has(clave)) return true;
        return this.marcaPar.get(clave) === true;
    }

    rubrosOrdenados() {
        return [...this.rubros].sort((a, b) => a.localeCompare(b));
    }

    /** Pares visibles en el desplegable de categorías: solo de rubros tildados. */
    paresVisibles() {
        const lista = [];
        this.rubrosOrdenados().forEach(rubro => {
            if (!this.marcaRubro.get(rubro)) return;
            [...(this.pares.get(rubro) || [])].sort((a, b) => a.localeCompare(b))
                .forEach(categoria => lista.push({ rubro, categoria }));
        });
        return lista;
    }

    conteoRubros() {
        const total = this.rubros.size;
        const tildados = [...this.rubros].filter(r => this.marcaRubro.get(r)).length;
        return { tildados, total };
    }

    conteoCategorias() {
        const visibles = this.paresVisibles();
        const tildados = visibles.filter(p => this.marcaPar.get(FiltroMultiple.clavePar(p.rubro, p.categoria))).length;
        return { tildados, total: visibles.length };
    }

    /** Hay algo destildado (rubro o categoría de un rubro tildado). */
    hayDestildados() {
        const r = this.conteoRubros();
        const c = this.conteoCategorias();
        return r.tildados < r.total || c.tildados < c.total;
    }

    /** No queda nada que mostrar: ningún rubro o ninguna categoría tildada. */
    nadaTildado() {
        if (!this.rubros.size) return false;
        const r = this.conteoRubros();
        if (r.tildados === 0) return true;
        const c = this.conteoCategorias();
        return c.total > 0 && c.tildados === 0;
    }

    // ------------------------------------------------------------------
    // Cambios
    // ------------------------------------------------------------------

    /** Todo tildado. Es lo que hace "Limpiar filtros". */
    tildarTodo({ notificar = true } = {}) {
        this.rubros.forEach(r => this.marcaRubro.set(r, true));
        this.pares.forEach((cats, r) => cats.forEach(c => this.marcaPar.set(FiltroMultiple.clavePar(r, c), true)));
        this.renderizar();
        if (notificar) this.notificar();
    }

    marcarRubro(rubro, valor) {
        // Al volver a tildar un rubro sus categorías vuelven tildadas: si no, el
        // rubro reaparecería vacío y parecería que no tiene datos.
        this.marcaRubro.set(rubro, valor);
        if (valor) {
            (this.pares.get(rubro) || []).forEach(c => this.marcaPar.set(FiltroMultiple.clavePar(rubro, c), true));
        }
    }

    marcarPar(rubro, categoria, valor) {
        this.marcaPar.set(FiltroMultiple.clavePar(rubro, categoria), valor);
    }

    notificar() {
        if (typeof this.onCambio === 'function') this.onCambio();
    }

    // ------------------------------------------------------------------
    // Vistas
    // ------------------------------------------------------------------

    /**
     * Monta un par de desplegables. Los contenedores pueden ser null (por ejemplo,
     * una solapa que solo filtra por rubro).
     */
    montar(contRubros, contCategorias) {
        const vista = {
            rubros: contRubros ? this.crearDesplegable(contRubros, 'rubro') : null,
            categorias: contCategorias ? this.crearDesplegable(contCategorias, 'categoria') : null
        };
        this.vistas.push(vista);
        this.renderizarVista(vista);
        return vista;
    }

    crearDesplegable(contenedor, tipo) {
        const titulo = tipo === 'rubro' ? 'Rubros' : 'Categorías';
        contenedor.classList.add('dropdown', 'filtro-multiple');
        contenedor.innerHTML = `
            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle filtro-multiple-boton w-100"
                    data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <span class="filtro-multiple-leyenda">${titulo}</span>
            </button>
            <div class="dropdown-menu filtro-multiple-menu p-2">
                <input type="search" class="form-control form-control-sm mb-2 filtro-multiple-buscar"
                       placeholder="Buscar ${titulo.toLowerCase()}..." autocomplete="off">
                <div class="d-flex justify-content-between mb-1 small">
                    <a href="#" class="filtro-multiple-todos">Seleccionar todos</a>
                    <a href="#" class="filtro-multiple-ninguno">Ninguno</a>
                </div>
                <div class="filtro-multiple-lista"></div>
            </div>`;

        const d = {
            tipo,
            titulo,
            contenedor,
            leyenda: contenedor.querySelector('.filtro-multiple-leyenda'),
            boton: contenedor.querySelector('.filtro-multiple-boton'),
            buscar: contenedor.querySelector('.filtro-multiple-buscar'),
            lista: contenedor.querySelector('.filtro-multiple-lista')
        };

        d.buscar.addEventListener('input', () => this.renderizarLista(d));
        // El texto del buscador interno es solo para encontrar casillas: se vacía al
        // abrir para que nunca quede escondida una opción "olvidada".
        contenedor.addEventListener('show.bs.dropdown', () => {
            d.buscar.value = '';
            this.renderizarLista(d);
        });
        contenedor.addEventListener('shown.bs.dropdown', () => d.buscar.focus());

        // "Todos" y "Ninguno" actúan sobre lo que se ve en la lista: si hay texto en el
        // buscador interno, solo sobre lo encontrado. Es lo que se espera al buscar
        // "CARTERA" y tocar "Ninguno".
        contenedor.querySelector('.filtro-multiple-todos').addEventListener('click', (e) => {
            e.preventDefault();
            this.marcarVisibles(d, true);
        });
        contenedor.querySelector('.filtro-multiple-ninguno').addEventListener('click', (e) => {
            e.preventDefault();
            this.marcarVisibles(d, false);
        });

        d.lista.addEventListener('change', (e) => {
            const input = e.target.closest('input[type="checkbox"]');
            if (!input) return;
            if (d.tipo === 'rubro') {
                this.marcarRubro(input.dataset.rubro, input.checked);
            } else {
                this.marcarPar(input.dataset.rubro, input.dataset.categoria, input.checked);
            }
            this.renderizar();
            this.notificar();
        });

        return d;
    }

    itemsDe(d) {
        const texto = (d.buscar.value || '').trim().toLowerCase();
        const coincide = (s) => !texto || s.toLowerCase().includes(texto);

        if (d.tipo === 'rubro') {
            return this.rubrosOrdenados().filter(coincide).map(r => ({ rubro: r }));
        }
        return this.paresVisibles().filter(p => coincide(p.categoria) || coincide(p.rubro));
    }

    marcarVisibles(d, valor) {
        this.itemsDe(d).forEach(it => {
            if (d.tipo === 'rubro') this.marcarRubro(it.rubro, valor);
            else this.marcarPar(it.rubro, it.categoria, valor);
        });
        this.renderizar();
        this.notificar();
    }

    renderizar() {
        this.vistas.forEach(v => this.renderizarVista(v));
    }

    renderizarVista(vista) {
        [vista.rubros, vista.categorias].forEach(d => {
            if (!d) return;
            const { tildados, total } = d.tipo === 'rubro' ? this.conteoRubros() : this.conteoCategorias();
            d.leyenda.textContent = `${d.titulo} (${tildados}/${total})`;
            d.boton.classList.toggle('filtro-multiple-parcial', tildados < total);
            // La lista solo se arma si el menú está abierto: repintar 72 casillas en
            // tres solapas en cada clic no aporta nada mientras nadie las mira.
            if (d.contenedor.querySelector('.dropdown-menu.show')) this.renderizarLista(d);
        });
    }

    renderizarLista(d) {
        const esc = FiltroMultiple.escapar;
        const items = this.itemsDe(d);

        if (!items.length) {
            d.lista.innerHTML = `<div class="text-muted small px-1 py-2">${
                d.tipo === 'categoria' && this.conteoRubros().tildados === 0
                    ? 'No hay rubros seleccionados.'
                    : 'Sin resultados.'}</div>`;
            return;
        }

        if (d.tipo === 'rubro') {
            d.lista.innerHTML = items.map(it => `
                <label class="form-check filtro-multiple-item">
                    <input class="form-check-input" type="checkbox" data-rubro="${esc(it.rubro)}"
                           ${this.marcaRubro.get(it.rubro) ? 'checked' : ''}>
                    <span class="form-check-label">${esc(it.rubro)}</span>
                </label>`).join('');
            return;
        }

        // Categorías agrupadas por rubro: con dos rubros tildados que comparten una
        // categoría, el grupo deja claro cuál de las dos se está tildando.
        let html = '';
        let rubroActual = null;
        items.forEach(it => {
            if (it.rubro !== rubroActual) {
                rubroActual = it.rubro;
                html += `<div class="filtro-multiple-grupo">${esc(it.rubro)}</div>`;
            }
            const marcado = this.marcaPar.get(FiltroMultiple.clavePar(it.rubro, it.categoria));
            html += `
                <label class="form-check filtro-multiple-item">
                    <input class="form-check-input" type="checkbox"
                           data-rubro="${esc(it.rubro)}" data-categoria="${esc(it.categoria)}"
                           ${marcado ? 'checked' : ''}>
                    <span class="form-check-label">${esc(it.categoria)}</span>
                </label>`;
        });
        d.lista.innerHTML = html;
    }

    static escapar(texto) {
        return String(texto ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
}

window.FiltroMultiple = FiltroMultiple;
