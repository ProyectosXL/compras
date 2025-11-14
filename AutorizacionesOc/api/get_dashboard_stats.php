<?php
// --- api/get_dashboard_stats.php ---
ini_set('display_errors', 1); error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

// --- KPI 1 & 2: OCs pendientes y su monto total ---
$sql_pendientes = "SELECT COUNT(*) AS total_count, SUM(TOTAL_CTE) AS total_monto FROM CPA35 WHERE ESTADO = 1";
$stmt_pendientes = sqlsrv_query($conn, $sql_pendientes);
$pendientes_data = sqlsrv_fetch_array($stmt_pendientes, SQLSRV_FETCH_ASSOC);

// --- KPI 3: OCs autorizadas hoy ---
// Usamos GETDATE() para la fecha del servidor, que es más seguro
$sql_autorizadas = "SELECT COUNT(*) AS total_count FROM CPA35 WHERE ESTADO = 2 AND CAST(FEC_AUTORI AS DATE) = CAST(GETDATE() AS DATE)";
$stmt_autorizadas = sqlsrv_query($conn, $sql_autorizadas);
$autorizadas_data = sqlsrv_fetch_array($stmt_autorizadas, SQLSRV_FETCH_ASSOC);

// --- KPI 4: OCs rechazadas (desautorizadas) hoy ---
$sql_rechazadas = "SELECT COUNT(*) AS total_count FROM CPA35 WHERE ESTADO = 4 AND CAST(FECHA_DESAUTORIZACION AS DATE) = CAST(GETDATE() AS DATE)";
$stmt_rechazadas = sqlsrv_query($conn, $sql_rechazadas);
$rechazadas_data = sqlsrv_fetch_array($stmt_rechazadas, SQLSRV_FETCH_ASSOC);


// --- Ensamblar el resultado final ---
$dashboard_stats = [
    'pendientes_count' => $pendientes_data['total_count'] ?? 0,
    'pendientes_monto' => $pendientes_data['total_monto'] ?? 0,
    'autorizadas_hoy'  => $autorizadas_data['total_count'] ?? 0,
    'rechazadas_hoy'   => $rechazadas_data['total_count'] ?? 0,
];

echo json_encode($dashboard_stats, JSON_UNESCAPED_UNICODE);

// Liberar recursos
sqlsrv_free_stmt($stmt_pendientes);
sqlsrv_free_stmt($stmt_autorizadas);
sqlsrv_free_stmt($stmt_rechazadas);
sqlsrv_close($conn);

?>