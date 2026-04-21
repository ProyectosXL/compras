<?php
// --- api/get_dashboard_stats.php (NUEVA LÓGICA AUTOMÁTICA) ---
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

// --- KPI 1 & 2: OCs PENDIENTES (Cálculo Automático) ---
$sql_pendientes = "
    WITH Reglas AS (
        SELECT COMPRADOR, USR_HASTA_100K, USR_HASTA_500K, USR_HASTA_2M, USR_MAYOR_2M
        FROM POWER_BI_CONTROL.dbo.FP_OC_REGLAS_AUTORIZACION
    ),
    Pendientes AS (
        SELECT
            A.N_ORDEN_CO,
            A.TOTAL_CTE,
            CASE 
                WHEN A.TOTAL_CTE <= 100000 THEN R.USR_HASTA_100K
                WHEN A.TOTAL_CTE <= 500000 THEN ISNULL(R.USR_HASTA_500K, R.USR_HASTA_100K)
                WHEN A.TOTAL_CTE <= 2000000 THEN ISNULL(R.USR_HASTA_2M, ISNULL(R.USR_HASTA_500K, R.USR_HASTA_100K))
                ELSE ISNULL(R.USR_MAYOR_2M, ISNULL(R.USR_HASTA_2M, ISNULL(R.USR_HASTA_500K, R.USR_HASTA_100K)))
            END as autorizador_asignado
        FROM CPA35 A
        INNER JOIN CPA50 C ON A.ID_CPA50 = C.ID_CPA50
        LEFT JOIN Reglas R ON C.NOM_COMPRA = R.COMPRADOR
        WHERE A.ESTADO = 1
    )
    SELECT COUNT(N_ORDEN_CO) AS total_count, SUM(TOTAL_CTE) AS total_monto FROM Pendientes";

if ($usuario_seleccionado) {
    $sql_pendientes .= " WHERE autorizador_asignado = ?";
    $stmt_pendientes = sqlsrv_query($conn, $sql_pendientes, [$usuario_seleccionado]);
} else {
    $stmt_pendientes = sqlsrv_query($conn, $sql_pendientes);
}

if ($stmt_pendientes === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error SQL en Pendientes', 'details' => sqlsrv_errors()]);
    exit();
}
$pendientes_data = sqlsrv_fetch_array($stmt_pendientes, SQLSRV_FETCH_ASSOC);


// --- KPI 3 & 4: HISTORIAL (Autorizadas/Rechazadas hoy) ---
$where_accion = '';
$params_accion = [];
if ($usuario_seleccionado) {
    $where_accion = " AND (AUTORIZO = ? OR USUARIO_DESAUTORIZACION = ?) ";
    $params_accion = [$usuario_seleccionado, $usuario_seleccionado];
}

$sql_autorizadas = "SELECT COUNT(*) AS total_count FROM CPA35 WHERE ESTADO = 2 AND CAST(FEC_AUTORI AS DATE) = CAST(GETDATE() AS DATE)" . $where_accion;
$stmt_autorizadas = sqlsrv_query($conn, $sql_autorizadas, $params_accion);
if ($stmt_autorizadas === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error SQL en Autorizadas', 'details' => sqlsrv_errors()]);
    exit();
}
$autorizadas_data = sqlsrv_fetch_array($stmt_autorizadas, SQLSRV_FETCH_ASSOC);

$sql_rechazadas = "SELECT COUNT(*) AS total_count FROM CPA35 WHERE ESTADO = 4 AND CAST(FECHA_DESAUTORIZACION AS DATE) = CAST(GETDATE() AS DATE)" . $where_accion;
$stmt_rechazadas = sqlsrv_query($conn, $sql_rechazadas, $params_accion);
if ($stmt_rechazadas === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error SQL en Rechazadas', 'details' => sqlsrv_errors()]);
    exit();
}
$rechazadas_data = sqlsrv_fetch_array($stmt_rechazadas, SQLSRV_FETCH_ASSOC);

echo json_encode([
    'pendientes_count' => $pendientes_data['total_count'] ?? 0,
    'pendientes_monto' => $pendientes_data['total_monto'] ?? 0,
    'autorizadas_hoy'  => $autorizadas_data['total_count'] ?? 0,
    'rechazadas_hoy'   => $rechazadas_data['total_count'] ?? 0,
], JSON_UNESCAPED_UNICODE);

sqlsrv_close($conn);
sqlsrv_close($conn_sistemas);
?>
se($conn_sistemas);
?>