<?php
// --- api/derivar_proveedor.php ---
// Este script inserta o actualiza la asignación de un proveedor a un usuario.

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

// Incluimos nuestro archivo de base de datos que ahora maneja múltiples conexiones
require_once '../config/database.php';

// Validamos la conexión a la base de datos 'sistemas'
if ($conn_sistemas === null) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'No se pudo establecer conexión con la base de datos de asignaciones.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit();
}

// Recibimos los datos enviados desde el frontend
$cod_provee = isset($_POST['cod_provee']) ? trim($_POST['cod_provee']) : null;
$usuario_asignado = isset($_POST['usuario_asignado']) ? trim($_POST['usuario_asignado']) : null;
$asignado_por = isset($_POST['asignado_por']) ? trim($_POST['asignado_por']) : null; // Quién hizo la derivación

// Validamos que los datos necesarios estén presentes
if (empty($cod_provee) || empty($usuario_asignado) || empty($asignado_por)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros requeridos.']);
    exit();
}

// Lógica de UPSERT (Update or Insert):
// 1. Intentamos actualizar una fila existente para este proveedor.
// 2. Si no se afecta ninguna fila (porque no existía), procedemos a insertar una nueva.

$sql_update = "UPDATE FP_DERIVACION_OC SET 
                    USUARIO_AUTORIZADOR = ?, 
                    ASIGNADO_POR = ?, 
                    FECHA_ASIGNACION = GETDATE()
                WHERE COD_PROVEE = ?";

$params_update = [$usuario_asignado, $asignado_por, $cod_provee];
$stmt_update = sqlsrv_query($conn_sistemas, $sql_update, $params_update);

if ($stmt_update === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al intentar actualizar la asignación.', 'details' => sqlsrv_errors()]);
    sqlsrv_close($conn_sistemas);
    exit();
}

// Verificamos si la actualización afectó a alguna fila
if (sqlsrv_rows_affected($stmt_update) > 0) {
    // Si se actualizó, la tarea está hecha.
    echo json_encode(['status' => 'success', 'message' => "Proveedor {$cod_provee} re-asignado exitosamente a {$usuario_asignado}."]);
} else {
    // Si no se actualizó, significa que el proveedor no estaba en la tabla. Lo insertamos.
    $sql_insert = "INSERT INTO FP_DERIVACION_OC (COD_PROVEE, USUARIO_AUTORIZADOR, ASIGNADO_POR, FECHA_ASIGNACION) 
                   VALUES (?, ?, ?, GETDATE())";

    $params_insert = [$cod_provee, $usuario_asignado, $asignado_por];
    $stmt_insert = sqlsrv_query($conn_sistemas, $sql_insert, $params_insert);
    
    if ($stmt_insert === false) {
        http_response_code(500);
        // Podría fallar si otro proceso lo insertó justo en el medio (muy improbable)
        echo json_encode(['status' => 'error', 'message' => 'Error al insertar la nueva asignación.', 'details' => sqlsrv_errors()]);
    } else {
        echo json_encode(['status' => 'success', 'message' => "Proveedor {$cod_provee} asignado por primera vez a {$usuario_asignado}."]);
    }
    sqlsrv_free_stmt($stmt_insert);
}

sqlsrv_free_stmt($stmt_update);
sqlsrv_close($conn_sistemas); // Cerramos la conexión específica que abrimos

?>