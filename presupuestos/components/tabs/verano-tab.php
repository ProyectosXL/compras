
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
            <div class="col-md-3 text-end export-buttons">
                <button class="btn btn-success btn-sm" onclick="exportarExcel('verano')">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
            </div>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0" id="tabla-verano">
            <thead class="table-dark sticky-header">
                <tr>
                    <th>Rubro</th>
                    <th>Categoría</th>
                    <th class="text-center bg-info">Stock<br>Proyectado</th>
                    <th class="text-center bg-warning">Índice<br>Variación</th>
                    <th class="text-center header-venta-anterior" id="header-venta-verano-ant">Venta Ver.<br>Anterior</th>
                    <th class="text-center header-venta-proyectada" id="header-venta-verano">Venta Proy.<br>Verano</th>
                    <th class="text-center header-venta-anterior" id="header-venta-invierno-ant">Venta Inv.<br>Anterior</th>
                    <th class="text-center header-venta-proyectada" id="header-venta-invierno">Venta Proy.<br>Invierno</th>
                    <th class="text-center header-compra-proyectada">Compra<br>Proyectada</th>
                </tr>
            </thead>
            <tbody id="tbody-verano">
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        <i class="fas fa-info-circle"></i>
                        Cargue los datos para ver la compra proyectada de verano
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>