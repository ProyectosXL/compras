// Gestor para guardar y ver el historial de presupuestos
// Archivo: presupuestos/js/historial-manager.js

class HistorialManager {

    /**
     * Inicializa el gestor, añadiendo event listeners delegados.
     */
    static init() {
        // Usar un listener en un contenedor padre que siempre exista
        document.body.addEventListener('click', (event) => {
            const target = event.target.closest('button');
            if (!target) return;

            if (target.id === 'btn-guardar-verano' || target.id === 'btn-guardar-invierno') {
                const temporada = target.dataset.temporada;
                if (temporada) {
                    HistorialManager.guardarPresupuesto(temporada);
                }
            }

            if (target.id === 'btn-buscar-historial') {
                HistorialManager.buscarHistorial();
            }
        });
    }

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
            const datosOriginales = window.presupuestoApp.getDatos(temporada);
            if (!datosOriginales || datosOriginales.length === 0) {
                throw new Error('No hay datos cargados para guardar.');
            }

            const datosFiltrados = FiltrosManager.filtrarDatos(datosOriginales);
            if (datosFiltrados.length === 0) {
                throw new Error('No hay datos visibles (filtrados) para guardar.');
            }

            const ahora = new Date();
            const pad = (num) => num.toString().padStart(2, '0');
            const fecha = `${ahora.getFullYear()}-${pad(ahora.getMonth() + 1)}-${pad(ahora.getDate())}`;
            const hora = `${pad(ahora.getHours())}-${pad(ahora.getMinutes())}`;
            const timestamp = `${fecha}_${hora}`;
            const nombrePresupuesto = `Presupuesto_${timestamp}_${temporada}`;
            const fechaParaGuardar = `${fecha} ${pad(ahora.getHours())}:${pad(ahora.getMinutes())}`;

            const filasParaGuardar = HistorialManager.transformarKeysAMinusculas(datosFiltrados);

            const payload = {
                nombre_presupuesto: nombrePresupuesto,
                temporada: temporada,
                filas: filasParaGuardar,
                fecha_guardado: fechaParaGuardar
            };

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

    /**
     * Busca en el historial de presupuestos y renderiza los resultados.
     */
    static async buscarHistorial() {
        UIUtils.mostrarLoading(true);
        try {
            const filtros = {
                termino: document.getElementById('search-historial').value,
                rubro: document.getElementById('filtro-rubro-historial').value,
                categoria: document.getElementById('filtro-categoria-historial').value,
                fecha_desde: document.getElementById('filtro-fecha-desde-historial').value,
                fecha_hasta: document.getElementById('filtro-fecha-hasta-historial').value
            };

            const respuesta = await APIClient.llamarAPI('buscar-historial', {}, 'POST', filtros);

            if (respuesta.success) {
                TablaRenderer.renderizarTablaHistorial(respuesta.data);
                UIUtils.mostrarAlerta(`${respuesta.data.length} registros de historial encontrados.`, 'success');
            } else {
                throw new Error(respuesta.message || 'Error al buscar en el historial.');
            }

        } catch (error) {
            console.error('Error al buscar historial:', error);
            UIUtils.mostrarAlerta(`Error: ${error.message}`, 'error');
            const tbody = document.getElementById('tbody-historial');
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${error.message}</td></tr>`;
            }
        } finally {
            UIUtils.mostrarLoading(false);
        }
    }
}

// Inicializar el gestor cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    HistorialManager.init();
});
