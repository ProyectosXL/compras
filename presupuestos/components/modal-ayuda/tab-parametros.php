
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
                            <td><strong>Venta Proyectada</strong></td>
                            <td>Venta de cada columna (Verano / Invierno). En Argentina se puede cargar a mano:
                                el índice de esa temporada se recalcula para que la venta dé exactamente el número cargado
                                (si el índice no queda redondo se marca con ≈ y se ofrecen los redondos más cercanos).
                                Sin venta anterior no hay índice: queda como "venta manual" y se reparte entre los tramos
                                según sus días.</td>
                            <td>Índice × venta anterior, o carga manual</td>
                            <td><span class="badge bg-success">Sí (Argentina)</span></td>
                        </tr>
                        <tr>
                            <td><strong>Índice de Cobertura</strong></td>
                            <td>Meses de venta que cubre el stock de cobertura. Uno solo por categoría, compartido
                                por Verano, Invierno y Stock. Su valor por defecto sale de Parámetros.</td>
                            <td>Parámetros (Argentina) / 1,50 (Uruguay)</td>
                            <td><span class="badge bg-success">Sí</span></td>
                        </tr>
                        <tr>
                            <td><strong>Parámetros (⚙)</strong></td>
                            <td>Solo Argentina. <em>Generales</em>: índice de cobertura por defecto, días por mes, días
                                mínimos para usar lo vendido, rubros sin cobertura, ventana y límites de los índices.
                                <em>Cobertura por rubro / categoría</em>: índices propios (prioridad categoría &gt; rubro &gt;
                                sin cobertura &gt; general). Estos se aplican al volver a cargar los datos.
                                <em>Exclusiones</em>: rubros, categorías, artículos y canales del proceso de datos; se
                                aplican la próxima vez que corre ese proceso. Cada cambio queda con usuario y fecha.</td>
                            <td>Pantalla de Parámetros</td>
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