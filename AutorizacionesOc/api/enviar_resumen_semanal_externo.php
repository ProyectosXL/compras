<?php
// --- api/enviar_resumen_semanal_externo.php (VERSIÓN FINAL CON DESTINATARIOS CONFIGURABLES Y LISTADO ÚNICO) ---

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../config/mailer.php';

// Incluimos el nuevo archivo de configuración de destinatarios
$destinatarios_config = require_once '../config/destinatarios.php';

// --- Seguridad ---
$CLAVE_SECRETA_DEFINIDA = 'MiClaveSuperSeguraParaElCron12345XYZ'; 
if (!isset($_GET['secret']) || $_GET['secret'] !== $CLAVE_SECRETA_DEFINIDA) {
    http_response_code(403);
    die('Acceso no autorizado.');
}

// --- Lógica de 7 días ---
$log_file = __DIR__ . '/last_weekly_summary.log';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$hoy = new DateTime();
$fecha_ultimo_envio = null;

if (file_exists($log_file)) {
    $contenido_log = file_get_contents($log_file);
    if ($contenido_log) { 
        $fecha_ultimo_envio = new DateTime($contenido_log); 
    }
}

if ($fecha_ultimo_envio !== null) {
    $dias_transcurridos = $hoy->diff($fecha_ultimo_envio)->days;
    if ($dias_transcurridos < 7) { 
        die("Proceso detenido: Aún no han pasado 7 días desde el último envío."); 
    }
}

echo "Iniciando proceso de envío de resumen GENERAL y ÚNICO de pendientes...\n<br>";

// Consulta con DISTINCT para obtener una lista ÚNICA de OCs.
$sql_pendientes = "
    SELECT DISTINCT
        A.N_ORDEN_CO AS numero,
        B.NOM_PROVEE AS proveedor,
        convert(varchar, A.FECHA_INGRESO, 103) as fecha,
        A.TOTAL_CTE AS monto
    FROM
        CPA35 A
    INNER JOIN
        CPA01 B ON A.COD_PROVEE = B.COD_PROVEE
    INNER JOIN
        CPA_PERFIL_AUTORIZACION_OC P ON A.TOTAL_CTE BETWEEN P.IMPORTE_MINIMO_AUTORIZAR AND P.IMPORTE_MAXIMO_AUTORIZAR
    WHERE
        A.ESTADO = 1 -- Solo OCs con estado 'Ingresada'
    ORDER BY
        fecha ASC;
";

$stmt_pendientes = sqlsrv_query($conn, $sql_pendientes);

if ($stmt_pendientes === false) {
    http_response_code(500);
    echo "Error ejecutando la consulta de pendientes: " . print_r(sqlsrv_errors(), true);
    exit();
}

if (sqlsrv_has_rows($stmt_pendientes)) {
    $pendientes = [];
    $total_monto_general = 0.0;
    while ($oc = sqlsrv_fetch_array($stmt_pendientes, SQLSRV_FETCH_ASSOC)) {
        $pendientes[] = $oc;
        $total_monto_general += (float)$oc['monto'];
    }
    $total_pendientes_general = count($pendientes);

    $asunto = "Resumen General: {$total_pendientes_general} OC(s) Pendientes en el Sistema";
    
    // Se ajusta la tabla HTML para que ya no incluya la columna "Autorizador Asignado".
    $cuerpo_html = "
    <!DOCTYPE html><html><head><style>body{font-family:Arial,sans-serif;color:#333}h2{color:#0056b3}table{border-collapse:collapse;width:100%;margin-top:20px}th,td{border:1px solid #ddd;padding:8px;text-align:left}thead{background-color:#f2f2f2}.monto{text-align:right}</style></head>
    <body><h2>Resumen General de Órdenes de Compra Pendientes</h2><p>A continuación se detallan las <strong>{$total_pendientes_general} órdenes de compra</strong> que se encuentran pendientes de autorización en el sistema, por un monto total de <strong>$" . number_format($total_monto_general, 2, ',', '.') . "</strong>.</p>
    <table>
        <thead>
            <tr>
                <th>Nro. OC</th>
                <th>Proveedor</th>
                <th>Fecha</th>
                <th class='monto'>Monto</th>
            </tr>
        </thead>
        <tbody>";

    foreach($pendientes as $oc){
        $cuerpo_html.="<tr>
            <td>{$oc['numero']}</td>
            <td>".htmlspecialchars($oc['proveedor'])."</td>
            <td>{$oc['fecha']}</td>
            <td class='monto'>$".number_format((float)$oc['monto'], 2, ',', '.')."</td>
        </tr>";}

    $cuerpo_html .= "</tbody></table><hr><p><small>Correo generado automáticamente por el Sistema de Autorizaciones.</small></p></body></html>";

    $mailer = configurarMailer();
    if ($mailer) {
        try {
            // Leemos los destinatarios del archivo de configuración y los añadimos
            foreach ($destinatarios_config['resumen_semanal'] as $destinatario) {
                $mailer->addAddress($destinatario);
            }

            $mailer->isHTML(true);
            $mailer->Subject = $asunto;
            $mailer->Body    = $cuerpo_html;
            $mailer->send();
            echo "Resumen GENERAL y ÚNICO enviado con éxito.<br>";
        } catch (Exception $e) {
            echo "ERROR al enviar el resumen general: {$mailer->ErrorInfo}<br>";
        }
    }
    
    file_put_contents($log_file, $hoy->format('Y-m-d'));
    echo "Proceso finalizado. Log de ejecución actualizado.\n";

} else {
    file_put_contents($log_file, $hoy->format('Y-m-d'));
    echo "No se encontraron Órdenes de Compra pendientes en el sistema.\n Proceso finalizado.\n";
}

if($stmt_pendientes){ sqlsrv_free_stmt($stmt_pendientes); }
sqlsrv_close($conn);
?>