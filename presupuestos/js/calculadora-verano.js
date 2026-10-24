// Calculadora para COMPRA PROYECTADA VERANO
// Archivo: presupuestos/js/calculadora-verano.js
//
// El calendario de temporadas (qué temporada corre, días totales y restantes) lo
// calcula el servidor y llega por TemporadaServidor. Acá quedó solo la fórmula.
// Antes esta clase repetía las cuentas de fecha con el reloj del navegador —incluso
// con calcularDiasRestantesVerano definida dos veces, ganando la segunda— y el
// resultado se separaba del que había calculado PHP al renderizar.

class CalculadoraVerano {

    /**
     * CORREGIDO: Calcular ventas proyectadas usando tu fórmula exacta
     */
    static calcularVentasProyectadas(ventaVeranoAnterior, ventaInviernoAnterior, indiceVerano, indiceInvierno, temporadaActual) {
        console.log('🔥 CALCULADORA VERANO - Iniciando cálculo');
        console.log(`Temporada actual: ${temporadaActual.tipo} (${temporadaActual.enCurso ? 'en curso' : 'no en curso'})`);
        
        let ventaProyVerano, ventaProyInvierno;
        
        if (temporadaActual.tipo === 'VERANO' && temporadaActual.enCurso) {
            // Contexto: Transitando verano
            // • Venta Proy. Verano: resto del verano en curso + próximo verano completo
            // • Venta Proy. Invierno: Próximo Invierno Completo

            // Días del servidor: son los mismos que usó PHP para el render inicial.
            const diasRestantesVerano = TemporadaServidor.diasRestantes();
            const diasTotalesVerano = TemporadaServidor.diasTotales();

            // Se replica el orden de operaciones de PHP (venta * indice * proporcion)
            // para que el redondeo caiga igual y editar un índice con el mismo valor
            // devuelva exactamente el número que ya estaba en pantalla.
            const veranoActualProporcional = Math.round(
                ventaVeranoAnterior * indiceVerano * (diasRestantesVerano / diasTotalesVerano)
            );
            const proximoVeranoCompleto = Math.round(ventaVeranoAnterior * indiceVerano);
            
            ventaProyVerano = veranoActualProporcional + proximoVeranoCompleto;
            ventaProyInvierno = Math.round(ventaInviernoAnterior * indiceInvierno); // Próximo invierno completo
            
            console.log(`VERANO (Transitando): Proporcional (${ventaVeranoAnterior}/${diasTotalesVerano}*${diasRestantesVerano}*${indiceVerano} = ${veranoActualProporcional}) + Próximo completo (${proximoVeranoCompleto}) = ${ventaProyVerano}`);
            console.log(`INVIERNO (Transitando): Próximo completo = ${ventaProyInvierno}`);
            
        } else if (temporadaActual.tipo === 'INVIERNO' && temporadaActual.enCurso) {
            // Contexto: Transitando invierno
            // • Venta Proy. Verano: Próximo Verano Completo
            // • Venta Proy. Invierno: FÓRMULA CORREGIDA
            
            ventaProyVerano = Math.round(ventaVeranoAnterior * indiceVerano); // Próximo verano completo

            const diasRestantesInvierno = TemporadaServidor.diasRestantes();
            const diasTotalesInvierno = TemporadaServidor.diasTotales();

            ventaProyInvierno = Math.round(
                ventaInviernoAnterior * indiceInvierno * (diasRestantesInvierno / diasTotalesInvierno)
            );
            
            console.log(`VERANO (No transitando): Próximo completo = ${ventaProyVerano}`);
            console.log(`INVIERNO (Transitando): Proporcional (${ventaInviernoAnterior}/${diasTotalesInvierno}*${diasRestantesInvierno}*${indiceInvierno} = ${ventaProyInvierno})`);
            
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
        console.log('🔥 VERANO - Actualizando datos con nuevo índice:', nuevoIndice);
        console.log('🔥 VERANO - Temporada editada:', indiceEditando.temporada);
        
        // CORRECCIÓN: NO leer de la tabla, usar los datos originales directamente
        console.log(`🔥 VERANO - Índices recibidos: V=${registro.INDICE_VARIACION}, I=${registro.INDICE_VARIACION_INVIERNO || registro.INDICE_VARIACION}`);
        
        // OBTENER VENTAS ANTERIORES DIRECTAMENTE DE LOS DATOS ORIGINALES
        const ventaVeranoAnterior = CalculadoraVerano.extraerVentaAnteriorDeRegistro(registro, 'VERANO');
        const ventaInviernoAnterior = CalculadoraVerano.extraerVentaAnteriorDeRegistro(registro, 'INVIERNO');
        
        console.log(`🔥 VERANO - Ventas desde datos originales: V=${ventaVeranoAnterior}, I=${ventaInviernoAnterior}`);
        
        // USAR LOS ÍNDICES QUE VIENEN EN EL REGISTRO (ya están correctamente actualizados)
        const indiceVerano = parseFloat(registro.INDICE_VARIACION || 1.0);
        const indiceInvierno = parseFloat(registro.INDICE_VARIACION_INVIERNO || indiceVerano);
        
        console.log(`🔥 VERANO - Índices a usar para cálculo: V=${indiceVerano}, I=${indiceInvierno}`);
        
        // Temporada del servidor. Si no llegó, no se recalcula: devolver el registro
        // intacto es preferible a producir un número que no coincida con el del render.
        const temporadaActual = CalculadoraVerano.determinarTemporadaActual();
        if (!temporadaActual) {
            TemporadaServidor.advertirNoDisponible('CalculadoraVerano');
            return registro;
        }

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
        
        // Crear registro actualizado conservando TODOS los índices originales
        const registroActualizado = {
            ...registro,
            VENTA_PROY_VERANO: ventaProyVerano,
            VENTA_PROY_INVIERNO: ventaProyInvierno,
            COMPRA_PROYECTADA: compraProyectada
        };
        
        console.log(`🔥 VERANO - RESULTADO: Stock: ${stockProyectado}, Venta V: ${ventaProyVerano}, Venta I: ${ventaProyInvierno}, Compra: ${compraProyectada}`);
        
        return registroActualizado;
    }

    /**
     * Venta anterior sobre la que proyecta esta fila.
     *
     * Delega en TemporadaServidor: la regla de qué columna es una venta histórica y
     * cuál es la más reciente la define el servidor y hay UNA sola implementación.
     * Acá vivía una copia que aceptaba cualquier columna con VERANO/INVIERNO en el
     * nombre y ordenaba por los dos primeros dígitos, así que en las filas sin
     * ventas de la temporada terminaba proyectando sobre CANT_PEND_OC_*.
     */
    static extraerVentaAnteriorDeRegistro(registro, temporada) {
        return TemporadaServidor.ventaAnteriorDe(registro, temporada);
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
     * Escribe en la fila de la tabla los valores recalculados de un registro.
     *
     * Delega en TablaRendererUtils, que ubica las celdas por data-col. Acá vivía
     * una copia que las ubicaba por número de columna (celdas[4], [6], [10]...), y
     * otra que leía las ventas anteriores parseando el texto de la celda: agregar
     * la columna del índice Total Empresa las habría corrido a todas.
     */
    static actualizarFilaTabla(indiceEditando, registro) {
        const fila = TablaRendererUtils.filaDeRegistro(
            indiceEditando.solapa, registro.RUBRO, registro.CATEGORIA_PADRE);
        TablaRendererUtils.actualizarCeldasCalculadas(fila, registro);
    }

    /**
     * Temporada en curso, según el servidor.
     * Ya no se deduce del mes con `new Date()`: esa deducción era la que podía
     * discrepar del render inicial cuando el reloj del cliente estaba corrido.
     */
    static determinarTemporadaActual() {
        return TemporadaServidor.temporadaActual();
    }

    // Función de diagnóstico
    static diagnosticar() {
        const temporada = CalculadoraVerano.determinarTemporadaActual();

        console.group('🔥 DIAGNÓSTICO CALCULADORA VERANO');
        if (!temporada) {
            console.warn('Sin info_temporada del servidor. Cargá los datos primero.');
            console.groupEnd();
            return null;
        }

        const diasRestantes = TemporadaServidor.diasRestantes();
        const diasTotales = TemporadaServidor.diasTotales();

        console.log('Temporada actual:', temporada.codigo, `(${temporada.desde} a ${temporada.hasta})`);
        console.log('Días restantes / totales:', diasRestantes, '/', diasTotales);
        console.log('Períodos de la solapa verano:', TemporadaServidor.periodos('verano'));
        console.log('Lógica aplicada:',
            temporada.tipo === 'VERANO'
                ? 'Verano: Resto + Próximo completo, Invierno: Próximo completo'
                : 'Verano: Próximo completo, Invierno: Resto'
        );
        console.groupEnd();

        return { temporada, diasRestantes, diasTotales };
    }
}

// Hacer disponible globalmente
window.CalculadoraVerano = CalculadoraVerano;

// Función de diagnóstico global
window.diagnosticarVerano = () => CalculadoraVerano.diagnosticar();

console.log('✅ CalculadoraVerano cargada (temporada calculada en el servidor)');