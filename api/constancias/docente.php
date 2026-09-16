<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if (array_diff(array_keys($_GET), ['id_docente'])) {
    responder_json([
        'ok' => false,
        'mensaje' => 'La solicitud contiene campos no permitidos.'
    ], 422);
}

$idDocente = filter_input(INPUT_GET, 'id_docente', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$idDocente) {
    responder_json([
        'ok' => false,
        'mensaje' => 'id_docente invalido.'
    ], 422);
}

$stmtDocente = preparar_consulta_constancias(
    $conexion,
    "SELECT id, nombres, apellidos
     FROM usuarios
     WHERE id = ? AND rol = 'docente'
     LIMIT 1"
);
$stmtDocente->bind_param('i', $idDocente);
$stmtDocente->execute();
$resultadoDocente = $stmtDocente->get_result();
$docente = $resultadoDocente->fetch_assoc();
$stmtDocente->close();

if (!$docente) {
    responder_json([
        'ok' => false,
        'mensaje' => 'Docente no encontrado.'
    ], 404);
}

$sql = "SELECT
            i.id AS inscripcion_id,
            i.fecha_inscripcion,
            i.fecha_limite,
            DATE(i.fecha_actualizacion) AS fecha_finalizacion,
            u.id AS docente_id,
            u.nombres AS docente_nombres,
            u.apellidos AS docente_apellidos,
            c.titulo AS capacitacion_titulo,
            c.duracion AS capacitacion_duracion,
            c.sede AS capacitacion_sede,
            c.programa AS capacitacion_programa
        FROM inscripciones i
        INNER JOIN usuarios u ON u.id = i.id_docente
        INNER JOIN capacitaciones c ON c.id = i.id_capacitacion
        WHERE i.id_docente = ?
          AND i.estado = 'completada'
          AND u.rol = 'docente'
        ORDER BY i.fecha_actualizacion DESC, i.id DESC";

$stmt = preparar_consulta_constancias($conexion, $sql);
$stmt->bind_param('i', $idDocente);
$stmt->execute();
$resultado = $stmt->get_result();
$constancias = [];

while ($fila = $resultado->fetch_assoc()) {
    $constancias[] = normalizar_constancia($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'docente' => [
        'id' => (int) $docente['id'],
        'nombre_completo' => trim($docente['nombres'] . ' ' . $docente['apellidos'])
    ],
    'constancias' => $constancias
]);

?>
