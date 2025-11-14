<?php
// --- api/get_ordenes_pendientes_por_usuario.php ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

$usuario_seleccionado = isset($_GET['autorizador']) ? $_GET['autorizador'] : '';
if (empty($usuario_seleccionado)) {
    http_response_code(400);
    echo json_encode(['error' => 'Error: Falta el parámetro "autorizador".']);
    exit();
}

// --- Paso 1: Obtener los IDs de los perfiles del usuario seleccionado ---
$sql_perfiles_usuario = "
    SELECT ID_CPA_PERFIL_AUTORIZACION_OC
    FROM CPA_PERFIL_AUTORIZACION_OC_USUARIO
    WHERE USUARIO = ?
";
$stmt_perfiles = sqlsrv_query($conn, $sql_perfiles_usuario, [$usuario_seleccionado]);
if ($stmt_perfiles === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de BD al buscar perfiles de usuario.']);
    exit();
}

$perfiles_ids = [];
while ($row = sqlsrv_fetch_array($stmt_perfiles, SQLSRV_FETCH_ASSOC)) {
    $perfiles_ids[] = $row['ID_CPA_PERFIL_AUTORIZACION_OC'];
}
sqlsrv_free_stmt($stmt_perfiles);

// Si el usuario no tiene perfiles asignados, no puede tener OCs pendientes. Devolvemos un array vacío.
if (empty($perfiles_ids)) {
    echo json_encode([]);
    exit();
}
$placeholders = implode(',', array_fill(0, count($perfiles_ids), '?'));

// --- Paso 2: Buscar OCs pendientes cuyo monto caiga en el rango de alguno de los perfiles del usuario ---
$sql_ordenes = "
    SELECT
        A.N_ORDEN_CO AS numero,
        CAST(A.FECHA_INGRESO AS DATE) AS fecha,
        B.NOM_PROVEE AS proveedor,
        A.OBSERVACIO AS observacion,
        CAST(A.TOTAL_CTE AS FLOAT) AS monto
    FROM
        CPA35 A
    INNER JOIN
        CPA01 B ON A.COD_PROVEE = B.COD_PROVEE
    INNER JOIN
        CPA_PERFIL_AUTORIZACION_OC P
        ON A.TOTAL_CTE BETWEEN P.IMPORTE_MINIMO_AUTORIZAR AND P.IMPORTE_MAXIMO_AUTORIZAR
    WHERE
        A.ESTADO = 1 -- Solo OCs con estado 'Ingresada'
        AND P.ID_CPA_PERFIL_AUTORIZACION_OC IN ($placeholders) -- Y que el perfil que corresponde a su monto sea uno de los perfiles del usuario
";

$stmt_ordenes = sqlsrv_query($conn, $sql_ordenes, $perfiles_ids);
if ($stmt_ordenes === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de BD al buscar órdenes pendientes.']);
    exit();
}

$ordenes = [];
while ($row = sqlsrv_fetch_array($stmt_ordenes, SQLSRV_FETCH_ASSOC)) {
    if ($row['fecha']) {
        $row['fecha'] = $row['fecha']->format('d-m-Y');
    } else {
        $row['fecha'] = 'N/A';
    }
    $row['observacion'] = $row['observacion'] ?? '';
    $ordenes[] = $row;
}
sqlsrv_free_stmt($stmt_ordenes);

echo json_encode($ordenes, JSON_UNESCAPED_UNICODE);
sqlsrv_close($conn);
?>