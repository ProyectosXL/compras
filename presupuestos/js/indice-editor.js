
// Editor de índices de variación
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
        document.getElementById('modal-nuevo-indice').value = FormatoUtils.formatearDecimal(indiceActual, 4);
        
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
     * Guardar nuevo índice
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
            // Enviar actualización al servidor
            const response = await APIClient.actualizarIndice(
                IndiceEditor.indiceEditando.rubro,
                IndiceEditor.indiceEditando.categoria,
                nuevoIndice,
                IndiceEditor.indiceEditando.solapa
            );
            
            if (response.success) {
                // Actualizar datos en memoria
                IndiceEditor.actualizarDatosMemoria(nuevoIndice, response.data);
                
                // Actualizar tabla
                IndiceEditor.actualizarFilaTabla(response.data);
                
                // Registrar cambio en historial
                IndiceEditor.registrarCambio(nuevoIndice);
                
                UIUtils.mostrarAlerta('Índice actualizado correctamente', 'success');
                
                // Cerrar modal
                IndiceEditor.modalInstancia.hide();
                
            } else {
                UIUtils.mostrarAlerta('Error al actualizar índice: ' + response.message, 'error');
            }
            
        } catch (error) {
            console.error('Error actualizando índice:', error);
            UIUtils.mostrarAlerta('Error de conexión al actualizar índice', 'error');
        } finally {
            IndiceEditor.mostrarLoadingBoton(btnGuardar, false, textoOriginal);
            TablaRenderer.resaltarFila(IndiceEditor.indiceEditando.solapa, IndiceEditor.indiceEditando.index, false);
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
     * Mostrar preview del cálculo con el nuevo índice
     */
    static mostrarPreviewCalculo(nuevoIndice) {
        if (!IndiceEditor.indiceEditando || isNaN(nuevoIndice)) return;
        
        const diferencia = nuevoIndice - IndiceEditor.indiceEditando.indiceActual;
        const porcentajeCambio = (diferencia / IndiceEditor.indiceEditando.indiceActual) * 100;
        
        let previewElement = document.getElementById('preview-calculo');
        if (!previewElement) {
            previewElement = document.createElement('div');
            previewElement.id = 'preview-calculo';
            previewElement.className = 'mt-2 p-2 bg-light rounded';
            document.querySelector('#modalEditarIndice .modal-body').appendChild(previewElement);
        }
        
        let mensaje = `Cambio: ${FormatoUtils.formatearDecimal(diferencia, 4)} `;
        mensaje += `(${porcentajeCambio > 0 ? '+' : ''}${FormatoUtils.formatearDecimal(porcentajeCambio, 1)}%)`;
        
        previewElement.innerHTML = `
            <small class="text-muted">
                <strong>Preview:</strong><br>
                Índice anterior: ${FormatoUtils.formatearDecimal(IndiceEditor.indiceEditando.indiceActual, 4)}<br>
                Índice nuevo: ${FormatoUtils.formatearDecimal(nuevoIndice, 4)}<br>
                ${mensaje}
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
     * Actualizar datos en memoria
     */
    static actualizarDatosMemoria(nuevoIndice, datosActualizados) {
        const app = window.presupuestoApp;
        const datos = app.getDatos(IndiceEditor.indiceEditando.solapa);
        
        if (datos[IndiceEditor.indiceEditando.index]) {
            datos[IndiceEditor.indiceEditando.index].INDICE_VARIACION = nuevoIndice;
            
            // Actualizar valores calculados si vienen en la respuesta
            if (datosActualizados) {
                Object.assign(datos[IndiceEditor.indiceEditando.index], datosActualizados);
            }
        }
    }

    /**
     * Actualizar fila en la tabla
     */
    static actualizarFilaTabla(datosActualizados) {
        if (!datosActualizados) return;
        
        // Re-renderizar la tabla correspondiente
        const app = window.presupuestoApp;
        const datos = app.getDatos(IndiceEditor.indiceEditando.solapa);
        
        switch (IndiceEditor.indiceEditando.solapa) {
            case 'verano':
                TablaRenderer.renderizarTablaVerano(datos);
                break;
            case 'invierno':
                TablaRenderer.renderizarTablaInvierno(datos);
                break;
        }
        
        // También actualizar solo la fila específica si es más eficiente
        TablaRenderer.actualizarFilaCalculos(
            IndiceEditor.indiceEditando.solapa, 
            IndiceEditor.indiceEditando.index, 
            datosActualizados
        );
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
            usuario: 'current_user' // Esto se puede obtener del sistema de autenticación
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
        input.step = '0.0001';
        input.min = '0';
        input.max = '10';
        input.value = FormatoUtils.formatearDecimal(indiceActual, 4);
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
                    const response = await APIClient.actualizarIndice(rubro, categoria, nuevoIndice, solapa);
                    
                    if (response.success) {
                        // Actualizar datos
                        IndiceEditor.actualizarDatosMemoria(nuevoIndice, response.data);
                        
                        // Restaurar celda con nuevo valor
                        celda.innerHTML = contenidoOriginal.replace(
                            /value="[^"]*"/,
                            `value="${FormatoUtils.formatearDecimal(nuevoIndice, 4)}"`
                        );
                        
                        UIUtils.mostrarAlerta('Índice actualizado', 'success', 2000);
                        
                        // Registrar cambio
                        IndiceEditor.registrarCambio(nuevoIndice);
                        
                    } else {
                        throw new Error(response.message);
                    }
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
            
            // Actualizar en lotes
            const LOTE_SIZE = 10;
            let procesados = 0;
            
            for (let i = 0; i < actualizaciones.length; i += LOTE_SIZE) {
                const lote = actualizaciones.slice(i, i + LOTE_SIZE);
                
                try {
                    await APIClient.actualizarMultiplesIndices(lote, 'verano');
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
            const response = await APIClient.actualizarIndice(
                ultimoCambio.rubro,
                ultimoCambio.categoria,
                ultimoCambio.indiceAnterior,
                ultimoCambio.solapa
            );
            
            if (response.success) {
                // Remover del historial
                IndiceEditor.historialCambios.shift();
                StorageUtils.guardar('historial_indices', IndiceEditor.historialCambios);
                
                // Actualizar datos
                await window.presupuestoApp.cargarDatosSolapas();
                
                UIUtils.mostrarAlerta('Cambio deshecho correctamente', 'success');
            }
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
     * Cargar historial desde localStorage
     */
    static cargarHistorial() {
        const historial = StorageUtils.obtener('historial_indices');
        if (historial && Array.isArray(historial)) {
            IndiceEditor.historialCambios = historial;
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    IndiceEditor.init();
    IndiceEditor.cargarHistorial();
});