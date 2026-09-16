<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$payload = payload_docente_request();
$id = id_request_docente($payload);

if (!obtener_docente_por_id($conexion, $id)) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Docente no encontrado.'], 404);
}

$activoPayload = filter_var($payload['activo'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

if ($activoPayload === null) {
    responder_json(['ok' => false, 'mensaje' => 'Estado invalido.'], 422);
}

if ($activoPayload) {
    $sql = 'UPDATE docentes SET activo = 1, motivo_baja = NULL, fecha_baja = NULL WHERE id = ?';
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
    }

    $stmt->bind_param('i', $id);
} else {
    $motivo = texto_opcional_docente($payload, 'motivo_baja', 100);

    if (!$motivo) {
        responder_json(['ok' => false, 'mensaje' => 'El motivo de la baja es obligatorio.'], 422);
    }

    $sql = 'UPDATE docentes SET activo = 0, motivo_baja = ?, fecha_baja = CURRENT_DATE WHERE id = ?';
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
    }

    $stmt->bind_param('si', $motivo, $id);
}

$stmt->execute();
$stmt->close();

$docente = obtener_docente_por_id($conexion, $id);
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => $activoPayload ? 'Docente reactivado.' : 'Docente desactivado.',
    'docente' => $docente
]);

?>
