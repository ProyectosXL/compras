<?php
/**
 * Clase para gestión de Dólar Futuro ROFEX en la base de datos de Sistemas (XL-APPS)
 * Tabla: dbo.FP_DOLAR_FUTURO_ROFEX
 */

require_once __DIR__ . '/../../Class/conexion.php';

class DolarFuturoService {
    private $conn;

    public function __construct() {
        $conexion = new Conexion();
        $this->conn = $conexion->conectar('apps');
        $this->asegurarTabla();
    }

    /**
     * Asegura la existencia de la tabla dbo.FP_DOLAR_FUTURO_ROFEX
     */
    private function asegurarTabla() {
        if (!$this->conn) return;

        $sql = "
        IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'dbo.FP_DOLAR_FUTURO_ROFEX') AND type in (N'U'))
        BEGIN
            CREATE TABLE dbo.FP_DOLAR_FUTURO_ROFEX (
                id INT IDENTITY(1,1) PRIMARY KEY,
                simbolo VARCHAR(50) NOT NULL,
                mes INT NOT NULL,
                anio INT NOT NULL,
                cotizacion DECIMAL(12,4) NOT NULL,
                fecha_actualizacion DATETIME DEFAULT GETDATE(),
                CONSTRAINT UQ_FP_DOLAR_FUTURO_MES_ANIO UNIQUE (mes, anio)
            );
        END";
        @sqlsrv_query($this->conn, $sql);
    }

    /**
     * Actualiza cotizaciones desde la API pública de Matba Rofex Primary
     */
    public function actualizarDesdeAPI($force = false) {
        if (!$this->conn) {
            return ['success' => false, 'message' => 'Sin conexión a base de datos sistemas.'];
        }

        // Si no es forzado, verificar si ya se actualizaron cotizaciones en las últimas 2 horas
        if (!$force) {
            $sqlCheck = "SELECT DATEDIFF(MINUTE, MAX(fecha_actualizacion), GETDATE()) as diff_min FROM dbo.FP_DOLAR_FUTURO_ROFEX";
            $stmtCheck = sqlsrv_query($this->conn, $sqlCheck);
            if ($stmtCheck !== false) {
                $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
                $diffMin = $rowCheck['diff_min'] ?? null;
                sqlsrv_free_stmt($stmtCheck);

                if ($diffMin !== null && $diffMin < 120) {
                    return ['success' => true, 'message' => 'Cotizaciones vigentes recientemente guardadas.'];
                }
            }
        }

        $refUrl = "https://matbarofex.primary.ventures/api/v2/ref-data";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $refUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $refJson = curl_exec($ch);
        curl_close($ch);

        if (empty($refJson)) {
            return ['success' => false, 'message' => 'No se pudo conectar a la API de Matba Rofex Primary.'];
        }

        $refData = json_decode($refJson, true);
        $securities = $refData['securities'] ?? [];

        $mesesMapa = [
            'ENE' => 1, 'FEB' => 2, 'MAR' => 3, 'ABR' => 4,
            'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AGO' => 8,
            'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DIC' => 12
        ];

        $futurosProcesados = [];
        $from = date('Y-m-d\TH:i:s\Z', strtotime('-7 days'));
        $to = date('Y-m-d\TH:i:s\Z');

        foreach ($securities as $sec) {
            $symbol = trim($sec['symbol'] ?? '');
            $secId = trim($sec['id'] ?? '');

            if (preg_match('/^DLR\/([A-Z]{3})(\d{2})$/', $symbol, $mMatches)) {
                $mesCodigo = $mMatches[1];
                $anioSuffix = (int)$mMatches[2];

                if (!isset($mesesMapa[$mesCodigo])) continue;

                $mes = $mesesMapa[$mesCodigo];
                $anio = 2000 + $anioSuffix;

                $seriesUrl = "https://matbarofex.primary.ventures/api/v2/series/securities/{$secId}?resolution=D&from=" . urlencode($from) . "&to=" . urlencode($to);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $seriesUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                $seriesJson = curl_exec($ch);
                curl_close($ch);

                $seriesData = json_decode($seriesJson, true);
                $series = $seriesData['series'] ?? [];

                if (!empty($series)) {
                    $ultimoPunto = end($series);
                    $cotizacion = (float)($ultimoPunto['c'] ?? 0);

                    if ($cotizacion > 0) {
                        $futurosProcesados[] = [
                            'simbolo' => $symbol,
                            'mes' => $mes,
                            'anio' => $anio,
                            'cotizacion' => $cotizacion
                        ];
                    }
                }
            }
        }

        if (empty($futurosProcesados)) {
            return ['success' => false, 'message' => 'No se obtuvieron cotizaciones de futuros válidas.'];
        }

        $fechaActual = date('Y-m-d H:i:s');
        $guardadosCount = 0;

        foreach ($futurosProcesados as $item) {
            $sql = "
                MERGE INTO dbo.FP_DOLAR_FUTURO_ROFEX AS target
                USING (SELECT ? AS mes, ? AS anio) AS source
                ON (target.mes = source.mes AND target.anio = source.anio)
                WHEN MATCHED THEN
                    UPDATE SET 
                        simbolo = ?,
                        cotizacion = ?,
                        fecha_actualizacion = ?
                WHEN NOT MATCHED THEN
                    INSERT (simbolo, mes, anio, cotizacion, fecha_actualizacion)
                    VALUES (?, ?, ?, ?, ?);
            ";

            $params = [
                $item['mes'], $item['anio'],
                $item['simbolo'], $item['cotizacion'], $fechaActual,
                $item['simbolo'], $item['mes'], $item['anio'], $item['cotizacion'], $fechaActual
            ];

            $stmt = sqlsrv_query($this->conn, $sql, $params);
            if ($stmt !== false) {
                $guardadosCount++;
                sqlsrv_free_stmt($stmt);
            }
        }

        return [
            'success' => true,
            'message' => "Se actualizaron $guardadosCount cotizaciones en dbo.FP_DOLAR_FUTURO_ROFEX.",
            'datos' => $futurosProcesados
        ];
    }

    /**
     * Obtener mapa de cotizaciones de dólar futuro guardadas en la base de datos
     * Retorna array asociativo con clave 'mes-anio' => cotizacion (Ej: '8-2026' => 1506.0)
     */
    public function obtenerCotizacionesGuardadas() {
        if (!$this->conn) return [];

        $sql = "SELECT simbolo, mes, anio, cotizacion FROM dbo.FP_DOLAR_FUTURO_ROFEX ORDER BY anio, mes";
        $stmt = sqlsrv_query($this->conn, $sql);
        if ($stmt === false) return [];

        $resultado = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $clave = $row['mes'] . '-' . $row['anio'];
            $resultado[$clave] = (float)$row['cotizacion'];
        }
        sqlsrv_free_stmt($stmt);
        return $resultado;
    }
}
