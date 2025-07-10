
<?php

/**
 * Controlador específico para funcionalidades de exportación
 * Incluye exportación de compras detalle
 */
class ExportacionController {
    
    private $presupuesto;
    private $compras;
    
    public function __construct() {
        require_once __DIR__ . '/../class/presupuesto.php';
        require_once __DIR__ . '/../class/compras.php';
        require_once __DIR__ . '/../class/ExcelExporter.php';
        require_once __DIR__ . '/ProcesadorDatos.php';
        
        $this->presupuesto = new Presupuesto();
        $this->compras = new Compras();
    }
    
    /**
     * Exportar a Excel según la solapa especificada
     */
    public function exportarExcel() {
        try {
            $solapa = $_GET['solapa'] ?? 'verano';
            
            switch ($solapa) {
                case 'verano':
                    $this->exportarCompraVeranoExcel();
                    break;
                case 'invierno':
                    $this->exportarCompraInviernoExcel();
                    break;
                case 'stock':
                    $this->exportarStockProyectadoExcel();
                    break;
                case 'compras-detalle':
                    $this->exportarComprasDetalleExcel();
                    break;
                case 'completo':
                    $this->exportarPresupuestoCompletoExcel();
                    break;
                default:
                    $this->jsonResponse([
                        'success' => false,
                        'message' => 'Solapa no válida. Use: verano, invierno, stock, compras-detalle o completo'
                    ], 400);
            }
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al exportar: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Exportar compra verano a Excel
     */
    private function exportarCompraVeranoExcel() {
        $datosBase = $this->obtenerDatosBase();
        $procesador = new ProcesadorDatos();
        $datos = $procesador->procesarDatosCompraVerano($datosBase);
        
        ExcelExporter::exportarExcel(
            $datos, 
            'Compra Verano', 
            'compra_verano_' . date('Y-m-d') . '.xlsx'
        );
    }
    
    /**
     * Exportar compra invierno a Excel
     */
    private function exportarCompraInviernoExcel() {
        $datosBase = $this->obtenerDatosBase();
        $procesador = new ProcesadorDatos();
        $datos = $procesador->procesarDatosCompraInvierno($datosBase);
        
        ExcelExporter::exportarExcel(
            $datos, 
            'Compra Invierno', 
            'compra_invierno_' . date('Y-m-d') . '.xlsx'
        );
    }
    
    /**
     * Exportar stock proyectado a Excel
     */
    private function exportarStockProyectadoExcel() {
        $datosBase = $this->obtenerDatosBase();
        $procesador = new ProcesadorDatos();
        $datos = $procesador->procesarDatosStockProyectado($datosBase);
        
        ExcelExporter::exportarExcel(
            $datos, 
            'Stock Proyectado', 
            'stock_proyectado_' . date('Y-m-d') . '.xlsx'
        );
    }
    
    /**
     * Exportar compras detalle a Excel - NUEVO
     */
    private function exportarComprasDetalleExcel() {
        $filtros = $this->obtenerFiltros();
        $datos = $this->compras->obtenerComprasDetalle($filtros);
        
        if (isset($datos['error'])) {
            throw new Exception($datos['mensaje']);
        }
        
        // Procesar datos para Excel
        $datosExcel = $this->procesarDatosComprasParaExcel($datos);
        
        ExcelExporter::exportarExcel(
            $datosExcel, 
            'Compras Detalle', 
            'compras_detalle_' . date('Y-m-d') . '.xlsx'
        );
    }
    
    /**
     * Exportar presupuesto completo con todas las solapas incluida compras detalle
     */
    private function exportarPresupuestoCompletoExcel() {
        $datosBase = $this->obtenerDatosBase();
        $procesador = new ProcesadorDatos();
        
        // Datos de presupuesto
        $datosVerano = $procesador->procesarDatosCompraVerano($datosBase);
        $datosInvierno = $procesador->procesarDatosCompraInvierno($datosBase);
        $datosStock = $procesador->procesarDatosStockProyectado($datosBase);
        
        // Datos de compras detalle
        $filtros = $this->obtenerFiltros();
        $datosComprasRaw = $this->compras->obtenerComprasDetalle($filtros);
        
        if (isset($datosComprasRaw['error'])) {
            // Si no hay compras, crear array vacío
            $datosCompras = [];
        } else {
            $datosCompras = $this->procesarDatosComprasParaExcel($datosComprasRaw);
        }
        
        // Crear archivo Excel con múltiples hojas
        $this->exportarExcelMultipleHojas([
            'Compra Verano' => $datosVerano,
            'Compra Invierno' => $datosInvierno,
            'Stock Proyectado' => $datosStock,
            'Compras Detalle' => $datosCompras
        ]);
    }
    
    /**
     * Procesar datos de compras para Excel
     */
    private function procesarDatosComprasParaExcel($datos) {
        $resultado = [];
        
        foreach ($datos as $item) {
            $verano = (float)($item['VERANO'] ?? 0);
            $invierno = (float)($item['INVIERNO'] ?? 0);
            $atemporal = (float)($item['ATEMPORAL'] ?? 0);
            $total = $verano + $invierno + $atemporal;
            
            $resultado[] = [
                'Fecha Emisión' => $item['FEC_EMISIO'] ?? '',
                'N° Orden' => $item['N_ORDEN_CO'] ?? '',
                'Proveedor' => $item['NOM_PROVEE'] ?? '',
                'Código Artículo' => $item['COD_ARTICU'] ?? '',
                'Descripción' => $item['DESCRIPCIO'] ?? '',
                'Rubro' => $item['RUBRO'] ?? '',
                'Categoría' => $item['CATEGORIA_PADRE'] ?? '',
                'Verano' => $verano,
                'Invierno' => $invierno,
                'Atemporal' => $atemporal,
                'Total' => $total
            ];
        }
        
        return $resultado;
    }
    
    /**
     * Exportar Excel con múltiples hojas
     */
    private function exportarExcelMultipleHojas($hojas) {
        $nombreArchivo = 'presupuesto_completo_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        $zip = new ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_multi_');
        
        if ($zip->open($tempFile, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('No se pudo crear el archivo Excel');
        }
        
        // Crear XMLs para cada hoja
        $worksheets = [];
        $sheetIndex = 1;
        
        foreach ($hojas as $nombreHoja => $datos) {
            $xmlDatos = $this->generarXMLExcel($datos, $nombreHoja);
            $worksheets[] = [
                'nombre' => $nombreHoja,
                'xml' => $xmlDatos,
                'index' => $sheetIndex
            ];
            $zip->addFromString("xl/worksheets/sheet{$sheetIndex}.xml", $xmlDatos);
            $sheetIndex++;
        }
        
        // Agregar archivos base de Excel
        $this->agregarArchivosBaseExcel($zip, $worksheets);
        
        // Crear workbook.xml para múltiples hojas
        $workbookXML = $this->generarWorkbookMultipleHojas($worksheets);
        $zip->addFromString('xl/workbook.xml', $workbookXML);
        
        $zip->close();
        
        // Headers para descarga
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Content-Length: ' . filesize($tempFile));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        // Enviar archivo
        readfile($tempFile);
        
        // Limpiar archivo temporal
        unlink($tempFile);
        exit;
    }
    
    /**
     * Generar XML para Excel
     */
    private function generarXMLExcel($datos, $nombreHoja) {
        if (empty($datos)) {
            return $this->generarXMLVacio($nombreHoja);
        }
        
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n";
        $xml .= '<sheetData>' . "\n";
        
        // Encabezados
        $headers = array_keys($datos[0]);
        $xml .= '<row r="1">' . "\n";
        foreach ($headers as $index => $header) {
            $cellRef = $this->obtenerReferenciaColumna($index + 1) . '1';
            $xml .= '<c r="' . $cellRef . '" t="inlineStr">';
            $xml .= '<is><t>' . htmlspecialchars($header) . '</t></is>';
            $xml .= '</c>' . "\n";
        }
        $xml .= '</row>' . "\n";
        
        // Datos
        foreach ($datos as $rowIndex => $fila) {
            $rowNum = $rowIndex + 2;
            $xml .= '<row r="' . $rowNum . '">' . "\n";
            
            $colIndex = 1;
            foreach ($headers as $header) {
                $cellRef = $this->obtenerReferenciaColumna($colIndex) . $rowNum;
                $valor = $fila[$header] ?? '';
                
                if (is_numeric($valor)) {
                    $xml .= '<c r="' . $cellRef . '">';
                    $xml .= '<v>' . $valor . '</v>';
                    $xml .= '</c>' . "\n";
                } else {
                    $xml .= '<c r="' . $cellRef . '" t="inlineStr">';
                    $xml .= '<is><t>' . htmlspecialchars($valor) . '</t></is>';
                    $xml .= '</c>' . "\n";
                }
                $colIndex++;
            }
            $xml .= '</row>' . "\n";
        }
        
        $xml .= '</sheetData>' . "\n";
        $xml .= '</worksheet>';
        
        return $xml;
    }
    
    /**
     * Generar XML vacío
     */
    private function generarXMLVacio($nombreHoja) {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>
        <row r="1">
            <c r="A1" t="inlineStr">
                <is><t>No hay datos disponibles para ' . htmlspecialchars($nombreHoja) . '</t></is>
            </c>
        </row>
    </sheetData>
</worksheet>';
    }
    
    /**
     * Convertir número de columna a referencia de Excel
     */
    private function obtenerReferenciaColumna($numero) {
        $referencia = '';
        while ($numero > 0) {
            $numero--;
            $referencia = chr(65 + ($numero % 26)) . $referencia;
            $numero = intval($numero / 26);
        }
        return $referencia;
    }
    
    /**
     * Agregar archivos base para Excel con múltiples hojas
     */
    private function agregarArchivosBaseExcel($zip, $worksheets) {
        // [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        
        foreach ($worksheets as $ws) {
            $contentTypes .= "\n    <Override PartName=\"/xl/worksheets/sheet{$ws['index']}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        }
        
        $contentTypes .= "\n</Types>";
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        
        // _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);
        
        // xl/_rels/workbook.xml.rels
        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        
        foreach ($worksheets as $ws) {
            $workbookRels .= "\n    <Relationship Id=\"rId{$ws['index']}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$ws['index']}.xml\"/>";
        }
        
        $workbookRels .= "\n</Relationships>";
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    }
    
    /**
     * Generar workbook.xml para múltiples hojas
     */
    private function generarWorkbookMultipleHojas($worksheets) {
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>';
        
        foreach ($worksheets as $ws) {
            $workbook .= "\n        <sheet name=\"" . htmlspecialchars($ws['nombre']) . "\" sheetId=\"{$ws['index']}\" r:id=\"rId{$ws['index']}\"/>";
        }
        
        $workbook .= "\n    </sheets>\n</workbook>";
        
        return $workbook;
    }
    
    /**
     * Obtener datos base con validación
     */
    private function obtenerDatosBase() {
        $datos = $this->presupuesto->obtenerPresupuestoCompras();
        
        if (isset($datos['error'])) {
            throw new Exception($datos['mensaje']);
        }
        
        return $datos;
    }
    
    /**
     * Obtener filtros desde parámetros GET
     */
    private function obtenerFiltros() {
        return [
            'proveedor' => $_GET['proveedor'] ?? '',
            'rubro' => $_GET['rubro'] ?? '',
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
            'temporada' => $_GET['temporada'] ?? ''
        ];
    }
    
    /**
     * Enviar respuesta JSON
     */
    private function jsonResponse($data, $httpCode = 200) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
?>