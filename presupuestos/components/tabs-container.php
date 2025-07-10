
<!-- Tabs Container -->
<div class="row" id="tabs-container" style="display: none;">
    <div class="col-12">
        <div class="card border-0 shadow">
            <!-- Nav Tabs -->
            <ul class="nav nav-tabs" id="presupuestoTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="verano-tab" data-bs-toggle="tab" 
                            data-bs-target="#verano" type="button" role="tab">
                        <i class="fas fa-sun me-2"></i>Compra Proyectada Verano
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="invierno-tab" data-bs-toggle="tab" 
                            data-bs-target="#invierno" type="button" role="tab">
                        <i class="fas fa-snowflake me-2"></i>Compra Proyectada Invierno
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="stock-tab" data-bs-toggle="tab" 
                            data-bs-target="#stock" type="button" role="tab">
                        <i class="fas fa-boxes me-2"></i>Stock Proyectado
                    </button>
                </li>
                <!-- Nueva solapa de compras pendientes -->
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="compras-detalle-tab" data-bs-toggle="tab" 
                            data-bs-target="#compras-detalle" type="button" role="tab">
                        <i class="fas fa-shopping-cart me-2"></i>Compras Pendientes
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="presupuestoTabContent">
                <!-- Compra Proyectada Verano -->
                <?php include 'components/tabs/verano-tab.php'; ?>
                
                <!-- Compra Proyectada Invierno -->
                <?php include 'components/tabs/invierno-tab.php'; ?>
                
                <!-- Stock Proyectado -->
                <?php include 'components/tabs/stock-tab.php'; ?>
                
                <!-- Compras Pendientes -->
                <?php include 'components/tabs/compras-detalle-tab.php'; ?>
            </div>
        </div>
    </div>
</div>