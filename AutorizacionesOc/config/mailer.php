<?php
// --- config/mailer.php ---

// Importar las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Cargar el autoloader de Composer para que encuentre las clases
require_once __DIR__ . '/../vendor/autoload.php';

// Esta función creará y configurará una instancia de PHPMailer lista para usar.
function configurarMailer()
{
    $mail = new PHPMailer(true); // El 'true' activa las excepciones

    try {
        // --- CONFIGURACIÓN DEL SERVIDOR SMTP ---
        //$mail->SMTPDebug = SMTP::DEBUG_SERVER;  // Descomenta esta línea si necesitas ver el log detallado de la conexión

        $mail->isSMTP();
        $mail->Host = $_ENV['HOST_EMAIL_EGRESOS'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['USER_EMAIL_EGRESOS'] ?? 'notificaciones@xl.com.ar';
        $mail->Password = $_ENV['PASS_EMAIL_EGRESOS'] ?? 'yvsuiewmcztagevs';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $_ENV['PORT_EMAIL_EGRESOS'] ?? 587;

        // --- REMITENTE (DE PARTE DE QUIÉN) ---
        $mail->setFrom($mail->Username, 'Sistema de Autorizaciones OC');

        $mail->CharSet = 'UTF-8';

        return $mail;

    } catch (Exception $e) {
        // Si hay un error al configurar, lo registramos. El script que lo llame recibirá null.
        error_log("Error al configurar PHPMailer: {$e->getMessage()}");
        return null;
    }
}