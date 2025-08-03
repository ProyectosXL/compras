
<!-- Modal de ayuda con instructivo -->
<div class="modal fade" id="ayudaRubrosModal" tabindex="-1" aria-labelledby="ayudaRubrosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-lg-down modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="ayudaRubrosModalLabel">
                    <i class="fas fa-question-circle me-2"></i>
                    Ayuda del Sistema de Cálculo de Rubros
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-2 p-md-3">
                <div class="container-fluid px-0">
                    <!-- Tabs de navegación -->
                    <ul class="nav nav-tabs flex-wrap" id="ayudaTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                                <i class="fas fa-info-circle me-1 d-none d-sm-inline"></i> 
                                <span class="d-none d-sm-inline">Resumen</span>
                                <span class="d-sm-none">Info</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="calculos-tab" data-bs-toggle="tab" data-bs-target="#calculos" type="button" role="tab">
                                <i class="fas fa-calculator me-1 d-none d-sm-inline"></i> Cálculos
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="parametros-tab" data-bs-toggle="tab" data-bs-target="#parametros" type="button" role="tab">
                                <i class="fas fa-cogs me-1 d-none d-sm-inline"></i> 
                                <span class="d-none d-sm-inline">Parámetros</span>
                                <span class="d-sm-none">Config</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="ejemplos-tab" data-bs-toggle="tab" data-bs-target="#ejemplos" type="button" role="tab">
                                <i class="fas fa-chart-line me-1 d-none d-sm-inline"></i> Ejemplos
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Contenido de las tabs con altura controlada -->
                    <div class="tab-content mt-2" id="ayudaTabsContent" style="max-height: 70vh; overflow-y: auto;">
                        <!-- Tab 1: Resumen General -->
                        <?php include 'modal-ayuda/tab-overview.php'; ?>
                        
                        <!-- Tab 2: Cálculos -->
                        <?php include 'modal-ayuda/tab-calculos.php'; ?>
                        
                        <!-- Tab 3: Parámetros -->
                        <?php include 'modal-ayuda/tab-parametros.php'; ?>
                        
                        <!-- Tab 4: Ejemplos -->
                        <?php include 'modal-ayuda/tab-ejemplos.php'; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cerrar
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> <span class="d-none d-sm-inline">Imprimir</span>
                </button>
            </div>
        </div>
    </div>
</div>