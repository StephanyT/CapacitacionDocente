<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json([
        'ok' => false,
        'mensaje' => 'Metodo no permitido.'
    ], 405);
}

$raw = file_get_contents('php://input');
$datos = json_decode($raw, true);

if (!is_array($datos)) {
    $datos = $_POST;
}

$idInscripcion = filter_var($datos['id_inscripcion'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$idInscripcion) {
    responder_json([
        'ok' => false,
        'mensaje' => 'id_inscripcion invalido.'
    ], 422);
}

$estado = trim((string) ($datos['estado'] ?? ''));
$estadosPermitidos = ['pendiente', 'en curso', 'completada'];

if (!in_array($estado, $estadosPermitidos, true)) {
    responder_json([
        'ok' => false,
        'mensaje' => 'estado invalido.'
    ], 422);
}

$sqlBuscar = 'SELECT
                  i.id,
                  i.estado,
                  i.fecha_inscripcion,
                  i.fecha_limite,
                  i.fecha_actualizacion,
                  u.id AS docente_id,
                  u.nombres AS docente_nombres,
                  u.apellidos AS docente_apellidos,
                  u.correo AS docente_correo,
                  c.id AS capacitacion_id,
                  c.titulo AS capacitacion_titulo
              FROM inscripciones i
              INNER JOIN usuarios u ON u.id = i.id_docente
              INNER JOIN capacitaciones c ON c.id = i.id_capacitacion
              WHERE i.id = ?
              LIMIT 1';

$stmt = preparar_consulta_inscripciones($conexion, $sqlBuscar);
$stmt->bind_param('i', $idInscripcion);
$stmt->execute();
$resultado = $stmt->get_result();
$inscripcion = $resultado->fetch_assoc();
$stmt->close();

if (!$inscripcion) {
    $conexion->close();
    responder_json([
        'ok' => false,
        'mensaje' => 'Inscripcion no encontrada.'
    ], 404);
}

$sqlActualizar = 'UPDATE inscripciones
                  SET estado = ?
                  WHERE id = ?';
$stmt = preparar_consulta_inscripciones($conexion, $sqlActualizar);
$stmt->bind_param('si', $estado, $idInscripcion);

if (!$stmt->execute()) {
    $stmt->close();
    $conexion->close();
    responder_json([
        'ok' => false,
        'mensaje' => 'No se pudo actualizar la inscripcion.'
    ], 500);
}

$stmt->close();

$stmt = preparar_consulta_inscripciones($conexion, $sqlBuscar);
$stmt->bind_param('i', $idInscripcion);
$stmt->execute();
$resultado = $stmt->get_result();
$inscripcionActualizada = $resultado->fetch_assoc();
$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Estado de inscripcion actualizado correctamente.',
    'inscripcion' => normalizar_inscripcion_admin($inscripcionActualizada)
]);

?>
