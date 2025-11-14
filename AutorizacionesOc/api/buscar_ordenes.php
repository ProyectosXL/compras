<?php
// --- api/buscar_ordenes.php (VERSIÓN MEJORADA Y UNIFICADA) ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

// --- Base de la consulta ---
$sql = "SELECT
            A.N_ORDEN_CO AS numero, CAST(A.FECHA_INGRESO AS DATE) AS fecha, B.NOM_PROVEE AS proveedor,
            C.NOM_COMPRA AS comprador, D.DESC_ESTADO_ORDEN_COMPRA AS estado_desc,
            A.OBSERVACIO AS observacion, CAST(A.TOTAL_CTE AS FLOAT) AS monto
        FROM CPA35 A
        INNER JOIN CPA01 B ON A.COD_PROVEE = B.COD_PROVEE
        INNER JOIN CPA50 C ON A.ID_CPA50 = C.ID_CPA50
        LEFT JOIN ESTADO_ORDEN_COMPRA D ON A.ID_ESTADO_ORDEN_COMPRA = D.ID_ESTADO_ORDEN_COMPRA
        ";

// --- Construcción dinámica del WHERE ---
$whereClauses = [];
$params = [];

// Filtro por usuario INVOLUCRADO (comprador, autorizador, etc.)
if (!empty($_GET['usuario_involucrado'])) {
    $whereClauses[] = "(
        C.NOM_COMPRA = ? 
        OR A.AUTORIZO = ? 
        OR A.USUARIO_DESAUTORIZACION = ? 
        OR A.USUA_ULTIMA_MODIFICACION = ?
    )";
    // Añadimos el parámetro 4 veces
    $params[] = $_GET['usuario_involucrado'];
    $params[] = $_GET['usuario_involucrado'];
    $params[] = $_GET['usuario_involucrado'];
    $params[] = $_GET['usuario_involucrado'];
}

// Filtro por estado
if (!empty($_GET['estado'])) {
    $whereClauses[] = "A.ESTADO = ?";
    $params[] = $_GET['estado'];
}

// Filtro por fecha desde
if (!empty($_GET['fecha_desde'])) {
    $whereClauses[] = "A.FECHA_INGRESO >= ?";
    $params[] = str_replace('-', '', $_GET['fecha_desde']);
}

// Filtro por fecha hasta
if (!empty($_GET['fecha_hasta'])) {
    $whereClauses[] = "A.FECHA_INGRESO <= ?";
    $params[] = str_replace('-', '', $_GET['fecha_hasta']);
}


if (count($whereClauses) > 0) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

$sql .= " ORDER BY A.FECHA_INGRESO DESC, A.N_ORDEN_CO DESC;";

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al buscar las órdenes.', 'details' => sqlsrv_errors()], JSON_UNESCAPED_UNICODE);
    exit();
}

$resultados = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($row['fecha']) { $row['fecha'] = $row['fecha']->format('d-m-Y'); } 
    else { $row['fecha'] = 'N/A'; }
    $row['observacion'] = $row['observacion'] ?? '';
    $resultados[] = $row;
}

echo json_encode($resultados, JSON_UNESCAPED_UNICODE);

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>