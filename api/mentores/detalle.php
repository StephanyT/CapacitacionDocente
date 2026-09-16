<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol(['docente', 'admin'], true);

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$id) {
    responder_json(['ok' => false, 'mensaje' => 'ID invalido.'], 422);
}

$mentor = obtener_mentor_por_id($conexion, (int) $id, $usuario['rol'] === 'admin');

if (!$mentor || ($usuario['rol'] === 'docente' && !$mentor['activo'])) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Mentor no encontrado.'], 404);
}

$sqlReviews = 'SELECT id, autor, comentario, rating, fecha FROM resenas_mentoria WHERE id_mentor = ? ORDER BY fecha DESC';
$stmt = $conexion->prepare($sqlReviews);
$stmt->bind_param('i', $id);
$stmt->execute();
$resultado = $stmt->get_result();
$reviews = [];

while ($fila = $resultado->fetch_assoc()) {
    $reviews[] = [
        'id' => (int) $fila['id'],
        'autor' => $fila['autor'],
        'comentario' => $fila['comentario'],
        'rating' => (int) $fila['rating'],
        'fecha' => $fila['fecha']
    ];
}

$stmt->close();
$conexion->close();

$mentor['reviews'] = $reviews;

responder_json([
    'ok' => true,
    'mentor' => $mentor
]);

?>
