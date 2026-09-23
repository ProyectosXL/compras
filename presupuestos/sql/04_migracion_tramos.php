<?php
/* =====================================================================
   Fase 3 - Reconstruccion del reparto por tramo de las versiones guardadas

   POR QUE ESTA MIGRACION ES PHP Y NO .SQL
   Por lo mismo que la 02: el reparto es la funcion pura
   PresupuestoCalculos::repartirCompraPorTramo(), y reescribirla en T-SQL habria
   creado una segunda implementacion del mismo calculo, que es justo lo que las
   fases anteriores vinieron a eliminar. Reconstruir desde PHP garantiza que una
   version migrada y una guardada hoy salgan del mismo codigo.

   QUE HACE
   Por cada version con cabecera, recalcula el reparto por tramo de cada fila de
   detalle y lo escribe en RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO.

   NO inventa nada. El reparto es reproducible solo si la cabecera guardo los dos
   factores del prorrateo —dias_restantes_temporada y dias_totales_temporada—,
   porque el resto de la temporada en curso se prorratea contra ellos. Se usan
   los GUARDADOS y no los de hoy: recalcularlos con la fecha actual daria otro
   numero para la misma version.

   Cada version se verifica ANTES de escribirse: la suma de los tramos de verano
   tiene que dar venta_proyectada_verano, la de invierno venta_proyectada_invierno
   y la suma de las compras MAX(0, -compra_proyectada), fila por fila. La version
   que no cierra exacto NO se migra: queda marcada SIN_REPARTO con el motivo, para
   que se vea que le falta el dato y no que nunca lo tuvo.

   NO borra ni modifica datos existentes: solo inserta filas de tramo y completa
   tramos_estado / tramos_observacion en la cabecera, que hoy estan en NULL.

   COMO CORRERLO
     php 04_migracion_tramos.php                 -> solo muestra que quedaria
     php 04_migracion_tramos.php --aplicar       -> escribe
     php 04_migracion_tramos.php --pais=uruguay  -> la otra base

   Reejecutable: las versiones que ya tienen tramos se saltean.
   ===================================================================== */

if (PHP_SAPI !== 'cli') {
    die("Este script se corre por linea de comandos.\n");
}

// Misma zona horaria que la aplicacion. Ver el comentario de 02_migracion_versiones.php.
date_default_timezone_set('America/Argentina/Buenos_Aires');

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
echo "Migracion del reparto por tramo - pais: $pais  ($nameServer)\n";
echo "Modo: " . ($aplicar ? "APLICAR (escribe)" : "PREVIEW (no escribe nada)") . "\n";
echo "=====================================================================\n\n";

/* --------------------------------------------------------------------
   Requisito: el 03 ya tiene que estar aplicado.
   -------------------------------------------------------------------- */
$chk = sqlsrv_query($cid, "
    SELECT
        CASE WHEN OBJECT_ID('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO','U') IS NULL THEN 0 ELSE 1 END AS tra,
        CASE WHEN COL_LENGTH('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA','tramos_estado') IS NULL THEN 0 ELSE 1 END AS col");
$estado = sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($chk);
if (!$estado['tra'] || !$estado['col']) {
    die("Falta correr antes 03_compra_por_tramo.sql en esta base.\n");
}

/* --------------------------------------------------------------------
   Versiones con cabecera que todavia no tienen tramos.
   -------------------------------------------------------------------- */
$st = sqlsrv_query($cid, "
    SELECT c.id, c.nombre_presupuesto, c.solapa, c.fecha_calculo,
           c.dias_restantes_temporada, c.dias_totales_temporada,
           c.temporada_objetivo, c.es_completa, c.es_oficial,
           (SELECT COUNT(*) FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO d
             WHERE d.id_cabecera = c.id) AS filas_detalle
      FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA c
     WHERE NOT EXISTS (SELECT 1 FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO t
                        WHERE t.id_cabecera = c.id)
     ORDER BY c.id");
if ($st === false) {
    die("Error leyendo las versiones: " . print_r(sqlsrv_errors(), true));
}

$versiones = [];
while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
    $versiones[] = $r;
}
sqlsrv_free_stmt($st);

/* --------------------------------------------------------------------
   Filas sueltas: detalle sin cabecera.

   No se pueden reconstruir porque los dias del prorrateo viven en la cabecera.
   Se informan para que quede claro que existen y que la solucion es correr antes
   02_migracion_versiones.php, no que este script las ignore en silencio.
   -------------------------------------------------------------------- */
$st = sqlsrv_query($cid, "
    SELECT COUNT(*) AS filas, COUNT(DISTINCT nombre_presupuesto) AS versiones
      FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO WHERE id_cabecera IS NULL");
$sueltas = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($st);
if ((int)$sueltas['filas'] > 0) {
    echo "AVISO: hay {$sueltas['filas']} fila(s) de detalle sin cabecera, en "
       . "{$sueltas['versiones']} version(es). Sin cabecera no hay dias guardados y el\n";
    echo "       reparto no se puede reconstruir. Correr antes 02_migracion_versiones.php.\n\n";
}

if (empty($versiones)) {
    echo "No hay versiones pendientes de reconstruir.\n";
    exit(0);
}

/* --------------------------------------------------------------------
   Reconstruir y verificar, sin escribir todavia.
   -------------------------------------------------------------------- */
$plan = [];

foreach ($versiones as $v) {
    $idCab = (int)$v['id'];
    $nombre = $v['nombre_presupuesto'];
    $solapa = strtolower(trim((string)$v['solapa'])) === 'invierno' ? 'invierno' : 'verano';

    $entrada = [
        'id' => $idCab, 'nombre' => $nombre, 'solapa' => $solapa,
        'filas' => (int)$v['filas_detalle'], 'oficial' => (int)$v['es_oficial'],
        'objetivo' => $v['temporada_objetivo'],
        'estado' => null, 'motivo' => null, 'tramos' => [], 'totales' => []
    ];

    // ---- condiciones para poder reconstruir exacto
    $dRest = $v['dias_restantes_temporada'];
    $dTot  = $v['dias_totales_temporada'];

    if ($dRest === null || $dTot === null || (int)$dTot <= 0) {
        $entrada['estado'] = 'SIN_REPARTO';
        $entrada['motivo'] = 'La cabecera no tiene los dias de temporada, que son los dos '
                           . 'factores del prorrateo del resto. No se reconstruye para no inventar el reparto.';
        $plan[] = $entrada;
        continue;
    }
    if ($entrada['filas'] === 0) {
        $entrada['estado'] = 'SIN_REPARTO';
        $entrada['motivo'] = 'La version no tiene filas de detalle.';
        $plan[] = $entrada;
        continue;
    }

    $fechaCalculo = $v['fecha_calculo'] instanceof DateTime
        ? $v['fecha_calculo']->format('Y-m-d')
        : substr((string)$v['fecha_calculo'], 0, 10);

    // ---- detalle de la version
    $stDet = sqlsrv_query($cid, "
        SELECT id, rubro, categoria_padre, stock_proyectado,
               venta_verano_anterior, indice_verano_variacion, venta_proyectada_verano,
               venta_invierno_anterior, indice_invierno_variacion, venta_proyectada_invierno,
               compra_proyectada
          FROM RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO
         WHERE id_cabecera = ? ORDER BY id", [$idCab]);
    if ($stDet === false) {
        die("Error leyendo el detalle de la version $idCab: " . print_r(sqlsrv_errors(), true));
    }

    $filasTramo = [];
    $totales = [];
    $desvios = [];

    while ($d = sqlsrv_fetch_array($stDet, SQLSRV_FETCH_ASSOC)) {
        $tramos = PresupuestoCalculos::calcularTramosDeFila(
            (float)$d['stock_proyectado'],
            (float)$d['venta_verano_anterior'],   (float)$d['indice_verano_variacion'],
            (float)$d['venta_invierno_anterior'], (float)$d['indice_invierno_variacion'],
            $fechaCalculo,
            $solapa,
            // Los dias GUARDADOS, no los de hoy: es lo que hace que la reconstruccion
            // de una version de abril siga dando lo que dio en abril.
            (int)$dRest,
            (int)$dTot
        );

        $sumaV = 0; $sumaI = 0; $sumaCompra = 0;
        foreach ($tramos as $t) {
            if ($t['temporada_tipo'] === 'VERANO') { $sumaV += $t['venta_proyectada']; }
            else { $sumaI += $t['venta_proyectada']; }
            $sumaCompra += $t['compra'];

            $clave = ($t['es_resto'] ? 'Resto ' : '') . $t['temporada_codigo'];
            if (!isset($totales[$clave])) {
                $totales[$clave] = ['venta' => 0, 'compra' => 0, 'deficit' => 0,
                                    'objetivo' => $t['es_objetivo'], 'comprable' => $t['es_comprable']];
            }
            $totales[$clave]['venta']   += $t['venta_proyectada'];
            $totales[$clave]['compra']  += $t['compra'];
            $totales[$clave]['deficit'] += $t['compra_deficit_cobertura'];
        }

        // ---- las tres verificaciones, fila por fila
        $esperadoCompra = max(0, -(float)$d['compra_proyectada']);
        $etiqueta = trim((string)$d['rubro']) . '|' . trim((string)$d['categoria_padre']);

        if (abs($sumaV - (float)$d['venta_proyectada_verano']) > 0.0001) {
            $desvios[] = "$etiqueta: venta verano guardada {$d['venta_proyectada_verano']}, reconstruida $sumaV";
        }
        if (abs($sumaI - (float)$d['venta_proyectada_invierno']) > 0.0001) {
            $desvios[] = "$etiqueta: venta invierno guardada {$d['venta_proyectada_invierno']}, reconstruida $sumaI";
        }
        if (abs($sumaCompra - $esperadoCompra) > 0.0001) {
            $desvios[] = "$etiqueta: suma de compras $sumaCompra, esperado $esperadoCompra";
        }

        $filasTramo[] = ['id_detalle' => (int)$d['id'], 'rubro' => $d['rubro'],
                         'categoria' => $d['categoria_padre'], 'tramos' => $tramos];
    }
    sqlsrv_free_stmt($stDet);

    if ($desvios) {
        $entrada['estado'] = 'SIN_REPARTO';
        $entrada['motivo'] = 'La reconstruccion no dio exacto en ' . count($desvios) . ' verificacion(es). '
                           . 'Primera: ' . $desvios[0];
        $entrada['desvios'] = $desvios;
    } else {
        $entrada['estado'] = 'RECONSTRUIDO';
        $entrada['tramos'] = $filasTramo;
        $entrada['totales'] = $totales;
    }

    $plan[] = $entrada;
}

/* --------------------------------------------------------------------
   Mostrar el plan.
   -------------------------------------------------------------------- */
$fmt = function ($x) { return number_format((float)$x, 0, ',', '.'); };

printf("%-4s %-38s %-9s %-11s %-7s %-4s %s\n",
    'ID', 'NOMBRE', 'SOLAPA', 'OBJETIVO', 'FILAS', 'OFI', 'ESTADO');
echo str_repeat('-', 110), "\n";
foreach ($plan as $p) {
    printf("%-4d %-38s %-9s %-11s %-7d %-4s %s\n",
        $p['id'], substr($p['nombre'], 0, 38), $p['solapa'], $p['objetivo'],
        $p['filas'], $p['oficial'] ? 'si' : '', $p['estado']);
}

foreach ($plan as $p) {
    echo "\n  [{$p['id']}] {$p['nombre']}\n";
    if ($p['estado'] === 'SIN_REPARTO') {
        echo "      SIN REPARTO: {$p['motivo']}\n";
        foreach (array_slice($p['desvios'] ?? [], 0, 5) as $d) {
            echo "        - $d\n";
        }
        continue;
    }

    $sumaTotal = 0;
    foreach ($p['totales'] as $clave => $t) {
        printf("      %s%s %-20s venta %12s   compra %12s%s\n",
            $t['objetivo'] ? '*' : ' ', $t['comprable'] ? ' ' : '#', $clave,
            $fmt($t['venta']), $fmt($t['compra']),
            $t['deficit'] ? '   (deficit ' . $fmt($t['deficit']) . ')' : '');
        $sumaTotal += $t['compra'];
    }
    printf("      %-23s %31s\n", 'suma de tramos', $fmt($sumaTotal));
    echo "      verificado: la suma de los tramos da la venta proyectada y la compra de cada fila.\n";
}

echo "\n  (* temporada objetivo, # tramo no comprable: resto de la temporada en curso)\n";

$rec = 0; $sin = 0; $filasTramo = 0;
foreach ($plan as $p) {
    if ($p['estado'] === 'RECONSTRUIDO') {
        $rec++;
        foreach ($p['tramos'] as $f) { $filasTramo += count($f['tramos']); }
    } else {
        $sin++;
    }
}
echo "\nResumen: " . count($plan) . " version(es) - $rec reconstruida(s) ($filasTramo fila(s) de tramo), "
   . "$sin sin reparto.\n";

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
    $sqlTra = "INSERT INTO RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO (
                    id_cabecera, id_detalle, rubro, categoria_padre, orden,
                    temporada_codigo, temporada_tipo, temporada_desde, temporada_hasta,
                    es_resto, es_objetivo, es_comprable,
                    venta_proyectada, stock_aplicado, compra, compra_deficit_cobertura
               ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $insertadas = 0;

    foreach ($plan as $p) {
        if ($p['estado'] === 'RECONSTRUIDO') {
            foreach ($p['tramos'] as $f) {
                foreach ($f['tramos'] as $t) {
                    $stmt = sqlsrv_query($cid, $sqlTra, [
                        $p['id'], $f['id_detalle'], $f['rubro'], $f['categoria'], $t['orden'],
                        $t['temporada_codigo'], $t['temporada_tipo'], $t['temporada_desde'], $t['temporada_hasta'],
                        $t['es_resto'] ? 1 : 0, $t['es_objetivo'] ? 1 : 0, $t['es_comprable'] ? 1 : 0,
                        $t['venta_proyectada'], $t['stock_aplicado'], $t['compra'], $t['compra_deficit_cobertura']
                    ]);
                    if ($stmt === false) {
                        throw new Exception("Tramo de la version {$p['id']}: " . print_r(sqlsrv_errors(), true));
                    }
                    sqlsrv_free_stmt($stmt);
                    $insertadas++;
                }
            }
        }

        // El estado se escribe SIEMPRE, tambien para las que no se pudieron
        // reconstruir: es lo que distingue "no tiene reparto" de "todavia no se migro".
        $up = sqlsrv_query($cid,
            "UPDATE RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA
                SET tramos_estado = ?, tramos_observacion = ?
              WHERE id = ?",
            [$p['estado'], $p['motivo'], $p['id']]);
        if ($up === false) {
            throw new Exception("Estado de la version {$p['id']}: " . print_r(sqlsrv_errors(), true));
        }
        sqlsrv_free_stmt($up);

        echo "  version {$p['id']}  {$p['estado']}  {$p['nombre']}\n";
    }

    sqlsrv_commit($cid);
    echo "\nOK: $rec version(es) reconstruida(s), $insertadas fila(s) de tramo insertada(s), "
       . "$sin marcada(s) SIN_REPARTO.\n";

} catch (Exception $e) {
    sqlsrv_rollback($cid);
    echo "\nERROR, se revirtio todo: " . $e->getMessage() . "\n";
    exit(1);
}
