
<div class="tab-pane fade" id="invierno" role="tabpanel">
    <?php $barra = ['solapa' => 'invierno', 'guardar' => true, 'retomar' => true, 'oc' => true]; include __DIR__ . '/../barra-solapa.php'; ?>

    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0" id="tabla-invierno">
            <thead class="table-dark sticky-header">
                <tr>
                    <th data-col="rubro">Rubro</th>
                    <th data-col="categoria">Categoría</th>
                    <th class="text-center bg-warning text-dark" data-col="indice-cobertura" title="Factor en meses de cobertura de stock (por defecto 1.50 meses / 45 días de venta promedio). Clic para editarlo.">Índice<br>Cobertura</th>
                    <th class="text-center bg-info" data-col="stock-proyectado">Stock<br>Proyectado</th>
                    <!-- OC pendientes: ya están DENTRO del stock proyectado. Se muestran agrupadas
                         (una columna) o abiertas por temporada, según el botón de la barra. -->
                    <th class="text-center bg-success-subtle text-dark col-oc-agrupada" data-col="oc-total" title="OC pendientes de ingreso (verano + invierno + atemporal). Ya están incluidas en el Stock Proyectado: la compra proyectada es neta de lo pedido.">OC<br>Pendientes</th>
                    <th class="text-center bg-success-subtle text-dark col-oc-abierta" data-col="oc-ver" title="OC pendientes de verano. Ya están incluidas en el Stock Proyectado.">OC Pend.<br>Verano</th>
                    <th class="text-center bg-success-subtle text-dark col-oc-abierta" data-col="oc-inv" title="OC pendientes de invierno. Ya están incluidas en el Stock Proyectado.">OC Pend.<br>Invierno</th>
                    <th class="text-center bg-success-subtle text-dark col-oc-abierta" data-col="oc-atemp" title="OC pendientes atemporales. Ya están incluidas en el Stock Proyectado.">OC Pend.<br>Atemporal</th>
                    <th class="text-center bg-secondary text-white" data-col="indice-original"
                        title="Índice editable tal como vino del SP, antes de cualquier edición. Es el valor al que vuelve Restaurar.">Índice Var.<br>Original</th>
                    <th class="text-center bg-secondary-subtle text-dark" data-col="indice-ver-lp" data-indice-tipo="lp">Índice Ver.<br>LP</th>
                    <th class="text-center bg-warning" data-col="indice-ver-te" data-indice-tipo="te">Índice Ver.<br>Total Empresa</th>
                    <!-- IDs con sufijo -inv: los de la solapa verano son otros. Antes las dos
                         solapas usaban los mismos, así que getElementById devolvía siempre los
                         de verano y los encabezados de esta solapa nunca se actualizaban. -->
                    <th class="text-center header-venta-anterior" data-col="venta-ver-ant" id="header-venta-verano-ant-inv">Venta Ver.<br>Anterior</th>
                    <th class="text-center header-venta-proyectada" data-col="venta-proy-ver" id="header-venta-verano-inv">Venta Proy.<br>Verano</th>
                    <th class="text-center bg-secondary-subtle text-dark" data-col="indice-inv-lp" data-indice-tipo="lp">Índice Inv.<br>LP</th>
                    <th class="text-center bg-warning" data-col="indice-inv-te" data-indice-tipo="te">Índice Inv.<br>Total Empresa</th>
                    <th class="text-center header-venta-anterior" data-col="venta-inv-ant" id="header-venta-invierno-ant-inv">Venta Inv.<br>Anterior</th>
                    <th class="text-center header-venta-proyectada" data-col="venta-proy-inv" id="header-venta-invierno-inv">Venta Proy.<br>Invierno</th>
                    <th class="text-center header-compra-proyectada" data-col="compra-proyectada">Compra<br>Proyectada</th>
                </tr>
            </thead>
            <tbody id="tbody-invierno">
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        <i class="fas fa-info-circle"></i>
                        Cargue los datos para ver la compra proyectada de invierno
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>