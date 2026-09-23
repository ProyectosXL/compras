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

        // 4. Actualizar datos en memoria
        datos[indiceReal] = registroActualizado;

        // 5. Actualizar la fila visualmente (Buscar la fila en el DOM)
        const tbody = document.getElementById(`tbody-${solapa}`);
        const filas = tbody.querySelectorAll('tr.fila-datos');
        let filaVisual = null;
        let indexVisual = -1;

        for (let i = 0; i < filas.length; i++) {
            const f = filas[i];
            if (f.children[0]?.textContent === rubro && f.children[1]?.textContent === categoria) {
                filaVisual = f;
                indexVisual = i;
                break;
            }
        }

        if (filaVisual) {
            // Actualizar valores en la tabla
            const contextoTabla = { temporada, index: indexVisual, solapa };
            if (solapa === 'verano') CalculadoraVerano.actualizarFilaTabla(contextoTabla, registroActualizado);
            else if (solapa === 'invierno') CalculadoraInvierno.actualizarFilaTabla(contextoTabla, registroActualizado);
            
            // 6. Gestionar marcas visuales (Editar vs Restaurar)
            const valorOriginal = registro._indicesOriginales[temporada];
            const esDiferente = Math.abs(nuevoIndice - valorOriginal) > 0.001;
            
            if (esDiferente) {
                // Marcar como editado
                if (!registro._indicesEditados) registro._indicesEditados = {};
                registro._indicesEditados[temporada] = {
                    valorOriginal: valorOriginal,
                    valorActual: nuevoIndice
                };
                IndiceEditor.aplicarMarcasVisualesEnFila(filaVisual, temporada, nuevoIndice);
            } else {
                // Limpiar marcas (Restaurado)
                if (registro._indicesEditados) delete registro._indicesEditados[temporada];
                IndiceEditor.limpiarMarcasVisuales(filaVisual, temporada, valorOriginal);
            }
        }

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

    /**
     * Actualiza las celdas de tramo de una fila con el reparto que devuelve el servidor.
     * Si la llamada falla, las celdas quedan marcadas como desactualizadas en vez de
     * mostrar un número viejo como si fuera el nuevo.
     */
    static async refrescarTramos(solapa, indiceReal, registro, filaVisual) {
        const celdas = filaVisual
            ? Array.from(filaVisual.querySelectorAll('td[data-tramo-orden]'))
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
        const rubro = fila.children[0]?.textContent?.trim();
        const categoria = fila.children[1]?.textContent?.trim();
        
        // Buscar solapa activa
        const solapaActiva = document.querySelector('.nav-link.active').getAttribute('data-bs-target').replace('#', '');
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
        IndiceEditor.indiceEditando = { rubro, categoria, indiceActual: parseFloat(indiceActual), solapa, index, temporada };
        
        document.getElementById('modal-rubro').textContent = rubro;
        document.getElementById('modal-categoria').textContent = categoria;
        document.querySelector('#modalEditarIndice .modal-title').textContent = 
            `Editar Índice - ${temporada.charAt(0).toUpperCase() + temporada.slice(1)}`;
        
        const input = document.getElementById('modal-nuevo-indice');
        input.value = parseFloat(indiceActual).toFixed(2);
        
        // Mostrar preview inicial
        IndiceEditor.mostrarPreviewCalculo(parseFloat(indiceActual));
        
        TablaRenderer.resaltarFila(solapa, index, true);
        IndiceEditor.modalInstancia.show();
        setTimeout(() => input.select(), 500);
    }

    static async guardarNuevoIndice() {
        if (!IndiceEditor.indiceEditando) return;
        
        const nuevoIndice = parseFloat(document.getElementById('modal-nuevo-indice').value);
        if (isNaN(nuevoIndice) || nuevoIndice < 0 || nuevoIndice > 10) {
            UIUtils.mostrarAlerta('Ingrese un índice válido (0-10)', 'warning');
            return;
        }
        
        const btnGuardar = document.querySelector('#modalEditarIndice .btn-primary');
        const textoOriginal = btnGuardar.innerHTML;
        btnGuardar.innerHTML = 'Guardando...'; btnGuardar.disabled = true;

        try {
            await new Promise(r => setTimeout(r, 300)); // Pequeña pausa UX
            
            const { rubro, categoria, solapa, temporada } = IndiceEditor.indiceEditando;
            
            // USAR LA FUNCIÓN CENTRALIZADA
            IndiceEditor.aplicarCambio(rubro, categoria, solapa, temporada, nuevoIndice);
            
            UIUtils.mostrarAlerta('Índice actualizado', 'success');
            IndiceEditor.modalInstancia.hide();
            
        } catch (error) {
            console.error(error);
            UIUtils.mostrarAlerta('Error al guardar', 'error');
        } finally {
            btnGuardar.innerHTML = textoOriginal; btnGuardar.disabled = false;
            TablaRenderer.resaltarFila(IndiceEditor.indiceEditando.solapa, IndiceEditor.indiceEditando.index, false);
        }
    }

    // ==========================================
    // UTILS VISUALES
    // ==========================================

    static aplicarMarcasVisualesEnFila(fila, temporada, nuevoValor) {
        const colIndex = temporada === 'verano' ? 4 : 7;
        const celda = fila.children[colIndex];
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
        const colIndex = temporada === 'verano' ? 4 : 7;
        const celda = fila.children[colIndex];
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
                const temporada = celda.getAttribute('data-temporada'); // Asegúrate que el TD tenga este atributo
                // Fallback si no tiene atributo
                const temp = celda.cellIndex === 4 ? 'verano' : 'invierno';
                if (confirm('¿Restaurar original?')) {
                    IndiceEditor.restaurarIndiceOriginalDesdeDatos(fila, temp);
                }
            }
        });
    }

    static limpiarFormulario() {
        document.getElementById('modal-nuevo-indice').value = '';
        const preview = document.getElementById('preview-calculo');
        if (preview) preview.innerHTML = '';
    }

    static mostrarPreviewCalculo(nuevoIndice) {
        if (!IndiceEditor.indiceEditando || isNaN(nuevoIndice)) return;
        const diff = nuevoIndice - IndiceEditor.indiceEditando.indiceActual;
        const pct = IndiceEditor.indiceEditando.indiceActual ? (diff / IndiceEditor.indiceEditando.indiceActual) * 100 : 0;
        
        let div = document.getElementById('preview-calculo');
        if (!div) {
            div = document.createElement('div');
            div.id = 'preview-calculo';
            div.className = 'alert alert-light mt-2 mb-0 py-2 small';
            document.querySelector('#modalEditarIndice .modal-body').appendChild(div);
        }
        
        div.innerHTML = `
            <strong>Cambio:</strong> 
            <span class="${diff !== 0 ? (diff > 0 ? 'text-success' : 'text-danger') : 'text-muted'}">
                ${diff > 0 ? '+' : ''}${diff.toFixed(2)} (${diff > 0 ? '+' : ''}${pct.toFixed(1)}%)
            </span>
        `;
    }
    
    // Funciones legacy/soporte para no romper llamadas externas
    static cargarHistorial() {}
    static diagnosticarTemporadas() { return {}; }
}

// Inicializar
document.addEventListener('DOMContentLoaded', () => IndiceEditor.init());
window.IndiceEditor = IndiceEditor;