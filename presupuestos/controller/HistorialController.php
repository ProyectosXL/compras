<?php
require_once __DIR__ . '/../class/historial.php';

class HistorialController {
    private $historial;

    public function __construct() {
        $this->historial = new Historial();
    }

    /**
     * Maneja la solicitud para guardar un presupuesto.
     *
     * El pais, la fecha y los periodos NO se toman del payload: los resuelve el
     * servidor. La fecha venia del reloj del navegador y es contra la que se
     * prorratean los restos de temporada, asi que una maquina desfasada dejaba
     * guardada una version que no se podia reproducir.
     */
    public function guardarPresupuesto() {
        $datos = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonResponse(['success' => false, 'message' => 'Error: JSON inválido.'], 400);
            return;
        }

        if (empty($datos['nombre_presupuesto']) || empty($datos['temporada']) || empty($datos['filas'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Faltan datos requeridos (nombre, temporada, filas).'], 400);
            return;
        }

        try {
            $resultado = $this->historial->guardarPresupuesto($datos);
            $this->jsonResponse($resultado, $resultado['success'] ? 200 : 500);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Maneja la solicitud para buscar en el historial.
     */
    public function buscarHistorial() {
        $filtros = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonResponse(['success' => false, 'message' => 'Error: JSON inválido.'], 400);
            return;
        }

        try {
            $resultado = $this->historial->buscarHistorial($filtros ?: []);
            $this->jsonResponse($resultado, $resultado['success'] ? 200 : 500);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Lista las versiones guardadas: una fila por version, no por rubro.
     * Es la vista sobre la que se marca la oficial.
     */
    public function listarVersiones() {
        try {
            $filtros = [
                'temporada_objetivo' => $_GET['temporada_objetivo'] ?? null,
                'solo_oficiales'     => !empty($_GET['solo_oficiales'])
            ];
            $resultado = $this->historial->listarVersiones($filtros);
            $this->jsonResponse($resultado, $resultado['success'] ? 200 : 500);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Marca una version como oficial.
     *
     * Dos pasos a proposito: la primera llamada, sin `confirmado`, no escribe y
     * devuelve cual version se va a desmarcar para que la UI lo muestre. Recien
     * la segunda aplica el cambio. Asi la confirmacion la decide el servidor con
     * el dato real y no el front con lo que tenia en pantalla.
     */
    public function marcarOficial() {
        $datos = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonResponse(['success' => false, 'message' => 'Error: JSON inválido.'], 400);
            return;
        }

        $idCabecera = (int)($datos['id_cabecera'] ?? 0);
        if ($idCabecera <= 0) {
            $this->jsonResponse(['success' => false, 'message' => 'Falta el id de la versión.'], 400);
            return;
        }

        try {
            $resultado = $this->historial->marcarOficial($idCabecera, !empty($datos['confirmado']));
            $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Desmarca la version oficial, sin poner otra en su lugar.
     *
     * Mismo esquema de dos pasos que marcarOficial(). Existe porque la version
     * oficial ya no se puede eliminar: sin un desmarcado explicito, la regla la
     * dejaba atrapada sin salida.
     */
    public function desmarcarOficial() {
        $datos = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonResponse(['success' => false, 'message' => 'Error: JSON inválido.'], 400);
            return;
        }

        $idCabecera = (int)($datos['id_cabecera'] ?? 0);
        if ($idCabecera <= 0) {
            $this->jsonResponse(['success' => false, 'message' => 'Falta el id de la versión.'], 400);
            return;
        }

        try {
            $resultado = $this->historial->desmarcarOficial($idCabecera, !empty($datos['confirmado']));
            $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Elimina una version guardada.
     *
     * Dos pasos como marcarOficial: la primera llamada no borra y devuelve
     * cuantas filas se llevaria y si es la oficial, para poder confirmarlo con
     * el dato real. Es la unica operacion destructiva del modulo.
     */
    public function eliminarVersion() {
        $datos = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonResponse(['success' => false, 'message' => 'Error: JSON inválido.'], 400);
            return;
        }

        $idCabecera = isset($datos['id_cabecera']) ? (int)$datos['id_cabecera'] : null;
        $nombre = $datos['nombre_presupuesto'] ?? null;

        if (!$idCabecera && empty($nombre)) {
            $this->jsonResponse(['success' => false, 'message' => 'Falta indicar qué versión eliminar.'], 400);
            return;
        }

        try {
            $resultado = $this->historial->eliminarVersion(
                $idCabecera, $nombre, !empty($datos['confirmado']), $datos['motivo'] ?? null);
            $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    /** Quien marco que version como oficial y cuando. */
    public function historialOficial() {
        try {
            $resultado = $this->historial->historialOficial($_GET['temporada_objetivo'] ?? null);
            $this->jsonResponse($resultado, $resultado['success'] ? 200 : 500);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    private function jsonResponse($data, $httpCode = 200) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
