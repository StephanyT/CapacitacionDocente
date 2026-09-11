<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

$sql = 'SELECT
            e.id,
            e.id_insignia,
            e.texto,
            e.estado,
            e.fecha_envio,
            e.fecha_revision,
            e.revisado_por,
            i.id AS insignia_id,
            i.nombre AS insignia_nombre,
            i.codigo_icono AS insignia_codigo_icono
        FROM evidencias_insignia e
        INNER JOIN insignias i ON i.id = e.id_insignia
        WHERE e.id_docente = ?
        ORDER BY e.fecha_envio DESC, e.id DESC';

$stmt = preparar_consulta_evidencias($conexion, $sql);
$stmt->bind_param('i', $idDocente);
$stmt->execute();
$resultado = $stmt->get_result();
$evidencias = [];

while ($fila = $resultado->fetch_assoc()) {
    $evidencias[] = normalizar_evidencia_docente($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'evidencias' => $evidencias
]);

?>
