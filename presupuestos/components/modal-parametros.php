<!-- Modal de Parámetros (solo Argentina). El contenido lo arma js/parametros-manager.js -->
<div class="modal fade" id="modalParametros" tabindex="-1" aria-labelledby="modalParametrosLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow" style="border-radius: 16px;">
            <div class="modal-header bg-warning text-dark border-0 py-3" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <h5 class="modal-title fw-bold" id="modalParametrosLabel">
                    <i class="fas fa-cog me-2"></i>Parámetros del Presupuesto de Compras
                </h5>
                <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <div id="parametros-aviso-recarga" class="alert alert-info py-2 small d-none d-flex align-items-center justify-content-between">
                    <span><i class="fas fa-info-circle me-1"></i>
                        Los cambios de <strong>Generales</strong> y <strong>Cobertura</strong> se aplican al volver a cargar los datos.</span>
                    <button type="button" class="btn btn-sm btn-primary" id="parametros-btn-recargar">
                        <i class="fas fa-sync-alt me-1"></i>Cargar datos
                    </button>
                </div>
                <div id="parametros-sin-tablas" class="alert alert-warning py-2 small d-none">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Todavía no están las tablas de parámetros (falta correr <code>sql/08_parametros.sql</code>).
                    La aplicación usa los valores de fábrica que se ven abajo y no se pueden modificar.
                </div>

                <ul class="nav nav-pills nav-fill mb-3" role="tablist" style="background: #eef2f7; padding: 4px; border-radius: 8px;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold py-2" data-bs-toggle="pill" data-bs-target="#param-pane-generales" type="button" role="tab">
                            <i class="fas fa-sliders-h me-2"></i>Generales
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold py-2" data-bs-toggle="pill" data-bs-target="#param-pane-cobertura" type="button" role="tab">
                            <i class="fas fa-layer-group me-2"></i>Cobertura por rubro / categoría
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold py-2" data-bs-toggle="pill" data-bs-target="#param-pane-exclusiones" type="button" role="tab">
                            <i class="fas fa-ban me-2"></i>Exclusiones del cálculo
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold py-2" data-bs-toggle="pill" data-bs-target="#param-pane-historial" type="button" role="tab">
                            <i class="fas fa-history me-2"></i>Historial de cambios
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="param-pane-generales" role="tabpanel"></div>
                    <div class="tab-pane fade" id="param-pane-cobertura" role="tabpanel"></div>
                    <div class="tab-pane fade" id="param-pane-exclusiones" role="tabpanel"></div>
                    <div class="tab-pane fade" id="param-pane-historial" role="tabpanel"></div>
                </div>
            </div>
        </div>
    </div>
</div>
