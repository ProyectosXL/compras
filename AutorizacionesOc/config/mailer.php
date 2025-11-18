<?php
// --- config/mailer.php ---

// Importar las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Cargar el autoloader de Composer para que encuentre las clases
require_once __DIR__ . '/../vendor/autoload.php';

// Esta función creará y configurará una instancia de PHPMailer lista para usar.
function configurarMailer() {
    $mail = new PHPMailer(true); // El 'true' activa las excepciones

    try {
        // --- CONFIGURACIÓN DEL SERVIDOR SMTP ---
        //$mail->SMTPDebug = SMTP::DEBUG_SERVER;  // Descomenta esta línea si necesitas ver el log detallado de la conexión

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';             // ¡¡CAMBIAR!! -> Servidor SMTP. Para Office365 es 'smtp.office365.com'
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sistemas@xl.com.ar';         // ¡¡CAMBIAR!! -> Tu dirección de correo completa que se usará para enviar
        $mail->Password   = 'bhwbrwswykkbwcpc'; // ¡¡CAMBIAR!! -> Tu contraseña.
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;    // Usar 'tls' o 'ssl'. Para Gmail suele ser 'ssl'.
        $mail->Port       = 465;                            // Puerto SMTP. Para 'ssl' es 465, para 'tls' es 587.

        // --- REMITENTE (DE PARTE DE QUIÉN) ---
        $mail->setFrom('sistemas@xl.com.ar', 'Sistema de Autorizaciones OC');
        
        $mail->CharSet = 'UTF-8';

        return $mail;

    } catch (Exception $e) {
        // Si hay un error al configurar, lo registramos. El script que lo llame recibirá null.
        error_log("Error al configurar PHPMailer: {$e->getMessage()}");
        return null;
    }
}