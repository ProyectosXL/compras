<?php
// --- api/get_derivaciones.php ---
// Devuelve el listado de proveedores asignados a usuarios autorizadores.

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

// Validamos la conexión
if ($conn === null || $conn_sistemas === null) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a la base de datos.']);
    exit();
}

// 1. Obtenemos las derivaciones de la base de datos de aplicaciones
$sql_deriv = "SELECT COD_PROVEE, USUARIO_AUTORIZADOR AS usuario, ASIGNADO_POR, CAST(FECHA_ASIGNACION AS DATETIME) AS fecha
              FROM FP_DERIVACION_OC
              ORDER BY FECHA_ASIGNACION DESC";

$stmt_deriv = sqlsrv_query($conn_sistemas, $sql_deriv);

if ($stmt_deriv === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al consultar las derivaciones.', 'details' => sqlsrv_errors()]);
    exit();
}

$derivaciones = [];
$codigos_provee = [];
while ($row = sqlsrv_fetch_array($stmt_deriv, SQLSRV_FETCH_ASSOC)) {
    if ($row['fecha']) { $row['fecha'] = $row['fecha']->format('d-m-Y H:i'); }
    $row['proveedor'] = ''; // Inicializamos
    $derivaciones[] = $row;
    $cod_esc = str_replace("'", "''", $row['COD_PROVEE']);
    if (!in_array("'".$cod_esc."'", $codigos_provee)) {
        $codigos_provee[] = "'".$cod_esc."'";
    }
}
sqlsrv_free_stmt($stmt_deriv);

// 2. Si hay códigos, buscamos sus nombres en la base de datos central
if (!empty($codigos_provee)) {
    $lista_codigos = implode(',', $codigos_provee);
    $sql_prov = "SELECT COD_PROVEE, NOM_PROVEE FROM CPA01 WHERE COD_PROVEE IN ($lista_codigos)";
    $stmt_prov = sqlsrv_query($conn, $sql_prov);
    
    if ($stmt_prov) {
        $nombres = [];
        while ($p = sqlsrv_fetch_array($stmt_prov, SQLSRV_FETCH_ASSOC)) {
            $nombres[trim($p['COD_PROVEE'])] = $p['NOM_PROVEE'];
        }
        sqlsrv_free_stmt($stmt_prov);
        
        // Mapeamos los nombres
        foreach ($derivaciones as &$d) {
            $key = trim($d['COD_PROVEE']);
            if (isset($nombres[$key])) {
                $d['proveedor'] = $nombres[$key];
            }
        }
    }
}

echo json_encode($derivaciones, JSON_UNESCAPED_UNICODE);

sqlsrv_close($conn);
sqlsrv_close($conn_sistemas);
?>
