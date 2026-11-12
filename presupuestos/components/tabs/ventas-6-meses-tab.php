
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
                <div id="filtro-rubro-ventas"></div>
            </div>
            <div class="col-md-2">
                <div id="filtro-categoria-ventas"></div>
            </div>
            <!-- Una acción principal a la vista y el resto en "Más acciones", como en las solapas de compra. -->
            <div class="col-md-2 d-flex justify-content-end gap-1">
                <button class="btn btn-primary btn-sm" onclick="VentasManager.cargarDatos()">
                    <i class="fas fa-sync-alt me-1"></i> Cargar
                </button>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Más acciones
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button class="dropdown-item" type="button" onclick="VentasManager.exportarExcel()">
                            <i class="fas fa-file-excel fa-fw me-2 text-success"></i>Exportar Excel</button></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button class="dropdown-item" type="button" onclick="VentasManager.limpiarFiltros()">
                            <i class="fas fa-eraser fa-fw me-2"></i>Limpiar filtros</button></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Resumen de ventas en la parte superior (siempre visible) -->
    <!-- Mismo componente que el resto de las solapas. Los IDs se conservan:
         VentasManager solo escribe el texto adentro. -->
    <div class="resumen-superior resumen-superior--ventas d-none" id="resumen-ventas-superior">
        <div class="resumen-item">
            <span class="resumen-item__label">Últimos 60 días</span>
            <span class="resumen-item__valor" id="badge-ventas-actuales">0</span>
        </div>
        <div class="resumen-item">
            <span class="resumen-item__label">Mismo período año anterior</span>
            <span class="resumen-item__valor" id="badge-ventas-anteriores">0</span>
        </div>
        <div class="resumen-item">
            <span class="resumen-item__label">Variación</span>
            <span class="resumen-item__valor" id="badge-variacion-total">0%</span>
        </div>
        <div class="resumen-item resumen-item--fin" title="Rubro/categoría que mejoraron, se mantuvieron o empeoraron respecto del año anterior.">
            <span class="resumen-item__label">↗ Mejor · → Igual · ↘ Peor</span>
            <span class="resumen-item__valor" id="badge-estadisticas">0|0|0</span>
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