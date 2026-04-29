<?php
// --- api/autorizar_orden.php (VERSIÓN FINAL USANDO destinatarios.php) ---
ini_set('display_errors', 1); error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/mailer.php';

// Incluimos nuestro archivo "agenda" de destinatarios
$destinatarios_config = require_once '../config/destinatarios.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('...'); }
$n_orden_co = isset($_POST['n_orden_co']) ? trim($_POST['n_orden_co']) : null;
$usuario_autoriza = isset($_POST['usuario_autoriza']) ? $_POST['usuario_autoriza'] : null;
if (empty($n_orden_co) || empty($usuario_autoriza)) { http_response_code(400); exit('...'); }

// --- SEGURIDAD: RODRIAL NO PUEDE AUTORIZAR ---
if ($usuario_autoriza === 'RODRIAL') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'El usuario RODRIAL no tiene permisos para autorizar órdenes. Solo puede derivar.']);
    exit();
}

// Obtenemos el nombre del proveedor Y el usuario que ingresó la OC
$sql_check = "SELECT A.USUARIO_INGRESO AS usuario_creador, B.NOM_PROVEE AS proveedor_nombre FROM CPA35 AS A INNER JOIN CPA01 AS B ON A.COD_PROVEE = B.COD_PROVEE WHERE LTRIM(A.N_ORDEN_CO) = ? AND A.ESTADO = 1";
$params_check = [$n_orden_co];
$stmt_check = sqlsrv_query($conn, $sql_check, $params_check);

if ($stmt_check && sqlsrv_has_rows($stmt_check)) {
    $oc_data = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
    $proveedor_oc = $oc_data['proveedor_nombre'];
    $usuario_creador = $oc_data['usuario_creador'];
    sqlsrv_free_stmt($stmt_check);
} else { echo json_encode(['status' => 'info', 'message' => 'OC no encontrada o ya procesada.']); if ($stmt_check) sqlsrv_free_stmt($stmt_check); sqlsrv_close($conn); exit(); }

$sql_update = "UPDATE CPA35 SET ESTADO = 2, ID_ESTADO_ORDEN_COMPRA = 2, AUTORIZO = ?, FEC_AUTORI = GETDATE(), HORA_AUTOR = FORMAT(GETDATE(), 'HHmm'), TERM_AUTORIZACION = 'APP_MOVIL', USUA_ULTIMA_MODIFICACION = ?, HORA_ULTIMA_MODIFICACION = FORMAT(GETDATE(), 'HHmmss'), TERM_ULTIMA_MODIFICACION = 'APP_MOVIL' WHERE LTRIM(N_ORDEN_CO) = ? AND ESTADO = 1";
$params_update = [$usuario_autoriza, $usuario_autoriza, $n_orden_co];
$stmt_update = sqlsrv_query($conn, $sql_update, $params_update);

if ($stmt_update === false) { http_response_code(500); echo json_encode(['status' => 'error', 'message' => 'Error al ejecutar el UPDATE en la OC.', 'details' => sqlsrv_errors()]); exit(); }

if (sqlsrv_rows_affected($stmt_update) > 0) {
    
    // ---- INICIO DE LA NUEVA LÓGICA DE BÚSQUEDA DE DESTINATARIO ----
    
    // Verificamos si el usuario creador existe en nuestro archivo de configuración.
    if (isset($destinatarios_config['por_usuario'][$usuario_creador])) {
        
        $destinatario = $destinatarios_config['por_usuario'][$usuario_creador];
        
        $asunto = "OC Aprobada: Nro. {$n_orden_co}";
        $cuerpo = "<html><body><h2>Notificación de Orden de Compra Aprobada</h2><p>Hola <strong>{$usuario_creador}</strong>,</p><p>La OC <strong>Nro. {$n_orden_co}</strong> que ingresaste para el proveedor <strong>" . htmlspecialchars($proveedor_oc, ENT_QUOTES, 'UTF-8') . "</strong> ha sido aprobada.</p><p><strong>Aprobado por:</strong> {$usuario_autoriza}</p><hr><p><small>Este es un correo automático.</small></p></body></html>";
        
        $mailer = configurarMailer();
        if ($mailer) {
            try {
                $mailer->addAddress($destinatario);
                $mailer->isHTML(true);
                $mailer->Subject = $asunto;
                $mailer->Body    = $cuerpo;
                $mailer->send();
            } catch (Exception $e) {
                error_log("Error de PHPMailer al autorizar: {$mailer->ErrorInfo}");
            }
        }
    }
    // Si el usuario_creador NO está en el archivo, simplemente no se hace nada.

    // --- LIMPIEZA DE DERIVACIÓN SI ES PROVEEDOR ESPECIAL ---
    $sql_prov_especial = "SELECT COD_PROVEE FROM CPA35 WHERE LTRIM(N_ORDEN_CO) = ?";
    $stmt_prov_especial = sqlsrv_query($conn, $sql_prov_especial, [$n_orden_co]);
    if ($stmt_prov_especial && $row_prov = sqlsrv_fetch_array($stmt_prov_especial, SQLSRV_FETCH_ASSOC)) {
        $cod_provee = $row_prov['COD_PROVEE'];
        if (in_array($cod_provee, ['OGONIS', 'OGGESS'])) {
            $sql_del = "DELETE FROM sistemas.dbo.FP_DERIVACION_OC WHERE COD_PROVEE = ?";
            sqlsrv_query($conn_sistemas, $sql_del, [$cod_provee]);
        }
        sqlsrv_free_stmt($stmt_prov_especial);
    }
    
    echo json_encode(['status' => 'success', 'message' => '¡OC autorizada con éxito!']);

} else {
    echo json_encode(['status' => 'info', 'message' => 'La OC no requiere autorización.']);
}

sqlsrv_free_stmt($stmt_update);
sqlsrv_close($conn);
?>