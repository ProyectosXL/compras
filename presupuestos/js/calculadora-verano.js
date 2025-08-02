
// Calculadora específica para COMPRA PROYECTADA VERANO
// Archivo: presupuestos/js/calculadora-verano.js

class CalculadoraVerano {
    
    /**
     * Calcular ventas proyectadas específicas para la solapa de Compra Proyectada Verano
     */
    static calcularVentasProyectadas(ventaVeranoAnterior, ventaInviernoAnterior, indiceVerano, indiceInvierno, temporadaActual) {
        console.log('🔥 CALCULADORA VERANO - Iniciando cálculo');
        console.log(`Temporada actual: ${temporadaActual.tipo} (${temporadaActual.enCurso ? 'en curso' : 'no en curso'})`);
        
        let ventaProyVerano, ventaProyInvierno;
        
        if (temporadaActual.tipo === 'VERANO' && temporadaActual.enCurso) {
            // Contexto: Transitando verano
            // • Venta Proy. Verano: Proporcional Verano Actual + Próximo Verano Completo
            // • Venta Proy. Invierno: Próximo Invierno Completo
            
            const diasRestantesVerano = CalculadoraVerano.calcularDiasRestantesVerano(new Date());
            const diasTotalesVerano = CalculadoraVerano.calcularDiasTotalesVerano();
            const proporcionVeranoActual = diasRestantesVerano / diasTotalesVerano;
            
            const veranoActualProporcional = Math.round(ventaVeranoAnterior * indiceVerano * proporcionVeranoActual);
            const proximoVeranoCompleto = Math.round(ventaVeranoAnterior * indiceVerano);
            
            ventaProyVerano = veranoActualProporcional + proximoVeranoCompleto;
            ventaProyInvierno = Math.round(ventaInviernoAnterior * indiceInvierno); // Próximo invierno completo
            
            console.log(`VERANO (Transitando): Proporcional actual (${veranoActualProporcional}) + Próximo completo (${proximoVeranoCompleto}) = ${ventaProyVerano}`);
            console.log(`INVIERNO (Transitando): Próximo completo = ${ventaProyInvierno}`);
            
        } else {
            // Contexto: Transitando invierno
            // • Venta Proy. Verano: Próximo Verano Completo
            // • Venta Proy. Invierno: Proporcional Invierno Actual
            
            ventaProyVerano = Math.round(ventaVeranoAnterior * indiceVerano); // Próximo verano completo
            
            if (temporadaActual.tipo === 'INVIERNO' && temporadaActual.enCurso) {
                const diasRestantesInvierno = CalculadoraVerano.calcularDiasRestantesInvierno(new Date());
                const diasTotalesInvierno = CalculadoraVerano.calcularDiasTotalesInvierno();
                const proporcionInviernoActual = diasRestantesInvierno / diasTotalesInvierno;
                
                ventaProyInvierno = Math.round(ventaInviernoAnterior * indiceInvierno * proporcionInviernoActual);
                
                console.log(`VERANO (No transitando): Próximo completo = ${ventaProyVerano}`);
                console.log(`INVIERNO (Transitando): Proporcional actual (${proporcionInviernoActual.toFixed(3)}) = ${ventaProyInvierno}`);
            } else {
                // Fuera de temporadas, usar completo
                ventaProyInvierno = Math.round(ventaInviernoAnterior * indiceInvierno);
                console.log(`VERANO (Fuera temporada): Próximo completo = ${ventaProyVerano}`);
                console.log(`INVIERNO (Fuera temporada): Próximo completo = ${ventaProyInvierno}`);
            }
        }
        
        return {
            ventaProyVerano,
            ventaProyInvierno
        };
    }
    
    /**
     * Actualizar datos cuando se edita un índice en la solapa de verano
     */
    static actualizarDatosSolapa(nuevoIndice, indiceEditando, registro) {
        console.log('🔥 VERANO - Actualizando datos con nuevo índice:', nuevoIndice);
        
        const temporada = indiceEditando.temporada || 'verano';
        
        // Actualizar el índice correspondiente
        if (temporada === 'verano') {
            registro.INDICE_VARIACION = parseFloat(nuevoIndice.toFixed(2));
        } else {
            registro.INDICE_VARIACION_INVIERNO = parseFloat(nuevoIndice.toFixed(2));
        }
        
        // Obtener ventas anteriores de la tabla
        const { ventaVeranoAnterior, ventaInviernoAnterior } = CalculadoraVerano.obtenerVentasAnterioresDeTabla(indiceEditando);
        
        // Obtener índices actualizados
        const indiceVerano = parseFloat(registro.INDICE_VARIACION || 1.0);
        const indiceInvierno = parseFloat(registro.INDICE_VARIACION_INVIERNO || 1.0);
        
        // Determinar temporada actual
        const temporadaActual = CalculadoraVerano.determinarTemporadaActual();
        
        // Calcular ventas proyectadas específicas para VERANO
        const { ventaProyVerano, ventaProyInvierno } = CalculadoraVerano.calcularVentasProyectadas(
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
        
        console.log(`🔥 VERANO - Stock: ${stockProyectado}, Venta V: ${ventaProyVerano}, Venta I: ${ventaProyInvierno}, Compra: ${compraProyectada}`);
        
        return registro;
    }
    
    /**
     * Obtener ventas anteriores de la tabla
     */
    static obtenerVentasAnterioresDeTabla(indiceEditando) {
        const tbody = document.getElementById(`tbody-${indiceEditando.solapa}`);
        const fila = tbody ? tbody.children[indiceEditando.index] : null;
        
        let ventaVeranoAnterior = 0;
        let ventaInviernoAnterior = 0;
        
        if (fila && fila.children.length >= 10) {
            const celdaVeranoAnterior = fila.children[4];   // "Venta Ver.Anterior"
            const celdaInviernoAnterior = fila.children[7]; // "Venta Inv.Anterior"
            
            if (celdaVeranoAnterior) {
                ventaVeranoAnterior = FormatoUtils.parsearNumero(celdaVeranoAnterior.textContent.trim()) || 0;
            }
            
            if (celdaInviernoAnterior) {
                ventaInviernoAnterior = FormatoUtils.parsearNumero(celdaInviernoAnterior.textContent.trim()) || 0;
            }
        }
        
        return { ventaVeranoAnterior, ventaInviernoAnterior };
    }
    
    /**
     * Actualizar fila específica para solapa de verano
     */
    static actualizarFilaTabla(indiceEditando, registro) {
        const tbody = document.getElementById(`tbody-${indiceEditando.solapa}`);
        const fila = tbody.children[indiceEditando.index];
        
        if (!fila) return;
        
        const celdas = fila.children;
        const temporadaEditada = indiceEditando.temporada;
        
        // Solo actualizar el índice que se editó
        if (temporadaEditada === 'verano' && celdas[3]) {
            const input = celdas[3].querySelector('.indice-input');
            if (input) {
                input.value = (registro.INDICE_VARIACION || 1.0).toFixed(2);
            }
        } else if (temporadaEditada === 'invierno' && celdas[6]) {
            const input = celdas[6].querySelector('.indice-input');
            if (input) {
                input.value = (registro.INDICE_VARIACION_INVIERNO || 1.0).toFixed(2);
            }
        }
        
        // Actualizar ventas proyectadas y compra
        if (celdas[5]) celdas[5].textContent = FormatoUtils.formatearNumero(registro.VENTA_PROY_VERANO || 0);
        if (celdas[8]) celdas[8].textContent = FormatoUtils.formatearNumero(registro.VENTA_PROY_INVIERNO || 0);
        
        if (celdas[9]) {
            const compraProyectada = registro.COMPRA_PROYECTADA || 0;
            celdas[9].textContent = FormatoUtils.formatearNumero(compraProyectada);
            celdas[9].className = `text-end bg-success-subtle text-success-emphasis fw-bold ${FormatoUtils.obtenerClaseValor(compraProyectada)}`;
        }
    }
    
    // Funciones auxiliares de cálculo de días
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
}

// Hacer disponible globalmente
window.CalculadoraVerano = CalculadoraVerano;