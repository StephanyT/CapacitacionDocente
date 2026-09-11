<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

$sql = "SELECT
            e.id,
            e.id_docente,
            e.id_insignia,
            e.texto,
            e.estado,
            e.fecha_envio,
            e.fecha_revision,
            e.revisado_por,
            u.id AS docente_id,
            u.nombres AS docente_nombres,
            u.apellidos AS docente_apellidos,
            u.correo AS docente_correo,
            i.id AS insignia_id,
            i.nombre AS insignia_nombre,
            i.codigo_icono AS insignia_codigo_icono
        FROM evidencias_insignia e
        INNER JOIN usuarios u ON u.id = e.id_docente
        INNER JOIN insignias i ON i.id = e.id_insignia
        WHERE e.estado = 'pendiente'
        ORDER BY e.fecha_envio ASC, e.id ASC";

$stmt = preparar_consulta_evidencias($conexion, $sql);
$stmt->execute();
$resultado = $stmt->get_result();
$evidencias = [];

while ($fila = $resultado->fetch_assoc()) {
    $evidencias[] = normalizar_evidencia_admin($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'evidencias' => $evidencias
]);

?>
