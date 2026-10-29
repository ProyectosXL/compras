<?php
/**
 * CRON - Sincronizador de Dólar Futuro Matba Rofex
 * 
 * Este script se ejecuta mediante una Tarea Programada de Windows (Task Scheduler)
 * todos los días a las 8:00 AM en el servidor XL-APPS.
 * 
 * Actualiza la tabla dbo.FP_DOLAR_FUTURO_ROFEX en la base de datos 'sistemas' (XL-APPS)
 * consumiendo la API de Matba Rofex Primary Ventures.
 */

date_default_timezone_set('America/Argentina/Buenos_Aires');

$cronDir = __DIR__;
$logDir = $cronDir . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}

$logFile = $logDir . '/sync_rofex_' . date('Y-m-d') . '.log';
$statusFile = $logDir . '/sync_status.json';

function logCron($msg, $logFile) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

logCron("=================================================================", $logFile);
logCron("=== INICIO DE SINCRONIZACION DOLAR FUTURO ROFEX (COMPRAS) ===", $logFile);

try {
    require_once __DIR__ . '/../class/DolarFuturoService.php';

    $rofexService = new DolarFuturoService();

    logCron("Conectando con la API de Matba Rofex Primary...", $logFile);

    // Forzar actualización diaria independientemente del tiempo transcurrido
    $resultado = $rofexService->actualizarDesdeAPI(true);

    if (!empty($resultado['success'])) {
        logCron("Resultado: " . ($resultado['message'] ?? 'OK'), $logFile);

        $datos = $resultado['datos'] ?? [];
        logCron("Total de contratos vigentes actualizados: " . count($datos), $logFile);

        foreach ($datos as $item) {
            $simbolo = str_pad($item['simbolo'], 12);
            $periodo = str_pad($item['mes'] . '/' . $item['anio'], 8);
            $precio = number_format($item['cotizacion'], 2, '.', ',');
            logCron("  - {$simbolo} ({$periodo}) -> $ {$precio}", $logFile);
        }

        logCron("=== SINCRONIZACION FINALIZADA CON EXITO ===", $logFile);

        $statusData = [
            'ultima_ejecucion' => date('Y-m-d H:i:s'),
            'estado' => 'OK',
            'mensaje' => $resultado['message'] ?? 'Actualización completada',
            'total_contratos' => count($datos),
            'contratos' => $datos
        ];
        @file_put_contents($statusFile, json_encode($statusData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        exit(0);
    } else {
        $errorMsg = $resultado['message'] ?? 'Error desconocido al actualizar cotizaciones.';
        logCron("ERROR: " . $errorMsg, $logFile);
        logCron("=== SINCRONIZACION FINALIZADA CON ERRORES ===", $logFile);

        $statusData = [
            'ultima_ejecucion' => date('Y-m-d H:i:s'),
            'estado' => 'ERROR',
            'mensaje' => $errorMsg
        ];
        @file_put_contents($statusFile, json_encode($statusData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        exit(1);
    }
} catch (Throwable $e) {
    logCron("EXCEPCION: " . $e->getMessage(), $logFile);
    logCron("Trace: " . $e->getTraceAsString(), $logFile);
    logCron("=== SINCRONIZACION FALLIDA ===", $logFile);

    $statusData = [
        'ultima_ejecucion' => date('Y-m-d H:i:s'),
        'estado' => 'ERROR',
        'mensaje' => $e->getMessage()
    ];
    @file_put_contents($statusFile, json_encode($statusData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    exit(1);
}
