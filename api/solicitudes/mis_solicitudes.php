<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

$sql = 'SELECT
            s.id,
            s.estado,
            s.fecha_solicitud,
            s.fecha_resolucion,
            c.id AS capacitacion_id,
            c.titulo AS capacitacion_titulo
        FROM solicitudes_capacitacion s
        INNER JOIN capacitaciones c ON c.id = s.id_capacitacion
        WHERE s.id_docente = ?
        ORDER BY s.fecha_solicitud DESC, s.id DESC';

$stmt = preparar_consulta_solicitudes($conexion, $sql);
$stmt->bind_param('i', $idDocente);
$stmt->execute();
$resultado = $stmt->get_result();
$solicitudes = [];

while ($fila = $resultado->fetch_assoc()) {
    $solicitudes[] = normalizar_solicitud_docente($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'solicitudes' => $solicitudes
]);

?>
