
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