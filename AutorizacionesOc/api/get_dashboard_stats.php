<?php
// --- api/get_dashboard_stats.php (VERSIÓN FINAL CORREGIDA Y COMPLETA) ---
ini_set('display_errors', 1); 
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

// Validamos las conexiones a ambas bases de datos.
if ($conn === null || $conn_sistemas === null) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a una o más bases de datos.']);
    exit();
}

$usuario_seleccionado = isset($_GET['usuario']) ? trim($_GET['usuario']) : null;
$where_pendientes = '';
// Ya no usamos $params_pendientes para la consulta principal.

if ($usuario_seleccionado) {
        if ($usuario_seleccionado === 'DANM') {
        // LÓGICA PARA DANM (Dispatcher):
        
        // 1. Excluir asignados (Existente)
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
        
        $where_pendientes = ""; // Inicializamos vacío
        
        // 2. Construimos la cláusula WHERE de proveedores
        if (!empty($proveedores_asignados)) {
            $lista_proveedores_str = implode(',', $proveedores_asignados);
            $where_pendientes .= " AND A.COD_PROVEE NOT IN ({$lista_proveedores_str}) ";
        }
        
        // ---- NUEVA CONDICIÓN: Solo montos >= 1.000.000 para los KPI ----
        $where_pendientes .= " AND A.TOTAL_CTE >= 1000000 ";

    } else {
        // LÓGICA PARA USUARIOS NORMALES:
        // 1. Obtener la lista de proveedores ASIGNADOS a este usuario.
        $sql_asignados = "SELECT COD_PROVEE FROM sistemas.dbo.FP_DERIVACION_OC WHERE USUARIO_AUTORIZADOR = ?";
        $stmt_asignados = sqlsrv_query($conn_sistemas, $sql_asignados, [$usuario_seleccionado]);
        $proveedores_asignados = [];
        if ($stmt_asignados) {
            while ($row = sqlsrv_fetch_array($stmt_asignados, SQLSRV_FETCH_ASSOC)) {
                // CORRECCIÓN: Reemplazo de la función inexistente.
                $escaped_provee = str_replace("'", "''", $row['COD_PROVEE']);
                $proveedores_asignados[] = "'" . $escaped_provee . "'";
            }
            sqlsrv_free_stmt($stmt_asignados);
        }
        
        // 2. Construimos la cláusula WHERE.
        if (!empty($proveedores_asignados)) {
            $lista_proveedores_str = implode(',', $proveedores_asignados);
            $where_pendientes = " AND A.COD_PROVEE IN ({$lista_proveedores_str}) ";
        } else {
            // Si el usuario no tiene proveedores asignados, forzamos que el resultado sea 0.
            $where_pendientes = " AND 1 = 0 "; // Condición que nunca es verdadera
        }
    }
}
// Si no se proporciona un usuario, $where_pendientes queda vacío, y se contarán TODAS las pendientes.

// --- KPI 1 & 2: OCs pendientes y su monto total ---
// Usamos la conexión principal ($conn) para consultar CPA35.
$sql_pendientes = "SELECT COUNT(A.N_ORDEN_CO) AS total_count, SUM(A.TOTAL_CTE) AS total_monto FROM CPA35 A WHERE A.ESTADO = 1" . $where_pendientes;
$stmt_pendientes = sqlsrv_query($conn, $sql_pendientes); 

if ($stmt_pendientes === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al ejecutar la consulta para contar pendientes.', 'details' => sqlsrv_errors()], JSON_UNESCAPED_UNICODE);
    exit();
}
$pendientes_data = sqlsrv_fetch_array($stmt_pendientes, SQLSRV_FETCH_ASSOC);


// --- KPI 3 & 4: OCs autorizadas y rechazadas HOY ---
$where_accion = '';
$params_accion = [];
if ($usuario_seleccionado) {
    $where_accion = " AND (AUTORIZO = ? OR USUARIO_DESAUTORIZACION = ?) ";
    $params_accion = [$usuario_seleccionado, $usuario_seleccionado];
}

$sql_autorizadas = "SELECT COUNT(*) AS total_count FROM CPA35 WHERE ESTADO = 2 AND CAST(FEC_AUTORI AS DATE) = CAST(GETDATE() AS DATE)" . $where_accion;
$stmt_autorizadas = sqlsrv_query($conn, $sql_autorizadas, $params_accion);
$autorizadas_data = sqlsrv_fetch_array($stmt_autorizadas, SQLSRV_FETCH_ASSOC);

$sql_rechazadas = "SELECT COUNT(*) AS total_count FROM CPA35 WHERE ESTADO = 4 AND CAST(FECHA_DESAUTORIZACION AS DATE) = CAST(GETDATE() AS DATE)" . $where_accion;
$stmt_rechazadas = sqlsrv_query($conn, $sql_rechazadas, $params_accion);
$rechazadas_data = sqlsrv_fetch_array($stmt_rechazadas, SQLSRV_FETCH_ASSOC);

// --- Ensamblar el resultado final ---
$dashboard_stats = [
    'pendientes_count' => $pendientes_data['total_count'] ?? 0,
    'pendientes_monto' => $pendientes_data['total_monto'] ?? 0,
    'autorizadas_hoy'  => $autorizadas_data['total_count'] ?? 0,
    'rechazadas_hoy'   => $rechazadas_data['total_count'] ?? 0,
];

echo json_encode($dashboard_stats, JSON_UNESCAPED_UNICODE);

// Liberar todos los recursos de los statements
if (isset($stmt_pendientes) && $stmt_pendientes) sqlsrv_free_stmt($stmt_pendientes);
if (isset($stmt_autorizadas) && $stmt_autorizadas) sqlsrv_free_stmt($stmt_autorizadas);
if (isset($stmt_rechazadas) && $stmt_rechazadas) sqlsrv_free_stmt($stmt_rechazadas);

// Cerramos las conexiones abiertas
if ($conn) sqlsrv_close($conn);
if ($conn_sistemas) sqlsrv_close($conn_sistemas);
?>