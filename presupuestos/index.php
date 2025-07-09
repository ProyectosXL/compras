
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Presupuesto de Compras</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/obtener-presupuesto.css" rel="stylesheet">
    <style>
        .loading { display: none; }
        .table-responsive { max-height: 75vh; overflow: auto; }
        .nav-tabs { border-bottom: 2px solid #dee2e6; }
        .nav-tabs .nav-link { 
            border: none; 
            color: #6c757d; 
            font-weight: 600;
            padding: 0.75rem 1rem; /* Reducido padding */
        }
        .nav-tabs .nav-link.active { 
            background-color: #0d6efd; 
            color: white; 
            border-radius: 0.5rem 0.5rem 0 0;
        }
        .tab-content { 
            background: white; 
            border: 1px solid #dee2e6; 
            border-top: none;
            border-radius: 0 0 0.5rem 0.5rem;
            min-height: 400px;
        }
        .search-container {
            background: #f8f9fa;
            padding: 0.75rem; /* Reducido de 1rem */
            border-bottom: 1px solid #dee2e6;
        }
        .editable-cell {
            background-color: #fff3cd !important;
            cursor: pointer;
            position: relative;
        }
        .editable-cell:hover {
            background-color: #ffeaa7 !important;
        }
        .indice-input {
            width: 100%;
            border: none;
            background: transparent;
            text-align: center;
            font-weight: bold;
        }
        .indice-input:focus {
            outline: 2px solid #0d6efd;
            background: white;
        }
        .valor-positivo { color: #198754; font-weight: bold; }
        .valor-negativo { color: #dc3545; font-weight: bold; }
        .valor-neutro { color: #6c757d; font-weight: bold; }
        .sticky-header th {
            position: sticky;
            top: 0;
            background: #343a40;
            z-index: 10;
        }
        /* NUEVO: Header compacto */
        .header-compacto {
            background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%);
            color: white;
            padding: 1rem 0; /* Reducido de 2rem */
            margin: -1rem -15px 1rem -15px; /* Ajustado márgenes */
            border-radius: 0 0 0.5rem 0.5rem;
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.2);
        }
        .header-compacto h1 {
            font-size: 1.5rem; /* Reducido tamaño */
            margin-bottom: 0;
            font-weight: 700;
        }
        /* NUEVO: Botones de exportación agrupados */
        .export-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        /* NUEVO: Zona horaria Argentina */
        .timezone-info {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        .temporada-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem; /* Reducido de 1rem */
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        /* Mejorar búsqueda rápida */
        .search-input-fast {
            transition: all 0.15s ease;
        }
        .search-input-fast:focus {
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
            border-color: #0d6efd;
            transform: scale(1.02);
        }
        /* Badge animado para contadores */
        .contador-animado {
            transition: all 0.3s ease;
        }
        .contador-animado.actualizado {
            animation: pulse-counter 0.5s ease;
        }
        @keyframes pulse-counter {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .header-venta-anterior {
            background-color: #0dcaf0 !important; /* Info azul claro */
            color: #000 !important; /* Texto negro para contraste */
            font-weight: 700 !important;
            font-size: 0.75rem !important;
            text-align: center !important;
            padding: 8px 4px !important;
            border: 1px solid #000 !important;
        }
        
        .header-venta-proyectada {
            background-color: #0d6efd !important; /* Azul primario */
            color: #fff !important; /* Texto blanco */
            font-weight: 700 !important;
            font-size: 0.75rem !important;
            text-align: center !important;
            padding: 8px 4px !important;
            border: 1px solid #000 !important;
        }
        
        .header-compra-proyectada {
            background-color: #198754 !important; /* Verde */
            color: #fff !important; /* Texto blanco */
            font-weight: 700 !important;
            font-size: 0.75rem !important;
            text-align: center !important;
            padding: 8px 4px !important;
            border: 1px solid #000 !important;
        }
        
        /* Asegurar que todos los headers de la tabla sean visibles */
        .table thead th {
            background-color: #343a40 !important;
            color: #fff !important;
            font-weight: 700 !important;
            text-align: center !important;
            padding: 8px 4px !important;
            border: 1px solid #000 !important;
            font-size: 0.75rem !important;
        }
        
        /* Headers específicos con colores distintivos */
        .bg-warning {
            background-color: #ffc107 !important;
            color: #000 !important; /* Texto negro para contraste */
            font-weight: 700 !important;
            border: 1px solid #000 !important;
        }
        
        .bg-info.text-dark {
            background-color: #0dcaf0 !important;
            color: #000 !important;
            font-weight: 700 !important;
            border: 1px solid #000 !important;
        }
        
        /* Sticky headers mejorados */
        .sticky-header th {
            position: sticky !important;
            top: 0 !important;
            z-index: 10 !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3) !important;
        }
        
        /* Responsive mejorado */
        @media (max-width: 768px) {
            .table thead th {
                font-size: 0.65rem !important;
                padding: 4px 2px !important;
            }
            
            .header-venta-anterior,
            .header-venta-proyectada,
            .header-compra-proyectada {
                font-size: 0.6rem !important;
                padding: 4px 2px !important;
            }
        }

        .card {
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .contador-animado.actualizado {
            animation: pulse-total 0.6s ease;
        }

        @keyframes pulse-total {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Responsive para móviles */
        @media (max-width: 768px) {
            .search-container .row {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .search-container .col-md-3,
            .search-container .col-md-4 {
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .card.bg-warning-subtle,
            .card.bg-primary-subtle {
                margin-bottom: 0.5rem;
            }
            
            .export-buttons {
                justify-content: center;
            }
        }

    </style>
</head>
<body>
    <div class="container-fluid py-2 pb-1"> <!-- Reducido padding -->
        <div class="container-fluid py-2 pb-1" style="margin-bottom: 0; padding-bottom: 0.5rem;">
        <!-- Header Compacto -->
        <div class="row">
            <div class="col-12">
                <div class="header-compacto">
                    <div class="container-fluid">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1>
                                    <i class="fas fa-calculator me-2"></i>
                                    Sistema de Presupuesto de Compras
                                </h1>
                                <div class="timezone-info">
                                    <i class="fas fa-clock me-1"></i>
                                    <span id="fecha-hora-actual">--</span> (GMT-3 Argentina)
                                </div>
                            </div>
                            <div class="export-buttons">
                                <button type="button" class="btn btn-light" onclick="cargarDatos()">
                                    <i class="fas fa-sync-alt me-1"></i> Cargar Datos
                                </button>
                                <button type="button" class="btn btn-success" onclick="exportarExcel('completo')" 
                                        style="display: none;" id="btn-export-completo-header">
                                    <i class="fas fa-file-excel me-1"></i> Excel Completo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información de Temporada -->
        <div class="row mb-3" id="info-temporada-container" style="display: none;">
            <div class="col-12">
                <div class="temporada-info">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <h6 class="mb-1">
                                <i class="fas fa-calendar-alt me-2"></i>
                                <span id="temporada-actual">Temporada Actual</span>
                            </h6>
                            <small id="fecha-actual"></small>
                        </div>
                        <div class="col-md-4 text-center">
                            <h6 class="mb-1">Días Restantes</h6>
                            <span class="h5" id="dias-restantes">-</span>
                        </div>
                        <div class="col-md-4 text-end">
                            <small>Última actualización: <span id="ultima-actualizacion">--:--</span></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div class="text-center loading" id="loading">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-2">Procesando datos del presupuesto...</p>
        </div>

        <!-- Tabs Container -->
        <div class="row" id="tabs-container" style="display: none;">
            <div class="col-12">
                <div class="card border-0 shadow">
                    <!-- Nav Tabs -->
                    <ul class="nav nav-tabs" id="presupuestoTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="verano-tab" data-bs-toggle="tab" 
                                    data-bs-target="#verano" type="button" role="tab">
                                <i class="fas fa-sun me-2"></i>Compra Proyectada Verano
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="invierno-tab" data-bs-toggle="tab" 
                                    data-bs-target="#invierno" type="button" role="tab">
                                <i class="fas fa-snowflake me-2"></i>Compra Proyectada Invierno
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="stock-tab" data-bs-toggle="tab" 
                                    data-bs-target="#stock" type="button" role="tab">
                                <i class="fas fa-boxes me-2"></i>Stock Proyectado
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="presupuestoTabContent">
                        <!-- Compra Proyectada Verano -->
                        <div class="tab-pane fade show active" id="verano" role="tabpanel">
                            <div class="search-container">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-search"></i>
                                            </span>
                                            <input type="text" class="form-control search-input-fast" id="search-verano" 
                                                placeholder="Búsqueda instantánea por rubro o categoría..." 
                                                onkeyup="buscarDatos('verano')">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <span class="badge bg-info fs-6 contador-animado" id="count-verano">0 registros</span>
                                    </div>
                                    <div class="col-md-3">
                                        <!-- NUEVO: Total de unidades a comprar -->
                                        <div class="card bg-warning-subtle border-warning">
                                            <div class="card-body p-2 text-center">
                                                <h6 class="card-title mb-1 text-warning-emphasis">
                                                    <i class="fas fa-shopping-cart me-1"></i>
                                                    Total a Comprar
                                                </h6>
                                                <div class="text-warning-emphasis" id="total-comprar-verano">
                                                    <strong>0</strong> unidades
                                                    <small class="text-muted d-block">Calculando...</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-end export-buttons">
                                        <button class="btn btn-success btn-sm" onclick="exportarExcel('verano')">
                                            <i class="fas fa-file-excel me-1"></i> Excel
                                        </button>
                                        <button class="btn btn-info btn-sm" onclick="mostrarEstadisticasCompra('verano')" title="Ver estadísticas detalladas">
                                            <i class="fas fa-chart-bar"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Compra Proyectada Invierno -->
                        <div class="tab-pane fade" id="invierno" role="tabpanel">
                            <div class="search-container">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-search"></i>
                                            </span>
                                            <input type="text" class="form-control search-input-fast" id="search-invierno" 
                                                placeholder="Búsqueda instantánea por rubro o categoría..." 
                                                onkeyup="buscarDatos('invierno')">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <span class="badge bg-info fs-6 contador-animado" id="count-invierno">0 registros</span>
                                    </div>
                                    <div class="col-md-3">
                                        <!-- NUEVO: Total de unidades a comprar -->
                                        <div class="card bg-primary-subtle border-primary">
                                            <div class="card-body p-2 text-center">
                                                <h6 class="card-title mb-1 text-primary-emphasis">
                                                    <i class="fas fa-shopping-cart me-1"></i>
                                                    Total a Comprar
                                                </h6>
                                                <div class="text-primary-emphasis" id="total-comprar-invierno">
                                                    <strong>0</strong> unidades
                                                    <small class="text-muted d-block">Calculando...</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-end export-buttons">
                                        <button class="btn btn-success btn-sm" onclick="exportarExcel('invierno')">
                                            <i class="fas fa-file-excel me-1"></i> Excel
                                        </button>
                                        <button class="btn btn-info btn-sm" onclick="mostrarEstadisticasCompra('invierno')" title="Ver estadísticas detalladas">
                                            <i class="fas fa-chart-bar"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Stock Proyectado -->
                        <div class="tab-pane fade" id="stock" role="tabpanel">
                            <div class="search-container">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-search"></i>
                                            </span>
                                            <input type="text" class="form-control search-input-fast" id="search-stock" 
                                                   placeholder="Búsqueda instantánea por rubro o categoría..." 
                                                   onkeyup="buscarDatos('stock')">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="badge bg-info fs-6 contador-animado" id="count-stock">0 registros</span>
                                    </div>
                                    <div class="col-md-3 text-end export-buttons">
                                        <button class="btn btn-success btn-sm" onclick="exportarExcel('stock')">
                                            <i class="fas fa-file-excel me-1"></i> Excel
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0" id="tabla-stock">
                                    <thead class="table-dark sticky-header">
                                        <tr>
                                            <th>Rubro</th>
                                            <th>Categoría</th>
                                            <th class="text-center bg-info">Stock Actual</th>
                                            <th class="text-center bg-warning">Stock a Guardar</th>
                                            <th class="text-center bg-success">Compras Verano</th>
                                            <th class="text-center bg-success">Compras Invierno</th>
                                            <th class="text-center bg-success">Compras Atemporal</th>
                                            <th class="text-center bg-danger text-white">Stock Cobertura</th>
                                            <th class="text-center bg-primary text-white">Stock Proyectado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-stock">
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="fas fa-info-circle"></i>
                                                Cargue los datos para ver el stock proyectado
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Container -->
    <div class="alert-container position-fixed top-0 end-0 p-3" id="alert-container" style="z-index: 1060;"></div>

    <!-- Modal para editar índice -->
    <div class="modal fade" id="modalEditarIndice" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Índice de Variación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Rubro:</label>
                        <p class="fw-bold" id="modal-rubro"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoría:</label>
                        <p class="fw-bold" id="modal-categoria"></p>
                    </div>
                    <div class="mb-3">
                        <label for="modal-nuevo-indice" class="form-label">Nuevo Índice:</label>
                        <input type="number" class="form-control" id="modal-nuevo-indice" 
                               step="0.01" min="0" max="10">
                               <div class="form-text">
                                <strong>Ejemplos:</strong><br>
                                • 1.0 = Sin cambio (100%)<br>
                                • 1.2 = Aumento 20%<br>
                                • 0.8 = Reducción 20%
                            </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarNuevoIndice()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
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
            console.log('Sistema de Presupuesto de Compras v2.2 - CORREGIDO iniciado');

            // Verificar que todos los módulos estén cargados
            if (!verificarModulosCargados()) {
                return;
            }

            console.log('✅ Correcciones aplicadas:');
            console.log('  - Cálculo proporcional por días para temporada actual');
            console.log('  - Nomenclatura VERANO XX-XX correcta');
            console.log('  - Headers de ventas anteriores visibles');
            console.log('  - Tabla de stock corregida (sin ventas proyectadas)');
            console.log('  - TablaRenderer simplificado');
            
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
                'modalEditarIndice'
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
            
            console.log('✅ Sistema inicializado correctamente');
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

        // Debug para verificar elementos DOM
        function verificarElementosTablas() {
            const elementos = [
                'tabla-verano', 'tbody-verano',
                'tabla-invierno', 'tbody-invierno', 
                'tabla-stock', 'tbody-stock'
            ];
            
            console.group('🔍 VERIFICACIÓN ELEMENTOS DOM');
            elementos.forEach(id => {
                const elemento = document.getElementById(id);
                if (elemento) {
                    console.log(`✅ ${id}: ENCONTRADO`);
                } else {
                    console.error(`❌ ${id}: NO ENCONTRADO`);
                }
            });
            console.groupEnd();
            
            // Verificar estructura de tablas
            ['verano', 'invierno', 'stock'].forEach(solapa => {
                const tabla = document.getElementById(`tabla-${solapa}`);
                if (tabla) {
                    const thead = tabla.querySelector('thead');
                    const tbody = tabla.querySelector('tbody');
                    console.log(`📊 Tabla ${solapa}:`, {
                        tabla: !!tabla,
                        thead: !!thead,
                        tbody: !!tbody,
                        headers: thead ? thead.querySelectorAll('th').length : 0,
                        filas: tbody ? tbody.querySelectorAll('tr').length : 0
                    });
                }
            });
        }

        // Ejecutar verificación después de cargar el DOM
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar inmediatamente
            verificarElementosTablas();
            
            // Función global para debugging
            window.verificarElementosTablas = verificarElementosTablas;
            
            console.log('💡 Usa verificarElementosTablas() para ver estado de elementos DOM');
        });

    </script>
</body>
</html>