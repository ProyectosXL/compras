
<div class="tab-pane fade" id="ejemplos" role="tabpanel">
    <div class="row">
        <div class="col-12">
            <h5 class="text-primary mb-2 small">
                <i class="fas fa-chart-line me-2"></i>
                Ejemplo Práctico de Cálculo
            </h5>
        </div>
    </div>
    
    <div class="row g-2">
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-primary text-white p-2">
                    <h6 class="mb-0 small">Datos de Entrada</h6>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table class="table table-sm small mb-0">
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
                                <td><strong>1,000 unidades</strong></td>
                            </tr>
                            <tr>
                                <td>Ventas invierno anterior:</td>
                                <td><strong>800 unidades</strong></td>
                            </tr>
                            <tr>
                                <td>Índice variación:</td>
                                <td><strong>1.2 (+20%)</strong></td>
                            </tr>
                            <tr>
                                <td>Promedio venta 12 meses:</td>
                                <td><strong>60 unidades/mes</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white p-2">
                    <h6 class="mb-0 small">Cálculos por Contexto de Solapa</h6>
                </div>
                <div class="card-body p-2">
                    <div class="alert alert-info p-2 mb-2">
                        <strong class="small">📅 Escenario:</strong> <span class="small">Marzo 2025 (Transitando Invierno)</span><br>
                        <strong class="small">📦 Producto:</strong> <span class="small">Zapatos Casuales</span>
                    </div>
                    
                    <!-- Cards apiladas más compactas -->
                    <div class="d-flex flex-column gap-1">
                        <div class="card bg-warning-subtle">
                            <div class="card-body p-2">
                                <h6 class="mb-1 small"><i class="fas fa-sun me-1"></i>En Solapa Verano:</h6>
                                <div class="small">
                                    • Venta Ver.: 1,200 (próximo completo)<br>
                                    • Venta Inv.: 797 (proporcional actual)<br>
                                    • Total: 1,997 | Compra: <span class="text-danger fw-bold">-837</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card bg-info-subtle">
                            <div class="card-body p-2">
                                <h6 class="mb-1 small"><i class="fas fa-snowflake me-1"></i>En Solapa Invierno:</h6>
                                <div class="small">
                                    • Venta Ver.: 1,200 (próximo completo)<br>
                                    • Venta Inv.: 1,757 (prop. + próximo)<br>
                                    • Total: 2,957 | Compra: <span class="text-danger fw-bold">-1,797</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="step-calculation mt-2">
                        <h6 class="text-primary small">Stock Proyectado (común):</h6>
                        <p class="small mb-1"><code>500 + 300 + 200 + 150 + 100 - 90 = 1,160</code></p>
                        
                        <div class="alert alert-warning mt-1 p-2">
                            <strong class="small">📈 Conclusión:</strong><br>
                            <span class="small">Ambas solapas indican necesidad de compra, pero con magnitudes diferentes según el contexto de uso.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-2">
        <div class="col-12">
            <div class="alert alert-warning p-2">
                <h6 class="small"><i class="fas fa-lightbulb me-2"></i>Interpretación de Resultados por Solapa</h6>
                <div class="row g-2">
                    <div class="col-12 col-md-6">
                        <strong class="small">Valores Generales:</strong>
                        <ul class="mb-1 small">
                            <li><strong>Compra proyectada positiva:</strong> Exceso de stock</li>
                            <li><strong>Compra proyectada negativa:</strong> Necesidad de compra</li>
                            <li><strong>Cerca de cero:</strong> Stock equilibrado</li>
                        </ul>
                    </div>
                    <div class="col-12 col-md-6">
                        <strong class="small">Diferencias por Solapa:</strong>
                        <ul class="mb-0 small">
                            <li><strong>Solapa Verano:</strong> Optimizada para planificación de temporada alta</li>
                            <li><strong>Solapa Invierno:</strong> Considera mayor proyección de temporada baja</li>
                            <li><strong>Ambas se ajustan:</strong> Según período actual automáticamente</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>