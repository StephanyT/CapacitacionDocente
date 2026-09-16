<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json([
        'ok' => false,
        'mensaje' => 'Metodo no permitido.'
    ], 405);
}

$payload = payload_request();
$id = id_request($payload);

$activoPayload = $payload['activo'] ?? null;
$estadoPayload = $payload['estado'] ?? null;

if ($estadoPayload !== null) {
    if (!in_array($estadoPayload, ['activa', 'inactiva'], true)) {
        responder_json([
            'ok' => false,
            'mensaje' => 'Estado invalido.'
        ], 422);
    }
    $activo = $estadoPayload === 'activa' ? 1 : 0;
} else {
    $activo = filter_var($activoPayload, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($activo === null) {
        responder_json([
            'ok' => false,
            'mensaje' => 'Estado invalido.'
        ], 422);
    }
    $activo = $activo ? 1 : 0;
}

$estado = $activo ? 'activa' : 'inactiva';

if (!obtener_capacitacion_por_id($conexion, $id, false)) {
    $conexion->close();
    responder_json([
        'ok' => false,
        'mensaje' => 'Capacitacion no encontrada.'
    ], 404);
}

$stmt = $conexion->prepare('UPDATE capacitaciones SET activo = ?, estado = ? WHERE id = ?');

if (!$stmt) {
    responder_json([
        'ok' => false,
        'mensaje' => 'No se pudo preparar la consulta.'
    ], 500);
}

$stmt->bind_param('isi', $activo, $estado, $id);
$stmt->execute();
$stmt->close();

$capacitacion = obtener_capacitacion_por_id($conexion, $id, false);
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => $activo ? 'Capacitacion reactivada.' : 'Capacitacion desactivada.',
    'capacitacion' => $capacitacion
]);

?>
