<?php
// --- api/get_ordenes_pendientes_por_usuario.php (CON LÓGICA HÍBRIDA MEJORADA) ---
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

// ---- INICIO DE LA NUEVA LÓGICA HÍBRIDA ----
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
    WHERE
        A.ESTADO = 1 -- Condición 1: La orden debe estar pendiente.
        AND (
            -- Criterio A: El proveedor de la OC está en la lista histórica de este usuario.
            A.COD_PROVEE IN (
                SELECT DISTINCT T.COD_PROVEE
                FROM CPA35 T
                WHERE T.AUTORIZO = ?
            )
            OR
            -- Criterio B: O el proveedor de la OC NUNCA ha sido autorizado por NADIE.
            -- Esto hace que los proveedores nuevos aparezcan para todos.
            A.COD_PROVEE NOT IN (
                SELECT DISTINCT T.COD_PROVEE
                FROM CPA35 T
                WHERE T.AUTORIZO IS NOT NULL AND T.AUTORIZO <> ''
            )
        )
    ORDER BY
        A.FECHA_INGRESO ASC; -- Mantenemos el orden cronológico.
";
// ---- FIN DE LA NUEVA LÓGICA HÍBRIDA ----

// El parámetro de usuario se necesita solo para el primer criterio (Criterio A)
$params = [$usuario_seleccionado]; 
$stmt_ordenes = sqlsrv_query($conn, $sql_ordenes, $params);

if ($stmt_ordenes === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de BD al buscar órdenes pendientes.', 'details' => sqlsrv_errors()]);
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