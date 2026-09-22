<?php
/* =====================================================================
   Fase 2 - Migracion de las versiones ya guardadas a la cabecera

   POR QUE ESTA MIGRACION ES PHP Y NO .SQL
   Deducir la temporada objetivo de una version vieja es exactamente el calculo
   que la fase 1 dejo en un solo lugar (PresupuestoCalculos). Reescribirlo en
   T-SQL habria creado una quinta implementacion del calendario de temporadas,
   que es el problema que la fase 1 vino a resolver. El script vive igual en
   presupuestos/sql/ porque es un artefacto de migracion, y deja una consulta de
   verificacion en SQL puro (02_migracion_verificacion.sql) para auditar el
   resultado sin depender de PHP.

   QUE HACE
   Por cada version guardada (nombre_presupuesto + pais) crea una fila de
   cabecera y la enlaza con sus filas de detalle. NO borra ni modifica ningun
   dato existente: solo completa id_cabecera, que hoy esta en NULL.

   REGLAS
   - La temporada objetivo se deduce de la fecha de guardado y de la solapa.
   - Se marca parcial toda version que no cubra el universo completo de
     rubro/categoria.
   - NINGUNA queda como oficial: marcar la vigente es una decision de negocio y
     se hace desde el historial.

   COMO CORRERLO
     php 02_migracion_versiones.php                 -> solo muestra que quedaria
     php 02_migracion_versiones.php --aplicar       -> escribe
     php 02_migracion_versiones.php --pais=uruguay  -> la otra base

   Reejecutable: las versiones que ya tienen cabecera se saltean.
   ===================================================================== */

if (PHP_SAPI !== 'cli') {
    die("Este script se corre por linea de comandos.\n");
}

require_once __DIR__ . '/../../class/conexion.php';
require_once __DIR__ . '/../class/presupuestoCalculos.php';

$aplicar = in_array('--aplicar', $argv, true);
$pais = 'argentina';
foreach ($argv as $a) {
    if (strpos($a, '--pais=') === 0) {
        $pais = strtolower(substr($a, 7));
    }
}

$nameServer = ($pais === 'uruguay' || $pais === 'uy') ? 'apps_power_uy' : 'apps_power';
$pais = ($nameServer === 'apps_power_uy') ? 'uruguay' : 'argentina';

$conexion = new Conexion();
$cid = $conexion->conectar($nameServer);
if (!$cid) {
    die("No se pudo conectar a $nameServer\n");
}

echo "=====================================================================\n";
echo "Migracion de versiones - pais: $pais  ($nameServer)\n";
echo "Modo: " . ($aplicar ? "APLICAR (escribe)" : "PREVIEW (no escribe nada)") . "\n";
echo "=====================================================================\n\n";

/* --------------------------------------------------------------------
   Requisito: el 01 ya tiene que estar aplicado.
   -------------------------------------------------------------------- */
$chk = sqlsrv_query($cid, "
    SELECT
        CASE WHEN OBJECT_ID('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA','U') IS NULL THEN 0 ELSE 1 END AS cab,
        CASE WHEN COL_LENGTH('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO','id_cabecera') IS NULL THEN 0 ELSE 1 END AS col");
$estado = sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC);
if (!$estado['cab'] || !$estado['col']) {
    die("Falta correr antes 01_cabecera_versiones.sql en esta base.\n");
}

/* --------------------------------------------------------------------
   Universo actual de rubro/categoria, para decidir completa vs parcial.

   Se compara contra el universo de HOY porque la tabla de origen se reconstruye
   a diario y no hay registro de cuantas combinaciones habia el dia en que se
   guardo cada version. Es una aproximacion, y por eso el criterio es
   conservador: solo se marca completa si cubre todo. Ante la duda, parcial, que
   ademas impide que se la marque como oficial.
   -------------------------------------------------------------------- */
$st = sqlsrv_query($cid, "SELECT COUNT(*) AS n FROM RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS");
$filasTotales = (int)sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)['n'];
echo "Universo actual de rubro/categoria: $filasTotales\n\n";

/* --------------------------------------------------------------------
   Versiones guardadas que todavia no tienen cabecera.
   -------------------------------------------------------------------- */
$sql = "
    SELECT  nombre_presupuesto,
            temporada,
            MAX(pais) AS pais,
            MIN(fecha_guardado) AS fecha_guardado,
            COUNT(*) AS filas,
            COUNT(DISTINCT ISNULL(rubro,'') + '|' + ISNULL(categoria_padre,'')) AS combos
    FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO
    WHERE id_cabecera IS NULL
    GROUP BY nombre_presupuesto, temporada
    ORDER BY MIN(fecha_guardado)";

$st = sqlsrv_query($cid, $sql);
if ($st === false) {
    die("Error leyendo el historial: " . print_r(sqlsrv_errors(), true));
}

$versiones = [];
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $versiones[] = $r;
}

if (empty($versiones)) {
    echo "No hay versiones pendientes de migrar.\n";
    exit(0);
}

/* --------------------------------------------------------------------
   Armar lo que quedaria.
   -------------------------------------------------------------------- */
$plan = [];
foreach ($versiones as $v) {
    $fecha = $v['fecha_guardado'] instanceof DateTime
        ? $v['fecha_guardado']
        : new DateTime((string)$v['fecha_guardado']);

    $solapa = strtolower(trim((string)$v['temporada'])) === 'invierno' ? 'invierno' : 'verano';
    $fechaCalculo = $fecha->format('Y-m-d');

    // Mismo calculo que usa la pantalla: sin duplicar la logica de temporadas.
    $periodos = PresupuestoCalculos::obtenerPeriodosProyeccion($fechaCalculo, $solapa);
    $temporadaActual = PresupuestoCalculos::obtenerTemporadaActual($fechaCalculo);

    $combos = (int)$v['combos'];
    $completa = ($filasTotales > 0 && $combos >= $filasTotales) ? 1 : 0;

    $plan[] = [
        'nombre'      => $v['nombre_presupuesto'],
        'solapa'      => $solapa,
        // El pais guardado en el detalle manda; si viniera vacio se usa el de la base.
        'pais'        => trim((string)$v['pais']) !== '' ? strtolower(trim($v['pais'])) : $pais,
        'fecha'       => $fecha->format('Y-m-d H:i:s'),
        'fecha_calc'  => $fechaCalculo,
        'dias_rest'   => PresupuestoCalculos::calcularDiasRestantesTemporada($fechaCalculo),
        'dias_tot'    => $temporadaActual['dias'],
        'temp_actual' => $temporadaActual['codigo'],
        'objetivo'    => $periodos['objetivo']['codigo'],
        'obj_desde'   => $periodos['objetivo']['desde'],
        'obj_hasta'   => $periodos['objetivo']['hasta'],
        'pv_desde'    => $periodos['verano']['desde'],
        'pv_hasta'    => $periodos['verano']['hasta'],
        'pv_etiq'     => $periodos['verano']['etiqueta'],
        'pi_desde'    => $periodos['invierno']['desde'],
        'pi_hasta'    => $periodos['invierno']['hasta'],
        'pi_etiq'     => $periodos['invierno']['etiqueta'],
        'filas'       => (int)$v['filas'],
        'combos'      => $combos,
        'completa'    => $completa
    ];
}

/* --------------------------------------------------------------------
   Mostrar el plan.
   -------------------------------------------------------------------- */
printf("%-38s %-9s %-16s %-10s %-11s %-9s %s\n",
    'NOMBRE', 'SOLAPA', 'GUARDADO', 'EN CURSO', 'OBJETIVO', 'FILAS', 'ESTADO');
echo str_repeat('-', 120), "\n";
foreach ($plan as $p) {
    printf("%-38s %-9s %-16s %-10s %-11s %-9s %s\n",
        substr($p['nombre'], 0, 38),
        $p['solapa'],
        substr($p['fecha'], 0, 16),
        $p['temp_actual'],
        $p['objetivo'],
        $p['combos'] . '/' . $filasTotales,
        $p['completa'] ? 'COMPLETA' : 'PARCIAL'
    );
}

echo "\nPeriodos que cubre cada version:\n";
foreach ($plan as $p) {
    echo "  " . $p['nombre'] . "\n";
    echo "     objetivo : {$p['objetivo']}  ({$p['obj_desde']} a {$p['obj_hasta']})\n";
    echo "     vta ver. : {$p['pv_etiq']}  ({$p['pv_desde']} a {$p['pv_hasta']})\n";
    echo "     vta inv. : {$p['pi_etiq']}  ({$p['pi_desde']} a {$p['pi_hasta']})\n";
}

$completas = 0;
foreach ($plan as $p) { $completas += $p['completa']; }
echo "\nResumen: " . count($plan) . " version(es) a migrar - "
   . $completas . " completa(s), " . (count($plan) - $completas) . " parcial(es).\n";
echo "Ninguna queda como oficial.\n";

if (!$aplicar) {
    echo "\nPREVIEW: no se escribio nada. Volver a correr con --aplicar para migrar.\n";
    exit(0);
}

/* --------------------------------------------------------------------
   Aplicar. Todo dentro de una transaccion: o se migran todas o ninguna.
   -------------------------------------------------------------------- */
echo "\nAplicando...\n";

if (sqlsrv_begin_transaction($cid) === false) {
    die("No se pudo iniciar la transaccion: " . print_r(sqlsrv_errors(), true));
}

try {
    $sqlCab = "INSERT INTO RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA (
                    nombre_presupuesto, fecha_guardado, usuario_guardado, pais, solapa,
                    fecha_calculo, dias_restantes_temporada, dias_totales_temporada,
                    temporada_objetivo, temporada_objetivo_desde, temporada_objetivo_hasta,
                    periodo_venta_verano_desde, periodo_venta_verano_hasta, periodo_venta_verano_etiqueta,
                    periodo_venta_invierno_desde, periodo_venta_invierno_hasta, periodo_venta_invierno_etiqueta,
                    es_completa, filas_guardadas, filas_totales, es_oficial
               ) OUTPUT INSERTED.id
               VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";

    $migradas = 0;
    $filasEnlazadas = 0;

    foreach ($plan as $p) {
        $stmt = sqlsrv_query($cid, $sqlCab, [
            $p['nombre'], $p['fecha'], $p['pais'], $p['solapa'],
            $p['fecha_calc'], $p['dias_rest'], $p['dias_tot'],
            $p['objetivo'], $p['obj_desde'], $p['obj_hasta'],
            $p['pv_desde'], $p['pv_hasta'], $p['pv_etiq'],
            $p['pi_desde'], $p['pi_hasta'], $p['pi_etiq'],
            $p['completa'], $p['combos'], $filasTotales
        ]);
        if ($stmt === false) {
            throw new Exception("Insert de cabecera '{$p['nombre']}': " . print_r(sqlsrv_errors(), true));
        }
        $idCab = (int)sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC)[0];
        sqlsrv_free_stmt($stmt);

        // Solo se tocan las filas que siguen sin cabecera: si el script se corta
        // a la mitad y se vuelve a correr, no se repisa lo ya enlazado.
        $up = sqlsrv_query($cid,
            "UPDATE RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO
                SET id_cabecera = ?
              WHERE nombre_presupuesto = ? AND temporada = ? AND id_cabecera IS NULL",
            [$idCab, $p['nombre'], $p['solapa']]);
        if ($up === false) {
            throw new Exception("Enlace del detalle '{$p['nombre']}': " . print_r(sqlsrv_errors(), true));
        }

        $n = sqlsrv_rows_affected($up);
        sqlsrv_free_stmt($up);

        $migradas++;
        $filasEnlazadas += $n;
        echo "  cabecera $idCab  {$p['nombre']}  -> $n fila(s) de detalle\n";
    }

    sqlsrv_commit($cid);
    echo "\nOK: $migradas cabecera(s) creada(s), $filasEnlazadas fila(s) de detalle enlazada(s).\n";
    echo "Ninguna quedo como oficial.\n";

} catch (Exception $e) {
    sqlsrv_rollback($cid);
    echo "\nERROR, se revirtio todo: " . $e->getMessage() . "\n";
    exit(1);
}
