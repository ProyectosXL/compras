
// Editor de índices de variación - CORREGIDO PARA TEMPORADA ACTUAL
// Archivo: presupuestos/js/indice-editor.js

class IndiceEditor {
    static indiceEditando = null;
    static historialCambios = [];
    static modalInstancia = null;

    /**
     * CORREGIDO: Actualizar datos en memoria con cálculo CORRECTO por temporada
     */
    static actualizarDatosMemoriaFrontendCorregido(nuevoIndice) {
        const app = window.presupuestoApp;
        const datos = app.getDatos(IndiceEditor.indiceEditando.solapa);
        
        if (datos[IndiceEditor.indiceEditando.index]) {
            const registro = datos[IndiceEditor.indiceEditando.index];
            
            console.log('=== DEBUG CÁLCULO CORREGIDO POR TEMPORADA ===');
            console.log(`Rubro: ${registro.RUBRO}`);
            console.log(`Categoría: ${registro.CATEGORIA_PADRE}`);
            console.log(`Índice anterior: ${registro.INDICE_VARIACION}`);
            console.log(`Índice nuevo: ${nuevoIndice.toFixed(2)}`);
            
            // Actualizar índice con 2 decimales
            registro.INDICE_VARIACION = parseFloat(nuevoIndice.toFixed(2));
            
            // Extraer ventas históricas
            const ventaVeranoAnterior = IndiceEditor.extraerVentaAnteriorCorregida(registro, 'VERANO');
            const ventaInviernoAnterior = IndiceEditor.extraerVentaAnteriorCorregida(registro, 'INVIERNO');
            
            console.log(`Venta Verano Anterior: ${ventaVeranoAnterior}`);
            console.log(`Venta Invierno Anterior: ${ventaInviernoAnterior}`);
            
            const stockProyectado = parseFloat(registro.STOCK_PROYECTADO || 0);
            
            // CORRECCIÓN PRINCIPAL: Determinar temporada actual y aplicar cálculo apropiado
            const fechaActual = new Date();
            const mes = fechaActual.getMonth() + 1;
            const temporadaActual = IndiceEditor.determinarTemporadaActual(fechaActual);
            
            console.log(`Temporada actual detectada: ${temporadaActual.tipo} (mes: ${mes})`);
            
            let nuevaVentaVerano, nuevaVentaInvierno;
            
            // CALCULAR VENTA PROYECTADA VERANO
            if (temporadaActual.tipo === 'VERANO' && temporadaActual.enCurso) {
                // Estamos EN temporada de verano -> cálculo proporcional
                const diasRestantesVerano = IndiceEditor.calcularDiasRestantesVerano(fechaActual);
                const diasTotalVerano = 180;
                nuevaVentaVerano = Math.round((ventaVeranoAnterior / diasTotalVerano) * diasRestantesVerano * registro.INDICE_VARIACION);
                
                console.log(`VERANO - CÁLCULO PROPORCIONAL:`);
                console.log(`Días restantes: ${diasRestantesVerano}`);
                console.log(`Fórmula: (${ventaVeranoAnterior} / ${diasTotalVerano}) * ${diasRestantesVerano} * ${registro.INDICE_VARIACION} = ${nuevaVentaVerano}`);
            } else {
                // NO estamos en temporada de verano -> cálculo normal
                nuevaVentaVerano = Math.round(ventaVeranoAnterior * registro.INDICE_VARIACION);
                console.log(`VERANO - CÁLCULO NORMAL: ${ventaVeranoAnterior} * ${registro.INDICE_VARIACION} = ${nuevaVentaVerano}`);
            }
            
            // CALCULAR VENTA PROYECTADA INVIERNO
            if (temporadaActual.tipo === 'INVIERNO' && temporadaActual.enCurso) {
                // Estamos EN temporada de invierno -> cálculo proporcional
                const diasRestantesInvierno = IndiceEditor.calcularDiasRestantesInvierno(fechaActual);
                const diasTotalInvierno = 180;
                nuevaVentaInvierno = Math.round((ventaInviernoAnterior / diasTotalInvierno) * diasRestantesInvierno * registro.INDICE_VARIACION);
                
                console.log(`INVIERNO - CÁLCULO PROPORCIONAL:`);
                console.log(`Días restantes: ${diasRestantesInvierno}`);
                console.log(`Fórmula: (${ventaInviernoAnterior} / ${diasTotalInvierno}) * ${diasRestantesInvierno} * ${registro.INDICE_VARIACION} = ${nuevaVentaInvierno}`);
            } else {
                // NO estamos en temporada de invierno -> cálculo normal
                nuevaVentaInvierno = Math.round(ventaInviernoAnterior * registro.INDICE_VARIACION);
                console.log(`INVIERNO - CÁLCULO NORMAL: ${ventaInviernoAnterior} * ${registro.INDICE_VARIACION} = ${nuevaVentaInvierno}`);
            }
            
            const nuevaCompraProyectada = Math.round(stockProyectado - nuevaVentaVerano - nuevaVentaInvierno);
            
            console.log(`Stock Proyectado: ${stockProyectado}`);
            console.log(`Compra Proyectada: ${stockProyectado} - ${nuevaVentaVerano} - ${nuevaVentaInvierno} = ${nuevaCompraProyectada}`);
            console.log('=== FIN DEBUG ===');
            
            // Actualizar valores calculados
            registro.VENTA_PROY_VERANO = nuevaVentaVerano;
            registro.VENTA_PROY_INVIERNO = nuevaVentaInvierno;
            registro.COMPRA_PROYECTADA = nuevaCompraProyectada;
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
     * NUEVO: Calcular días restantes de temporada de verano (CORREGIDO)
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
        return Math.ceil(diferencia / (1000 * 60 * 60 * 24));
    }

    /**
     * NUEVO: Calcular días restantes de temporada de invierno (CORREGIDO)
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
        return Math.ceil(diferencia / (1000 * 60 * 60 * 24));
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

    static editarIndice(rubro, categoria, indiceActual, solapa, index) {
        IndiceEditor.indiceEditando = {
            rubro: rubro,
            categoria: categoria,
            indiceActual: indiceActual,
            solapa: solapa,
            index: index
        };
        
        document.getElementById('modal-rubro').textContent = rubro;
        document.getElementById('modal-categoria').textContent = categoria;
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
        
        if (celdas[3]) {
            const input = celdas[3].querySelector('.indice-input');
            if (input) {
                input.value = registroActualizado.INDICE_VARIACION.toFixed(2);
            }
        }
        
        if (celdas[5]) {
            celdas[5].textContent = FormatoUtils.formatearNumero(registroActualizado.VENTA_PROY_VERANO || 0);
        }
        
        if (celdas[7]) {
            celdas[7].textContent = FormatoUtils.formatearNumero(registroActualizado.VENTA_PROY_INVIERNO || 0);
        }
        
        if (celdas[8]) {
            const compraProyectada = registroActualizado.COMPRA_PROYECTADA || 0;
            celdas[8].textContent = FormatoUtils.formatearNumero(compraProyectada);
            celdas[8].className = `text-end bg-success-subtle text-success-emphasis fw-bold ${FormatoUtils.obtenerClaseValor(compraProyectada)}`;
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
            indiceAnterior: IndiceEditor.indiceEditando.indiceActual,
            indiceNuevo: nuevoIndice,
            timestamp: Date.now(),
            usuario: 'current_user'
        };
        
        IndiceEditor.historialCambios.unshift(cambio);
        
        if (IndiceEditor.historialCambios.length > 100) {
            IndiceEditor.historialCambios = IndiceEditor.historialCambios.slice(0, 100);
        }
        
        StorageUtils.guardar('historial_indices', IndiceEditor.historialCambios, 30 * 24 * 60 * 60 * 1000);
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