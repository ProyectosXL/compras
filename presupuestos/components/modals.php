
<!-- Modales del sistema -->

<!-- Modal para editar índice -->
<div class="modal fade" id="modalEditarIndice" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Índice de Variación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Rubro:</label>
                    <p class="fw-bold" id="modal-rubro"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Categoría:</label>
                    <p class="fw-bold" id="modal-categoria"></p>
                </div>
                <div class="mb-3">
                    <label for="modal-nuevo-indice" class="form-label">Nuevo Índice:</label>
                    <input type="number" class="form-control" id="modal-nuevo-indice" 
                           step="0.01" min="0" max="10">
                           <div class="form-text">
                        <strong>Ejemplos:</strong><br>
                        • 1.0 = Sin cambio (100%)<br>
                        • 1.2 = Aumento 20%<br>
                        • 0.8 = Reducción 20%
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarNuevoIndice()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación genérico -->
<div class="modal fade" id="modalConfirmacion" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalConfirmacionTitulo">Confirmar Acción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="modalConfirmacionMensaje">¿Está seguro que desea continuar?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarAccion">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de información del sistema -->
<div class="modal fade" id="modalInfoSistema" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle me-2"></i>
                    Información del Sistema
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Estado General</h6>
                        <ul class="list-unstyled">
                            <li><strong>Versión:</strong> 2.1</li>
                            <li><strong>Módulos activos:</strong> <span id="info-modulos">-</span></li>
                            <li><strong>Última carga:</strong> <span id="info-ultima-carga">-</span></li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Datos Cargados</h6>
                        <ul class="list-unstyled">
                            <li><strong>Verano:</strong> <span id="info-registros-verano">-</span></li>
                            <li><strong>Invierno:</strong> <span id="info-registros-invierno">-</span></li>
                            <li><strong>Stock:</strong> <span id="info-registros-stock">-</span></li>
                        </ul>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Herramientas de Diagnóstico</h6>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="diagnosticoSistema()">
                                <i class="fas fa-stethoscope me-1"></i> Diagnóstico
                            </button>
                            <button class="btn btn-sm btn-outline-warning" onclick="limpiarCache()">
                                <i class="fas fa-trash me-1"></i> Limpiar Cache
                            </button>
                            <button class="btn btn-sm btn-outline-info" onclick="limpiarTablas()">
                                <i class="fas fa-table me-1"></i> Resetear Tablas
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de ayuda con instructivo -->
<div class="modal fade" id="ayudaRubrosModal" tabindex="-1" aria-labelledby="ayudaRubrosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="ayudaRubrosModalLabel">
                    <i class="fas fa-question-circle me-2"></i>
                    Ayuda del Sistema de Cálculo de Rubros
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="container-fluid">
                    <!-- Tabs de navegación -->
                    <ul class="nav nav-tabs" id="ayudaTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                                <i class="fas fa-info-circle me-1"></i> Resumen General
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="calculos-tab" data-bs-toggle="tab" data-bs-target="#calculos" type="button" role="tab">
                                <i class="fas fa-calculator me-1"></i> Cálculos
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="parametros-tab" data-bs-toggle="tab" data-bs-target="#parametros" type="button" role="tab">
                                <i class="fas fa-cogs me-1"></i> Parámetros
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="ejemplos-tab" data-bs-toggle="tab" data-bs-target="#ejemplos" type="button" role="tab">
                                <i class="fas fa-chart-line me-1"></i> Ejemplos
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Contenido de las tabs -->
                    <div class="tab-content mt-3" id="ayudaTabsContent">
                        <!-- Tab 1: Resumen General -->
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
                        
                        <!-- Tab 2: Cálculos -->
                        <div class="tab-pane fade" id="calculos" role="tabpanel">
                            <div class="accordion" id="calculosAccordion">
                                <!-- Cálculo 1: Stock Proyectado -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingStock">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseStock">
                                            <i class="fas fa-boxes me-2"></i>
                                            Stock Proyectado
                                        </button>
                                    </h2>
                                    <div id="collapseStock" class="accordion-collapse collapse show">
                                        <div class="accordion-body">
                                            <div class="alert alert-info">
                                                <strong>Fórmula:</strong>
                                                <code>STOCK + COMPRAS VERANO + COMPRAS INVIERNO + COMPRA ATEMPORAL + STOCK GUARDAR - STOCK IDEAL</code>
                                            </div>
                                            <p><strong>Descripción:</strong> Calcula el stock total proyectado considerando todas las entradas y salidas previstas.</p>
                                            <ul>
                                                <li><strong>Stock:</strong> Unidades disponibles (total - comprometido)</li>
                                                <li><strong>Compras por temporada:</strong> Órdenes pendientes de ingreso</li>
                                                <li><strong>Stock guardar:</strong> Unidades reservadas en central</li>
                                                <li><strong>Stock ideal:</strong> Cantidad de reserva necesaria</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Cálculo 2: Stock Reserva -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingReserva">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseReserva">
                                            <i class="fas fa-shield-alt me-2"></i>
                                            Stock Reserva
                                        </button>
                                    </h2>
                                    <div id="collapseReserva" class="accordion-collapse collapse">
                                        <div class="accordion-body">
                                            <div class="alert alert-info">
                                                <strong>Fórmula:</strong>
                                                <code>Promedio venta últimos 12 meses × 1.5</code>
                                            </div>
                                            <p><strong>Descripción:</strong> Calcula el stock de seguridad necesario para cubrir mes y medio de ventas.</p>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6 class="text-primary">Propósito:</h6>
                                                    <ul>
                                                        <li>Evitar agotamiento de stock</li>
                                                        <li>Cubrir variaciones de demanda</li>
                                                        <li>Garantizar disponibilidad</li>
                                                    </ul>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="text-warning">Factor 1.5:</h6>
                                                    <p>Representa 1.5 meses de cobertura basado en el promedio mensual de ventas del último año.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Cálculo 3: Ventas Proyectadas -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingVentas">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseVentas">
                                            <i class="fas fa-trending-up me-2"></i>
                                            Ventas Proyectadas
                                        </button>
                                    </h2>
                                    <div id="collapseVentas" class="accordion-collapse collapse">
                                        <div class="accordion-body">
                                            <div class="alert alert-info">
                                                <strong>Fórmula:</strong>
                                                <code>Ventas temporada anterior × Índice de variación</code>
                                            </div>
                                            <p><strong>Descripción:</strong> Proyecta las ventas futuras basándose en el comportamiento histórico y las tendencias actuales.</p>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6 class="text-primary">Verano:</h6>
                                                    <p>Aplica el índice de variación de los últimos 60 días a las ventas de verano de la temporada anterior.</p>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="text-warning">Invierno:</h6>
                                                    <p>Aplica el mismo índice a las ventas de invierno de la temporada anterior.</p>
                                                </div>
                                            </div>
                                            
                                            <div class="alert alert-warning">
                                                <strong>Nota:</strong> Si la temporada está en curso, el cálculo se ajusta por los días restantes.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Cálculo 4: Compra Proyectada -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingCompra">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCompra">
                                            <i class="fas fa-shopping-cart me-2"></i>
                                            Compra Proyectada
                                        </button>
                                    </h2>
                                    <div id="collapseCompra" class="accordion-collapse collapse">
                                        <div class="accordion-body">
                                            <div class="alert alert-info">
                                                <strong>Fórmula:</strong>
                                                <code>Stock proyectado - Venta proyectada verano - Venta proyectada invierno</code>
                                            </div>
                                            <p><strong>Descripción:</strong> Determina la cantidad adicional que debe comprarse para cubrir la demanda proyectada.</p>
                                            
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="card bg-success text-white">
                                                        <div class="card-body text-center">
                                                            <h6>Resultado > 0</h6>
                                                            <p class="mb-0">Exceso de stock</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="card bg-warning text-dark">
                                                        <div class="card-body text-center">
                                                            <h6>Resultado = 0</h6>
                                                            <p class="mb-0">Stock equilibrado</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="card bg-danger text-white">
                                                        <div class="card-body text-center">
                                                            <h6>Resultado < 0</h6>
                                                            <p class="mb-0">Necesidad de compra</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tab 3: Parámetros -->
                        <div class="tab-pane fade" id="parametros" role="tabpanel">
                            <div class="row">
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Parámetro</th>
                                                    <th>Descripción</th>
                                                    <th>Origen</th>
                                                    <th>Editable</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><strong>Stock</strong></td>
                                                    <td>Unidades disponibles (total - comprometido) con destino CONTINUO</td>
                                                    <td>Sistema de inventario</td>
                                                    <td><span class="badge bg-secondary">No</span></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Stock Guardar</strong></td>
                                                    <td>Unidades en central con destino GUARDAR</td>
                                                    <td>Sistema de inventario</td>
                                                    <td><span class="badge bg-secondary">No</span></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Ventas (6 temporadas)</strong></td>
                                                    <td>Historial de ventas de las 6 temporadas anteriores</td>
                                                    <td>Sistema de ventas</td>
                                                    <td><span class="badge bg-secondary">No</span></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Compras por temporada</strong></td>
                                                    <td>Órdenes pendientes de ingreso por temporada</td>
                                                    <td>Sistema de compras</td>
                                                    <td><span class="badge bg-secondary">No</span></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Índice Variación</strong></td>
                                                    <td>Variación de ventas últimos 60 días vs año anterior</td>
                                                    <td>Cálculo automático</td>
                                                    <td><span class="badge bg-success">Sí</span></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Stock Proyectado</strong></td>
                                                    <td>Resultado del cálculo de stock futuro</td>
                                                    <td>Cálculo automático</td>
                                                    <td><span class="badge bg-secondary">No</span></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <h6><i class="fas fa-info-circle me-2"></i>Información Importante</h6>
                                <ul class="mb-0">
                                    <li>Los parámetros editables pueden modificarse para ajustar las proyecciones</li>
                                    <li>Los cambios se reflejan automáticamente en todos los cálculos dependientes</li>
                                    <li>La categoría padre agrupa las categorías específicas de comercial</li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Tab 4: Ejemplos -->
                        <div class="tab-pane fade" id="ejemplos" role="tabpanel">
                            <div class="row">
                                <div class="col-12">
                                    <h5 class="text-primary mb-3">
                                        <i class="fas fa-chart-line me-2"></i>
                                        Ejemplo Práctico de Cálculo
                                    </h5>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-primary text-white">
                                            <h6 class="mb-0">Datos de Entrada</h6>
                                        </div>
                                        <div class="card-body">
                                            <table class="table table-sm">
                                                <tr>
                                                    <td>Stock actual:</td>
                                                    <td><strong>500 unidades</strong></td>
                                                </tr>
                                                <tr>
                                                    <td>Stock guardar:</td>
                                                    <td><strong>100 unidades</strong></td>
                                                </tr>
                                                <tr>
                                                    <td>Compras verano:</td>
                                                    <td><strong>300 unidades</strong></td>
                                                </tr>
                                                <tr>
                                                    <td>Compras invierno:</td>
                                                    <td><strong>200 unidades</strong></td>
                                                </tr>
                                                <tr>
                                                    <td>Compras atemporal:</td>
                                                    <td><strong>150 unidades</strong></td>
                                                </tr>
                                                <tr>
                                                    <td>Ventas verano anterior:</td>
                                                    <td><strong>400 unidades</strong></td>
                                                </tr>
                                                <tr>
                                                    <td>Ventas invierno anterior:</td>
                                                    <td><strong>350 unidades</strong></td>
                                                </tr>
                                                <tr>
                                                    <td>Índice variación:</td>
                                                    <td><strong>1.15 (+15%)</strong></td>
                                                </tr>
                                                <tr>
                                                    <td>Promedio venta 12 meses:</td>
                                                    <td><strong>60 unidades/mes</strong></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-success text-white">
                                            <h6 class="mb-0">Cálculos Paso a Paso</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="step-calculation">
                                                <h6 class="text-primary">1. Stock Reserva</h6>
                                                <p><code>60 × 1.5 = 90 unidades</code></p>
                                                
                                                <h6 class="text-primary">2. Stock Proyectado</h6>
                                                <p><code>500 + 300 + 200 + 150 + 100 - 90 = 1,160 unidades</code></p>
                                                
                                                <h6 class="text-primary">3. Ventas Proyectadas</h6>
                                                <p><strong>Verano:</strong> <code>400 × 1.15 = 460 unidades</code></p>
                                                <p><strong>Invierno:</strong> <code>350 × 1.15 = 403 unidades</code></p>
                                                
                                                <h6 class="text-primary">4. Compra Proyectada</h6>
                                                <p><code>1,160 - 460 - 403 = 297 unidades</code></p>
                                                
                                                <div class="alert alert-success mt-3">
                                                    <strong>Resultado:</strong> Exceso de 297 unidades. No se requiere compra adicional.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="alert alert-warning">
                                        <h6><i class="fas fa-lightbulb me-2"></i>Interpretación de Resultados</h6>
                                        <ul class="mb-0">
                                            <li><strong>Compra proyectada positiva:</strong> Indica exceso de stock</li>
                                            <li><strong>Compra proyectada negativa:</strong> Indica necesidad de compra</li>
                                            <li><strong>Cerca de cero:</strong> Stock equilibrado</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cerrar
                </button>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Imprimir
                </button>
            </div>
        </div>
    </div>
</div>