
<!-- Solapa: Detalle de Compras Pendientes -->
<div class="tab-pane fade" id="compras-detalle" role="tabpanel">
    <div class="search-container">
        <div class="row align-items-center">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control search-input-fast" id="search-compras-detalle" 
                        placeholder="Buscar por OC, artículo, proveedor..." 
                        onkeyup="buscarDatos('compras-detalle')">
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
                <button class="btn btn-success btn-sm" onclick="exportarExcel('compras-detalle')">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
                <button class="btn btn-info btn-sm" onclick="mostrarResumenCompras()">
                    <i class="fas fa-chart-pie me-1"></i> Resumen
                </button>
            </div>
        </div>
        
        <!-- Fila adicional con filtros de fecha -->
        <div class="row mt-2">
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
                <button class="btn btn-outline-secondary btn-sm" onclick="limpiarFiltros()">
                    <i class="fas fa-eraser me-1"></i> Limpiar Filtros
                </button>
            </div>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0" id="tabla-compras-detalle">
            <thead class="table-dark sticky-header">
                <tr>
                    <th>Fecha Emisión</th>
                    <th>N° Orden</th>
                    <th>Proveedor</th>
                    <th>Cod. Artículo</th>
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
                        Cargue los datos para ver el detalle de compras pendientes
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Resumen en la parte inferior -->
    <div class="mt-3 p-3 bg-light rounded d-none" id="resumen-compras-detalle">
        <div class="row">
            <div class="col-md-3 text-center">
                <h6>Total Verano</h6>
                <span class="h4 text-warning" id="total-verano">0</span>
            </div>
            <div class="col-md-3 text-center">
                <h6>Total Invierno</h6>
                <span class="h4 text-info" id="total-invierno">0</span>
            </div>
            <div class="col-md-3 text-center">
                <h6>Total Atemporal</h6>
                <span class="h4 text-success" id="total-atemporal">0</span>
            </div>
            <div class="col-md-3 text-center">
                <h6>Total General</h6>
                <span class="h4 text-primary" id="total-general">0</span>
            </div>
        </div>
    </div>
</div>