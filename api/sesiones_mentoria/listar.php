<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

$sql = "SELECT
            s.id, s.slot, s.meta, s.estado, s.resenia_enviada, s.fecha, s.fecha_creacion,
            d.id AS docente_id, d.nombres AS docente_nombres, d.apellidos AS docente_apellidos,
            m.id AS mentor_id,
            COALESCE(CONCAT(md.nombres, ' ', md.apellidos), m.nombres) AS mentor_nombres
        FROM sesiones_mentoria s
        INNER JOIN docentes d ON d.id = s.id_docente
        INNER JOIN mentores m ON m.id = s.id_mentor
        LEFT JOIN docentes md ON md.id = m.id_docente
        ORDER BY s.fecha_creacion DESC, s.id DESC";

$stmt = preparar_consulta_sesiones($conexion, $sql);
$stmt->execute();
$resultado = $stmt->get_result();
$sesiones = [];

while ($fila = $resultado->fetch_assoc()) {
    $sesiones[] = normalizar_sesion_admin($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'sesiones' => $sesiones
]);

?>
