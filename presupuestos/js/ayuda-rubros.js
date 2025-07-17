
/**
 * JavaScript para la funcionalidad del modal de ayuda del sistema de rubros
 * Archivo: ayuda-rubros.js
 */

// Objeto principal para manejar la ayuda de rubros
const AyudaRubros = {
    // Configuración inicial
    init: function() {
        console.log('Inicializando sistema de ayuda de rubros...');
        this.setupEventListeners();
        this.setupModalEvents();
        this.loadDynamicContent();
        this.setupTooltips();
    },

    // Configurar event listeners
    setupEventListeners: function() {
        // Listener para el botón de ayuda
        const btnAyuda = document.querySelector('[data-bs-target="#ayudaRubrosModal"]');
        if (btnAyuda) {
            btnAyuda.addEventListener('click', (e) => {
                this.abrirModal();
            });
        }

        // Listeners para las pestañas
        const tabs = document.querySelectorAll('#ayudaTabs button[data-bs-toggle="tab"]');
        tabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', (e) => {
                this.onTabChanged(e.target);
            });
        });

        // Listener para el botón de imprimir
        const btnImprimir = document.querySelector('#ayudaRubrosModal .btn-primary');
        if (btnImprimir) {
            btnImprimir.addEventListener('click', (e) => {
                this.imprimirAyuda();
            });
        }
    },

    // Configurar eventos del modal
    setupModalEvents: function() {
        const modal = document.getElementById('ayudaRubrosModal');
        if (modal) {
            modal.addEventListener('show.bs.modal', (e) => {
                this.onModalShow();
            });

            modal.addEventListener('hidden.bs.modal', (e) => {
                this.onModalHide();
            });
        }
    },

    // Configurar tooltips
    setupTooltips: function() {
        // Inicializar tooltips de Bootstrap en elementos del modal
        const tooltipTriggerList = document.querySelectorAll('#ayudaRubrosModal [data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(tooltipTriggerEl => {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    },

    // Cargar contenido dinámico
    loadDynamicContent: function() {
        // Cargar ejemplos actualizados si hay datos disponibles
        this.updateExamples();
        
        // Cargar parámetros actuales del sistema
        this.loadCurrentParameters();
    },

    // Abrir el modal
    abrirModal: function() {
        console.log('Abriendo modal de ayuda...');
        
        // Activar la primera pestaña por defecto
        const firstTab = document.querySelector('#overview-tab');
        if (firstTab) {
            const tab = new bootstrap.Tab(firstTab);
            tab.show();
        }

        // Registrar evento de apertura
        this.trackEvent('ayuda_modal_abierto');
    },

    // Evento cuando se muestra el modal
    onModalShow: function() {
        console.log('Modal de ayuda mostrado');
        
        // Enfocar el contenido para accesibilidad
        document.querySelector('#ayudaRubrosModal .modal-body').focus();
        
        // Animar entrada del contenido
        this.animateContent();
    },

    // Evento cuando se oculta el modal
    onModalHide: function() {
        console.log('Modal de ayuda ocultado');
        
        // Limpiar cualquier estado temporal
        this.clearTemporaryState();
    },

    // Evento cuando cambia de pestaña
    onTabChanged: function(tabElement) {
        const tabId = tabElement.getAttribute('aria-controls');
        console.log('Pestaña cambiada a:', tabId);
        
        // Animar contenido de la nueva pestaña
        this.animateTabContent(tabId);
        
        // Registrar evento
        this.trackEvent('ayuda_tab_cambiado', { tab: tabId });
        
        // Cargar contenido específico de la pestaña si es necesario
        this.loadTabContent(tabId);
    },

    // Actualizar ejemplos con datos reales
    updateExamples: function() {
        // Si hay datos disponibles del sistema, actualizar los ejemplos
        if (typeof window.datosRubros !== 'undefined') {
            this.updateExampleWithRealData(window.datosRubros);
        }
    },

    // Actualizar ejemplo con datos reales
    updateExampleWithRealData: function(datos) {
        const exampleContainer = document.querySelector('#ejemplos .card-body');
        if (!exampleContainer || !datos) return;

        // Tomar el primer rubro como ejemplo
        const rubros = Object.keys(datos);
        if (rubros.length === 0) return;

        const primerRubro = datos[rubros[0]];
        
        // Actualizar tabla de datos de entrada
        this.updateExampleInput(primerRubro);
        
        // Actualizar cálculos
        this.updateExampleCalculations(primerRubro);
    },

    // Actualizar datos de entrada del ejemplo
    updateExampleInput: function(rubro) {
        const inputTable = document.querySelector('#ejemplos .card-body table');
        if (!inputTable) return;

        const rows = inputTable.querySelectorAll('tr');
        if (rows.length >= 9) {
            rows[0].cells[1].innerHTML = `<strong>${rubro.stock || 0} unidades</strong>`;
            rows[1].cells[1].innerHTML = `<strong>${rubro.stock_guardar || 0} unidades</strong>`;
            rows[2].cells[1].innerHTML = `<strong>${rubro.compras_verano || 0} unidades</strong>`;
            rows[3].cells[1].innerHTML = `<strong>${rubro.compras_invierno || 0} unidades</strong>`;
            rows[4].cells[1].innerHTML = `<strong>${rubro.compras_atemporal || 0} unidades</strong>`;
            rows[5].cells[1].innerHTML = `<strong>${rubro.ventas_verano_anterior || 0} unidades</strong>`;
            rows[6].cells[1].innerHTML = `<strong>${rubro.ventas_invierno_anterior || 0} unidades</strong>`;
            rows[7].cells[1].innerHTML = `<strong>${rubro.indice_variacion || 1} (${((rubro.indice_variacion - 1) * 100).toFixed(1)}%)</strong>`;
            rows[8].cells[1].innerHTML = `<strong>${rubro.promedio_venta_mensual || 0} unidades/mes</strong>`;
        }
    },

    // Actualizar cálculos del ejemplo
    updateExampleCalculations: function(rubro) {
        const calculationsContainer = document.querySelector('#ejemplos .step-calculation');
        if (!calculationsContainer) return;

        const stockReserva = (rubro.promedio_venta_mensual || 0) * 1.5;
        const stockProyectado = (rubro.stock || 0) + (rubro.compras_verano || 0) + 
                               (rubro.compras_invierno || 0) + (rubro.compras_atemporal || 0) + 
                               (rubro.stock_guardar || 0) - stockReserva;
        const ventasVeranoProyectadas = (rubro.ventas_verano_anterior || 0) * (rubro.indice_variacion || 1);
        const ventasInviernoProjecdatas = (rubro.ventas_invierno_anterior || 0) * (rubro.indice_variacion || 1);
        const compraProyectada = stockProyectado - ventasVeranoProyectadas - ventasInviernoProjecdatas;

        // Actualizar los valores calculados
        const calculations = calculationsContainer.querySelectorAll('p');
        if (calculations.length >= 4) {
            calculations[0].innerHTML = `<code>${rubro.promedio_venta_mensual || 0} × 1.5 = ${stockReserva.toFixed(0)} unidades</code>`;
            calculations[1].innerHTML = `<code>${rubro.stock || 0} + ${rubro.compras_verano || 0} + ${rubro.compras_invierno || 0} + ${rubro.compras_atemporal || 0} + ${rubro.stock_guardar || 0} - ${stockReserva.toFixed(0)} = ${stockProyectado.toFixed(0)} unidades</code>`;
            calculations[2].innerHTML = `<strong>Verano:</strong> <code>${rubro.ventas_verano_anterior || 0} × ${rubro.indice_variacion || 1} = ${ventasVeranoProyectadas.toFixed(0)} unidades</code>`;
            calculations[3].innerHTML = `<strong>Invierno:</strong> <code>${rubro.ventas_invierno_anterior || 0} × ${rubro.indice_variacion || 1} = ${ventasInviernoProjecdatas.toFixed(0)} unidades</code>`;
        }

        // Actualizar el resultado final
        const resultAlert = calculationsContainer.querySelector('.alert-success');
        if (resultAlert) {
            if (compraProyectada > 0) {
                resultAlert.className = 'alert alert-success mt-3';
                resultAlert.innerHTML = `<strong>Resultado:</strong> Exceso de ${compraProyectada.toFixed(0)} unidades. No se requiere compra adicional.`;
            } else if (compraProyectada < 0) {
                resultAlert.className = 'alert alert-danger mt-3';
                resultAlert.innerHTML = `<strong>Resultado:</strong> Déficit de ${Math.abs(compraProyectada).toFixed(0)} unidades. Se requiere compra adicional.`;
            } else {
                resultAlert.className = 'alert alert-warning mt-3';
                resultAlert.innerHTML = `<strong>Resultado:</strong> Stock equilibrado. No se requiere ajuste.`;
            }
        }
    },

    // Cargar parámetros actuales del sistema
    loadCurrentParameters: function() {
        // Aquí se puede integrar con el sistema para obtener parámetros actuales
        console.log('Cargando parámetros actuales del sistema...');
    },

    // Cargar contenido específico de pestaña
    loadTabContent: function(tabId) {
        switch (tabId) {
            case 'calculos':
                this.loadCalculationsContent();
                break;
            case 'parametros':
                this.loadParametersContent();
                break;
            case 'ejemplos':
                this.loadExamplesContent();
                break;
            default:
                break;
        }
    },

    // Cargar contenido de cálculos
    loadCalculationsContent: function() {
        console.log('Cargando contenido de cálculos...');
        
        // Expandir el primer acordeón por defecto
        const firstAccordion = document.querySelector('#collapseStock');
        if (firstAccordion && !firstAccordion.classList.contains('show')) {
            const accordion = new bootstrap.Collapse(firstAccordion, {
                show: true
            });
        }
    },

    // Cargar contenido de parámetros
    loadParametersContent: function() {
        console.log('Cargando contenido de parámetros...');
        
        // Resaltar parámetros editables
        this.highlightEditableParameters();
    },

    // Cargar contenido de ejemplos
    loadExamplesContent: function() {
        console.log('Cargando contenido de ejemplos...');
        
        // Actualizar ejemplos con datos más recientes
        this.updateExamples();
    },

    // Resaltar parámetros editables
    highlightEditableParameters: function() {
        const editableRows = document.querySelectorAll('#parametros .badge.bg-success');
        editableRows.forEach(badge => {
            badge.parentElement.parentElement.style.backgroundColor = '#f8f9fa';
        });
    },

    // Animar contenido
    animateContent: function() {
        const content = document.querySelector('#ayudaRubrosModal .modal-body');
        if (content) {
            content.style.opacity = '0';
            content.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                content.style.transition = 'all 0.3s ease';
                content.style.opacity = '1';
                content.style.transform = 'translateY(0)';
            }, 100);
        }
    },

    // Animar contenido de pestaña
    animateTabContent: function(tabId) {
        const tabContent = document.querySelector(`#${tabId}`);
        if (tabContent) {
            tabContent.style.opacity = '0';
            tabContent.style.transform = 'translateY(10px)';
            
            setTimeout(() => {
                tabContent.style.transition = 'all 0.2s ease';
                tabContent.style.opacity = '1';
                tabContent.style.transform = 'translateY(0)';
            }, 50);
        }
    },

    // Imprimir ayuda
    imprimirAyuda: function() {
        console.log('Imprimiendo ayuda...');
        
        // Mostrar todas las pestañas para impresión
        const tabPanes = document.querySelectorAll('#ayudaRubrosModal .tab-pane');
        tabPanes.forEach(pane => {
            pane.classList.add('show', 'active');
        });
        
        // Expandir todos los acordeones
        const accordionCollapses = document.querySelectorAll('#ayudaRubrosModal .accordion-collapse');
        accordionCollapses.forEach(collapse => {
            collapse.classList.add('show');
        });
        
        // Imprimir
        window.print();
        
        // Restaurar estado después de imprimir
        setTimeout(() => {
            this.restoreTabState();
        }, 1000);
        
        // Registrar evento
        this.trackEvent('ayuda_impresa');
    },

    // Restaurar estado de pestañas
    restoreTabState: function() {
        const tabPanes = document.querySelectorAll('#ayudaRubrosModal .tab-pane');
        tabPanes.forEach((pane, index) => {
            if (index === 0) {
                pane.classList.add('show', 'active');
            } else {
                pane.classList.remove('show', 'active');
            }
        });
    },

    // Limpiar estado temporal
    clearTemporaryState: function() {
        // Limpiar cualquier estado temporal o cache
        console.log('Limpiando estado temporal...');
    },

    // Función para tracking de eventos (opcional)
    trackEvent: function(eventName, eventData = {}) {
        // Aquí se puede integrar con sistemas de analytics
        console.log(`Evento: ${eventName}`, eventData);
        
        // Ejemplo de integración con Google Analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', eventName, eventData);
        }
    },

    // Función para búsqueda dentro del modal
    setupSearch: function() {
        const searchInput = document.querySelector('#ayudaRubrosModal .search-input');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.searchContent(e.target.value);
            });
        }
    },

    // Buscar contenido
    searchContent: function(searchTerm) {
        const content = document.querySelector('#ayudaRubrosModal .modal-body');
        if (!content || !searchTerm) return;

        // Implementar lógica de búsqueda
        const searchRegex = new RegExp(searchTerm, 'gi');
        const textNodes = this.getTextNodes(content);
        
        textNodes.forEach(node => {
            const parent = node.parentNode;
            const text = node.textContent;
            
            if (searchRegex.test(text)) {
                const highlightedText = text.replace(searchRegex, '<mark>$&</mark>');
                parent.innerHTML = parent.innerHTML.replace(text, highlightedText);
            }
        });
    },

    // Obtener nodos de texto
    getTextNodes: function(element) {
        const textNodes = [];
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );

        let node;
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }

        return textNodes;
    },

    // Función para exportar ayuda a PDF (opcional)
    exportToPDF: function() {
        if (typeof jsPDF !== 'undefined') {
            const doc = new jsPDF();
            const content = document.querySelector('#ayudaRubrosModal .modal-body');
            
            doc.fromHTML(content.innerHTML, 15, 15, {
                'width': 170,
                'elementHandlers': {
                    '#ignorePDF': function (element, renderer) {
                        return true;
                    }
                }
            });
            
            doc.save('ayuda-sistema-rubros.pdf');
            this.trackEvent('ayuda_exportada_pdf');
        }
    }
};

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    AyudaRubros.init();
    console.log('✅ Sistema de ayuda de rubros inicializado');
});

// Función global para abrir directamente una pestaña específica
window.abrirAyudaRubros = function(tabId = 'overview') {
    const modal = new bootstrap.Modal(document.getElementById('ayudaRubrosModal'));
    modal.show();
    
    // Activar la pestaña especificada
    setTimeout(() => {
        const tab = document.querySelector(`#${tabId}-tab`);
        if (tab) {
            const bsTab = new bootstrap.Tab(tab);
            bsTab.show();
        }
    }, 300);
};

// Función global para actualizar datos del ejemplo
window.actualizarEjemploRubros = function(datos) {
    AyudaRubros.updateExampleWithRealData(datos);
};