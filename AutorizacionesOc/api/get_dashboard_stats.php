<?php
// --- api/get_dashboard_stats.php (CON LÓGICA HÍBRIDA MEJORADA) ---
ini_set('display_errors', 1); error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

$usuario_seleccionado = isset($_GET['usuario']) ? $_GET['usuario'] : null;
$where_pendientes = '';
$params_pendientes = [];
$where_accion = '';
$params_accion = [];

if ($usuario_seleccionado) {
    // La nueva cláusula WHERE para las pendientes se construye con la lógica híbrida.
    $where_pendientes = "
        AND (
            A.COD_PROVEE IN (
                SELECT DISTINCT T.COD_PROVEE FROM CPA35 T WHERE T.AUTORIZO = ?
            )
            OR
            A.COD_PROVEE NOT IN (
                SELECT DISTINCT T.COD_PROVEE FROM CPA35 T WHERE T.AUTORIZO IS NOT NULL AND T.AUTORIZO <> ''
            )
        )
    ";
    $params_pendientes = [$usuario_seleccionado];
    
    // El filtro de acciones se mantiene igual
    $where_accion = " AND (AUTORIZO = ? OR USUARIO_DESAUTORIZACION = ?) ";
    $params_accion = [$usuario_seleccionado, $usuario_seleccionado];
}

// --- KPI 1 & 2: OCs pendientes ---
$sql_pendientes = "SELECT COUNT(A.N_ORDEN_CO) AS total_count, SUM(A.TOTAL_CTE) AS total_monto FROM CPA35 A WHERE A.ESTADO = 1" . $where_pendientes;
$stmt_pendientes = sqlsrv_query($conn, $sql_pendientes, $params_pendientes);
if ($stmt_pendientes === false) { http_response_code(500); echo json_encode(['status' => 'error', 'message' => 'Error al contar pendientes.', 'details' => sqlsrv_errors()]); exit(); }
$pendientes_data = sqlsrv_fetch_array($stmt_pendientes, SQLSRV_FETCH_ASSOC);

// --- KPI 3 & 4: Autorizadas y Rechazadas Hoy (sin cambios) ---
$sql_autorizadas = "SELECT COUNT(*) AS total_count FROM CPA35 WHERE ESTADO = 2 AND CAST(FEC_AUTORI AS DATE) = CAST(GETDATE() AS DATE)" . $where_accion;
$stmt_autorizadas = sqlsrv_query($conn, $sql_autorizadas, $params_accion);
$autorizadas_data = sqlsrv_fetch_array($stmt_autorizadas, SQLSRV_FETCH_ASSOC);

$sql_rechazadas = "SELECT COUNT(*) AS total_count FROM CPA35 WHERE ESTADO = 4 AND CAST(FECHA_DESAUTORIZACION AS DATE) = CAST(GETDATE() AS DATE)" . $where_accion;
$stmt_rechazadas = sqlsrv_query($conn, $sql_rechazadas, $params_accion);
$rechazadas_data = sqlsrv_fetch_array($stmt_rechazadas, SQLSRV_FETCH_ASSOC);

// --- Ensamblar el resultado ---
$dashboard_stats = [
    'pendientes_count' => $pendientes_data['total_count'] ?? 0,
    'pendientes_monto' => $pendientes_data['total_monto'] ?? 0,
    'autorizadas_hoy'  => $autorizadas_data['total_count'] ?? 0,
    'rechazadas_hoy'   => $rechazadas_data['total_count'] ?? 0,
];

echo json_encode($dashboard_stats, JSON_UNESCAPED_UNICODE);

// Liberar recursos
if (isset($stmt_pendientes) && $stmt_pendientes) sqlsrv_free_stmt($stmt_pendientes);
if (isset($stmt_autorizadas) && $stmt_autorizadas) sqlsrv_free_stmt($stmt_autorizadas);
if (isset($stmt_rechazadas) && $stmt_rechazadas) sqlsrv_free_stmt($stmt_rechazadas);
sqlsrv_close($conn);
?>