// Gestor para guardar el historial de presupuestos
// Archivo: presupuestos/js/historial-manager.js

class HistorialManager {

    /**
     * Transforma las claves de un objeto a minúsculas.
     * @param {object} obj El objeto a transformar.
     * @returns {object} Un nuevo objeto con las claves en minúsculas.
     */
    static transformarKeysAMinusculas(obj) {
        if (obj === null || typeof obj !== 'object') {
            return obj;
        }
        if (Array.isArray(obj)) {
            return obj.map(item => HistorialManager.transformarKeysAMinusculas(item));
        }
        return Object.keys(obj).reduce((acc, key) => {
            const lowerCaseKey = key.toLowerCase();
            acc[lowerCaseKey] = HistorialManager.transformarKeysAMinusculas(obj[key]);
            return acc;
        }, {});
    }

    /**
     * Guarda el estado actual del presupuesto proyectado.
     * @param {string} temporada La temporada a guardar ('verano' o 'invierno').
     */
    static async guardarPresupuesto(temporada) {
        if (!['verano', 'invierno'].includes(temporada)) {
            UIUtils.mostrarAlerta('Temporada no válida para guardar.', 'error');
            return;
        }

        UIUtils.mostrarLoading(true);

        try {
            // 1. Obtener los datos originales y filtrados
            const datosOriginales = window.presupuestoApp.getDatos(temporada);
            if (!datosOriginales || datosOriginales.length === 0) {
                throw new Error('No hay datos cargados para guardar.');
            }

            const datosFiltrados = FiltrosManager.filtrarDatos(datosOriginales);

            if (datosFiltrados.length === 0) {
                throw new Error('No hay datos visibles (filtrados) para guardar.');
            }

            // 2. Generar un nombre para el presupuesto
            const ahora = new Date();
            const timestamp = ahora.toISOString().slice(0, 19).replace('T', '_').replace(/:/g, '-');
            const nombrePresupuesto = `Presupuesto_${timestamp}_${temporada}`;

            // 3. Transformar las claves a minúsculas para el backend
            const filasParaGuardar = HistorialManager.transformarKeysAMinusculas(datosFiltrados);

            // 4. Preparar el payload
            const payload = {
                nombre_presupuesto: nombrePresupuesto,
                temporada: temporada,
                filas: filasParaGuardar
            };

            // 5. Llamar a la API
            const respuesta = await APIClient.llamarAPI('guardar-presupuesto', {}, 'POST', payload);

            if (respuesta.success) {
                UIUtils.mostrarAlerta(respuesta.message, 'success');
            } else {
                throw new Error(respuesta.message || 'Ocurrió un error desconocido al guardar.');
            }

        } catch (error) {
            console.error('Error al guardar el presupuesto:', error);
            UIUtils.mostrarAlerta(`Error: ${error.message}`, 'error');
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }
}

// Hacerlo disponible globalmente
window.HistorialManager = HistorialManager;
