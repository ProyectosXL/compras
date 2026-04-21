<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

if ($conn_sistemas === null) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión a sistemas.']);
    exit();
}

$sql = "SELECT COMPRADOR, USR_HASTA_100K, USR_HASTA_500K, USR_HASTA_2M, USR_MAYOR_2M 
        FROM FP_OC_REGLAS_AUTORIZACION 
        ORDER BY COMPRADOR";

$stmt = sqlsrv_query($conn_sistemas, $sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al consultar reglas.', 'details' => sqlsrv_errors()]);
    exit();
}

$reglas = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $reglas[] = $row;
}

echo json_encode($reglas, JSON_UNESCAPED_UNICODE);
sqlsrv_close($conn_sistemas);
?>
