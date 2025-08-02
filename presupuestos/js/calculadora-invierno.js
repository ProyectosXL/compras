
// Calculadora específica para COMPRA PROYECTADA INVIERNO - CORREGIDA
// Archivo: presupuestos/js/calculadora-invierno.js

class CalculadoraInvierno {
    
    /**
     * CORREGIDO: Calcular ventas proyectadas específicas para la solapa de Compra Proyectada Invierno
     */
    static calcularVentasProyectadas(ventaVeranoAnterior, ventaInviernoAnterior, indiceVerano, indiceInvierno, temporadaActual) {
        console.log('❄️ CALCULADORA INVIERNO - Iniciando cálculo');
        console.log(`Temporada actual: ${temporadaActual.tipo} (${temporadaActual.enCurso ? 'en curso' : 'no en curso'})`);
        
        let ventaProyVerano, ventaProyInvierno;
        
        if (temporadaActual.tipo === 'INVIERNO' && temporadaActual.enCurso) {
            // Contexto: Transitando invierno
            // • Venta Proy. Verano: Próximo Verano Completo
            // • Venta Proy. Invierno: Proporcional Invierno Actual + Próximo Invierno Completo
            
            ventaProyVerano = Math.round(ventaVeranoAnterior * indiceVerano); // Próximo verano completo
            
            const diasRestantesInvierno = CalculadoraInvierno.calcularDiasRestantesInvierno(new Date());
            const diasTotalesInvierno = CalculadoraInvierno.calcularDiasTotalesInvierno();
            const proporcionInviernoActual = diasRestantesInvierno / diasTotalesInvierno;
            
            const inviernoActualProporcional = Math.round(ventaInviernoAnterior * indiceInvierno * proporcionInviernoActual);
            const proximoInviernoCompleto = Math.round(ventaInviernoAnterior * indiceInvierno);
            
            ventaProyInvierno = inviernoActualProporcional + proximoInviernoCompleto;
            
            console.log(`VERANO (Transitando invierno): Próximo completo = ${ventaProyVerano}`);
            console.log(`INVIERNO (Transitando): Proporcional actual (${inviernoActualProporcional}) + Próximo completo (${proximoInviernoCompleto}) = ${ventaProyInvierno}`);
            
        } else if (temporadaActual.tipo === 'VERANO' && temporadaActual.enCurso) {
            // Contexto: Transitando verano
            // • Venta Proy. Verano: Proporcional Verano Actual
            // • Venta Proy. Invierno: Próximo Invierno Completo
            
            ventaProyInvierno = Math.round(ventaInviernoAnterior * indiceInvierno); // Próximo invierno completo
            
            const diasRestantesVerano = CalculadoraInvierno.calcularDiasRestantesVerano(new Date());
            const diasTotalesVerano = CalculadoraInvierno.calcularDiasTotalesVerano();
            const proporcionVeranoActual = diasRestantesVerano / diasTotalesVerano;
            
            ventaProyVerano = Math.round(ventaVeranoAnterior * indiceVerano * proporcionVeranoActual);
            
            console.log(`VERANO (Transitando): Proporcional actual (${proporcionVeranoActual.toFixed(3)}) = ${ventaProyVerano}`);
            console.log(`INVIERNO (No transitando): Próximo completo = ${ventaProyInvierno}`);
            
        } else {
            // Fuera de temporadas activas, usar completo para ambas
            ventaProyVerano = Math.round(ventaVeranoAnterior * indiceVerano);
            ventaProyInvierno = Math.round(ventaInviernoAnterior * indiceInvierno);
            console.log(`VERANO (Fuera temporada): Próximo completo = ${ventaProyVerano}`);
            console.log(`INVIERNO (Fuera temporada): Próximo completo = ${ventaProyInvierno}`);
        }
        
        return {
            ventaProyVerano,
            ventaProyInvierno
        };
    }
    
    /**
     * Actualizar datos cuando se edita un índice en la solapa de invierno
     */
    static actualizarDatosSolapa(nuevoIndice, indiceEditando, registro) {
        console.log('❄️ INVIERNO - Actualizando datos con nuevo índice:', nuevoIndice);
        
        const temporada = indiceEditando.temporada || 'verano';
        
        // Actualizar el índice correspondiente
        if (temporada === 'verano') {
            registro.INDICE_VARIACION = parseFloat(nuevoIndice.toFixed(2));
        } else {
            // Asegurar que existe el índice de invierno
            if (!registro.INDICE_VARIACION_INVIERNO) {
                registro.INDICE_VARIACION_INVIERNO = registro.INDICE_VARIACION || 1.0;
            }
            registro.INDICE_VARIACION_INVIERNO = parseFloat(nuevoIndice.toFixed(2));
        }
        
        // Obtener ventas anteriores de la tabla
        const { ventaVeranoAnterior, ventaInviernoAnterior } = CalculadoraInvierno.obtenerVentasAnterioresDeTabla(indiceEditando);
        
        // Obtener índices actualizados
        const indiceVerano = parseFloat(registro.INDICE_VARIACION || 1.0);
        const indiceInvierno = parseFloat(registro.INDICE_VARIACION_INVIERNO || indiceVerano);
        
        // Determinar temporada actual
        const temporadaActual = CalculadoraInvierno.determinarTemporadaActual();
        
        // Calcular ventas proyectadas específicas para INVIERNO
        const { ventaProyVerano, ventaProyInvierno } = CalculadoraInvierno.calcularVentasProyectadas(
            ventaVeranoAnterior, 
            ventaInviernoAnterior, 
            indiceVerano, 
            indiceInvierno, 
            temporadaActual
        );
        
        // Calcular compra proyectada
        const stockProyectado = parseFloat(registro.STOCK_PROYECTADO || 0);
        const compraProyectada = Math.round(stockProyectado - ventaProyVerano - ventaProyInvierno);
        
        // Actualizar registro
        registro.VENTA_PROY_VERANO = ventaProyVerano;
        registro.VENTA_PROY_INVIERNO = ventaProyInvierno;
        registro.COMPRA_PROYECTADA = compraProyectada;
        
        console.log(`❄️ INVIERNO - Stock: ${stockProyectado}, Venta V: ${ventaProyVerano}, Venta I: ${ventaProyInvierno}, Compra: ${compraProyectada}`);
        
        return registro;
    }
    
    /**
     * CORREGIDA: Obtener ventas anteriores de la tabla con nuevo orden de columnas
     */
    static obtenerVentasAnterioresDeTabla(indiceEditando) {
        const tbody = document.getElementById(`tbody-${indiceEditando.solapa}`);
        const fila = tbody ? tbody.children[indiceEditando.index] : null;
        
        let ventaVeranoAnterior = 0;
        let ventaInviernoAnterior = 0;
        
        if (fila && fila.children.length >= 11) {
            // NUEVO ORDEN DE COLUMNAS:
            // 0: Rubro
            // 1: Categoría  
            // 2: Stock Proyectado
            // 3: Índice Var. Original
            // 4: Índice Ver. Variación (editable)
            // 5: Venta Ver. Anterior
            // 6: Venta Proy. Verano
            // 7: Índice Inv. Variación (editable)
            // 8: Venta Inv. Anterior  
            // 9: Venta Proy. Invierno
            // 10: Compra Proyectada
            
            const celdaVeranoAnterior = fila.children[5];   // "Venta Ver. Anterior"
            const celdaInviernoAnterior = fila.children[8]; // "Venta Inv. Anterior"
            
            if (celdaVeranoAnterior) {
                ventaVeranoAnterior = FormatoUtils.parsearNumero(celdaVeranoAnterior.textContent.trim()) || 0;
            }
            
            if (celdaInviernoAnterior) {
                ventaInviernoAnterior = FormatoUtils.parsearNumero(celdaInviernoAnterior.textContent.trim()) || 0;
            }
        }
        
        console.log(`📊 ${indiceEditando.solapa.toUpperCase()} - Ventas obtenidas: V=${ventaVeranoAnterior}, I=${ventaInviernoAnterior}`);
        return { ventaVeranoAnterior, ventaInviernoAnterior };
    }
    
    /**
     * CORREGIDA: Actualizar fila específica con nuevo orden de columnas
     */
    static actualizarFilaTabla(indiceEditando, registro) {
        const tbody = document.getElementById(`tbody-${indiceEditando.solapa}`);
        const fila = tbody.children[indiceEditando.index];
        
        if (!fila) {
            console.error('Fila no encontrada para actualizar');
            return;
        }
        
        const celdas = fila.children;
        const temporadaEditada = indiceEditando.temporada;
        
        console.log(`🔄 ${indiceEditando.solapa.toUpperCase()} - Actualizando fila, temporada editada: ${temporadaEditada}`);
        
        // NUEVO ORDEN: Solo actualizar el índice que se editó
        if (temporadaEditada === 'verano' && celdas[4]) { // Índice Ver. Variación
            const input = celdas[4].querySelector('.indice-input');
            if (input) {
                input.value = (registro.INDICE_VARIACION || 1.0).toFixed(2);
                console.log(`✅ Índice VERANO actualizado: ${input.value}`);
            }
        } else if (temporadaEditada === 'invierno' && celdas[7]) { // Índice Inv. Variación
            const input = celdas[7].querySelector('.indice-input');
            if (input) {
                const indiceInvierno = registro.INDICE_VARIACION_INVIERNO || registro.INDICE_VARIACION || 1.0;
                input.value = indiceInvierno.toFixed(2);
                console.log(`✅ Índice INVIERNO actualizado: ${input.value}`);
            }
        }
        
        // Actualizar ventas proyectadas y compra (nuevas posiciones)
        if (celdas[6]) { // Venta Proy. Verano
            celdas[6].textContent = FormatoUtils.formatearNumero(registro.VENTA_PROY_VERANO || 0);
        }
        if (celdas[9]) { // Venta Proy. Invierno
            celdas[9].textContent = FormatoUtils.formatearNumero(registro.VENTA_PROY_INVIERNO || 0);
        }
        
        if (celdas[10]) { // Compra Proyectada
            const compraProyectada = registro.COMPRA_PROYECTADA || 0;
            celdas[10].textContent = FormatoUtils.formatearNumero(compraProyectada);
            celdas[10].className = `text-end bg-success-subtle text-success-emphasis fw-bold ${FormatoUtils.obtenerClaseValor(compraProyectada)}`;
        }
        
        console.log(`✅ ${indiceEditando.solapa.toUpperCase()} - Fila actualizada correctamente`);
    }
    
    // Funciones auxiliares idénticas a CalculadoraVerano (para independencia)
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
    
    static calcularDiasRestantesInvierno(fecha) {
        const mes = fecha.getMonth() + 1;
        const ano = fecha.getFullYear();
        
        if (mes < 2 || mes > 7) return 0;
        
        const finInvierno = new Date(ano, 6, 31, 23, 59, 59);
        if (fecha > finInvierno) return 0;
        
        return Math.ceil((finInvierno - fecha) / (1000 * 60 * 60 * 24));
    }
    
    static calcularDiasTotalesVerano(ano = null) {
        if (!ano) ano = new Date().getFullYear();
        const inicioVerano = new Date(ano - 1, 7, 1);
        const finVerano = new Date(ano, 0, 31);
        return Math.ceil((finVerano - inicioVerano) / (1000 * 60 * 60 * 24)) + 1;
    }
    
    static calcularDiasTotalesInvierno(ano = null) {
        if (!ano) ano = new Date().getFullYear();
        const inicioInvierno = new Date(ano, 1, 1);
        const finInvierno = new Date(ano, 6, 31);
        return Math.ceil((finInvierno - inicioInvierno) / (1000 * 60 * 60 * 24)) + 1;
    }
    
    static determinarTemporadaActual(fecha = null) {
        if (!fecha) fecha = new Date();
        const mes = fecha.getMonth() + 1;
        
        if (mes >= 8 || mes === 1) {
            return {
                tipo: 'VERANO',
                enCurso: true,
                descripcion: mes >= 8 ? 'Verano inicio' : 'Verano final'
            };
        } else if (mes >= 2 && mes <= 7) {
            return {
                tipo: 'INVIERNO',
                enCurso: true,
                descripcion: mes <= 4 ? 'Invierno inicio' : 'Invierno final'
            };
        } else {
            return {
                tipo: 'TRANSICION',
                enCurso: false,
                descripcion: 'Período de transición'
            };
        }
    }
    
    // Función de diagnóstico
    static diagnosticar() {
        const temporada = CalculadoraInvierno.determinarTemporadaActual();
        const diasVerano = temporada.tipo === 'VERANO' ? CalculadoraInvierno.calcularDiasRestantesVerano(new Date()) : 0;
        const diasInvierno = temporada.tipo === 'INVIERNO' ? CalculadoraInvierno.calcularDiasRestantesInvierno(new Date()) : 0;
        
        console.group('❄️ DIAGNÓSTICO CALCULADORA INVIERNO');
        console.log('Temporada actual:', temporada);
        console.log('Días restantes verano:', diasVerano);
        console.log('Días restantes invierno:', diasInvierno);
        console.log('Lógica aplicada:', 
            temporada.tipo === 'INVIERNO' ? 'Verano: Próximo, Invierno: Proporcional + Próximo' :
            temporada.tipo === 'VERANO' ? 'Verano: Proporcional, Invierno: Próximo' :
            'Ambos: Próximo completo'
        );
        console.groupEnd();
        
        return { temporada, diasVerano, diasInvierno };
    }
}

// Hacer disponible globalmente
window.CalculadoraInvierno = CalculadoraInvierno;

// Función de diagnóstico global
window.diagnosticarInvierno = () => CalculadoraInvierno.diagnosticar();

console.log('✅ CalculadoraInvierno CORREGIDA cargada');