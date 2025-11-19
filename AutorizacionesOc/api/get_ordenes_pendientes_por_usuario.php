<?php
// --- api/get_ordenes_pendientes_por_usuario.php (CON LÓGICA DE EXCLUSIVIDAD CORRECTA) ---
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

// ---- INICIO DE LA LÓGICA DE EXCLUSIVIDAD ----
$sql_ordenes = "
    SELECT
        A.N_ORDEN_CO AS numero, CAST(A.FECHA_INGRESO AS DATE) AS fecha, B.NOM_PROVEE AS proveedor,
        A.OBSERVACIO AS observacion, CAST(A.TOTAL_CTE AS FLOAT) AS monto
    FROM
        CPA35 A
    INNER JOIN
        CPA01 B ON A.COD_PROVEE = B.COD_PROVEE
    WHERE
        A.ESTADO = 1 -- Condición 1: La orden debe estar pendiente.
    AND (
        -- CRITERIO 1: REGLAS ESPECIALES. La OC es para un proveedor especial Y el usuario es el correcto.
        ( (? = 'JUANM'   AND B.NOM_PROVEE = 'DHL EXPRESS (ARGENTINA) S.A.') OR
          (? = 'LUCAST'  AND B.NOM_PROVEE = 'SUPERA3 S.A.') OR
          (? = 'VALERIA' AND B.NOM_PROVEE = 'COMISSO MARIO WALTER') )

        OR

        -- CRITERIO 2: REGLAS GENERALES. Se aplican SÓLO si la OC no es de un proveedor especial.
        (
            -- Parte A: ASEGURAMOS que el proveedor no sea uno de los especiales.
            B.NOM_PROVEE NOT IN ('DHL EXPRESS (ARGENTINA) S.A.', 'SUPERA3 S.A.', 'COMISSO MARIO WALTER')
            
            AND -- Y
            
            -- Parte B: Aplicamos la lógica de historial o proveedor nuevo.
            (
                A.COD_PROVEE IN (SELECT DISTINCT T.COD_PROVEE FROM CPA35 T WHERE T.AUTORIZO = ?)
                OR
                A.COD_PROVEE NOT IN (SELECT DISTINCT T.COD_PROVEE FROM CPA35 T WHERE T.AUTORIZO IS NOT NULL AND T.AUTORIZO <> '')
            )
        )
    )
    ORDER BY
        A.FECHA_INGRESO ASC;
";
// ---- FIN DE LA LÓGICA DE EXCLUSIVIDAD ----

// La consulta ahora tiene 5 placeholders '?'
$params = [ $usuario_seleccionado, $usuario_seleccionado, $usuario_seleccionado, $usuario_seleccionado, $usuario_seleccionado ];
$stmt_ordenes = sqlsrv_query($conn, $sql_ordenes, $params);

if ($stmt_ordenes === false) { http_response_code(500); echo json_encode(['error' => 'Error de BD al buscar órdenes pendientes.', 'details' => sqlsrv_errors()]); exit(); }

$ordenes = [];
while ($row = sqlsrv_fetch_array($stmt_ordenes, SQLSRV_FETCH_ASSOC)) {
    if ($row['fecha']) { $row['fecha'] = $row['fecha']->format('d-m-Y'); } else { $row['fecha'] = 'N/A'; }
    $row['observacion'] = $row['observacion'] ?? '';
    $ordenes[] = $row;
}
sqlsrv_free_stmt($stmt_ordenes);
echo json_encode($ordenes, JSON_UNESCAPED_UNICODE);
sqlsrv_close($conn);
?>