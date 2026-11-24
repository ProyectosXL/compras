<?php
/**
 * Pruebas del reparto exacto Sucursal x Mes en Paso 1 (DistribucionController::repartirSucursalesPorMes).
 * Verifica:
 *   1. Caso real de captura: ACCESORIOS DE CUERO | ACCESORIO DE CUERO | LOCALES PROPIOS (124 u.)
 *   2. Cierre horizontal (sum meses sucursal == VENTA_DISTRIBUIDA sucursal) y vertical
 *      (sum sucursales mes == unidades mes del canal) en cientos de casos aleatorios
 *   3. Cierre real contra todos los grupos con sucursales de V7_INV_27 - Repartición mensual mejorada
 */
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../class/distribucion.php';
require_once __DIR__ . '/../controller/DistribucionController.php';

echo "== Caso real ACCESORIO DE CUERO (124 u.) ==\n";
$sucsReal = [
    'UNICENTER'      => ['total' => 43],
    'ALTO PALERMO'   => ['total' => 40],
    'PASEO DEL SIGLO'=> ['total' => 37],
    'CABALLITO'      => ['total' => 30],
    'ABASTO'         => ['total' => 28],
    'AVELLANEDA'     => ['total' => 23],
    'DISTRITO ARCOS' => ['total' => 22],
    'MDP ALDREY'     => ['total' => 21],
    'ALTO ROSARIO'   => ['total' => 19],
    'SOLEIL'         => ['total' => 17],
    'MDP GALLEGOS'   => ['total' => 16],
    'FLORES 1'       => ['total' => 15],
    'GURRUCHAGA'     => ['total' => 12],
    'MALVINAS'       => ['total' => 12],
    'TORTUGAS'       => ['total' => 12],
    'SAN JUSTO'      => ['total' => 11],
    'DOT'            => ['total' => 10],
    'SOLAR'          => ['total' => 10],
    'PORTAL ROSARIO' => ['total' => 9],
    'PARQUE BROWN'   => ['total' => 6],
    'PALMAS DEL PILAR'=>['total' => 4],
]; // suma = 397
$mesesCanal = [
    'Feb 27' => ['unidades' => 24, 'porcentaje' => 19],
    'Mar 27' => ['unidades' => 27, 'porcentaje' => 22],
    'Abr 27' => ['unidades' => 17, 'porcentaje' => 14],
    'May 27' => ['unidades' => 19, 'porcentaje' => 15],
    'Jun 27' => ['unidades' => 17, 'porcentaje' => 14],
    'Jul 27' => ['unidades' => 20, 'porcentaje' => 16],
]; // suma = 124

$rep = DistribucionController::repartirSucursalesPorMes($sucsReal, 124, $mesesCanal);
$sumSucTot = 0;
$sumCol = array_fill_keys(array_keys($mesesCanal), 0);
$okHoriz = true;
foreach ($rep as $s) {
    $sumSucTot += $s['VENTA_DISTRIBUIDA'];
    $sumRow = 0;
    foreach ($mesesCanal as $m => $info) {
        $u = $s['MESES'][$m]['unidades'];
        if ($u < 0) $okHoriz = false;
        $sumRow += $u;
        $sumCol[$m] += $u;
    }
    if ($sumRow !== $s['VENTA_DISTRIBUIDA']) $okHoriz = false;
}
verificar('suma total sucursales == 124', $sumSucTot === 124, $sumSucTot);
verificar('cierre horizontal exacto y sin negativos', $okHoriz);
$okVert = true;
foreach ($mesesCanal as $m => $info) {
    if ($sumCol[$m] !== $info['unidades']) $okVert = false;
}
verificar('cierre vertical exacto por mes (24,27,17,19,17,20)', $okVert, $sumCol);

$gurru = null;
$unic = null;
foreach ($rep as $s) {
    if ($s['SUCURSAL'] === 'GURRUCHAGA') $gurru = $s;
    if ($s['SUCURSAL'] === 'UNICENTER') $unic = $s;
}
verificar('UNICENTER (43/397) tiene mas unidades que GURRUCHAGA (12/397)', $unic['VENTA_DISTRIBUIDA'] > $gurru['VENTA_DISTRIBUIDA']);
verificar('GURRUCHAGA en Feb 27 es proporcional (<= 1, no 3)', $gurru['MESES']['Feb 27']['unidades'] <= 1, $gurru['MESES']);

echo "\n== Barrido aleatorio 300 escenarios ==\n";
mt_srand(20261124);
$fallas = 0;
for ($t = 0; $t < 300; $t++) {
    $numSuc = mt_rand(2, 30);
    $sucs = [];
    for ($i = 0; $i < $numSuc; $i++) {
        $sucs["SUC_$i"] = ['total' => mt_rand(0, 500)];
    }
    $dist = mt_rand(0, 5000);
    $pesos = [];
    for ($m = 0; $m < 6; $m++) $pesos[] = mt_rand(1, 30);
    $sumP = array_sum($pesos);
    $meses = [];
    $acc = 0;
    for ($m = 0; $m < 6; $m++) {
        $u = ($m < 5) ? (int)round($dist * $pesos[$m] / $sumP) : max(0, $dist - $acc);
        $acc += $u;
        $meses["M$m"] = ['unidades' => $u, 'porcentaje' => round($pesos[$m] * 100 / $sumP)];
    }
    $distReal = array_sum(array_column($meses, 'unidades'));
    $r = DistribucionController::repartirSucursalesPorMes($sucs, $distReal, $meses);
    $totR = 0;
    $cols = array_fill_keys(array_keys($meses), 0);
    foreach ($r as $sr) {
        $totR += $sr['VENTA_DISTRIBUIDA'];
        $rowS = 0;
        foreach ($meses as $mk => $mv) {
            $u = $sr['MESES'][$mk]['unidades'];
            if ($u < 0) $fallas++;
            $rowS += $u;
            $cols[$mk] += $u;
        }
        if ($rowS !== $sr['VENTA_DISTRIBUIDA']) $fallas++;
    }
    if ($totR !== $distReal) $fallas++;
    foreach ($meses as $mk => $mv) {
        if ($cols[$mk] !== $mv['unidades']) $fallas++;
    }
}
verificar('300 escenarios aleatorios con cierre exacto horizontal y vertical', $fallas === 0, $fallas);

echo "\n== Validacion en vivo sobre V7_INV_27 - Repartición mensual mejorada ==\n";
$_GET['pais'] = 'argentina';
$_SESSION['pais_seleccionado'] = 'argentina';
$distModel = new Distribucion();
$ventasTodas = $distModel->obtenerTodasLasVentasPorCanal('2026-02-01', '2026-07-31');
$guardados = $distModel->obtenerDistribucionGuardada('argentina', 'INVIERNO', 'V7_INV_27 - Repartición mensual mejorada');

$fallasLive = 0;
$canalesConSuc = 0;
foreach ($guardados as $g) {
    $rub = trim($g['rubro']);
    $cat = trim($g['categoria_padre']);
    $can = trim($g['canal']);
    $sucsMap = $ventasTodas[$rub][$cat][$can]['sucursales'] ?? [];
    if (empty($sucsMap)) continue;

    $mArr = json_decode($g['distribucion_mensual_json'] ?? '[]', true);
    if (!is_array($mArr) || empty($mArr)) continue;

    $canalesConSuc++;
    $distFinal = (int)$g['distribucion_final'];
    $repLive = DistribucionController::repartirSucursalesPorMes($sucsMap, $distFinal, $mArr);

    $tot = 0;
    $cols = array_fill_keys(array_keys($mArr), 0);
    foreach ($repLive as $sr) {
        $tot += $sr['VENTA_DISTRIBUIDA'];
        $rs = 0;
        foreach ($mArr as $mk => $mv) {
            $u = $sr['MESES'][$mk]['unidades'];
            if ($u < 0) $fallasLive++;
            $rs += $u;
            $cols[$mk] += $u;
        }
        if ($rs !== $sr['VENTA_DISTRIBUIDA']) $fallasLive++;
    }
    if ($tot !== $distFinal) $fallasLive++;
    foreach ($mArr as $mk => $mv) {
        if ($cols[$mk] !== (int)$mv['unidades']) $fallasLive++;
    }
}
verificar("Todos los canales con sucursales ($canalesConSuc) cierran 100% horizontal y vertical", $canalesConSuc > 0 && $fallasLive === 0, $fallasLive);

terminar();
