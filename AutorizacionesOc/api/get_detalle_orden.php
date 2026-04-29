<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

if ($conn === null) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión.']);
    exit();
}

$n_orden_co = isset($_GET['n_orden_co']) ? trim($_GET['n_orden_co']) : null;

if (!$n_orden_co) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Falta el número de orden.']);
    exit();
}

$sql = "
    SELECT 
        COD_ARTICU,
        DESCRIPCION_ARTICULO,
        DESC_ADICIONAL_ARTICULO,
        CAST(CAN_PEDIDA AS FLOAT) as CAN_PEDIDA
    FROM CPA36
    WHERE LTRIM(N_ORDEN_CO) = ?
    ORDER BY N_RENGL_OC ASC
";

$params = [$n_orden_co];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al consultar detalle.', 'details' => sqlsrv_errors()]);
    exit();
}

$detalle = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $detalle[] = $row;
}

echo json_encode($detalle, JSON_UNESCAPED_UNICODE);
sqlsrv_close($conn);
?>
