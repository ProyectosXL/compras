<?php
// --- api/get_autorizadores.php (VERSIÓN FILTRADA POR CAMPO 'TIPO') ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

// -------- POR FAVOR, CONFIRMA Y AJUSTA ESTOS DOS VALORES --------
$columnaFiltro = 'TIPO';     // ¿Es 'TIPO' el nombre correcto de la columna?
$valorFiltro   = 'GERENCIA';  // ¿Es 'GERENCIA' el valor correcto para los gerentes?
// ----------------------------------------------------------------

$sql = "
    SELECT DISTINCT
        USUARIO
    FROM
        CPA_PERFIL_AUTORIZACION_OC_USUARIO
";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al consultar la lista de usuarios.', 'details' => sqlsrv_errors()], JSON_UNESCAPED_UNICODE);
    exit();
}

$autorizadores_filtrados = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($row['USUARIO'] && trim($row['USUARIO']) !== '') {
        $autorizadores_filtrados[] = trim($row['USUARIO']);
    }
}

echo json_encode($autorizadores_filtrados, JSON_UNESCAPED_UNICODE);

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>