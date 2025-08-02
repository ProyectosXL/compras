
// Editor de índices de variación - CORREGIDO PARA TEMPORADA ACTUAL
// Archivo: presupuestos/js/indice-editor.js

class IndiceEditor {
    static indiceEditando = null;
    static historialCambios = [];
    static modalInstancia = null;

    static actualizarDatosMemoriaFrontendCorregido(nuevoIndice) {
        const app = window.presupuestoApp;
        const datos = app.getDatos(IndiceEditor.indiceEditando.solapa);
        
        if (datos[IndiceEditor.indiceEditando.index]) {
            const registro = datos[IndiceEditor.indiceEditando.index];
            const solapa = IndiceEditor.indiceEditando.solapa;
            
            console.log(`🔄 Delegando cálculo a calculadora específica de ${solapa.toUpperCase()}`);
            
            // Delegar a la calculadora específica según la solapa
            let registroActualizado;
            
            if (solapa === 'verano') {
                registroActualizado = CalculadoraVerano.actualizarDatosSolapa(nuevoIndice, IndiceEditor.indiceEditando, registro);
            } else if (solapa === 'invierno') {
                registroActualizado = CalculadoraInvierno.actualizarDatosSolapa(nuevoIndice, IndiceEditor.indiceEditando, registro);
            } else {
                console.error('Solapa no reconocida:', solapa);
                return;
            }
            
            // Actualizar los datos en memoria
            datos[IndiceEditor.indiceEditando.index] = registroActualizado;
        }
    }

    /**
     * NUEVO: Determinar temporada actual con más precisión
     */
    static determinarTemporadaActual(fecha) {
        const mes = fecha.getMonth() + 1; // 1-12
        const dia = fecha.getDate();
        
        // Definir rangos exactos de temporadas
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
     * CORREGIDO: Mostrar preview del cálculo indicando método
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
        
        // CORRECCIÓN: Determinar método de cálculo actual
        const fechaActual = new Date();
        const temporadaActual = IndiceEditor.determinarTemporadaActual(fechaActual);
        
        let infoCalculoVerano = '';
        let infoCalculoInvierno = '';
        
        if (temporadaActual.tipo === 'VERANO' && temporadaActual.enCurso) {
            const diasRestantes = IndiceEditor.calcularDiasRestantesVerano(fechaActual);
            infoCalculoVerano = `<span class="text-primary">🔥 VERANO: Cálculo proporcional (${diasRestantes} días restantes)</span>`;
            infoCalculoInvierno = `<span class="text-muted">❄️ INVIERNO: Cálculo normal</span>`;
        } else if (temporadaActual.tipo === 'INVIERNO' && temporadaActual.enCurso) {
            const diasRestantes = IndiceEditor.calcularDiasRestantesInvierno(fechaActual);
            infoCalculoVerano = `<span class="text-muted">🔥 VERANO: Cálculo normal</span>`;
            infoCalculoInvierno = `<span class="text-info">❄️ INVIERNO: Cálculo proporcional (${diasRestantes} días restantes)</span>`;
        } else {
            infoCalculoVerano = `<span class="text-muted">🔥 VERANO: Cálculo normal</span>`;
            infoCalculoInvierno = `<span class="text-muted">❄️ INVIERNO: Cálculo normal</span>`;
        }
        
        let mensaje = `Cambio: ${diferencia.toFixed(2)} `;
        mensaje += `(${porcentajeCambio > 0 ? '+' : ''}${porcentajeCambio.toFixed(1)}%)`;
        
        previewElement.innerHTML = `
            <small class="text-muted">
                <strong>Preview de Cálculo:</strong><br>
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
     * CORREGIDO: Calcular días restantes de temporada de verano usando días reales
     */
    static calcularDiasRestantesVerano(fecha) {
        const mes = fecha.getMonth() + 1;
        const ano = fecha.getFullYear();
        
        let finVerano;
        if (mes >= 8) {
            // Estamos en agosto-diciembre, el verano termina el 31 de enero del año siguiente
            finVerano = new Date(ano + 1, 0, 31, 23, 59, 59);
        } else if (mes === 1) {
            // Estamos en enero, el verano termina el 31 de enero del mismo año
            finVerano = new Date(ano, 0, 31, 23, 59, 59);
        } else {
            return 0; // No estamos en temporada de verano
        }
        
        if (fecha > finVerano) {
            return 0;
        }
        
        const diferencia = finVerano - fecha;
        const diasRestantes = Math.ceil(diferencia / (1000 * 60 * 60 * 24));
        console.log(`Días restantes VERANO: ${diasRestantes}`);
        return diasRestantes;
    }

    /**
     * CORREGIDO: Calcular días restantes de temporada de invierno usando días reales
     */
    static calcularDiasRestantesInvierno(fecha) {
        const mes = fecha.getMonth() + 1;
        const ano = fecha.getFullYear();
        
        if (mes < 2 || mes > 7) {
            return 0; // No estamos en temporada de invierno
        }
        
        const finInvierno = new Date(ano, 6, 31, 23, 59, 59); // 31 de julio
        
        if (fecha > finInvierno) {
            return 0;
        }
        
        const diferencia = finInvierno - fecha;
        const diasRestantes = Math.ceil(diferencia / (1000 * 60 * 60 * 24));
        console.log(`Días restantes INVIERNO: ${diasRestantes}`);
        return diasRestantes;
    }

    /**
     * CORREGIDO: Extraer venta anterior con búsqueda mejorada
     */
    static extraerVentaAnteriorCorregida(registro, temporada) {
        const anoActual = new Date().getFullYear() % 100;
        
        if (temporada === 'VERANO') {
            // Para VERANO, buscar formato XX-XX del año anterior
            for (let i = 1; i <= 3; i++) {
                let anoInicialAnterior = (anoActual - i) < 0 ? (anoActual - i + 100) : (anoActual - i);
                let anoFinalAnterior = (anoActual - i + 1) < 0 ? (anoActual - i + 1 + 100) : (anoActual - i + 1);
                
                const anoInicialStr = anoInicialAnterior.toString().padStart(2, '0');
                const anoFinalStr = anoFinalAnterior.toString().padStart(2, '0');
                
                const posiblesColumnas = [
                    `VERANO ${anoInicialStr}-${anoFinalStr}`,
                    `VERANO ${anoInicialStr}`,
                    `VERANO_${anoInicialStr}`,
                    `VERANO${anoInicialStr}`,
                    `VTA_VERANO_${anoInicialStr}`,
                    `VTA_VERANO${anoInicialStr}`
                ];
                
                for (const columna of posiblesColumnas) {
                    if (registro[columna] && !isNaN(registro[columna]) && registro[columna] > 0) {
                        console.log(`Encontrada columna VERANO: ${columna} = ${registro[columna]}`);
                        return parseFloat(registro[columna]);
                    }
                }
            }
        } else {
            // Para INVIERNO, mantener búsqueda simple por año
            for (let i = 1; i <= 3; i++) {
                let anoObjetivo = anoActual - i;
                if (anoObjetivo < 0) anoObjetivo += 100;
                
                const anoStr = anoObjetivo.toString().padStart(2, '0');
                
                const posiblesColumnas = [
                    `INVIERNO ${anoStr}`,
                    `INVIERNO_${anoStr}`,
                    `INVIERNO${anoStr}`,
                    `VTA_INVIERNO_${anoStr}`,
                    `VTA_INVIERNO${anoStr}`
                ];
                
                for (const columna of posiblesColumnas) {
                    if (registro[columna] && !isNaN(registro[columna]) && registro[columna] > 0) {
                        console.log(`Encontrada columna INVIERNO: ${columna} = ${registro[columna]}`);
                        return parseFloat(registro[columna]);
                    }
                }
            }
        }
        
        // Búsqueda genérica como fallback
        for (const [columna, valor] of Object.entries(registro)) {
            if (columna.includes(temporada) && !isNaN(valor) && valor > 0) {
                console.log(`Columna genérica encontrada: ${columna} = ${valor}`);
                return parseFloat(valor);
            }
        }
        
        console.log(`No se encontró venta anterior para ${temporada}`);
        return 0;
    }

    // NUEVO: Función de diagnóstico mejorada
    static diagnosticarTemporadas() {
        const fechaActual = new Date();
        const temporada = IndiceEditor.determinarTemporadaActual(fechaActual);
        
        const info = {
            fecha_actual: fechaActual.toLocaleDateString('es-AR'),
            mes: fechaActual.getMonth() + 1,
            dia: fechaActual.getDate(),
            temporada_detectada: temporada.tipo,
            temporada_en_curso: temporada.enCurso,
            descripcion: temporada.descripcion,
            calculo_verano: null,
            calculo_invierno: null,
            dias_restantes_verano: null,
            dias_restantes_invierno: null
        };
        
        if (temporada.tipo === 'VERANO' && temporada.enCurso) {
            info.calculo_verano = 'PROPORCIONAL';
            info.calculo_invierno = 'NORMAL';
            info.dias_restantes_verano = IndiceEditor.calcularDiasRestantesVerano(fechaActual);
        } else if (temporada.tipo === 'INVIERNO' && temporada.enCurso) {
            info.calculo_verano = 'NORMAL';
            info.calculo_invierno = 'PROPORCIONAL';
            info.dias_restantes_invierno = IndiceEditor.calcularDiasRestantesInvierno(fechaActual);
        } else {
            info.calculo_verano = 'NORMAL';
            info.calculo_invierno = 'NORMAL';
        }
        
        console.group('🌡️ DIAGNÓSTICO TEMPORADAS CORREGIDO');
        console.table(info);
        console.groupEnd();
        
        return info;
    }

    // NUEVO: Función para simular cambio de fecha (testing)
    static simularFecha(mes, dia = 15) {
        const fechaSimulada = new Date(2025, mes - 1, dia);
        const temporada = IndiceEditor.determinarTemporadaActual(fechaSimulada);
        
        console.log(`🎯 SIMULACIÓN FECHA: ${fechaSimulada.toLocaleDateString('es-AR')}`);
        console.log(`Temporada: ${temporada.tipo} (${temporada.descripcion})`);
        console.log(`En curso: ${temporada.enCurso}`);
        
        if (temporada.tipo === 'VERANO') {
            const dias = IndiceEditor.calcularDiasRestantesVerano(fechaSimulada);
            console.log(`Días restantes verano: ${dias}`);
        } else if (temporada.tipo === 'INVIERNO') {
            const dias = IndiceEditor.calcularDiasRestantesInvierno(fechaSimulada);
            console.log(`Días restantes invierno: ${dias}`);
        }
        
        return { fecha: fechaSimulada, temporada };
    }

    // Resto de métodos mantener como estaban...
    static init() {
        IndiceEditor.configurarModal();
        IndiceEditor.configurarEventListeners();
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
            temporada: temporada // NUEVO: identificar qué índice se está editando
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
            
            IndiceEditor.actualizarDatosMemoriaFrontendCorregido(nuevoIndice);
            IndiceEditor.actualizarFilaTablaSoloFrontendCorregido();
            IndiceEditor.registrarCambio(nuevoIndice);
            
            // AGREGAR ESTAS LÍNEAS - Notificar cambio a totales
            if (['verano', 'invierno'].includes(IndiceEditor.indiceEditando.solapa)) {
                TotalesCompra.onIndiceActualizado(
                    IndiceEditor.indiceEditando.solapa,
                    IndiceEditor.indiceEditando.rubro,
                    IndiceEditor.indiceEditando.categoria,
                    nuevoIndice
                );
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

    static actualizarFilaTablaSoloFrontendCorregido() {
        const tbody = document.getElementById(`tbody-${IndiceEditor.indiceEditando.solapa}`);
        const fila = tbody.children[IndiceEditor.indiceEditando.index];
        
        if (!fila) return;
        
        const app = window.presupuestoApp;
        const datos = app.getDatos(IndiceEditor.indiceEditando.solapa);
        const registroActualizado = datos[IndiceEditor.indiceEditando.index];
        
        if (!registroActualizado) return;
        
        const celdas = fila.children;
        const temporadaEditada = IndiceEditor.indiceEditando.temporada || 'verano';
        
        console.log(`🔄 Actualizando fila - temporada editada: ${temporadaEditada}`);
        
        // CORRECCIÓN: Solo actualizar el índice que se editó
        if (temporadaEditada === 'verano') {
            // Solo actualizar índice de verano (celda 3)
            if (celdas[3]) {
                const input = celdas[3].querySelector('.indice-input');
                if (input) {
                    input.value = (registroActualizado.INDICE_VARIACION || 1.0).toFixed(2);
                    console.log(`✅ Índice VERANO actualizado: ${input.value}`);
                }
            }
            
            // NO tocar el índice de invierno (celda 6) - mantener su valor actual
            console.log(`⚠️ Índice INVIERNO mantenido sin cambios`);
            
        } else if (temporadaEditada === 'invierno') {
            // Solo actualizar índice de invierno (celda 6)
            if (celdas[6]) {
                const input = celdas[6].querySelector('.indice-input');
                if (input) {
                    const indiceInvierno = registroActualizado.INDICE_VARIACION_INVIERNO || 1.0;
                    input.value = indiceInvierno.toFixed(2);
                    console.log(`✅ Índice INVIERNO actualizado: ${input.value}`);
                }
            }
            
            // NO tocar el índice de verano (celda 3) - mantener su valor actual
            console.log(`⚠️ Índice VERANO mantenido sin cambios`);
        }
        
        // Actualizar venta proyectada verano (celda 5)
        if (celdas[5]) {
            celdas[5].textContent = FormatoUtils.formatearNumero(registroActualizado.VENTA_PROY_VERANO || 0);
        }
        
        // Actualizar venta proyectada invierno (celda 8)
        if (celdas[8]) {
            celdas[8].textContent = FormatoUtils.formatearNumero(registroActualizado.VENTA_PROY_INVIERNO || 0);
        }
        
        // Actualizar compra proyectada (celda 9)
        if (celdas[9]) {
            const compraProyectada = registroActualizado.COMPRA_PROYECTADA || 0;
            celdas[9].textContent = FormatoUtils.formatearNumero(compraProyectada);
            celdas[9].className = `text-end bg-success-subtle text-success-emphasis fw-bold ${FormatoUtils.obtenerClaseValor(compraProyectada)}`;
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
            indiceOriginal: IndiceEditor.indiceEditando.indiceActual,
            indiceNuevo: nuevoIndice,
            timestamp: Date.now(),
            fecha: new Date().toLocaleString('es-AR'),
            usuario: 'current_user'
        };
        
        // Buscar si ya existe un cambio para este item
        const indiceExistente = IndiceEditor.historialCambios.findIndex(c => 
            c.rubro === cambio.rubro && c.categoria === cambio.categoria
        );
        
        if (indiceExistente >= 0) {
            // Actualizar el cambio existente manteniendo el índice original
            const cambioExistente = IndiceEditor.historialCambios[indiceExistente];
            cambio.indiceOriginal = cambioExistente.indiceOriginal; // Mantener el original
            IndiceEditor.historialCambios[indiceExistente] = cambio;
        } else {
            // Nuevo cambio
            IndiceEditor.historialCambios.unshift(cambio);
        }
        
        // Mantener máximo 100 cambios únicos
        if (IndiceEditor.historialCambios.length > 100) {
            IndiceEditor.historialCambios = IndiceEditor.historialCambios.slice(0, 100);
        }
        
        StorageUtils.guardar('historial_indices', IndiceEditor.historialCambios, 30 * 24 * 60 * 60 * 1000);
        
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
        const historial = StorageUtils.obtener('historial_indices');
        if (historial && Array.isArray(historial)) {
            IndiceEditor.historialCambios = historial;
        }
    }

    static mostrarHistorialCambios() {
        const cambios = IndiceEditor.historialCambios.slice(0, 20); // Últimos 20
        
        if (cambios.length === 0) {
            UIUtils.mostrarAlerta('No hay cambios de índices registrados', 'info');
            return;
        }
        
        const modalHTML = `
            <div class="modal fade" id="modal-historial-indices" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-history me-2"></i>
                                Historial de Cambios de Índices (${cambios.length} cambios)
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Rubro</th>
                                            <th>Categoría</th>
                                            <th>Índice Original</th>
                                            <th>Índice Actual</th>
                                            <th>Cambio</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${cambios.map(cambio => {
                                            const diferencia = cambio.indiceNuevo - cambio.indiceOriginal;
                                            const claseCambio = diferencia > 0 ? 'text-success' : diferencia < 0 ? 'text-danger' : 'text-muted';
                                            return `
                                                <tr>
                                                    <td>${cambio.fecha}</td>
                                                    <td><strong>${cambio.rubro}</strong></td>
                                                    <td>${cambio.categoria}</td>
                                                    <td>${cambio.indiceOriginal.toFixed(2)}</td>
                                                    <td>${cambio.indiceNuevo.toFixed(2)}</td>
                                                    <td class="${claseCambio}">
                                                        ${diferencia > 0 ? '+' : ''}${diferencia.toFixed(2)}
                                                    </td>
                                                </tr>
                                            `;
                                        }).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" onclick="IndiceEditor.limpiarHistorial()">
                                <i class="fas fa-trash"></i> Limpiar Historial
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        const modal = new bootstrap.Modal(document.getElementById('modal-historial-indices'));
        modal.show();
        
        document.getElementById('modal-historial-indices').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    static limpiarHistorial() {
        if (confirm('¿Está seguro que desea limpiar todo el historial de cambios?')) {
            IndiceEditor.historialCambios = [];
            StorageUtils.eliminar('historial_indices');
            UIUtils.mostrarAlerta('Historial limpiado', 'success');
            
            // Cerrar modal si está abierto
            const modal = document.getElementById('modal-historial-indices');
            if (modal) {
                bootstrap.Modal.getInstance(modal).hide();
            }
        }
    }

    /**
     * NUEVO: Función temporal para debug - agregar al final de la clase IndiceEditor
     */
    static debugEstructuraTabla(solapa) {
        const tabla = document.getElementById(`tabla-${solapa}`);
        const thead = tabla.querySelector('thead tr');
        const tbody = tabla.querySelector('tbody');
        const primeraFila = tbody.children[0];
        
        console.group(`🔍 DEBUG ESTRUCTURA TABLA ${solapa.toUpperCase()}`);
        
        // Mostrar headers
        console.log('HEADERS:');
        Array.from(thead.children).forEach((th, i) => {
            console.log(`${i}: "${th.textContent.trim()}" (clases: ${th.className})`);
        });
        
        // Mostrar primera fila de datos
        if (primeraFila) {
            console.log('\nPRIMERA FILA:');
            Array.from(primeraFila.children).forEach((td, i) => {
                console.log(`${i}: "${td.textContent.trim()}" (clases: ${td.className})`);
            });
        }
        
        console.groupEnd();
    }

    /**
     * NUEVO: Calcular días totales reales de una temporada
     */
    static calcularDiasTotalesTemporada(temporada, ano = null) {
        if (!ano) ano = new Date().getFullYear();
        
        if (temporada === 'VERANO') {
            // VERANO: 1 agosto año anterior al 31 enero año actual
            const inicioVerano = new Date(ano - 1, 7, 1); // 1 agosto año anterior
            const finVerano = new Date(ano, 0, 31); // 31 enero año actual
            const diferencia = finVerano - inicioVerano;
            return Math.ceil(diferencia / (1000 * 60 * 60 * 24)) + 1;
        } else {
            // INVIERNO: 1 febrero al 31 julio del mismo año
            const inicioInvierno = new Date(ano, 1, 1); // 1 febrero
            const finInvierno = new Date(ano, 6, 31); // 31 julio
            const diferencia = finInvierno - inicioInvierno;
            return Math.ceil(diferencia / (1000 * 60 * 60 * 24)) + 1;
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    IndiceEditor.init();
    IndiceEditor.cargarHistorial();
    
    // Funciones globales para diagnóstico
    window.diagnosticarTemporadas = () => IndiceEditor.diagnosticarTemporadas();
    window.simularFecha = (mes, dia) => IndiceEditor.simularFecha(mes, dia);
    
    console.log('✅ IndiceEditor CORREGIDO - Cálculo por temporada implementado');
    console.log('💡 Funciones disponibles:');
    console.log('  📊 diagnosticarTemporadas() - Ver lógica de temporadas');
    console.log('  🎯 simularFecha(mes, dia) - Simular fecha específica');
    console.log('Ejemplos:');
    console.log('  simularFecha(8, 15) // Agosto (verano)');
    console.log('  simularFecha(3, 15) // Marzo (invierno)');
});

// Función global para debug de estructura de tabla
window.debugEstructuraTabla = (solapa) => IndiceEditor.debugEstructuraTabla(solapa);


