
<!-- Solapa: Detalle de Compras Pendientes -->
<div class="tab-pane fade" id="compras-detalle" role="tabpanel">
    <div class="search-container">
        <!-- Primera fila: Búsqueda y controles principales -->
        <div class="row align-items-center mb-2">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control search-input-fast" id="search-compras-detalle" 
                        placeholder="Buscar por OC, artículo, proveedor..." 
                        onkeyup="buscarComprasDetalle()">
                </div>
            </div>
            <div class="col-md-2">
                <span class="badge bg-info fs-6 contador-animado" id="count-compras-detalle">0 registros</span>
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm" id="filtro-proveedor" onchange="filtrarPorProveedor()">
                    <option value="">Todos los proveedores</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="filtro-rubro" onchange="filtrarPorRubro()">
                    <option value="">Todos los rubros</option>
                </select>
            </div>
            <div class="col-md-2 text-end export-buttons">
                <button class="btn btn-success btn-sm" onclick="ComprasManager.exportarExcel()">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
                <button class="btn btn-primary btn-sm" onclick="ComprasManager.cargarDatos()">
                    <i class="fas fa-sync-alt me-1"></i> Cargar
                </button>
            </div>
        </div>
        
        <!-- Segunda fila: Filtros adicionales -->
        <div class="row">
            <div class="col-md-3">
                <label class="form-label-sm">Fecha desde:</label>
                <input type="date" class="form-control form-control-sm" id="fecha-desde" onchange="filtrarPorFecha()">
            </div>
            <div class="col-md-3">
                <label class="form-label-sm">Fecha hasta:</label>
                <input type="date" class="form-control form-control-sm" id="fecha-hasta" onchange="filtrarPorFecha()">
            </div>
            <div class="col-md-3">
                <label class="form-label-sm">Temporada:</label>
                <select class="form-select form-select-sm" id="filtro-temporada" onchange="filtrarPorTemporada()">
                    <option value="">Todas las temporadas</option>
                    <option value="verano">Solo Verano</option>
                    <option value="invierno">Solo Invierno</option>
                    <option value="atemporal">Solo Atemporal</option>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-secondary btn-sm" onclick="ComprasManager.limpiarFiltros()">
                    <i class="fas fa-eraser me-1"></i> Limpiar Filtros
                </button>
            </div>
        </div>
    </div>
    
    <!-- Resumen de totales en la parte superior (siempre visible) -->
    <div class="bg-light p-2 border-bottom d-none" id="resumen-compras-superior">
        <div class="row text-center">
            <div class="col-3">
                <small class="text-muted d-block">Total Verano</small>
                <span class="badge bg-warning text-dark fs-6" id="badge-total-verano">0</span>
            </div>
            <div class="col-3">
                <small class="text-muted d-block">Total Invierno</small>
                <span class="badge bg-info fs-6" id="badge-total-invierno">0</span>
            </div>
            <div class="col-3">
                <small class="text-muted d-block">Total Atemporal</small>
                <span class="badge bg-success fs-6" id="badge-total-atemporal">0</span>
            </div>
            <div class="col-3">
                <small class="text-muted d-block">Total General</small>
                <span class="badge bg-primary fs-6" id="badge-total-general">0</span>
            </div>
        </div>
    </div>
    
    <!-- Tabla con altura optimizada -->
    <div class="table-responsive" style="max-height: calc(100vh - 280px); overflow-y: auto;">
        <table class="table table-striped table-hover mb-0" id="tabla-compras-detalle">
            <thead class="table-dark sticky-header">
                <tr>
                    <th class="text-center">Fecha<br>Emisión</th>
                    <th class="text-center">N° Orden</th>
                    <th>Proveedor</th>
                    <th class="text-center">Cod.<br>Artículo</th>
                    <th>Descripción</th>
                    <th>Rubro</th>
                    <th>Categoría</th>
                    <th class="text-center bg-warning text-dark">Verano</th>
                    <th class="text-center bg-info text-dark">Invierno</th>
                    <th class="text-center bg-success text-white">Atemporal</th>
                    <th class="text-center bg-primary text-white">Total</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-compras-detalle">
                <tr>
                    <td colspan="12" class="text-center text-muted py-4">
                        <i class="fas fa-info-circle"></i>
                        Presione "Cargar" para obtener el detalle de compras pendientes
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>