<?php
// --- api/get_ordenes_pendientes_por_usuario.php (NUEVA LÓGICA AUTOMÁTICA) ---
ini_set('display_errors', 1); 
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

if ($conn === null || $conn_sistemas === null) { 
    http_response_code(500); 
    echo json_encode(['error' => 'Error de conexión.']); 
    exit(); 
}

$usuario_seleccionado = isset($_GET['autorizador']) ? trim($_GET['autorizador']) : '';

if (empty($usuario_seleccionado)) { 
    http_response_code(400); 
    echo json_encode(['error' => 'Falta parámetro de autorizador.']); 
    exit(); 
}

// SQL principal que calcula el autorizador basado en la tabla de reglas
$sql_ordenes = "
    WITH Reglas AS (
        SELECT 
            COMPRADOR,
            USR_HASTA_100K,
            USR_HASTA_500K,
            USR_HASTA_2M,
            USR_MAYOR_2M
        FROM POWER_BI_CONTROL.dbo.FP_OC_REGLAS_AUTORIZACION
    ),
    OrdenesCalculadas AS (
        SELECT
            A.N_ORDEN_CO AS numero,
            CAST(A.FECHA_INGRESO AS DATE) AS fecha,
            B.NOM_PROVEE AS proveedor,
            C.NOM_COMPRA AS comprador,
            A.COD_PROVEE AS cod_provee,
            A.OBSERVACIO AS observacion,
            CAST(A.TOTAL_CTE AS FLOAT) AS monto,
            CASE 
                WHEN A.TOTAL_CTE <= 100000 THEN R.USR_HASTA_100K
                WHEN A.TOTAL_CTE <= 500000 THEN ISNULL(R.USR_HASTA_500K, R.USR_HASTA_100K)
                WHEN A.TOTAL_CTE <= 2000000 THEN ISNULL(R.USR_HASTA_2M, ISNULL(R.USR_HASTA_500K, R.USR_HASTA_100K))
                ELSE ISNULL(R.USR_MAYOR_2M, ISNULL(R.USR_HASTA_2M, ISNULL(R.USR_HASTA_500K, R.USR_HASTA_100K)))
            END as autorizador_asignado
        FROM CPA35 A
        INNER JOIN CPA01 B ON A.COD_PROVEE = B.COD_PROVEE
        INNER JOIN CPA50 C ON A.ID_CPA50 = C.ID_CPA50
        LEFT JOIN Reglas R ON C.NOM_COMPRA = R.COMPRADOR
        WHERE A.ESTADO = 1
    )
    SELECT * 
    FROM OrdenesCalculadas 
    WHERE autorizador_asignado = ?
    ORDER BY fecha ASC;";

$stmt_ordenes = sqlsrv_query($conn, $sql_ordenes, [$usuario_seleccionado]);

if ($stmt_ordenes === false) { 
    http_response_code(500); 
    echo json_encode(['error' => 'Error de BD al consultar órdenes.', 'details' => sqlsrv_errors()]); 
    exit(); 
}

$ordenes = [];
while ($row = sqlsrv_fetch_array($stmt_ordenes, SQLSRV_FETCH_ASSOC)) {
    if ($row['fecha']) { $row['fecha'] = $row['fecha']->format('d-m-Y'); }
    $row['observacion'] = $row['observacion'] ?? '';
    $row['asignado'] = 1; // En este esquema, todas las mostradas están asignadas al usuario que consulta
    $ordenes[] = $row;
}

sqlsrv_free_stmt($stmt_ordenes);
echo json_encode($ordenes, JSON_UNESCAPED_UNICODE);

sqlsrv_close($conn);
sqlsrv_close($conn_sistemas);
?>
