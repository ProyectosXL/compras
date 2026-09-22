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

    <!-- Versiones guardadas: una fila por versión, no por rubro.
         Es la vista sobre la que se marca cuál es la oficial de cada temporada.
         Colapsable porque la lista crece con cada guardado y no tiene sentido que
         se coma el alto de la tabla de detalle cuando no se la está usando. -->
    <div class="card mb-2">
        <div class="card-header py-1 d-flex align-items-center justify-content-between">
            <button class="btn btn-link btn-sm p-0 text-decoration-none fw-bold"
                    type="button" data-bs-toggle="collapse" data-bs-target="#panel-versiones"
                    aria-expanded="false" aria-controls="panel-versiones">
                <i class="fas fa-chevron-right me-1 icono-colapso"></i>
                <i class="fas fa-code-branch me-1"></i> Versiones guardadas
            </button>
            <div>
                <span class="badge bg-info text-dark me-2" id="count-versiones">0 versiones</span>
                <button class="btn btn-outline-primary btn-sm" id="btn-cargar-versiones">
                    <i class="fas fa-sync-alt me-1"></i> Cargar
                </button>
            </div>
        </div>
        <div class="collapse" id="panel-versiones">
            <!-- data-altura-fija: esta tabla conserva su alto y no entra en el
                 reparto que hace ajustarAltura(), que es para la tabla principal. -->
            <div class="table-responsive" data-altura-fija style="max-height: 230px; overflow-y: auto;">
                <table class="table table-sm table-hover mb-0" id="tabla-versiones">
                    <thead class="table-light sticky-header">
                        <tr>
                            <th>Guardado</th>
                            <th>Nombre</th>
                            <th>Solapa</th>
                            <th>Temporada objetivo</th>
                            <th class="text-center">Alcance</th>
                            <th class="text-center">Oficial</th>
                            <th>Marcada por</th>
                            <th class="text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-versiones">
                        <tr>
                            <td colspan="8" class="text-center text-muted py-3">
                                <i class="fas fa-info-circle me-1"></i>
                                Presioná "Cargar" para ver las versiones guardadas
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover table-sm mb-0" id="tabla-historial">
            <thead class="table-dark sticky-header">
                <tr>
                    <th>Fecha Guardado</th>
                    <th>Nombre Presupuesto</th>
                    <th>Solapa</th>
                    <th>Temporada objetivo</th>
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
