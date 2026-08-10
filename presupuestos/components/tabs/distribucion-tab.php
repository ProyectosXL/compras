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
            <div class="step-label">Costo de Proyección</div>
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
                <button class="btn btn-dark btn-sm" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Imprimir
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

    <!-- PASO 2: Costo de Proyección -->
    <div class="step-pane" id="paso-2">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="tabla-costos">
                <thead class="table-dark sticky-header">
                    <tr id="thead-costos-row">
                        <th>Rubro</th>
                        <th>Categoría</th>
                        <th class="text-center bg-secondary text-white">Venta Proyectada</th>
                        <th class="text-center bg-info text-dark" style="width: 130px;">Costo Prom</th>
                        <th class="text-center bg-warning text-dark" style="width: 130px;">Inc Fob %</th>
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
