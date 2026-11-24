<?php
/* =====================================================================
   Estandarización de unidades:
   1. Actualiza dbo.RO_SP_VENTAS_PRESUPUESTO_COMPRAS en POWER_BI_CONTROL
      para excluir DESC_SUCURSAL = 'XL RURAL' y re-ejecuta el SP.
   2. Recalcula la última versión oficial de Invierno (id_cabecera oficial)
      aplicando la regla sin fallback a temporadas anteriores cuando la
      última temporada tuvo venta <= 0 y con VTA_INVIERNO_26 / VTA_VERANO_26
      sin XL RURAL.
   3. Recalcula la última versión de Distribución de Invierno en
      FP_T_DISTRIBUCION_COMPRAS_CANAL y FP_T_PRESUPUESTO_VERSION_CONSOLIDADA,
      conservando las participaciones % y estacionalidades % guardadas.

   Uso:
     php 12_estandarizacion_unidades.php            -> solo preview (no escribe)
     php 12_estandarizacion_unidades.php --aplicar  -> aplica en BD (con backup JSON)
   ===================================================================== */

if (PHP_SAPI !== 'cli') {
    die("Este script se corre por linea de comandos.\n");
}

ob_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once __DIR__ . '/../../class/conexion.php';
require_once __DIR__ . '/../class/presupuestoCalculos.php';
require_once __DIR__ . '/../class/distribucion.php';

$aplicar = in_array('--aplicar', $argv, true);

$conn = new Conexion();
$cidPower = $conn->conectar('apps_power');
$cidApps  = $conn->conectar('apps');

if (!$cidPower || !$cidApps) {
    die("Error de conexion a apps_power o apps.\n");
}

echo "=====================================================================\n";
echo "Estandarizacion de unidades - Modo: " . ($aplicar ? "APLICAR (escribe en BD)" : "PREVIEW (solo lectura)") . "\n";
echo "=====================================================================\n\n";

// ---------------------------------------------------------------------
// 1. Actualizar SP RO_SP_VENTAS_PRESUPUESTO_COMPRAS (excluir XL RURAL)
// ---------------------------------------------------------------------
$spFile = __DIR__ . '/06_sp_presupuesto_compras.sql';
$spRaw  = file_get_contents($spFile);
if ($spRaw === false) {
    die("No se pudo leer $spFile\n");
}

// Extraer exclusivamente el bloque CREATE OR ALTER PROCEDURE ... END;
$posStart = strpos($spRaw, 'CREATE OR ALTER PROCEDURE dbo.RO_SP_VENTAS_PRESUPUESTO_COMPRAS');
$posEnd   = strpos($spRaw, "\nGO\n\nSET NOEXEC OFF;", $posStart ?: 0);
if ($posStart === false || $posEnd === false) {
    // Fallback CRLF
    $posEnd = strpos($spRaw, "\r\nGO\r\n\r\nSET NOEXEC OFF;", $posStart ?: 0);
}
if ($posStart === false || $posEnd === false) {
    die("No se pudo extraer el bloque CREATE OR ALTER PROCEDURE de $spFile\n");
}
$spCreateSql = substr($spRaw, $posStart, $posEnd - $posStart);

if ($aplicar) {
    echo "1) Actualizando dbo.RO_SP_VENTAS_PRESUPUESTO_COMPRAS en POWER_BI_CONTROL...\n";
    $stmtAlt = sqlsrv_query($cidPower, $spCreateSql);
    if ($stmtAlt === false) {
        die("Error al alterar SP: " . print_r(sqlsrv_errors(), true) . "\n");
    }
    sqlsrv_free_stmt($stmtAlt);

    echo "   Ejecutando EXEC dbo.RO_SP_VENTAS_PRESUPUESTO_COMPRAS...\n";
    $stmtExec = sqlsrv_query($cidPower, "EXEC dbo.RO_SP_VENTAS_PRESUPUESTO_COMPRAS");
    if ($stmtExec === false) {
        die("Error al ejecutar SP: " . print_r(sqlsrv_errors(), true) . "\n");
    }
    while (sqlsrv_next_result($stmtExec) === true) {}
    sqlsrv_free_stmt($stmtExec);
    echo "   OK SP actualizado y ejecutado.\n\n";
} else {
    echo "1) [PREVIEW] Se actualizaria dbo.RO_SP_VENTAS_PRESUPUESTO_COMPRAS para excluir XL RURAL y se re-ejecutaria.\n\n";
}

// Leer tabla SP (en preview simulamos restando XL RURAL)
$xlRuralInv26 = [];
if (!$aplicar) {
    $qXlr = sqlsrv_query($cidPower, "
        SELECT RUBRO, CATEGORIA_PADRE, SUM(CANT_VEND) AS unid
        FROM dbo.RO_VENTAS_COMERCIAL
        WHERE DESC_SUCURSAL = 'XL RURAL'
          AND FECHA_VENTA >= '2026-02-01' AND FECHA_VENTA <= '2026-07-31'
          AND CANAL IN ('LOCALES PROPIOS', 'CENTRAL')
          AND RUBRO NOT IN ('PACKAGING', '_KITS')
          AND (RUBRO NOT LIKE '%OUTLET%' OR (RUBRO = 'SINTETICOS OUTLET' AND (ARTICULO LIKE 'X%' OR ARTICULO LIKE 'O%') AND ARTICULO NOT LIKE 'OU%'))
          AND CATEGORIA <> 'REMERA'
          AND ARTICULO <> 'XV00LV00A0001'
          AND RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
        GROUP BY RUBRO, CATEGORIA_PADRE
    ");
    while ($r = sqlsrv_fetch_array($qXlr, SQLSRV_FETCH_ASSOC)) {
        $xlRuralInv26[trim($r['RUBRO']) . '|' . trim($r['CATEGORIA_PADRE'])] = (int)$r['unid'];
    }
    sqlsrv_free_stmt($qXlr);
}

$spRows = [];
$qSp = sqlsrv_query($cidPower, "SELECT * FROM dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS");
while ($r = sqlsrv_fetch_array($qSp, SQLSRV_FETCH_ASSOC)) {
    $k = trim($r['RUBRO']) . '|' . trim($r['CATEGORIA_PADRE']);
    if (!$aplicar && isset($xlRuralInv26[$k])) {
        $r['VTA_INVIERNO_26'] = (float)$r['VTA_INVIERNO_26'] - $xlRuralInv26[$k];
    }
    $spRows[$k] = $r;
}
sqlsrv_free_stmt($qSp);

// ---------------------------------------------------------------------
// 2. Recalcular última versión oficial de Invierno
// ---------------------------------------------------------------------
$qCab = sqlsrv_query($cidPower, "
    SELECT TOP 1 *
    FROM dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA
    WHERE solapa = 'invierno' AND ISNULL(eliminada, 0) = 0 AND es_oficial = 1
    ORDER BY fecha_guardado DESC, id DESC
");
$cab = sqlsrv_fetch_array($qCab, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($qCab);

if (!$cab) {
    die("No se encontro version oficial de invierno.\n");
}
$idCabecera = (int)$cab['id'];
$nombreOficial = $cab['nombre_presupuesto'];
$fechaCalculo = $cab['fecha_calculo'] instanceof DateTime ? $cab['fecha_calculo']->format('Y-m-d') : (string)$cab['fecha_calculo'];
$diasRestantes = (int)$cab['dias_restantes_temporada'];
$diasTotales   = (int)$cab['dias_totales_temporada'];
$periodos      = PresupuestoCalculos::obtenerPeriodosProyeccion($fechaCalculo, 'invierno');
$tramosCron    = PresupuestoCalculos::tramosCronologicos($periodos);

echo "2) Version oficial Invierno: #{$idCabecera} '{$nombreOficial}' (fecha_calculo={$fechaCalculo})\n";

$qDet = sqlsrv_query($cidPower, "SELECT * FROM dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO WHERE id_cabecera = ? ORDER BY rubro, categoria_padre", [$idCabecera]);
$detalles = [];
while ($r = sqlsrv_fetch_array($qDet, SQLSRV_FETCH_ASSOC)) {
    $detalles[] = $r;
}
sqlsrv_free_stmt($qDet);

$actualizacionesHist = [];
$sumOldInvAnt = 0; $sumNewInvAnt = 0;
$sumOldProyInv = 0; $sumNewProyInv = 0;
$sumOldProyVer = 0; $sumNewProyVer = 0;
$mapaNuevaProyInv = [];

foreach ($detalles as $d) {
    $k = trim($d['rubro']) . '|' . trim($d['categoria_padre']);
    $sp = $spRows[$k] ?? null;

    $baseVer = $sp ? PresupuestoCalculos::extraerColumnaVentaAnterior($sp, 'VERANO') : null;
    $baseInv = $sp ? PresupuestoCalculos::extraerColumnaVentaAnterior($sp, 'INVIERNO') : null;

    $newVva = $baseVer ? (int)round($baseVer['valor']) : 0;
    $newVia = $baseInv ? (int)round($baseInv['valor']) : 0;
    $codVer = $baseVer ? $baseVer['codigo'] : null;
    $codInv = $baseInv ? $baseInv['codigo'] : null;

    $idxVer = (float)$d['indice_verano_variacion'];
    $idxInv = (float)$d['indice_invierno_variacion'];

    $newVpv = (int)PresupuestoCalculos::calcularVentaProyectadaVerano($newVva, $idxVer, $fechaCalculo, 'invierno');

    // Conservar ratio exacto en filas donde la versión oficial guardada tenía índice con mayor precisión que 2 decimales
    $oldVia = (float)$d['venta_invierno_anterior'];
    $oldVpi = (int)$d['venta_proyectada_invierno'];
    $calcOldVpi = (int)PresupuestoCalculos::calcularVentaProyectadaInvierno($oldVia, $idxInv, $fechaCalculo, 'invierno');
    if ($oldVia > 0 && $oldVpi !== $calcOldVpi && $newVia > 0) {
        $newVpi = (int)round($newVia * ($oldVpi / $oldVia));
    } else {
        $newVpi = (int)PresupuestoCalculos::calcularVentaProyectadaInvierno($newVia, $idxInv, $fechaCalculo, 'invierno');
    }

    $stockProy = (float)$d['stock_proyectado'];
    $newCompra = (int)(round($stockProy) - $newVpv - $newVpi);

    $ventasPorTramo = [];
    foreach ($periodos['verano']['tramos'] as $t) {
        $ventasPorTramo[PresupuestoCalculos::claveTramo($t)] = $newVpv;
    }
    foreach ($periodos['invierno']['tramos'] as $t) {
        $ventasPorTramo[PresupuestoCalculos::claveTramo($t)] = $newVpi;
    }

    $tramos = PresupuestoCalculos::repartirCompraPorTramo(
        $stockProy,
        $tramosCron,
        [
            'VERANO'   => ['valor' => $newVva, 'indice' => $idxVer],
            'INVIERNO' => ['valor' => $newVia, 'indice' => $idxInv]
        ],
        $diasRestantes,
        $diasTotales,
        $periodos['objetivo'],
        $ventasPorTramo
    );

    $sumCompraTramos = array_sum(array_column($tramos, 'compra'));
    if ($sumCompraTramos !== max(0, -$newCompra)) {
        die("Invariante roto en {$k}: sumCompraTramos={$sumCompraTramos} vs max(0, -newCompra)=" . max(0, -$newCompra) . "\n");
    }

    $sumOldInvAnt  += (int)$d['venta_invierno_anterior'];
    $sumNewInvAnt  += $newVia;
    $sumOldProyInv += $oldVpi;
    $sumNewProyInv += $newVpi;
    $sumOldProyVer += (int)$d['venta_proyectada_verano'];
    $sumNewProyVer += $newVpv;

    $mapaNuevaProyInv[$k] = $newVpi;
    $actualizacionesHist[] = [
        'id_detalle' => (int)$d['id'],
        'rubro' => trim($d['rubro']),
        'categoria_padre' => trim($d['categoria_padre']),
        'vva' => $newVva, 'cod_ver' => $codVer, 'vpv' => $newVpv,
        'via' => $newVia, 'cod_inv' => $codInv, 'vpi' => $newVpi,
        'compra' => $newCompra,
        'tramos' => $tramos
    ];
}

echo "   Venta Inv. Anterior:   {$sumOldInvAnt} -> {$sumNewInvAnt}\n";
echo "   Venta Proy. Invierno:  {$sumOldProyInv} -> {$sumNewProyInv}\n";
echo "   Venta Proy. Verano:    {$sumOldProyVer} -> {$sumNewProyVer}\n\n";

// ---------------------------------------------------------------------
// 3. Recalcular última versión de Distribución (Invierno)
// ---------------------------------------------------------------------
$qUltDist = sqlsrv_query($cidApps, "
    SELECT TOP 1 nombre_distribucion
    FROM dbo.FP_T_DISTRIBUCION_COMPRAS_CANAL
    WHERE pais = 'argentina' AND temporada = 'INVIERNO'
    GROUP BY nombre_distribucion
    ORDER BY MAX(fecha_guardado) DESC
");
$rowUltDist = sqlsrv_fetch_array($qUltDist, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($qUltDist);

$nombreDist = $rowUltDist ? trim($rowUltDist['nombre_distribucion']) : '';
if ($nombreDist === '') {
    die("No se encontro distribucion de INVIERNO para argentina.\n");
}
echo "3) Ultima version de Distribucion (Invierno): '{$nombreDist}'\n";

$distribucionModel = new Distribucion();
$guardadosDist = $distribucionModel->obtenerDistribucionGuardada('argentina', 'INVIERNO', $nombreDist);
if (empty($guardadosDist)) {
    die("La distribucion '{$nombreDist}' no tiene filas.\n");
}

$periodoAnalisis = $guardadosDist[0]['periodo_analisis'] ?? '2026-02-01 a 2026-07-31';
$partesPer = explode(' a ', $periodoAnalisis);
$fechaDesde = trim($partesPer[0] ?? '2026-02-01');
$fechaHasta = trim($partesPer[1] ?? '2026-07-31');

// En preview excluimos XL RURAL de las ventas obtenidas (en --aplicar ya lo excluye construirFiltroVentasPresupuesto)
$ventasCanalTodas = $distribucionModel->obtenerTodasLasVentasPorCanal($fechaDesde, $fechaHasta);

// Agrupar filas por rubro|categoria_padre
$gruposDist = [];
foreach ($guardadosDist as $g) {
    $k = trim($g['rubro']) . '|' . trim($g['categoria_padre']);
    $gruposDist[$k][] = $g;
}

$filasDistActualizar = [];
$sumOldDist = 0; $sumNewDist = 0; $sumNewVtaCanal = 0;

foreach ($gruposDist as $k => $canalesRows) {
    $compraProyGrupo = $mapaNuevaProyInv[$k] ?? (int)$canalesRows[0]['compra_proyectada'];
    if ($compraProyGrupo < 0) $compraProyGrupo = 0;

    list($rubro, $cat) = explode('|', $k, 2);
    $vCanalMap = $ventasCanalTodas[$rubro][$cat] ?? [];

    // Canal más fuerte para ajuste de redondeo (misma lógica que DistribucionController)
    $canalMasFuerte = null;
    $maxVenta = -1;
    $sumaPart = 0.0;
    $sumaDist = 0;
    $tempFilas = [];

    foreach ($canalesRows as $row) {
        $canal = trim($row['canal']);
        $part = round((float)$row['participacion_porcentaje'], 0);
        $vHistCanal = (int)($vCanalMap[$canal]['total'] ?? 0);

        if ($vHistCanal > $maxVenta) {
            $maxVenta = $vHistCanal;
            $canalMasFuerte = $canal;
        }
        $sumaPart += $part;

        $uDist = (int)round($compraProyGrupo * ($part / 100));
        $sumaDist += $uDist;

        $sumOldDist += (int)$row['distribucion_final'];
        $sumNewVtaCanal += $vHistCanal;

        $tempFilas[$canal] = [
            'row' => $row,
            'vHistCanal' => $vHistCanal,
            'part' => $part,
            'uDist' => $uDist
        ];
    }

    if (abs($sumaPart - 100.0) < 0.1 && $canalMasFuerte !== null && isset($tempFilas[$canalMasFuerte])) {
        $tempFilas[$canalMasFuerte]['uDist'] += ($compraProyGrupo - $sumaDist);
    }

    foreach ($tempFilas as $canal => $tf) {
        $uDist = $tf['uDist'];
        $sumNewDist += $uDist;

        // Recalcular unidades mensuales conservando exactamente los porcentajes guardados
        $jsonStr = $tf['row']['distribucion_mensual_json'] ?? null;
        $nuevoJsonStr = $jsonStr;
        if (!empty($jsonStr)) {
            $mesesArr = json_decode($jsonStr, true);
            if (is_array($mesesArr) && !empty($mesesArr)) {
                $sumaMeses = 0;
                $mesMayor = null;
                $maxP = -1;
                foreach ($mesesArr as $mLabel => &$mData) {
                    $p = (float)($mData['porcentaje'] ?? 0) / 100.0;
                    if ($p > $maxP) {
                        $maxP = $p;
                        $mesMayor = $mLabel;
                    }
                    $uMes = (int)round($uDist * $p);
                    $mData['unidades'] = $uMes;
                    $sumaMeses += $uMes;
                }
                unset($mData);
                if ($uDist !== $sumaMeses && $mesMayor !== null) {
                    $mesesArr[$mesMayor]['unidades'] += ($uDist - $sumaMeses);
                }
                $nuevoJsonStr = json_encode($mesesArr, JSON_UNESCAPED_UNICODE);
            }
        }

        $filasDistActualizar[] = [
            'pais' => 'argentina',
            'temporada' => 'INVIERNO',
            'rubro' => $rubro,
            'categoria_padre' => $cat,
            'canal' => $canal,
            'compra_proyectada' => $compraProyGrupo,
            'venta_historica_canal' => $tf['vHistCanal'],
            'participacion_porcentaje' => $tf['part'],
            'compra_distribuida' => $uDist,
            'ajuste_manual' => (int)($tf['row']['ajuste_manual'] ?? 0),
            'distribucion_final' => $uDist,
            'periodo_analisis' => $periodoAnalisis,
            'nombre_distribucion' => $nombreDist,
            'distribucion_mensual_json' => $nuevoJsonStr
        ];
    }
}

echo "   Venta Hist. Canal total: {$sumNewVtaCanal}\n";
echo "   Distribucion final:      {$sumOldDist} -> {$sumNewDist}\n\n";

if (!$aplicar) {
    echo "[PREVIEW] Sin escritura. Ejecutar con --aplicar para guardar estos cambios.\n";
    exit(0);
}

// ---------------------------------------------------------------------
// 4. Backup JSON + Escritura transaccional
// ---------------------------------------------------------------------
$backupDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'presupuestos_backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}
$backupFile = $backupDir . DIRECTORY_SEPARATOR . 'backup_estandarizacion_' . date('Ymd_His') . '.json';
file_put_contents($backupFile, json_encode([
    'cabecera_id' => $idCabecera,
    'historial_detalle' => $detalles,
    'distribucion_nombre' => $nombreDist,
    'distribucion_filas' => $guardadosDist
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "4) Backup guardado en: $backupFile\n";

if (sqlsrv_begin_transaction($cidPower) === false) {
    die("No se pudo iniciar transaccion en POWER_BI_CONTROL.\n");
}

try {
    $sqlUpdDet = "
        UPDATE dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO
        SET venta_verano_anterior = ?,
            temporada_base_verano = ?,
            venta_proyectada_verano = ?,
            venta_invierno_anterior = ?,
            temporada_base_invierno = ?,
            venta_proyectada_invierno = ?,
            compra_proyectada = ?
        WHERE id = ? AND id_cabecera = ?
    ";
    $sqlDelTramo = "DELETE FROM dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO WHERE id_cabecera = ? AND id_detalle = ?";
    $sqlInsTramo = "
        INSERT INTO dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO (
            id_cabecera, id_detalle, rubro, categoria_padre, orden,
            temporada_codigo, temporada_tipo, temporada_desde, temporada_hasta,
            es_resto, es_objetivo, es_comprable,
            venta_proyectada, stock_aplicado, compra, compra_deficit_cobertura
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    foreach ($actualizacionesHist as $act) {
        $stUpd = sqlsrv_query($cidPower, $sqlUpdDet, [
            $act['vva'], $act['cod_ver'], $act['vpv'],
            $act['via'], $act['cod_inv'], $act['vpi'],
            $act['compra'], $act['id_detalle'], $idCabecera
        ]);
        if ($stUpd === false) {
            throw new Exception("Error actualizando detalle {$act['id_detalle']}: " . print_r(sqlsrv_errors(), true));
        }
        sqlsrv_free_stmt($stUpd);

        $stDel = sqlsrv_query($cidPower, $sqlDelTramo, [$idCabecera, $act['id_detalle']]);
        if ($stDel === false) {
            throw new Exception("Error borrando tramos {$act['id_detalle']}: " . print_r(sqlsrv_errors(), true));
        }
        sqlsrv_free_stmt($stDel);

        foreach ($act['tramos'] as $t) {
            $stIns = sqlsrv_query($cidPower, $sqlInsTramo, [
                $idCabecera, $act['id_detalle'], $act['rubro'], $act['categoria_padre'], $t['orden'],
                $t['temporada_codigo'], $t['temporada_tipo'], $t['temporada_desde'], $t['temporada_hasta'],
                $t['es_resto'] ? 1 : 0, $t['es_objetivo'] ? 1 : 0, $t['es_comprable'] ? 1 : 0,
                $t['venta_proyectada'], $t['stock_aplicado'], $t['compra'], $t['compra_deficit_cobertura']
            ]);
            if ($stIns === false) {
                throw new Exception("Error insertando tramo {$act['id_detalle']}: " . print_r(sqlsrv_errors(), true));
            }
            sqlsrv_free_stmt($stIns);
        }
    }

    sqlsrv_commit($cidPower);
    echo "   OK Version oficial #{$idCabecera} actualizada.\n";
} catch (Exception $e) {
    sqlsrv_rollback($cidPower);
    die("Rollback en POWER_BI_CONTROL: " . $e->getMessage() . "\n");
}

$resDist = $distribucionModel->guardarDistribucion($filasDistActualizar);
if ($resDist !== true) {
    die("Error actualizando distribucion '{$nombreDist}': " . ($resDist['mensaje'] ?? 'desconocido') . "\n");
}
echo "   OK Distribucion '{$nombreDist}' y su consolidado actualizados.\n";
echo "\nEstandarizacion aplicada con exito.\n";
