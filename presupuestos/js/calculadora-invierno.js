
// Calculadora para COMPRA PROYECTADA INVIERNO
// Archivo: presupuestos/js/calculadora-invierno.js
//
// Igual que CalculadoraVerano: el calendario de temporadas lo calcula el servidor
// y llega por TemporadaServidor. Acá quedó solo la fórmula. Ver el comentario de
// temporada-servidor.js para el porqué.

class CalculadoraInvierno {
    
    /**
     * CORREGIDO: Calcular ventas proyectadas usando tu fórmula exacta
     */
    static calcularVentasProyectadas(ventaVeranoAnterior, ventaInviernoAnterior, indiceVerano, indiceInvierno, temporadaActual) {
        console.log('❄️ CALCULADORA INVIERNO - Iniciando cálculo');
        console.log(`Temporada actual: ${temporadaActual.tipo} (${temporadaActual.enCurso ? 'en curso' : 'no en curso'})`);
        
        let ventaProyVerano, ventaProyInvierno;
        
        if (temporadaActual.tipo === 'INVIERNO' && temporadaActual.enCurso) {
            // Contexto: Transitando invierno
            // • Venta Proy. Verano: Próximo Verano Completo
            // • Venta Proy. Invierno: FÓRMULA CORREGIDA
            
            ventaProyVerano = Math.round(ventaVeranoAnterior * indiceVerano); // Próximo verano completo

            // Días del servidor: son los mismos que usó PHP para el render inicial.
            const diasRestantesInvierno = TemporadaServidor.diasRestantes();
            const diasTotalesInvierno = TemporadaServidor.diasTotales();

            // Mismo orden de operaciones que PHP (venta * indice * proporcion) para que
            // el redondeo caiga igual y reeditar el mismo índice no mueva el número.
            const inviernoActualProporcional = Math.round(
                ventaInviernoAnterior * indiceInvierno * (diasRestantesInvierno / diasTotalesInvierno)
            );
            const proximoInviernoCompleto = Math.round(ventaInviernoAnterior * indiceInvierno);
            
            ventaProyInvierno = inviernoActualProporcional + proximoInviernoCompleto;
            
            console.log(`VERANO (No transitando): Próximo completo = ${ventaProyVerano}`);
            console.log(`INVIERNO (Transitando): Proporcional (${ventaInviernoAnterior}/${diasTotalesInvierno}*${diasRestantesInvierno}*${indiceInvierno} = ${inviernoActualProporcional}) + Próximo completo (${proximoInviernoCompleto}) = ${ventaProyInvierno}`);
            
        } else if (temporadaActual.tipo === 'VERANO' && temporadaActual.enCurso) {
            // Contexto: Transitando verano
            // • Venta Proy. Verano: FÓRMULA CORREGIDA
            // • Venta Proy. Invierno: Próximo Invierno Completo
            
            const diasRestantesVerano = TemporadaServidor.diasRestantes();
            const diasTotalesVerano = TemporadaServidor.diasTotales();

            ventaProyVerano = Math.round(
                ventaVeranoAnterior * indiceVerano * (diasRestantesVerano / diasTotalesVerano)
            );
            
            ventaProyInvierno = Math.round(ventaInviernoAnterior * indiceInvierno); // Próximo invierno completo
            
            console.log(`VERANO (Transitando): Proporcional (${ventaVeranoAnterior}/${diasTotalesVerano}*${diasRestantesVerano}*${indiceVerano} = ${ventaProyVerano})`);
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
     * CORREGIDO: Actualizar datos leyendo ventas desde los datos originales, no de la tabla
     */
    static actualizarDatosSolapa(nuevoIndice, indiceEditando, registro) {
        console.log('❄️ INVIERNO - Actualizando datos con nuevo índice:', nuevoIndice);
        console.log('❄️ INVIERNO - Temporada editada:', indiceEditando.temporada);
        
        // CORRECCIÓN: NO leer de la tabla, usar los datos originales directamente
        console.log(`❄️ INVIERNO - Índices recibidos: V=${registro.INDICE_VARIACION}, I=${registro.INDICE_VARIACION_INVIERNO || registro.INDICE_VARIACION}`);
        
        // OBTENER VENTAS ANTERIORES DIRECTAMENTE DE LOS DATOS ORIGINALES
        const ventaVeranoAnterior = CalculadoraInvierno.extraerVentaAnteriorDeRegistro(registro, 'VERANO');
        const ventaInviernoAnterior = CalculadoraInvierno.extraerVentaAnteriorDeRegistro(registro, 'INVIERNO');
        
        console.log(`❄️ INVIERNO - Ventas desde datos originales: V=${ventaVeranoAnterior}, I=${ventaInviernoAnterior}`);
        
        // USAR LOS ÍNDICES QUE VIENEN EN EL REGISTRO (ya están correctamente actualizados)
        const indiceVerano = parseFloat(registro.INDICE_VARIACION || 1.0);
        const indiceInvierno = parseFloat(registro.INDICE_VARIACION_INVIERNO || indiceVerano);
        
        console.log(`❄️ INVIERNO - Índices a usar para cálculo: V=${indiceVerano}, I=${indiceInvierno}`);
        
        // Temporada del servidor. Si no llegó, no se recalcula: devolver el registro
        // intacto es preferible a producir un número que no coincida con el del render.
        const temporadaActual = CalculadoraInvierno.determinarTemporadaActual();
        if (!temporadaActual) {
            TemporadaServidor.advertirNoDisponible('CalculadoraInvierno');
            return registro;
        }

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
        
        // Crear registro actualizado conservando TODOS los índices originales
        const registroActualizado = {
            ...registro,
            VENTA_PROY_VERANO: ventaProyVerano,
            VENTA_PROY_INVIERNO: ventaProyInvierno,
            COMPRA_PROYECTADA: compraProyectada
        };
        
        console.log(`❄️ INVIERNO - RESULTADO: Stock: ${stockProyectado}, Venta V: ${ventaProyVerano}, Venta I: ${ventaProyInvierno}, Compra: ${compraProyectada}`);
        
        return registroActualizado;
    }

    /**
     * NUEVO: Extraer venta anterior directamente del registro de datos (DINÁMICO)
     */
    static extraerVentaAnteriorDeRegistro(registro, temporada) {
        if (temporada === 'VERANO') {
            // Buscar columnas de VERANO y ordenar por año (más reciente primero)
            const columnasVerano = [];
            for (const [columna, valor] of Object.entries(registro)) {
                if ((columna.includes('VERANO') || columna.includes('VTA_VERANO')) && 
                    !columna.includes('PROY') && 
                    valor && !isNaN(valor) && parseFloat(valor) > 0) {
                    
                    // Extraer año de la columna
                    const matchAno = columna.match(/\d{2}/);
                    const ano = matchAno ? parseInt(matchAno[0]) : 0;
                    
                    columnasVerano.push({
                        columna: columna,
                        valor: parseFloat(valor),
                        ano: ano
                    });
                }
            }
            
            // Ordenar por año descendente (más reciente primero)
            columnasVerano.sort((a, b) => b.ano - a.ano);
            
            // Tomar la primera (más reciente)
            if (columnasVerano.length > 0) {
                console.log(`✅ VERANO encontrado en datos: ${columnasVerano[0].columna} = ${columnasVerano[0].valor}`);
                return columnasVerano[0].valor;
            }
            
        } else if (temporada === 'INVIERNO') {
            // Buscar columnas de INVIERNO y ordenar por año (más reciente primero)
            const columnasInvierno = [];
            for (const [columna, valor] of Object.entries(registro)) {
                if ((columna.includes('INVIERNO') || columna.includes('VTA_INVIERNO')) && 
                    !columna.includes('PROY') && 
                    valor && !isNaN(valor) && parseFloat(valor) > 0) {
                    
                    // Extraer año de la columna
                    const matchAno = columna.match(/\d{2}/);
                    const ano = matchAno ? parseInt(matchAno[0]) : 0;
                    
                    columnasInvierno.push({
                        columna: columna,
                        valor: parseFloat(valor),
                        ano: ano
                    });
                }
            }
            
            // Ordenar por año descendente (más reciente primero)
            columnasInvierno.sort((a, b) => b.ano - a.ano);
            
            // Tomar la primera (más reciente)
            if (columnasInvierno.length > 0) {
                console.log(`✅ INVIERNO encontrado en datos: ${columnasInvierno[0].columna} = ${columnasInvierno[0].valor}`);
                return columnasInvierno[0].valor;
            }
            
            // DEBUG: Mostrar todas las columnas disponibles si no encuentra
            const columnasInviernoDisponibles = Object.keys(registro).filter(key => 
                key.toLowerCase().includes('invierno')
            );
            console.warn(`❌ No se encontró venta INVIERNO en datos. Columnas disponibles:`, columnasInviernoDisponibles);
        }
        
        console.warn(`❌ No se encontró venta ${temporada} en datos para:`, {
            rubro: registro.RUBRO,
            categoria: registro.CATEGORIA_PADRE,
            columnas_disponibles: Object.keys(registro).filter(key => 
                key.toLowerCase().includes(temporada.toLowerCase())
            )
        });
        
        return 0;
    }
            
    /**
     * CORREGIDA: Función auxiliar para parsear números con separador de miles
     */
    static parsearNumeroConSeparadorMiles(texto) {
        if (!texto || typeof texto !== 'string') return 0;
        
        // Eliminar espacios y obtener texto limpio
        const textoLimpio = texto.trim();
        
        // Si está vacío o es '-', retornar 0
        if (!textoLimpio || textoLimpio === '-') return 0;
        
        // CORRECCIÓN: Detectar el formato del número
        // Formato argentino: "1.500,50" (punto = miles, coma = decimal)
        // Formato internacional: "1,500.50" (coma = miles, punto = decimal)
        
        let numeroLimpio;
        
        // Si tiene punto seguido de exactamente 3 dígitos y luego coma, es formato argentino
        if (/\d{1,3}(\.\d{3})*,\d{1,2}$/.test(textoLimpio)) {
            // Formato argentino: "1.500,50"
            numeroLimpio = textoLimpio
                .replace(/\./g, '')  // Eliminar puntos (separadores de miles)
                .replace(',', '.');  // Reemplazar coma decimal por punto
            console.log(`🔍 Formato argentino detectado: "${textoLimpio}" → "${numeroLimpio}"`);
        } 
        // Si tiene coma seguida de exactamente 3 dígitos y luego punto, es formato internacional
        else if (/\d{1,3}(,\d{3})*\.\d{1,2}$/.test(textoLimpio)) {
            // Formato internacional: "1,500.50"
            numeroLimpio = textoLimpio.replace(/,/g, ''); // Eliminar comas (separadores de miles)
            console.log(`🔍 Formato internacional detectado: "${textoLimpio}" → "${numeroLimpio}"`);
        }
        // Si solo tiene puntos sin comas (ej: "1.500")
        else if (/^\d{1,3}(\.\d{3})+$/.test(textoLimpio)) {
            // Formato argentino sin decimales: "1.500"
            numeroLimpio = textoLimpio.replace(/\./g, '');
            console.log(`🔍 Formato argentino sin decimales: "${textoLimpio}" → "${numeroLimpio}"`);
        }
        // Si solo tiene comas sin puntos (ej: "1,500")
        else if (/^\d{1,3}(,\d{3})+$/.test(textoLimpio)) {
            // Formato internacional sin decimales: "1,500"
            numeroLimpio = textoLimpio.replace(/,/g, '');
            console.log(`🔍 Formato internacional sin decimales: "${textoLimpio}" → "${numeroLimpio}"`);
        }
        // Si tiene solo un punto y menos de 4 dígitos después, es decimal
        else if (/^\d+\.\d{1,2}$/.test(textoLimpio)) {
            // Número decimal simple: "0.95"
            numeroLimpio = textoLimpio;
            console.log(`🔍 Número decimal simple: "${textoLimpio}" → "${numeroLimpio}"`);
        }
        // Si tiene solo una coma y menos de 4 dígitos después, es decimal argentino
        else if (/^\d+,\d{1,2}$/.test(textoLimpio)) {
            // Número decimal argentino: "0,95"
            numeroLimpio = textoLimpio.replace(',', '.');
            console.log(`🔍 Número decimal argentino: "${textoLimpio}" → "${numeroLimpio}"`);
        }
        // Si es un número entero simple
        else if (/^\d+$/.test(textoLimpio)) {
            numeroLimpio = textoLimpio;
            console.log(`🔍 Número entero simple: "${textoLimpio}" → "${numeroLimpio}"`);
        }
        // Fallback: intentar limpiar de forma genérica
        else {
            // Última opción: asumir formato argentino
            numeroLimpio = textoLimpio
                .replace(/\./g, '')
                .replace(',', '.');
            console.log(`🔍 Fallback formato argentino: "${textoLimpio}" → "${numeroLimpio}"`);
        }
        
        const numero = parseFloat(numeroLimpio);
        const resultado = isNaN(numero) ? 0 : numero;
        
        console.log(`🔍 PARSEO FINAL: "${texto}" → ${resultado}`);
        return resultado;
    }
    
    /**
     * CORREGIDA: Obtener ventas anteriores de la tabla con parseo correcto y debugging
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
            
            console.log(`🔍 DEBUG TABLA - Fila ${indiceEditando.index}:`);
            console.log(`  Total columnas: ${fila.children.length}`);
            
            if (celdaVeranoAnterior) {
                const textoVerano = celdaVeranoAnterior.textContent.trim();
                console.log(`🔍 Celda Verano [5]: "${textoVerano}"`);
                ventaVeranoAnterior = CalculadoraVerano.parsearNumeroConSeparadorMiles(textoVerano);
            } else {
                console.error(`❌ Celda Verano Anterior [5] no encontrada`);
            }
            
            if (celdaInviernoAnterior) {
                const textoInvierno = celdaInviernoAnterior.textContent.trim();
                console.log(`🔍 Celda Invierno [8]: "${textoInvierno}"`);
                ventaInviernoAnterior = CalculadoraVerano.parsearNumeroConSeparadorMiles(textoInvierno);
            } else {
                console.error(`❌ Celda Invierno Anterior [8] no encontrada`);
            }
            
            // DEBUG: Mostrar contenido de todas las celdas
            console.log(`🔍 Contenido de todas las celdas:`);
            for (let i = 0; i < Math.min(fila.children.length, 11); i++) {
                console.log(`  [${i}]: "${fila.children[i].textContent.trim()}"`);
            }
        } else {
            console.error(`❌ Fila no encontrada o insuficientes columnas. Fila: ${!!fila}, Columnas: ${fila?.children?.length || 0}`);
        }
        
        console.log(`📊 ${indiceEditando.solapa.toUpperCase()} - Ventas parseadas: V=${ventaVeranoAnterior}, I=${ventaInviernoAnterior}`);
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
        
        // Mantener celda de Índice Var. Original
        if (celdas[3] && typeof TablaRendererUtils !== 'undefined') {
            const orig = TablaRendererUtils.obtenerIndiceOriginal(registro);
            celdas[3].textContent = orig.toFixed(2);
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
    
    /**
     * Temporada en curso, según el servidor (ver CalculadoraVerano).
     */
    static determinarTemporadaActual() {
        return TemporadaServidor.temporadaActual();
    }

    // Función de diagnóstico
    static diagnosticar() {
        const temporada = CalculadoraInvierno.determinarTemporadaActual();

        console.group('❄️ DIAGNÓSTICO CALCULADORA INVIERNO');
        if (!temporada) {
            console.warn('Sin info_temporada del servidor. Cargá los datos primero.');
            console.groupEnd();
            return null;
        }

        const diasRestantes = TemporadaServidor.diasRestantes();
        const diasTotales = TemporadaServidor.diasTotales();

        console.log('Temporada actual:', temporada.codigo, `(${temporada.desde} a ${temporada.hasta})`);
        console.log('Días restantes / totales:', diasRestantes, '/', diasTotales);
        console.log('Períodos de la solapa invierno:', TemporadaServidor.periodos('invierno'));
        console.log('Lógica aplicada:',
            temporada.tipo === 'INVIERNO'
                ? 'Verano: Próximo completo, Invierno: Resto + Próximo completo'
                : 'Verano: Resto, Invierno: Próximo completo'
        );
        console.groupEnd();

        return { temporada, diasRestantes, diasTotales };
    }
}

// Hacer disponible globalmente
window.CalculadoraInvierno = CalculadoraInvierno;

// Función de diagnóstico global
window.diagnosticarInvierno = () => CalculadoraInvierno.diagnosticar();

console.log('✅ CalculadoraInvierno cargada (temporada calculada en el servidor)');