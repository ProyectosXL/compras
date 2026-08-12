<!-- Solapa: Proceso para Presupuesto -->
<div class="tab-pane fade" id="distribucion" role="tabpanel">
    
    <!-- Stepper del Proceso para Presupuesto -->
    <div class="stepper-container my-3">
        <button class="step-item active" id="btn-step-1" onclick="DistribucionManager.irAPaso(1)">
            <div class="step-circle">1</div>
            <div class="step-label">Distribución por Canal</div>
        </button>
        <button class="step-item" id="btn-step-2" onclick="DistribucionManager.irAPaso(2)">
            <div class="step-circle">2</div>
            <div class="step-label">Proyección De Ventas</div>
        </button>
        <button class="step-item" id="btn-step-3" onclick="DistribucionManager.irAPaso(3)">
            <div class="step-circle">3</div>
            <div class="step-label">Análisis de Distribución</div>
        </button>
    </div>

    <!-- Barra de Búsqueda y Filtros Compartidos -->
    <div class="search-container mb-3 rounded shadow-sm">
        <!-- Fila 1: Búsqueda, contadores y filtros de texto -->
        <div class="row align-items-center mb-2 g-2">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control search-input-fast" id="search-distribucion"
                        placeholder="Buscar por rubro o categoría..."
                        onkeyup="DistribucionManager.buscarDatos()">
                </div>
            </div>
            <div class="col-auto">
                <span class="badge bg-info fs-6 contador-animado" id="count-distribucion">0 registros</span>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="filtro-rubro-distribucion" onchange="DistribucionManager.filtrarDatos()">
                    <option value="">Todos los rubros</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="filtro-categoria-distribucion" onchange="DistribucionManager.filtrarDatos()">
                    <option value="">Todas las categorías</option>
                </select>
            </div>
        </div>

        <!-- Fila 2: Filtros de canal/temporada/fecha + botones de acción -->
        <div class="row align-items-center g-2">
            <div class="col-auto" id="col-filtro-canal">
                <select class="form-select form-select-sm" id="filtro-canal-distribucion" onchange="DistribucionManager.filtrarDatos()">
                    <option value="">Todos los canales</option>
                </select>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" id="filtro-temporada-distribucion" onchange="DistribucionManager.inicializarVersiones(true)">
                    <option value="VERANO">Presupuesto: Verano</option>
                    <option value="INVIERNO">Presupuesto: Invierno</option>
                </select>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" id="filtro-version-distribucion" onfocus="DistribucionManager.inicializarVersiones()" onchange="DistribucionManager.cambiarVersion(this.value)" style="min-width: 150px;">
                    <option value="Por defecto">Versión: Por defecto</option>
                </select>
            </div>
            <div class="col-auto">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Desde</span>
                    <input type="date" class="form-control" id="filtro-desde-distribucion">
                    <span class="input-group-text">Hasta</span>
                    <input type="date" class="form-control" id="filtro-hasta-distribucion">
                </div>
            </div>
            <!-- Switch de moneda -->
            <div class="col-auto" id="col-switch-moneda" style="display: none;">
                <div class="btn-group btn-group-sm" role="group" aria-label="Moneda">
                    <button type="button" class="btn btn-primary active" id="btn-moneda-ars"
                        onclick="DistribucionManager.toggleMoneda('ARS')" title="Ver en Pesos">
                        <i class="fas fa-dollar-sign me-1"></i>$
                    </button>
                    <button type="button" class="btn btn-outline-success" id="btn-moneda-usd"
                        onclick="DistribucionManager.toggleMoneda('USD')" title="Ver en Dólares">
                        <span class="fw-bold">U$D</span>
                    </button>
                </div>
                <small class="text-muted ms-1 d-none" id="label-tipo-cambio" style="font-size:0.72rem;"></small>
            </div>
            <div class="col text-end export-buttons">
                <button class="btn btn-primary btn-sm me-1" onclick="DistribucionManager.ejecutarCargar()">
                    <i class="fas fa-sync-alt me-1"></i> Cargar / Recalcular
                </button>
                <button class="btn btn-success btn-sm me-1" onclick="DistribucionManager.ejecutarGuardar()">
                    <i class="fas fa-save me-1"></i> Guardar
                </button>
                <button class="btn btn-success btn-sm me-1" onclick="DistribucionManager.ejecutarExcel()">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
                <button class="btn btn-dark btn-sm me-1" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Imprimir
                </button>
                <button class="btn btn-warning btn-sm fw-bold text-dark" id="btn-costos-parametros" onclick="DistribucionManager.abrirModalParametros()" style="display: none;">
                    <i class="fas fa-sliders-h me-1"></i> Parámetros
                </button>
            </div>
        </div>
    </div>

    <!-- PASO 1: Distribución por Canal -->
    <div class="step-pane active" id="paso-1">
            <div class="row text-center">
                <div class="col-md-2 border-end">
                    <small class="text-muted d-block">Venta Proyectada</small>
                    <span class="badge bg-warning text-dark fs-5 fw-bold" id="card-compra-total">0</span>
                </div>
                <div class="col-md-2 border-end">
                    <small class="text-muted d-block">Total Distribuido</small>
                    <span class="badge bg-success fs-5 fw-bold" id="card-total-distribuido">0</span>
                </div>
                <div class="col-md-2 border-end">
                    <small class="text-muted d-block">Canales</small>
                    <span class="badge bg-info text-dark fs-6" id="card-cant-canales">0</span>
                </div>
                <div class="col-md-2 border-end">
                    <small class="text-muted d-block">Rubros</small>
                    <span class="badge bg-secondary fs-6" id="card-cant-rubros">0</span>
                </div>
                <div class="col-md-2 border-end">
                    <small class="text-muted d-block">Categorías</small>
                    <span class="badge bg-secondary fs-6" id="card-cant-categorias">0</span>
                </div>
                <div class="col-md-2">
                    <small class="text-muted d-block">Última Actualización</small>
                    <span class="badge bg-light text-dark border fs-7" id="card-ult-act">--</span>
                </div>
            </div>
        
        <!-- Grilla/Tabla -->
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="tabla-distribucion">
                <thead class="table-dark sticky-header">
                    <tr id="thead-distribucion-row">
                        <th>Rubro</th>
                        <th>Categoría</th>
                        <th class="text-center bg-secondary text-white">Venta Proyectada</th>
                        <th class="text-center bg-dark text-white">Venta Histórica Total</th>
                        <th class="text-center bg-info text-dark">Canal</th>
                        <th class="text-center">Venta del Canal</th>
                        <th class="text-center bg-warning text-dark" style="width: 120px;">Participación %</th>
                        <th class="text-center bg-success text-white">Venta Distribuida</th>
                    </tr>
                </thead>
                <tbody id="tbody-distribucion">
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="fas fa-info-circle mb-2"></i><br>
                            Presione "Cargar / Recalcular" para calcular la distribución por canal
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="step-pane" id="paso-2" style="overflow-y: auto !important; max-height: calc(100vh - 310px) !important; padding-bottom: 20px;">
        <!-- SECCIÓN: TABLA DE COSTOS -->
        <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; overflow: hidden; border-left: 5px solid #0d6efd !important;">
            <div class="card-header bg-light border-0 py-3 d-flex align-items-center justify-content-between">
                <h5 class="mb-0 text-primary fw-bold d-flex align-items-center">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Tabla de Costo
                    <button type="button" class="btn btn-link text-primary p-0 ms-2 lh-1" 
                            data-bs-toggle="popover" 
                            data-bs-trigger="hover focus"
                            data-bs-placement="right" 
                            title="¿Cómo se calcula la Tabla de Costo?" 
                            data-bs-content="Calcula el costo proyectado multiplicando las unidades distribuidas de cada mes por el valor de costo de adquisición (Vcosto = Costo Promedio * (1 + Inc FOB % / 100)).">
                        <i class="far fa-question-circle" style="font-size: 0.95rem;"></i>
                    </button>
                </h5>
                <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="DistribucionManager.toggleCollapseTabla('tabla-costos-container', this)">
                    <i class="fas fa-chevron-up me-1"></i>Colapsar
                </button>
            </div>
            <div class="card-body p-0" id="tabla-costos-container">
                <div class="table-responsive" style="max-height: 450px !important;">
                    <table class="table table-striped table-hover mb-0" id="tabla-costos">
                        <thead class="table-dark sticky-header">
                            <tr id="thead-costos-row">
                                <th>Rubro</th>
                                <th>Categoría</th>
                                <th class="text-center bg-info text-dark">Canal</th>
                                <th class="text-center bg-secondary text-white">Venta Proyectada (U.)</th>
                                <th class="text-center bg-success text-white" style="width: 130px;">Vcosto</th>
                                <!-- Month columns will be dynamically appended here -->
                            </tr>
                        </thead>
                        <tbody id="tbody-costos">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle mb-2"></i><br>
                                    Seleccione una versión y presione "Cargar / Recalcular" para calcular los costos
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SECCIÓN: TABLA DE MARK-UP -->
        <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; overflow: hidden; border-left: 5px solid #198754 !important;">
            <div class="card-header bg-light border-0 py-3 d-flex align-items-center justify-content-between">
                <h5 class="mb-0 text-success fw-bold d-flex align-items-center">
                    <i class="fas fa-percentage me-2"></i>Tabla de Mark-Up
                    <button type="button" class="btn btn-link text-success p-0 ms-2 lh-1" 
                            data-bs-toggle="popover" 
                            data-bs-trigger="hover focus" 
                            data-bs-placement="right"
                            title="¿Cómo se calcula el Mark-Up?" 
                            data-bs-content="Calcula el valor comercial proyectado multiplicando las unidades de cada mes por el Vcosto y luego por el multiplicador decimal de Mark-Up asignado globalmente a ese canal de venta (Local Propio, Franquicias, Mayoristas, Ecommerce).">
                        <i class="far fa-question-circle" style="font-size: 0.95rem;"></i>
                    </button>
                </h5>
                <button class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="DistribucionManager.toggleCollapseTabla('tabla-markup-container', this)">
                    <i class="fas fa-chevron-up me-1"></i>Colapsar
                </button>
            </div>
            <div class="card-body p-0" id="tabla-markup-container">
                <div class="table-responsive" style="max-height: 450px !important;">
                    <table class="table table-striped table-hover mb-0" id="tabla-markup">
                        <thead class="table-dark sticky-header">
                            <tr id="thead-markup-row">
                                <th>Rubro</th>
                                <th>Categoría</th>
                                <th class="text-center bg-info text-dark">Canal</th>
                                <th class="text-center bg-secondary text-white">Venta Proyectada (U.)</th>
                                <th class="text-center bg-success text-white" style="width: 130px;">Vcosto</th>
                                <!-- Month columns will be dynamically appended here -->
                            </tr>
                        </thead>
                        <tbody id="tbody-markup">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle mb-2"></i><br>
                                    Seleccione una versión y presione "Cargar / Recalcular" para calcular el Mark-Up
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- PASO 3: Análisis de Distribución -->
    <div class="step-pane" id="paso-3">
        <div class="card border-0 shadow-sm p-5 text-center mt-3" style="border-radius: 16px; background: linear-gradient(135deg, #ffffff 0%, #f1f3f5 100%);">
            <div class="mb-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle" style="width: 100px; height: 100px;">
                    <i class="fas fa-chart-pie text-success" style="font-size: 3.2rem;"></i>
                </div>
            </div>
            <h3 class="text-success fw-bold mb-2">Paso 3: Análisis de Distribución</h3>
            <p class="text-muted mx-auto mb-4" style="max-width: 600px; font-size: 1.05rem;">
                Consolide e inspeccione reportes de participación de compras, auditoría de desviaciones e informes interactivos de presupuesto final.
            </p>
            <div class="d-inline-flex justify-content-center align-items-center gap-3">
                <span class="badge bg-secondary p-2 px-3 fs-6 rounded-pill"><i class="fas fa-lock me-2"></i>Próximamente</span>
                <button class="btn btn-success btn-sm px-4 rounded-pill text-white" onclick="DistribucionManager.irAPaso(1)">
                    <i class="fas fa-arrow-left me-1"></i> Volver al Paso 1
                </button>
            </div>
        </div>
    </div>

</div>

<!-- MODAL DE PARÁMETROS GLOBALES DE COSTOS -->
<div class="modal fade" id="modal-parametros-costos" tabindex="-1" aria-labelledby="modalParametrosCostosLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <div class="modal-header bg-warning text-dark border-0 py-3" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <h5 class="modal-title fw-bold" id="modalParametrosCostosLabel">
                    <i class="fas fa-sliders-h me-2"></i>Parámetros Globales de Costo por Rubro / Categoría
                </h5>
                <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-3 d-flex align-items-center gap-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fas fa-filter"></i></span>
                        <input type="text" class="form-control" id="buscar-parametro-modal" placeholder="Filtrar parámetros por rubro o categoría..." onkeyup="DistribucionManager.filtrarParametrosModal(this.value)">
                    </div>
                </div>

                <!-- Pestañas internas del Modal -->
                <ul class="nav nav-pills nav-fill mb-3" id="modalParametrosTabs" role="tablist" style="background: #eef2f7; padding: 4px; border-radius: 8px;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold py-2" id="modal-tab-costos" data-bs-toggle="pill" data-bs-target="#modal-pane-costos" type="button" role="tab" aria-controls="modal-pane-costos" aria-selected="true">
                            <i class="fas fa-dollar-sign me-2"></i>Costos Globales
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold py-2" id="modal-tab-markup" data-bs-toggle="pill" data-bs-target="#modal-pane-markup" type="button" role="tab" aria-controls="modal-pane-markup" aria-selected="false">
                            <i class="fas fa-percent me-2"></i>Mark-Up por Canal
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="modalParametrosTabContent">
                    <!-- PANEL DE COSTOS -->
                    <div class="tab-pane fade show active" id="modal-pane-costos" role="tabpanel" aria-labelledby="modal-tab-costos">
                        <div class="table-responsive" id="scroll-costos-modal">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                    <tr class="table-dark">
                                        <th style="min-width:160px;">Rubro</th>
                                        <th style="min-width:180px;">Categoría</th>
                                        <th class="text-center" style="width:140px;">
                                            <div>Costo Prom (U$D)</div>
                                            <button type="button" class="btn btn-xxs btn-light text-dark fw-bold mt-1 px-2" title="Replicar primer valor en todas las filas" onclick="DistribucionManager.replicarPrimerValorModal('costo_prom')">
                                                <i class="fas fa-copy me-1"></i>Replicar
                                            </button>
                                        </th>
                                        <th class="text-center" style="width:130px;">
                                            <div>Inc FOB %</div>
                                            <button type="button" class="btn btn-xxs btn-light text-dark fw-bold mt-1 px-2" title="Replicar primer valor en todas las filas" onclick="DistribucionManager.replicarPrimerValorModal('inc_fob')">
                                                <i class="fas fa-copy me-1"></i>Replicar
                                            </button>
                                        </th>
                                        <th class="text-center text-warning" style="width:130px;">Vcosto (U$D)</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-parametros-globales">
                                    <!-- Parámetros dinámicos aquí -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PANEL DE MARK-UP -->
                    <div class="tab-pane fade" id="modal-pane-markup" role="tabpanel" aria-labelledby="modal-tab-markup">
                        <div class="table-responsive" id="scroll-markup-modal">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                    <tr class="table-dark">
                                        <th style="min-width:150px;">Rubro</th>
                                        <th style="min-width:170px;">Categoría</th>
                                        <th class="text-center" style="width:120px;">
                                            <div>Local Propio</div>
                                            <button type="button" class="btn btn-xxs btn-light text-dark fw-bold mt-1 px-2" title="Replicar primer valor en todas las filas" onclick="DistribucionManager.replicarPrimerValorModal('markup_locales_propios')">
                                                <i class="fas fa-copy me-1"></i>Replicar
                                            </button>
                                        </th>
                                        <th class="text-center" style="width:120px;">
                                            <div>Franquicias</div>
                                            <button type="button" class="btn btn-xxs btn-light text-dark fw-bold mt-1 px-2" title="Replicar primer valor en todas las filas" onclick="DistribucionManager.replicarPrimerValorModal('markup_franquicias')">
                                                <i class="fas fa-copy me-1"></i>Replicar
                                            </button>
                                        </th>
                                        <th class="text-center" style="width:120px;">
                                            <div>Mayoristas</div>
                                            <button type="button" class="btn btn-xxs btn-light text-dark fw-bold mt-1 px-2" title="Replicar primer valor en todas las filas" onclick="DistribucionManager.replicarPrimerValorModal('markup_mayoristas')">
                                                <i class="fas fa-copy me-1"></i>Replicar
                                            </button>
                                        </th>
                                        <th class="text-center" style="width:120px;">
                                            <div>Ecommerce</div>
                                            <button type="button" class="btn btn-xxs btn-light text-dark fw-bold mt-1 px-2" title="Replicar primer valor en todas las filas" onclick="DistribucionManager.replicarPrimerValorModal('markup_ecommerce')">
                                                <i class="fas fa-copy me-1"></i>Replicar
                                            </button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-parametros-markup">
                                    <!-- Inputs de markup dinámicos aquí -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-3" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning btn-sm fw-bold text-dark rounded-pill px-4" onclick="DistribucionManager.guardarParametrosModal()">
                    <i class="fas fa-save me-1"></i>Guardar Parámetros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Inicializador de Popovers de Información -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl)
        })
    });
</script>

