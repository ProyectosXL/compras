// Solapa Stock Proyectado sincronizada con Verano / Invierno (solo Argentina).
// Archivo: presupuestos/js/stock-sincronizado.js
//
// En Argentina la cobertura depende de los índices de variación y de las ventas
// proyectadas, que son propios de cada solapa de compra. La solapa Stock NO recalcula
// nada por su cuenta: muestra la cobertura, el stock proyectado y el índice de
// cobertura de la fila equivalente de la solapa elegida en "Calcular con índices de",
// que ya calculó el servidor. Así hay una sola fuente y Stock se actualiza al
// instante cuando cambia algo en Verano o Invierno (antes no se enteraba de que se
// había editado la cobertura).
//
// Uruguay no pasa por acá: su solapa Stock sigue mostrando la cobertura del SP.

const StockSincronizado = {

    _elegida: 'verano',

    CAMPOS: ['INDICE_COBERTURA', 'INDICE_COBERTURA_DEFECTO', 'INDICE_COBERTURA_ORIGEN',
             'STOCK_COBERTURA', 'STOCK_PROYECTADO', 'COBERTURA_DETALLE', '_coberturaEditada'],

    init() {
        document.querySelectorAll('input[name="stock-indices"]').forEach(r => {
            r.addEventListener('change', () => {
                if (r.checked) {
                    StockSincronizado._elegida = r.value;
                    StockSincronizado.refrescar();
                }
            });
        });
    },

    solapaElegida() {
        return StockSincronizado._elegida;
    },

    esArgentina() {
        const stock = (window.presupuestoApp && window.presupuestoApp.getDatos('stock')) || [];
        return stock.length > 0 && !!stock[0].CALCULO_AR;
    },

    /** La columna "Índice Cobertura" de Stock solo existe en Argentina. */
    columnaIndiceVisible() {
        return StockSincronizado.esArgentina();
    },

    clave(r) {
        return `${r.RUBRO}|${r.CATEGORIA_PADRE}`;
    },

    /**
     * Categorías cuyos índices difieren entre Verano e Invierno (alguien editó una
     * sola solapa): índices de variación o ventas cargadas a mano.
     */
    categoriasConDiferencias() {
        const app = window.presupuestoApp;
        const inv = new Map((app.getDatos('invierno') || []).map(r => [StockSincronizado.clave(r), r]));
        let n = 0;
        (app.getDatos('verano') || []).forEach(v => {
            const i = inv.get(StockSincronizado.clave(v));
            if (!i) return;
            const distinto = (a, b) => Math.abs(parseFloat(a ?? 0) - parseFloat(b ?? 0)) > 1e-9;
            if (distinto(v.INDICE_VARIACION, i.INDICE_VARIACION)
                || distinto(v.INDICE_VARIACION_INVIERNO, i.INDICE_VARIACION_INVIERNO)
                || (v.VENTA_MANUAL_VERANO ?? null) !== (i.VENTA_MANUAL_VERANO ?? null)
                || (v.VENTA_MANUAL_INVIERNO ?? null) !== (i.VENTA_MANUAL_INVIERNO ?? null)) {
                n++;
            }
        });
        return n;
    },

    /** Copia de la solapa elegida a Stock lo que depende de la cobertura y vuelve a dibujar. */
    refrescar() {
        const app = window.presupuestoApp;
        const contenedor = document.getElementById('stock-selector-indices');
        const th = document.querySelector('#tabla-stock th[data-col="indice-cobertura"]');
        const ar = StockSincronizado.esArgentina();

        if (contenedor) contenedor.classList.toggle('d-none', !ar);
        if (th) th.classList.toggle('d-none', !ar);
        if (!ar) return;

        const origen = new Map((app.getDatos(StockSincronizado._elegida) || []).map(r => [StockSincronizado.clave(r), r]));
        (app.getDatos('stock') || []).forEach(s => {
            const o = origen.get(StockSincronizado.clave(s));
            if (!o) return;
            StockSincronizado.CAMPOS.forEach(c => {
                if (o[c] === undefined) delete s[c];
                else s[c] = o[c];
            });
            s._factorCoberturaOriginal = parseFloat(o.INDICE_COBERTURA_DEFECTO ?? 0);
        });

        // Selector: si las dos solapas tienen los mismos índices da lo mismo cuál se
        // elija, y se deshabilita con la explicación en vez de esconderlo.
        const diferencias = StockSincronizado.categoriasConDiferencias();
        const grupo = document.getElementById('stock-indices-grupo');
        const aviso = document.getElementById('stock-aviso-indices');
        document.querySelectorAll('input[name="stock-indices"]').forEach(r => { r.disabled = diferencias === 0; });
        if (grupo) {
            grupo.title = diferencias === 0
                ? 'Verano e Invierno tienen los mismos índices: la cobertura da igual con cualquiera de las dos.'
                : 'La cobertura se calcula con los índices de variación y las ventas proyectadas de la solapa elegida.';
        }
        if (aviso) {
            aviso.innerHTML = diferencias > 0
                ? `<i class="fas fa-exclamation-triangle me-1"></i>Hay ${diferencias} categoría${diferencias === 1 ? '' : 's'} con índices distintos entre Verano e Invierno`
                : '';
        }

        // Totales con todos los datos y después el filtro, que redibuja y vuelve a
        // sumar solo lo visible.
        if (typeof TotalesStock !== 'undefined') TotalesStock.actualizarDatos(app.getDatos('stock'));
        if (typeof FiltrosManager !== 'undefined') FiltrosManager.aplicarFiltrosASolapa('stock');
    }
};

document.addEventListener('DOMContentLoaded', () => StockSincronizado.init());
window.StockSincronizado = StockSincronizado;
