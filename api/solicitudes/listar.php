<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

$sql = 'SELECT
            s.id,
            s.estado,
            s.fecha_solicitud,
            s.fecha_resolucion,
            u.id AS docente_id,
            u.nombres AS docente_nombres,
            u.apellidos AS docente_apellidos,
            u.correo AS docente_correo,
            c.id AS capacitacion_id,
            c.titulo AS capacitacion_titulo
        FROM solicitudes_capacitacion s
        INNER JOIN usuarios u ON u.id = s.id_docente
        INNER JOIN capacitaciones c ON c.id = s.id_capacitacion
        ORDER BY s.fecha_solicitud DESC, s.id DESC';

$stmt = preparar_consulta_solicitudes($conexion, $sql);
$stmt->execute();
$resultado = $stmt->get_result();
$solicitudes = [];

while ($fila = $resultado->fetch_assoc()) {
    $solicitudes[] = [
        'id' => (int) $fila['id'],
        'estado' => $fila['estado'],
        'fecha_solicitud' => $fila['fecha_solicitud'],
        'fecha_resolucion' => $fila['fecha_resolucion'],
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
