<?php
// --- api/get_dashboard_stats.php (VERSIÓN FINAL DINÁMICA) ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

$usuario_seleccionado = isset($_GET['usuario']) ? $_GET['usuario'] : null;
$where_pendientes = '';
$params_pendientes = [];
$where_accion = '';
$params_accion = [];


// Si se especificó un usuario, preparamos filtros adicionales
if ($usuario_seleccionado) {
    // --- Primero, obtenemos los perfiles de autorización del usuario ---
    $sql_perfiles = "SELECT ID_CPA_PERFIL_AUTORIZACION_OC FROM CPA_PERFIL_AUTORIZACION_OC_USUARIO WHERE USUARIO = ?";
    $stmt_perfiles = sqlsrv_query($conn, $sql_perfiles, [$usuario_seleccionado]);
    
    $perfiles_ids = [];
    if ($stmt_perfiles) {
        while ($row = sqlsrv_fetch_array($stmt_perfiles, SQLSRV_FETCH_ASSOC)) {
            $perfiles_ids[] = $row['ID_CPA_PERFIL_AUTORIZACION_OC'];
        }
        sqlsrv_free_stmt($stmt_perfiles);
    }
    
    // Si el usuario tiene perfiles, construimos la cláusula WHERE para las pendientes
    if (!empty($perfiles_ids)) {
        $placeholders = implode(',', array_fill(0, count($perfiles_ids), '?'));
        
        // El filtro busca OCs cuyo perfil (basado en el monto) coincida con alguno de los perfiles del usuario.
        // Se hace en una subconsulta para poder usar los parámetros correctamente.
        $where_pendientes = " AND A.N_ORDEN_CO IN (
            SELECT A_sub.N_ORDEN_CO 
            FROM CPA35 A_sub
            INNER JOIN CPA_PERFIL_AUTORIZACION_OC P ON A_sub.TOTAL_CTE BETWEEN P.IMPORTE_MINIMO_AUTORIZAR AND P.IMPORTE_MAXIMO_AUTORIZAR
            WHERE P.ID_CPA_PERFIL_AUTORIZACION_OC IN ($placeholders)
        ) ";
        $params_pendientes = $perfiles_ids;
    } else {
        // Si el usuario no tiene perfiles, nunca tendrá pendientes. Forzamos que la consulta no devuelva nada.
        $where_pendientes = " AND 1 = 0 "; // Condición siempre falsa
    }
    
    // Filtro para acciones realizadas (autorizadas/rechazadas) por el usuario
    $where_accion = " AND (AUTORIZO = ? OR USUARIO_DESAUTORIZACION = ?) ";
    $params_accion = [$usuario_seleccionado, $usuario_seleccionado];
    
}


// --- KPI 1 & 2: OCs pendientes y su monto total ---
// Se añade el filtro dinámico a la consulta base
$sql_pendientes = "SELECT COUNT(*) AS total_count, SUM(TOTAL_CTE) AS total_monto FROM CPA35 A WHERE ESTADO = 1" . $where_pendientes;
$stmt_pendientes = sqlsrv_query($conn, $sql_pendientes, $params_pendientes);
$pendientes_data = sqlsrv_fetch_array($stmt_pendientes, SQLSRV_FETCH_ASSOC);


// --- KPI 3: OCs autorizadas hoy ---
// Se añade el filtro de usuario si existe
$sql_autorizadas = "SELECT COUNT(*) AS total_count FROM CPA35 WHERE ESTADO = 2 AND CAST(FEC_AUTORI AS DATE) = CAST(GETDATE() AS DATE)" . $where_accion;
$stmt_autorizadas = sqlsrv_query($conn, $sql_autorizadas, $params_accion);
$autorizadas_data = sqlsrv_fetch_array($stmt_autorizadas, SQLSRV_FETCH_ASSOC);


// --- KPI 4: OCs rechazadas (desautorizadas) hoy ---
// Se añade el filtro de usuario si existe
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


// Liberar recursos de los statements que se ejecutaron
if (isset($stmt_pendientes) && $stmt_pendientes) sqlsrv_free_stmt($stmt_pendientes);
if (isset($stmt_autorizadas) && $stmt_autorizadas) sqlsrv_free_stmt($stmt_autorizadas);
if (isset($stmt_rechazadas) && $stmt_rechazadas) sqlsrv_free_stmt($stmt_rechazadas);
sqlsrv_close($conn);

?>