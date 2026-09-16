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

try {
    if (!$conexion->begin_transaction()) {
        responder_json([
            'ok' => false,
            'mensaje' => 'No se pudo iniciar la aprobacion.'
        ], 500);
    }

    $sqlSolicitud = 'SELECT id, id_docente, id_capacitacion, estado
                     FROM solicitudes_capacitacion
                     WHERE id = ?
                     LIMIT 1
                     FOR UPDATE';
    $stmt = $conexion->prepare($sqlSolicitud);

    if (!$stmt) {
        $conexion->rollback();
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

    $idDocente = (int) $solicitud['id_docente'];
    $idCapacitacion = (int) $solicitud['id_capacitacion'];

    $sqlInscripcionExistente = 'SELECT id
                                FROM inscripciones
                                WHERE id_docente = ? AND id_capacitacion = ?
                                LIMIT 1
                                FOR UPDATE';
    $stmt = $conexion->prepare($sqlInscripcionExistente);

    if (!$stmt) {
        $conexion->rollback();
        responder_json([
            'ok' => false,
            'mensaje' => 'No se pudo preparar la consulta.'
        ], 500);
    }

    $stmt->bind_param('ii', $idDocente, $idCapacitacion);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $inscripcionExistente = $resultado->fetch_assoc();
    $stmt->close();

    if ($inscripcionExistente) {
        $conexion->rollback();
        $conexion->close();
        responder_json([
            'ok' => false,
            'mensaje' => 'Ya existe una inscripcion para esta solicitud.'
        ], 409);
    }

    $estadoInscripcion = 'pendiente';
    $sqlCrearInscripcion = 'INSERT INTO inscripciones
                            (id_docente, id_capacitacion, estado)
                            VALUES (?, ?, ?)';
    $stmt = $conexion->prepare($sqlCrearInscripcion);

    if (!$stmt) {
        $conexion->rollback();
        responder_json([
            'ok' => false,
            'mensaje' => 'No se pudo preparar la consulta.'
        ], 500);
    }

    $stmt->bind_param('iis', $idDocente, $idCapacitacion, $estadoInscripcion);

    if (!$stmt->execute()) {
        $codigoError = (int) $stmt->errno;
        $stmt->close();
        $conexion->rollback();
        $conexion->close();

        if ($codigoError === 1062) {
            responder_json([
                'ok' => false,
                'mensaje' => 'Ya existe una inscripcion para esta solicitud.'
            ], 409);
        }

        responder_json([
            'ok' => false,
            'mensaje' => 'No se pudo crear la inscripcion.'
        ], 500);
    }

    $idInscripcion = (int) $conexion->insert_id;
    $stmt->close();

    $estadoAprobada = 'aprobada';
    $sqlActualizarSolicitud = 'UPDATE solicitudes_capacitacion
                               SET estado = ?, resuelto_por = ?, fecha_resolucion = CURRENT_DATE
                               WHERE id = ? AND estado = ?';
    $stmt = $conexion->prepare($sqlActualizarSolicitud);

    if (!$stmt) {
        $conexion->rollback();
        responder_json([
            'ok' => false,
            'mensaje' => 'No se pudo preparar la consulta.'
        ], 500);
    }

    $estadoPendiente = 'pendiente';
    $stmt->bind_param('siis', $estadoAprobada, $idAdmin, $idSolicitud, $estadoPendiente);

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
            'mensaje' => 'No se pudo completar la aprobacion.'
        ], 500);
    }

    $conexion->close();

    responder_json([
        'ok' => true,
        'mensaje' => 'Solicitud aprobada correctamente.',
        'solicitud' => [
            'id' => $idSolicitud
        ],
        'inscripcion' => [
            'id' => $idInscripcion
        ]
    ]);
} catch (mysqli_sql_exception $error) {
    if ($conexion->errno) {
        $conexion->rollback();
    }

    $codigoError = (int) $error->getCode();
    $conexion->close();

    if ($codigoError === 1062) {
        responder_json([
            'ok' => false,
            'mensaje' => 'Ya existe una inscripcion para esta solicitud.'
        ], 409);
    }

    responder_json([
        'ok' => false,
        'mensaje' => 'No se pudo aprobar la solicitud.'
    ], 500);
}

?>
