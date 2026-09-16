<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

$sql = 'SELECT
            s.id, s.estado, s.motivo, s.motivo_rechazo,
            s.fecha_solicitud, s.fecha_resolucion, s.nueva_fecha_limite,
            i.id AS inscripcion_id, i.estado AS inscripcion_estado, i.fecha_limite,
            c.id AS capacitacion_id, c.titulo AS capacitacion_titulo
        FROM solicitudes_ampliacion s
        INNER JOIN inscripciones i ON i.id = s.id_inscripcion
        INNER JOIN capacitaciones c ON c.id = i.id_capacitacion
        WHERE i.id_docente = ?
        ORDER BY s.fecha_solicitud DESC, s.id DESC';

$stmt = preparar_consulta_ampliacion($conexion, $sql);
$stmt->bind_param('i', $idDocente);
$stmt->execute();
$resultado = $stmt->get_result();
$solicitudes = [];

while ($fila = $resultado->fetch_assoc()) {
    $solicitudes[] = normalizar_solicitud_ampliacion_docente($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'solicitudes' => $solicitudes
]);

?>
