// Recalcular UNA fila de Argentina en el servidor.
// Archivo: presupuestos/js/recalculo-fila.js
//
// En Argentina la cobertura depende de la venta proyectada, así que editar un índice,
// una venta o el índice de cobertura mueve la venta, la cobertura, el stock
// proyectado, la compra y los tramos. Nada de eso se recalcula acá: se le pide la
// fila a PresupuestoCalculos::calcularFila() (acción recalcular-fila), la misma
// función que arma la carga, el guardado y "Retomar". Las calculadoras JS de verano e
// invierno quedan solo para Uruguay, que sigue como siempre.
//
// Mientras se espera la respuesta, las celdas que van a cambiar se marcan como
// "recalculando" (igual que los tramos antes), y si la llamada falla quedan marcadas
// como desactualizadas en vez de mostrar un número viejo como si fuera el nuevo.

const RecalculoFila = {

    /** Si la fila usa el cálculo de Argentina (lo marca el servidor). */
    aplica(registro) {
        return !!(registro && registro.CALCULO_AR);
    },

    /** Entrada de calcularFila() a partir de la fila en memoria. */
    entradaDe(registro) {
        const num = (v) => (v === null || v === undefined || v === '' ? 0 : parseFloat(v) || 0);
        const manual = (v) => (v === null || v === undefined || v === '' ? null : parseInt(v, 10));
        return {
            cant_stock: num(registro.CANT_STOCK),
            cant_stock_guardar: num(registro.CANT_STOCK_GUARDAR),
            cant_pend_oc_verano: num(registro.CANT_PEND_OC_VERANO),
            cant_pend_oc_invierno: num(registro.CANT_PEND_OC_INVIERNO),
            cant_pend_oc_atemporal: num(registro.CANT_PEND_OC_ATEMPORAL),
            venta_verano_anterior: TemporadaServidor.ventaAnteriorDe(registro, 'VERANO'),
            venta_invierno_anterior: TemporadaServidor.ventaAnteriorDe(registro, 'INVIERNO'),
            indice_verano: num(registro.INDICE_VARIACION ?? 1),
            indice_invierno: num(registro.INDICE_VARIACION_INVIERNO ?? registro.INDICE_VARIACION ?? 1),
            venta_manual_verano: manual(registro.VENTA_MANUAL_VERANO),
            venta_manual_invierno: manual(registro.VENTA_MANUAL_INVIERNO),
            indice_cobertura: num(registro.INDICE_COBERTURA),
            vta_temporada_en_curso: registro.VTA_TEMPORADA_EN_CURSO === null || registro.VTA_TEMPORADA_EN_CURSO === undefined
                ? null : num(registro.VTA_TEMPORADA_EN_CURSO)
        };
    },

    /**
     * Pide la fila al servidor con algunos valores cambiados, SIN aplicarla.
     * La usan las vistas previas de los modales.
     *
     * @returns {Promise<{ok: boolean, fila?: object, mensaje?: string, codigo?: string}>}
     */
    async simular(solapa, registro, cambios, editado) {
        const entrada = Object.assign(RecalculoFila.entradaDe(registro), cambios || {});
        const p = (TemporadaServidor.info() || {}).parametros || {};
        try {
            const r = await APIClient.llamarAPI('recalcular-fila', {}, 'POST', {
                solapa, editado: editado || '', entrada,
                dias_por_mes: p.dias_por_mes, dias_minimos: p.dias_minimos
            });
            if (!r || !r.success) {
                return { ok: false, mensaje: (r && r.message) || 'Respuesta inesperada', codigo: r && r.codigo };
            }
            return { ok: true, fila: r.fila };
        } catch (error) {
            // llamarAPI tira con el mensaje del servidor si la respuesta no es 2xx.
            return { ok: false, mensaje: error.message, codigo: /fuera del rango/.test(error.message) ? 'fuera_de_rango' : null };
        }
    },

    /** Celdas de una fila que pueden cambiar con un recálculo. */
    celdasAfectadas(fila) {
        if (!fila) return [];
        return Array.from(fila.querySelectorAll(
            'td[data-tramo-orden], td[data-col="tec-resto"], td[data-col="tec-total"], '
            + 'td[data-col="venta-proy-ver"], td[data-col="venta-proy-inv"], td[data-col="compra-proyectada"], '
            + 'td[data-col="stock-proyectado"], td[data-col="indice-cobertura"], td[data-indice-editable]'));
    },

    /**
     * Aplica cambios a una fila: pide el cálculo, actualiza la memoria y la pantalla.
     * Si el servidor rechaza el valor (fuera de rango) no se toca nada y se avisa.
     *
     * @param {string} solapa  'verano' o 'invierno'
     * @param {object} registro la fila en memoria (se actualiza en el lugar)
     * @param {object} cambios  campos de la entrada a cambiar (indice_verano, venta_manual_invierno, ...)
     * @param {string} editado  qué tocó el usuario, para la validación de rango
     * @returns {Promise<boolean>} si se aplicó
     */
    async aplicar(solapa, registro, cambios, editado) {
        const fila = TablaRendererUtils.filaDeRegistro(solapa, registro.RUBRO, registro.CATEGORIA_PADRE);
        const celdas = RecalculoFila.celdasAfectadas(fila);
        celdas.forEach(td => td.classList.add('tramo-recalculando'));

        try {
            const r = await RecalculoFila.simular(solapa, registro, cambios, editado);
            if (!r.ok) {
                if (r.codigo === 'fuera_de_rango') {
                    UIUtils.mostrarAlerta(r.mensaje, 'warning');
                    return false;
                }
                throw new Error(r.mensaje);
            }

            // Lo editado que no devuelve el servidor (porque es entrada, no resultado)
            // se escribe en la fila: índices sin venta manual e índice de cobertura.
            if ('indice_verano' in cambios) registro.INDICE_VARIACION = cambios.indice_verano;
            if ('indice_invierno' in cambios) registro.INDICE_VARIACION_INVIERNO = cambios.indice_invierno;
            if ('indice_cobertura' in cambios) registro.INDICE_COBERTURA = cambios.indice_cobertura;
            Object.assign(registro, r.fila);

            RecalculoFila.repintar(solapa, registro);
            celdas.forEach(td => td.classList.remove('tramo-desactualizado'));

            if (typeof RetomarVersion !== 'undefined') RetomarVersion.marcarCambios(solapa);
            if (typeof TotalesCompra !== 'undefined') {
                TotalesCompra.onIndiceActualizado(solapa, registro.RUBRO, registro.CATEGORIA_PADRE, null);
            }
            if (typeof StockSincronizado !== 'undefined') StockSincronizado.refrescar();
            return true;

        } catch (error) {
            console.error('No se pudo recalcular la fila:', error);
            celdas.forEach(td => {
                td.classList.add('tramo-desactualizado');
                td.title = 'No se pudo recalcular esta fila. El número que se ve es el anterior a '
                         + 'esta edición: volvé a cargar los datos.';
            });
            UIUtils.mostrarAlerta('No se pudo recalcular la fila: ' + error.message, 'error');
            return false;
        } finally {
            celdas.forEach(td => td.classList.remove('tramo-recalculando'));
        }
    },

    /** Vuelve a escribir en pantalla todo lo calculado de una fila. */
    repintar(solapa, registro) {
        const fila = TablaRendererUtils.filaDeRegistro(solapa, registro.RUBRO, registro.CATEGORIA_PADRE);
        if (!fila) return;

        TablaRendererUtils.actualizarCeldasCalculadas(fila, registro);
        (registro.TRAMOS || []).forEach(tramo => {
            const td = fila.querySelector(`td[data-tramo-orden="${tramo.orden}"]`);
            if (td) TablaRendererUtils.pintarCeldaTramo(td, tramo);
        });
        TablaRendererUtils.pintarTemporadaEnCurso(fila, registro);
    }
};

window.RecalculoFila = RecalculoFila;
