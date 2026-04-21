<?php
// --- api/get_dashboard_stats.php (VERSIÓN CORREGIDA 500 ERROR) ---
ini_set('display_errors', 1); 
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

// 1. Validamos conexión
if ($conn === null || $conn_sistemas === null) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a una o más bases de datos.']);
    exit();
}

$usuario_seleccionado = isset($_GET['usuario']) ? trim($_GET['usuario']) : null;
$where_pendientes = '';

// 2. Lógica de filtros (DANM vs Usuario Normal)
if ($usuario_seleccionado) {
    if ($usuario_seleccionado === 'RODRIAL') {
        // --- LÓGICA RODRIAL (DERIVADOR) ---
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
        
        $where_pendientes = ""; 
        
        if (!empty($proveedores_asignados)) {
            $lista_proveedores_str = implode(',', $proveedores_asignados);
            $where_pendientes .= " AND A.COD_PROVEE NOT IN ({$lista_proveedores_str}) ";
        }
        
        // Se sacó la condición de monto (TOTAL_CTE >= 1.000.000) por pedido usuario.
        // $where_pendientes .= " AND A.TOTAL_CTE >= 1000000 ";

    } else {
        // --- LÓGICA USUARIO NORMAL ---
        $sql_asignados = "SELECT COD_PROVEE FROM sistemas.dbo.FP_DERIVACION_OC WHERE USUARIO_AUTORIZADOR = ?";
        // Pasamos el parámetro como array
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
            $lista_proveedores_str = implode(',', $proveedores_asignados);
            $where_pendientes = " AND A.COD_PROVEE IN ({$lista_proveedores_str}) ";
        } else {
            $where_pendientes = " AND 1 = 0 "; 
        }
    }
}

// --- KPI 1 & 2: OCs PENDIENTES ---
$sql_pendientes = "SELECT COUNT(A.N_ORDEN_CO) AS total_count, SUM(A.TOTAL_CTE) AS total_monto FROM CPA35 A WHERE A.ESTADO = 1" . $where_pendientes;
$stmt_pendientes = sqlsrv_query($conn, $sql_pendientes); 

if ($stmt_pendientes === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error SQL en Pendientes', 'details' => sqlsrv_errors()]);
    exit();
}
$pendientes_data = sqlsrv_fetch_array($stmt_pendientes, SQLSRV_FETCH_ASSOC);


// --- PREPARACIÓN KPI 3 & 4 ---
$where_accion = '';
$params_accion = [];
if ($usuario_seleccionado) {
    // ATENCIÓN: Asegúrate que la columna USUARIO_DESAUTORIZACION exista en tu tabla CPA35. 
    // Si no existe, esto causará el error.
    $where_accion = " AND (AUTORIZO = ? OR USUARIO_DESAUTORIZACION = ?) ";
    $params_accion = [$usuario_seleccionado, $usuario_seleccionado];
}

// --- KPI 3: AUTORIZADAS HOY ---
$sql_autorizadas = "SELECT COUNT(*) AS total_count FROM CPA35 WHERE ESTADO = 2 AND CAST(FEC_AUTORI AS DATE) = CAST(GETDATE() AS DATE)" . $where_accion;
$stmt_autorizadas = sqlsrv_query($conn, $sql_autorizadas, $params_accion);

// CORRECCIÓN: Validar si la consulta falló antes de hacer fetch
if ($stmt_autorizadas === false) {
    http_response_code(500);
    // Esto te dirá exactamente qué está mal (ej: nombre de columna inválido)
    echo json_encode(['status' => 'error', 'message' => 'Error SQL en Autorizadas', 'details' => sqlsrv_errors()]);
    exit();
}
$autorizadas_data = sqlsrv_fetch_array($stmt_autorizadas, SQLSRV_FETCH_ASSOC);


// --- KPI 4: RECHAZADAS HOY ---
// ATENCIÓN: Asegúrate que FECHA_DESAUTORIZACION exista.
$sql_rechazadas = "SELECT COUNT(*) AS total_count FROM CPA35 WHERE ESTADO = 4 AND CAST(FECHA_DESAUTORIZACION AS DATE) = CAST(GETDATE() AS DATE)" . $where_accion;
$stmt_rechazadas = sqlsrv_query($conn, $sql_rechazadas, $params_accion);

// CORRECCIÓN: Validar si la consulta falló antes de hacer fetch
if ($stmt_rechazadas === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error SQL en Rechazadas', 'details' => sqlsrv_errors()]);
    exit();
}
$rechazadas_data = sqlsrv_fetch_array($stmt_rechazadas, SQLSRV_FETCH_ASSOC);


// --- RETORNO FINAL ---
$dashboard_stats = [
    'pendientes_count' => $pendientes_data['total_count'] ?? 0,
    'pendientes_monto' => $pendientes_data['total_monto'] ?? 0,
    'autorizadas_hoy'  => $autorizadas_data['total_count'] ?? 0,
    'rechazadas_hoy'   => $rechazadas_data['total_count'] ?? 0,
];

echo json_encode($dashboard_stats, JSON_UNESCAPED_UNICODE);

// Liberar recursos
if (isset($stmt_pendientes) && $stmt_pendientes) sqlsrv_free_stmt($stmt_pendientes);
if (isset($stmt_autorizadas) && $stmt_autorizadas) sqlsrv_free_stmt($stmt_autorizadas);
if (isset($stmt_rechazadas) && $stmt_rechazadas) sqlsrv_free_stmt($stmt_rechazadas);

if ($conn) sqlsrv_close($conn);
if ($conn_sistemas) sqlsrv_close($conn_sistemas);
?>