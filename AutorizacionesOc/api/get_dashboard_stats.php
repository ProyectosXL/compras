<?php
// --- api/get_dashboard_stats.php (VERSIÓN FINAL CON LÓGICA DE EXCLUSIVIDAD CORRECTA) ---
ini_set('display_errors', 1); 
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

$usuario_seleccionado = isset($_GET['usuario']) ? $_GET['usuario'] : null;
$where_pendientes = '';
$params_pendientes = [];

if ($usuario_seleccionado) {
    // Si se proporciona un usuario, aplicamos la lógica de negocio completa
    $where_pendientes = "
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
    )";
    
    // La consulta tiene 5 placeholders '?' que corresponden al usuario_seleccionado
    $params_pendientes = [ 
        $usuario_seleccionado, 
        $usuario_seleccionado, 
        $usuario_seleccionado, 
        $usuario_seleccionado,
        $usuario_seleccionado
    ];

} else {
    // Si NO se proporciona un usuario (vista global en el dashboard inicial), contamos TODAS las pendientes
    // sin aplicar ninguna regla de usuario. Dejamos $where_pendientes vacío.
}


// La consulta principal ahora SIEMPRE necesita el JOIN a CPA01 para usar B.NOM_PROVEE en la cláusula WHERE.
$sql_pendientes = "
    SELECT COUNT(A.N_ORDEN_CO) AS total_count, SUM(A.TOTAL_CTE) AS total_monto 
    FROM CPA35 A
    INNER JOIN CPA01 B ON A.COD_PROVEE = B.COD_PROVEE
    WHERE A.ESTADO = 1" . $where_pendientes;


$stmt_pendientes = sqlsrv_query($conn, $sql_pendientes, $params_pendientes);
if ($stmt_pendientes === false) { 
    http_response_code(500); 
    echo json_encode(['status' => 'error', 'message' => 'Error al contar pendientes.', 'details' => sqlsrv_errors()]); 
    exit(); 
}
$pendientes_data = sqlsrv_fetch_array($stmt_pendientes, SQLSRV_FETCH_ASSOC);


// KPIs de autorizadas y rechazadas hoy. La lógica aquí no cambia.
// Siempre deben estar filtrados por el usuario, si es que se proporciona uno.
$where_accion = '';
$params_accion = [];
if($usuario_seleccionado){
    $where_accion = " AND (AUTORIZO = ? OR USUARIO_DESAUTORIZACION = ?) ";
    $params_accion = [$usuario_seleccionado, $usuario_seleccionado];
}
$sql_autorizadas = "SELECT COUNT(*) AS total_count FROM CPA35 WHERE ESTADO = 2 AND CAST(FEC_AUTORI AS DATE) = CAST(GETDATE() AS DATE)" . $where_accion;
$stmt_autorizadas = sqlsrv_query($conn, $sql_autorizadas, $params_accion);
$autorizadas_data = sqlsrv_fetch_array($stmt_autorizadas, SQLSRV_FETCH_ASSOC);

$sql_rechazadas = "SELECT COUNT(*) AS total_count FROM CPA35 WHERE ESTADO = 4 AND CAST(FECHA_DESAUTORIZACION AS DATE) = CAST(GETDATE() AS DATE)" . $where_accion;
$stmt_rechazadas = sqlsrv_query($conn, $sql_rechazadas, $params_accion);
$rechazadas_data = sqlsrv_fetch_array($stmt_rechazadas, SQLSRV_FETCH_ASSOC);


// Ensamblar el resultado final
$dashboard_stats = [
    'pendientes_count' => $pendientes_data['total_count'] ?? 0,
    'pendientes_monto' => $pendientes_data['total_monto'] ?? 0,
    'autorizadas_hoy' => $autorizadas_data['total_count'] ?? 0,
    'rechazadas_hoy' => $rechazadas_data['total_count'] ?? 0
];

echo json_encode($dashboard_stats, JSON_UNESCAPED_UNICODE);


// Liberar recursos
if (isset($stmt_pendientes) && $stmt_pendientes) sqlsrv_free_stmt($stmt_pendientes);
if (isset($stmt_autorizadas) && $stmt_autorizadas) sqlsrv_free_stmt($stmt_autorizadas);
if (isset($stmt_rechazadas) && $stmt_rechazadas) sqlsrv_free_stmt($stmt_rechazadas);
sqlsrv_close($conn);

?>