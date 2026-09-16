<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$datos = payload_evidencia_request();

$id = filter_var($datos['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$estado = trim((string) ($datos['estado'] ?? ''));

if (!$id) {
    responder_json(['ok' => false, 'mensaje' => 'ID invalido.'], 422);
}

if (!in_array($estado, ['aprobada', 'rechazada'], true)) {
    responder_json(['ok' => false, 'mensaje' => 'Estado invalido.'], 422);
}

$id = (int) $id;

$sqlExiste = 'SELECT id FROM evidencias_insignia WHERE id = ? LIMIT 1';
$stmt = preparar_consulta_evidencias($conexion, $sqlExiste);
$stmt->bind_param('i', $id);
$stmt->execute();
$existe = (bool) $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existe) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Evidencia no encontrada.'], 404);
}

$sqlUpdate = 'UPDATE evidencias_insignia SET estado = ? WHERE id = ?';
$stmt = preparar_consulta_evidencias($conexion, $sqlUpdate);
$stmt->bind_param('si', $estado, $id);
$stmt->execute();
$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => $estado === 'aprobada' ? 'Evidencia aprobada — insignia otorgada.' : 'Evidencia rechazada.',
    'evidencia' => ['id' => $id, 'estado' => $estado]
]);

?>
