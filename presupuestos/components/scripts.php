
<!-- Scripts del sistema -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<!-- Scripts modulares del sistema -->
<script src="js/utils.js"></script>
<script src="js/api-client.js"></script>
<script src="js/tabla-renderer.js"></script>
<script src="js/busqueda-manager.js"></script>
<script src="js/indice-editor.js"></script>
<script src="js/compras-manager.js"></script>
<script src="js/totales-compra.js"></script>
<script src="js/main.js"></script>

<script>
    // Variables globales para compatibilidad
    let datosActuales = {
        verano: [],
        invierno: [],
        stock: []
    };
    let timeoutBusqueda = null;
    let indiceEditando = null;

    // Funciones de compatibilidad para llamadas desde HTML
    function cargarDatos() {
        window.presupuestoApp.cargarDatos();
    }

    function buscarDatos(solapa) {
        if (BusquedaManager && BusquedaManager.busquedaInstantanea) {
            const input = document.getElementById(`search-${solapa}`);
            if (input) {
                BusquedaManager.busquedaInstantanea(solapa, input.value);
            }
        } else {
            window.presupuestoApp.buscarDatos(solapa);
        }
    }

    function exportarExcel(solapa) {
        window.presupuestoApp.exportarExcel(solapa);
    }

    function editarIndice(rubro, categoria, indiceActual, solapa, index) {
        IndiceEditor.editarIndice(rubro, categoria, indiceActual, solapa, index);
    }

    function guardarNuevoIndice() {
        IndiceEditor.guardarNuevoIndice();
    }

    // Funciones de utilidad globales
    function mostrarLoading(mostrar) {
        UIUtils.mostrarLoading(mostrar);
    }

    function mostrarTabsContainer(mostrar) {
        UIUtils.mostrarTabsContainer(mostrar);
        const btnExport = document.getElementById('btn-export-completo-header');
        if (btnExport) {
            btnExport.style.display = mostrar ? 'inline-block' : 'none';
        }
    }

    function mostrarInfoTemporada(info) {
        UIUtils.mostrarInfoTemporada(info);
    }

    function actualizarContador(elementId, count) {
        UIUtils.actualizarContador(elementId, count);
        const elemento = document.getElementById(elementId);
        if (elemento) {
            elemento.classList.add('actualizado');
            setTimeout(() => elemento.classList.remove('actualizado'), 500);
        }
    }

    function mostrarAlerta(mensaje, tipo, duracion) {
        UIUtils.mostrarAlerta(mensaje, tipo, duracion);
    }

    function formatearNumero(numero) {
        return FormatoUtils.formatearNumero(numero);
    }

    function formatearDecimal(numero, decimales) {
        return FormatoUtils.formatearDecimal(numero, decimales);
    }

    function obtenerClaseValor(valor) {
        return FormatoUtils.obtenerClaseValor(valor);
    }

    // Función para mostrar modal de información del sistema
    function mostrarInfoSistema() {
        const modal = new bootstrap.Modal(document.getElementById('modalInfoSistema'));
        
        // Actualizar información antes de mostrar
        const info = diagnosticoSistema();
        document.getElementById('info-modulos').textContent = Object.keys(info.datos_cargados).length;
        document.getElementById('info-ultima-carga').textContent = info.estado.ultimaActualizacion || 'Nunca';
        document.getElementById('info-registros-verano').textContent = info.datos_cargados.find(d => d.solapa === 'verano')?.registros || 0;
        document.getElementById('info-registros-invierno').textContent = info.datos_cargados.find(d => d.solapa === 'invierno')?.registros || 0;
        document.getElementById('info-registros-stock').textContent = info.datos_cargados.find(d => d.solapa === 'stock')?.registros || 0;
        
        modal.show();
    }

    // Función para actualizar fecha y hora con zona horaria Argentina
    function actualizarFechaHora() {
        const ahora = new Date();
        const opciones = {
            timeZone: 'America/Argentina/Buenos_Aires',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        };
        
        const fechaHora = ahora.toLocaleString('es-AR', opciones);
        const elemento = document.getElementById('fecha-hora-actual');
        if (elemento) {
            elemento.textContent = fechaHora;
        }
    }

    // Función para manejo de tabs con eventos
    function manejarCambioTab(event) {
        const targetTab = event.target.getAttribute('data-bs-target');
        const tabId = targetTab ? targetTab.replace('#', '') : null;
        
        // Si es la solapa de compras detalle y no hay datos cargados, mostrar mensaje
        if (tabId === 'compras-detalle' && ComprasManager.datos.length === 0) {
            const tbody = document.getElementById('tbody-compras-detalle');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="12" class="text-center text-muted py-4">
                            <i class="fas fa-info-circle mb-2"></i><br>
                            Presione "Cargar" para obtener el detalle de compras pendientes
                        </td>
                    </tr>
                `;
            }
        }
    }

    // Verificación de módulos
    function verificarModulosCargados() {
        const modulos = {
            'FormatoUtils': typeof FormatoUtils !== 'undefined',
            'UIUtils': typeof UIUtils !== 'undefined',
            'APIClient': typeof APIClient !== 'undefined',
            'TablaRenderer': typeof TablaRenderer !== 'undefined',
            'BusquedaManager': typeof BusquedaManager !== 'undefined',
            'IndiceEditor': typeof IndiceEditor !== 'undefined',
            'ComprasManager': typeof ComprasManager !== 'undefined',
            'PresupuestoApp': typeof PresupuestoApp !== 'undefined'
        };
        
        const faltantes = Object.entries(modulos)
            .filter(([nombre, cargado]) => !cargado)
            .map(([nombre]) => nombre);
            
        if (faltantes.length > 0) {
            console.error('❌ Módulos no cargados:', faltantes);
            alert('Error: Faltan módulos del sistema. Recarga la página.');
            return false;
        }
        
        console.log('✅ Todos los módulos cargados correctamente');
        return true;
    }

    // Función de diagnóstico del sistema
    function diagnosticoSistema() {
        const info = {
            estado: {
                ultimaActualizacion: new Date().toLocaleString('es-AR'),
                modulos: {
                    'FormatoUtils': typeof FormatoUtils !== 'undefined',
                    'UIUtils': typeof UIUtils !== 'undefined',
                    'APIClient': typeof APIClient !== 'undefined',
                    'TablaRenderer': typeof TablaRenderer !== 'undefined',
                    'BusquedaManager': typeof BusquedaManager !== 'undefined',
                    'IndiceEditor': typeof IndiceEditor !== 'undefined',
                    'ComprasManager': typeof ComprasManager !== 'undefined',
                    'PresupuestoApp': typeof PresupuestoApp !== 'undefined'
                }
            },
            datos_cargados: [
                {
                    solapa: 'verano',
                    registros: datosActuales.verano?.length || 0
                },
                {
                    solapa: 'invierno',
                    registros: datosActuales.invierno?.length || 0
                },
                {
                    solapa: 'stock',
                    registros: datosActuales.stock?.length || 0
                },
                {
                    solapa: 'compras-detalle',
                    registros: ComprasManager?.datos?.length || 0
                }
            ]
        };
        
        console.log('📊 Diagnóstico del sistema:', info);
        return info;
    }

    // Función para limpiar cache
    function limpiarCache() {
        if (APIClient?.limpiarCache) {
            APIClient.limpiarCache();
        }
        
        if (StorageUtils?.limpiarExpirados) {
            const eliminados = StorageUtils.limpiarExpirados();
            console.log(`🗑️ Cache limpiado: ${eliminados} elementos eliminados`);
        }
        
        UIUtils.mostrarAlerta('Cache limpiado correctamente', 'success');
    }

    // Función para limpiar tablas
    function limpiarTablas() {
        datosActuales = {
            verano: [],
            invierno: [],
            stock: []
        };
        
        if (ComprasManager) {
            ComprasManager.datos = [];
            ComprasManager.datosFiltrados = [];
        }
        
        // Limpiar contenido de tablas
        const tablas = ['tbody-verano', 'tbody-invierno', 'tbody-stock', 'tbody-compras-detalle'];
        tablas.forEach(id => {
            const tabla = document.getElementById(id);
            if (tabla) {
                tabla.innerHTML = '<tr><td colspan="12" class="text-center text-muted py-4"><i class="fas fa-info-circle"></i><br>Datos limpiados</td></tr>';
            }
        });
        
        // Resetear contadores
        const contadores = ['count-verano', 'count-invierno', 'count-stock', 'count-compras-detalle'];
        contadores.forEach(id => {
            const contador = document.getElementById(id);
            if (contador) {
                contador.textContent = '0 registros';
            }
        });
        
        UIUtils.mostrarAlerta('Tablas limpiadas correctamente', 'success');
    }

    // Inicialización principal
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Sistema de Presupuesto de Compras v2.3 - CON COMPRAS DETALLE iniciado');

        // Verificar que todos los módulos estén cargados
        if (!verificarModulosCargados()) {
            return;
        }

        console.log('✅ Refactorización completada + Compras Detalle:');
        console.log('  - Archivos divididos en componentes');
        console.log('  - Estructura modular implementada');
        console.log('  - Nueva solapa de compras detalle');
        console.log('  - Sistema de filtros avanzados');
        console.log('  - Mantenimiento simplificado');
        
        // Actualizar fecha y hora inmediatamente
        actualizarFechaHora();
        
        // Actualizar cada segundo
        setInterval(actualizarFechaHora, 1000);
        
        // Configurar eventos de tabs
        const tabLinks = document.querySelectorAll('[data-bs-toggle="tab"]');
        tabLinks.forEach(tab => {
            tab.addEventListener('shown.bs.tab', manejarCambioTab);
        });
        
        // Mostrar información del sistema en consola
        console.log('Módulos cargados:', {
            'Utils': typeof FormatoUtils !== 'undefined',
            'APIClient': typeof APIClient !== 'undefined', 
            'TablaRenderer': typeof TablaRenderer !== 'undefined',
            'BusquedaManager': typeof BusquedaManager !== 'undefined',
            'IndiceEditor': typeof IndiceEditor !== 'undefined',
            'ComprasManager': typeof ComprasManager !== 'undefined',
            'PresupuestoApp': typeof window.presupuestoApp !== 'undefined'
        });
        
        // Verificar elementos del DOM
        const elementosRequeridos = [
            'loading', 'tabs-container', 'info-temporada-container',
            'temporada-actual', 'dias-restantes', 'ultima-actualizacion',
            'search-verano', 'search-invierno', 'search-stock', 'search-compras-detalle',
            'tabla-verano', 'tabla-invierno', 'tabla-stock', 'tabla-compras-detalle',
            'modalEditarIndice', 'modalInfoSistema'
        ];
        
        const elementosFaltantes = elementosRequeridos.filter(id => !document.getElementById(id));
        if (elementosFaltantes.length > 0) {
            console.warn('Elementos DOM faltantes:', elementosFaltantes);
        }
        
        // Configurar tooltips de Bootstrap
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Agregar botón de información al header
        const exportButtons = document.querySelector('.export-buttons');
        if (exportButtons) {
            const btnInfo = document.createElement('button');
            btnInfo.className = 'btn btn-outline-light btn-sm';
            btnInfo.innerHTML = '<i class="fas fa-info-circle"></i>';
            btnInfo.onclick = mostrarInfoSistema;
            btnInfo.title = 'Información del Sistema';
            exportButtons.appendChild(btnInfo);
        }
        
        console.log('✅ Sistema refactorizado con compras detalle inicializado correctamente');
    });

    // Event listeners adicionales
    document.addEventListener('DOMContentLoaded', function() {
        // Shortcuts de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + L para cargar datos
            if (e.ctrlKey && e.key === 'l') {
                e.preventDefault();
                cargarDatos();
            }
            
            // Ctrl + E para exportar completo
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportarExcel('completo');
            }
            
            // Ctrl + I para mostrar información del sistema
            if (e.ctrlKey && e.key === 'i') {
                e.preventDefault();
                mostrarInfoSistema();
            }
            
            // Ctrl + C para cargar compras detalle
            if (e.ctrlKey && e.key === 'c') {
                e.preventDefault();
                if (ComprasManager) {
                    ComprasManager.cargarDatos();
                }
            }
            
            // Escape para limpiar búsquedas
            if (e.key === 'Escape') {
                ['verano', 'invierno', 'stock', 'compras-detalle'].forEach(solapa => {
                    const input = document.getElementById(`search-${solapa}`);
                    if (input && input === document.activeElement) {
                        input.value = '';
                        if (solapa === 'compras-detalle') {
                            ComprasManager.buscarInstantanea();
                        } else {
                            buscarDatos(solapa);
                        }
                    }
                });
            }
        });
        
        // Auto-save de preferencias de usuario
        window.addEventListener('beforeunload', function() {
            const preferencias = {
                ultima_solapa: document.querySelector('.nav-link.active')?.getAttribute('data-bs-target')?.replace('#', ''),
                busquedas: {
                    verano: document.getElementById('search-verano')?.value || '',
                    invierno: document.getElementById('search-invierno')?.value || '',
                    stock: document.getElementById('search-stock')?.value || '',
                    compras: document.getElementById('search-compras-detalle')?.value || ''
                },
                filtros_compras: ComprasManager ? ComprasManager.filtrosActivos : {},
                timestamp: Date.now()
            };
            
            if (typeof StorageUtils !== 'undefined') {
                StorageUtils.guardar('preferencias_usuario', preferencias, 24 * 60 * 60 * 1000);
            }
        });
        
        // Restaurar preferencias al cargar
        if (typeof StorageUtils !== 'undefined') {
            const preferencias = StorageUtils.obtener('preferencias_usuario');
            if (preferencias) {
                // Restaurar búsquedas
                Object.entries(preferencias.busquedas || {}).forEach(([solapa, valor]) => {
                    const input = document.getElementById(`search-${solapa}`);
                    if (input) {
                        input.value = valor;
                    }
                });
                
                // Restaurar solapa activa
                if (preferencias.ultima_solapa) {
                    const tab = document.querySelector(`[data-bs-target="#${preferencias.ultima_solapa}"]`);
                    if (tab) {
                        setTimeout(() => {
                            const tabInstance = new bootstrap.Tab(tab);
                            tabInstance.show();
                        }, 100);
                    }
                }
            }
        }
    });

        // Función para ajustar altura dinámicamente
        function ajustarAltura() {
            const windowHeight = window.innerHeight;
            const headerHeight = document.querySelector('.flex-header')?.offsetHeight || 0;
            const availableHeight = windowHeight - headerHeight - 20; // 20px de margen
            
            // Ajustar altura del contenido principal
            const flexContent = document.querySelector('.flex-content');
            if (flexContent) {
                flexContent.style.height = availableHeight + 'px';
            }
            
            // Ajustar altura de las tablas
            const tablesResponsive = document.querySelectorAll('.table-responsive');
            const tableHeight = availableHeight - 100; // Espacio para controles
            
            tablesResponsive.forEach(table => {
                if (table.closest('#compras-detalle')) {
                    table.style.maxHeight = (tableHeight - 60) + 'px'; // Espacio extra para resumen
                } else {
                    table.style.maxHeight = tableHeight + 'px';
                }
            });
        }
        
        // Ejecutar al cargar y redimensionar
        window.addEventListener('load', ajustarAltura);
        window.addEventListener('resize', ajustarAltura);
        
        // Ejecutar cuando se muestren las tabs
        document.addEventListener('DOMContentLoaded', function() {
            const tabTriggers = document.querySelectorAll('[data-bs-toggle="tab"]');
            tabTriggers.forEach(trigger => {
                trigger.addEventListener('shown.bs.tab', function() {
                    setTimeout(ajustarAltura, 100);
                });
            });
        });
        
</script>