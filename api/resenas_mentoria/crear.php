<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';
require_once __DIR__ . '/../sesiones_mentoria/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$datos = payload_sesion_request();

$idSesion = filter_var($datos['id_sesion'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$comentario = trim((string) ($datos['comentario'] ?? ''));
$rating = filter_var($datos['rating'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);

if (!$idSesion) {
    responder_json(['ok' => false, 'mensaje' => 'Sesion invalida.'], 422);
}

if (!$rating) {
    responder_json(['ok' => false, 'mensaje' => 'Elige una calificacion de 1 a 5.'], 422);
}

if ($comentario === '') {
    responder_json(['ok' => false, 'mensaje' => 'Escribe un breve comentario.'], 422);
}

$idSesion = (int) $idSesion;

if (!$conexion->begin_transaction()) {
    responder_json(['ok' => false, 'mensaje' => 'No se pudo iniciar el registro de la reseña.'], 500);
}

$sqlSesion = 'SELECT id, id_mentor, id_docente, estado, resenia_enviada
              FROM sesiones_mentoria WHERE id = ? LIMIT 1 FOR UPDATE';
$stmt = preparar_consulta_sesiones($conexion, $sqlSesion);
$stmt->bind_param('i', $idSesion);
$stmt->execute();
$resultado = $stmt->get_result();
$sesion = $resultado->fetch_assoc();
$stmt->close();

if (!$sesion || (int) $sesion['id_docente'] !== $idDocente) {
    $conexion->rollback();
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Sesion no encontrada.'], 404);
}

if ($sesion['estado'] !== 'completada') {
    $conexion->rollback();
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Solo puedes dejar una reseña de una sesion completada.'], 422);
}

if ((int) $sesion['resenia_enviada'] === 1) {
    $conexion->rollback();
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Ya dejaste una reseña para esta sesion.'], 409);
}

$idMentor = (int) $sesion['id_mentor'];
$autor = $usuario['nombre'];

$sqlInsert = 'INSERT INTO resenas_mentoria (id_mentor, id_sesion, autor, comentario, rating) VALUES (?, ?, ?, ?, ?)';
$stmt = preparar_consulta_sesiones($conexion, $sqlInsert);
$stmt->bind_param('iissi', $idMentor, $idSesion, $autor, $comentario, $rating);
$stmt->execute();
$idResena = (int) $conexion->insert_id;
$stmt->close();

$sqlMarcar = 'UPDATE sesiones_mentoria SET resenia_enviada = 1 WHERE id = ?';
$stmt = preparar_consulta_sesiones($conexion, $sqlMarcar);
$stmt->bind_param('i', $idSesion);
$stmt->execute();
$stmt->close();

if (!$conexion->commit()) {
    $conexion->rollback();
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'No se pudo guardar la reseña.'], 500);
}

$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Gracias por tu reseña.',
    'resena' => ['id' => $idResena]
], 201);

?>
