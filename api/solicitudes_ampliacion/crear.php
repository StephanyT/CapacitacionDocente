<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$datos = payload_ampliacion_request();
$idInscripcion = filter_var($datos['id_inscripcion'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$motivo = trim((string) ($datos['motivo'] ?? ''));

if (!$idInscripcion) {
    responder_json(['ok' => false, 'mensaje' => 'id_inscripcion invalido.'], 422);
}

if ($motivo === '') {
    responder_json(['ok' => false, 'mensaje' => 'Cuentanos el motivo de tu solicitud.'], 422);
}

$idInscripcion = (int) $idInscripcion;

$inscripcion = obtener_inscripcion_vencida_propia($conexion, $idInscripcion, $idDocente);

if (!$inscripcion) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Inscripcion no encontrada.'], 404);
}

if ($inscripcion['estado'] !== 'vencida') {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Solo puedes pedir ampliacion de una capacitacion vencida.'], 422);
}

if (existe_solicitud_ampliacion_pendiente($conexion, $idInscripcion)) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Ya tienes una solicitud de ampliacion pendiente para esta capacitacion.'], 409);
}

$sql = "INSERT INTO solicitudes_ampliacion (id_inscripcion, motivo, estado, fecha_solicitud)
        VALUES (?, ?, ?, CURRENT_DATE)";
$estado = 'pendiente';
$stmt = preparar_consulta_ampliacion($conexion, $sql);
$stmt->bind_param('iss', $idInscripcion, $motivo, $estado);

if (!$stmt->execute()) {
    $stmt->close();
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'No se pudo crear la solicitud.'], 500);
}

$idSolicitud = (int) $conexion->insert_id;
$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Solicitud de ampliacion enviada. Un administrador la revisara.',
    'solicitud' => ['id' => $idSolicitud]
], 201);

?>
