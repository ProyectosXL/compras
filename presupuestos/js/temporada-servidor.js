// Acceso único a los datos de temporada que calcula el servidor.
// Archivo: presupuestos/js/temporada-servidor.js
//
// El navegador NO calcula temporadas ni días: los recibe en `info_temporada` y los
// lee desde acá. Antes cada calculadora rehacía las cuentas con `new Date()`, y como
// medía el resto de temporada en milisegundos contra el 31/01 23:59:59, el mismo día
// daba 132 días a la mañana y 131 a la tarde. Editar un índice a la tarde cambiaba el
// número aunque se reescribiera el mismo valor, porque el render inicial lo hace PHP
// y el recálculo lo hacía el JS.
//
// Se descartó replicar en JS el redondeo del servidor: mientras existieran dos
// implementaciones iban a volver a separarse. Con una sola fuente, además, el
// resultado deja de depender del reloj y la zona horaria del cliente.

const TemporadaServidor = {

    /**
     * Payload `info_temporada` tal como lo dejó main.js al cargar los datos.
     */
    info() {
        return (window.presupuestoApp && window.presupuestoApp.temporadaInfo) || null;
    },

    disponible() {
        const info = TemporadaServidor.info();
        return !!(info && info.temporada_actual);
    },

    /**
     * Temporada en curso, en la forma que esperan las calculadoras.
     * `enCurso` queda siempre en true: el servidor solo devuelve verano o invierno,
     * no hay meses fuera de temporada. Se mantiene la propiedad porque las
     * calculadoras ya ramificaban sobre ella.
     */
    temporadaActual() {
        const info = TemporadaServidor.info();
        if (!info || !info.temporada_actual) return null;

        const t = info.temporada_actual;
        return {
            tipo: t.temporada,
            enCurso: true,
            codigo: t.codigo,
            desde: t.desde,
            hasta: t.hasta,
            dias: t.dias,
            descripcion: t.codigo
        };
    },

    /** Días que faltan para terminar la temporada en curso, incluido hoy. */
    diasRestantes() {
        const info = TemporadaServidor.info();
        return info ? parseInt(info.dias_restantes, 10) : 0;
    },

    /** Días totales de la temporada en curso (la que se prorratea). */
    diasTotales() {
        const info = TemporadaServidor.info();
        return info ? parseInt(info.dias_totales, 10) : 0;
    },

    /**
     * Períodos que cubre cada columna proyectada en una solapa dada.
     * @param {string} solapa 'verano' o 'invierno'
     */
    periodos(solapa) {
        const info = TemporadaServidor.info();
        if (!info || !info.periodos) return null;
        return info.periodos[solapa] || null;
    },

    /**
     * Temporada a la que corresponde una columna de venta histórica, o null.
     *
     * Port literal de PresupuestoCalculos::temporadaDeColumna(). Vive acá —y no en
     * cada calculadora— porque es la regla que decide qué columna es una venta y
     * cuál no, y con una copia por calculadora volvían a separarse.
     *
     * @returns {{tipo: string, anoFin: number, desde: string}|null}
     */
    temporadaDeColumna(columna) {
        const nombre = String(columna);

        // Las cantidades pendientes de OC llevan VERANO/INVIERNO en el nombre pero
        // no son ventas históricas. Sin este filtro, una fila sin ventas de verano
        // termina proyectando sobre CANT_PEND_OC_VERANO, o sea sobre lo ya pedido.
        if (/CANT_PEND_OC/i.test(nombre)) return null;

        // Par inicio-fin ("VERANO 25-26"): el año de cierre es el segundo.
        let m = nombre.match(/(VERANO|INVIERNO)[\s_]*(\d{2})\s*-\s*(\d{2})/i);
        if (m) {
            m = [m[1], m[3]];
        } else {
            const simple = nombre.match(/(VERANO|INVIERNO)[\s_]*(\d{2})/i);
            if (!simple) return null;
            m = [simple[1], simple[2]];
        }

        const tipo = m[0].toUpperCase();
        const anoFin = 2000 + parseInt(m[1], 10);

        // Mismo calendario que construirTemporada(): el verano arranca el 01/08 del
        // año anterior al de cierre y el invierno el 01/02 del año de cierre.
        const desde = tipo === 'VERANO' ? `${anoFin - 1}-08-01` : `${anoFin}-02-01`;

        return { tipo, anoFin, desde };
    },

    /**
     * Venta anterior sobre la que se proyecta una fila, con la MISMA regla que PHP.
     *
     * Prioridad al valor que ya eligió el servidor (VENTA_*_ANTERIOR): es el que usó
     * el render y el que se guarda en la versión. El respaldo replica
     * PresupuestoCalculos::extraerColumnaVentaAnterior(): la temporada con ventas más
     * reciente por FECHA REAL de inicio, no por el número que aparezca en el nombre.
     *
     * Antes cada calculadora buscaba por su cuenta, aceptando cualquier columna que
     * dijera VERANO/INVIERNO y ordenando por los dos primeros dígitos del nombre.
     * Como CANT_PEND_OC_VERANO no tiene dígitos quedaba con año 0 y perdía contra
     * cualquier VTA_*, así que casi siempre coincidía con PHP. Pero en las filas sin
     * ninguna venta de verano era la única candidata y ganaba: en Uruguay, tres
     * rubros (ZAPATO, BOLSO, TOALLON) pasaban a proyectar sobre las unidades pedidas
     * en vez de sobre 0. El error solo aparecía al editar un índice, porque el render
     * inicial lo hace PHP.
     *
     * @param {object} registro fila de datos
     * @param {string} tipo 'VERANO' o 'INVIERNO'
     */
    ventaAnteriorDe(registro, tipo) {
        const campoServidor = tipo === 'VERANO' ? 'VENTA_VERANO_ANTERIOR' : 'VENTA_INVIERNO_ANTERIOR';
        const delServidor = registro ? registro[campoServidor] : undefined;
        if (delServidor !== undefined && delServidor !== null && delServidor !== '') {
            return parseFloat(delServidor) || 0;
        }

        const candidatas = [];
        for (const [columna, valor] of Object.entries(registro || {})) {
            if (valor === null || valor === '' || isNaN(valor) || parseFloat(valor) <= 0) continue;
            if (/PROY/i.test(columna)) continue;

            const t = TemporadaServidor.temporadaDeColumna(columna);
            if (!t || t.tipo !== tipo) continue;

            candidatas.push({ columna, valor: parseFloat(valor), desde: t.desde });
        }

        if (!candidatas.length) return 0;

        // Más reciente primero, por fecha de inicio de temporada. Las fechas están en
        // ISO, así que compararlas como texto ya las ordena cronológicamente.
        candidatas.sort((a, b) => (a.desde < b.desde ? 1 : a.desde > b.desde ? -1 : 0));
        return candidatas[0].valor;
    },

    // Diccionario {columna del SP => etiqueta} que manda el servidor con cada solapa.
    _etiquetasHistoricas: {},

    registrarEtiquetasHistoricas(dict) {
        if (dict && typeof dict === 'object') {
            Object.assign(TemporadaServidor._etiquetasHistoricas, dict);
        }
    },

    /**
     * Etiqueta de una columna histórica en la convención de la app.
     * VTA_VERANO_26 -> "VER 25-26"   VTA_INVIERNO_26 -> "INV 26"
     *
     * Usa el diccionario del servidor y solo traduce por su cuenta si todavía no
     * llegó. El fallback replica la regla de PresupuestoCalculos: el SP numera el
     * verano por el año en que TERMINA, así que el año de inicio es uno menos.
     */
    etiquetaHistorica(columna) {
        if (TemporadaServidor._etiquetasHistoricas[columna]) {
            return TemporadaServidor._etiquetasHistoricas[columna];
        }

        const m = String(columna).match(/(VERANO|INVIERNO)[\s_]*(\d{2})(?:\s*-\s*(\d{2}))?/i);
        if (!m) return columna;

        const tipo = m[1].toUpperCase();
        const anoFin = parseInt(m[3] !== undefined ? m[3] : m[2], 10);
        const dosDigitos = (n) => String((n + 100) % 100).padStart(2, '0');

        return tipo === 'VERANO'
            ? `VER ${dosDigitos(anoFin - 1)}-${dosDigitos(anoFin)}`
            : `INV ${dosDigitos(anoFin)}`;
    },

    /**
     * Aviso único cuando se intenta recalcular sin datos del servidor.
     * Se prefiere no recalcular antes que calcular con el reloj del navegador:
     * un número que no coincide con el del servidor es peor que ninguno.
     */
    advertirNoDisponible(origen) {
        console.error(`[${origen}] No hay info_temporada del servidor: no se recalcula.`);
        if (typeof UIUtils !== 'undefined' && UIUtils.mostrarAlerta) {
            UIUtils.mostrarAlerta(
                'No se pudo recalcular: faltan los datos de temporada. Recargá los datos.',
                'error'
            );
        }
    }
};

window.TemporadaServidor = TemporadaServidor;
