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
$datos = datos_capacitacion_validados($payload);

if (!obtener_capacitacion_por_id($conexion, $id, false)) {
    $conexion->close();
    responder_json([
        'ok' => false,
        'mensaje' => 'Capacitacion no encontrada.'
    ], 404);
}

$sql = 'UPDATE capacitaciones
        SET titulo = ?, fecha = ?, duracion = ?, sede = ?, programa = ?,
            descripcion = ?, instructor = ?, objetivos = ?, temario = ?,
            habilidades = ?, instructor_bio = ?, requisitos = ?, estado = ?,
            activo = ?
        WHERE id = ?';

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    responder_json([
        'ok' => false,
        'mensaje' => 'No se pudo preparar la consulta.'
    ], 500);
}

$stmt->bind_param(
    'sssssssssssssii',
    $datos['titulo'],
    $datos['fecha'],
    $datos['duracion'],
    $datos['sede'],
    $datos['programa'],
    $datos['descripcion'],
    $datos['instructor'],
    $datos['objetivos'],
    $datos['temario'],
    $datos['habilidades'],
    $datos['instructor_bio'],
    $datos['requisitos'],
    $datos['estado'],
    $datos['activo'],
    $id
);
$stmt->execute();
$stmt->close();

$capacitacion = obtener_capacitacion_por_id($conexion, $id, false);
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Capacitacion actualizada correctamente.',
    'capacitacion' => $capacitacion
]);

?>
