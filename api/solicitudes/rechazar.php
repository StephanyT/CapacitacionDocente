<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('admin', true);
$idAdmin = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json([
        'ok' => false,
        'mensaje' => 'Metodo no permitido.'
    ], 405);
}

$datos = payload_solicitud_request();
rechazar_campos_no_permitidos_solicitud($datos, ['id_solicitud']);

if (!array_key_exists('id_solicitud', $datos)) {
    responder_json([
        'ok' => false,
        'mensaje' => 'El campo id_solicitud es obligatorio.'
    ], 422);
}

$idSolicitud = filter_var($datos['id_solicitud'], FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$idSolicitud) {
    responder_json([
        'ok' => false,
        'mensaje' => 'id_solicitud invalido.'
    ], 422);
}

$idSolicitud = (int) $idSolicitud;
$transaccionIniciada = false;

try {
    if (!$conexion->begin_transaction()) {
        responder_json([
            'ok' => false,
            'mensaje' => 'No se pudo iniciar el rechazo.'
        ], 500);
    }

    $transaccionIniciada = true;

    $sqlSolicitud = 'SELECT id, estado
                     FROM solicitudes_capacitacion
                     WHERE id = ?
                     LIMIT 1
                     FOR UPDATE';
    $stmt = $conexion->prepare($sqlSolicitud);

    if (!$stmt) {
        $conexion->rollback();
        $conexion->close();
        responder_json([
            'ok' => false,
            'mensaje' => 'No se pudo preparar la consulta.'
        ], 500);
    }

    $stmt->bind_param('i', $idSolicitud);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $solicitud = $resultado->fetch_assoc();
    $stmt->close();

    if (!$solicitud) {
        $conexion->rollback();
        $conexion->close();
        responder_json([
            'ok' => false,
            'mensaje' => 'Solicitud no encontrada.'
        ], 404);
    }

    if ($solicitud['estado'] !== 'pendiente') {
        $conexion->rollback();
        $conexion->close();
        responder_json([
            'ok' => false,
            'mensaje' => 'La solicitud ya fue resuelta.'
        ], 409);
    }

    $estadoRechazada = 'rechazada';
    $estadoPendiente = 'pendiente';
    $sqlActualizarSolicitud = 'UPDATE solicitudes_capacitacion
                               SET estado = ?, resuelto_por = ?, fecha_resolucion = CURRENT_DATE
                               WHERE id = ? AND estado = ?';
    $stmt = $conexion->prepare($sqlActualizarSolicitud);

    if (!$stmt) {
        $conexion->rollback();
        $conexion->close();
        responder_json([
            'ok' => false,
            'mensaje' => 'No se pudo preparar la consulta.'
        ], 500);
    }

    $stmt->bind_param('siis', $estadoRechazada, $idAdmin, $idSolicitud, $estadoPendiente);

    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        $stmt->close();
        $conexion->rollback();
        $conexion->close();
        responder_json([
            'ok' => false,
            'mensaje' => 'La solicitud ya fue resuelta.'
        ], 409);
    }

    $stmt->close();

    if (!$conexion->commit()) {
        $conexion->rollback();
        $conexion->close();
        responder_json([
            'ok' => false,
            'mensaje' => 'No se pudo completar el rechazo.'
        ], 500);
    }

    $transaccionIniciada = false;
    $conexion->close();

    responder_json([
        'ok' => true,
        'mensaje' => 'Solicitud rechazada correctamente.',
        'solicitud' => [
            'id' => $idSolicitud
        ]
    ]);
} catch (mysqli_sql_exception $error) {
    if ($transaccionIniciada) {
        $conexion->rollback();
    }

    $conexion->close();

    responder_json([
        'ok' => false,
        'mensaje' => 'No se pudo rechazar la solicitud.'
    ], 500);
}

?>
