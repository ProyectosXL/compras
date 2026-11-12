<?php
/**
 * Mínimo para los tests de cálculo: no usan base de datos ni sesión.
 * Correr con:  php presupuestos/tests/test_cobertura.php
 *              php presupuestos/tests/test_venta_indice.php
 * (o php presupuestos/tests/correr_todos.php)
 */
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once __DIR__ . '/../class/presupuestoCalculos.php';
require_once __DIR__ . '/../class/parametros.php';

$GLOBALS['__fallas'] = 0;
$GLOBALS['__oks'] = 0;

function verificar($nombre, $condicion, $detalle = '') {
    if ($condicion) {
        $GLOBALS['__oks']++;
        echo "  OK     $nombre\n";
    } else {
        $GLOBALS['__fallas']++;
        echo "  FALLA  $nombre" . ($detalle !== '' ? "  ->  " . (is_string($detalle) ? $detalle : json_encode($detalle)) : '') . "\n";
    }
}

function terminar() {
    echo "\n{$GLOBALS['__oks']} ok, {$GLOBALS['__fallas']} falla(s)\n";
    exit($GLOBALS['__fallas'] ? 1 : 0);
}

/** Contexto de cálculo con una fecha de "hoy" y de corrida del SP fijas. */
function contexto($solapa, $hoy, $corridaSp, $params = null) {
    $params = $params ?: Parametros::desdeValores();
    return PresupuestoCalculos::contextoCalculo($solapa, $hoy, $corridaSp, true, $params);
}

/** Fila de prueba con números inventados. */
function fila(array $cambios = []) {
    return array_merge([
        'cant_stock' => 2000, 'cant_stock_guardar' => 100,
        'cant_pend_oc_verano' => 300, 'cant_pend_oc_invierno' => 50, 'cant_pend_oc_atemporal' => 0,
        'venta_verano_anterior' => 1000, 'venta_invierno_anterior' => 800,
        'indice_verano' => 1.10, 'indice_invierno' => 0.90,
        'venta_manual_verano' => null, 'venta_manual_invierno' => null,
        'indice_cobertura' => 1.50, 'vta_temporada_en_curso' => 500
    ], $cambios);
}
