// Editor de índices de variación - ROBUSTO Y CORREGIDO
// Archivo: presupuestos/js/indice-editor.js

class IndiceEditor {
    static indiceEditando = null;
    static historialCambios = [];
    static modalInstancia = null;

    /**
     * Inicializar
     */
    static init() {
        IndiceEditor.configurarModal();
        IndiceEditor.configurarEventListeners();
        IndiceEditor.configurarEventosRestaurar();
    }

    /**
     * Actualizar datos en memoria (Lógica principal de cálculo)
     */
    static actualizarDatosMemoria(nuevoIndice, registro, temporada, solapa) {
        console.log(`🔄 Calculando actualización: ${temporada} -> ${nuevoIndice}`);
        
        // Crear copia del registro para no mutar el original prematuramente
        const registroTemporal = { ...registro };
        
        // Actualizar el índice correspondiente
        if (temporada === 'verano') {
            registroTemporal.INDICE_VARIACION = parseFloat(nuevoIndice.toFixed(2));
        } else if (temporada === 'invierno') {
            registroTemporal.INDICE_VARIACION = parseFloat(registro.INDICE_VARIACION || 1.0);
            registroTemporal.INDICE_VARIACION_INVIERNO = parseFloat(nuevoIndice.toFixed(2));
        }
        
        // Preparar objeto de contexto para las calculadoras
        // Importante: Usamos un índice dummy porque estamos operando sobre el objeto directo
        const contexto = {
            rubro: registro.RUBRO,
            categoria: registro.CATEGORIA_PADRE || registro.CATEGORIA,
            solapa: solapa,
            temporada: temporada,
            index: 0 
        };
        
        // Delegar cálculo a la calculadora específica
        let registroCalculado;
        if (solapa === 'verano' && typeof CalculadoraVerano !== 'undefined') {
            registroCalculado = CalculadoraVerano.actualizarDatosSolapa(nuevoIndice, contexto, registroTemporal);
        } else if (solapa === 'invierno' && typeof CalculadoraInvierno !== 'undefined') {
            registroCalculado = CalculadoraInvierno.actualizarDatosSolapa(nuevoIndice, contexto, registroTemporal);
        } else {
            console.error('Calculadora no disponible para solapa:', solapa);
            return registroTemporal;
        }
        
        return registroCalculado;
    }

    /**
     * Encontrar el índice real en los datos (Maneja filtros)
     */
    static encontrarIndiceRealEnDatos(rubro, categoria, solapa) {
        const app = window.presupuestoApp;
        const datosOriginales = app.getDatos(solapa);
        
        const indice = datosOriginales.findIndex(item => 
            item.RUBRO === rubro && 
            (item.CATEGORIA_PADRE === categoria || item.CATEGORIA === categoria)
        );
        
        if (indice === -1) {
            console.warn(`Registro no encontrado en datos originales: ${rubro} - ${categoria}`);
        }
        return indice;
    }

    /**
     * Método principal para aplicar cambios (Usado por Editar y Restaurar)
     */
    static aplicarCambio(rubro, categoria, solapa, temporada, nuevoIndice) {
        const app = window.presupuestoApp;
        const datos = app.getDatos(solapa);
        
        // 1. Encontrar registro en memoria
        const indiceReal = IndiceEditor.encontrarIndiceRealEnDatos(rubro, categoria, solapa);
        if (indiceReal === -1) return;
        
        const registro = datos[indiceReal];

        // 2. GUARDAR BACKUP DEL ORIGINAL (SOLO LA PRIMERA VEZ)
        // Esto soluciona el problema de perder el valor original al editar varias veces
        if (!registro._indicesOriginales) {
            registro._indicesOriginales = {};
        }
        if (registro._indicesOriginales[temporada] === undefined) {
            // Prioridad 1: Columna de Base de Datos / Procesador (INDICE_VAR_ORIGINAL / INDICE_ORIGINAL)
            if (registro.INDICE_VAR_ORIGINAL !== undefined && registro.INDICE_VAR_ORIGINAL !== null) {
                registro._indicesOriginales[temporada] = parseFloat(registro.INDICE_VAR_ORIGINAL);
            } else if (registro.INDICE_ORIGINAL !== undefined && registro.INDICE_ORIGINAL !== null) {
                registro._indicesOriginales[temporada] = parseFloat(registro.INDICE_ORIGINAL);
            } else if (registro._indiceOriginal !== undefined && registro._indiceOriginal !== null) {
                registro._indicesOriginales[temporada] = parseFloat(registro._indiceOriginal);
            } else {
                // Prioridad 2: Valor actual antes de la primera edición
                registro._indicesOriginales[temporada] = temporada === 'invierno' 
                    ? parseFloat(registro.INDICE_VARIACION_INVIERNO || registro.INDICE_VARIACION || 1.0)
                    : parseFloat(registro.INDICE_VARIACION || 1.0);
            }
            console.log(`💾 Original guardado permanentemente para ${temporada}: ${registro._indicesOriginales[temporada]}`);
        }

        const valorOriginal = registro._indicesOriginales[temporada];
        if (registro.INDICE_VAR_ORIGINAL === undefined) registro.INDICE_VAR_ORIGINAL = valorOriginal;
        if (registro.INDICE_ORIGINAL === undefined) registro.INDICE_ORIGINAL = valorOriginal;
        if (registro._indiceOriginal === undefined) registro._indiceOriginal = valorOriginal;

        // Argentina: la fila entera la recalcula el servidor (cobertura incluida).
        // Editar el índice descarta la venta cargada a mano de esa temporada.
        if (RecalculoFila.aplica(registro)) {
            return IndiceEditor.aplicarCambioAr(solapa, registro, temporada, nuevoIndice);
        }

        // 3. Calcular nuevos valores
        const registroActualizado = IndiceEditor.actualizarDatosMemoria(nuevoIndice, registro, temporada, solapa);
        
        // Preservar valores originales y metadatos en el registro actualizado
        registroActualizado.INDICE_VAR_ORIGINAL = registro.INDICE_VAR_ORIGINAL;
        registroActualizado.INDICE_ORIGINAL = registro.INDICE_ORIGINAL;
        registroActualizado._indiceOriginal = registro._indiceOriginal;
        registroActualizado._indicesOriginales = { ...registro._indicesOriginales };
        if (registro._indicesEditados) {
            registroActualizado._indicesEditados = { ...registro._indicesEditados };
        }

        // 4. Marca de "editado", en el registro que QUEDA en memoria.
        //
        // Antes se escribía en `registro`, que en el paso siguiente se reemplaza por
        // registroActualizado: la última edición perdía la marca en cuanto la tabla se
        // volvía a dibujar (al filtrar, por ejemplo). Tampoco depende de que la fila
        // esté visible: una fila oculta por un filtro también queda editada.
        const esDiferente = Math.abs(nuevoIndice - valorOriginal) > 0.001;
        if (esDiferente) {
            registroActualizado._indicesEditados = registroActualizado._indicesEditados || {};
            registroActualizado._indicesEditados[temporada] = {
                valorOriginal: valorOriginal,
                valorActual: nuevoIndice
            };
        } else if (registroActualizado._indicesEditados) {
            delete registroActualizado._indicesEditados[temporada];
        }

        // 5. Actualizar datos en memoria
        datos[indiceReal] = registroActualizado;

        // 6. Actualizar la fila en pantalla, buscándola por rubro y categoría: con
        // filtros u orden aplicados, la posición en el tbody no identifica a la fila.
        const filaVisual = TablaRendererUtils.filaDeRegistro(solapa, rubro, categoria);

        if (filaVisual) {
            TablaRendererUtils.actualizarCeldasCalculadas(filaVisual, registroActualizado);

            if (esDiferente) {
                IndiceEditor.aplicarMarcasVisualesEnFila(filaVisual, temporada, nuevoIndice);
            } else {
                IndiceEditor.limpiarMarcasVisuales(filaVisual, temporada, valorOriginal);
            }
        }

        // Para avisar antes de retomar otra versión encima de esta edición.
        if (typeof RetomarVersion !== 'undefined') RetomarVersion.marcarCambios(solapa);

        // 7. Notificar a Totales (Recalcular sumas)
        if (typeof TotalesCompra !== 'undefined') {
            TotalesCompra.onIndiceActualizado(solapa, rubro, categoria, nuevoIndice);
        }

        // 8. Pedirle al servidor el reparto por tramo de esta fila.
        //
        // No se recalcula acá: el reparto del stock entre tramos tiene UNA sola
        // implementación (PresupuestoCalculos::repartirCompraPorTramo) y duplicarla en
        // JS es exactamente lo que ya separó los números una vez. Va aparte del resto
        // del flujo, que sigue siendo síncrono, para que la edición no se quede esperando
        // el viaje: las celdas de tramo se marcan mientras tanto y se completan al volver.
        IndiceEditor.refrescarTramos(solapa, indiceReal, registroActualizado, filaVisual);
    }

    // ==========================================
    // ARGENTINA: índice, venta y cobertura recalculados en el servidor
    // ==========================================

    static datoDe(solapa, rubro, categoria) {
        return (window.presupuestoApp.getDatos(solapa) || [])
            .find(r => r.RUBRO === rubro && (r.CATEGORIA_PADRE === categoria || r.CATEGORIA === categoria)) || null;
    }

    /** Parámetros de edición que vinieron con la carga (límites de los índices). */
    static limites() {
        const p = (TemporadaServidor.info() || {}).parametros || {};
        return {
            variacionMin: p.variacion_min ?? 0, variacionMax: p.variacion_max ?? 10,
            coberturaMin: p.cobertura_min ?? 0, coberturaMax: p.cobertura_max ?? 12
        };
    }

    static async aplicarCambioAr(solapa, registro, temporada, nuevoIndice) {
        const ok = await RecalculoFila.aplicar(solapa, registro,
            { [`indice_${temporada}`]: nuevoIndice, [`venta_manual_${temporada}`]: null }, `indice_${temporada}`);
        if (ok) IndiceEditor.actualizarMarcaIndice(solapa, registro, temporada);
        return ok;
    }

    /**
     * Venta proyectada cargada a mano. El servidor despeja el índice (exacto, 6
     * decimales) o, sin venta anterior, reparte la venta por días y deja el índice en "—".
     */
    static async aplicarVentaAr(solapa, registro, temporada, venta) {
        if (!registro._indicesOriginales) {
            const original = parseFloat(registro.INDICE_VAR_ORIGINAL ?? 1);
            registro._indicesOriginales = { verano: original, invierno: original };
        }
        const ok = await RecalculoFila.aplicar(solapa, registro,
            { [`venta_manual_${temporada}`]: venta }, `venta_${temporada}`);
        if (ok) IndiceEditor.actualizarMarcaIndice(solapa, registro, temporada);
        return ok;
    }

    /**
     * Marca de "editado" de una temporada: el índice difiere del original o hay una
     * venta cargada a mano. Vive en el registro (sobrevive a filtrar y redibujar).
     */
    static actualizarMarcaIndice(solapa, registro, temporada) {
        const original = parseFloat(registro._indicesOriginales?.[temporada] ?? registro.INDICE_VAR_ORIGINAL ?? 1);
        const actual = parseFloat(temporada === 'invierno' ? registro.INDICE_VARIACION_INVIERNO : registro.INDICE_VARIACION);
        const manual = registro[`VENTA_MANUAL_${temporada.toUpperCase()}`];
        const editado = Math.abs(actual - original) > 0.001 || (manual !== null && manual !== undefined);

        if (editado) {
            registro._indicesEditados = registro._indicesEditados || {};
            registro._indicesEditados[temporada] = { valorOriginal: original, valorActual: actual };
        } else if (registro._indicesEditados) {
            delete registro._indicesEditados[temporada];
        }

        const fila = TablaRendererUtils.filaDeRegistro(solapa, registro.RUBRO, registro.CATEGORIA_PADRE);
        if (!fila) return;
        if (editado) IndiceEditor.aplicarMarcasVisualesEnFila(fila, temporada, actual);
        else IndiceEditor.limpiarMarcasVisuales(fila, temporada, original);
        TablaRendererUtils.pintarMarcasVentaAr(fila, registro);
    }

    /**
     * Índice de cobertura de Argentina: uno solo por categoría, compartido por las tres
     * solapas. Cada solapa de compra lo recalcula con SUS índices de variación.
     */
    static async aplicarCambioCoberturaAr(rubro, categoria, nuevoIndice) {
        let algunoOk = false;
        for (const tab of ['verano', 'invierno']) {
            const reg = IndiceEditor.datoDe(tab, rubro, categoria);
            if (!reg) continue;
            const ok = await RecalculoFila.aplicar(tab, reg, { indice_cobertura: nuevoIndice }, 'indice_cobertura');
            if (!ok) continue;
            algunoOk = true;
            const esDiferente = Math.abs(nuevoIndice - parseFloat(reg._factorCoberturaOriginal ?? reg.INDICE_COBERTURA_DEFECTO ?? 0)) > 0.001;
            if (esDiferente) reg._coberturaEditada = { valorOriginal: reg._factorCoberturaOriginal, valorActual: nuevoIndice };
            else delete reg._coberturaEditada;
        }
        if (typeof StockSincronizado !== 'undefined') StockSincronizado.refrescar();
        return algunoOk;
    }

    /** Edición de la venta proyectada de una columna (solo Argentina). */
    static editarVenta(rubro, categoria, solapa, temporada) {
        const registro = IndiceEditor.datoDe(solapa, rubro, categoria);
        if (!registro) return;
        const T = temporada.toUpperCase();
        const ventaActual = parseFloat(registro[`VENTA_PROY_${T}`] || 0);
        const base = TemporadaServidor.ventaAnteriorDe(registro, T);

        IndiceEditor.indiceEditando = { rubro, categoria, indiceActual: ventaActual, solapa, temporada, tipo: 'venta' };

        document.getElementById('modal-rubro').textContent = rubro;
        document.getElementById('modal-categoria').textContent = categoria;
        const modalEl = document.getElementById('modalEditarIndice');
        modalEl.querySelector('.modal-title').textContent =
            `Editar Venta Proyectada - ${temporada.charAt(0).toUpperCase() + temporada.slice(1)}`;
        const label = modalEl.querySelector('label[for="modal-nuevo-indice"]');
        if (label) label.textContent = 'Venta proyectada (unidades):';
        const formText = modalEl.querySelector('.form-text');
        if (formText) {
            formText.innerHTML = base > 0
                ? 'Se recalcula el índice de la temporada para que la venta dé exactamente este número '
                  + '(el mismo índice para todos los tramos de la columna).'
                : '<strong>Sin venta anterior:</strong> no hay índice que calcular. La venta queda como '
                  + '"venta manual" y se reparte entre los tramos según sus días.';
        }
        const notaGroup = document.getElementById('modal-nota-indice')?.closest('.mb-3');
        if (notaGroup) notaGroup.classList.remove('d-none');
        const notaInput = document.getElementById('modal-nota-indice');
        if (notaInput) notaInput.value = registro.NOTA || '';

        const input = document.getElementById('modal-nuevo-indice');
        input.value = Math.round(ventaActual);
        input.step = '1';
        input.min = '0';
        input.removeAttribute('max');

        const btnRestaurar = document.getElementById('btn-modal-restaurar-indice');
        const manual = registro[`VENTA_MANUAL_${T}`];
        if (btnRestaurar) {
            const original = parseFloat(registro._indicesOriginales?.[temporada] ?? registro.INDICE_VAR_ORIGINAL ?? 1);
            btnRestaurar.classList.toggle('d-none', manual === null || manual === undefined);
            btnRestaurar.innerHTML = `<i class="fas fa-undo me-1"></i>Restaurar índice original (${original.toFixed(2)})`;
        }

        IndiceEditor.mostrarPreviewCalculo(ventaActual);
        IndiceEditor.modalInstancia.show();
        setTimeout(() => input.select(), 500);
    }

    /**
     * Aviso de índice no redondo: índice exacto y los dos redondos más cercanos con
     * la venta que daría cada uno. [Usar] aplica ese índice (y con él esa venta).
     */
    static mostrarSugerencias(td, solapa, rubro, categoria, temporada) {
        const registro = IndiceEditor.datoDe(solapa, rubro, categoria);
        const s = registro && registro[`SUGERENCIAS_${temporada.toUpperCase()}`];
        if (!s) {
            IndiceEditor.editarIndice(rubro, categoria, registro ? registro.INDICE_VARIACION : 1, solapa, null, temporada);
            return;
        }

        const fmt = (n, d) => Number(n).toLocaleString('es-AR', { minimumFractionDigits: d, maximumFractionDigits: d });
        const cont = document.createElement('div');
        cont.className = 'small';
        cont.innerHTML = `
            <div class="mb-1">Índice exacto <strong>${fmt(s.indice_exacto, 4)}</strong>.</div>
            <div class="mb-2">Para un índice redondo:</div>
            <div class="d-flex align-items-center justify-content-between mb-1 gap-2">
                <span>${fmt(s.abajo.indice, 2)} → ${FormatoUtils.formatearNumero(s.abajo.venta)} unidades</span>
                <button type="button" class="btn btn-sm btn-outline-primary py-0" data-usar="${s.abajo.indice}">Usar</button>
            </div>
            <div class="d-flex align-items-center justify-content-between mb-2 gap-2">
                <span>${fmt(s.arriba.indice, 2)} → ${FormatoUtils.formatearNumero(s.arriba.venta)} unidades</span>
                <button type="button" class="btn btn-sm btn-outline-primary py-0" data-usar="${s.arriba.indice}">Usar</button>
            </div>
            <div class="d-flex gap-2 border-top pt-2">
                <button type="button" class="btn btn-sm btn-link p-0" data-accion="indice">Editar índice</button>
                <button type="button" class="btn btn-sm btn-link p-0" data-accion="venta">Editar venta</button>
            </div>`;

        const anterior = bootstrap.Popover.getInstance(td);
        if (anterior) anterior.dispose();
        const pop = new bootstrap.Popover(td, {
            html: true, sanitize: false, trigger: 'manual', placement: 'top', container: 'body',
            title: 'Índice no redondo', content: cont
        });
        const cerrar = () => { pop.dispose(); document.removeEventListener('mousedown', fuera, true); };
        const fuera = (e) => { if (!cont.contains(e.target) && !td.contains(e.target)) cerrar(); };

        cont.querySelectorAll('[data-usar]').forEach(b => b.addEventListener('click', () => {
            cerrar();
            IndiceEditor.aplicarCambio(rubro, categoria, solapa, temporada, parseFloat(b.dataset.usar));
        }));
        cont.querySelector('[data-accion="indice"]').addEventListener('click', () => {
            cerrar();
            const actual = parseFloat(temporada === 'invierno' ? registro.INDICE_VARIACION_INVIERNO : registro.INDICE_VARIACION);
            IndiceEditor.editarIndice(rubro, categoria, actual, solapa, null, temporada);
        });
        cont.querySelector('[data-accion="venta"]').addEventListener('click', () => {
            cerrar();
            IndiceEditor.editarVenta(rubro, categoria, solapa, temporada);
        });

        pop.show();
        setTimeout(() => document.addEventListener('mousedown', fuera, true), 0);
    }

    /**
     * Actualiza las celdas de tramo de una fila con el reparto que devuelve el servidor.
     * Si la llamada falla, las celdas quedan marcadas como desactualizadas en vez de
     * mostrar un número viejo como si fuera el nuevo.
     */
    static async refrescarTramos(solapa, indiceReal, registro, filaVisual) {
        const celdas = filaVisual
            // Resto y Total de la temporada en curso salen del mismo reparto, así que se
            // marcan y se actualizan junto con los tramos.
            ? Array.from(filaVisual.querySelectorAll('td[data-tramo-orden], td[data-col="tec-resto"], td[data-col="tec-total"]'))
            : [];

        celdas.forEach(td => td.classList.add('tramo-recalculando'));

        try {
            const respuesta = await APIClient.llamarAPI('recalcular-tramos', {}, 'POST', {
                solapa: solapa,
                stock_proyectado: parseFloat(registro.STOCK_PROYECTADO || 0),
                venta_verano_anterior: TemporadaServidor.ventaAnteriorDe(registro, 'VERANO'),
                venta_invierno_anterior: TemporadaServidor.ventaAnteriorDe(registro, 'INVIERNO'),
                indice_verano: parseFloat(registro.INDICE_VARIACION || 1.0),
                indice_invierno: parseFloat(registro.INDICE_VARIACION_INVIERNO || registro.INDICE_VARIACION || 1.0)
            });

            if (!respuesta || !respuesta.success || !Array.isArray(respuesta.tramos)) {
                throw new Error(respuesta && respuesta.message ? respuesta.message : 'Respuesta inesperada');
            }

            // El registro en memoria es el que después se guarda y el que suman los
            // totales, así que se actualiza junto con la pantalla.
            registro.TRAMOS = respuesta.tramos;
            const datos = window.presupuestoApp.getDatos(solapa);
            if (datos[indiceReal]) {
                datos[indiceReal].TRAMOS = respuesta.tramos;
            }

            respuesta.tramos.forEach(tramo => {
                const td = celdas.find(c => String(c.dataset.tramoOrden) === String(tramo.orden));
                if (td) {
                    TablaRendererUtils.pintarCeldaTramo(td, tramo);
                    td.classList.remove('tramo-desactualizado');
                }
            });

            if (filaVisual) {
                TablaRendererUtils.pintarTemporadaEnCurso(filaVisual, registro);
                celdas.forEach(td => td.classList.remove('tramo-desactualizado'));
            }

            if (typeof TotalesCompra !== 'undefined') {
                TotalesCompra.onIndiceActualizado(solapa, registro.RUBRO, registro.CATEGORIA_PADRE, null);
            }

        } catch (error) {
            console.error('No se pudo recalcular el reparto por tramo:', error);
            celdas.forEach(td => {
                td.classList.add('tramo-desactualizado');
                td.title = 'No se pudo recalcular el reparto por tramo. El número que se ve es el '
                         + 'anterior a esta edición: volvé a cargar los datos.';
            });
        } finally {
            celdas.forEach(td => td.classList.remove('tramo-recalculando'));
        }
    }

    /**
     * Restaurar índice al valor original (Lógica Corregida)
     */
    static restaurarIndiceOriginalDesdeDatos(fila, temporada) {
        const rubro = fila.dataset.rubro;
        const categoria = fila.dataset.categoria;

        // La solapa sale de la tabla a la que pertenece la fila y no de la pestaña
        // activa: así no depende de qué pestaña tenga la clase .active en ese momento.
        const solapaActiva = (fila.closest('tbody')?.id || '').replace('tbody-', '');
        const datos = window.presupuestoApp.getDatos(solapaActiva);
        
        const registro = datos.find(item => 
            item.RUBRO === rubro && 
            (item.CATEGORIA_PADRE === categoria || item.CATEGORIA === categoria)
        );

        if (!registro) {
            console.error('Registro no encontrado para restaurar');
            return;
        }

        // 1. DETERMINAR EL VERDADERO VALOR ORIGINAL
        let valorOriginal = 1.0;

        // Opción A: Backup en memoria (lo más seguro)
        if (registro._indicesOriginales && registro._indicesOriginales[temporada] !== undefined) {
            valorOriginal = registro._indicesOriginales[temporada];
        } 
        // Opción B: Columna de Base de Datos (Si no se editó nunca en esta sesión pero queremos resetear)
        else if (registro.INDICE_VAR_ORIGINAL) {
            valorOriginal = parseFloat(registro.INDICE_VAR_ORIGINAL);
        }

        console.log(`🔙 Restaurando ${rubro} (${temporada}) al valor: ${valorOriginal}`);

        // 2. Aplicar el cambio usando el valor original
        IndiceEditor.aplicarCambio(rubro, categoria, solapaActiva, temporada, valorOriginal);
        
        UIUtils.mostrarAlerta(`Índice ${temporada} restaurado a ${valorOriginal.toFixed(2)}`, 'info');
    }

    // ==========================================
    // MANEJO DEL MODAL Y EVENTOS
    // ==========================================

    static configurarModal() {
        const modal = document.getElementById('modalEditarIndice');
        if (modal) {
            IndiceEditor.modalInstancia = new bootstrap.Modal(modal);
            modal.addEventListener('hidden.bs.modal', () => {
                document.querySelectorAll('.fila-datos.table-warning').forEach(f => {
                    f.classList.remove('table-warning');
                    f.style.transform = '';
                    f.style.boxShadow = '';
                });
                IndiceEditor.limpiarFormulario();
                IndiceEditor.indiceEditando = null;
            });
        }
    }

    static configurarEventListeners() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && IndiceEditor.modalInstancia) IndiceEditor.modalInstancia.hide();
            if (e.key === 'Enter' && e.ctrlKey && IndiceEditor.indiceEditando) IndiceEditor.guardarNuevoIndice();
        });

        const inputIndice = document.getElementById('modal-nuevo-indice');
        if (inputIndice) {
            inputIndice.addEventListener('input', (e) => IndiceEditor.mostrarPreviewCalculo(parseFloat(e.target.value)));
            inputIndice.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); IndiceEditor.guardarNuevoIndice(); }
            });
        }
    }

    static editarIndice(rubro, categoria, indiceActual, solapa, index, temporada = 'verano') {
        IndiceEditor.indiceEditando = { rubro, categoria, indiceActual: parseFloat(indiceActual), solapa, index, temporada, tipo: 'variacion' };
        
        document.getElementById('modal-rubro').textContent = rubro;
        document.getElementById('modal-categoria').textContent = categoria;

        const modalEl = document.getElementById('modalEditarIndice');
        const registro = (window.presupuestoApp.getDatos(solapa) || [])
            .find(r => r.RUBRO === rubro && (r.CATEGORIA_PADRE === categoria || r.CATEGORIA === categoria));
        const tipoIndice = registro && registro.TE_DISPONIBLE ? 'Total Empresa' : 'Locales Propios';
        modalEl.querySelector('.modal-title').textContent =
            `Editar Índice ${tipoIndice} - ${temporada.charAt(0).toUpperCase() + temporada.slice(1)}`;
        
        const labelIndice = modalEl.querySelector('label[for="modal-nuevo-indice"]');
        if (labelIndice) labelIndice.textContent = 'Nuevo Índice:';

        const formText = modalEl.querySelector('.form-text');
        if (formText) {
            formText.innerHTML = `
                <strong>Ejemplos:</strong><br>
                • 1.0 = Sin cambio (100%)<br>
                • 1.2 = Aumento 20%<br>
                • 0.8 = Reducción 20%
            `;
        }

        const notaGroup = document.getElementById('modal-nota-indice')?.closest('.mb-3');
        if (notaGroup) notaGroup.classList.remove('d-none');

        const input = document.getElementById('modal-nuevo-indice');
        input.value = parseFloat(indiceActual).toFixed(2);
        input.step = '0.01';
        input.min = String(IndiceEditor.limites().variacionMin);
        input.max = String(IndiceEditor.limites().variacionMax);

        // La nota que ya tenga la fila, para corregirla en vez de reescribirla.
        const notaInput = document.getElementById('modal-nota-indice');
        if (notaInput) notaInput.value = (registro && registro.NOTA) || '';
        
        // Botón restaurar en modal si difiere del original
        const btnRestaurar = document.getElementById('btn-modal-restaurar-indice');
        const valorOriginal = registro ? TablaRendererUtils.obtenerIndiceOriginal(registro) : parseFloat(indiceActual);
        if (btnRestaurar) {
            if (Math.abs(parseFloat(indiceActual) - valorOriginal) > 0.001) {
                btnRestaurar.classList.remove('d-none');
                btnRestaurar.innerHTML = `<i class="fas fa-undo me-1"></i>Restaurar Original (${valorOriginal.toFixed(2)})`;
            } else {
                btnRestaurar.classList.add('d-none');
            }
        }

        // Mostrar preview inicial
        IndiceEditor.mostrarPreviewCalculo(parseFloat(indiceActual));
        
        // Fila no resaltada para evitar distorsiones
        IndiceEditor.modalInstancia.show();
        setTimeout(() => input.select(), 500);
    }

    /**
     * Modal para editar el factor de cobertura de stock (en meses de venta promedio)
     */
    static editarIndiceCobertura(rubro, categoria, indiceActual, solapa) {
        IndiceEditor.indiceEditando = {
            rubro,
            categoria,
            indiceActual: parseFloat(indiceActual),
            solapa,
            tipo: 'cobertura'
        };

        document.getElementById('modal-rubro').textContent = rubro;
        document.getElementById('modal-categoria').textContent = categoria;

        const modalEl = document.getElementById('modalEditarIndice');
        modalEl.querySelector('.modal-title').textContent = 'Editar Índice de Cobertura (Meses)';

        const labelIndice = modalEl.querySelector('label[for="modal-nuevo-indice"]');
        if (labelIndice) labelIndice.textContent = 'Factor Cobertura (Meses):';

        const formText = modalEl.querySelector('.form-text');
        if (formText) {
            formText.innerHTML = `
                <strong>Meses de venta promedio (45 días = 1.50):</strong><br>
                • 1.50 = 45 días (cobertura por defecto)<br>
                • 2.00 = 60 días (aumenta stock de reserva)<br>
                • 1.00 = 30 días (reduce stock de reserva)
            `;
        }

        const notaGroup = document.getElementById('modal-nota-indice')?.closest('.mb-3');
        if (notaGroup) notaGroup.classList.add('d-none');

        const input = document.getElementById('modal-nuevo-indice');
        input.value = parseFloat(indiceActual).toFixed(2);
        input.step = '0.1';
        input.min = String(IndiceEditor.limites().coberturaMin);
        input.max = String(IndiceEditor.limites().coberturaMax);

        // Botón restaurar en modal si difiere del original
        const reg = (window.presupuestoApp.getDatos(solapa) || [])
            .find(r => r.RUBRO === rubro && (r.CATEGORIA_PADRE === categoria || r.CATEGORIA === categoria));
        const factorOriginal = reg ? parseFloat(reg._factorCoberturaOriginal !== undefined ? reg._factorCoberturaOriginal : (parseFloat(reg.STOCK_COBERTURA || 0) > 0 ? 1.5 : 0.0)) : 1.5;
        const btnRestaurar = document.getElementById('btn-modal-restaurar-indice');
        if (btnRestaurar) {
            if (Math.abs(parseFloat(indiceActual) - factorOriginal) > 0.001) {
                btnRestaurar.classList.remove('d-none');
                btnRestaurar.innerHTML = `<i class="fas fa-undo me-1"></i>Restaurar Original (${factorOriginal.toFixed(2)})`;
            } else {
                btnRestaurar.classList.add('d-none');
            }
        }

        IndiceEditor.mostrarPreviewCalculo(parseFloat(indiceActual));

        // Fila no resaltada para evitar distorsiones
        IndiceEditor.modalInstancia.show();
        setTimeout(() => input.select(), 500);
    }

    static async guardarNuevoIndice() {
        if (!IndiceEditor.indiceEditando) return;

        const nuevoIndice = parseFloat(document.getElementById('modal-nuevo-indice').value);
        const tipoEdicion = IndiceEditor.indiceEditando.tipo;
        const lim = IndiceEditor.limites();
        // Límites de Parámetros (0-10 y 0-12 por defecto, que eran los fijos de antes).
        const rango = tipoEdicion === 'venta' ? [0, Infinity]
            : tipoEdicion === 'cobertura' ? [lim.coberturaMin, lim.coberturaMax]
            : [lim.variacionMin, lim.variacionMax];
        if (isNaN(nuevoIndice) || nuevoIndice < rango[0] || nuevoIndice > rango[1]) {
            UIUtils.mostrarAlerta(tipoEdicion === 'venta'
                ? 'Ingresá una venta mayor o igual a 0.'
                : `Ingresá un valor válido (${rango[0]}-${rango[1]})`, 'warning');
            return;
        }

        const editando = { ...IndiceEditor.indiceEditando };
        const btnGuardar = document.querySelector('#modalEditarIndice .btn-primary');
        const textoOriginal = btnGuardar.innerHTML;
        btnGuardar.innerHTML = 'Guardando...'; btnGuardar.disabled = true;

        try {
            await new Promise(r => setTimeout(r, 300)); // Pequeña pausa UX

            const { rubro, categoria, solapa, temporada, tipo } = IndiceEditor.indiceEditando;
            const registroEditado = IndiceEditor.datoDe(solapa, rubro, categoria);

            if (tipo === 'venta') {
                // Si el servidor rechaza la venta (índice fuera de rango) el modal queda
                // abierto con el aviso, para corregir el número sin volver a empezar.
                const ok = await IndiceEditor.aplicarVentaAr(solapa, registroEditado, temporada, Math.round(nuevoIndice));
                if (!ok) return;
                const notaInput = document.getElementById('modal-nota-indice');
                if (notaInput) IndiceEditor.asignarNota(solapa, rubro, categoria, notaInput.value);
                UIUtils.mostrarAlerta('Venta proyectada actualizada', 'success');
            } else if (tipo === 'cobertura') {
                if (RecalculoFila.aplica(registroEditado)) {
                    await IndiceEditor.aplicarCambioCoberturaAr(rubro, categoria, nuevoIndice);
                } else {
                    IndiceEditor.aplicarCambioCobertura(rubro, categoria, solapa, nuevoIndice);
                }
                UIUtils.mostrarAlerta(`Índice de cobertura actualizado a ${nuevoIndice.toFixed(2)} meses`, 'success');
            } else {
                // USAR LA FUNCIÓN CENTRALIZADA
                const ok = await IndiceEditor.aplicarCambio(rubro, categoria, solapa, temporada, nuevoIndice);
                if (ok === false) return;

                // La nota viaja con la fila, como el índice editado, hasta que se guarda la versión.
                const notaInput = document.getElementById('modal-nota-indice');
                if (notaInput) IndiceEditor.asignarNota(solapa, rubro, categoria, notaInput.value);
                
                UIUtils.mostrarAlerta('Índice actualizado', 'success');
            }

            IndiceEditor.modalInstancia.hide();
            
        } catch (error) {
            console.error(error);
            UIUtils.mostrarAlerta('Error al guardar', 'error');
        } finally {
            btnGuardar.innerHTML = textoOriginal; btnGuardar.disabled = false;
            // Se usa la copia: el modal pone indiceEditando en null al cerrarse.
            if (editando) TablaRenderer.resaltarFila(editando.solapa, editando.rubro, editando.categoria, false);
        }
    }

    // ==========================================
    // UTILS VISUALES
    // ==========================================

    static aplicarMarcasVisualesEnFila(fila, temporada, nuevoValor) {
        const celda = fila.querySelector(`td[data-indice-editable="${temporada}"]`);
        if (!celda) return;

        celda.classList.add('indice-editado');
        const input = celda.querySelector('.indice-input');
        if (input) {
            input.classList.add('input-editado');
            input.value = nuevoValor.toFixed(2);
        }

        if (!celda.querySelector('.btn-restaurar-original')) {
            const btn = document.createElement('button');
            btn.className = 'btn-restaurar-original';
            btn.innerHTML = '×';
            btn.title = 'Restaurar valor original';
            btn.onclick = (e) => {
                e.stopPropagation();
                if (confirm(`¿Restaurar índice original?`)) {
                    IndiceEditor.restaurarIndiceOriginalDesdeDatos(fila, temporada);
                }
            };
            celda.appendChild(btn);
        }
    }

    static limpiarMarcasVisuales(fila, temporada, valorOriginal) {
        const celda = fila.querySelector(`td[data-indice-editable="${temporada}"]`);
        if (!celda) return;

        celda.classList.remove('indice-editado');
        const input = celda.querySelector('.indice-input');
        if (input) {
            input.classList.remove('input-editado');
            input.value = valorOriginal.toFixed(2);
        }
        const btn = celda.querySelector('.btn-restaurar-original');
        if (btn) btn.remove();
    }

    /**
     * Aplica el cambio de índice de cobertura sincronizando Verano e Invierno
     */
    static aplicarCambioCobertura(rubro, categoria, solapa, nuevoIndice) {
        const app = window.presupuestoApp;
        if (!app) return;

        ['verano', 'invierno'].forEach(tab => {
            const datos = app.getDatos(tab);
            if (!datos || !datos.length) return;
            const idx = datos.findIndex(r => r.RUBRO === rubro && (r.CATEGORIA_PADRE === categoria || r.CATEGORIA === categoria));
            if (idx === -1) return;
            const reg = datos[idx];

            const stockCob = parseFloat(reg.STOCK_COBERTURA || 0);
            if (reg._stockCoberturaOriginal === undefined) reg._stockCoberturaOriginal = stockCob;
            if (reg._baseCoberturaMensual === undefined) {
                reg._baseCoberturaMensual = stockCob > 0 ? (stockCob / 1.5) : 0;
            }
            if (reg._factorCoberturaOriginal === undefined) {
                reg._factorCoberturaOriginal = reg._stockCoberturaOriginal > 0 ? 1.5 : 0.0;
            }

            const factorOriginal = reg._factorCoberturaOriginal;
            const nuevoStockCob = Math.round(reg._baseCoberturaMensual * nuevoIndice);
            reg.INDICE_COBERTURA = parseFloat(nuevoIndice.toFixed(2));
            reg.STOCK_COBERTURA = nuevoStockCob;

            // Recalcular Stock Proyectado
            const stock = parseFloat(reg.STOCK || reg.CANT_STOCK || 0);
            const stockGuardar = parseFloat(reg.STOCK_GUARDAR || reg.CANT_STOCK_GUARDAR || 0);
            const ocVer = parseFloat(reg.CANT_PEND_OC_VERANO || 0);
            const ocInv = parseFloat(reg.CANT_PEND_OC_INVIERNO || 0);
            const ocAtemp = parseFloat(reg.CANT_PEND_OC_ATEMPORAL || 0);
            reg.STOCK_PROYECTADO = Math.round(stock + stockGuardar + ocVer + ocInv + ocAtemp - nuevoStockCob);

            // Recalcular Compra Proyectada
            const vVer = parseFloat(reg.VENTA_PROY_VERANO || 0);
            const vInv = parseFloat(reg.VENTA_PROY_INVIERNO || 0);
            reg.COMPRA_PROYECTADA = Math.round(reg.STOCK_PROYECTADO - vVer - vInv);

            // Marcas de edición
            const esDiferente = Math.abs(nuevoIndice - factorOriginal) > 0.001;
            if (esDiferente) {
                reg._coberturaEditada = { valorOriginal: factorOriginal, valorActual: nuevoIndice };
            } else {
                delete reg._coberturaEditada;
            }

            // Actualizar fila visual si está visible en pantalla
            const filaVisual = TablaRendererUtils.filaDeRegistro(tab, rubro, categoria);
            if (filaVisual) {
                TablaRendererUtils.actualizarCeldasCalculadas(filaVisual, reg);
                if (esDiferente) {
                    IndiceEditor.aplicarMarcasVisualesCobertura(filaVisual, nuevoIndice);
                } else {
                    IndiceEditor.limpiarMarcasVisualesCobertura(filaVisual, factorOriginal);
                }
            }

            // Notificar a totales para recalcular tarjetas y footer
            if (typeof TotalesCompra !== 'undefined') {
                TotalesCompra.actualizarDatos(tab, datos);
            }

            // Marcar cambios para no perderlos al retomar versión
            if (typeof RetomarVersion !== 'undefined') {
                RetomarVersion.marcarCambios(tab);
            }

            // Refrescar tramos en el servidor con el nuevo stock proyectado
            IndiceEditor.refrescarTramos(tab, idx, reg, filaVisual);
        });
    }

    static restaurarCoberturaDesdeDatos(fila) {
        const rubro = fila.dataset.rubro;
        const categoria = fila.dataset.categoria;
        const solapaActiva = (fila.closest('tbody')?.id || '').replace('tbody-', '');
        const datos = window.presupuestoApp.getDatos(solapaActiva);
        const reg = datos?.find(item => item.RUBRO === rubro && (item.CATEGORIA_PADRE === categoria || item.CATEGORIA === categoria));
        if (!reg) return;

        const factorOriginal = reg._factorCoberturaOriginal !== undefined
            ? reg._factorCoberturaOriginal
            : (parseFloat(reg._stockCoberturaOriginal || reg.STOCK_COBERTURA || 0) > 0 ? 1.5 : 0.0);

        if (RecalculoFila.aplica(reg) || reg.CALCULO_AR) {
            IndiceEditor.aplicarCambioCoberturaAr(rubro, categoria, factorOriginal);
        } else {
            IndiceEditor.aplicarCambioCobertura(rubro, categoria, solapaActiva, factorOriginal);
        }
        UIUtils.mostrarAlerta(`Índice de cobertura restaurado a ${factorOriginal.toFixed(2)} meses`, 'info');
    }

    static aplicarMarcasVisualesCobertura(fila, nuevoValor) {
        const celda = fila.querySelector('td[data-col="indice-cobertura"]');
        if (!celda) return;
        celda.classList.add('indice-editado');
        const input = celda.querySelector('.indice-input');
        if (input) {
            input.classList.add('input-editado');
            input.value = nuevoValor.toFixed(2);
        }
        if (!celda.querySelector('.btn-restaurar-original')) {
            const btn = document.createElement('button');
            btn.className = 'btn-restaurar-original';
            btn.innerHTML = '×';
            btn.title = 'Restaurar valor original';
            btn.onclick = (e) => {
                e.stopPropagation();
                if (confirm('¿Restaurar índice de cobertura original?')) {
                    IndiceEditor.restaurarCoberturaDesdeDatos(fila);
                }
            };
            celda.appendChild(btn);
        }
    }

    static limpiarMarcasVisualesCobertura(fila, valorOriginal) {
        const celda = fila.querySelector('td[data-col="indice-cobertura"]');
        if (!celda) return;
        celda.classList.remove('indice-editado');
        const input = celda.querySelector('.indice-input');
        if (input) {
            input.classList.remove('input-editado');
            input.value = valorOriginal.toFixed(2);
        }
        const btn = celda.querySelector('.btn-restaurar-original');
        if (btn) btn.remove();
    }

    static configurarEventosRestaurar() {
        document.addEventListener('dblclick', (e) => {
            if (e.target.classList.contains('input-editado')) {
                const celda = e.target.closest('td');
                const fila = celda.closest('tr');
                if (!fila) return;

                if (celda.dataset.col === 'indice-cobertura') {
                    if (confirm('¿Restaurar índice de cobertura original?')) {
                        IndiceEditor.restaurarCoberturaDesdeDatos(fila);
                    }
                    return;
                }

                const temp = celda.dataset.temporada;
                if (!temp) return;
                if (confirm('¿Restaurar original?')) {
                    IndiceEditor.restaurarIndiceOriginalDesdeDatos(fila, temp);
                }
            }
        });
    }

    // ==========================================
    // NOTAS POR FILA
    // ==========================================

    /**
     * Deja la nota en el registro en memoria y actualiza el ícono de la fila.
     * Vive con la fila, igual que el índice editado, hasta que se guarda la versión
     * (detalle.nota). Una nota vacía la borra.
     */
    static asignarNota(solapa, rubro, categoria, texto) {
        const datos = window.presupuestoApp.getDatos(solapa) || [];
        const registro = datos.find(r => r.RUBRO === rubro && r.CATEGORIA_PADRE === categoria);
        if (!registro) return;

        const nota = String(texto || '').trim().slice(0, 500);
        registro.NOTA = nota || null;
        if (typeof RetomarVersion !== 'undefined') RetomarVersion.marcarCambios(solapa);

        const fila = TablaRendererUtils.filaDeRegistro(solapa, rubro, categoria);
        if (fila) TablaRendererUtils.pintarNota(fila, registro);
    }

    /** Agregar o editar la nota de una fila sin tocar sus índices. */
    static editarNota(solapa, rubro, categoria) {
        const registro = (window.presupuestoApp.getDatos(solapa) || [])
            .find(r => r.RUBRO === rubro && r.CATEGORIA_PADRE === categoria);
        if (!registro) return;

        const id = 'modal-nota-' + Date.now();
        const div = document.createElement('div');
        div.innerHTML = `
            <div class="modal fade" id="${id}" tabindex="-1">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-comment-dots me-2"></i>Nota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <p class="small mb-2"><strong>${HistorialManager.escaparHtml(rubro)}</strong> · ${HistorialManager.escaparHtml(categoria)}</p>
                    <textarea class="form-control" rows="3" maxlength="500"
                              placeholder="Por qué se cambió el índice, o cualquier aclaración de esta fila">${HistorialManager.escaparHtml(registro.NOTA || '')}</textarea>
                    <div class="form-text">Se guarda con la versión. Dejala vacía para borrarla.</div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" data-accion="guardar">Aceptar</button>
                  </div>
                </div>
              </div>
            </div>`;
        document.body.appendChild(div);

        const el = document.getElementById(id);
        const modal = new bootstrap.Modal(el);
        el.querySelector('[data-accion="guardar"]').addEventListener('click', () => {
            IndiceEditor.asignarNota(solapa, rubro, categoria, el.querySelector('textarea').value);
            modal.hide();
        });
        el.addEventListener('shown.bs.modal', () => el.querySelector('textarea').focus());
        el.addEventListener('hidden.bs.modal', () => div.remove());
        modal.show();
    }

    static restaurarDesdeModal() {
        if (!IndiceEditor.indiceEditando) return;
        const { rubro, categoria, solapa, temporada, tipo } = IndiceEditor.indiceEditando;
        const filaVisual = TablaRendererUtils.filaDeRegistro(solapa, rubro, categoria);

        if (tipo === 'cobertura') {
            if (filaVisual) {
                IndiceEditor.restaurarCoberturaDesdeDatos(filaVisual);
            } else {
                const reg = (window.presupuestoApp.getDatos(solapa) || [])
                    .find(r => r.RUBRO === rubro && (r.CATEGORIA_PADRE === categoria || r.CATEGORIA === categoria));
                const factorOrig = reg ? (reg._factorCoberturaOriginal ?? 1.5) : 1.5;
                if (reg && reg.CALCULO_AR) IndiceEditor.aplicarCambioCoberturaAr(rubro, categoria, factorOrig);
                else IndiceEditor.aplicarCambioCobertura(rubro, categoria, solapa, factorOrig);
            }
        } else {
            if (filaVisual) {
                IndiceEditor.restaurarIndiceOriginalDesdeDatos(filaVisual, temporada);
            } else {
                const reg = (window.presupuestoApp.getDatos(solapa) || [])
                    .find(r => r.RUBRO === rubro && (r.CATEGORIA_PADRE === categoria || r.CATEGORIA === categoria));
                const orig = reg ? (reg._indicesOriginales?.[temporada] || 1.0) : 1.0;
                IndiceEditor.aplicarCambio(rubro, categoria, solapa, temporada, orig);
            }
        }
        IndiceEditor.modalInstancia.hide();
    }

    static limpiarFormulario() {
        document.getElementById('modal-nuevo-indice').value = '';
        const nota = document.getElementById('modal-nota-indice');
        if (nota) nota.value = '';
        const preview = document.getElementById('preview-calculo');
        if (preview) preview.innerHTML = '';
    }

    static mostrarPreviewCalculo(nuevoIndice) {
        if (!IndiceEditor.indiceEditando || isNaN(nuevoIndice)) return;
        
        let div = document.getElementById('preview-calculo');
        if (!div) {
            div = document.createElement('div');
            div.id = 'preview-calculo';
            div.className = 'alert alert-light mt-2 mb-0 py-2 small';
            document.querySelector('#modalEditarIndice .modal-body').appendChild(div);
        }

        // Argentina: la vista previa sale del servidor (la cobertura ya no se puede
        // estimar acá con una regla de tres: depende de la venta proyectada).
        {
            const ed = IndiceEditor.indiceEditando;
            const solapaCalculo = ed.solapa === 'stock'
                ? (typeof StockSincronizado !== 'undefined' ? StockSincronizado.solapaElegida() : 'verano')
                : ed.solapa;
            const regCalculo = IndiceEditor.datoDe(solapaCalculo, ed.rubro, ed.categoria);
            if (RecalculoFila.aplica(regCalculo)) {
                IndiceEditor.previewServidor(div, solapaCalculo, regCalculo, ed, nuevoIndice);
                return;
            }
        }

        if (IndiceEditor.indiceEditando.tipo === 'cobertura') {
            const { rubro, categoria, solapa, indiceActual } = IndiceEditor.indiceEditando;
            const registro = (window.presupuestoApp.getDatos(solapa) || [])
                .find(r => r.RUBRO === rubro && (r.CATEGORIA_PADRE === categoria || r.CATEGORIA === categoria));
            
            if (!registro) return;

            const baseCob = registro._baseCoberturaMensual !== undefined
                ? registro._baseCoberturaMensual
                : (parseFloat(registro.STOCK_COBERTURA || 0) > 0 ? parseFloat(registro.STOCK_COBERTURA) / 1.5 : 0);
            
            const cobActual = Math.round(baseCob * indiceActual);
            const cobNueva = Math.round(baseCob * nuevoIndice);
            const diffCob = cobNueva - cobActual;

            const stockActual = parseFloat(registro.STOCK || registro.CANT_STOCK || 0);
            const stockGuardar = parseFloat(registro.STOCK_GUARDAR || registro.CANT_STOCK_GUARDAR || 0);
            const ocVer = parseFloat(registro.CANT_PEND_OC_VERANO || 0);
            const ocInv = parseFloat(registro.CANT_PEND_OC_INVIERNO || 0);
            const ocAtemp = parseFloat(registro.CANT_PEND_OC_ATEMPORAL || 0);
            const disponible = stockActual + stockGuardar + ocVer + ocInv + ocAtemp;

            const stockProyActual = Math.round(disponible - cobActual);
            const stockProyNuevo = Math.round(disponible - cobNueva);
            const diffStock = stockProyNuevo - stockProyActual;

            const vVer = parseFloat(registro.VENTA_PROY_VERANO || 0);
            const vInv = parseFloat(registro.VENTA_PROY_INVIERNO || 0);
            const compraActual = Math.round(stockProyActual - vVer - vInv);
            const compraNueva = Math.round(stockProyNuevo - vVer - vInv);
            const diffCompra = compraNueva - compraActual;

            div.innerHTML = `
                <div class="row g-1 text-center py-1">
                    <div class="col-4 border-end">
                        <span class="d-block text-muted" style="font-size:0.75rem;">Stock Cobertura</span>
                        <strong>${FormatoUtils.formatearNumero(cobNueva)}</strong>
                        <span class="d-block small ${diffCob > 0 ? 'text-primary' : (diffCob < 0 ? 'text-secondary' : 'text-muted')}">
                            ${diffCob >= 0 ? '+' : ''}${FormatoUtils.formatearNumero(diffCob)}
                        </span>
                    </div>
                    <div class="col-4 border-end">
                        <span class="d-block text-muted" style="font-size:0.75rem;">Stock Proyectado</span>
                        <strong class="text-info">${FormatoUtils.formatearNumero(stockProyNuevo)}</strong>
                        <span class="d-block small ${diffStock > 0 ? 'text-success' : (diffStock < 0 ? 'text-danger' : 'text-muted')}">
                            ${diffStock >= 0 ? '+' : ''}${FormatoUtils.formatearNumero(diffStock)}
                        </span>
                    </div>
                    <div class="col-4">
                        <span class="d-block text-muted" style="font-size:0.75rem;">Compra Proyectada</span>
                        <strong class="${compraNueva < 0 ? 'text-danger' : 'text-success'}">${FormatoUtils.formatearNumero(compraNueva)}</strong>
                        <span class="d-block small ${diffCompra > 0 ? 'text-success' : (diffCompra < 0 ? 'text-danger' : 'text-muted')}">
                            ${diffCompra >= 0 ? '+' : ''}${FormatoUtils.formatearNumero(diffCompra)}
                        </span>
                    </div>
                </div>
            `;
            return;
        }

        const diff = nuevoIndice - IndiceEditor.indiceEditando.indiceActual;
        const pct = IndiceEditor.indiceEditando.indiceActual ? (diff / IndiceEditor.indiceEditando.indiceActual) * 100 : 0;
        
        div.innerHTML = `
            <strong>Cambio:</strong> 
            <span class="${diff !== 0 ? (diff > 0 ? 'text-success' : 'text-danger') : 'text-muted'}">
                ${diff > 0 ? '+' : ''}${diff.toFixed(2)} (${diff > 0 ? '+' : ''}${pct.toFixed(1)}%)
            </span>
        `;
    }
    
    /**
     * Vista previa de Argentina: simula la edición en recalcular-fila (sin aplicarla)
     * y muestra cuánto cambian venta, cobertura, stock proyectado y compra.
     */
    static previewServidor(div, solapa, registro, ed, valor) {
        clearTimeout(IndiceEditor._timerPreview);
        const token = (IndiceEditor._tokenPreview = (IndiceEditor._tokenPreview || 0) + 1);
        div.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Calculando...</span>';

        IndiceEditor._timerPreview = setTimeout(async () => {
            const t = ed.temporada;
            const cambios = ed.tipo === 'venta' ? { [`venta_manual_${t}`]: Math.round(valor) }
                : ed.tipo === 'cobertura' ? { indice_cobertura: valor }
                : { [`indice_${t}`]: valor, [`venta_manual_${t}`]: null };
            const editado = ed.tipo === 'venta' ? `venta_${t}` : ed.tipo === 'cobertura' ? 'indice_cobertura' : `indice_${t}`;

            const r = await RecalculoFila.simular(solapa, registro, cambios, editado);
            if (token !== IndiceEditor._tokenPreview) return;   // llegó una respuesta vieja
            if (!r.ok) {
                div.innerHTML = `<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>${FiltroMultiple.escapar(r.mensaje)}</span>`;
                return;
            }

            const f = r.fila;
            const n = FormatoUtils.formatearNumero;
            const bloque = (titulo, nuevo, actual, clase = '') => {
                const d = Math.round(nuevo) - Math.round(actual);
                return `<div class="col border-end">
                    <span class="d-block text-muted" style="font-size:0.75rem;">${titulo}</span>
                    <strong class="${clase}">${n(nuevo)}</strong>
                    <span class="d-block small ${d > 0 ? 'text-success' : d < 0 ? 'text-danger' : 'text-muted'}">${d >= 0 ? '+' : ''}${n(d)}</span>
                </div>`;
            };

            let extra = '';
            if (ed.tipo === 'venta') {
                const T = t.toUpperCase();
                const s = f[`SUGERENCIAS_${T}`];
                const indice = f[t === 'invierno' ? 'INDICE_VARIACION_INVIERNO' : 'INDICE_VARIACION'];
                const fmt = (x, d) => Number(x).toLocaleString('es-AR', { minimumFractionDigits: d, maximumFractionDigits: d });
                extra = s
                    ? `<div class="mt-2">Índice resultante: <strong>${s.aproximado ? '≈' : ''}${fmt(s.indice_exacto, s.aproximado ? 4 : 2)}</strong>`
                      + (s.aproximado ? `<span class="text-muted"> · redondos: ${fmt(s.abajo.indice, 2)} → ${n(s.abajo.venta)}, ${fmt(s.arriba.indice, 2)} → ${n(s.arriba.venta)}</span>` : '')
                      + '</div>'
                    : `<div class="mt-2 text-muted">Sin venta anterior: el índice queda en "—" (venta manual).${indice !== undefined ? '' : ''}</div>`;
            }

            const ventaCol = t === 'invierno' ? 'VENTA_PROY_INVIERNO' : 'VENTA_PROY_VERANO';
            div.innerHTML = `
                <div class="row g-1 text-center py-1">
                    ${ed.tipo !== 'cobertura' ? bloque('Venta Proy.', f[ventaCol], registro[ventaCol]) : ''}
                    ${bloque('Stock Cobertura', f.STOCK_COBERTURA, registro.STOCK_COBERTURA)}
                    ${bloque('Stock Proyectado', f.STOCK_PROYECTADO, registro.STOCK_PROYECTADO, 'text-info')}
                    ${bloque('Compra Proyectada', f.COMPRA_PROYECTADA, registro.COMPRA_PROYECTADA, f.COMPRA_PROYECTADA < 0 ? 'text-danger' : 'text-success')}
                </div>${extra}`;
        }, 350);
    }

    // Funciones legacy/soporte para no romper llamadas externas
    static cargarHistorial() {}
    static diagnosticarTemporadas() { return {}; }
}

// Inicializar
document.addEventListener('DOMContentLoaded', () => IndiceEditor.init());
window.IndiceEditor = IndiceEditor;