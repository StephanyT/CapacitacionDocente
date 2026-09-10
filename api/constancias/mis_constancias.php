<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

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
    'constancias' => $constancias
]);

?>
