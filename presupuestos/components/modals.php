
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