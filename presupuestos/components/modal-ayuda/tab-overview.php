
<div class="tab-pane fade show active" id="overview" role="tabpanel">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-primary">
                        <i class="fas fa-bullseye me-2"></i>
                        Objetivo del Sistema
                    </h5>
                    <p class="card-text">
                        El sistema de cálculo de rubros analiza los artículos importados para determinar las cantidades óptimas de compra, 
                        basándose en el historial de ventas, stock actual y proyecciones de demanda.
                    </p>
                </div>
            </div>

            <!-- Solapas del Sistema -->
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-layer-group me-2"></i>Solapas del Sistema</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <i class="fas fa-sun fa-2x text-warning"></i>
                                <h6 class="mt-2">Compra Proy. Verano</h6>
                                <p class="small">Optimizada para planificar compras de productos de temporada verano. Aplica lógica específica según período actual.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <i class="fas fa-snowflake fa-2x text-info"></i>
                                <h6 class="mt-2">Compra Proy. Invierno</h6>
                                <p class="small">Enfocada en productos de temporada invierno. Calcula ventas proyectadas con método diferenciado.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <i class="fas fa-boxes fa-2x text-success"></i>
                                <h6 class="mt-2">Stock Proyectado</h6>
                                <p class="small">Muestra el estado consolidado del inventario considerando todas las variables del sistema.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-success">
                        <strong>💡 Ventaja Clave:</strong> Cada solapa usa algoritmos específicos que se adaptan automáticamente a la temporada actual, proporcionando proyecciones más precisas según el contexto de negocio.
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title text-success">
                                <i class="fas fa-check-circle me-2"></i>
                                Datos de Entrada
                            </h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-dot-circle me-2 text-primary"></i> Stock actual disponible</li>
                                <li><i class="fas fa-dot-circle me-2 text-primary"></i> Stock reservado (guardar)</li>
                                <li><i class="fas fa-dot-circle me-2 text-primary"></i> Ventas de 6 temporadas anteriores</li>
                                <li><i class="fas fa-dot-circle me-2 text-primary"></i> Órdenes de compra pendientes</li>
                                <li><i class="fas fa-dot-circle me-2 text-primary"></i> Índice de variación de ventas</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title text-warning">
                                <i class="fas fa-chart-line me-2"></i>
                                Resultados Calculados
                            </h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-arrow-right me-2 text-success"></i> Stock proyectado</li>
                                <li><i class="fas fa-arrow-right me-2 text-success"></i> Ventas proyectadas por temporada</li>
                                <li><i class="fas fa-arrow-right me-2 text-success"></i> Stock de reserva ideal</li>
                                <li><i class="fas fa-arrow-right me-2 text-success"></i> Compra proyectada necesaria</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>