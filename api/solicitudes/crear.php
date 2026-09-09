<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json([
        'ok' => false,
        'mensaje' => 'Metodo no permitido.'
    ], 405);
}

$datos = payload_solicitud_request();
rechazar_campos_no_permitidos_solicitud($datos, ['id_capacitacion']);
$idCapacitacion = id_capacitacion_solicitud($datos);

$capacitacion = obtener_capacitacion_activa_solicitud($conexion, $idCapacitacion);

if (!$capacitacion) {
    $conexion->close();
    responder_json([
        'ok' => false,
        'mensaje' => 'Capacitacion no encontrada o inactiva.'
    ], 404);
}

if (existe_inscripcion_docente_capacitacion($conexion, $idDocente, $idCapacitacion)) {
    $conexion->close();
    responder_json([
        'ok' => false,
        'mensaje' => 'Ya tienes una inscripcion en esta capacitacion.'
    ], 409);
}

$solicitudExistente = existe_solicitud_docente_capacitacion($conexion, $idDocente, $idCapacitacion);

if ($solicitudExistente) {
    $conexion->close();
    responder_json([
        'ok' => false,
        'mensaje' => 'Ya existe una solicitud para esta capacitacion.',
        'solicitud' => $solicitudExistente
    ], 409);
}

$sql = 'INSERT INTO solicitudes_capacitacion
        (id_docente, id_capacitacion, estado)
        VALUES (?, ?, ?)';

$estado = 'pendiente';
$stmt = preparar_consulta_solicitudes($conexion, $sql);
$stmt->bind_param('iis', $idDocente, $idCapacitacion, $estado);

try {
    $ejecutado = $stmt->execute();
} catch (mysqli_sql_exception $error) {
    if ((int) $error->getCode() === 1062) {
        $stmt->close();
        $conexion->close();
        responder_json([
            'ok' => false,
            'mensaje' => 'Ya existe una solicitud para esta capacitacion.'
        ], 409);
    }

    $stmt->close();
    $conexion->close();
    responder_json([
        'ok' => false,
        'mensaje' => 'No se pudo crear la solicitud.'
    ], 500);
}

if (!$ejecutado) {
    $codigoError = (int) $conexion->errno;
    $stmt->close();
    $conexion->close();

    if ($codigoError === 1062) {
        responder_json([
            'ok' => false,
            'mensaje' => 'Ya existe una solicitud para esta capacitacion.'
        ], 409);
    }

    responder_json([
        'ok' => false,
        'mensaje' => 'No se pudo crear la solicitud.'
    ], 500);
}

$idSolicitud = $conexion->insert_id;
$stmt->close();

$solicitud = obtener_solicitud_docente_por_id($conexion, $idSolicitud, $idDocente);
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Solicitud creada correctamente.',
    'solicitud' => $solicitud
], 201);

?>
