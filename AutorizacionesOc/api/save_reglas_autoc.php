<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

if ($conn_sistemas === null) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión.']);
    exit();
}

$comprador = isset($_POST['comprador']) ? $_POST['comprador'] : null;
$campo = isset($_POST['campo']) ? $_POST['campo'] : null;
$valor = isset($_POST['valor']) ? $_POST['valor'] : null;
$usuario_modifica = isset($_POST['usuario_modifica']) ? $_POST['usuario_modifica'] : 'SISTEMA';

if (!$comprador || !$campo) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos.']);
    exit();
}

// Validar que el campo sea uno de los permitidos
$campos_permitidos = ['USR_HASTA_100K', 'USR_HASTA_500K', 'USR_HASTA_2M', 'USR_MAYOR_2M'];
if (!in_array($campo, $campos_permitidos)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Campo no válido.']);
    exit();
}

$sql = "UPDATE FP_OC_REGLAS_AUTORIZACION 
        SET $campo = ?, MODIFICADO_POR = ?, ULTIMA_MODIFICACION = GETDATE()
        WHERE COMPRADOR = ?";

$params = [$valor, $usuario_modifica, $comprador];
$stmt = sqlsrv_query($conn_sistemas, $sql, $params);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al guardar.', 'details' => sqlsrv_errors()]);
} else {
    echo json_encode(['status' => 'success', 'message' => 'Regla actualizada correctamente.']);
}

sqlsrv_close($conn_sistemas);
?>
