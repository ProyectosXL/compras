
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
                <div id="filtro-rubro-stock"></div>
            </div>
            <div class="col-md-2">
                <div id="filtro-categoria-stock"></div>
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
    
    <!-- Solo Argentina: de qué solapa salen los índices con los que se calcula la cobertura.
         Lo muestra/oculta js/stock-sincronizado.js. -->
    <div id="stock-selector-indices" class="d-flex flex-wrap align-items-center gap-2 mb-2 small d-none">
        <span class="text-muted">Calcular con índices de:</span>
        <div class="btn-group btn-group-sm" role="group" id="stock-indices-grupo">
            <input type="radio" class="btn-check" name="stock-indices" id="stock-indices-verano" value="verano" checked>
            <label class="btn btn-outline-primary" for="stock-indices-verano">Verano</label>
            <input type="radio" class="btn-check" name="stock-indices" id="stock-indices-invierno" value="invierno">
            <label class="btn btn-outline-primary" for="stock-indices-invierno">Invierno</label>
        </div>
        <span id="stock-aviso-indices" class="text-warning-emphasis"></span>
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
                    <th class="text-center bg-warning text-dark d-none" data-col="indice-cobertura"
                        title="Índice de cobertura en meses de venta. Es uno solo por categoría: el mismo de Verano e Invierno. Clic en la celda para editarlo.">Índice<br>Cobertura</th>
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