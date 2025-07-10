
// Gestor de Totales de Compra Proyectada
// Archivo: presupuestos/js/totales-compra.js

class TotalesCompra {
    static datos = {
        verano: [],
        invierno: []
    };
    
    static datosFiltrados = {
        verano: [],
        invierno: []
    };

    /**
     * Actualizar datos para una solapa específica
     */
    static actualizarDatos(solapa, datos) {
        if (!['verano', 'invierno'].includes(solapa)) {
            console.error('Solapa no válida:', solapa);
            return;
        }

        TotalesCompra.datos[solapa] = Array.isArray(datos) ? datos : [];
        TotalesCompra.datosFiltrados[solapa] = [...TotalesCompra.datos[solapa]];
        
        console.log(`Datos ${solapa} actualizados:`, TotalesCompra.datos[solapa].length, 'registros');
        
        // Calcular y mostrar totales
        TotalesCompra.calcularTotales(solapa);
    }

    /**
     * Aplicar filtros a los datos
     */
    static aplicarFiltros(solapa, datosFiltrados) {
        if (!['verano', 'invierno'].includes(solapa)) return;

        TotalesCompra.datosFiltrados[solapa] = Array.isArray(datosFiltrados) ? datosFiltrados : [];
        TotalesCompra.calcularTotales(solapa);
    }

    /**
     * Calcular totales de compra proyectada (solo valores negativos) - MEJORADA
     */
    static calcularTotales(solapa) {
        try {
            const datos = TotalesCompra.datosFiltrados[solapa] || [];
            
            console.log(`📊 Calculando totales para ${solapa}:`, datos.length, 'registros');
            
            if (datos.length === 0) {
                TotalesCompra.mostrarTotal(solapa, 0, 0);
                return;
            }

            let totalNegativo = 0;
            let cantidadItems = 0;

            datos.forEach((item, index) => {
                // Buscar la columna de compra proyectada
                const compraProyectada = TotalesCompra.obtenerCompraProyectada(item);
                
                console.log(`Item ${index}: ${item.RUBRO} - ${item.CATEGORIA_PADRE} = ${compraProyectada}`);
                
                // Solo sumar si es negativo (necesita reposición)
                if (compraProyectada < 0) {
                    totalNegativo += Math.abs(compraProyectada); // Convertir a positivo para suma
                    cantidadItems++;
                    console.log(`✓ Agregado al total: ${Math.abs(compraProyectada)}`);
                }
            });

            console.log(`🎯 ${solapa} - Total negativo: ${totalNegativo}, Items: ${cantidadItems}`);
            
            TotalesCompra.mostrarTotal(solapa, totalNegativo, cantidadItems);
            
        } catch (error) {
            console.error('Error calculando totales:', error);
            TotalesCompra.mostrarTotal(solapa, 0, 0);
        }
    }

    /**
     * Obtener el valor de compra proyectada de un item (MEJORADA)
     */
    static obtenerCompraProyectada(item) {
        // Buscar diferentes posibles nombres de columna
        const posiblesCampos = [
            'COMPRA_PROYECTADA',
            'Compra Proyectada',
            'compra_proyectada',
            'COMPRA_PROY',
            'COMPRA'
        ];

        for (const campo of posiblesCampos) {
            if (item.hasOwnProperty(campo)) {
                const valor = parseFloat(item[campo]) || 0;
                console.log(`Campo encontrado: ${campo} = ${valor}`);
                return valor;
            }
        }

        // Si no encuentra el campo específico, buscar el último campo numérico
        const keys = Object.keys(item);
        
        // Buscar desde el final hacia atrás
        for (let i = keys.length - 1; i >= 0; i--) {
            const key = keys[i];
            const valor = item[key];
            
            // Verificar si es numérico y no es un campo de identificación
            if (!isNaN(parseFloat(valor)) && 
                !key.includes('INDICE') && 
                !key.includes('STOCK') &&
                !key.includes('VENTA') &&
                !key.includes('VTA') &&
                key !== 'CATEGORIA' &&
                key !== 'RUBRO') {
                
                console.log(`Último campo numérico encontrado: ${key} = ${valor}`);
                return parseFloat(valor) || 0;
            }
        }

        console.warn('No se pudo encontrar campo de compra proyectada en:', Object.keys(item));
        return 0;
    }

    /**
     * Mostrar el total en la interfaz
     */
    static mostrarTotal(solapa, total, cantidad) {
        const containerId = `total-compra-${solapa}`;
        let container = document.getElementById(containerId);

        // Crear el contenedor si no existe
        if (!container) {
            TotalesCompra.crearContainerTotal(solapa);
            container = document.getElementById(containerId);
        }

        if (!container) {
            console.warn('No se pudo crear el contenedor de totales para:', solapa);
            return;
        }

        // Formatear el total
        const totalFormateado = TotalesCompra.formatearNumero(total);
        const icono = solapa === 'verano' ? 'fa-sun' : 'fa-snowflake';
        const color = solapa === 'verano' ? 'warning' : 'info';

        // Actualizar contenido
        container.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas ${icono} me-2"></i>
                    <strong>Total Compra Proyectada:</strong>
                </div>
                <div>
                    <span class="badge bg-${color} fs-6 me-2">${cantidad} items</span>
                    <span class="badge bg-danger fs-5">${totalFormateado}</span>
                </div>
            </div>
        `;

        // Mostrar el contenedor
        container.classList.remove('d-none');
        
        // Animación de actualización
        container.classList.add('actualizado');
        setTimeout(() => container.classList.remove('actualizado'), 500);
    }

    /**
     * Crear el contenedor de totales en la interfaz
     */
    static crearContainerTotal(solapa) {
        const tabPane = document.getElementById(solapa);
        if (!tabPane) {
            console.error('No se encontró la solapa:', solapa);
            return;
        }

        // Buscar el search-container para insertar después
        const searchContainer = tabPane.querySelector('.search-container');
        if (!searchContainer) {
            console.error('No se encontró search-container en:', solapa);
            return;
        }

        // Crear el contenedor de totales
        const totalContainer = document.createElement('div');
        totalContainer.id = `total-compra-${solapa}`;
        totalContainer.className = 'bg-light p-2 border-bottom d-none total-compra-container';
        totalContainer.style.borderLeft = '4px solid #dc3545';

        // Insertar después del search-container
        searchContainer.parentNode.insertBefore(totalContainer, searchContainer.nextSibling);
        
        console.log('Contenedor de totales creado para:', solapa);
    }

    /**
     * Formatear números para mostrar
     */
    static formatearNumero(numero) {
        if (typeof FormatoUtils !== 'undefined' && FormatoUtils.formatearNumero) {
            return FormatoUtils.formatearNumero(numero);
        }
        
        // Fallback si FormatoUtils no está disponible
        return new Intl.NumberFormat('es-AR').format(numero);
    }

    /**
     * Manejar cambios de índice (cuando se edita un índice) - MEJORADA
     */
    static onIndiceActualizado(solapa, rubro, categoria, nuevoIndice) {
        try {
            console.log(`🔄 Índice actualizado en ${solapa}:`, rubro, categoria, nuevoIndice);
            
            // Obtener datos actualizados directamente del PresupuestoApp
            const datosActualizados = window.presupuestoApp?.datos?.[solapa] || [];
            
            if (datosActualizados.length === 0) {
                console.warn('No hay datos disponibles para actualizar totales');
                return;
            }
            
            // Actualizar datos filtrados con los nuevos valores
            TotalesCompra.datosFiltrados[solapa] = [...datosActualizados];
            
            // Aplicar filtros actuales si existen
            const inputBusqueda = document.getElementById(`search-${solapa}`);
            if (inputBusqueda && inputBusqueda.value.trim().length > 0) {
                const termino = inputBusqueda.value.trim().toLowerCase();
                TotalesCompra.datosFiltrados[solapa] = datosActualizados.filter(item => {
                    return TotalesCompra.cumpleFiltro(item, termino);
                });
            }
            
            // Recalcular totales
            TotalesCompra.calcularTotales(solapa);
            
            // Mostrar notificación
            if (typeof UIUtils !== 'undefined') {
                UIUtils.mostrarAlerta('Total actualizado por cambio de índice', 'info', 2000);
            }
            
        } catch (error) {
            console.error('Error manejando actualización de índice:', error);
        }
    }

    /**
     * Obtener el valor original de compra (antes del índice)
     */
    static obtenerCompraOriginal(item) {
        // Intentar obtener el valor base sin índice aplicado
        const posiblesCampos = [
            'COMPRA_BASE',
            'COMPRA_ORIGINAL',
            'VENTA_PROYECTADA', // A veces la compra se basa en la venta proyectada
            'STOCK_OBJETIVO'
        ];

        for (const campo of posiblesCampos) {
            if (item.hasOwnProperty(campo)) {
                return parseFloat(item[campo]) || 0;
            }
        }

        // Si no hay campo base, usar el actual dividido por el índice
        const compraActual = TotalesCompra.obtenerCompraProyectada(item);
        const indice = parseFloat(item.INDICE_VARIACION || item.INDICE || 1);
        
        return compraActual / indice;
    }

    /**
     * Actualizar el valor de compra proyectada en un item
     */
    static actualizarCompraProyectada(item, nuevoValor) {
        const posiblesCampos = [
            'COMPRA_PROYECTADA',
            'Compra Proyectada',
            'compra_proyectada'
        ];

        // Intentar actualizar en el campo conocido
        for (const campo of posiblesCampos) {
            if (item.hasOwnProperty(campo)) {
                item[campo] = nuevoValor;
                return;
            }
        }

        // Si no existe, crear el campo
        item['COMPRA_PROYECTADA'] = nuevoValor;
    }

    /**
     * Limpiar totales
     */
    static limpiarTotales(solapa = null) {
        const solapas = solapa ? [solapa] : ['verano', 'invierno'];
        
        solapas.forEach(s => {
            const container = document.getElementById(`total-compra-${s}`);
            if (container) {
                container.classList.add('d-none');
                container.innerHTML = '';
            }
            
            TotalesCompra.datos[s] = [];
            TotalesCompra.datosFiltrados[s] = [];
        });
    }

    /**
     * Obtener estadísticas de totales
     */
    static obtenerEstadisticas(solapa) {
        const datos = TotalesCompra.datosFiltrados[solapa] || [];
        
        let totalPositivo = 0;
        let totalNegativo = 0;
        let itemsPositivos = 0;
        let itemsNegativos = 0;

        datos.forEach(item => {
            const compra = TotalesCompra.obtenerCompraProyectada(item);
            if (compra > 0) {
                totalPositivo += compra;
                itemsPositivos++;
            } else if (compra < 0) {
                totalNegativo += Math.abs(compra);
                itemsNegativos++;
            }
        });

        return {
            totalPositivo,
            totalNegativo,
            itemsPositivos,
            itemsNegativos,
            totalItems: datos.length
        };
    }

    /**
     * NUEVA: Verificar si un item cumple con el filtro de búsqueda
     */
    static cumpleFiltro(item, termino) {
        const campos = ['RUBRO', 'CATEGORIA', 'CATEGORIA_PADRE', 'DESCRIPCION'];
        
        return campos.some(campo => {
            const valor = item[campo];
            return valor && valor.toString().toLowerCase().includes(termino);
        });
    }

    /**
     * Debug y diagnóstico
     */
    static debug(solapa = null) {
        const solapas = solapa ? [solapa] : ['verano', 'invierno'];
        
        console.log('=== DEBUG TOTALES COMPRA ===');
        
        solapas.forEach(s => {
            console.log(`\n--- ${s.toUpperCase()} ---`);
            console.log('Datos totales:', TotalesCompra.datos[s]?.length || 0);
            console.log('Datos filtrados:', TotalesCompra.datosFiltrados[s]?.length || 0);
            
            if (TotalesCompra.datosFiltrados[s]?.length > 0) {
                const primer = TotalesCompra.datosFiltrados[s][0];
                console.log('Primer item:', primer);
                console.log('Compra proyectada:', TotalesCompra.obtenerCompraProyectada(primer));
                console.log('Campos disponibles:', Object.keys(primer));
            }
            
            const stats = TotalesCompra.obtenerEstadisticas(s);
            console.log('Estadísticas:', stats);
        });
    }

    /**
     * Debug mejorado para ver estructura de datos
     */
    static debugDetallado(solapa = 'verano') {
        console.group(`🔍 DEBUG DETALLADO - ${solapa.toUpperCase()}`);
        
        const datos = TotalesCompra.datosFiltrados[solapa] || [];
        
        console.log('Total registros:', datos.length);
        
        if (datos.length > 0) {
            const primer = datos[0];
            console.log('Estructura del primer registro:');
            console.table(primer);
            
            console.log('Campos disponibles:', Object.keys(primer));
            
            // Buscar campos numéricos
            const camposNumericos = Object.keys(primer).filter(key => {
                const valor = primer[key];
                return !isNaN(parseFloat(valor)) && isFinite(valor);
            });
            
            console.log('Campos numéricos encontrados:', camposNumericos);
            
            // Probar detectar compra proyectada en varios registros
            console.log('🔍 Detección de compra proyectada:');
            datos.slice(0, 5).forEach((item, index) => {
                const compra = TotalesCompra.obtenerCompraProyectada(item);
                console.log(`Item ${index}: ${item.RUBRO} -> ${compra}`);
            });
        }
        
        console.groupEnd();
    }
}

// Hacer disponible globalmente
window.TotalesCompra = TotalesCompra;

// Funciones globales para compatibilidad
window.actualizarTotalesCompra = function(solapa, datos) {
    TotalesCompra.actualizarDatos(solapa, datos);
};

window.aplicarFiltrosTotales = function(solapa, datosFiltrados) {
    TotalesCompra.aplicarFiltros(solapa, datosFiltrados);
};

window.onIndiceActualizado = function(solapa, rubro, categoria, nuevoIndice) {
    TotalesCompra.onIndiceActualizado(solapa, rubro, categoria, nuevoIndice);
};