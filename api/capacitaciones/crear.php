<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json([
        'ok' => false,
        'mensaje' => 'Metodo no permitido.'
    ], 405);
}

$datos = datos_capacitacion_validados(payload_request());

$sql = 'INSERT INTO capacitaciones
        (titulo, fecha, duracion, sede, programa, descripcion, instructor,
         objetivos, temario, habilidades, instructor_bio, requisitos, estado, activo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    responder_json([
        'ok' => false,
        'mensaje' => 'No se pudo preparar la consulta.'
    ], 500);
}

$stmt->bind_param(
    'sssssssssssssi',
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
    $datos['activo']
);
$stmt->execute();
$id = $conexion->insert_id;
$stmt->close();

$capacitacion = obtener_capacitacion_por_id($conexion, $id, false);
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Capacitacion creada correctamente.',
    'capacitacion' => $capacitacion
], 201);

?>
