
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