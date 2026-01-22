<?php
// --- api/get_ordenes_pendientes_por_usuario.php (VERSIÓN CORREGIDA) ---
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

$where_clause = "";

if ($usuario_seleccionado === 'DANM') {
    // Lógica DANM: NO debe ver proveedores que ya tienen dueño asignado.
    $sql_asignados = "SELECT COD_PROVEE FROM sistemas.dbo.FP_DERIVACION_OC";
    $stmt_asignados = sqlsrv_query($conn_sistemas, $sql_asignados);
    $proveedores_asignados = [];
    if ($stmt_asignados) {
        while ($row = sqlsrv_fetch_array($stmt_asignados, SQLSRV_FETCH_ASSOC)) { 
            $escaped_provee = str_replace("'", "''", $row['COD_PROVEE']);
            $proveedores_asignados[] = "'" . $escaped_provee . "'"; 
        }
        sqlsrv_free_stmt($stmt_asignados);
    }
    
    if (!empty($proveedores_asignados)) { 
        $where_clause .= " AND A.COD_PROVEE NOT IN (" . implode(',', $proveedores_asignados) . ") "; 
    }
    
    // <<< LÍNEA ELIMINADA DE AQUÍ: $where_clause .= " AND A.TOTAL_CTE >= 1000000 ";

} else {
    // Lógica Usuarios Normales: Ven solo lo que tienen asignado.
    $sql_asignados = "SELECT COD_PROVEE FROM sistemas.dbo.FP_DERIVACION_OC WHERE USUARIO_AUTORIZADOR = ?";
    $stmt_asignados = sqlsrv_query($conn_sistemas, $sql_asignados, [$usuario_seleccionado]);
    $proveedores_asignados = [];
    if ($stmt_asignados) {
        while ($row = sqlsrv_fetch_array($stmt_asignados, SQLSRV_FETCH_ASSOC)) {
            $escaped_provee = str_replace("'", "''", $row['COD_PROVEE']);
            $proveedores_asignados[] = "'" . $escaped_provee . "'";
        }
        sqlsrv_free_stmt($stmt_asignados);
    }
    
    if (!empty($proveedores_asignados)) { 
        $where_clause = " AND A.COD_PROVEE IN (" . implode(',', $proveedores_asignados) . ") "; 
    } else { 
        // Si no tiene proveedores asignados, no debe ver ninguna orden.
        $where_clause = " AND 1 = 0 "; 
    }
}
sqlsrv_close($conn_sistemas);

// Se construye la consulta SQL final
$sql_ordenes = "
    SELECT
        A.N_ORDEN_CO AS numero,
        CAST(A.FECHA_INGRESO AS DATE) AS fecha,
        B.NOM_PROVEE AS proveedor,
        C.NOM_COMPRA AS comprador,
        A.COD_PROVEE AS cod_provee,
        A.OBSERVACIO AS observacion,
        CAST(A.TOTAL_CTE AS FLOAT) AS monto
    FROM CPA35 A
    INNER JOIN CPA01 B ON A.COD_PROVEE = B.COD_PROVEE
    INNER JOIN CPA50 C ON A.ID_CPA50 = C.ID_CPA50
    WHERE A.ESTADO = 1 
      AND A.TOTAL_CTE >= 1000000 -- <<< LÍNEA AÑADIDA AQUÍ: El filtro de monto se aplica para TODOS.
      " . $where_clause . " -- Se concatena la lógica de proveedores específica del usuario
    ORDER BY A.FECHA_INGRESO ASC;";

$stmt_ordenes = sqlsrv_query($conn, $sql_ordenes);
if ($stmt_ordenes === false) { 
    http_response_code(500); 
    echo json_encode(['error' => 'Error de BD al consultar órdenes.', 'details' => sqlsrv_errors()]); 
    exit(); 
}

$ordenes = [];
while ($row = sqlsrv_fetch_array($stmt_ordenes, SQLSRV_FETCH_ASSOC)) {
    if ($row['fecha']) {
        $row['fecha'] = $row['fecha']->format('d-m-Y');
    }
    $row['observacion'] = $row['observacion'] ?? '';
    $ordenes[] = $row;
}
sqlsrv_free_stmt($stmt_ordenes);

echo json_encode($ordenes, JSON_UNESCAPED_UNICODE);
sqlsrv_close($conn);
?>