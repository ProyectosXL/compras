<?php
// --- api/rechazar_orden.php ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit();
}

$n_orden_co = isset($_POST['n_orden_co']) ? $_POST['n_orden_co'] : null;
$usuario_rechaza = isset($_POST['usuario_rechaza']) ? $_POST['usuario_rechaza'] : null;
$motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : '';

if (empty($n_orden_co) || empty($usuario_rechaza) || empty($motivo)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Parámetros requeridos: n_orden_co, usuario_rechaza y motivo.'], JSON_UNESCAPED_UNICODE);
    exit();
}

$estado_rechazado = 4; // 4 = Desautorizada
$motivo_completo = "RECHAZADO APP: " . $motivo;

$sql = "UPDATE CPA35
    SET
        ESTADO = ?,
        ID_ESTADO_ORDEN_COMPRA = ?,
        OBSERVACIONES = ?,
        FECHA_DESAUTORIZACION = GETDATE(),
        USUARIO_DESAUTORIZACION = ?,
        TERMINAL_DESAUTORIZACION = 'APP_MOVIL',
        USUA_ULTIMA_MODIFICACION = ?,
        HORA_ULTIMA_MODIFICACION = FORMAT(GETDATE(), 'HHmmss'),
        TERM_ULTIMA_MODIFICACION = 'APP_MOVIL'
    WHERE
        N_ORDEN_CO = ? AND ESTADO = 1";

$params = [
    $estado_rechazado,
    $estado_rechazado,
    $motivo_completo,
    $usuario_rechaza,
    $usuario_rechaza,
    $n_orden_co
];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Falló el UPDATE para rechazar la OC.', 'details' => sqlsrv_errors()], JSON_UNESCAPED_UNICODE);
    exit();
}

if (sqlsrv_rows_affected($stmt) > 0) {
    echo json_encode(['status' => 'success', 'message' => '¡OC ' . $n_orden_co . ' rechazada correctamente!'], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['status' => 'info', 'message' => 'La OC no pudo ser rechazada (posiblemente ya fue procesada).'], JSON_UNESCAPED_UNICODE);
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>