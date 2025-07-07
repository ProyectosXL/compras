
<?php

/**
 * Clase para exportar datos a formato Excel (.xlsx)
 * Maneja la creación de archivos Excel con múltiples hojas y formato
 */
class ExcelExporter {
    
    /**
     * Crea un archivo Excel simple con los datos proporcionados
     */
    public static function crearArchivoExcel($datos, $nombreHoja = 'Datos', $nombreArchivo = null) {
        if (!$nombreArchivo) {
            $nombreArchivo = 'presupuesto_' . date('Y-m-d_H-i-s') . '.xlsx';
        }
        
        // Crear estructura XML para Excel
        $xml = self::generarXMLExcel($datos, $nombreHoja);
        
        // Crear archivo ZIP (formato .xlsx)
        $zip = new ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
        
        if ($zip->open($tempFile, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('No se pudo crear el archivo Excel');
        }
        
        // Agregar archivos necesarios para Excel
        self::agregarArchivosExcel($zip, $xml, $nombreHoja);
        
        $zip->close();
        
        return [
            'archivo' => $tempFile,
            'nombre' => $nombreArchivo,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
    }
    
    /**
     * Exporta directamente el archivo Excel al navegador
     */
    public static function exportarExcel($datos, $nombreHoja = 'Datos', $nombreArchivo = null) {
        $archivo = self::crearArchivoExcel($datos, $nombreHoja, $nombreArchivo);
        
        // Headers para descarga
        header('Content-Type: ' . $archivo['mime_type']);
        header('Content-Disposition: attachment; filename="' . $archivo['nombre'] . '"');
        header('Content-Length: ' . filesize($archivo['archivo']));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        // Enviar archivo
        readfile($archivo['archivo']);
        
        // Limpiar archivo temporal
        unlink($archivo['archivo']);
        exit;
    }
    
    /**
     * Crea un Excel con múltiples hojas para las 3 solapas del presupuesto
     */
    public static function exportarPresupuestoCompleto($datosVerano, $datosInvierno, $datosStock) {
        $nombreArchivo = 'presupuesto_completo_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        $zip = new ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_multi_');
        
        if ($zip->open($tempFile, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('No se pudo crear el archivo Excel');
        }
        
        // Crear XMLs para cada hoja
        $xmlVerano = self::generarXMLExcel($datosVerano, 'Compra Verano');
        $xmlInvierno = self::generarXMLExcel($datosInvierno, 'Compra Invierno');
        $xmlStock = self::generarXMLExcel($datosStock, 'Stock Proyectado');
        
        // Agregar archivos base de Excel
        self::agregarArchivosBaseExcel($zip);
        
        // Agregar hojas de datos
        $zip->addFromString('xl/worksheets/sheet1.xml', $xmlVerano);
        $zip->addFromString('xl/worksheets/sheet2.xml', $xmlInvierno);
        $zip->addFromString('xl/worksheets/sheet3.xml', $xmlStock);
        
        // Crear workbook.xml para múltiples hojas
        $workbookXML = self::generarWorkbookMultipleHojas();
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
     * Genera el XML principal de datos para una hoja de Excel
     */
    private static function generarXMLExcel($datos, $nombreHoja) {
        if (empty($datos)) {
            return self::generarXMLVacio($nombreHoja);
        }
        
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n";
        $xml .= '<sheetData>' . "\n";
        
        // Encabezados
        $headers = array_keys($datos[0]);
        $xml .= '<row r="1">' . "\n";
        foreach ($headers as $index => $header) {
            $cellRef = self::obtenerReferenciaColumna($index + 1) . '1';
            $xml .= '<c r="' . $cellRef . '" t="inlineStr">';
            $xml .= '<is><t>' . htmlspecialchars($header) . '</t></is>';
            $xml .= '</c>' . "\n";
        }
        $xml .= '</row>' . "\n";
        
        // Datos
        foreach ($datos as $rowIndex => $fila) {
            $rowNum = $rowIndex + 2; // +2 porque empezamos en fila 2 (después de headers)
            $xml .= '<row r="' . $rowNum . '">' . "\n";
            
            $colIndex = 1;
            foreach ($headers as $header) {
                $cellRef = self::obtenerReferenciaColumna($colIndex) . $rowNum;
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
     * Convierte número de columna a referencia de Excel (A, B, C, ... AA, AB, etc.)
     */
    private static function obtenerReferenciaColumna($numero) {
        $referencia = '';
        while ($numero > 0) {
            $numero--; // Ajustar para base 0
            $referencia = chr(65 + ($numero % 26)) . $referencia;
            $numero = intval($numero / 26);
        }
        return $referencia;
    }
    
    /**
     * Agrega todos los archivos necesarios para un Excel válido
     */
    private static function agregarArchivosExcel($zip, $xmlDatos, $nombreHoja) {
        // [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        
        // _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);
        
        // xl/_rels/workbook.xml.rels
        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        
        // xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheets>
        <sheet name="' . htmlspecialchars($nombreHoja) . '" sheetId="1" r:id="rId1" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/>
    </sheets>
</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);
        
        // xl/worksheets/sheet1.xml (datos)
        $zip->addFromString('xl/worksheets/sheet1.xml', $xmlDatos);
    }
    
    /**
     * Genera XML para hoja vacía
     */
    private static function generarXMLVacio($nombreHoja) {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>
        <row r="1">
            <c r="A1" t="inlineStr">
                <is><t>No hay datos disponibles</t></is>
            </c>
        </row>
    </sheetData>
</worksheet>';
    }
    
    /**
     * Agrega archivos base para Excel con múltiples hojas
     */
    private static function agregarArchivosBaseExcel($zip) {
        // [Content_Types].xml para múltiples hojas
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        
        // _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);
        
        // xl/_rels/workbook.xml.rels para múltiples hojas
        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    }
    
    /**
     * Genera workbook.xml para múltiples hojas
     */
    private static function generarWorkbookMultipleHojas() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Compra Verano" sheetId="1" r:id="rId1"/>
        <sheet name="Compra Invierno" sheetId="2" r:id="rId2"/>
        <sheet name="Stock Proyectado" sheetId="3" r:id="rId3"/>
    </sheets>
</workbook>';
    }
}
?>