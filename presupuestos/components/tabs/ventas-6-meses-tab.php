
<!-- Solapa: Ventas 6 Meses -->
<div class="tab-pane fade" id="ventas-6-meses" role="tabpanel">
    <div class="search-container">
        <!-- Primera fila: Búsqueda y controles principales -->
        <div class="row align-items-center mb-2">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control search-input-fast" id="search-ventas-6-meses" 
                        placeholder="Buscar por rubro o categoría..." 
                        onkeyup="buscarVentas6Meses()">
                </div>
            </div>
            <div class="col-md-2">
                <span class="badge bg-info fs-6 contador-animado" id="count-ventas-6-meses">0 registros</span>
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm" id="filtro-rubro-ventas" onchange="filtrarPorRubroVentas()">
                    <option value="">Todos los rubros</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="filtro-categoria-ventas" onchange="filtrarPorCategoriaVentas()">
                    <option value="">Todas las categorías</option>
                </select>
            </div>
            <div class="col-md-2 text-end export-buttons">
                <button class="btn btn-success btn-sm" onclick="VentasManager.exportarExcel()">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
                <button class="btn btn-primary btn-sm" onclick="VentasManager.cargarDatos()">
                    <i class="fas fa-sync-alt me-1"></i> Cargar
                </button>
            </div>
        </div>
        
        <!-- Segunda fila: Limpiar filtros -->
        <div class="row">
            <div class="col-md-12 text-end">
                <button class="btn btn-outline-secondary btn-sm" onclick="VentasManager.limpiarFiltros()">
                    <i class="fas fa-eraser me-1"></i> Limpiar Filtros
                </button>
            </div>
        </div>
    </div>
    
    <!-- Resumen de ventas en la parte superior (siempre visible) -->
    <div class="bg-light p-2 border-bottom d-none" id="resumen-ventas-superior">
        <div class="row text-center">
            <div class="col-3">
                <small class="text-muted d-block">Ventas Actuales (60 días)</small>
                <span class="badge bg-primary fs-6" id="badge-ventas-actuales">0</span>
            </div>
            <div class="col-3">
                <small class="text-muted d-block">Año Anterior</small>
                <span class="badge bg-secondary fs-6" id="badge-ventas-anteriores">0</span>
            </div>
            <div class="col-3">
                <small class="text-muted d-block">Variación Total</small>
                <span class="badge bg-info fs-6" id="badge-variacion-total">0%</span>
            </div>
            <div class="col-3">
                <small class="text-muted d-block">Items (↗Mejor | →Igual | ↘Peor)</small>
                <span class="badge bg-warning text-dark fs-6" id="badge-estadisticas">0|0|0</span>
            </div>
        </div>
    </div>
    
    <!-- Tabla SIN altura fija inline - igual que las otras solapas -->
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0" id="tabla-ventas-6-meses">
            <thead class="table-dark sticky-header">
                <tr>
                    <th>Rubro</th>
                    <th>Categoría</th>
                    <th class="text-center bg-primary text-white">Ventas<br>Últimos 60 días</th>
                    <th class="text-center bg-secondary text-white">Ventas<br>Año Anterior</th>
                    <th class="text-center bg-warning text-dark">Índice<br>Variación</th>
                    <!-- Columnas de meses se generan dinámicamente -->
                    <th class="text-center bg-info text-white">Mes 1</th>
                    <th class="text-center bg-info text-white">Mes 2</th>
                    <th class="text-center bg-info text-white">Mes 3</th>
                    <th class="text-center bg-info text-white">Mes 4</th>
                    <th class="text-center bg-info text-white">Mes 5</th>
                    <th class="text-center bg-info text-white">Mes 6</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-ventas-6-meses">
                <tr>
                    <td colspan="15" class="text-center text-muted py-4">
                        <i class="fas fa-info-circle"></i>
                        Presione "Cargar" para obtener los datos de ventas de los últimos 6 meses
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>