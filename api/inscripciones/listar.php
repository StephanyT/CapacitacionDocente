<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

// Antes de listar, se pasan a "vencida" las inscripciones cuyo plazo ya
// paso (ver comentario junto a la funcion en _helpers.php).
actualizar_inscripciones_vencidas($conexion);

$sql = 'SELECT
            i.id,
            i.estado,
            i.fecha_inscripcion,
            i.fecha_limite,
            i.fecha_actualizacion,
            i.actualizado_por,
            a.nombres AS actualizado_por_nombres,
            a.apellidos AS actualizado_por_apellidos,
            d.id AS docente_id,
            d.nombres AS docente_nombres,
            d.apellidos AS docente_apellidos,
            d.correo AS docente_correo,
            c.id AS capacitacion_id,
            c.titulo AS capacitacion_titulo
        FROM inscripciones i
        INNER JOIN docentes d ON d.id = i.id_docente
        INNER JOIN capacitaciones c ON c.id = i.id_capacitacion
        LEFT JOIN administradores a ON a.id = i.actualizado_por
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
