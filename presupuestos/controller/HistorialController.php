<?php
require_once __DIR__ . '/../class/historial.php';

class HistorialController {
    private $historial;

    public function __construct() {
        $this->historial = new Historial();
    }

    /**
     * Maneja la solicitud para guardar un presupuesto.
     */
    public function guardarPresupuesto() {
        $datos = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonResponse(['success' => false, 'message' => 'Error: JSON inválido.'], 400);
            return;
        }

        $nombrePresupuesto = $datos['nombre_presupuesto'] ?? null;
        $temporada = $datos['temporada'] ?? null;
        $filas = $datos['filas'] ?? [];

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $pais = $_SESSION['pais_seleccionado'] ?? 'argentina';

        if (empty($nombrePresupuesto) || empty($temporada) || empty($filas)) {
            $this->jsonResponse(['success' => false, 'message' => 'Faltan datos requeridos (nombre, temporada, filas).'], 400);
            return;
        }

        try {
            $resultado = $this->historial->guardarPresupuesto($nombrePresupuesto, $temporada, $pais, $filas);
            if ($resultado['success']) {
                $this->jsonResponse($resultado);
            } else {
                $this->jsonResponse($resultado, 500);
            }
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
