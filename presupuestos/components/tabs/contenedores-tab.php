
<!-- Solapa: Contenedores -->
<div class="tab-pane fade" id="contenedores" role="tabpanel">
    <div class="search-container">
        <!-- Primera fila: Búsqueda y controles principales -->
        <div class="row align-items-center mb-2">
            <div class="col-md-2">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control search-input-fast" id="search-contenedores"
                        placeholder="Buscar contenedor, proveedor o rubro..."
                        onkeyup="buscarContenedores()">
                </div>
            </div>
            <div class="col-md-1">
                <span class="badge bg-info fs-6 contador-animado" id="count-contenedores">0 registros</span>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="filtro-proveedor-cont" onchange="filtrarPorProveedorContenedores()">
                    <option value="">Todos los proveedores</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="filtro-rubro-cont" onchange="filtrarPorRubroContenedores()">
                    <option value="">Todos los rubros</option>
                </select>
            </div>
            <div class="col-md-3">
                <div class="btn-group btn-group-sm w-100" role="group" id="grupo-modos-cont">
                    <button type="button" class="btn btn-outline-secondary active"
                            onclick="ContenedoresManager.cambiarModo('detalle')" data-modo="detalle">
                        <i class="fas fa-list"></i> Detalle
                    </button>
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="ContenedoresManager.cambiarModo('rubro')" data-modo="rubro">
                        Rubro
                    </button>
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="ContenedoresManager.cambiarModo('proveedor')" data-modo="proveedor">
                        Proveedor
                    </button>
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="ContenedoresManager.cambiarModo('contenedor')" data-modo="contenedor">
                        Contenedor
                    </button>
                </div>
            </div>
            <div class="col-md-2 text-end export-buttons">
                <button class="btn btn-success btn-sm" onclick="ContenedoresManager.exportarExcel()">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
                <button class="btn btn-primary btn-sm" onclick="ContenedoresManager.cargarDatos()">
                    <i class="fas fa-sync-alt me-1"></i> Cargar
                </button>
            </div>
        </div>

        <!-- Segunda fila: Filtros adicionales -->
        <div class="row">
            <div class="col-md-3">
                <label class="form-label-sm">Fecha desde:</label>
                <input type="date" class="form-control form-control-sm" id="fecha-desde-cont" onchange="filtrarPorFechaContenedores()">
            </div>
            <div class="col-md-3">
                <label class="form-label-sm">Fecha hasta:</label>
                <input type="date" class="form-control form-control-sm" id="fecha-hasta-cont" onchange="filtrarPorFechaContenedores()">
            </div>
            <div class="col-md-3">
                <label class="form-label-sm">Temporada:</label>
                <select class="form-select form-select-sm" id="filtro-temporada-cont" onchange="filtrarPorTemporadaContenedores()">
                    <option value="">Todas las temporadas</option>
                    <option value="verano">Solo Verano</option>
                    <option value="invierno">Solo Invierno</option>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-secondary btn-sm" onclick="ContenedoresManager.limpiarFiltros()">
                    <i class="fas fa-eraser me-1"></i> Limpiar Filtros
                </button>
            </div>
        </div>
    </div>

    <!-- Resumen de totales en la parte superior (siempre visible) -->
    <!-- Mismo componente que el resto de las solapas. Los IDs se conservan:
         ContenedoresManager solo escribe el texto adentro. -->
    <div class="resumen-superior resumen-superior--compras d-none" id="resumen-contenedores-superior">
        <div class="resumen-item">
            <span class="resumen-item__label">Verano</span>
            <span class="resumen-item__valor" id="badge-cont-verano">0</span>
        </div>
        <div class="resumen-item">
            <span class="resumen-item__label">Invierno</span>
            <span class="resumen-item__valor" id="badge-cont-invierno">0</span>
        </div>
        <div class="resumen-item resumen-item--fin">
            <span class="resumen-item__label">Total</span>
            <span class="resumen-item__valor" id="badge-cont-total">0</span>
        </div>
    </div>

    <!-- Tabla con altura optimizada -->
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0" id="tabla-contenedores">
            <thead class="table-dark sticky-header" id="thead-contenedores">
                <tr>
                    <th class="text-center">Contenedor</th>
                    <th>Proveedor</th>
                    <th>Rubro</th>
                    <th class="text-center bg-warning text-dark">Verano</th>
                    <th class="text-center bg-info text-dark">Invierno</th>
                    <th class="text-center bg-primary text-white">Total</th>
                </tr>
            </thead>
            <tbody id="tbody-contenedores">
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="fas fa-ship"></i>
                        Presione "Cargar" para obtener el detalle de contenedores
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
