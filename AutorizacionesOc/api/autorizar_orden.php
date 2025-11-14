<?php
// --- api/autorizar_orden.php ---
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
$usuario_autoriza = isset($_POST['usuario_autoriza']) ? $_POST['usuario_autoriza'] : null;

if (empty($n_orden_co) || empty($usuario_autoriza)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros requeridos.'], JSON_UNESCAPED_UNICODE);
    exit();
}

$sql = "UPDATE CPA35
    SET
        ESTADO = 2,
        ID_ESTADO_ORDEN_COMPRA = 2,
        AUTORIZO = ?,
        FEC_AUTORI = GETDATE(),
        HORA_AUTOR = FORMAT(GETDATE(), 'HHmm'),
        TERM_AUTORIZACION = 'APP_MOVIL',
        USUA_ULTIMA_MODIFICACION = ?,
        HORA_ULTIMA_MODIFICACION = FORMAT(GETDATE(), 'HHmmss'),
        TERM_ULTIMA_MODIFICACION = 'APP_MOVIL'
    WHERE
        N_ORDEN_CO = ? AND ESTADO = 1";

$params = [$usuario_autoriza, $usuario_autoriza, $n_orden_co];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Falló la ejecución del UPDATE.', 'details' => sqlsrv_errors()], JSON_UNESCAPED_UNICODE);
    exit();
}

if (sqlsrv_rows_affected($stmt) > 0) {
    echo json_encode(['status' => 'success', 'message' => '¡OC ' . $n_orden_co . ' autorizada con éxito!'], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['status' => 'info', 'message' => 'La OC no requiere autorización o ya fue procesada.'], JSON_UNESCAPED_UNICODE);
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>