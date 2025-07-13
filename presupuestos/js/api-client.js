
// Cliente API para comunicación con el backend
// Archivo: presupuestos/js/api-client.js

class APIClient {
    static baseUrl = 'api.php';

    /**
     * Realizar llamada genérica a la API
     */
    static async llamarAPI(accion, parametros = {}, metodo = 'GET', body = null) {
        try {
            const url = new URL(APIClient.baseUrl, window.location.origin + window.location.pathname.replace('index.php', ''));
            url.searchParams.append('accion', accion);
            
            Object.keys(parametros).forEach(key => {
                url.searchParams.append(key, parametros[key]);
            });

            const options = {
                method: metodo,
                headers: {
                    'Content-Type': 'application/json',
                }
            };

            if (body && metodo !== 'GET') {
                options.body = JSON.stringify(body);
            }

            const response = await fetch(url, options);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Error en la respuesta del servidor');
            }

            return data;
        } catch (error) {
            console.error('Error en llamada API:', error);
            throw error;
        }
    }

    /**
     * Obtener datos base del presupuesto
     */
    static async obtenerDatosBase() {
        return await APIClient.llamarAPI('datos-base');
    }

    /**
     * Obtener datos de compra proyectada verano
     */
    static async obtenerCompraVerano() {
        return await APIClient.llamarAPI('compra-verano');
    }

    /**
     * Obtener datos de compra proyectada invierno
     */
    static async obtenerCompraInvierno() {
        return await APIClient.llamarAPI('compra-invierno');
    }

    /**
     * Obtener datos de stock proyectado
     */
    static async obtenerStockProyectado() {
        return await APIClient.llamarAPI('stock-proyectado');
    }

    /**
     * Buscar datos por término
     */
    static async buscarDatos(termino, solapa) {
        return await APIClient.llamarAPI('buscar', { q: termino, solapa: solapa });
    }

    /**
     * Obtener lista de rubros únicos
     */
    static async obtenerRubros() {
        return await APIClient.llamarAPI('rubros');
    }

    /**
     * Filtrar por rubro específico
     */
    static async filtrarPorRubro(rubro, solapa) {
        return await APIClient.llamarAPI('filtrar-rubro', { rubro: rubro, solapa: solapa });
    }

    /**
     * Actualizar índice de variación
     */
    static async actualizarIndice(rubro, categoria, indice, solapa) {
        return await APIClient.llamarAPI('actualizar-indice', {}, 'POST', {
            rubro: rubro,
            categoria: categoria,
            indice: indice,
            solapa: solapa
        });
    }

    /**
     * Actualizar múltiples índices
     */
    static async actualizarMultiplesIndices(actualizaciones, solapa) {
        return await APIClient.llamarAPI('actualizar-multiples-indices', {}, 'POST', {
            actualizaciones: actualizaciones,
            solapa: solapa
        });
    }

    /**
     * Resetear índices a valor por defecto
     */
    static async resetearIndices(valorDefecto = 1.0, filtros = {}) {
        return await APIClient.llamarAPI('resetear-indices', {}, 'POST', {
            valor_defecto: valorDefecto,
            filtros: filtros
        });
    }

    /**
     * Obtener estadísticas de índices
     */
    static async obtenerEstadisticasIndices() {
        return await APIClient.llamarAPI('estadisticas-indices');
    }

    /**
     * Exportar a Excel (abre en nueva ventana)
     */
    static async exportarExcel(solapa) {
        const url = `${APIClient.baseUrl}?accion=exportar&solapa=${solapa}`;
        window.open(url, '_blank');
    }

    /**
     * Probar conexión (funcionalidad de debugging)
     */
    static async probarConexion() {
        try {
            // Hacer una llamada simple para verificar conectividad
            const response = await fetch(APIClient.baseUrl + '?accion=datos-base');
            return {
                success: response.ok,
                status: response.status,
                statusText: response.statusText
            };
        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Exportar a Excel usando el nuevo sistema unificado
     */
    static async exportarExcel(solapa) {
        try {
            if (solapa === 'completo') {
                await ExcelExporter.exportarPresupuestoCompleto();
            } else {
                await ExcelExporter.exportarSolapa(solapa);
            }
        } catch (error) {
            console.error('Error en exportación:', error);
            throw error;
        }
    }

    /**
     * Exportar datos a CSV (función auxiliar)
     */
    static exportarCSV(datos, nombreArchivo, headers = null) {
        try {
            if (!datos || datos.length === 0) {
                throw new Error('No hay datos para exportar');
            }

            // Usar headers proporcionados o extraer del primer objeto
            const columnHeaders = headers || Object.keys(datos[0]);
            
            // Crear contenido CSV
            const csvContent = [
                columnHeaders.join(','),
                ...datos.map(row => 
                    columnHeaders.map(header => {
                        const value = row[header] || '';
                        // Escapar comillas y envolver en comillas si contiene comas
                        if (typeof value === 'string' && (value.includes(',') || value.includes('"'))) {
                            return '"' + value.replace(/"/g, '""') + '"';
                        }
                        return value;
                    }).join(',')
                )
            ].join('\n');

            // Crear y descargar archivo
            const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', nombreArchivo || 'exportacion.csv');
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Limpiar URL
            URL.revokeObjectURL(url);
            
            return true;
        } catch (error) {
            console.error('Error exportando CSV:', error);
            throw error;
        }
    }

    /**
     * Manejar errores de red de forma centralizada
     */
    static manejarErrorRed(error) {
        console.error('Error de red:', error);
        
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            return 'Error de conectividad. Verifique su conexión a internet.';
        }
        
        if (error.message.includes('404')) {
            return 'Endpoint no encontrado. Verifique la configuración del servidor.';
        }
        
        if (error.message.includes('500')) {
            return 'Error interno del servidor. Contacte al administrador.';
        }
        
        return error.message || 'Error desconocido en la comunicación con el servidor.';
    }

    /**
     * Configurar interceptores de respuesta (para manejo global de errores)
     */
    static configurarInterceptores() {
        // Interceptar respuestas globalmente si es necesario
        const originalFetch = window.fetch;
        
        window.fetch = async function(...args) {
            try {
                const response = await originalFetch.apply(this, args);
                
                // Log para debugging en desarrollo
                if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                    console.log(`API Call: ${args[0]} - Status: ${response.status}`);
                }
                
                return response;
            } catch (error) {
                console.error('Intercepted fetch error:', error);
                throw error;
            }
        };
    }

    /**
     * Validar respuesta de API
     */
    static validarRespuesta(response) {
        if (!response || typeof response !== 'object') {
            throw new Error('Respuesta inválida del servidor');
        }
        
        if (!response.hasOwnProperty('success')) {
            throw new Error('Formato de respuesta incorrecto');
        }
        
        if (!response.success && !response.message) {
            throw new Error('Error del servidor sin mensaje descriptivo');
        }
        
        return true;
    }

    /**
     * Cache simple para optimizar llamadas repetidas
     */
    static cache = new Map();
    static CACHE_DURATION = 5 * 60 * 1000; // 5 minutos

    /**
     * Llamar API con cache
     */
    static async llamarAPIConCache(accion, parametros = {}, usarCache = true) {
        const cacheKey = `${accion}_${JSON.stringify(parametros)}`;
        
        if (usarCache && APIClient.cache.has(cacheKey)) {
            const cached = APIClient.cache.get(cacheKey);
            if (Date.now() - cached.timestamp < APIClient.CACHE_DURATION) {
                console.log(`Cache hit para: ${accion}`);
                return cached.data;
            } else {
                APIClient.cache.delete(cacheKey);
            }
        }
        
        const response = await APIClient.llamarAPI(accion, parametros);
        
        if (usarCache && response.success) {
            APIClient.cache.set(cacheKey, {
                data: response,
                timestamp: Date.now()
            });
        }
        
        return response;
    }

    /**
     * Limpiar cache
     */
    static limpiarCache() {
        APIClient.cache.clear();
        console.log('Cache limpiado');
    }

    /**
     * Obtener estadísticas del cache
     */
    static obtenerEstadisticasCache() {
        const now = Date.now();
        let validas = 0;
        let expiradas = 0;
        
        APIClient.cache.forEach((value) => {
            if (now - value.timestamp < APIClient.CACHE_DURATION) {
                validas++;
            } else {
                expiradas++;
            }
        });
        
        return {
            total: APIClient.cache.size,
            validas: validas,
            expiradas: expiradas,
            tamaño_mb: (JSON.stringify([...APIClient.cache]).length / 1024 / 1024).toFixed(2)
        };
    }

    /**
     * Obtener datos de ventas de 6 meses
     */
    static async obtenerVentas6Meses() {
        return await APIClient.llamarAPI('ventas-6-meses');
    }

    /**
     * Buscar en ventas de 6 meses
     */
    static async buscarVentas6Meses(termino, filtros = {}) {
        const params = { q: termino, ...filtros };
        return await APIClient.llamarAPI('buscar-ventas', params);
    }

    /**
     * Obtener rubros de ventas
     */
    static async obtenerRubrosVentas() {
        return await APIClient.llamarAPI('rubros-ventas');
    }
    }

// Configurar interceptores al cargar
document.addEventListener('DOMContentLoaded', function() {
    APIClient.configurarInterceptores();
});

// Limpiar cache expirado cada 10 minutos
setInterval(() => {
    const now = Date.now();
    for (const [key, value] of APIClient.cache.entries()) {
        if (now - value.timestamp >= APIClient.CACHE_DURATION) {
            APIClient.cache.delete(key);
        }
    }
}, 10 * 60 * 1000);