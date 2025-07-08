
// Editor de índices de variación - CORREGIDO Y COMPLETO
// Archivo: presupuestos/js/indice-editor.js

class IndiceEditor {
    static indiceEditando = null;
    static historialCambios = [];
    static modalInstancia = null;

    /**
     * Inicializar editor de índices
     */
    static init() {
        IndiceEditor.configurarModal();
        IndiceEditor.configurarEventListeners();
    }

    /**
     * Configurar modal de edición
     */
    static configurarModal() {
        const modal = document.getElementById('modalEditarIndice');
        if (modal) {
            IndiceEditor.modalInstancia = new bootstrap.Modal(modal);
            
            // Event listener para cuando se cierra el modal
            modal.addEventListener('hidden.bs.modal', () => {
                IndiceEditor.limpiarFormulario();
                IndiceEditor.indiceEditando = null;
            });
        }
    }

    /**
     * Configurar event listeners
     */
    static configurarEventListeners() {
        // Event listener para teclas rápidas
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && IndiceEditor.modalInstancia) {
                IndiceEditor.modalInstancia.hide();
            }
            
            if (e.key === 'Enter' && e.ctrlKey && IndiceEditor.indiceEditando) {
                IndiceEditor.guardarNuevoIndice();
            }
        });

        // Event listener para input de índice
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

    /**
     * Editar índice (función principal)
     */
    static editarIndice(rubro, categoria, indiceActual, solapa, index) {
        IndiceEditor.indiceEditando = {
            rubro: rubro,
            categoria: categoria,
            indiceActual: indiceActual,
            solapa: solapa,
            index: index
        };
        
        // Poblar modal con datos
        document.getElementById('modal-rubro').textContent = rubro;
        document.getElementById('modal-categoria').textContent = categoria;
        document.getElementById('modal-nuevo-indice').value = FormatoUtils.formatearDecimal(indiceActual, 2);
        
        // Resaltar fila en la tabla
        TablaRenderer.resaltarFila(solapa, index, true);
        
        // Mostrar modal
        if (IndiceEditor.modalInstancia) {
            IndiceEditor.modalInstancia.show();
        }
        
        // Focus en el input
        setTimeout(() => {
            const input = document.getElementById('modal-nuevo-indice');
            if (input) {
                input.focus();
                input.select();
            }
        }, 500);
    }

    /**
     * Guardar nuevo índice - CORREGIDO
     */
    static async guardarNuevoIndice() {
        if (!IndiceEditor.indiceEditando) return;
        
        const nuevoIndice = parseFloat(document.getElementById('modal-nuevo-indice').value);
        
        // Validar entrada
        if (!IndiceEditor.validarIndice(nuevoIndice)) {
            return;
        }
        
        // Mostrar loading en el botón
        const btnGuardar = document.querySelector('#modalEditarIndice .btn-primary');
        const textoOriginal = btnGuardar.innerHTML;
        IndiceEditor.mostrarLoadingBoton(btnGuardar, true);
        
        try {
            // SIMULACIÓN: En lugar de llamar API, actualizar solo frontend
            await new Promise(resolve => setTimeout(resolve, 500)); // Simular delay de red
            
            // Actualizar datos en memoria
            IndiceEditor.actualizarDatosMemoriaFrontendCorregido(nuevoIndice);
            
            // Actualizar tabla
            IndiceEditor.actualizarFilaTablaSoloFrontendCorregido();
            
            // Registrar cambio en historial
            IndiceEditor.registrarCambio(nuevoIndice);
            
            UIUtils.mostrarAlerta('Índice actualizado correctamente (solo frontend)', 'success');
            
            // Cerrar modal
            IndiceEditor.modalInstancia.hide();
            
        } catch (error) {
            console.error('Error actualizando índice:', error);
            UIUtils.mostrarAlerta('Error al actualizar índice', 'error');
        } finally {
            IndiceEditor.mostrarLoadingBoton(btnGuardar, false, textoOriginal);
            TablaRenderer.resaltarFila(IndiceEditor.indiceEditando.solapa, IndiceEditor.indiceEditando.index, false);
        }
    }

    /**
     * CORREGIDO: Actualizar datos en memoria solo frontend con cálculos corregidos
     */
    static actualizarDatosMemoriaFrontendCorregido(nuevoIndice) {
        const app = window.presupuestoApp;
        const datos = app.getDatos(IndiceEditor.indiceEditando.solapa);
        
        if (datos[IndiceEditor.indiceEditando.index]) {
            const registro = datos[IndiceEditor.indiceEditando.index];
            
            // DEBUG: Información del registro
            console.log('=== DEBUG CÁLCULO VENTA PROYECTADA CORREGIDO ===');
            console.log(`Rubro: ${registro.RUBRO}`);
            console.log(`Categoría: ${registro.CATEGORIA_PADRE}`);
            console.log(`Índice anterior: ${registro.INDICE_VARIACION}`);
            console.log(`Índice nuevo: ${nuevoIndice.toFixed(2)}`);
            
            // Actualizar índice con 2 decimales
            registro.INDICE_VARIACION = parseFloat(nuevoIndice.toFixed(2));
            
            // CORREGIDO: Extraer ventas históricas usando la función corregida
            const ventaVeranoAnterior = IndiceEditor.extraerVentaAnteriorCorregida(registro, 'VERANO');
            const ventaInviernoAnterior = IndiceEditor.extraerVentaAnteriorCorregida(registro, 'INVIERNO');
            
            console.log(`Venta Verano Anterior: ${ventaVeranoAnterior}`);
            console.log(`Venta Invierno Anterior: ${ventaInviernoAnterior}`);
            
            // CORREGIDO: Calcular nuevas ventas proyectadas con lógica proporcional
            const stockProyectado = parseFloat(registro.STOCK_PROYECTADO || 0);
            
            // Verificar si estamos en temporada actual para aplicar proporción
            const fechaActual = new Date();
            const mes = fechaActual.getMonth() + 1; // JavaScript months are 0-indexed
            
            let nuevaVentaVerano, nuevaVentaInvierno;
            
            // Si estamos en temporada de INVIERNO (febrero-julio) y estamos calculando INVIERNO
            if (mes >= 2 && mes <= 7 && IndiceEditor.indiceEditando.solapa === 'invierno') {
                // Aplicar cálculo proporcional para invierno
                const diasRestantes = IndiceEditor.calcularDiasRestantesInvierno(fechaActual);
                const diasTotalInvierno = 180; // 6 meses
                
                nuevaVentaInvierno = Math.round((ventaInviernoAnterior / diasTotalInvierno) * diasRestantes * registro.INDICE_VARIACION);
                nuevaVentaVerano = Math.round(ventaVeranoAnterior * registro.INDICE_VARIACION);
                
                console.log(`CÁLCULO PROPORCIONAL INVIERNO:`);
                console.log(`Días restantes invierno: ${diasRestantes}`);
                console.log(`Fórmula: (${ventaInviernoAnterior} / ${diasTotalInvierno}) * ${diasRestantes} * ${registro.INDICE_VARIACION} = ${nuevaVentaInvierno}`);
            }
            // Si estamos en temporada de VERANO (agosto-enero) y estamos calculando VERANO
            else if ((mes >= 8 || mes <= 1) && IndiceEditor.indiceEditando.solapa === 'verano') {
                // Aplicar cálculo proporcional para verano
                const diasRestantes = IndiceEditor.calcularDiasRestantesVerano(fechaActual);
                const diasTotalVerano = 180; // 6 meses
                
                nuevaVentaVerano = Math.round((ventaVeranoAnterior / diasTotalVerano) * diasRestantes * registro.INDICE_VARIACION);
                nuevaVentaInvierno = Math.round(ventaInviernoAnterior * registro.INDICE_VARIACION);
                
                console.log(`CÁLCULO PROPORCIONAL VERANO:`);
                console.log(`Días restantes verano: ${diasRestantes}`);
                console.log(`Fórmula: (${ventaVeranoAnterior} / ${diasTotalVerano}) * ${diasRestantes} * ${registro.INDICE_VARIACION} = ${nuevaVentaVerano}`);
            }
            // Fuera de temporada: aplicar índice normal
            else {
                nuevaVentaVerano = Math.round(ventaVeranoAnterior * registro.INDICE_VARIACION);
                nuevaVentaInvierno = Math.round(ventaInviernoAnterior * registro.INDICE_VARIACION);
                
                console.log(`CÁLCULO NORMAL (fuera de temporada):`);
            }
            
            const nuevaCompraProyectada = Math.round(stockProyectado - nuevaVentaVerano - nuevaVentaInvierno);
            
            console.log(`Cálculo Venta Verano: ${nuevaVentaVerano}`);
            console.log(`Cálculo Venta Invierno: ${nuevaVentaInvierno}`);
            console.log(`Stock Proyectado: ${stockProyectado}`);
            console.log(`Cálculo Compra Proyectada: ${stockProyectado} - ${nuevaVentaVerano} - ${nuevaVentaInvierno} = ${nuevaCompraProyectada}`);
            console.log('=== FIN DEBUG ===');
            
            // Actualizar valores calculados
            registro.VENTA_PROY_VERANO = nuevaVentaVerano;
            registro.VENTA_PROY_INVIERNO = nuevaVentaInvierno;
            registro.COMPRA_PROYECTADA = nuevaCompraProyectada;
        }
    }

    /**
     * CORREGIDO: Extraer venta anterior con búsqueda mejorada para VERANO XX-XX
     */
    static extraerVentaAnteriorCorregida(registro, temporada) {
        const anoActual = new Date().getFullYear() % 100;
        
        if (temporada === 'VERANO') {
            // Para VERANO, buscar formato XX-XX del año anterior
            // Si estamos en 2025, buscar VERANO 24-25, VERANO 23-24, etc.
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
        
        // Si no encontramos nada específico, buscar cualquier columna que contenga la temporada
        for (const [columna, valor] of Object.entries(registro)) {
            if (columna.includes(temporada) && !isNaN(valor) && valor > 0) {
                console.log(`Columna genérica encontrada: ${columna} = ${valor}`);
                return parseFloat(valor);
            }
        }
        
        console.log(`No se encontró venta anterior para ${temporada}`);
        return 0;
    }

    /**
     * NUEVO: Calcular días restantes de temporada de invierno
     */
    static calcularDiasRestantesInvierno(fecha) {
        const ano = fecha.getFullYear();
        const finInvierno = new Date(ano, 6, 31, 23, 59, 59); // 31 de julio
        
        if (fecha > finInvierno) {
            return 0; // Ya pasó la temporada
        }
        
        const diferencia = finInvierno - fecha;
        return Math.ceil(diferencia / (1000 * 60 * 60 * 24));
    }

    /**
     * NUEVO: Calcular días restantes de temporada de verano
     */
    static calcularDiasRestantesVerano(fecha) {
        const mes = fecha.getMonth() + 1;
        const ano = fecha.getFullYear();
        
        let finVerano;
        if (mes >= 8) {
            // Estamos en la parte agosto-diciembre, el verano termina el 31 de enero del año siguiente
            finVerano = new Date(ano + 1, 0, 31, 23, 59, 59);
        } else {
            // Estamos en enero, el verano termina el 31 de enero del mismo año
            finVerano = new Date(ano, 0, 31, 23, 59, 59);
        }
        
        if (fecha > finVerano) {
            return 0; // Ya pasó la temporada
        }
        
        const diferencia = finVerano - fecha;
        return Math.ceil(diferencia / (1000 * 60 * 60 * 24));
    }

    /**
     * CORREGIDO: Actualizar fila en la tabla solo frontend
     */
    static actualizarFilaTablaSoloFrontendCorregido() {
        const tbody = document.getElementById(`tbody-${IndiceEditor.indiceEditando.solapa}`);
        const fila = tbody.children[IndiceEditor.indiceEditando.index];
        
        if (!fila) return;
        
        const app = window.presupuestoApp;
        const datos = app.getDatos(IndiceEditor.indiceEditando.solapa);
        const registroActualizado = datos[IndiceEditor.indiceEditando.index];
        
        if (!registroActualizado) return;
        
        const celdas = fila.children;
        
        // Actualizar celda del índice (columna 3) - FORMATO CORRECTO
        if (celdas[3]) {
            const input = celdas[3].querySelector('.indice-input');
            if (input) {
                // Usar punto como separador decimal para el input
                input.value = registroActualizado.INDICE_VARIACION.toFixed(2);
            }
        }
        
        // Actualizar Venta Proyectada Verano (columna 5)
        if (celdas[5]) {
            celdas[5].textContent = FormatoUtils.formatearNumero(registroActualizado.VENTA_PROY_VERANO || 0);
        }
        
        // Actualizar Venta Proyectada Invierno (columna 7)
        if (celdas[7]) {
            celdas[7].textContent = FormatoUtils.formatearNumero(registroActualizado.VENTA_PROY_INVIERNO || 0);
        }
        
        // Actualizar Compra Proyectada (columna 8)
        if (celdas[8]) {
            const compraProyectada = registroActualizado.COMPRA_PROYECTADA || 0;
            celdas[8].textContent = FormatoUtils.formatearNumero(compraProyectada);
            
            // Actualizar clase de color según el valor
            celdas[8].className = `text-end bg-success-subtle text-success-emphasis fw-bold ${FormatoUtils.obtenerClaseValor(compraProyectada)}`;
        }
    }

    /**
     * Validar índice ingresado
     */
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

    /**
     * Validar input en tiempo real
     */
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
        
        // Mostrar preview del cálculo
        IndiceEditor.mostrarPreviewCalculo(valor);
    }

    /**
     * CORREGIDO: Mostrar preview del cálculo con el nuevo índice
     */
    static mostrarPreviewCalculo(nuevoIndice) {
        if (!IndiceEditor.indiceEditando || isNaN(nuevoIndice)) return;
        
        // Usar 2 decimales para el cálculo
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
        
        // NUEVO: Mostrar información sobre si se aplicará cálculo proporcional
        const fechaActual = new Date();
        const mes = fechaActual.getMonth() + 1;
        let infoTemporal = '';
        
        if ((mes >= 2 && mes <= 7) && IndiceEditor.indiceEditando.solapa === 'invierno') {
            const diasRestantes = IndiceEditor.calcularDiasRestantesInvierno(fechaActual);
            infoTemporal = `<br><span class="text-info">⚠️ Se aplicará cálculo proporcional para INVIERNO (${diasRestantes} días restantes)</span>`;
        } else if ((mes >= 8 || mes <= 1) && IndiceEditor.indiceEditando.solapa === 'verano') {
            const diasRestantes = IndiceEditor.calcularDiasRestantesVerano(fechaActual);
            infoTemporal = `<br><span class="text-info">⚠️ Se aplicará cálculo proporcional para VERANO (${diasRestantes} días restantes)</span>`;
        } else {
            infoTemporal = '<br><span class="text-muted">📊 Se aplicará cálculo normal (fuera de temporada)</span>';
        }
        
        let mensaje = `Cambio: ${diferencia.toFixed(2)} `;
        mensaje += `(${porcentajeCambio > 0 ? '+' : ''}${porcentajeCambio.toFixed(1)}%)`;
        
        previewElement.innerHTML = `
            <small class="text-muted">
                <strong>Preview:</strong><br>
                Índice anterior: ${IndiceEditor.indiceEditando.indiceActual.toFixed(2)}<br>
                Índice nuevo: ${indiceFormateado.toFixed(2)}<br>
                ${mensaje}
                ${infoTemporal}
            </small>
        `;
    }

    /**
     * Marcar input con error
     */
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

    /**
     * Mostrar loading en botón
     */
    static mostrarLoadingBoton(boton, mostrar, textoOriginal = 'Guardar') {
        if (mostrar) {
            boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
            boton.disabled = true;
        } else {
            boton.innerHTML = textoOriginal;
            boton.disabled = false;
        }
    }

    /**
     * Registrar cambio en historial
     */
    static registrarCambio(nuevoIndice) {
        const cambio = {
            rubro: IndiceEditor.indiceEditando.rubro,
            categoria: IndiceEditor.indiceEditando.categoria,
            solapa: IndiceEditor.indiceEditando.solapa,
            indiceAnterior: IndiceEditor.indiceEditando.indiceActual,
            indiceNuevo: nuevoIndice,
            timestamp: Date.now(),
            usuario: 'current_user'
        };
        
        IndiceEditor.historialCambios.unshift(cambio);
        
        // Mantener máximo 100 cambios en historial
        if (IndiceEditor.historialCambios.length > 100) {
            IndiceEditor.historialCambios = IndiceEditor.historialCambios.slice(0, 100);
        }
        
        // Guardar en localStorage
        StorageUtils.guardar('historial_indices', IndiceEditor.historialCambios, 30 * 24 * 60 * 60 * 1000); // 30 días
    }

    /**
     * Limpiar formulario del modal
     */
    static limpiarFormulario() {
        document.getElementById('modal-rubro').textContent = '';
        document.getElementById('modal-categoria').textContent = '';
        document.getElementById('modal-nuevo-indice').value = '';
        
        const input = document.getElementById('modal-nuevo-indice');
        input.classList.remove('is-valid', 'is-invalid');
        
        const preview = document.getElementById('preview-calculo');
        if (preview) preview.remove();
    }

    /**
     * Edición rápida inline (alternativa al modal)
     */
    static editarInline(celda, rubro, categoria, indiceActual, solapa, index) {
        // Convertir celda a input editable
        const input = document.createElement('input');
        input.type = 'number';
        input.step = '0.01';
        input.min = '0';
        input.max = '10';
        input.value = FormatoUtils.formatearDecimal(indiceActual, 2);
        input.className = 'form-control form-control-sm';
        input.style.width = '100%';
        
        // Guardar contenido original
        const contenidoOriginal = celda.innerHTML;
        
        // Reemplazar contenido con input
        celda.innerHTML = '';
        celda.appendChild(input);
        
        // Focus y seleccionar
        input.focus();
        input.select();
        
        // Manejar guardado
        const guardar = async () => {
            const nuevoIndice = parseFloat(input.value);
            
            if (IndiceEditor.validarIndice(nuevoIndice)) {
                try {
                    // Simular actualización (en lugar de API real)
                    await new Promise(resolve => setTimeout(resolve, 200));
                    
                    // Actualizar datos en memoria
                    IndiceEditor.actualizarDatosMemoriaFrontendCorregido(nuevoIndice);
                    
                    // Restaurar celda con nuevo valor
                    celda.innerHTML = contenidoOriginal.replace(
                        /value="[^"]*"/,
                        `value="${FormatoUtils.formatearDecimal(nuevoIndice, 2)}"`
                    );
                    
                    UIUtils.mostrarAlerta('Índice actualizado', 'success', 2000);
                    
                    // Registrar cambio
                    IndiceEditor.registrarCambio(nuevoIndice);
                    
                } catch (error) {
                    UIUtils.mostrarAlerta('Error: ' + error.message, 'error');
                    celda.innerHTML = contenidoOriginal; // Restaurar original
                }
            } else {
                celda.innerHTML = contenidoOriginal; // Restaurar original
            }
        };
        
        // Manejar cancelación
        const cancelar = () => {
            celda.innerHTML = contenidoOriginal;
        };
        
        // Event listeners para input
        input.addEventListener('blur', guardar);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                guardar();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                cancelar();
            }
        });
    }

    /**
     * Actualización masiva de índices
     */
    static async actualizarMasivo(filtros, nuevoIndice) {
        const confirmacion = await UIUtils.confirmarAccion(
            'Actualización Masiva',
            `¿Está seguro de actualizar múltiples índices a ${nuevoIndice}?`,
            'warning'
        );
        
        if (!confirmacion) return;
        
        const progressModal = UIUtils.mostrarProgreso('Actualizando índices...', 0);
        
        try {
            const app = window.presupuestoApp;
            const datos = app.getDatos();
            const actualizaciones = [];
            
            // Preparar actualizaciones según filtros
            ['verano', 'invierno'].forEach(solapa => {
                datos[solapa].forEach(item => {
                    if (IndiceEditor.aplicarFiltro(item, filtros)) {
                        actualizaciones.push({
                            rubro: item.RUBRO,
                            categoria: item.CATEGORIA_PADRE,
                            indice: nuevoIndice
                        });
                    }
                });
            });
            
            if (actualizaciones.length === 0) {
                UIUtils.mostrarAlerta('No se encontraron registros que coincidan con los filtros', 'warning');
                UIUtils.cerrarProgreso();
                return;
            }
            
            // Simular actualización en lotes
            const LOTE_SIZE = 10;
            let procesados = 0;
            
            for (let i = 0; i < actualizaciones.length; i += LOTE_SIZE) {
                const lote = actualizaciones.slice(i, i + LOTE_SIZE);
                
                try {
                    // Simular procesamiento
                    await new Promise(resolve => setTimeout(resolve, 500));
                    procesados += lote.length;
                    
                    const progreso = (procesados / actualizaciones.length) * 100;
                    UIUtils.actualizarProgreso(progreso);
                    
                } catch (error) {
                    console.error('Error en lote:', error);
                }
            }
            
            UIUtils.cerrarProgreso();
            UIUtils.mostrarAlerta(`Actualizados ${procesados} índices correctamente`, 'success');
            
            // Recargar datos
            await window.presupuestoApp.cargarDatosSolapas();
            
        } catch (error) {
            UIUtils.cerrarProgreso();
            UIUtils.mostrarAlerta('Error en actualización masiva: ' + error.message, 'error');
        }
    }

    /**
     * Aplicar filtro para actualización masiva
     */
    static aplicarFiltro(item, filtros) {
        // Filtro por rubro
        if (filtros.rubros && filtros.rubros.length > 0) {
            if (!filtros.rubros.includes(item.RUBRO)) return false;
        }
        
        // Filtro por categoría
        if (filtros.categorias && filtros.categorias.length > 0) {
            if (!filtros.categorias.includes(item.CATEGORIA_PADRE)) return false;
        }
        
        // Filtro por rango de índice actual
        if (filtros.rangoIndice) {
            const indice = parseFloat(item.INDICE_VARIACION || 0);
            if (indice < filtros.rangoIndice.min || indice > filtros.rangoIndice.max) return false;
        }
        
        return true;
    }

    /**
     * Deshacer último cambio
     */
    static async deshacerUltimoCambio() {
        if (IndiceEditor.historialCambios.length === 0) {
            UIUtils.mostrarAlerta('No hay cambios para deshacer', 'info');
            return;
        }
        
        const ultimoCambio = IndiceEditor.historialCambios[0];
        
        const confirmacion = await UIUtils.confirmarAccion(
            'Deshacer Cambio',
            `¿Deshacer cambio en ${ultimoCambio.rubro} - ${ultimoCambio.categoria}?`
        );
        
        if (!confirmacion) return;
        
        try {
            // Simular deshacer
            await new Promise(resolve => setTimeout(resolve, 300));
            
            // Remover del historial
            IndiceEditor.historialCambios.shift();
            StorageUtils.guardar('historial_indices', IndiceEditor.historialCambios);
            
            // Actualizar datos
            await window.presupuestoApp.cargarDatosSolapas();
            
            UIUtils.mostrarAlerta('Cambio deshecho correctamente', 'success');
        } catch (error) {
            UIUtils.mostrarAlerta('Error deshaciendo cambio: ' + error.message, 'error');
        }
    }

    /**
     * Obtener estadísticas de cambios
     */
    static obtenerEstadisticasCambios() {
        const total = IndiceEditor.historialCambios.length;
        const ultimaHora = IndiceEditor.historialCambios.filter(
            c => Date.now() - c.timestamp < 60 * 60 * 1000
        ).length;
        
        const cambiosPorSolapa = {};
        IndiceEditor.historialCambios.forEach(c => {
            cambiosPorSolapa[c.solapa] = (cambiosPorSolapa[c.solapa] || 0) + 1;
        });
        
        return {
            total_cambios: total,
            cambios_ultima_hora: ultimaHora,
            cambios_por_solapa: cambiosPorSolapa,
            ultimo_cambio: total > 0 ? new Date(IndiceEditor.historialCambios[0].timestamp) : null
        };
    }

    /**
     * Exportar historial de cambios
     */
    static exportarHistorial() {
        if (IndiceEditor.historialCambios.length === 0) {
            UIUtils.mostrarAlerta('No hay cambios en el historial', 'info');
            return;
        }
        
        const csv = IndiceEditor.convertirHistorialCSV();
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', `historial_indices_${new Date().toISOString().slice(0, 10)}.csv`);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        UIUtils.mostrarAlerta('Historial exportado', 'success');
    }

    /**
     * Convertir historial a CSV
     */
    static convertirHistorialCSV() {
        const headers = ['Fecha', 'Hora', 'Rubro', 'Categoría', 'Solapa', 'Índice Anterior', 'Índice Nuevo', 'Usuario'];
        const filas = IndiceEditor.historialCambios.map(cambio => {
            const fecha = new Date(cambio.timestamp);
            return [
                FechaUtils.formatearFecha(fecha),
                fecha.toLocaleTimeString('es-AR'),
                cambio.rubro,
                cambio.categoria,
                cambio.solapa,
                cambio.indiceAnterior,
                cambio.indiceNuevo,
                cambio.usuario
            ].map(valor => `"${valor}"`).join(',');
        });
        
        return [headers.join(','), ...filas].join('\n');
    }

    /**
     * Resetear índices a valor por defecto
     */
    static async resetearIndices(valorDefecto = 1.0, filtros = {}) {
        const confirmacion = await UIUtils.confirmarAccion(
            'Resetear Índices',
            `¿Está seguro de resetear índices a ${valorDefecto}?`,
            'warning'
        );
        
        if (!confirmacion) return;
        
        try {
            const app = window.presupuestoApp;
            const datos = app.getDatos();
            let contadorActualizados = 0;
            
            ['verano', 'invierno', 'stock'].forEach(solapa => {
                datos[solapa].forEach(registro => {
                    if (IndiceEditor.aplicarFiltros(registro, filtros)) {
                        registro.INDICE_VARIACION = valorDefecto;
                        contadorActualizados++;
                    }
                });
            });
            
            UIUtils.mostrarAlerta(`Se resetearon ${contadorActualizados} índices`, 'success');
            
            // Recargar tablas
            await window.presupuestoApp.cargarDatosSolapas();
            
        } catch (error) {
            UIUtils.mostrarAlerta('Error al resetear índices: ' + error.message, 'error');
        }
    }

    /**
     * Aplicar filtros a un registro
     */
    static aplicarFiltros(registro, filtros) {
        if (Object.keys(filtros).length === 0) {
            return true; // Sin filtros, aplicar a todos
        }
        
        for (const [campo, valor] of Object.entries(filtros)) {
            if (registro[campo] && registro[campo] !== valor) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Obtener estadísticas de índices
     */
    static obtenerEstadisticasIndices() {
        const app = window.presupuestoApp;
        const todosLosDatos = [];
        
        ['verano', 'invierno', 'stock'].forEach(solapa => {
            const datos = app.getDatos(solapa);
            todosLosDatos.push(...datos);
        });
        
        const indices = todosLosDatos
            .map(item => parseFloat(item.INDICE_VARIACION || 0))
            .filter(indice => !isNaN(indice));
        
        if (indices.length === 0) {
            return {
                total_registros: 0,
                promedio: 0,
                minimo: 0,
                maximo: 0,
                mediana: 0,
                registros_por_rango: {
                    muy_bajo: 0,
                    bajo: 0,
                    normal: 0,
                    alto: 0,
                    muy_alto: 0
                }
            };
        }
        
        const suma = indices.reduce((acc, val) => acc + val, 0);
        const promedio = suma / indices.length;
        const minimo = Math.min(...indices);
        const maximo = Math.max(...indices);
        const mediana = IndiceEditor.calcularMediana([...indices]);
        
        return {
            total_registros: indices.length,
            promedio: promedio.toFixed(4),
            minimo: minimo,
            maximo: maximo,
            mediana: mediana,
            registros_por_rango: IndiceEditor.contarPorRango(indices)
        };
    }

    /**
     * Calcular mediana de un array
     */
    static calcularMediana(array) {
        array.sort((a, b) => a - b);
        const count = array.length;
        const middle = Math.floor((count - 1) / 2);
        
        if (count % 2) {
            return array[middle];
        } else {
            return (array[middle] + array[middle + 1]) / 2;
        }
    }

    /**
     * Contar registros por rango de índices
     */
    static contarPorRango(indices) {
        const rangos = {
            muy_bajo: 0,    // 0 - 0.5
            bajo: 0,        // 0.5 - 0.8
            normal: 0,      // 0.8 - 1.2
            alto: 0,        // 1.2 - 2.0
            muy_alto: 0     // > 2.0
        };
        
        indices.forEach(indice => {
            if (indice < 0.5) {
                rangos.muy_bajo++;
            } else if (indice < 0.8) {
                rangos.bajo++;
            } else if (indice <= 1.2) {
                rangos.normal++;
            } else if (indice <= 2.0) {
                rangos.alto++;
            } else {
                rangos.muy_alto++;
            }
        });
        
        return rangos;
    }

    /**
     * Cargar historial desde localStorage
     */
    static cargarHistorial() {
        const historial = StorageUtils.obtener('historial_indices');
        if (historial && Array.isArray(historial)) {
            IndiceEditor.historialCambios = historial;
        }
    }

    /**
     * NUEVO: Función de diagnóstico para verificar temporadas
     */
    static diagnosticarTemporadas() {
        const fechaActual = new Date();
        const mes = fechaActual.getMonth() + 1;
        const dia = fechaActual.getDate();
        
        const info = {
            fecha_actual: fechaActual.toLocaleDateString('es-AR'),
            mes: mes,
            dia: dia,
            temporada_detectada: null,
            calculo_aplicable: null,
            dias_restantes: null
        };
        
        if (mes >= 2 && mes <= 7) {
            info.temporada_detectada = 'INVIERNO';
            info.calculo_aplicable = 'proporcional_invierno';
            info.dias_restantes = IndiceEditor.calcularDiasRestantesInvierno(fechaActual);
        } else if (mes >= 8 || mes <= 1) {
            info.temporada_detectada = 'VERANO';
            info.calculo_aplicable = 'proporcional_verano';
            info.dias_restantes = IndiceEditor.calcularDiasRestantesVerano(fechaActual);
        } else {
            info.temporada_detectada = 'TRANSICION';
            info.calculo_aplicable = 'normal';
            info.dias_restantes = 0;
        }
        
        console.table(info);
        return info;
    }

    /**
     * NUEVO: Función utilitaria para debug completo
     */
    static debugCompleto() {
        const info = {
            temporadas: IndiceEditor.diagnosticarTemporadas(),
            estadisticas_indices: IndiceEditor.obtenerEstadisticasIndices(),
            estadisticas_cambios: IndiceEditor.obtenerEstadisticasCambios(),
            historial_size: IndiceEditor.historialCambios.length
        };
        
        console.group('🔍 DEBUG COMPLETO - IndiceEditor');
        console.log('📊 Temporadas:', info.temporadas);
        console.log('📈 Estadísticas Índices:', info.estadisticas_indices);
        console.log('📝 Estadísticas Cambios:', info.estadisticas_cambios);
        console.log('💾 Tamaño Historial:', info.historial_size);
        console.groupEnd();
        
        return info;
    }

    /**
     * NUEVO: Simular escenarios de cálculo
     */
    static simularEscenarios() {
        const escenarios = [
            { temporada: 'VERANO', mes: 8, descripcion: 'Agosto - Inicio Verano' },
            { temporada: 'VERANO', mes: 12, descripcion: 'Diciembre - Medio Verano' },
            { temporada: 'VERANO', mes: 1, descripcion: 'Enero - Final Verano' },
            { temporada: 'INVIERNO', mes: 3, descripcion: 'Marzo - Inicio Invierno' },
            { temporada: 'INVIERNO', mes: 5, descripcion: 'Mayo - Medio Invierno' },
            { temporada: 'INVIERNO', mes: 7, descripcion: 'Julio - Final Invierno' }
        ];
        
        console.group('🎯 SIMULACIÓN DE ESCENARIOS');
        
        escenarios.forEach(escenario => {
            const fechaSimulada = new Date(2025, escenario.mes - 1, 15);
            const diasRestantes = escenario.temporada === 'VERANO' 
                ? IndiceEditor.calcularDiasRestantesVerano(fechaSimulada)
                : IndiceEditor.calcularDiasRestantesInvierno(fechaSimulada);
            
            console.log(`${escenario.descripcion}:`, {
                fecha: fechaSimulada.toLocaleDateString('es-AR'),
                dias_restantes: diasRestantes,
                calculo: diasRestantes > 0 ? 'PROPORCIONAL' : 'NORMAL'
            });
        });
        
        console.groupEnd();
    }

    /**
     * NUEVO: Función para probar cálculos manualmente
     */
    static probarCalculo(ventaAnterior, indice, diasRestantes = null) {
        const diasTotal = 180;
        
        let resultado = {
            venta_anterior: ventaAnterior,
            indice: indice,
            calculo_normal: Math.round(ventaAnterior * indice),
            calculo_proporcional: null
        };
        
        if (diasRestantes) {
            resultado.dias_restantes = diasRestantes;
            resultado.calculo_proporcional = Math.round((ventaAnterior / diasTotal) * diasRestantes * indice);
            resultado.formula = `(${ventaAnterior} / ${diasTotal}) * ${diasRestantes} * ${indice}`;
        }
        
        console.log('🧮 PRUEBA DE CÁLCULO:', resultado);
        return resultado;
    }

    /**
     * NUEVO: Herramienta de comparación de métodos
     */
    static compararMetodos(ventaAnterior, indice) {
        const fechaActual = new Date();
        const mes = fechaActual.getMonth() + 1;
        
        const comparacion = {
            datos_entrada: { venta_anterior: ventaAnterior, indice: indice },
            metodo_normal: Math.round(ventaAnterior * indice),
            metodo_proporcional_verano: null,
            metodo_proporcional_invierno: null,
            recomendacion: 'NORMAL'
        };
        
        // Calcular método proporcional para verano
        const diasVerano = IndiceEditor.calcularDiasRestantesVerano(fechaActual);
        if (diasVerano > 0) {
            comparacion.metodo_proporcional_verano = Math.round((ventaAnterior / 180) * diasVerano * indice);
            comparacion.dias_restantes_verano = diasVerano;
            
            if (mes >= 8 || mes <= 1) {
                comparacion.recomendacion = 'PROPORCIONAL_VERANO';
            }
        }
        
        // Calcular método proporcional para invierno
        const diasInvierno = IndiceEditor.calcularDiasRestantesInvierno(fechaActual);
        if (diasInvierno > 0) {
            comparacion.metodo_proporcional_invierno = Math.round((ventaAnterior / 180) * diasInvierno * indice);
            comparacion.dias_restantes_invierno = diasInvierno;
            
            if (mes >= 2 && mes <= 7) {
                comparacion.recomendacion = 'PROPORCIONAL_INVIERNO';
            }
        }
        
        console.table(comparacion);
        return comparacion;
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    IndiceEditor.init();
    IndiceEditor.cargarHistorial();
    
    // Funciones globales para diagnóstico y pruebas
    window.diagnosticarTemporadas = () => IndiceEditor.diagnosticarTemporadas();
    window.debugIndiceEditor = () => IndiceEditor.debugCompleto();
    window.simularEscenarios = () => IndiceEditor.simularEscenarios();
    window.estadisticasIndices = () => IndiceEditor.obtenerEstadisticasIndices();
    window.probarCalculo = (venta, indice, dias) => IndiceEditor.probarCalculo(venta, indice, dias);
    window.compararMetodos = (venta, indice) => IndiceEditor.compararMetodos(venta, indice);
    window.exportarHistorialIndices = () => IndiceEditor.exportarHistorial();
    window.resetearTodosLosIndices = (valor) => IndiceEditor.resetearIndices(valor);
    
    console.log('✅ IndiceEditor CORREGIDO cargado COMPLETO');
    console.log('💡 Funciones disponibles en consola:');
    console.log('  📊 diagnosticarTemporadas() - Ver lógica de temporadas');
    console.log('  🔍 debugIndiceEditor() - Debug completo del sistema');
    console.log('  🎯 simularEscenarios() - Simular diferentes meses');
    console.log('  📈 estadisticasIndices() - Ver estadísticas de índices');
    console.log('  🧮 probarCalculo(venta, indice, dias) - Probar cálculo manual');
    console.log('  ⚖️  compararMetodos(venta, indice) - Comparar métodos de cálculo');
    console.log('  📄 exportarHistorialIndices() - Exportar historial a CSV');
    console.log('  🔄 resetearTodosLosIndices(valor) - Resetear todos los índices');
    
    console.log('\n🎯 Ejemplos de uso:');
    console.log('  probarCalculo(1000, 1.2, 45) // Probar con 1000 de venta, índice 1.2 y 45 días');
    console.log('  compararMetodos(1500, 0.8) // Comparar métodos para venta 1500 e índice 0.8');
});