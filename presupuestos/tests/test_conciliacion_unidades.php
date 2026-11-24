<?php
/**
 * Conciliacion de unidades entre:
 *   1. Compra Proyectada Invierno (INV 26 y VENTA INV. ANTERIOR)
 *   2. Version oficial guardada (#20 o la vigente)
 *   3. Proceso para Presupuesto - Paso 1 (Venta Historica Total y Venta Proyectada)
 *   4. Proceso para Presupuesto - Paso 2B (Real 26 y Ppto 27)
 *
 * Ademas valida unitariamente que extraerColumnaVentaAnterior no haga fallback
 * a temporadas viejas cuando la ultima temporada tuvo venta <= 0.
 */
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../class/presupuesto.php';
require_once __DIR__ . '/../class/distribucion.php';
require_once __DIR__ . '/../class/historial.php';
require_once __DIR__ . '/../controller/ProcesadorDatos.php';

echo "== Regla sin fallback cuando la ultima temporada es 0 o negativa ==\n";
$base0 = PresupuestoCalculos::extraerColumnaVentaAnterior([
    'VTA_INVIERNO_24' => 212,
    'VTA_INVIERNO_25' => 43,
    'VTA_INVIERNO_26' => 0,
], 'INVIERNO');
verificar('INV 26 = 0 no cae a INV 25/24', $base0 && $base0['codigo'] === 'INV 26' && $base0['valor'] == 0.0, $base0);

$baseNeg = PresupuestoCalculos::extraerColumnaVentaAnterior([
    'VTA_INVIERNO_24' => 18,
    'VTA_INVIERNO_25' => 120,
    'VTA_INVIERNO_26' => -1,
], 'INVIERNO');
verificar('INV 26 = -1 no cae a INV 25/24', $baseNeg && $baseNeg['codigo'] === 'INV 26' && $baseNeg['valor'] == -1.0, $baseNeg);

$ctxInv = contexto('invierno', '2026-11-24', '2026-11-24 07:00');
$f0 = PresupuestoCalculos::calcularFila(fila(['venta_invierno_anterior' => 0]), $ctxInv);
verificar('base 0 proyecta 0 en invierno', $f0['venta_proy_invierno'] === 0, $f0['venta_proy_invierno']);
$fNeg = PresupuestoCalculos::calcularFila(fila(['venta_invierno_anterior' => -2]), $ctxInv);
verificar('base negativa proyecta 0 en invierno', $fNeg['venta_proy_invierno'] === 0, $fNeg['venta_proy_invierno']);

echo "\n== Conciliacion en vivo contra base Argentina ==\n";
$_GET['pais'] = 'argentina';
$_SESSION['pais_seleccionado'] = 'argentina';

$presupuesto = new Presupuesto();
$datosSp = $presupuesto->obtenerPresupuestoCompras();
$proc = new ProcesadorDatos();
$filasInv = $proc->procesarDatosCompraInvierno($datosSp);

$sumInv26 = 0;
$sumVtaInvAnt = 0;
$clavesSp = [];
foreach ($filasInv as $r) {
    $sumInv26 += (int)round($r['VTA_INVIERNO_26'] ?? 0);
    $sumVtaInvAnt += (int)round($r['VENTA_INVIERNO_ANTERIOR'] ?? 0);
    $clavesSp[strtoupper(trim($r['RUBRO']) . '|' . trim($r['CATEGORIA_PADRE']))] = true;
}
verificar("INV 26 ($sumInv26) == VENTA INV. ANTERIOR ($sumVtaInvAnt)", $sumInv26 === $sumVtaInvAnt);

$hist = new Historial();
$respOficial = $hist->buscarHistorial(['solo_oficiales' => 1]);
$filasOficial = array_values(array_filter($respOficial['data'] ?? [], function ($f) {
    return strtoupper(trim($f['temporada'] ?? '')) === 'INVIERNO';
}));
$sumOficInvAnt = 0;
$sumOficProyInv = 0;
foreach ($filasOficial as $fo) {
    $sumOficInvAnt += (int)$fo['venta_invierno_anterior'];
    $sumOficProyInv += (int)$fo['venta_proyectada_invierno'];
}
verificar("Version oficial venta_invierno_anterior ($sumOficInvAnt) == INV 26 ($sumInv26)", $sumOficInvAnt === $sumInv26);

$dist = new Distribucion();
$ventasCanal = $dist->obtenerTodasLasVentasPorCanal('2026-02-01', '2026-07-31');
$sumPaso1 = 0;
foreach ($filasOficial as $fo) {
    $rub = trim($fo['rubro']);
    $cat = trim($fo['categoria_padre']);
    foreach ($dist->obtenerCanales() as $c) {
        $sumPaso1 += (int)($ventasCanal[$rub][$cat][$c]['total'] ?? 0);
    }
}
verificar("Paso 1 Venta Historica Total ($sumPaso1) == INV 26 ($sumInv26)", $sumPaso1 === $sumInv26);

$real2B = $dist->obtenerRealComparativoPorCanal('2026-02-01', '2026-07-31', [], $clavesSp);
$sumPaso2B = 0;
foreach ($real2B['por_canal'] as $c => $meses) {
    foreach ($meses as $m => $info) {
        $sumPaso2B += (int)$info['unidades'];
    }
}
verificar("Paso 2B Real 26 unidades ($sumPaso2B) == INV 26 ($sumInv26)", $sumPaso2B === $sumInv26);

$guardadosDist = $dist->obtenerDistribucionGuardada('argentina', 'INVIERNO', 'V7_INV_27 - Repartición mensual mejorada');
$sumDistFinal = 0;
$sumDistMeses = 0;
foreach ($guardadosDist as $gd) {
    $sumDistFinal += (int)$gd['distribucion_final'];
    $mArr = json_decode($gd['distribucion_mensual_json'] ?? '[]', true);
    if (is_array($mArr)) {
        foreach ($mArr as $mInfo) {
            $sumDistMeses += (int)($mInfo['unidades'] ?? 0);
        }
    }
}
verificar("Distribucion V7_INV_27 ($sumDistFinal) == Oficial Proy. Invierno ($sumOficProyInv)", $sumDistFinal === $sumOficProyInv);
verificar("Distribucion V7_INV_27 suma mensual ($sumDistMeses) == Distribucion final ($sumDistFinal)", $sumDistMeses === $sumDistFinal);

terminar();
