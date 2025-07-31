
<!-- Header Compacto -->
<div class="row">
    <div class="col-12">
        <div class="header-compacto">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1>
                            <i class="fas fa-calculator me-2"></i>
                            <span id="titulo-sistema">Sistema de Presupuesto de Compras - Argentina</span>
                        </h1>
                        <div class="country-switch mt-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="country-switch" onchange="cambiarPais()">
                                <label class="form-check-label text-white" for="country-switch" id="country-label">
                                    <i class="fas fa-flag me-1"></i>Argentina
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="export-buttons">
                        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#ayudaRubrosModal">
                            <i class="fas fa-question-circle me-1"></i> Ayuda
                        </button>
                        <button type="button" class="btn btn-light" onclick="cargarDatos()">
                            <i class="fas fa-sync-alt me-1"></i> Cargar Datos
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportarExcel('completo')" 
                                style="display: none;" id="btn-export-completo-header">
                            <i class="fas fa-file-excel me-1"></i> Excel Completo
                        </button>
                        <button class="btn btn-outline-light btn-sm" onclick="mostrarHistorialIndices()" title="Ver historial de cambios de índices">
                            <i class="fas fa-history"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>