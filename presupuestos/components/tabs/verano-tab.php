
<div class="tab-pane fade show active" id="verano" role="tabpanel">
    <div class="search-container">
        <div class="row align-items-center mb-2">
            <div class="col-md-3">
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
                <select class="form-select form-select-sm" id="filtro-rubro-verano" onchange="filtrarPorRubroPresupuesto('verano')">
                    <option value="">Todos los rubros</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="filtro-categoria-verano" onchange="filtrarPorCategoriaPresupuesto('verano')">
                    <option value="">Todas las categorías</option>
                </select>
            </div>
            <div class="col-md-2 text-end export-buttons">
                <button class="btn btn-primary btn-sm" id="btn-guardar-verano" data-temporada="verano">
                    <i class="fas fa-save me-1"></i> Guardar
                </button>
                <button class="btn btn-success btn-sm" onclick="exportarExcel('verano')">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="limpiarFiltrosPresupuesto('verano')">
                    <i class="fas fa-eraser me-1"></i> Limpiar
                </button>
            </div>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0" id="tabla-verano">
            <thead class="table-dark sticky-header">
                <tr>
                    <th data-col="rubro">Rubro</th>
                    <th data-col="categoria">Categoría</th>
                    <th class="text-center bg-info" data-col="stock-proyectado">Stock<br>Proyectado</th>
                    <th class="text-center bg-secondary text-white" data-col="indice-original"
                        title="Índice editable tal como vino del SP, antes de cualquier edición. Es el valor al que vuelve Restaurar.">Índice Var.<br>Original</th>
                    <th class="text-center bg-secondary-subtle text-dark" data-col="indice-ver-lp" data-indice-tipo="lp">Índice Ver.<br>LP</th>
                    <th class="text-center bg-warning" data-col="indice-ver-te" data-indice-tipo="te">Índice Ver.<br>Total Empresa</th>
                    <th class="text-center header-venta-anterior" data-col="venta-ver-ant" id="header-venta-verano-ant">Venta Ver.<br>Anterior</th>
                    <th class="text-center header-venta-proyectada" data-col="venta-proy-ver" id="header-venta-verano">Venta Proy.<br>Verano</th>
                    <th class="text-center bg-secondary-subtle text-dark" data-col="indice-inv-lp" data-indice-tipo="lp">Índice Inv.<br>LP</th>
                    <th class="text-center bg-warning" data-col="indice-inv-te" data-indice-tipo="te">Índice Inv.<br>Total Empresa</th>
                    <th class="text-center header-venta-anterior" data-col="venta-inv-ant" id="header-venta-invierno-ant">Venta Inv.<br>Anterior</th>
                    <th class="text-center header-venta-proyectada" data-col="venta-proy-inv" id="header-venta-invierno">Venta Proy.<br>Invierno</th>
                    <th class="text-center header-compra-proyectada" data-col="compra-proyectada">Compra<br>Proyectada</th>
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