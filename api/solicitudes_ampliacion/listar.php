<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

$sql = "SELECT
            s.id, s.estado, s.motivo, s.motivo_rechazo,
            s.fecha_solicitud, s.fecha_resolucion, s.nueva_fecha_limite,
            i.id AS inscripcion_id, i.estado AS inscripcion_estado, i.fecha_limite,
            d.id AS docente_id, d.nombres AS docente_nombres, d.apellidos AS docente_apellidos, d.correo AS docente_correo,
            c.id AS capacitacion_id, c.titulo AS capacitacion_titulo
        FROM solicitudes_ampliacion s
        INNER JOIN inscripciones i ON i.id = s.id_inscripcion
        INNER JOIN docentes d ON d.id = i.id_docente
        INNER JOIN capacitaciones c ON c.id = i.id_capacitacion
        ORDER BY s.fecha_solicitud DESC, s.id DESC";

$stmt = preparar_consulta_ampliacion($conexion, $sql);
$stmt->execute();
$resultado = $stmt->get_result();
$solicitudes = [];

while ($fila = $resultado->fetch_assoc()) {
    $solicitudes[] = [
        'id' => (int) $fila['id'],
        'estado' => $fila['estado'],
        'motivo' => $fila['motivo'],
        'motivo_rechazo' => $fila['motivo_rechazo'],
        'fecha_solicitud' => $fila['fecha_solicitud'],
        'fecha_resolucion' => $fila['fecha_resolucion'],
        'nueva_fecha_limite' => $fila['nueva_fecha_limite'],
        'inscripcion' => [
            'id' => (int) $fila['inscripcion_id'],
            'estado' => $fila['inscripcion_estado'],
            'fecha_limite' => $fila['fecha_limite']
        ],
        'docente' => [
            'id' => (int) $fila['docente_id'],
            'nombres' => $fila['docente_nombres'],
            'apellidos' => $fila['docente_apellidos'],
            'correo' => $fila['docente_correo']
        ],
        'capacitacion' => [
            'id' => (int) $fila['capacitacion_id'],
            'titulo' => $fila['capacitacion_titulo']
        ]
    ];
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'solicitudes' => $solicitudes
]);

?>
