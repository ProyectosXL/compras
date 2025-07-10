
<!-- Scripts del sistema -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<!-- Scripts modulares del sistema -->
<script src="js/utils.js"></script>
<script src="js/api-client.js"></script>
<script src="js/tabla-renderer.js"></script>
<script src="js/busqueda-manager.js"></script>
<script src="js/indice-editor.js"></script>
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

    // Verificación de módulos
    function verificarModulosCargados() {
        const modulos = {
            'FormatoUtils': typeof FormatoUtils !== 'undefined',
            'UIUtils': typeof UIUtils !== 'undefined',
            'APIClient': typeof APIClient !== 'undefined',
            'TablaRenderer': typeof TablaRenderer !== 'undefined',
            'BusquedaManager': typeof BusquedaManager !== 'undefined',
            'IndiceEditor': typeof IndiceEditor !== 'undefined',
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

    // Inicialización principal
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Sistema de Presupuesto de Compras v2.2 - REFACTORIZADO iniciado');

        // Verificar que todos los módulos estén cargados
        if (!verificarModulosCargados()) {
            return;
        }

        console.log('✅ Refactorización completada:');
        console.log('  - Archivos divididos en componentes');
        console.log('  - Estructura modular implementada');
        console.log('  - Preparado para nueva solapa');
        console.log('  - Mantenimiento simplificado');
        
        // Actualizar fecha y hora inmediatamente
        actualizarFechaHora();
        
        // Actualizar cada segundo
        setInterval(actualizarFechaHora, 1000);
        
        // Mostrar información del sistema en consola
        console.log('Módulos cargados:', {
            'Utils': typeof FormatoUtils !== 'undefined',
            'APIClient': typeof APIClient !== 'undefined', 
            'TablaRenderer': typeof TablaRenderer !== 'undefined',
            'BusquedaManager': typeof BusquedaManager !== 'undefined',
            'IndiceEditor': typeof IndiceEditor !== 'undefined',
            'PresupuestoApp': typeof window.presupuestoApp !== 'undefined'
        });
        
        // Verificar elementos del DOM
        const elementosRequeridos = [
            'loading', 'tabs-container', 'info-temporada-container',
            'temporada-actual', 'dias-restantes', 'ultima-actualizacion',
            'search-verano', 'search-invierno', 'search-stock',
            'tabla-verano', 'tabla-invierno', 'tabla-stock',
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
        
        console.log('✅ Sistema refactorizado inicializado correctamente');
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
            
            // Escape para limpiar búsquedas
            if (e.key === 'Escape') {
                ['verano', 'invierno', 'stock'].forEach(solapa => {
                    const input = document.getElementById(`search-${solapa}`);
                    if (input && input === document.activeElement) {
                        input.value = '';
                        buscarDatos(solapa);
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
                    stock: document.getElementById('search-stock')?.value || ''
                },
                timestamp: Date.now()
            };
            
            if (typeof StorageUtils !== 'undefined') {
                StorageUtils.guardar('preferencias_usuario', preferencias, 24 * 60 * 60 * 1000);
            }
        });
    });
</script>