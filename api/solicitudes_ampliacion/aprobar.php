<?php

require_once __DIR__ . '/_helpers.php';

$usuarioActual = exigir_rol('admin', true);
$idAdmin = (int) $usuarioActual['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$datos = payload_ampliacion_request();
$idSolicitud = filter_var($datos['id_solicitud'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$nuevaFechaLimite = trim((string) ($datos['nueva_fecha_limite'] ?? ''));

if (!$idSolicitud) {
    responder_json(['ok' => false, 'mensaje' => 'id_solicitud invalido.'], 422);
}

$fechaValida = DateTime::createFromFormat('Y-m-d', $nuevaFechaLimite);
if (!$fechaValida || $fechaValida->format('Y-m-d') !== $nuevaFechaLimite) {
    responder_json(['ok' => false, 'mensaje' => 'Elige una nueva fecha limite valida.'], 422);
}

$idSolicitud = (int) $idSolicitud;
$transaccionIniciada = false;

try {
    if (!$conexion->begin_transaction()) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo iniciar la aprobacion.'], 500);
    }
    $transaccionIniciada = true;

    $sqlSolicitud = 'SELECT id, id_inscripcion, estado
                     FROM solicitudes_ampliacion
                     WHERE id = ? LIMIT 1 FOR UPDATE';
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

    $idInscripcion = (int) $solicitud['id_inscripcion'];

    $sqlInscripcion = "SELECT id, estado FROM inscripciones WHERE id = ? LIMIT 1 FOR UPDATE";
    $stmt = $conexion->prepare($sqlInscripcion);
    $stmt->bind_param('i', $idInscripcion);
    $stmt->execute();
    $inscripcion = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inscripcion) {
        $conexion->rollback();
        $conexion->close();
        responder_json(['ok' => false, 'mensaje' => 'La inscripcion de esta solicitud ya no existe.'], 404);
    }

    // Regla del documento: al aprobar, la capacitacion "vuelve a estar
    // activa" con el progreso conservado -- se traduce a "en curso" en nuestro
    // enum real (pendiente/en curso/completada/vencida). No se toca si por
    // alguna razon ya no esta vencida (evita pisar un cambio manual del
    // admin hecho mientras la solicitud seguia pendiente).
    if ($inscripcion['estado'] === 'vencida') {
        $nuevoEstado = 'en curso';
        $sqlUpdateInscripcion = 'UPDATE inscripciones
                                 SET estado = ?, fecha_limite = ?, actualizado_por = ?
                                 WHERE id = ?';
        $stmt = $conexion->prepare($sqlUpdateInscripcion);
        $stmt->bind_param('ssii', $nuevoEstado, $nuevaFechaLimite, $idAdmin, $idInscripcion);
        $stmt->execute();
        $stmt->close();
    } else {
        // Ya no esta vencida (por ejemplo el admin la reabrio a mano antes
        // de resolver la solicitud) -- se respeta su estado actual, pero
        // igual se aplica la nueva fecha que el admin acaba de aprobar.
        $sqlUpdateFecha = 'UPDATE inscripciones
                            SET fecha_limite = ?, actualizado_por = ?
                            WHERE id = ?';
        $stmt = $conexion->prepare($sqlUpdateFecha);
        $stmt->bind_param('sii', $nuevaFechaLimite, $idAdmin, $idInscripcion);
        $stmt->execute();
        $stmt->close();
    }

    $estadoAprobada = 'aprobada';
    $estadoPendiente = 'pendiente';
    $sqlUpdateSolicitud = "UPDATE solicitudes_ampliacion
                           SET estado = ?, resuelto_por = ?, fecha_resolucion = CURRENT_DATE, nueva_fecha_limite = ?
                           WHERE id = ? AND estado = ?";
    $stmt = $conexion->prepare($sqlUpdateSolicitud);
    $stmt->bind_param('sisis', $estadoAprobada, $idAdmin, $nuevaFechaLimite, $idSolicitud, $estadoPendiente);

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
        responder_json(['ok' => false, 'mensaje' => 'No se pudo completar la aprobacion.'], 500);
    }

    $transaccionIniciada = false;
    $conexion->close();

    responder_json([
        'ok' => true,
        'mensaje' => 'Solicitud aprobada. Se reprogramo el plazo y la capacitacion vuelve a estar activa.',
        'solicitud' => ['id' => $idSolicitud]
    ]);
} catch (mysqli_sql_exception $error) {
    if ($transaccionIniciada) {
        $conexion->rollback();
    }
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'No se pudo aprobar la solicitud.'], 500);
}

?>
