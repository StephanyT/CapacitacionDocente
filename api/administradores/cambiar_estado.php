<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$payload = payload_administrador_request();
$id = id_request_administrador($payload);

$administrador = obtener_administrador_por_id($conexion, $id);

if (!$administrador) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Administrador no encontrado.'], 404);
}

$activoPayload = filter_var($payload['activo'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

if ($activoPayload === null) {
    responder_json(['ok' => false, 'mensaje' => 'Estado invalido.'], 422);
}

if (!$activoPayload) {
    $motivo = texto_opcional_administrador($payload, 'motivo_baja', 100);

    if (!$motivo) {
        responder_json(['ok' => false, 'mensaje' => 'El motivo de la baja es obligatorio.'], 422);
    }

    if (!$conexion->begin_transaction()) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo iniciar la actualizacion.'], 500);
    }

    // Regla de seguridad: no se puede desactivar al ultimo administrador
    // activo, o nadie podria volver a entrar al panel admin. El conteo y el
    // UPDATE van dentro de la misma transaccion con bloqueo de fila para
    // evitar que dos desactivaciones simultaneas dejen el conteo en 0.
    $sqlConteo = 'SELECT COUNT(*) AS total FROM administradores WHERE activo = 1 FOR UPDATE';
    $resultadoConteo = $conexion->query($sqlConteo);
    $filaConteo = $resultadoConteo->fetch_assoc();

    if ($administrador['activo'] && (int) $filaConteo['total'] <= 1) {
        $conexion->rollback();
        $conexion->close();
        responder_json(['ok' => false, 'mensaje' => 'Debe quedar al menos un administrador activo.'], 409);
    }

    $sql = 'UPDATE administradores SET activo = 0, motivo_baja = ?, fecha_baja = CURRENT_DATE WHERE id = ?';
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        $conexion->rollback();
        responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
    }

    $stmt->bind_param('si', $motivo, $id);
} else {
    $sql = 'UPDATE administradores SET activo = 1, motivo_baja = NULL, fecha_baja = NULL WHERE id = ?';
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
    }

    $stmt->bind_param('i', $id);
}

$stmt->execute();
$stmt->close();

if (!$activoPayload) {
    if (!$conexion->commit()) {
        $conexion->rollback();
        $conexion->close();
        responder_json(['ok' => false, 'mensaje' => 'No se pudo completar la actualizacion.'], 500);
    }
}

$administrador = obtener_administrador_por_id($conexion, $id);
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => $activoPayload ? 'Administrador reactivado.' : 'Administrador desactivado.',
    'administrador' => $administrador
]);

?>
