<?php
/**
 * Venta proyectada editable (Bloque C): índice <-> venta, columnas de 1 y 2 tramos,
 * base 0, sugerencias de índice redondo e invariante del reparto por tramo.
 *
 * Fecha fija 12/11/2026 (transitando VER 26-27, 81 de 184 días restantes):
 *   solapa Verano, columna Verano  = Resto VER 26-27 + VER 27-28  (2 tramos)
 *   solapa Verano, columna Invierno = INV 27                       (1 tramo)
 */
require __DIR__ . '/bootstrap.php';

$hoy = '2026-11-12';
$ctx = contexto('verano', $hoy, '2026-11-12 08:30');
$dr = $ctx['dias_restantes'];
$dt = $ctx['dias_totales'];
$per = PresupuestoCalculos::obtenerPeriodosProyeccion($hoy, 'verano');
$dosTramos = $per['verano']['tramos'];
$unTramo = $per['invierno']['tramos'];
verificar('columna verano tiene 2 tramos', count($dosTramos) === 2);
verificar('columna invierno tiene 1 tramo', count($unTramo) === 1);

echo "\n== Sin venta manual: misma fórmula de siempre ==\n";
$std = PresupuestoCalculos::ventasDeColumna($dosTramos, 1000, 1.10, null, $dr, $dt);
verificar('igual que calcularVentaProyectadaVerano', $std['total'] == PresupuestoCalculos::calcularVentaProyectadaVerano(1000, 1.10, $hoy, 'verano'), $std);
verificar('índice 0 se trata como 1,00 (como siempre)',
    PresupuestoCalculos::ventasDeColumna($unTramo, 800, 0, null, $dr, $dt)['total'] == 800);

echo "\n== 1 tramo ==\n";
$r = PresupuestoCalculos::ventasDeColumna($unTramo, 800, null, 1000, $dr, $dt);
verificar('venta exacta 1000', $r['total'] === 1000, $r);
verificar('índice 1,25', abs($r['indice'] - 1.25) < 1e-9, $r['indice']);
$s = PresupuestoCalculos::sugerenciasIndiceRedondo($unTramo, 800, $r['indice'], $dr, $dt);
verificar('1,25 es redondo: sin aviso', $s['aproximado'] === false);

$r = PresupuestoCalculos::ventasDeColumna($unTramo, 800, null, 1001, $dr, $dt);
verificar('venta exacta 1001', $r['total'] === 1001, $r);
verificar('índice 1,25125', abs($r['indice'] - 1.25125) < 1e-9, $r['indice']);
$s = PresupuestoCalculos::sugerenciasIndiceRedondo($unTramo, 800, $r['indice'], $dr, $dt);
verificar('aviso de índice no redondo', $s['aproximado'] === true);
verificar('sugerencias 1,25 -> 1000 y 1,26 -> 1008',
    $s['abajo']['indice'] == 1.25 && $s['abajo']['venta'] === 1000 && $s['arriba']['indice'] == 1.26 && $s['arriba']['venta'] === 1008, $s);

echo "\n== 2 tramos: mismo índice en los dos y suma exacta ==\n";
$r = PresupuestoCalculos::ventasDeColumna($dosTramos, 1000, null, 1000, $dr, $dt);
// índice = 1000 / (1000 × (81/184 + 1)) = 0,694340
verificar('índice 0,694340', abs($r['indice'] - 0.694340) < 1e-6, $r['indice']);
verificar('suma 1000', $r['total'] === 1000, $r['ventas']);
verificar('resto = round(1000 × idx × 81/184)', $r['ventas']['VER 26-27|resto'] === (int)round(1000 * $r['indice'] * 81 / 184), $r['ventas']);

// Barrido: toda venta cargada vuelve exacta, con 1 y 2 tramos y bases variadas.
$fallas = 0;
$ajustes = 0;
foreach ([1, 7, 333, 1000, 4567, 98765] as $base) {
    for ($v = 0; $v <= 6000; $v += 37) {
        foreach ([$unTramo, $dosTramos] as $tramos) {
            $x = PresupuestoCalculos::ventasDeColumna($tramos, $base, null, $v, $dr, $dt);
            if ($x['total'] !== $v || min($x['ventas']) < 0) $fallas++;
            // ¿Cuántas veces hizo falta el ajuste del último tramo?
            $sinAjuste = 0;
            foreach ($tramos as $t) $sinAjuste += PresupuestoCalculos::ventaProyectadaDeTramo($t, $base, $x['indice'], $dr, $dt);
            if ($sinAjuste != $v) $ajustes++;
        }
    }
}
verificar("barrido: la venta cargada queda exacta ($ajustes casos con ajuste de 1 unidad en el último tramo)", $fallas === 0, $fallas);

echo "\n== Base 0: venta manual sin índice ==\n";
$r = PresupuestoCalculos::ventasDeColumna($dosTramos, 0, 1.0, 365, $dr, $dt);
verificar('índice null ("—")', $r['indice'] === null);
// días: resto 81, VER 27-28 184 -> 365 × 81/265 = 111,6 -> 112 ; 253
verificar('reparto por días 112 / 253', $r['ventas']['VER 26-27|resto'] === 112 && $r['ventas']['VER 27-28'] === 253, $r['ventas']);
verificar('suma exacta', $r['total'] === 365);
verificar('sin sugerencias', PresupuestoCalculos::sugerenciasIndiceRedondo($dosTramos, 0, null, $dr, $dt) === null);

echo "\n== La venta manual llega a la fila: tramos, compra, cobertura ==\n";
$f = PresupuestoCalculos::calcularFila(fila(['venta_verano_anterior' => 0, 'venta_manual_verano' => 365]), $ctx);
verificar('venta proy. verano = 365', $f['venta_proy_verano'] === 365);
verificar('índice verano "—"', $f['indice_verano'] === null);
verificar('marcada como manual', $f['venta_manual_verano'] === 365);
$sumaTramosVerano = array_sum(array_map(function ($t) { return $t['temporada_tipo'] === 'VERANO' ? $t['venta_proyectada'] : 0; }, $f['tramos']));
verificar('tramos de verano suman 365', $sumaTramosVerano === 365, $f['tramos']);
verificar('el resto (112) entra en la cobertura', $f['cobertura']['ventas'] == 500 + 112 + 720, $f['cobertura']);

echo "\n== Invariante SUM(compra) == MAX(0, -compra_proyectada) ==\n";
$fallas = 0;
$casos = 0;
mt_srand(20261112);
foreach (['verano', 'invierno'] as $solapa) {
    foreach (['2026-11-12', '2026-08-15', '2027-03-10', '2027-07-31', '2027-01-31'] as $fecha) {
        $c = PresupuestoCalculos::contextoCalculo($solapa, $fecha, $fecha, true, Parametros::desdeValores());
        for ($i = 0; $i < 400; $i++) {
            $e = fila([
                'cant_stock' => mt_rand(0, 6000), 'cant_stock_guardar' => mt_rand(0, 300),
                'cant_pend_oc_verano' => mt_rand(0, 2000), 'cant_pend_oc_invierno' => mt_rand(0, 2000),
                'venta_verano_anterior' => mt_rand(0, 3) ? mt_rand(1, 9000) : 0,
                'venta_invierno_anterior' => mt_rand(0, 3) ? mt_rand(1, 9000) : 0,
                'indice_verano' => mt_rand(0, 300) / 100, 'indice_invierno' => mt_rand(0, 300) / 100,
                'venta_manual_verano' => mt_rand(0, 2) ? null : mt_rand(0, 9000),
                'venta_manual_invierno' => mt_rand(0, 2) ? null : mt_rand(0, 9000),
                'indice_cobertura' => mt_rand(0, 400) / 100, 'vta_temporada_en_curso' => mt_rand(0, 3000)
            ]);
            $f = PresupuestoCalculos::calcularFila($e, $c);
            $casos++;
            $suma = array_sum(array_column($f['tramos'], 'compra'));
            $ok = $suma === max(0, -$f['compra_proyectada']);
            // Y cada venta manual queda exacta.
            foreach (['verano', 'invierno'] as $col) {
                if ($e["venta_manual_$col"] !== null && $f["venta_proy_$col"] !== $e["venta_manual_$col"]) $ok = false;
            }
            // Y las ventas de las columnas son la suma de sus tramos.
            $porTipo = ['VERANO' => 0, 'INVIERNO' => 0];
            foreach ($f['tramos'] as $t) $porTipo[$t['temporada_tipo']] += $t['venta_proyectada'];
            if ($porTipo['VERANO'] !== $f['venta_proy_verano'] || $porTipo['INVIERNO'] !== $f['venta_proy_invierno']) $ok = false;
            if (!$ok) {
                $fallas++;
                if ($fallas <= 3) echo "    caso: " . json_encode([$solapa, $fecha, $e, $suma, $f['compra_proyectada']]) . "\n";
            }
        }
    }
}
verificar("invariante, ventas manuales exactas y columnas = suma de tramos ($casos filas)", $fallas === 0, $fallas);

echo "\n== Sin venta manual da lo mismo que el cálculo anterior ==\n";
// calcularFila sin manuales tiene que dar las mismas ventas y tramos que el camino
// viejo con el mismo stock proyectado (lo único nuevo es la cobertura).
$fallas = 0;
foreach (['verano', 'invierno'] as $solapa) {
    $c = PresupuestoCalculos::contextoCalculo($solapa, $hoy, $hoy, true, Parametros::desdeValores());
    for ($i = 0; $i < 200; $i++) {
        $e = fila(['venta_verano_anterior' => mt_rand(0, 9000), 'venta_invierno_anterior' => mt_rand(0, 9000),
                   'indice_verano' => mt_rand(0, 300) / 100, 'indice_invierno' => mt_rand(0, 300) / 100,
                   'cant_stock' => mt_rand(0, 6000)]);
        $f = PresupuestoCalculos::calcularFila($e, $c);
        $viejo = PresupuestoCalculos::procesarRegistroCompraProyectada(
            ['INDICE_VARIACION' => $e['indice_verano'], 'INDICE_VARIACION_INVIERNO' => $e['indice_invierno'],
             'VTA_VERANO_26' => $e['venta_verano_anterior'], 'VTA_INVIERNO_26' => $e['venta_invierno_anterior']],
            $f['stock_proyectado'], strtoupper($solapa), $hoy, $solapa);
        if ($viejo['venta_proy_verano'] != $f['venta_proy_verano'] || $viejo['venta_proy_invierno'] != $f['venta_proy_invierno']
            || $viejo['tramos'] != $f['tramos'] || (int)round($viejo['compra_proyectada']) !== $f['compra_proyectada']) {
            $fallas++;
        }
    }
}
verificar('ventas, tramos y compra iguales al camino anterior (400 filas)', $fallas === 0, $fallas);

terminar();
