<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$raw = file_get_contents('php://input');
$datos = json_decode($raw, true);
if (!is_array($datos)) {
    $datos = $_POST;
}

$idCapacitacion = filter_var($datos['id_capacitacion'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$videoLink = trim((string) ($datos['video_link'] ?? ''));
$notaPropia = trim((string) ($datos['nota_propia'] ?? ''));
$checklistEntrada = $datos['checklist'] ?? [];

if (!$idCapacitacion) {
    responder_json(['ok' => false, 'mensaje' => 'Elige una capacitacion antes de guardar.'], 422);
}

if (!is_array($checklistEntrada)) {
    $checklistEntrada = [];
}

$idCapacitacion = (int) $idCapacitacion;

// Se re-valida del lado servidor que la capacitacion este completada por
// este docente — el formulario solo la ofrece si esta completada, pero no
// hay que confiar solo en eso (mismo patron que evidencias_insignia).
$sqlCheck = "SELECT id FROM inscripciones WHERE id_docente = ? AND id_capacitacion = ? AND estado = 'completada' LIMIT 1";
$stmt = preparar_consulta_autoeval($conexion, $sqlCheck);
$stmt->bind_param('ii', $idDocente, $idCapacitacion);
$stmt->execute();
$completada = (bool) $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$completada) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Solo puedes autoevaluarte sobre una capacitacion que ya completaste.'], 422);
}

// Una autoevaluacion por capacitacion (mismo patron que evidencias_insignia
// y solicitudes de acceso): sin esto, el docente podia darle "Guardar" varias
// veces seguidas sobre la misma capacitacion y quedaba con reflexiones
// identicas duplicadas en su historial.
$sqlDuplicado = 'SELECT id FROM autoevaluaciones WHERE id_docente = ? AND id_capacitacion = ? LIMIT 1';
$stmt = preparar_consulta_autoeval($conexion, $sqlDuplicado);
$stmt->bind_param('ii', $idDocente, $idCapacitacion);
$stmt->execute();
$yaExiste = (bool) $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($yaExiste) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Ya enviaste una autoevaluacion para esta capacitacion.'], 409);
}

$checklistTexto = checklist_a_texto($checklistEntrada);

$sqlInsert = 'INSERT INTO autoevaluaciones (id_docente, id_capacitacion, video_link, checklist, nota_propia, fecha)
              VALUES (?, ?, ?, ?, ?, CURRENT_DATE)';
$stmt = preparar_consulta_autoeval($conexion, $sqlInsert);
$stmt->bind_param('iisss', $idDocente, $idCapacitacion, $videoLink, $checklistTexto, $notaPropia);
$stmt->execute();
$idAutoeval = (int) $conexion->insert_id;
$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Autoevaluacion guardada.',
    'autoevaluacion' => ['id' => $idAutoeval]
], 201);

?>
