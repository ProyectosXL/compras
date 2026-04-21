<?php
// --- api/get_ordenes_pendientes_por_usuario.php (REINGENIERÍA PHP-SIDE MAPPING) ---
ini_set('display_errors', 1); 
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

if ($conn === null || $conn_sistemas === null) { 
    http_response_code(500); 
    echo json_encode(['error' => 'Error de conexión.']); 
    exit(); 
}

$usuario_seleccionado = isset($_GET['autorizador']) ? trim($_GET['autorizador']) : '';

if (empty($usuario_seleccionado)) { 
    http_response_code(400); 
    echo json_encode(['error' => 'Falta parámetro de autorizador.']); 
    exit(); 
}

// 1. Obtener Reglas desde Sistemas (Apps Server)
$sql_reglas = "SELECT COMPRADOR, USR_HASTA_100K, USR_HASTA_500K, USR_HASTA_2M, USR_MAYOR_2M FROM sistemas.dbo.FP_OC_REGLAS_AUTORIZACION";
$stmt_reglas = sqlsrv_query($conn_sistemas, $sql_reglas);
$reglas = [];
if ($stmt_reglas) {
    while ($r = sqlsrv_fetch_array($stmt_reglas, SQLSRV_FETCH_ASSOC)) {
        $reglas[$r['COMPRADOR']] = $r;
    }
    sqlsrv_free_stmt($stmt_reglas);
}

// 2. Obtener TODAS las OCs pendientes desde Central
$sql_ordenes = "
    SELECT
        A.N_ORDEN_CO AS numero,
        CAST(A.FECHA_INGRESO AS DATE) AS fecha,
        B.NOM_PROVEE AS proveedor,
        C.NOM_COMPRA AS comprador,
        A.COD_PROVEE AS cod_provee,
        A.OBSERVACIO AS observacion,
        CAST(A.TOTAL_CTE AS FLOAT) AS monto
    FROM CPA35 A
    INNER JOIN CPA01 B ON A.COD_PROVEE = B.COD_PROVEE
    INNER JOIN CPA50 C ON A.ID_CPA50 = C.ID_CPA50
    WHERE A.ESTADO = 1
    ORDER BY A.FECHA_INGRESO ASC;";

$stmt_ordenes = sqlsrv_query($conn, $sql_ordenes);
if ($stmt_ordenes === false) { 
    http_response_code(500); 
    echo json_encode(['error' => 'Error de BD al consultar órdenes.', 'details' => sqlsrv_errors()]); 
    exit(); 
}

$esDispatcher = ($usuario_seleccionado === 'RODRIAL' || $usuario_seleccionado === 'RODRIGOAL');
$resultado_final = [];

while ($row = sqlsrv_fetch_array($stmt_ordenes, SQLSRV_FETCH_ASSOC)) {
    // Aplicar lógica de Reglas en PHP
    $comprador = $row['comprador'];
    $monto = $row['monto'];
    $autorizador_asignado = null;

    if (isset($reglas[$comprador])) {
        $r = $reglas[$comprador];
        if ($monto <= 100000) {
            $autorizador_asignado = $r['USR_HASTA_100K'];
        } elseif ($monto <= 500000) {
            $autorizador_asignado = $r['USR_HASTA_500K'] ?: $r['USR_HASTA_100K'];
        } elseif ($monto <= 2000000) {
            $autorizador_asignado = $r['USR_HASTA_2M'] ?: ($r['USR_HASTA_500K'] ?: $r['USR_HASTA_100K']);
        } else {
            $autorizador_asignado = $r['USR_MAYOR_2M'] ?: ($r['USR_HASTA_2M'] ?: ($r['USR_HASTA_500K'] ?: $r['USR_HASTA_100K']));
        }
    }

    // Filtrado por usuario
    $mostrar = false;
    if ($esDispatcher) {
        // Los administradores ven TODAS las órdenes pendientes para monitoreo
        $mostrar = true;
    } else {
        // Usuario normal ve solo lo asignado
        if ($autorizador_asignado === $usuario_seleccionado) {
            $mostrar = true;
        }
    }

    if ($mostrar) {
        if ($row['fecha']) { $row['fecha'] = $row['fecha']->format('d-m-Y'); }
        $row['observacion'] = $row['observacion'] ?? '';
        $row['asignado'] = ($autorizador_asignado === null) ? 0 : 1;
        $row['autorizador_asignado'] = $autorizador_asignado;
        $resultado_final[] = $row;
    }
}

sqlsrv_free_stmt($stmt_ordenes);
echo json_encode($resultado_final, JSON_UNESCAPED_UNICODE);

sqlsrv_close($conn);
sqlsrv_close($conn_sistemas);
?>
