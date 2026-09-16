<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$datos = payload_sugerencia_request();
$id = filter_var($datos['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$id) {
    responder_json(['ok' => false, 'mensaje' => 'ID invalido.'], 422);
}

$id = (int) $id;
$estado = 'atendida';
$sqlUpdate = 'UPDATE sugerencias_capacitacion SET estado = ? WHERE id = ?';
$stmt = preparar_consulta_sugerencias($conexion, $sqlUpdate);
$stmt->bind_param('si', $estado, $id);
$stmt->execute();
$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Sugerencia marcada como atendida.',
    'sugerencia' => ['id' => $id, 'estado' => $estado]
]);

?>
