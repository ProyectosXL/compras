<?php
/**
 * Barra de acciones de una solapa de presupuesto (Verano, Invierno, Stock).
 *
 * [Buscar] [Rubros ▾] [Categorías ▾]  N registros       [Guardar] [Más acciones ▾]
 *
 * Una sola definición para las tres solapas: antes cada tab tenía su copia de la
 * barra y se iban separando (Stock tenía otro orden y otros anchos). Los id de los
 * botones son los de siempre (btn-guardar-*, btn-retomar-*, btn-vista-oc-*): los
 * handlers que ya existían los siguen encontrando aunque ahora vivan en el menú.
 *
 * Variables que espera:
 *   $barra = ['solapa' => 'verano', 'guardar' => true, 'retomar' => true, 'oc' => true]
 */
$s = $barra['solapa'];
$conGuardar = !empty($barra['guardar']);
$conRetomar = !empty($barra['retomar']);
$conOc = !empty($barra['oc']);
?>
<div class="search-container barra-solapa">
    <div class="barra-acciones">
        <div class="input-group input-group-sm barra-buscar">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" class="form-control search-input-fast" id="search-<?= $s ?>"
                   placeholder="Buscar..." title="Buscar por rubro o categoría"
                   onkeyup="buscarDatos('<?= $s ?>')">
        </div>
        <div id="filtro-rubro-<?= $s ?>" class="barra-filtro"></div>
        <div id="filtro-categoria-<?= $s ?>" class="barra-filtro"></div>
        <span class="badge bg-info contador-animado barra-contador" id="count-<?= $s ?>">0 registros</span>

        <div class="barra-derecha">
            <?php if ($conGuardar): ?>
            <button class="btn btn-primary btn-sm" id="btn-guardar-<?= $s ?>" data-temporada="<?= $s ?>"
                    title="Guardar una versión de esta solapa">
                <i class="fas fa-save me-1"></i> Guardar
            </button>
            <?php endif; ?>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    Más acciones
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if ($conRetomar): ?>
                    <li><button class="dropdown-item" type="button" id="btn-retomar-<?= $s ?>"
                                title="Aplicar los índices y notas de una versión guardada sobre los datos de hoy">
                        <i class="fas fa-code-branch fa-fw me-2"></i>Retomar versión</button></li>
                    <?php endif; ?>
                    <?php if ($conOc): ?>
                    <li><button class="dropdown-item" type="button" id="btn-vista-oc-<?= $s ?>" data-solapa="<?= $s ?>"
                                title="Ver las OC pendientes en una sola columna o abiertas por temporada">
                        <i class="fas fa-truck fa-fw me-2"></i>Ver OC abiertas/agrupadas</button></li>
                    <?php endif; ?>
                    <?php if ($conRetomar || $conOc): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                    <li><button class="dropdown-item" type="button" onclick="exportarExcel('<?= $s ?>')">
                        <i class="fas fa-file-excel fa-fw me-2 text-success"></i>Exportar Excel (solapa)</button></li>
                    <li><button class="dropdown-item" type="button" onclick="exportarExcel('completo')">
                        <i class="fas fa-file-excel fa-fw me-2 text-success"></i>Exportar Excel completo</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item" type="button" onclick="limpiarFiltrosPresupuesto('<?= $s ?>')">
                        <i class="fas fa-eraser fa-fw me-2"></i>Limpiar filtros</button></li>
                </ul>
            </div>
        </div>
    </div>
</div>
