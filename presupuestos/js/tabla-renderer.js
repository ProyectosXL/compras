
// TablaRenderer con columna de ÍNDICE ORIGINAL
// Archivo: presupuestos/js/tabla-renderer.js

// ========================================
// FUNCIONES BASE
// ========================================

const TablaRendererUtils = {
    
    mostrarTablaVacia(tbody, colspan, mensaje) {
        tbody.innerHTML = `
            <tr>
                <td colspan="${colspan}" class="text-center text-muted py-4">
                    <i class="fas fa-inbox"></i>
                    ${mensaje}
                </td>
            </tr>
        `;
    },

    escaparComillas(texto) {
        if (!texto) return '';
        return texto.replace(/'/g, "\\'").replace(/"/g, '\\"');
    },

    resetearEstadoTabla(solapa) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        if (tabla) {
            tabla.removeAttribute('data-columnas-procesadas');
            tabla.removeAttribute('data-limpia');
            
            const headersDinamicos = tabla.querySelectorAll('th[data-columna-dinamica]');
            headersDinamicos.forEach(th => th.remove());
            
            const celdasDinamicas = tabla.querySelectorAll('td[data-columna-dinamica]');
            celdasDinamicas.forEach(td => td.remove());
        }
    },

    limpiarTabla(solapa) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        if (!tabla) {
            console.warn(`Tabla ${solapa} no encontrada`);
            return;
        }
        
        const thead = tabla.querySelector('thead tr');
        if (!thead) {
            console.warn(`Header de tabla ${solapa} no encontrado`);
            return;
        }
        
        const columnasDinamicas = thead.querySelectorAll('[data-columna-dinamica]');
        columnasDinamicas.forEach(th => th.remove());
        
        tabla.removeAttribute('data-columnas-procesadas');
        tabla.setAttribute('data-limpia', 'true');
    },

    /**
     * Venta anterior que se usó como base de la proyección.
     *
     * La elige el servidor y viaja en VENTA_*_ANTERIOR. Antes se buscaba acá con una
     * lista fija de las últimas dos temporadas y el reloj del navegador: cuando la
     * última venta con movimiento era más vieja (por ejemplo BILLETERAS DE CUERO,
     * con ventas solo en INV 24), la pantalla mostraba 0 mientras la proyección se
     * había calculado sobre 335. Esa lista fija se reemplazó por el respaldo de
     * TemporadaServidor.ventaAnteriorDe(), que aplica la misma regla que PHP en vez
     * de adivinar los años a partir de `new Date()`.
     */
    buscarVentaHistoricaCorrecta(item, temporada) {
        return TemporadaServidor.ventaAnteriorDe(item, temporada);
    },

    /**
     * Tramos en los que se abre la compra de esta solapa.
     *
     * Salen del reparto que ya calculó el servidor y viajan en cada fila, así que
     * alcanza con mirar la primera: todas las filas de una solapa tienen los MISMOS
     * tramos (el mismo período cubierto), lo que cambia son los números. Se busca la
     * primera fila que los traiga y no `datos[0]` porque una versión vieja en caché
     * puede no tenerlos.
     */
    tramosDeSolapa(datos) {
        const fila = (datos || []).find(d => Array.isArray(d.TRAMOS) && d.TRAMOS.length > 0);
        return fila ? fila.TRAMOS : [];
    },

    /**
     * Encabezado de la columna de un tramo.
     *
     * El tramo objetivo se destaca porque es el único que el consumidor externo lee
     * de esta versión: los intermedios quedan como control. El resto de la temporada
     * en curso no se rotula "Compra" sino "Sin cubrir": esa mercadería ya no llega a
     * tiempo, así que no es algo a comprar sino venta que va a quedar sin cobertura.
     */
    encabezadoDeTramo(tramo) {
        const nombre = (tramo.es_resto ? 'Resto ' : '') + tramo.temporada_codigo;

        if (!tramo.es_comprable) {
            return {
                titulo: 'Sin cubrir<br>' + nombre,
                clase: 'text-center bg-danger-subtle text-danger-emphasis',
                ayuda: 'Venta de ' + nombre + ' que el stock proyectado no alcanza a cubrir. '
                     + 'No entra en lo que hay que comprar: es el resto de la temporada en curso '
                     + '(' + UIUtils.formatearFechaCorta(tramo.temporada_desde) + ' a '
                     + UIUtils.formatearFechaCorta(tramo.temporada_hasta) + ') y un contenedor '
                     + 'nuevo ya no llega a tiempo.'
            };
        }

        return {
            titulo: (tramo.es_objetivo ? '★ Compra<br>' : 'Compra<br>') + nombre,
            clase: tramo.es_objetivo
                ? 'text-center bg-success text-white'
                : 'text-center bg-success-subtle text-success-emphasis',
            ayuda: 'Compra que hay que hacer para cubrir ' + nombre + ' ('
                 + UIUtils.formatearFechaCorta(tramo.temporada_desde) + ' a '
                 + UIUtils.formatearFechaCorta(tramo.temporada_hasta) + '). '
                 + (tramo.es_objetivo
                    ? 'Es la TEMPORADA OBJETIVO de esta solapa: lo que aporta esta versión al cashflow.'
                    : 'Tramo intermedio: queda como control, porque esa temporada la aporta su propia versión oficial.')
        };
    },

    /** Celda de un tramo, con el desglose en el tooltip. */
    celdaDeTramo(tramo) {
        const td = document.createElement('td');
        td.setAttribute('data-columna-dinamica', 'true');
        td.setAttribute('data-tramo-orden', tramo.orden);
        td.dataset.col = 'tramo-' + tramo.orden;
        td.className = 'text-end fw-bold '
            + (!tramo.es_comprable
                ? 'bg-danger-subtle text-danger-emphasis'
                : (tramo.es_objetivo ? 'bg-success-subtle text-success-emphasis' : 'bg-light'));

        TablaRendererUtils.pintarCeldaTramo(td, tramo);
        return td;
    },

    /**
     * Escribe el valor y el tooltip de una celda de tramo.
     * Separado de celdaDeTramo() porque al editar un índice se reusa la celda que ya
     * está en el DOM en vez de rehacer la fila entera.
     */
    pintarCeldaTramo(td, tramo) {
        td.textContent = FormatoUtils.formatearNumero(tramo.compra || 0);
        td.dataset.valor = tramo.compra || 0;

        const partes = [
            (tramo.es_resto ? 'Resto ' : '') + tramo.temporada_codigo,
            'Venta proyectada: ' + FormatoUtils.formatearNumero(tramo.venta_proyectada || 0),
            'Cubierto con stock: ' + FormatoUtils.formatearNumero(tramo.stock_aplicado || 0),
            (tramo.es_comprable ? 'A comprar: ' : 'Queda sin cubrir: ')
                + FormatoUtils.formatearNumero(tramo.compra || 0)
        ];
        if (tramo.compra_deficit_cobertura > 0) {
            partes.push('De eso, ' + FormatoUtils.formatearNumero(tramo.compra_deficit_cobertura)
                      + ' es déficit de stock de cobertura, no venta proyectada.');
        }
        td.title = partes.join('\n');
    },

    extraerColumnasVentasHistoricas(datos) {
        if (!datos || datos.length === 0) return [];
        
        const primeraFila = datos[0];
        const columnasExcluidas = [
            'RUBRO', 'CATEGORIA_PADRE', 'CANT_STOCK', 'CANT_STOCK_GUARDAR',
            'CANT_PEND_OC_VERANO', 'CANT_PEND_OC_INVIERNO', 'CANT_PEND_OC_ATEMPORAL',
            'INDICE_VARIACION', 'INDICE_VARIACION_INVIERNO', 'INDICE_ORIGINAL',
            'STOCK_COBERTURA', 'VENTA_PROY_VERANO', 'VENTA_PROY_INVIERNO',
            'COMPRA_PROYECTADA', 'STOCK_PROYECTADO',
            // Bases del cálculo, no columnas históricas: llevan VERANO/INVIERNO en el
            // nombre y si no se excluyen aparecen como una temporada más en la tabla.
            'VENTA_VERANO_ANTERIOR', 'VENTA_INVIERNO_ANTERIOR',
            'TEMPORADA_BASE_VERANO', 'TEMPORADA_BASE_INVIERNO'
        ];
        
        const columnasVenta = [];

        // Solo las columnas que corresponden a una temporada identificable, con la
        // misma regla que ProcesadorDatos::obtenerColumnasVentasSeguras(). Antes
        // entraba cualquier columna con VERANO, INVIERNO o VTA_ en el nombre, y las
        // del SP que no son temporadas (VTA_60D_LP, VTA_TEMPORADA_EN_CURSO) se
        // habrían mostrado como un año histórico más.
        for (const columna of Object.keys(primeraFila)) {
            if (!columnasExcluidas.includes(columna) &&
                TemporadaServidor.temporadaDeColumna(columna)) {
                columnasVenta.push(columna);
            }
        }

        // Orden cronológico real, no alfabético. Un sort() de texto agrupaba todos los
        // inviernos y después todos los veranos, así que las columnas no se leían como
        // una línea de tiempo. Se ordena por fecha de inicio de cada temporada, que es
        // lo único comparable entre verano e invierno.
        return columnasVenta.sort((a, b) =>
            TablaRendererUtils.inicioTemporadaColumna(a) - TablaRendererUtils.inicioTemporadaColumna(b)
        );
    },

    /**
     * Fecha de inicio (como número ordenable) de la temporada de una columna.
     * El SP numera el verano por el año en que termina: VTA_VERANO_26 empieza en
     * agosto de 2025. Las que no son de temporada van al final.
     */
    inicioTemporadaColumna(columna) {
        const m = String(columna).match(/(VERANO|INVIERNO)[\s_]*(\d{2})(?:\s*-\s*(\d{2}))?/i);
        if (!m) return Number.MAX_SAFE_INTEGER;

        const tipo = m[1].toUpperCase();
        const anoFin = 2000 + parseInt(m[3] !== undefined ? m[3] : m[2], 10);

        return tipo === 'VERANO'
            ? (anoFin - 1) * 100 + 8   // 1 de agosto del año anterior
            : anoFin * 100 + 2;        // 1 de febrero del mismo año
    },

    /**
     * Etiqueta de una columna histórica en la convención única de la app.
     *
     * Antes acá vivía un parche que a "VERANO 26" le restaba 1 y mostraba "VERANO 25":
     * acertaba el año de inicio pero dejaba el verano nombrado con un año suelto
     * —ambiguo, porque la temporada cruza dos— y no tocaba el invierno. Ahora la
     * traducción la hace el servidor (etiquetas_historicas) y acá solo se consulta,
     * para que las columnas, el encabezado, el Excel y la ayuda digan lo mismo.
     */
    formatearNombreColumna(columna) {
        return TemporadaServidor.etiquetaHistorica(columna);
    },

    /**
     * Escribe un número formateado y deja el valor crudo en data-valor.
     * El valor crudo es el que usa el orden por columna: ordenar leyendo el texto
     * obligaba a deshacer el separador de miles, que depende del formato regional.
     */
    escribirNumero(td, valor) {
        if (valor === null || valor === undefined) return;
        const numero = parseFloat(valor) || 0;
        td.textContent = FormatoUtils.formatearNumero(numero);
        td.dataset.valor = numero;
    },

    /**
     * Celda de índice, LP o Total Empresa, de una temporada.
     *
     * Con índice Total Empresa disponible, el TE es el editable y el LP queda como
     * termómetro de solo lectura. Sin él (Uruguay, o Argentina antes del script 06)
     * se mantiene lo de siempre: el LP mueve el cálculo y se edita, y la columna TE
     * muestra "—". Se decidió así para no dejar a Uruguay sin poder editar índices.
     *
     * @param {string} temporada 'verano' o 'invierno'
     * @param {string} tipo 'lp' o 'te'
     */
    celdaIndice(item, solapa, temporada, tipo) {
        const td = document.createElement('td');
        td.dataset.col = `indice-${temporada === 'invierno' ? 'inv' : 'ver'}-${tipo}`;
        const editable = (tipo === 'te') === !!item.TE_DISPONIBLE;

        if (editable) {
            const valor = parseFloat(temporada === 'invierno'
                ? (item.INDICE_VARIACION_INVIERNO ?? item.INDICE_VARIACION ?? 1.0)
                : (item.INDICE_VARIACION ?? 1.0));

            td.className = 'text-center editable-cell';
            td.dataset.temporada = temporada;
            td.dataset.indiceEditable = temporada;
            td.dataset.valor = valor;
            td.innerHTML = `<input type="number" class="indice-input" value="${valor.toFixed(2)}" step="0.01" min="0" max="10" readonly>`;
            td.title = (tipo === 'te'
                ? 'Índice Total Empresa (Locales Propios + Central): es el que mueve la venta proyectada, la compra y los tramos.'
                : 'Índice Locales Propios: en esta base todavía no hay índice Total Empresa, así que este es el que mueve la proyección.')
                + ' Clic para editarlo.'
                + (valor === 0 ? ' El SP devuelve 0 cuando no hubo venta el año anterior: la proyección usa 1,00.' : '');

            // El valor se lee al hacer clic y no al armar la fila: después de una
            // edición la celda muestra otro número que el que tenía al renderizarse.
            td.addEventListener('click', () => {
                const actual = parseFloat(td.querySelector('.indice-input')?.value) || 0;
                IndiceEditor.editarIndice(item.RUBRO, item.CATEGORIA_PADRE, actual, solapa, null, temporada);
            });
            return td;
        }

        if (tipo === 'lp') {
            const valor = parseFloat(item.INDICE_LP ?? 0);
            td.className = 'text-center bg-secondary-subtle text-emphasis';
            td.textContent = valor.toFixed(2);
            td.dataset.valor = valor;
            td.title = 'Índice Locales Propios: últimos 60 días contra los mismos 60 días del año anterior, '
                     + 'solo canal Locales Propios. Es un termómetro: no mueve la proyección.';
            return td;
        }

        td.className = 'text-center text-muted';
        td.textContent = '—';
        td.title = 'Sin índice Total Empresa: el SP de esta base todavía no lo calcula (Uruguay, o falta '
                 + 'correr el script 06). La proyección usa el índice Locales Propios.';
        return td;
    },

    // ----------------------------------------------------------------------
    // Venta de la temporada en curso: real + proyectada
    // ----------------------------------------------------------------------

    /**
     * Columna junto a la que va el grupo de la temporada en curso: la "Venta Proy."
     * del mismo tipo que la temporada que se está transitando. Null si el servidor
     * todavía no mandó la información.
     */
    anclaTemporadaEnCurso() {
        const info = TemporadaServidor.ventaEnCurso();
        if (!info) return null;
        return info.tipo === 'INVIERNO' ? 'venta-proy-inv' : 'venta-proy-ver';
    },

    /**
     * Venta proyectada del resto de la temporada en curso para una fila.
     *
     * Es el tramo "resto" que ya repartió el servidor (obtenerPeriodosProyeccion /
     * ventaProyectadaDeTramo), no una cuenta propia: así el número es el mismo que
     * está dentro de la venta proyectada y de la compra, y al editar el índice se
     * actualiza con el reparto que devuelve recalcular-tramos.
     */
    restoTemporadaEnCurso(item) {
        const tramo = (item.TRAMOS || []).find(t => t.es_resto);
        return tramo ? (parseFloat(tramo.venta_proyectada) || 0) : null;
    },

    /**
     * Vendido real de la temporada en curso, o null si no se puede mostrar: base sin
     * el script 06 (Uruguay) o SP corrido en otra temporada.
     */
    vendidoTemporadaEnCurso(item) {
        const info = TemporadaServidor.ventaEnCurso();
        if (!info || !info.disponible || !info.vigente) return null;
        if (item.VTA_TEMPORADA_EN_CURSO === null || item.VTA_TEMPORADA_EN_CURSO === undefined) return null;
        return parseFloat(item.VTA_TEMPORADA_EN_CURSO) || 0;
    },

    /** Por qué el vendido real no se muestra, para el tooltip del "—". */
    motivoSinVendido() {
        const info = TemporadaServidor.ventaEnCurso();
        if (info && info.disponible && !info.vigente) {
            return 'La venta real del SP es de la temporada anterior (el SP no corrió desde que '
                 + 'empezó ' + info.codigo + '). Volvé a correr el SP para verla.';
        }
        return 'Esta base no trae la venta real de la temporada en curso (Uruguay, o falta correr el script 06).';
    },

    /** Encabezados del grupo, en orden: Vendido, Resto, Total. */
    encabezadosTemporadaEnCurso() {
        const info = TemporadaServidor.ventaEnCurso();
        if (!info) return [];

        const corte = info.fecha_corte ? UIUtils.formatearFechaCorta(info.fecha_corte).slice(0, 5) : null;
        const leyenda = 'Vendido es venta REAL (Locales Propios + Central) desde el '
            + UIUtils.formatearFechaCorta(info.desde) + (corte ? ' hasta el ' + corte : '') + '. '
            + 'Resto es venta PROYECTADA desde hoy hasta el fin de ' + info.codigo
            + ', con el índice de esa temporada: es el mismo tramo que está dentro de la venta '
            + 'proyectada y de la compra. Total = Vendido + Resto. Es informativo: no entra en la '
            + 'compra ni en los tramos.';

        // Si el SP corrió hace más de un día, entre el corte y hoy hay días que no
        // están ni en lo vendido ni en el resto: se avisa con la cantidad.
        const aviso = info.disponible && info.vigente && info.dias_sin_contar > 0
            ? ` <i class="fas fa-exclamation-triangle text-warning" title="Quedan ${info.dias_sin_contar} día(s) sin contar entre el ${corte} y hoy: el SP no corrió desde entonces. No están ni en Vendido ni en Resto."></i>`
            : '';

        return [
            { col: 'tec-vendido', clase: 'text-center bg-info text-dark',
              html: `<span class="th-linea">Vendido</span><span class="th-linea th-periodo">${info.codigo}</span>`
                  + (corte && info.vigente ? `<span class="th-linea th-periodo">al ${corte}${aviso}</span>` : ''),
              ayuda: leyenda },
            { col: 'tec-resto', clase: 'text-center header-venta-proyectada',
              html: `<span class="th-linea">Resto</span><span class="th-linea th-periodo">${info.codigo}</span>`
                  + '<span class="th-linea th-periodo">proyectado</span>',
              ayuda: leyenda },
            { col: 'tec-total', clase: 'text-center bg-dark-subtle text-dark',
              html: `<span class="th-linea">Total</span><span class="th-linea th-periodo">${info.codigo}</span>`
                  + '<span class="th-linea th-periodo">real + proy.</span>',
              ayuda: leyenda }
        ];
    },

    /**
     * Agrega las tres celdas del grupo a una fila, justo después de su ancla.
     * Los valores los escribe pintarTemporadaEnCurso(), que también usa el editor.
     */
    agregarCeldasTemporadaEnCurso(fila, item) {
        const ancla = TablaRendererUtils.celdaDeColumna(fila, TablaRendererUtils.anclaTemporadaEnCurso());
        if (!ancla) return;

        const clases = {
            'tec-vendido': 'text-end bg-info-subtle',
            'tec-resto': 'text-end bg-primary-subtle text-primary-emphasis',
            'tec-total': 'text-end fw-bold'
        };
        let anterior = ancla;
        ['tec-vendido', 'tec-resto', 'tec-total'].forEach(col => {
            const td = document.createElement('td');
            td.dataset.col = col;
            td.className = clases[col];
            anterior.after(td);
            anterior = td;
        });

        TablaRendererUtils.pintarTemporadaEnCurso(fila, item);
    },

    /** Escribe Vendido, Resto y Total de una fila. */
    pintarTemporadaEnCurso(fila, item) {
        const c = (col) => TablaRendererUtils.celdaDeColumna(fila, col);
        if (!c('tec-vendido')) return;

        const vendido = TablaRendererUtils.vendidoTemporadaEnCurso(item);
        const resto = TablaRendererUtils.restoTemporadaEnCurso(item);

        const guion = (td, motivo) => {
            td.textContent = '—';
            delete td.dataset.valor;
            td.title = motivo;
        };

        if (vendido === null) {
            guion(c('tec-vendido'), TablaRendererUtils.motivoSinVendido());
        } else {
            TablaRendererUtils.escribirNumero(c('tec-vendido'), vendido);
            c('tec-vendido').title = 'Venta real de la temporada en curso';
        }

        if (resto === null) {
            guion(c('tec-resto'), 'Esta fila no tiene tramo de resto de temporada.');
        } else {
            TablaRendererUtils.escribirNumero(c('tec-resto'), resto);
            c('tec-resto').title = 'Venta proyectada del resto de la temporada en curso';
        }

        // El total solo tiene sentido con las dos partes: sumar el resto solo lo
        // haría pasar por el total de la temporada.
        if (vendido === null || resto === null) {
            guion(c('tec-total'), 'Hace falta la venta real y la proyectada para el total.');
        } else {
            TablaRendererUtils.escribirNumero(c('tec-total'), vendido + resto);
            c('tec-total').title = 'Vendido + Resto';
        }
    },

    /** Celda de una columna (data-col) dentro de una fila. */
    celdaDeColumna(fila, col) {
        return fila ? fila.querySelector(`td[data-col="${col}"]`) : null;
    },

    /**
     * Fila de un rubro/categoría en la tabla de una solapa.
     * Se compara contra el dataset y no con un selector armado con el texto: los
     * rubros traen comillas y barras que habría que escapar.
     */
    filaDeRegistro(solapa, rubro, categoria) {
        const tbody = document.getElementById(`tbody-${solapa}`);
        if (!tbody) return null;
        return Array.from(tbody.querySelectorAll('tr.fila-datos'))
            .find(f => f.dataset.rubro === rubro && f.dataset.categoria === categoria) || null;
    },

    /**
     * Escribe en la fila todo lo que cambia cuando se edita un índice: los índices
     * editables, el original, las ventas proyectadas y la compra. Lo usan el render
     * y el editor, así una celda no puede quedar con un número que el otro no escribe.
     */
    actualizarCeldasCalculadas(fila, item) {
        if (!fila) return;
        const c = (col) => TablaRendererUtils.celdaDeColumna(fila, col);

        const original = c('indice-original');
        if (original) {
            const valor = TablaRendererUtils.obtenerIndiceOriginal(item);
            original.textContent = valor.toFixed(2);
            original.dataset.valor = valor;
        }

        ['verano', 'invierno'].forEach(temporada => {
            const td = fila.querySelector(`td[data-indice-editable="${temporada}"]`);
            if (!td) return;
            const valor = parseFloat(temporada === 'invierno'
                ? (item.INDICE_VARIACION_INVIERNO ?? item.INDICE_VARIACION ?? 1.0)
                : (item.INDICE_VARIACION ?? 1.0));
            const input = td.querySelector('.indice-input');
            if (input) input.value = valor.toFixed(2);
            td.dataset.valor = valor;
        });

        if (c('venta-proy-ver')) TablaRendererUtils.escribirNumero(c('venta-proy-ver'), item.VENTA_PROY_VERANO || 0);
        if (c('venta-proy-inv')) TablaRendererUtils.escribirNumero(c('venta-proy-inv'), item.VENTA_PROY_INVIERNO || 0);

        const compra = c('compra-proyectada');
        if (compra) {
            const valor = item.COMPRA_PROYECTADA || 0;
            TablaRendererUtils.escribirNumero(compra, valor);
            compra.className = `text-end bg-success-subtle text-success-emphasis fw-bold ${FormatoUtils.obtenerClaseValor(valor)}`;
        }
    },

    // NUEVO: Obtener índice original del registro
    obtenerIndiceOriginal(item) {
        // Prioridad 1: Objeto de respaldo en memoria
        if (item._indicesOriginales && item._indicesOriginales.verano !== undefined) {
            return parseFloat(item._indicesOriginales.verano);
        }
        // Prioridad 2: Propiedad de BD o procesador
        if (item.INDICE_VAR_ORIGINAL !== undefined && item.INDICE_VAR_ORIGINAL !== null) {
            return parseFloat(item.INDICE_VAR_ORIGINAL);
        }
        if (item.INDICE_ORIGINAL !== undefined && item.INDICE_ORIGINAL !== null) {
            return parseFloat(item.INDICE_ORIGINAL);
        }
        if (item._indiceOriginal !== undefined && item._indiceOriginal !== null) {
            return parseFloat(item._indiceOriginal);
        }
        // Fallback: Si no hay índice original guardado, registrarlo ahora y usar el actual
        const orig = parseFloat(item.INDICE_VARIACION || 1.0);
        item.INDICE_VAR_ORIGINAL = orig;
        item.INDICE_ORIGINAL = orig;
        item._indiceOriginal = orig;
        return orig;
    }
};

// ========================================
// CLASE PRINCIPAL TABLARENDERER
// ========================================

class TablaRenderer {
    
    /**
     * Renderizar tabla de Compra Proyectada Verano (MODIFICADO)
     */
    static renderizarTablaVerano(datos, etiquetas = null) {
        const tbody = document.getElementById('tbody-verano');
        
        if (!datos || datos.length === 0) {
            TablaRendererUtils.mostrarTablaVacia(tbody, 10, 'No hay datos disponibles para la proyección de verano');
            return;
        }

        TablaRendererUtils.limpiarTabla('verano');
        TablaRenderer.agregarColumnasVentasHistoricas('verano', datos);
        TablaRenderer.rotularEncabezadosIndices('verano', datos);
        
        tbody.innerHTML = '';
        
        datos.forEach((item, index) => {
            const tr = TablaRenderer.crearFilaCompraCompleta(item, index, datos, 'verano');
            tbody.appendChild(tr);
        });
        
        if (etiquetas) {
            TablaRenderer.actualizarHeadersEtiquetas('verano', etiquetas);
        }
        
        // NUEVO: Restaurar marcas de índices editados
        setTimeout(() => {
            TablaRenderer.restaurarMarcasIndicesEditados('verano', datos);
        }, 100);
        
        console.log(`✅ Tabla verano renderizada: ${datos.length} registros`);
    }

    /**
     * Renderizar tabla de Compra Proyectada Invierno (MODIFICADO)
     */
    static renderizarTablaInvierno(datos, etiquetas = null) {
        const tbody = document.getElementById('tbody-invierno');
        
        if (!datos || datos.length === 0) {
            TablaRendererUtils.mostrarTablaVacia(tbody, 10, 'No hay datos disponibles para la proyección de invierno');
            return;
        }

        TablaRendererUtils.limpiarTabla('invierno');
        TablaRenderer.agregarColumnasVentasHistoricas('invierno', datos);
        TablaRenderer.rotularEncabezadosIndices('invierno', datos);
        
        tbody.innerHTML = '';
        
        datos.forEach((item, index) => {
            const tr = TablaRenderer.crearFilaCompraCompleta(item, index, datos, 'invierno');
            tbody.appendChild(tr);
        });
        
        if (etiquetas) {
            TablaRenderer.actualizarHeadersEtiquetas('invierno', etiquetas);
        }
        
        // NUEVO: Restaurar marcas de índices editados
        setTimeout(() => {
            TablaRenderer.restaurarMarcasIndicesEditados('invierno', datos);
        }, 100);
        
        console.log(`✅ Tabla invierno renderizada: ${datos.length} registros`);
    }

    /**
     * Renderizar tabla de Stock Proyectado (sin cambios)
     */
    static renderizarTablaStock(datos) {
        const tbody = document.getElementById('tbody-stock');
        
        if (!datos || datos.length === 0) {
            TablaRendererUtils.mostrarTablaVacia(tbody, 9, 'No hay datos disponibles para el stock proyectado');
            return;
        }

        TablaRendererUtils.limpiarTabla('stock');
        tbody.innerHTML = '';
        
        datos.forEach((item, index) => {
            const tr = TablaRenderer.crearFilaStock(item, index);
            tbody.appendChild(tr);
        });
        
        console.log(`✅ Tabla stock renderizada: ${datos.length} registros`);
    }

    /**
     * Ajusta los tooltips de los encabezados de índice según haya o no índice
     * Total Empresa. Sin él la columna LP es la editable y la TE queda en "—", así
     * que el encabezado tiene que decir cuál de las dos mueve la proyección.
     */
    static rotularEncabezadosIndices(solapa, datos) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        if (!tabla) return;

        const teDisponible = (datos || []).some(d => d.TE_DISPONIBLE);
        tabla.querySelectorAll('th[data-indice-tipo]').forEach(th => {
            const esTe = th.dataset.indiceTipo === 'te';
            if (esTe) {
                th.title = teDisponible
                    ? 'Índice Total Empresa (Locales Propios + Central): últimos 60 días contra los mismos '
                      + '60 días del año anterior, mismo universo que la venta histórica. Es el que mueve la '
                      + 'venta proyectada, la compra y los tramos. Editable.'
                    : 'Sin índice Total Empresa en esta base (Uruguay, o falta correr el script 06).';
            } else {
                th.title = teDisponible
                    ? 'Índice Locales Propios: últimos 60 días contra los mismos 60 días del año anterior, '
                      + 'solo canal Locales Propios. Termómetro: no mueve la proyección.'
                    : 'Índice Locales Propios. Sin índice Total Empresa en esta base, es el que mueve la '
                      + 'proyección y el que se edita.';
            }
        });
    }

    /**
     * Agregar columnas de ventas históricas (solo para compras)
     */
    static agregarColumnasVentasHistoricas(solapa, datos) {
        if (!datos || datos.length === 0) return;
        
        const tabla = document.getElementById(`tabla-${solapa}`);
        const thead = tabla.querySelector('thead tr');
        
        if (tabla.getAttribute('data-columnas-procesadas') === 'true') {
            return;
        }
        
        // Grupo de la temporada en curso, pegado a la Venta Proy. de su mismo tipo:
        // es la otra mitad de esa temporada (lo ya vendido) y su total.
        const ancla = thead.querySelector(`th[data-col="${TablaRendererUtils.anclaTemporadaEnCurso()}"]`);
        if (ancla) {
            let anterior = ancla;
            TablaRendererUtils.encabezadosTemporadaEnCurso().forEach(e => {
                const th = document.createElement('th');
                th.className = e.clase;
                th.innerHTML = e.html;
                th.title = e.ayuda;
                th.dataset.col = e.col;
                th.setAttribute('data-columna-dinamica', 'true');
                anterior.after(th);
                anterior = th;
            });
        }

        // Los tramos van PRIMERO, pegados a Compra Proyectada: son la apertura de esa
        // misma columna, y ponerlos después de los años históricos los habría dejado
        // al final de la tabla, lejos del total que abren.
        const tramos = TablaRendererUtils.tramosDeSolapa(datos);
        tramos.forEach(tramo => {
            const info = TablaRendererUtils.encabezadoDeTramo(tramo);
            const th = document.createElement('th');
            th.className = info.clase;
            th.innerHTML = info.titulo;
            th.title = info.ayuda;
            th.setAttribute('data-columna-dinamica', 'true');
            th.setAttribute('data-tramo-orden', tramo.orden);
            th.dataset.col = 'tramo-' + tramo.orden;
            th.style.fontSize = '0.7rem';
            th.style.whiteSpace = 'nowrap';
            thead.appendChild(th);
        });

        const columnasVentasHistoricas = TablaRendererUtils.extraerColumnasVentasHistoricas(datos);

        if (columnasVentasHistoricas.length === 0) {
            tabla.setAttribute('data-columnas-procesadas', 'true');
            return;
        }

        console.log(`Agregando ${columnasVentasHistoricas.length} columnas históricas a ${solapa}`);

        columnasVentasHistoricas.forEach(columna => {
            const th = document.createElement('th');
            th.className = 'text-center bg-info-subtle text-dark';
            th.textContent = TablaRendererUtils.formatearNombreColumna(columna);
            th.dataset.col = 'hist-' + columna;
            th.setAttribute('data-columna-dinamica', 'true');
            th.style.fontSize = '0.7rem';
            th.style.whiteSpace = 'nowrap';
            thead.appendChild(th);
        });
        
        tabla.setAttribute('data-columnas-procesadas', 'true');
    }

    /**
     * CORREGIDO: Crear fila completa para compras con ÍNDICE ORIGINAL
     */
    /**
     * Fila de una solapa de compra.
     *
     * Cada celda lleva data-col y la fila lleva rubro y categoría. Antes la fila se
     * buscaba por su posición en el tbody y cada celda por su número de columna
     * (children[4] era el índice de verano, [10] la compra): agregar una columna
     * desarmaba en silencio la edición de índices, las marcas de "editado" y la fila
     * de totales, y ordenar las filas habría editado otra fila que la clickeada.
     */
    static crearFilaCompraCompleta(item, index, todosLosDatos, solapa) {
        const tr = document.createElement('tr');
        tr.className = 'fila-datos';
        tr.dataset.rubro = item.RUBRO || '';
        tr.dataset.categoria = item.CATEGORIA_PADRE || '';

        const ventaVeranoAnterior = TablaRendererUtils.buscarVentaHistoricaCorrecta(item, 'VERANO');
        const ventaInviernoAnterior = TablaRendererUtils.buscarVentaHistoricaCorrecta(item, 'INVIERNO');

        const celda = (col, clase, valor, titulo = '') => {
            const td = document.createElement('td');
            td.dataset.col = col;
            td.className = clase;
            if (titulo) td.title = titulo;
            TablaRendererUtils.escribirNumero(td, valor);
            return td;
        };

        tr.appendChild(celda('rubro', 'fw-medium', null));
        tr.lastChild.textContent = item.RUBRO || '';
        tr.appendChild(celda('categoria', '', null));
        tr.lastChild.textContent = item.CATEGORIA_PADRE || '';

        tr.appendChild(celda('stock-proyectado', 'text-end bg-info-subtle', item.STOCK_PROYECTADO || 0));
        tr.appendChild(celda('indice-original', 'text-center bg-secondary-subtle text-emphasis', null,
            'Índice editable tal como vino del SP, antes de cualquier edición (no editable).'));

        tr.appendChild(TablaRendererUtils.celdaIndice(item, solapa, 'verano', 'lp'));
        tr.appendChild(TablaRendererUtils.celdaIndice(item, solapa, 'verano', 'te'));
        tr.appendChild(celda('venta-ver-ant', 'text-end bg-info-subtle', ventaVeranoAnterior, 'Venta histórica verano anterior'));
        tr.appendChild(celda('venta-proy-ver', 'text-end bg-primary-subtle text-primary-emphasis fw-bold', null, 'Venta proyectada verano'));

        tr.appendChild(TablaRendererUtils.celdaIndice(item, solapa, 'invierno', 'lp'));
        tr.appendChild(TablaRendererUtils.celdaIndice(item, solapa, 'invierno', 'te'));
        tr.appendChild(celda('venta-inv-ant', 'text-end bg-info-subtle', ventaInviernoAnterior, 'Venta histórica invierno anterior'));
        tr.appendChild(celda('venta-proy-inv', 'text-end bg-primary-subtle text-primary-emphasis fw-bold', null, 'Venta proyectada invierno'));

        tr.appendChild(celda('compra-proyectada', '', null, 'Compra proyectada'));

        // Vendido / Resto / Total de la temporada en curso, al lado de su Venta Proy.
        TablaRendererUtils.agregarCeldasTemporadaEnCurso(tr, item);

        // Compra abierta por tramo, en el mismo orden que los encabezados: pegada a la
        // compra total y antes de los años históricos.
        (item.TRAMOS || []).forEach(tramo => {
            tr.appendChild(TablaRendererUtils.celdaDeTramo(tramo));
        });

        // Agregar columnas históricas dinámicas
        const columnasHistoricas = TablaRendererUtils.extraerColumnasVentasHistoricas(todosLosDatos);
        columnasHistoricas.forEach(columna => {
            const valor = item[columna] || 0;
            const td = celda('hist-' + columna, 'text-end bg-light', valor,
                `${columna}: ${FormatoUtils.formatearNumero(valor)}`);
            td.setAttribute('data-columna-dinamica', 'true');
            td.style.fontSize = '0.75rem';
            tr.appendChild(td);
        });

        // Los valores que cambian al editar un índice se escriben con la misma función
        // que usa el editor, para que el render y la edición no puedan diferir.
        TablaRendererUtils.actualizarCeldasCalculadas(tr, item);

        return tr;
    }

    /**
     * Crear fila para stock proyectado (SIN cambios)
     */
    static crearFilaStock(item, index) {
        const tr = document.createElement('tr');
        tr.className = 'fila-datos';
        
        const stock = FormatoUtils.formatearNumero(item.STOCK || item.CANT_STOCK || 0);
        const stockGuardar = FormatoUtils.formatearNumero(item.STOCK_GUARDAR || item.CANT_STOCK_GUARDAR || 0);
        const comprasVerano = FormatoUtils.formatearNumero(item.COMPRAS_VERANO || item.CANT_PEND_OC_VERANO || 0);
        const comprasInvierno = FormatoUtils.formatearNumero(item.COMPRAS_INVIERNO || item.CANT_PEND_OC_INVIERNO || 0);
        const comprasAtemporal = FormatoUtils.formatearNumero(item.COMPRAS_ATEMPORAL || item.CANT_PEND_OC_ATEMPORAL || 0);
        const stockCobertura = FormatoUtils.formatearNumero(item.STOCK_COBERTURA || 0);
        const stockProyectado = FormatoUtils.formatearNumero(item.STOCK_PROYECTADO || 0);
        
        tr.innerHTML = `
            <td class="fw-medium">${item.RUBRO || ''}</td>
            <td>${item.CATEGORIA_PADRE || ''}</td>
            <td class="text-end bg-info-subtle" title="Stock actual">${stock}</td>
            <td class="text-end bg-warning-subtle" title="Stock a guardar">${stockGuardar}</td>
            <td class="text-end bg-success-subtle" title="Compras pendientes verano">${comprasVerano}</td>
            <td class="text-end bg-success-subtle" title="Compras pendientes invierno">${comprasInvierno}</td>
            <td class="text-end bg-success-subtle" title="Compras pendientes atemporal">${comprasAtemporal}</td>
            <td class="text-end bg-danger-subtle" title="Stock cobertura">${stockCobertura}</td>
            <td class="text-end bg-primary-subtle text-primary-emphasis fw-bold" title="Stock proyectado final">${stockProyectado}</td>
        `;
        
        return tr;
    }

    /**
     * Funciones de utilidad
     */
    static resetearEstadoTabla(solapa) {
        return TablaRendererUtils.resetearEstadoTabla(solapa);
    }

    static aplicarFiltroVisual(solapa, termino = '') {
        const tabla = document.getElementById(`tbody-${solapa}`);
        const filas = tabla.querySelectorAll('.fila-datos');
        
        if (!termino || termino.length < 2) {
            filas.forEach(fila => {
                fila.style.display = '';
                fila.style.opacity = '1';
            });
            return filas.length;
        }
        
        const terminoLower = termino.toLowerCase();
        let coincidencias = 0;
        
        filas.forEach(fila => {
            // Las solapas de compra marcan rubro y categoría en la fila; las demás
            // tablas que usan este filtro siguen teniéndolos en las dos primeras celdas.
            const rubro = (fila.dataset.rubro ?? fila.children[0]?.textContent ?? '').toLowerCase();
            const categoria = (fila.dataset.categoria ?? fila.children[1]?.textContent ?? '').toLowerCase();
            
            if (rubro.includes(terminoLower) || categoria.includes(terminoLower)) {
                fila.style.display = '';
                fila.style.opacity = '1';
                coincidencias++;
            } else {
                fila.style.display = 'none';
                fila.style.opacity = '0.5';
            }
        });
        
        return coincidencias;
    }

    /**
     * Resalta la fila de un rubro/categoría. Se busca por rubro y categoría y no por
     * posición: con filtros u orden aplicados, el n-ésimo hijo del tbody es otra fila.
     */
    static resaltarFila(solapa, rubro, categoria, resaltar = true) {
        const fila = TablaRendererUtils.filaDeRegistro(solapa, rubro, categoria);

        if (fila) {
            if (resaltar) {
                fila.classList.add('table-warning');
                fila.style.transform = 'scale(1.02)';
                fila.style.boxShadow = '0 4px 8px rgba(255, 193, 7, 0.3)';
            } else {
                fila.classList.remove('table-warning');
                fila.style.transform = '';
                fila.style.boxShadow = '';
            }
        }
    }

    /**
     * Renderiza la tabla de historial de compras proyectadas.
     * @param {Array} datos Los datos del historial a renderizar.
     */
    /**
     * Celda de temporada objetivo de una fila del historial.
     *
     * Muestra tres cosas en un solo lugar para no sumar columnas: la temporada,
     * si la versión es la oficial (bandera) y si es parcial. Distingue además
     * las temporadas guardadas en la cabecera de las derivadas de la fecha, que
     * son las versiones viejas todavía sin migrar.
     */
    static badgeTemporadaObjetivo(item) {
        const codigo = item.temporada_objetivo || 's/d';
        const oficial = parseInt(item.es_oficial, 10) === 1;
        const parcial = item.es_completa !== undefined && item.es_completa !== null
                        && parseInt(item.es_completa, 10) === 0;
        const derivada = !!item.temporada_objetivo_derivada;

        const rango = item.temporada_objetivo_desde
            ? `${UIUtils.formatearFechaCorta(item.temporada_objetivo_desde)} a ${UIUtils.formatearFechaCorta(item.temporada_objetivo_hasta)}`
            : '';
        const titulo = derivada
            ? `${rango} (deducida de la fecha de guardado: esta versión es anterior a la cabecera)`
            : rango;

        let html = `<span class="badge bg-info text-dark" title="${titulo}">${codigo}`;
        if (derivada) html += ' *';
        html += '</span>';

        if (oficial) {
            html += ' <i class="fas fa-flag text-success" title="Versión oficial"></i>';
        }
        if (parcial) {
            html += ' <span class="badge bg-warning text-dark" title="Versión parcial: no puede ser oficial">parcial</span>';
        }
        return html;
    }

    static renderizarTablaHistorial(datos) {
        const tbody = document.getElementById('tbody-historial');
        if (!tbody) {
            console.error('No se encontró el cuerpo de la tabla de historial (tbody-historial).');
            return;
        }

        if (!datos || datos.length === 0) {
            tbody.innerHTML = `<tr><td colspan="15" class="text-center text-muted py-4">No se encontraron resultados para los filtros aplicados.</td></tr>`;
            return;
        }

        const html = datos.map(item => {
            const fechaFormateada = FormatoUtils.formatearFechaHora(item.fecha_guardado);

            return `
                <tr>
                    <td>${fechaFormateada}</td>
                    <td>${item.nombre_presupuesto || ''}</td>
                    <td><span class="badge bg-secondary">${item.temporada || ''}</span></td>
                    <td>${TablaRenderer.badgeTemporadaObjetivo(item)}</td>
                    <td>${item.rubro || ''}</td>
                    <td>${item.categoria_padre || ''}</td>
                    <td class="text-end">${FormatoUtils.formatearNumero(item.stock_proyectado)}</td>
                    <td class="text-center bg-warning-subtle">${parseFloat(item.indice_verano_variacion || 0).toFixed(2)}</td>
                    <td class="text-end">${FormatoUtils.formatearNumero(item.venta_verano_anterior)}</td>
                    <td class="text-center bg-primary-subtle">${FormatoUtils.formatearNumero(item.venta_proyectada_verano)}</td>
                    <td class="text-center bg-warning-subtle">${parseFloat(item.indice_invierno_variacion || 0).toFixed(2)}</td>
                    <td class="text-end">${FormatoUtils.formatearNumero(item.venta_invierno_anterior)}</td>
                    <td class="text-center bg-primary-subtle">${FormatoUtils.formatearNumero(item.venta_proyectada_invierno)}</td>
                    <td class="text-center bg-success-subtle"><strong>${FormatoUtils.formatearNumero(item.compra_proyectada)}</strong></td>
                    <td class="text-center bg-success-subtle">${TablaRenderer.desgloseTramos(item)}</td>
                </tr>
            `;
        }).join('');

        tbody.innerHTML = html;
    }

    /**
     * Compra por tramo de una fila del historial, como badges dentro de una celda.
     *
     * No se usan columnas fijas porque el historial mezcla versiones de distintas
     * solapas y fechas: una versión de verano tiene tres tramos y una de invierno dos,
     * y las temporadas cambian según cuándo se guardó. Con columnas, cada fila habría
     * necesitado un encabezado distinto.
     *
     * Distingue los tres casos que importan: el tramo objetivo (lo que esta versión le
     * aporta al cashflow), los intermedios (control) y el resto de la temporada en
     * curso, que no es mercadería a comprar.
     */
    static desgloseTramos(item) {
        const tramos = item.tramos || [];

        if (tramos.length === 0) {
            // Se distingue "esta versión no tiene reparto" de "todavía no se migró":
            // la migración marca explícitamente a las que no pudo reconstruir.
            if (item.tramos_estado === 'SIN_REPARTO') {
                const motivo = item.tramos_observacion || 'No se pudo reconstruir el reparto.';
                return `<span class="badge bg-secondary" title="${TablaRendererUtils.escaparComillas(motivo)}">sin reparto</span>`;
            }
            return '<span class="text-muted small" title="Esta versión es anterior al reparto por tramo, '
                 + 'o todavía no se corrió la migración en esta base.">—</span>';
        }

        return tramos.map(t => {
            const nombre = (t.es_resto ? 'Resto ' : '') + t.temporada_codigo;
            const valor = FormatoUtils.formatearNumero(t.compra || 0);

            let clase = 'bg-light text-dark border';
            let ayuda = `Tramo intermedio. Queda como control: ${nombre} la aporta su propia versión oficial.`;

            if (!t.es_comprable) {
                clase = 'bg-danger-subtle text-danger-emphasis';
                ayuda = `Venta de ${nombre} que quedó sin cubrir. No es mercadería a comprar: `
                      + 'es el resto de la temporada que estaba en curso al guardar.';
            } else if (t.es_objetivo) {
                clase = 'bg-success text-white';
                ayuda = `Temporada objetivo de esta versión: es lo que le aporta al cashflow.`;
            }

            if (t.compra_deficit_cobertura > 0) {
                ayuda += ` Incluye ${FormatoUtils.formatearNumero(t.compra_deficit_cobertura)} `
                       + 'de déficit de stock de cobertura.';
            }

            return `<span class="badge ${clase} me-1" title="${TablaRendererUtils.escaparComillas(ayuda)}">`
                 + `${nombre}: ${valor}</span>`;
        }).join('');
    }

    static actualizarHeadersEtiquetas(solapa, etiquetas) {
        if (!etiquetas) return;
        
        const headersById = [
            { id: 'header-venta-verano', key: 'verano' },
            { id: 'header-venta-invierno', key: 'invierno' },
            { id: 'header-venta-verano-inv', key: 'verano' },
            { id: 'header-venta-invierno-inv', key: 'invierno' }
        ];
        
        headersById.forEach(header => {
            const element = document.getElementById(header.id);
            if (element && etiquetas[header.key]) {
                element.textContent = etiquetas[header.key];
                element.setAttribute('title', etiquetas[header.key]);
            }
        });
    }

    static obtenerEstadoTabla(solapa) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        const tbody = tabla.querySelector('tbody');
        
        return {
            columnas_procesadas: tabla.getAttribute('data-columnas-procesadas') === 'true',
            tabla_limpia: tabla.getAttribute('data-limpia') === 'true',
            total_headers: tabla.querySelectorAll('thead th').length,
            total_filas: tbody.querySelectorAll('.fila-datos').length,
            columnas_dinamicas: tabla.querySelectorAll('[data-columna-dinamica]').length
        };
    }

    // Compatibilidad
    static mostrarTablaVacia(tbody, colspan, mensaje) {
        return TablaRendererUtils.mostrarTablaVacia(tbody, colspan, mensaje);
    }

    static limpiarTabla(solapa) {
        return TablaRendererUtils.limpiarTabla(solapa);
    }

    static escaparComillas(texto) {
        return TablaRendererUtils.escaparComillas(texto);
    }

    static buscarVentaHistoricaCorrecta(item, temporada) {
        return TablaRendererUtils.buscarVentaHistoricaCorrecta(item, temporada);
    }

    /**
     * Inicialización simplificada
     */
    static init() {
        console.log('✅ TablaRenderer (con índice original) inicializado correctamente');
    }

    static diagnosticarTablas() {
        const info = {};
        
        ['verano', 'invierno', 'stock'].forEach(solapa => {
            info[solapa] = TablaRenderer.obtenerEstadoTabla(solapa);
        });
        
        console.group('📊 DIAGNÓSTICO TABLAS');
        console.table(info);
        console.groupEnd();
        
        return info;
    }

    static limpiarTodasLasTablas() {
        ['verano', 'invierno', 'stock'].forEach(solapa => {
            TablaRenderer.resetearEstadoTabla(solapa);
            TablaRenderer.limpiarTabla(solapa);
        });
        console.log('✅ Todas las tablas limpiadas');
    }

    /**
     * NUEVO: Restaurar marcas de índices editados al renderizar
     */
    static restaurarMarcasIndicesEditados(solapa, datos) {
        if (!datos || datos.length === 0) return;
        
        const tbody = document.getElementById(`tbody-${solapa}`);
        if (!tbody) return;
        
        const filas = tbody.querySelectorAll('tr.fila-datos');
        
        filas.forEach(fila => {
            const rubro = fila.dataset.rubro;
            const categoria = fila.dataset.categoria;
            
            if (!rubro || !categoria) return;
            
            // Buscar el registro correspondiente
            const registro = datos.find(item => 
                item.RUBRO === rubro && 
                (item.CATEGORIA_PADRE === categoria || item.CATEGORIA === categoria)
            );
            
            if (registro && registro._indicesEditados) {
                // Restaurar marcas para cada temporada editada
                Object.entries(registro._indicesEditados).forEach(([temporada, info]) => {
                    IndiceEditor.aplicarMarcasVisualesEnFila(fila, temporada, info.valorActual);
                    console.log(`🎨 Marca de edición restaurada: ${rubro} - ${categoria} (${temporada})`);
                });
            }
        });
        
        console.log(`✅ Marcas de índices editados restauradas en solapa ${solapa}`);
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    TablaRenderer.init();
    
    // Funciones globales para debugging
    window.diagnosticarTablas = () => TablaRenderer.diagnosticarTablas();
    window.limpiarTodasLasTablas = () => TablaRenderer.limpiarTodasLasTablas();
    
    console.log('✅ TablaRenderer con índice original cargado');
});