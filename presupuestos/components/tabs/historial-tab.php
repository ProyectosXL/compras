<!-- Solapa: Historial de Compras Proyectadas -->
<div class="tab-pane fade" id="historial" role="tabpanel">
    <div class="search-container">
        <!-- Fila de Filtros -->
        <div class="row align-items-center mb-2">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" id="search-historial" placeholder="Buscar en rubro o categoría...">
                </div>
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control" id="filtro-rubro-historial" placeholder="Filtrar por rubro...">
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control" id="filtro-categoria-historial" placeholder="Filtrar por categoría...">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="filtro-fecha-desde-historial">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="filtro-fecha-hasta-historial">
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary" id="btn-buscar-historial">
                    <i class="fas fa-search me-1"></i> Buscar
                </button>
            </div>
        </div>
    </div>

    <div class="table-responsive" style="max-height: calc(100vh - 250px); overflow-y: auto;">
        <table class="table table-striped table-hover table-sm mb-0" id="tabla-historial">
            <thead class="table-dark sticky-header">
                <tr>
                    <th>Fecha Guardado</th>
                    <th>Nombre Presupuesto</th>
                    <th>Temporada</th>
                    <th>Rubro</th>
                    <th>Categoría</th>
                    <th class="text-end">Stock Proy.</th>
                    <th class="text-center bg-warning-subtle text-dark">Índice Var. Verano</th>
                    <th class="text-end">Venta Verano Ant.</th>
                    <th class="text-center bg-primary-subtle text-dark">Venta Proy. Verano</th>
                    <th class="text-center bg-warning-subtle text-dark">Índice Var. Invierno</th>
                    <th class="text-end">Venta Invierno Ant.</th>
                    <th class="text-center bg-primary-subtle text-dark">Venta Proy. Invierno</th>
                    <th class="text-center bg-success-subtle text-dark">Compra Proyectada</th>
                </tr>
            </thead>
            <tbody id="tbody-historial">
                <tr>
                    <td colspan="13" class="text-center text-muted py-4">
                        <i class="fas fa-filter"></i>
                        Utilice los filtros y presione "Buscar" para ver el historial.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
