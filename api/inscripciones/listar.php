<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

$sql = 'SELECT
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
        ORDER BY i.fecha_inscripcion DESC, i.id DESC';

$stmt = preparar_consulta_inscripciones($conexion, $sql);
$stmt->execute();
$resultado = $stmt->get_result();
$inscripciones = [];

while ($fila = $resultado->fetch_assoc()) {
    $inscripciones[] = normalizar_inscripcion_admin($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'inscripciones' => $inscripciones
]);

?>
