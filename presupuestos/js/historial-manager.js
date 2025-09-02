// Gestor para guardar y ver el historial de presupuestos
// Archivo: presupuestos/js/historial-manager.js

class HistorialManager {

    /**
     * Inicializa el gestor, añadiendo event listeners delegados.
     */
    static init() {
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

            // FIX: Construir explícitamente el objeto a guardar para asegurar todos los campos.
            const filasParaGuardar = datosFiltrados.map(item => {
                // Re-calculamos o extraemos los valores tal como se muestran en la tabla.
                const ventaVeranoAnterior = TablaRendererUtils.buscarVentaHistoricaCorrecta(item, 'VERANO');
                const ventaInviernoAnterior = TablaRendererUtils.buscarVentaHistoricaCorrecta(item, 'INVIERNO');

                return {
                    rubro: item.RUBRO,
                    categoria_padre: item.CATEGORIA_PADRE,
                    stock_proyectado: item.STOCK_PROYECTADO || 0,
                    indice_variacion_original: TablaRendererUtils.obtenerIndiceOriginal(item),
                    indice_verano_variacion: parseFloat(item.INDICE_VARIACION || 1.0),
                    venta_verano_anterior: ventaVeranoAnterior,
                    venta_proyectada_verano: item.VENTA_PROY_VERANO || 0,
                    indice_invierno_variacion: parseFloat(item.INDICE_VARIACION_INVIERNO || item.INDICE_VARIACION || 1.0),
                    venta_invierno_anterior: ventaInviernoAnterior,
                    venta_proyectada_invierno: item.VENTA_PROY_INVIERNO || 0,
                    compra_proyectada: item.COMPRA_PROYECTADA || 0
                };
            });

            const payload = {
                nombre_presupuesto: nombrePresupuesto,
                temporada: temporada,
                filas: filasParaGuardar, // Ya tiene las keys en minúscula y todos los datos.
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
