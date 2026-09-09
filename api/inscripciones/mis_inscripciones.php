<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

$sql = 'SELECT
            i.id,
            i.estado,
            i.fecha_inscripcion,
            i.fecha_limite,
            i.fecha_actualizacion,
            c.id AS capacitacion_id,
            c.titulo AS capacitacion_titulo,
            c.fecha AS capacitacion_fecha,
            c.duracion AS capacitacion_duracion,
            c.sede AS capacitacion_sede,
            c.programa AS capacitacion_programa
        FROM inscripciones i
        INNER JOIN capacitaciones c ON c.id = i.id_capacitacion
        WHERE i.id_docente = ?
        ORDER BY i.fecha_limite IS NULL, i.fecha_limite ASC, i.id ASC';

$stmt = preparar_consulta_inscripciones($conexion, $sql);
$stmt->bind_param('i', $idDocente);
$stmt->execute();
$resultado = $stmt->get_result();
$inscripciones = [];

while ($fila = $resultado->fetch_assoc()) {
    $inscripciones[] = normalizar_inscripcion_docente($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'inscripciones' => $inscripciones
]);

?>
