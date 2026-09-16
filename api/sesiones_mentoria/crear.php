<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$datos = payload_sesion_request();

$idMentor = filter_var($datos['id_mentor'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$slot = trim((string) ($datos['slot'] ?? ''));
$meta = trim((string) ($datos['meta'] ?? ''));

if (!$idMentor) {
    responder_json(['ok' => false, 'mensaje' => 'Elige un mentor valido.'], 422);
}

if ($slot === '') {
    responder_json(['ok' => false, 'mensaje' => 'Elige un horario disponible.'], 422);
}

if ($meta === '') {
    responder_json(['ok' => false, 'mensaje' => 'Escribe que buscas lograr en la sesion.'], 422);
}

$idMentor = (int) $idMentor;

$sqlMentor = 'SELECT id, id_docente FROM mentores WHERE id = ? AND activo = 1 LIMIT 1';
$stmt = preparar_consulta_sesiones($conexion, $sqlMentor);
$stmt->bind_param('i', $idMentor);
$stmt->execute();
$mentor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$mentor) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Mentor no encontrado.'], 404);
}

// Bug real: un docente que tambien es mentor podia agendar una sesion
// consigo mismo (nada lo impedia).
if ($mentor['id_docente'] !== null && (int) $mentor['id_docente'] === $idDocente) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'No puedes agendar una sesion de mentoria contigo mismo.'], 422);
}

$estado = 'agendada';
$sqlInsert = 'INSERT INTO sesiones_mentoria (id_docente, id_mentor, slot, meta, estado, fecha)
              VALUES (?, ?, ?, ?, ?, CURRENT_DATE)';
$stmt = preparar_consulta_sesiones($conexion, $sqlInsert);
$stmt->bind_param('iisss', $idDocente, $idMentor, $slot, $meta, $estado);
$stmt->execute();
$idSesion = (int) $conexion->insert_id;
$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Sesion agendada correctamente.',
    'sesion' => ['id' => $idSesion]
], 201);

?>
