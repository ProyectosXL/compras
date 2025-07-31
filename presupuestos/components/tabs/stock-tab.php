
<div class="tab-pane fade" id="stock" role="tabpanel">
    <div class="search-container">
        <div class="row align-items-center mb-2">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control search-input-fast" id="search-stock" 
                        placeholder="Búsqueda instantánea por rubro o categoría..." 
                        onkeyup="buscarDatos('stock')">
                </div>
            </div>
            <div class="col-md-2">
                <span class="badge bg-info fs-6 contador-animado" id="count-stock">0 registros</span>
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm" id="filtro-rubro-stock" onchange="filtrarPorRubroPresupuesto('stock')">
                    <option value="">Todos los rubros</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="filtro-categoria-stock" onchange="filtrarPorCategoriaPresupuesto('stock')">
                    <option value="">Todas las categorías</option>
                </select>
            </div>
            <div class="col-md-2 text-end export-buttons">
                <button class="btn btn-success btn-sm" onclick="exportarExcel('stock')">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="limpiarFiltrosPresupuesto('stock')">
                    <i class="fas fa-eraser me-1"></i> Limpiar
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