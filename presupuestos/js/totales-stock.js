
// AGREGAR AL FINAL DE totales-compra.js o crear nuevo archivo totales-stock.js

class TotalesStock {
    static datos = [];
    static datosFiltrados = [];

    /**
     * Actualizar datos para stock proyectado
     */
    static actualizarDatos(datos) {
        TotalesStock.datos = Array.isArray(datos) ? datos : [];
        TotalesStock.datosFiltrados = [...TotalesStock.datos];
        
        console.log('Datos stock actualizados:', TotalesStock.datos.length, 'registros');
        TotalesStock.calcularTotales();
    }

    /**
     * Aplicar filtros a los datos
     */
    static aplicarFiltros(datosFiltrados) {
        TotalesStock.datosFiltrados = Array.isArray(datosFiltrados) ? datosFiltrados : [];
        TotalesStock.calcularTotales();
    }

    /**
     * Calcular totales de stock proyectado
     */
    static calcularTotales() {
        try {
            const datos = TotalesStock.datosFiltrados || [];
            
            console.log(`📊 Calculando totales de stock:`, datos.length, 'registros');
            
            if (datos.length === 0) {
                TotalesStock.mostrarTotal({
                    stockActual: 0,
                    stockGuardar: 0,
                    comprasVerano: 0,
                    comprasInvierno: 0,
                    comprasAtemporal: 0,
                    stockCobertura: 0,
                    stockProyectado: 0,
                    cantidad: 0
                });
                return;
            }

            const totales = datos.reduce((acc, item) => {
                acc.stockActual += TotalesStock.obtenerValor(item, 'STOCK', 'CANT_STOCK');
                acc.stockGuardar += TotalesStock.obtenerValor(item, 'STOCK_GUARDAR', 'CANT_STOCK_GUARDAR');
                acc.comprasVerano += TotalesStock.obtenerValor(item, 'COMPRAS_VERANO', 'CANT_PEND_OC_VERANO');
                acc.comprasInvierno += TotalesStock.obtenerValor(item, 'COMPRAS_INVIERNO', 'CANT_PEND_OC_INVIERNO');
                acc.comprasAtemporal += TotalesStock.obtenerValor(item, 'COMPRAS_ATEMPORAL', 'CANT_PEND_OC_ATEMPORAL');
                acc.stockCobertura += TotalesStock.obtenerValor(item, 'STOCK_COBERTURA');
                acc.stockProyectado += TotalesStock.obtenerValor(item, 'STOCK_PROYECTADO');
                acc.cantidad++;
                return acc;
            }, {
                stockActual: 0,
                stockGuardar: 0,
                comprasVerano: 0,
                comprasInvierno: 0,
                comprasAtemporal: 0,
                stockCobertura: 0,
                stockProyectado: 0,
                cantidad: 0
            });

            console.log('🎯 Totales calculados:', totales);
            TotalesStock.mostrarTotal(totales);
            
        } catch (error) {
            console.error('Error calculando totales de stock:', error);
            TotalesStock.mostrarTotal({
                stockActual: 0, stockGuardar: 0, comprasVerano: 0,
                comprasInvierno: 0, comprasAtemporal: 0, stockCobertura: 0,
                stockProyectado: 0, cantidad: 0
            });
        }
    }

    /**
     * Obtener valor de un item con campos alternativos
     */
    static obtenerValor(item, campo1, campo2 = null) {
        const valor1 = parseFloat(item[campo1]) || 0;
        if (valor1 !== 0) return valor1;
        
        if (campo2) {
            const valor2 = parseFloat(item[campo2]) || 0;
            return valor2;
        }
        
        return 0;
    }

    /**
     * Mostrar totales en la interfaz
     */
    static mostrarTotal(totales) {
        // Agregar fila de totales al final de la tabla
        TotalesStock.agregarFilaTotales(totales);
        
        // Mostrar resumen en la parte superior
        TotalesStock.mostrarResumenSuperior(totales);
    }

    /**
     * Agregar fila de totales al final de la tabla
     */
    static agregarFilaTotales(totales) {
        const tbody = document.getElementById('tbody-stock');
        if (!tbody) return;

        // Remover fila de totales anterior si existe
        const filaAnterior = tbody.querySelector('.fila-totales');
        if (filaAnterior) {
            filaAnterior.remove();
        }

        // Crear nueva fila de totales
        const filaTotales = document.createElement('tr');
        filaTotales.className = 'fila-totales table-warning fw-bold';
        filaTotales.style.borderTop = '3px solid #ffc107';
        
        filaTotales.innerHTML = `
            <td class="fw-bold text-uppercase">TOTALES</td>
            <td class="text-center">${totales.cantidad} items</td>
            <td class="text-end bg-info-subtle fw-bold">${TotalesStock.formatearNumero(totales.stockActual)}</td>
            <td class="text-end bg-warning-subtle fw-bold">${TotalesStock.formatearNumero(totales.stockGuardar)}</td>
            <td class="text-end bg-success-subtle fw-bold">${TotalesStock.formatearNumero(totales.comprasVerano)}</td>
            <td class="text-end bg-success-subtle fw-bold">${TotalesStock.formatearNumero(totales.comprasInvierno)}</td>
            <td class="text-end bg-success-subtle fw-bold">${TotalesStock.formatearNumero(totales.comprasAtemporal)}</td>
            <td class="text-end bg-danger-subtle fw-bold">${TotalesStock.formatearNumero(totales.stockCobertura)}</td>
            <td class="text-end bg-primary-subtle fw-bold text-primary-emphasis">${TotalesStock.formatearNumero(totales.stockProyectado)}</td>
        `;

        tbody.appendChild(filaTotales);
    }

    /**
     * Mostrar resumen en la parte superior
     */
    static mostrarResumenSuperior(totales) {
        const containerId = 'total-stock-superior';
        let container = document.getElementById(containerId);

        // Crear el contenedor si no existe
        if (!container) {
            TotalesStock.crearContainerResumen();
            container = document.getElementById(containerId);
        }

        if (!container) {
            console.warn('No se pudo crear el contenedor de resumen de stock');
            return;
        }

        // Calcular totales de compras pendientes
        const totalComprasPendientes = totales.comprasVerano + totales.comprasInvierno + totales.comprasAtemporal;
        const incrementoStock = totales.stockProyectado - totales.stockActual;

        // Mismo componente que el resto de las solapas (ver UIUtils.resumenSuperior).
        container.innerHTML = UIUtils.resumenSuperior([
            { label: 'Stock actual', valor: TotalesStock.formatearNumero(totales.stockActual) },
            { label: 'OC pendientes', valor: TotalesStock.formatearNumero(totalComprasPendientes),
              ayuda: 'Unidades ya pedidas que todavía no ingresaron. Entran al stock '
                   + 'proyectado, así que la compra proyectada es neta de esto.' },
            { label: 'Stock proyectado', valor: TotalesStock.formatearNumero(totales.stockProyectado) },
            { label: 'Variación', fin: true,
              tono: incrementoStock >= 0 ? 'ok' : 'alerta',
              valor: (incrementoStock >= 0 ? '+' : '') + TotalesStock.formatearNumero(incrementoStock),
              ayuda: 'Stock proyectado menos stock actual.' }
        ]);

        // Mostrar el contenedor. Al aparecer, la tabla de abajo tiene menos alto.
        container.classList.remove('d-none');
        if (window.ajustarAltura) window.ajustarAltura();

        // Animación de actualización
        container.classList.add('actualizado');
        setTimeout(() => container.classList.remove('actualizado'), 500);
    }

    /**
     * Crear contenedor de resumen
     */
    static crearContainerResumen() {
        const tabPane = document.getElementById('stock');
        if (!tabPane) {
            console.error('No se encontró la solapa stock');
            return;
        }

        // Buscar el search-container para insertar después
        const searchContainer = tabPane.querySelector('.search-container');
        if (!searchContainer) {
            console.error('No se encontró search-container en stock');
            return;
        }

        // Crear el contenedor de resumen
        const resumenContainer = document.createElement('div');
        resumenContainer.id = 'total-stock-superior';
        resumenContainer.className = 'resumen-superior resumen-superior--stock d-none';

        // Insertar después del search-container
        searchContainer.parentNode.insertBefore(resumenContainer, searchContainer.nextSibling);
        
        console.log('Contenedor de resumen de stock creado');
    }

    /**
     * Formatear números
     */
    static formatearNumero(numero) {
        if (typeof FormatoUtils !== 'undefined' && FormatoUtils.formatearNumero) {
            return FormatoUtils.formatearNumero(numero);
        }
        return new Intl.NumberFormat('es-AR').format(numero);
    }

    /**
     * Limpiar totales
     */
    static limpiarTotales() {
        const container = document.getElementById('total-stock-superior');
        if (container) {
            container.classList.add('d-none');
            container.innerHTML = '';
        }
        
        const filaTotal = document.querySelector('#tbody-stock .fila-totales');
        if (filaTotal) {
            filaTotal.remove();
        }
        
        TotalesStock.datos = [];
        TotalesStock.datosFiltrados = [];
    }

    /**
     * Debug
     */
    static debug() {
        console.log('=== DEBUG TOTALES STOCK ===');
        console.log('Datos totales:', TotalesStock.datos?.length || 0);
        console.log('Datos filtrados:', TotalesStock.datosFiltrados?.length || 0);
        
        if (TotalesStock.datosFiltrados?.length > 0) {
            const primer = TotalesStock.datosFiltrados[0];
            console.log('Primer item:', primer);
            console.log('Campos disponibles:', Object.keys(primer));
        }
    }
}

// Hacer disponible globalmente
window.TotalesStock = TotalesStock;