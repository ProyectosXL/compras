
<!-- Encabezado: título + switch de país a la izquierda, acciones globales a la derecha.
     "Excel completo" ya no está acá: pasó al menú "Más acciones" de cada solapa. -->
<div class="row">
    <div class="col-12">
        <div class="header-compacto">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="header-titulo d-flex align-items-center flex-wrap gap-3">
                        <h1>
                            <i class="fas fa-calculator me-2"></i>
                            <span id="titulo-sistema">Presupuesto de Compras – Argentina</span>
                        </h1>
                        <div class="country-switch">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="country-switch" onchange="cambiarPais()">
                                <label class="form-check-label text-white" for="country-switch" id="country-label">
                                    <i class="fas fa-flag me-1"></i>Argentina
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="header-acciones d-flex align-items-center gap-1">
                        <button type="button" class="btn btn-light btn-sm" onclick="cargarDatos()" title="Cargar datos (Ctrl+L)">
                            <i class="fas fa-sync-alt me-1"></i> Cargar datos
                        </button>
                        <button type="button" class="btn btn-warning btn-sm btn-icono" id="btn-parametros"
                                onclick="ParametrosManager.abrir()" title="Parámetros" aria-label="Parámetros">
                            <i class="fas fa-cog"></i>
                        </button>
                        <button type="button" class="btn btn-info btn-sm btn-icono" data-bs-toggle="modal"
                                data-bs-target="#ayudaRubrosModal" title="Ayuda" aria-label="Ayuda">
                            <i class="fas fa-question"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
