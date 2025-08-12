
// Editor de índices de variación - SIMPLIFICADO Y CORREGIDO
// Archivo: presupuestos/js/indice-editor.js

class IndiceEditor {
    static indiceEditando = null;
    static historialCambios = [];
    static modalInstancia = null;

    /**
     * CORREGIDO: Actualizar datos en memoria usando el índice real de los datos originales
     */
    static actualizarDatosMemoriaFrontendCorregido(nuevoIndice) {
        const app = window.presupuestoApp;
        const datos = app.getDatos(IndiceEditor.indiceEditando.solapa);
        
        // CORRECCIÓN: Encontrar el índice real en los datos originales
        const indiceReal = IndiceEditor.encontrarIndiceRealEnDatos(
            IndiceEditor.indiceEditando.rubro,
            IndiceEditor.indiceEditando.categoria,
            IndiceEditor.indiceEditando.solapa
        );
        
        if (indiceReal === null) {
            console.error('❌ No se pudo encontrar el registro en los datos originales');
            return;
        }
        
        const registro = datos[indiceReal];
        if (!registro) {
            console.error('❌ Registro no encontrado en índice real:', indiceReal);
            return;
        }
        
        const solapa = IndiceEditor.indiceEditando.solapa;
        const temporada = IndiceEditor.indiceEditando.temporada;
        
        console.log(`🔄 Actualizando SOLO índice ${temporada} con valor ${nuevoIndice} en solapa ${solapa}`);
        console.log(`🔍 ANTES - Índices: V=${registro.INDICE_VARIACION}, I=${registro.INDICE_VARIACION_INVIERNO || 'undefined'}`);
        console.log(`🎯 Usando índice real ${indiceReal} para ${registro.RUBRO} - ${registro.CATEGORIA_PADRE}`);
        
        // CREAR UNA COPIA DEL REGISTRO PARA NO MODIFICAR EL ORIGINAL HASTA EL FINAL
        const registroTemporal = { ...registro };
        
        // CORRECCIÓN: Solo actualizar el índice específico que se está editando
        if (temporada === 'verano') {
            registroTemporal.INDICE_VARIACION = parseFloat(nuevoIndice.toFixed(2));
            console.log(`✅ Actualizando solo INDICE_VARIACION a ${registroTemporal.INDICE_VARIACION}`);
        } else if (temporada === 'invierno') {
            // Mantener el índice de verano existente
            registroTemporal.INDICE_VARIACION = parseFloat(registro.INDICE_VARIACION || 1.0);
            
            // Actualizar solo el índice de invierno
            registroTemporal.INDICE_VARIACION_INVIERNO = parseFloat(nuevoIndice.toFixed(2));
            console.log(`✅ Manteniendo INDICE_VARIACION en ${registroTemporal.INDICE_VARIACION}`);
            console.log(`✅ Actualizando solo INDICE_VARIACION_INVIERNO a ${registroTemporal.INDICE_VARIACION_INVIERNO}`);
        }
        
        console.log(`🔍 DESPUÉS - Índices: V=${registroTemporal.INDICE_VARIACION}, I=${registroTemporal.INDICE_VARIACION_INVIERNO || 'undefined'}`);
        
        // Actualizar indiceEditando con el índice real para las calculadoras
        const indiceEditandoConIndiceReal = {
            ...IndiceEditor.indiceEditando,
            index: indiceReal  // USAR EL ÍNDICE REAL
        };
        
        // Delegar a la calculadora específica según la solapa
        let registroActualizado;
        
        if (solapa === 'verano') {
            // Usar calculadora de verano con el índice real
            registroActualizado = CalculadoraVerano.actualizarDatosSolapa(nuevoIndice, indiceEditandoConIndiceReal, registroTemporal);
        } else if (solapa === 'invierno') {
            // Usar calculadora de invierno con el índice real
            registroActualizado = CalculadoraInvierno.actualizarDatosSolapa(nuevoIndice, indiceEditandoConIndiceReal, registroTemporal);
        } else {
            console.error('Solapa no reconocida:', solapa);
            return;
        }
        
        // Actualizar los datos en memoria usando el índice real
        datos[indiceReal] = registroActualizado;
        
        console.log(`✅ Datos actualizados en memoria para ${registro.RUBRO} - ${registro.CATEGORIA_PADRE}`);
        console.log(`  Índice verano final: ${registroActualizado.INDICE_VARIACION}`);
        console.log(`  Índice invierno final: ${registroActualizado.INDICE_VARIACION_INVIERNO || 'no definido'}`);
    }

    /**
     * NUEVO: Encontrar el índice real en los datos originales cuando hay filtros aplicados
     */
    static encontrarIndiceRealEnDatos(rubro, categoria, solapa) {
        const app = window.presupuestoApp;
        const datosOriginales = app.getDatos(solapa);
        
        // Buscar el registro exacto en los datos originales
        const indiceReal = datosOriginales.findIndex(item => 
            item.RUBRO === rubro && 
            (item.CATEGORIA_PADRE === categoria || item.CATEGORIA === categoria)
        );
        
        if (indiceReal === -1) {
            console.error(`❌ No se encontró el registro en datos originales: ${rubro} - ${categoria}`);
            return null;
        }
        
        console.log(`🎯 Índice visual: ${IndiceEditor.indiceEditando.index} → Índice real: ${indiceReal}`);
        return indiceReal;
    }

    /**
     * CORREGIDO: Actualizar fila usando índice real y luego buscar en tabla visual
     */
    static actualizarFilaTablaSoloFrontendCorregido() {
        const solapa = IndiceEditor.indiceEditando.solapa;
        
        console.log(`🔄 Actualizando fila en tabla ${solapa}`);
        
        // Encontrar el índice real primero
        const indiceReal = IndiceEditor.encontrarIndiceRealEnDatos(
            IndiceEditor.indiceEditando.rubro,
            IndiceEditor.indiceEditando.categoria,
            solapa
        );
        
        if (indiceReal === null) {
            console.error('❌ No se pudo encontrar el registro para actualizar la fila');
            return;
        }
        
        // Obtener el registro actualizado
        const registroActualizado = window.presupuestoApp.getDatos(solapa)[indiceReal];
        
        // BUSCAR LA FILA VISUAL en la tabla por rubro y categoría (no por índice)
        const tbody = document.getElementById(`tbody-${solapa}`);
        const filas = tbody.querySelectorAll('tr.fila-datos');
        
        let filaEncontrada = null;
        let indiceVisual = -1;
        
        for (let i = 0; i < filas.length; i++) {
            const fila = filas[i];
            const rubroCell = fila.children[0]?.textContent?.trim();
            const categoriaCell = fila.children[1]?.textContent?.trim();
            
            if (rubroCell === IndiceEditor.indiceEditando.rubro && 
                categoriaCell === IndiceEditor.indiceEditando.categoria) {
                filaEncontrada = fila;
                indiceVisual = i;
                break;
            }
        }
        
        if (!filaEncontrada) {
            console.error('❌ No se encontró la fila visual en la tabla');
            return;
        }
        
        console.log(`🎯 Fila encontrada en posición visual: ${indiceVisual}`);
        
        // Crear objeto temporal para las calculadoras con el índice visual correcto
        const indiceEditandoParaTabla = {
            ...IndiceEditor.indiceEditando,
            index: indiceVisual  // USAR EL ÍNDICE VISUAL PARA ACTUALIZAR LA TABLA
        };
        
        // Delegar a la calculadora específica para actualizar la fila visual
        if (solapa === 'verano') {
            CalculadoraVerano.actualizarFilaTabla(indiceEditandoParaTabla, registroActualizado);
        } else if (solapa === 'invierno') {
            CalculadoraInvierno.actualizarFilaTabla(indiceEditandoParaTabla, registroActualizado);
        } else {
            console.error('Solapa no reconocida para actualización de fila:', solapa);
        }
    }

    /**
     * Mostrar preview del cálculo indicando método
     */
    static mostrarPreviewCalculo(nuevoIndice) {
        if (!IndiceEditor.indiceEditando || isNaN(nuevoIndice)) return;
        
        const indiceFormateado = parseFloat(nuevoIndice.toFixed(2));
        const diferencia = indiceFormateado - IndiceEditor.indiceEditando.indiceActual;
        const porcentajeCambio = (diferencia / IndiceEditor.indiceEditando.indiceActual) * 100;
        
        let previewElement = document.getElementById('preview-calculo');
        if (!previewElement) {
            previewElement = document.createElement('div');
            previewElement.id = 'preview-calculo';
            previewElement.className = 'mt-2 p-2 bg-light rounded';
            document.querySelector('#modalEditarIndice .modal-body').appendChild(previewElement);
        }
        
        // Determinar método de cálculo actual
        const fechaActual = new Date();
        const temporadaActual = IndiceEditor.determinarTemporadaActual(fechaActual);
        const solapa = IndiceEditor.indiceEditando.solapa;
        
        let infoCalculoVerano = '';
        let infoCalculoInvierno = '';
        
        // Lógica específica según solapa y temporada
        if (solapa === 'verano') {
            if (temporadaActual.tipo === 'VERANO' && temporadaActual.enCurso) {
                const diasRestantes = IndiceEditor.calcularDiasRestantesVerano(fechaActual);
                infoCalculoVerano = `<span class="text-primary">🔥 VERANO: Proporcional actual + Próximo completo (${diasRestantes} días restantes)</span>`;
                infoCalculoInvierno = `<span class="text-muted">❄️ INVIERNO: Próximo completo</span>`;
            } else {
                infoCalculoVerano = `<span class="text-muted">🔥 VERANO: Próximo completo</span>`;
                if (temporadaActual.tipo === 'INVIERNO') {
                    const diasRestantes = IndiceEditor.calcularDiasRestantesInvierno(fechaActual);
                    infoCalculoInvierno = `<span class="text-info">❄️ INVIERNO: Proporcional (${diasRestantes} días restantes)</span>`;
                } else {
                    infoCalculoInvierno = `<span class="text-muted">❄️ INVIERNO: Próximo completo</span>`;
                }
            }
        } else if (solapa === 'invierno') {
            if (temporadaActual.tipo === 'INVIERNO' && temporadaActual.enCurso) {
                const diasRestantes = IndiceEditor.calcularDiasRestantesInvierno(fechaActual);
                infoCalculoVerano = `<span class="text-muted">🔥 VERANO: Próximo completo</span>`;
                infoCalculoInvierno = `<span class="text-info">❄️ INVIERNO: Proporcional actual + Próximo completo (${diasRestantes} días restantes)</span>`;
            } else {
                if (temporadaActual.tipo === 'VERANO') {
                    const diasRestantes = IndiceEditor.calcularDiasRestantesVerano(fechaActual);
                    infoCalculoVerano = `<span class="text-primary">🔥 VERANO: Proporcional (${diasRestantes} días restantes)</span>`;
                } else {
                    infoCalculoVerano = `<span class="text-muted">🔥 VERANO: Próximo completo</span>`;
                }
                infoCalculoInvierno = `<span class="text-muted">❄️ INVIERNO: Próximo completo</span>`;
            }
        }
        
        let mensaje = `Cambio: ${diferencia.toFixed(2)} `;
        mensaje += `(${porcentajeCambio > 0 ? '+' : ''}${porcentajeCambio.toFixed(1)}%)`;
        
        previewElement.innerHTML = `
            <small class="text-muted">
                <strong>Preview de Cálculo (Solapa ${solapa.toUpperCase()}):</strong><br>
                Índice anterior: ${IndiceEditor.indiceEditando.indiceActual.toFixed(2)}<br>
                Índice nuevo: ${indiceFormateado.toFixed(2)}<br>
                ${mensaje}<br><br>
                <strong>Método por temporada:</strong><br>
                ${infoCalculoVerano}<br>
                ${infoCalculoInvierno}
            </small>
        `;
    }

    /**
     * Determinar temporada actual con más precisión
     */
    static determinarTemporadaActual(fecha) {
        const mes = fecha.getMonth() + 1; // 1-12
        
        if (mes >= 8 || mes === 1) {
            // VERANO: Agosto a Enero
            return {
                tipo: 'VERANO',
                enCurso: true,
                descripcion: mes >= 8 ? 'Verano inicio' : 'Verano final'
            };
        } else if (mes >= 2 && mes <= 7) {
            // INVIERNO: Febrero a Julio
            return {
                tipo: 'INVIERNO',
                enCurso: true,
                descripcion: mes <= 4 ? 'Invierno inicio' : 'Invierno final'
            };
        } else {
            // No debería llegar aquí, pero por seguridad
            return {
                tipo: 'TRANSICION',
                enCurso: false,
                descripcion: 'Período de transición'
            };
        }
    }

    /**
     * Calcular días restantes de temporada de verano
     */
    static calcularDiasRestantesVerano(fecha) {
        const mes = fecha.getMonth() + 1;
        const ano = fecha.getFullYear();
        
        let finVerano;
        if (mes >= 8) {
            finVerano = new Date(ano + 1, 0, 31, 23, 59, 59);
        } else if (mes === 1) {
            finVerano = new Date(ano, 0, 31, 23, 59, 59);
        } else {
            return 0;
        }
        
        if (fecha > finVerano) return 0;
        return Math.ceil((finVerano - fecha) / (1000 * 60 * 60 * 24));
    }

    /**
     * Calcular días restantes de temporada de invierno
     */
    static calcularDiasRestantesInvierno(fecha) {
        const mes = fecha.getMonth() + 1;
        const ano = fecha.getFullYear();
        
        if (mes < 2 || mes > 7) return 0;
        
        const finInvierno = new Date(ano, 6, 31, 23, 59, 59);
        if (fecha > finInvierno) return 0;
        
        return Math.ceil((finInvierno - fecha) / (1000 * 60 * 60 * 24));
    }

    // ==========================================
    // MÉTODOS DE INTERFAZ (SIN CAMBIOS MAYORES)
    // ==========================================

    static init() {
        IndiceEditor.configurarModal();
        IndiceEditor.configurarEventListeners();
        IndiceEditor.configurarEventosRestaurar();
    }

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
            if (e.key === 'Escape' && IndiceEditor.modalInstancia) {
                IndiceEditor.modalInstancia.hide();
            }
            
            if (e.key === 'Enter' && e.ctrlKey && IndiceEditor.indiceEditando) {
                IndiceEditor.guardarNuevoIndice();
            }
        });

        const inputIndice = document.getElementById('modal-nuevo-indice');
        if (inputIndice) {
            inputIndice.addEventListener('input', IndiceEditor.validarInput);
            inputIndice.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    IndiceEditor.guardarNuevoIndice();
                }
            });
        }
    }

    static editarIndice(rubro, categoria, indiceActual, solapa, index, temporada = 'verano') {
        IndiceEditor.indiceEditando = {
            rubro: rubro,
            categoria: categoria,
            indiceActual: indiceActual,
            solapa: solapa,
            index: index,
            temporada: temporada
        };
        
        const tituloTemporada = temporada === 'verano' ? 'Verano' : 'Invierno';
        
        document.getElementById('modal-rubro').textContent = rubro;
        document.getElementById('modal-categoria').textContent = categoria;
        document.querySelector('#modalEditarIndice .modal-title').innerHTML = 
            `<i class="fas fa-edit me-2"></i>Editar Índice de Variación - ${tituloTemporada}`;
        document.getElementById('modal-nuevo-indice').value = FormatoUtils.formatearDecimal(indiceActual, 2);
        
        TablaRenderer.resaltarFila(solapa, index, true);
        
        if (IndiceEditor.modalInstancia) {
            IndiceEditor.modalInstancia.show();
        }
        
        setTimeout(() => {
            const input = document.getElementById('modal-nuevo-indice');
            if (input) {
                input.focus();
                input.select();
            }
        }, 500);
    }

    static async guardarNuevoIndice() {
        if (!IndiceEditor.indiceEditando) return;
        
        const nuevoIndice = parseFloat(document.getElementById('modal-nuevo-indice').value);
        
        if (!IndiceEditor.validarIndice(nuevoIndice)) {
            return;
        }
        
        const btnGuardar = document.querySelector('#modalEditarIndice .btn-primary');
        const textoOriginal = btnGuardar.innerHTML;
        IndiceEditor.mostrarLoadingBoton(btnGuardar, true);
        
        try {
            await new Promise(resolve => setTimeout(resolve, 500));
            
            // Usar métodos simplificados
            IndiceEditor.actualizarDatosMemoriaFrontendCorregido(nuevoIndice);
            IndiceEditor.actualizarFilaTablaSoloFrontendCorregido();
            
            // AGREGAR: Marcar índice como editado
            IndiceEditor.marcarIndiceEditado(
                IndiceEditor.indiceEditando.solapa,
                IndiceEditor.indiceEditando.index,
                IndiceEditor.indiceEditando.temporada,
                nuevoIndice
            );
            
            IndiceEditor.registrarCambio(nuevoIndice);
            
            // Notificar cambio a totales si es necesario
            if (['verano', 'invierno'].includes(IndiceEditor.indiceEditando.solapa)) {
                if (typeof TotalesCompra !== 'undefined') {
                    TotalesCompra.onIndiceActualizado(
                        IndiceEditor.indiceEditando.solapa,
                        IndiceEditor.indiceEditando.rubro,
                        IndiceEditor.indiceEditando.categoria,
                        nuevoIndice
                    );
                }
            }
            
            UIUtils.mostrarAlerta('Índice actualizado correctamente', 'success');
            IndiceEditor.modalInstancia.hide();
            
        } catch (error) {
            console.error('Error actualizando índice:', error);
            UIUtils.mostrarAlerta('Error al actualizar índice', 'error');
        } finally {
            IndiceEditor.mostrarLoadingBoton(btnGuardar, false, textoOriginal);
            TablaRenderer.resaltarFila(IndiceEditor.indiceEditando.solapa, IndiceEditor.indiceEditando.index, false);
        }
    }

    static validarIndice(indice) {
        if (isNaN(indice)) {
            UIUtils.mostrarAlerta('El índice debe ser un número válido', 'warning');
            IndiceEditor.marcarInputError(true);
            return false;
        }
        
        if (indice < 0) {
            UIUtils.mostrarAlerta('El índice no puede ser negativo', 'warning');
            IndiceEditor.marcarInputError(true);
            return false;
        }
        
        if (indice > 10) {
            UIUtils.mostrarAlerta('El índice no puede ser mayor a 10', 'warning');
            IndiceEditor.marcarInputError(true);
            return false;
        }
        
        IndiceEditor.marcarInputError(false);
        return true;
    }

    static validarInput(event) {
        const valor = parseFloat(event.target.value);
        const input = event.target;
        
        if (isNaN(valor) || valor < 0 || valor > 10) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
        } else {
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
        }
        
        IndiceEditor.mostrarPreviewCalculo(valor);
    }

    static marcarInputError(error) {
        const input = document.getElementById('modal-nuevo-indice');
        if (error) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
        } else {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
        }
    }

    static mostrarLoadingBoton(boton, mostrar, textoOriginal = 'Guardar') {
        if (mostrar) {
            boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
            boton.disabled = true;
        } else {
            boton.innerHTML = textoOriginal;
            boton.disabled = false;
        }
    }

    static registrarCambio(nuevoIndice) {
        const cambio = {
            rubro: IndiceEditor.indiceEditando.rubro,
            categoria: IndiceEditor.indiceEditando.categoria,
            solapa: IndiceEditor.indiceEditando.solapa,
            temporada: IndiceEditor.indiceEditando.temporada,
            indiceOriginal: IndiceEditor.indiceEditando.indiceActual,
            indiceNuevo: nuevoIndice,
            timestamp: Date.now(),
            fecha: new Date().toLocaleString('es-AR'),
            usuario: 'current_user'
        };
        
        // Buscar si ya existe un cambio para este item
        const indiceExistente = IndiceEditor.historialCambios.findIndex(c => 
            c.rubro === cambio.rubro && 
            c.categoria === cambio.categoria && 
            c.temporada === cambio.temporada
        );
        
        if (indiceExistente >= 0) {
            // Actualizar el cambio existente manteniendo el índice original
            const cambioExistente = IndiceEditor.historialCambios[indiceExistente];
            cambio.indiceOriginal = cambioExistente.indiceOriginal;
            IndiceEditor.historialCambios[indiceExistente] = cambio;
        } else {
            // Nuevo cambio
            IndiceEditor.historialCambios.unshift(cambio);
        }
        
        // Mantener máximo 100 cambios únicos
        if (IndiceEditor.historialCambios.length > 100) {
            IndiceEditor.historialCambios = IndiceEditor.historialCambios.slice(0, 100);
        }
        
        if (typeof StorageUtils !== 'undefined') {
            StorageUtils.guardar('historial_indices', IndiceEditor.historialCambios, 30 * 24 * 60 * 60 * 1000);
        }
        
        // Marcar como editado en los datos
        const app = window.presupuestoApp;
        const datos = app.getDatos(IndiceEditor.indiceEditando.solapa);
        if (datos[IndiceEditor.indiceEditando.index]) {
            datos[IndiceEditor.indiceEditando.index]._editado = true;
            datos[IndiceEditor.indiceEditando.index]._indiceOriginal = cambio.indiceOriginal;
        }
    }

    static limpiarFormulario() {
        document.getElementById('modal-rubro').textContent = '';
        document.getElementById('modal-categoria').textContent = '';
        document.getElementById('modal-nuevo-indice').value = '';
        
        const input = document.getElementById('modal-nuevo-indice');
        input.classList.remove('is-valid', 'is-invalid');
        
        const preview = document.getElementById('preview-calculo');
        if (preview) preview.remove();
    }

    static cargarHistorial() {
        if (typeof StorageUtils !== 'undefined') {
            const historial = StorageUtils.obtener('historial_indices');
            if (historial && Array.isArray(historial)) {
                IndiceEditor.historialCambios = historial;
            }
        }
    }

    // Funciones de diagnóstico simplificadas
    static diagnosticarTemporadas() {
        const fechaActual = new Date();
        const temporada = IndiceEditor.determinarTemporadaActual(fechaActual);
        
        const info = {
            fecha_actual: fechaActual.toLocaleDateString('es-AR'),
            mes: fechaActual.getMonth() + 1,
            temporada_detectada: temporada.tipo,
            temporada_en_curso: temporada.enCurso,
            descripcion: temporada.descripcion,
            dias_restantes_verano: temporada.tipo === 'VERANO' ? IndiceEditor.calcularDiasRestantesVerano(fechaActual) : null,
            dias_restantes_invierno: temporada.tipo === 'INVIERNO' ? IndiceEditor.calcularDiasRestantesInvierno(fechaActual) : null
        };
        
        console.group('🌡️ DIAGNÓSTICO TEMPORADAS');
        console.table(info);
        console.groupEnd();
        
        return info;
    }

    static simularFecha(mes, dia = 15) {
        const fechaSimulada = new Date(2025, mes - 1, dia);
        const temporada = IndiceEditor.determinarTemporadaActual(fechaSimulada);
        
        console.log(`🎯 SIMULACIÓN FECHA: ${fechaSimulada.toLocaleDateString('es-AR')}`);
        console.log(`Temporada: ${temporada.tipo} (${temporada.descripcion})`);
        console.log(`En curso: ${temporada.enCurso}`);
        
        return { fecha: fechaSimulada, temporada };
    }

    static marcarIndiceEditado(solapa, index, temporada, nuevoValor) {
        const tbody = document.getElementById(`tbody-${solapa}`);
        const fila = tbody.children[index];
        
        if (!fila) {
            console.warn('Fila no encontrada para marcar como editada');
            return;
        }
        
        // Determinar qué celda corresponde según la temporada
        let celdaIndice;
        if (temporada === 'verano') {
            celdaIndice = fila.children[4]; // Índice Ver. Variación
        } else if (temporada === 'invierno') {
            celdaIndice = fila.children[7]; // Índice Inv. Variación
        }
        
        if (celdaIndice) {
            // Agregar clases de resaltado
            celdaIndice.classList.add('indice-editado', 'animate-pulse');
            
            // Cambiar el estilo del input
            const input = celdaIndice.querySelector('.indice-input');
            if (input) {
                input.classList.add('input-editado');
                
                // Obtener valor original del registro
                const app = window.presupuestoApp;
                const datos = app.getDatos(solapa);
                const registro = datos[index];
                const valorOriginal = TablaRendererUtils.obtenerIndiceOriginal(registro);
                
                // Agregar tooltip con valor original
                input.setAttribute('data-valor-original', valorOriginal.toFixed(2));
                input.setAttribute('title', `Valor original: ${valorOriginal.toFixed(2)} | Valor actual: ${nuevoValor.toFixed(2)}`);
                
                // Crear botón de restaurar si no existe
                if (!celdaIndice.querySelector('.btn-restaurar-original')) {
                    const btnRestaurar = document.createElement('button');
                    btnRestaurar.className = 'btn-restaurar-original';
                    btnRestaurar.innerHTML = '×';
                    btnRestaurar.title = 'Restaurar valor original';
                    btnRestaurar.onclick = (e) => {
                        e.stopPropagation();
                        if (confirm(`¿Restaurar índice ${temporada} al valor original ${valorOriginal.toFixed(2)}?`)) {
                            IndiceEditor.restaurarIndiceOriginal(solapa, index, temporada);
                        }
                    };
                    celdaIndice.appendChild(btnRestaurar);
                }
            }
            
            // Remover animación después de 2 segundos
            setTimeout(() => {
                celdaIndice.classList.remove('animate-pulse');
            }, 2000);
            
            console.log(`🎨 Índice ${temporada} marcado como editado en fila ${index} (valor: ${nuevoValor.toFixed(2)})`);
        } else {
            console.error(`❌ No se encontró la celda para temporada ${temporada} en fila ${index}`);
        }
    }

    static restaurarIndiceOriginal(solapa, index, temporada) {
        const app = window.presupuestoApp;
        const datos = app.getDatos(solapa);
        const registro = datos[index];
        
        if (!registro) {
            console.error('Registro no encontrado para restaurar');
            return;
        }
        
        // Obtener valor original
        const valorOriginal = TablaRendererUtils.obtenerIndiceOriginal(registro);
        
        // Configurar para el editor (simular la estructura necesaria)
        IndiceEditor.indiceEditando = {
            rubro: registro.RUBRO,
            categoria: registro.CATEGORIA_PADRE,
            indiceActual: valorOriginal,
            solapa: solapa,
            index: index,
            temporada: temporada
        };
        
        // Restaurar en el registro
        if (temporada === 'verano') {
            registro.INDICE_VARIACION = valorOriginal;
        } else if (temporada === 'invierno') {
            registro.INDICE_VARIACION_INVIERNO = valorOriginal;
        }
        
        // Recalcular con el valor original
        IndiceEditor.actualizarDatosMemoriaFrontendCorregido(valorOriginal);
        IndiceEditor.actualizarFilaTablaSoloFrontendCorregido();
        
        // Limpiar marcas de edición
        const tbody = document.getElementById(`tbody-${solapa}`);
        const fila = tbody.children[index];
        
        if (fila) {
            let celdaIndice;
            if (temporada === 'verano') {
                celdaIndice = fila.children[4];
            } else if (temporada === 'invierno') {
                celdaIndice = fila.children[7];
            }
            
            if (celdaIndice) {
                // Remover clases de edición
                celdaIndice.classList.remove('indice-editado');
                
                const input = celdaIndice.querySelector('.indice-input');
                if (input) {
                    input.classList.remove('input-editado');
                    input.removeAttribute('title');
                    input.removeAttribute('data-valor-original');
                }
                
                // Remover botón de restaurar
                const btnRestaurar = celdaIndice.querySelector('.btn-restaurar-original');
                if (btnRestaurar) {
                    btnRestaurar.remove();
                }
            }
        }
        
        // Limpiar el estado de edición
        IndiceEditor.indiceEditando = null;
        
        UIUtils.mostrarAlerta(`Índice ${temporada} restaurado al valor original: ${valorOriginal.toFixed(2)}`, 'info');
        
        console.log(`🔄 Índice ${temporada} restaurado a valor original: ${valorOriginal.toFixed(2)}`);
    }

    /**
     * NUEVO: Configurar eventos de doble clic para restaurar
     */
    static configurarEventosRestaurar() {
        document.addEventListener('dblclick', function(event) {
            if (event.target.classList.contains('input-editado')) {
                const celda = event.target.closest('td');
                if (celda && celda.classList.contains('indice-editado')) {
                    const fila = celda.closest('tr');
                    const tbody = fila.parentNode;
                    const index = Array.from(tbody.children).indexOf(fila);
                    
                    // Determinar solapa y temporada
                    const tablaId = tbody.closest('table').id;
                    const solapa = tablaId.replace('tabla-', '');
                    const temporada = celda.getAttribute('data-temporada');
                    
                    if (confirm(`¿Restaurar índice ${temporada} al valor original?`)) {
                        IndiceEditor.restaurarIndiceOriginal(solapa, index, temporada);
                    }
                }
            }
        });
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    IndiceEditor.init();
    IndiceEditor.cargarHistorial();
    
    // Funciones globales para diagnóstico
    window.diagnosticarTemporadas = () => IndiceEditor.diagnosticarTemporadas();
    window.simularFecha = (mes, dia) => IndiceEditor.simularFecha(mes, dia);
    
    console.log('✅ IndiceEditor SIMPLIFICADO cargado - Delega a calculadoras específicas');
});


