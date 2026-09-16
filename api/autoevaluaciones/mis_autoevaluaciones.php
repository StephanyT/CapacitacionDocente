<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

$sql = 'SELECT id, id_docente, id_capacitacion, video_link, checklist, nota_propia, fecha
        FROM autoevaluaciones
        WHERE id_docente = ?
        ORDER BY fecha DESC, id DESC';

$stmt = preparar_consulta_autoeval($conexion, $sql);
$stmt->bind_param('i', $idDocente);
$stmt->execute();
$resultado = $stmt->get_result();
$autoevaluaciones = [];

while ($fila = $resultado->fetch_assoc()) {
    $autoevaluaciones[] = normalizar_autoevaluacion($fila);
}

$stmt->close();
$autoevaluaciones = adjuntar_comentarios($conexion, $autoevaluaciones);
$conexion->close();

responder_json([
    'ok' => true,
    'autoevaluaciones' => $autoevaluaciones
]);

?>
