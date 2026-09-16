<?php

require_once __DIR__ . '/_helpers.php';

$usuarioActual = exigir_rol('admin', true);
$idAdmin = (int) $usuarioActual['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$datos = payload_ampliacion_request();
$idSolicitud = filter_var($datos['id_solicitud'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$motivoRechazo = trim((string) ($datos['motivo_rechazo'] ?? ''));

if (!$idSolicitud) {
    responder_json(['ok' => false, 'mensaje' => 'id_solicitud invalido.'], 422);
}

$idSolicitud = (int) $idSolicitud;
$transaccionIniciada = false;

try {
    if (!$conexion->begin_transaction()) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo iniciar el rechazo.'], 500);
    }
    $transaccionIniciada = true;

    $sqlSolicitud = 'SELECT id, estado FROM solicitudes_ampliacion WHERE id = ? LIMIT 1 FOR UPDATE';
    $stmt = $conexion->prepare($sqlSolicitud);
    $stmt->bind_param('i', $idSolicitud);
    $stmt->execute();
    $solicitud = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$solicitud) {
        $conexion->rollback();
        $conexion->close();
        responder_json(['ok' => false, 'mensaje' => 'Solicitud no encontrada.'], 404);
    }

    if ($solicitud['estado'] !== 'pendiente') {
        $conexion->rollback();
        $conexion->close();
        responder_json(['ok' => false, 'mensaje' => 'La solicitud ya fue resuelta.'], 409);
    }

    // La inscripcion NO se toca -- permanece "vencida" (regla del documento:
    // rechazar solo cierra la solicitud, el historial se conserva).
    $estadoRechazada = 'rechazada';
    $estadoPendiente = 'pendiente';
    $motivoRechazoValor = $motivoRechazo !== '' ? $motivoRechazo : null;
    $sqlUpdate = "UPDATE solicitudes_ampliacion
                  SET estado = ?, resuelto_por = ?, fecha_resolucion = CURRENT_DATE, motivo_rechazo = ?
                  WHERE id = ? AND estado = ?";
    $stmt = $conexion->prepare($sqlUpdate);
    $stmt->bind_param('sisis', $estadoRechazada, $idAdmin, $motivoRechazoValor, $idSolicitud, $estadoPendiente);

    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        $stmt->close();
        $conexion->rollback();
        $conexion->close();
        responder_json(['ok' => false, 'mensaje' => 'La solicitud ya fue resuelta.'], 409);
    }
    $stmt->close();

    if (!$conexion->commit()) {
        $conexion->rollback();
        $conexion->close();
        responder_json(['ok' => false, 'mensaje' => 'No se pudo completar el rechazo.'], 500);
    }

    $transaccionIniciada = false;
    $conexion->close();

    responder_json([
        'ok' => true,
        'mensaje' => 'Solicitud rechazada correctamente.',
        'solicitud' => ['id' => $idSolicitud]
    ]);
} catch (mysqli_sql_exception $error) {
    if ($transaccionIniciada) {
        $conexion->rollback();
    }
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'No se pudo rechazar la solicitud.'], 500);
}

?>
