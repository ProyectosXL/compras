
<div class="tab-pane fade" id="calculos" role="tabpanel">
    <div class="accordion" id="calculosAccordion">
        <!-- Stock Proyectado -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseStock">
                    <i class="fas fa-boxes me-2"></i>
                    Stock Proyectado
                </button>
            </h2>
            <div id="collapseStock" class="accordion-collapse collapse show" data-bs-parent="#calculosAccordion">
                <div class="accordion-body">
                    <div class="alert alert-info">
                        <strong>Fórmula:</strong>
                        <code class="d-block small">STOCK + COMPRAS VERANO + COMPRAS INVIERNO + COMPRA ATEMPORAL + STOCK GUARDAR - STOCK IDEAL</code>
                    </div>
                    <p><strong>Descripción:</strong> Calcula el stock total proyectado considerando todas las entradas y salidas previstas.</p>
                    <ul class="small">
                        <li><strong>Stock:</strong> Unidades disponibles (total - comprometido)</li>
                        <li><strong>Compras por temporada:</strong> Órdenes pendientes de ingreso</li>
                        <li><strong>Stock guardar:</strong> Unidades reservadas en central</li>
                        <li><strong>Stock ideal:</strong> Cantidad de reserva necesaria</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Stock Reserva -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseReserva">
                    <i class="fas fa-shield-alt me-2"></i>
                    Stock Reserva
                </button>
            </h2>
            <div id="collapseReserva" class="accordion-collapse collapse" data-bs-parent="#calculosAccordion">
                <div class="accordion-body">
                    <div class="alert alert-info">
                        <strong>Fórmula:</strong>
                        <code>Promedio venta últimos 12 meses × 1.5</code>
                    </div>
                    <p><strong>Descripción:</strong> Calcula el stock de seguridad necesario para cubrir mes y medio de ventas.</p>
                    <div class="row">
                        <div class="col-lg-6 mb-2">
                            <h6 class="text-primary">Propósito:</h6>
                            <ul class="small">
                                <li>Evitar agotamiento de stock</li>
                                <li>Cubrir variaciones de demanda</li>
                                <li>Garantizar disponibilidad</li>
                            </ul>
                        </div>
                        <div class="col-lg-6">
                            <h6 class="text-warning">Factor 1.5:</h6>
                            <p class="small">Representa 1.5 meses de cobertura basado en el promedio mensual de ventas del último año.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ventas Proyectadas -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseVentas">
                    <i class="fas fa-chart-line me-2"></i>
                    Cálculo de Ventas Proyectadas
                </button>
            </h2>
            <div id="collapseVentas" class="accordion-collapse collapse" data-bs-parent="#calculosAccordion">
                <div class="accordion-body">
                    <div class="alert alert-info">
                        <strong>📊 Lógica de Cálculo por Solapa:</strong><br>
                        <small>El sistema aplica diferentes métodos de cálculo según la solapa actual y la temporada en curso.</small>
                    </div>
                    
                    <!-- Cards apiladas en móvil, lado a lado en desktop -->
                    <div class="row g-2">
                        <div class="col-12 col-lg-6">
                            <div class="card bg-warning-subtle h-100">
                                <div class="card-body p-2">
                                    <h6 class="card-title small">
                                        <i class="fas fa-sun text-warning me-2"></i>
                                        🔥 Venta Proyectada Verano
                                    </h6>
                                    <div class="mb-1">
                                        <strong class="small">Transitando verano (Ago-Ene):</strong>
                                        <ul class="mb-1 small">
                                            <li><strong>Verano:</strong> Proporcional actual + Próximo completo</li>
                                            <li><strong>Invierno:</strong> Próximo completo</li>
                                        </ul>
                                    </div>
                                    <div>
                                        <strong class="small">Transitando invierno (Feb-Jul):</strong>
                                        <ul class="mb-0 small">
                                            <li><strong>Verano:</strong> Próximo completo</li>
                                            <li><strong>Invierno:</strong> Proporcional actual</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-lg-6">
                            <div class="card bg-info-subtle h-100">
                                <div class="card-body p-2">
                                    <h6 class="card-title small">
                                        <i class="fas fa-snowflake text-info me-2"></i>
                                        ❄️ Venta Proyectada Invierno
                                    </h6>
                                    <div class="mb-1">
                                        <strong class="small">Transitando invierno (Feb-Jul):</strong>
                                        <ul class="mb-1 small">
                                            <li><strong>Verano:</strong> Próximo completo</li>
                                            <li><strong>Invierno:</strong> Proporcional actual + Próximo completo</li>
                                        </ul>
                                    </div>
                                    <div>
                                        <strong class="small">Transitando verano (Ago-Ene):</strong>
                                        <ul class="mb-0 small">
                                            <li><strong>Verano:</strong> Proporcional actual</li>
                                            <li><strong>Invierno:</strong> Próximo completo</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-success mt-2">
                        <strong class="small">🎯 Ejemplo Práctico (Marzo 2025 - Transitando Invierno):</strong><br>
                        <div class="row mt-1 g-1">
                            <div class="col-12 col-md-6">
                                <small><strong>En solapa Verano:</strong><br>
                                • Venta Ver.: Próximo verano completo<br>
                                • Venta Inv.: Proporcional invierno actual</small>
                            </div>
                            <div class="col-12 col-md-6">
                                <small><strong>En solapa Invierno:</strong><br>
                                • Venta Ver.: Próximo verano completo<br>
                                • Venta Inv.: Proporcional + Próximo invierno</small>
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="mt-2 small"><i class="fas fa-calculator me-2"></i>Fórmulas de Cálculo:</h6>
                    
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <strong class="small">Venta Proyectada Completa:</strong>
                            <code class="d-block bg-light p-1 mt-1 small">
                                Venta Anterior × Índice Variación
                            </code>
                        </div>
                        <div class="col-12 col-md-6">
                            <strong class="small">Venta Proyectada Proporcional:</strong>
                            <code class="d-block bg-light p-1 mt-1 small">
                                Venta Anterior × Índice × (Días Restantes / Días Totales)
                            </code>
                        </div>
                    </div>
                    
                    <div class="mt-2">
                        <strong class="small">Temporadas:</strong>
                        <ul class="small mb-1">
                            <li><strong>Verano:</strong> 1° Agosto al 31 Enero</li>
                            <li><strong>Invierno:</strong> 1° Febrero al 31 Julio</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-warning mt-2 p-2">
                        <strong class="small">⚠️ Importante:</strong> <span class="small">Los cálculos se ajustan automáticamente según la fecha actual y la solapa donde se esté trabajando.</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Compra Proyectada -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCompra">
                    <i class="fas fa-shopping-cart me-2"></i>
                    Compra Proyectada
                </button>
            </h2>
            <div id="collapseCompra" class="accordion-collapse collapse" data-bs-parent="#calculosAccordion">
                <div class="accordion-body">
                    <div class="alert alert-info">
                        <strong>Fórmula:</strong>
                        <code class="small">Stock proyectado - Venta proyectada verano - Venta proyectada invierno</code>
                    </div>
                    <p class="small"><strong>Descripción:</strong> Determina la cantidad adicional que debe comprarse para cubrir la demanda proyectada.</p>
                    
                    <div class="row g-2">
                        <div class="col-12 col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center p-2">
                                    <h6 class="small">Resultado > 0</h6>
                                    <p class="mb-0 small">Exceso de stock</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card bg-warning text-dark">
                                <div class="card-body text-center p-2">
                                    <h6 class="small">Resultado = 0</h6>
                                    <p class="mb-0 small">Stock equilibrado</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center p-2">
                                    <h6 class="small">Resultado < 0</h6>
                                    <p class="mb-0 small">Necesidad de compra</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>