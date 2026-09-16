<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$raw = file_get_contents('php://input');
$datos = json_decode($raw, true);
if (!is_array($datos)) {
    $datos = $_POST;
}

$idAutoeval = filter_var($datos['id_autoevaluacion'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$texto = trim((string) ($datos['texto'] ?? ''));

if (!$idAutoeval) {
    responder_json(['ok' => false, 'mensaje' => 'Autoevaluacion invalida.'], 422);
}

if ($texto === '') {
    responder_json(['ok' => false, 'mensaje' => 'Escribe un comentario antes de enviarlo.'], 422);
}

$idAutoeval = (int) $idAutoeval;

$sqlCheck = 'SELECT id FROM autoevaluaciones WHERE id = ? LIMIT 1';
$stmt = preparar_consulta_autoeval($conexion, $sqlCheck);
$stmt->bind_param('i', $idAutoeval);
$stmt->execute();
$existe = (bool) $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existe) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Autoevaluacion no encontrada.'], 404);
}

$autor = ($usuario['nombre'] ?? '') !== '' ? $usuario['nombre'] : 'Colega';

$sqlInsert = 'INSERT INTO comentarios_autoevaluacion (id_autoevaluacion, autor, texto) VALUES (?, ?, ?)';
$stmt = preparar_consulta_autoeval($conexion, $sqlInsert);
$stmt->bind_param('iss', $idAutoeval, $autor, $texto);
$stmt->execute();
$idComentario = (int) $conexion->insert_id;
$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Comentario enviado.',
    'comentario' => ['id' => $idComentario]
], 201);

?>
