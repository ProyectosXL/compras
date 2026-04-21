
<?php

/**
 * Controlador para gestionar la vista de contenedores (OC Pendientes + Importaciones)
 */
class ContenedoresController {

    private $contenedores;

    public function __construct() {
        require_once __DIR__ . '/../class/contenedores.php';
        $this->contenedores = new Contenedores();
    }

    /**
     * Obtener datos de contenedores detalle
     */
    public function obtenerContenedoresDetalle() {
        try {
            $filtros = $this->obtenerFiltros();
            $datos   = $this->contenedores->obtenerContenedoresDetalle($filtros);

            if (isset($datos['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $datos['mensaje']
                ], 500);
                return;
            }

            $datosProcesados = $this->procesarDatosContenedores($datos);

            $this->jsonResponse([
                'success'          => true,
                'message'          => 'Datos de contenedores obtenidos correctamente',
                'data'             => $datosProcesados,
                'total_registros'  => count($datosProcesados),
                'filtros_aplicados' => $filtros
            ]);

        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener contenedores: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener proveedores para filtro
     */
    public function obtenerProveedores() {
        try {
            $proveedores = $this->contenedores->obtenerProveedores();

            if (isset($proveedores['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $proveedores['mensaje']
                ], 500);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'data'    => $proveedores
            ]);

        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener proveedores: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener rubros para filtro
     */
    public function obtenerRubros() {
        try {
            $rubros = $this->contenedores->obtenerRubros();

            if (isset($rubros['error'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $rubros['mensaje']
                ], 500);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'data'    => $rubros
            ]);

        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener rubros: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener filtros desde parámetros GET
     */
    private function obtenerFiltros() {
        return [
            'proveedor'   => $_GET['proveedor']   ?? '',
            'rubro'       => $_GET['rubro']        ?? '',
            'fecha_desde' => $_GET['fecha_desde']  ?? '',
            'fecha_hasta' => $_GET['fecha_hasta']  ?? '',
            'temporada'   => $_GET['temporada']    ?? ''
        ];
    }

    /**
     * Procesar datos para normalizar tipos numéricos
     */
    private function procesarDatosContenedores($datos) {
        $resultado = [];

        foreach ($datos as $item) {
            $verano   = (float)($item['VERANO']   ?? 0);
            $invierno = (float)($item['INVIERNO'] ?? 0);
            $total    = $verano + $invierno;

            $resultado[] = [
                'CONTENEDOR' => $item['CONTENEDOR'] ?? 'Sin asignar',
                'NOM_PROVEE' => $item['NOM_PROVEE'] ?? '',
                'RUBRO'      => $item['RUBRO']      ?? '',
                'VERANO'     => $verano,
                'INVIERNO'   => $invierno,
                'TOTAL'      => $total
            ];
        }

        return $resultado;
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
