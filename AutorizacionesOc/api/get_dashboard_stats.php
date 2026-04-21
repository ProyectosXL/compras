<?php
// --- api/get_dashboard_stats.php (REINGENIERÍA PHP-SIDE MAPPING) ---
ini_set('display_errors', 1); 
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

if ($conn === null || $conn_sistemas === null) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión.']);
    exit();
}

$usuario_seleccionado = isset($_GET['usuario']) ? trim($_GET['usuario']) : null;
$esDispatcher = ($usuario_seleccionado === 'RODRIAL' || $usuario_seleccionado === 'RODRIGOAL');

// 1. Obtener Reglas (Sistemas Server)
$sql_reglas = "SELECT COMPRADOR, USR_HASTA_100K, USR_HASTA_500K, USR_HASTA_2M, USR_MAYOR_2M FROM sistemas.dbo.FP_OC_REGLAS_AUTORIZACION";
$stmt_reglas = sqlsrv_query($conn_sistemas, $sql_reglas);
$reglas = [];
if ($stmt_reglas) {
    while ($r = sqlsrv_fetch_array($stmt_reglas, SQLSRV_FETCH_ASSOC)) {
        $reglas[$r['COMPRADOR']] = $r;
    }
    sqlsrv_free_stmt($stmt_reglas);
}

// 2. Obtener TODAS las OCs pendientes (Central Server)
$sql_pendientes = "
    SELECT
        A.N_ORDEN_CO,
        A.TOTAL_CTE,
        C.NOM_COMPRA as comprador
    FROM CPA35 A
    INNER JOIN CPA50 C ON A.ID_CPA50 = C.ID_CPA50
    WHERE A.ESTADO = 1";

$stmt_pendientes = sqlsrv_query($conn, $sql_pendientes);
if ($stmt_pendientes === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error SQL en Pendientes', 'details' => sqlsrv_errors()]);
    exit();
}

$total_count = 0;
$total_monto = 0;

while ($row = sqlsrv_fetch_array($stmt_pendientes, SQLSRV_FETCH_ASSOC)) {
    $comprador = $row['comprador'];
    $monto = (float)$row['TOTAL_CTE'];
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

    $sumar = false;
    if (!$usuario_seleccionado || $esDispatcher) {
        $sumar = true;
    } else {
        if ($autorizador_asignado === $usuario_seleccionado) {
            $sumar = true;
        }
    }

    if ($sumar) {
        $total_count++;
        $total_monto += $monto;
    }
}
sqlsrv_free_stmt($stmt_pendientes);


// --- KPI 3 & 4: HISTORIAL (Autorizadas/Rechazadas hoy) ---
$where_accion = '';
$params_accion = [];
if ($usuario_seleccionado && !$esDispatcher) {
    $where_accion = " AND (AUTORIZO = ? OR USUARIO_DESAUTORIZACION = ?) ";
    $params_accion = [$usuario_seleccionado, $usuario_seleccionado];
}

$sql_autorizadas = "SELECT COUNT(*) AS total_count FROM CPA35 WHERE ESTADO = 2 AND CAST(FEC_AUTORI AS DATE) = CAST(GETDATE() AS DATE)" . $where_accion;
$stmt_autorizadas = sqlsrv_query($conn, $sql_autorizadas, $params_accion);
$autorizadas_data = ($stmt_autorizadas) ? sqlsrv_fetch_array($stmt_autorizadas, SQLSRV_FETCH_ASSOC) : ['total_count' => 0];

$sql_rechazadas = "SELECT COUNT(*) AS total_count FROM CPA35 WHERE ESTADO = 4 AND CAST(FECHA_DESAUTORIZACION AS DATE) = CAST(GETDATE() AS DATE)" . $where_accion;
$stmt_rechazadas = sqlsrv_query($conn, $sql_rechazadas, $params_accion);
$rechazadas_data = ($stmt_rechazadas) ? sqlsrv_fetch_array($stmt_rechazadas, SQLSRV_FETCH_ASSOC) : ['total_count' => 0];

echo json_encode([
    'pendientes_count' => $total_count,
    'pendientes_monto' => $total_monto,
    'autorizadas_hoy'  => $autorizadas_data['total_count'] ?? 0,
    'rechazadas_hoy'   => $rechazadas_data['total_count'] ?? 0,
], JSON_UNESCAPED_UNICODE);

sqlsrv_close($conn);
sqlsrv_close($conn_sistemas);
?>