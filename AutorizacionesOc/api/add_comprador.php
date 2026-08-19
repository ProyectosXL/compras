<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

if ($conn_sistemas === null) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión.']);
    exit();
}

$comprador = isset($_POST['comprador']) ? trim($_POST['comprador']) : null;
$usuario_modifica = isset($_POST['usuario_modifica']) ? trim($_POST['usuario_modifica']) : 'SISTEMA';

if (!$comprador) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'El nombre del comprador es requerido.']);
    exit();
}

// Verificar si ya existe
$sql_check = "SELECT COUNT(*) AS cant FROM sistemas.dbo.FP_OC_REGLAS_AUTORIZACION WHERE COMPRADOR = ?";
$stmt_check = sqlsrv_query($conn_sistemas, $sql_check, [$comprador]);
if ($stmt_check === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al validar comprador.', 'details' => sqlsrv_errors()]);
    exit();
}

$row = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
if ($row['cant'] > 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'El comprador ya existe.']);
    exit();
}

$sql_insert = "INSERT INTO sistemas.dbo.FP_OC_REGLAS_AUTORIZACION 
               (COMPRADOR, USR_HASTA_100K, USR_HASTA_500K, USR_HASTA_2M, USR_MAYOR_2M, ULTIMA_MODIFICACION, MODIFICADO_POR) 
               VALUES (?, '', '', '', '', GETDATE(), ?)";
$stmt_insert = sqlsrv_query($conn_sistemas, $sql_insert, [$comprador, $usuario_modifica]);

if ($stmt_insert === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al insertar comprador.', 'details' => sqlsrv_errors()]);
} else {
    echo json_encode(['status' => 'success', 'message' => 'Comprador creado exitosamente.']);
}

sqlsrv_close($conn_sistemas);
?>
