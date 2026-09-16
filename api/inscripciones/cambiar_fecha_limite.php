<?php

require_once __DIR__ . '/_helpers.php';

$usuarioActual = exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$fechaLimite = trim((string) ($payload['fecha_limite'] ?? ''));

if (!$id) {
    responder_json(['ok' => false, 'mensaje' => 'ID invalido.'], 422);
}

$fechaValida = DateTime::createFromFormat('Y-m-d', $fechaLimite);
if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fechaLimite) {
    responder_json(['ok' => false, 'mensaje' => 'Fecha invalida.'], 422);
}

$id = (int) $id;

$sql = 'SELECT id, estado FROM inscripciones WHERE id = ? LIMIT 1';
$stmt = preparar_consulta_inscripciones($conexion, $sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$inscripcion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$inscripcion) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Inscripcion no encontrada.'], 404);
}

// No tiene sentido reprogramar el plazo de algo que ya se completo -- el
// frontend ya oculta el boton de editar en ese caso, pero se valida
// tambien aqui (el mismo principio que ya se aplica en evidencias_insignia:
// no confiar solo en que el frontend oculte la opcion).
if ($inscripcion['estado'] === 'completada') {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Esta inscripcion ya esta completada, no tiene plazo que reprogramar.'], 422);
}

// A diferencia de RN-05 (que desplaza la fecha_limite de TODO el grupo aun
// pendiente cuando se reprograma la fecha de la capacitacion entera), esto
// es una excepcion individual para un solo docente -- por ejemplo, una
// licencia o un caso particular -- sin mover el plazo de sus compañeros.
$idAdminActual = (int) $usuarioActual['id'];
$sqlUpdate = 'UPDATE inscripciones SET fecha_limite = ?, actualizado_por = ? WHERE id = ?';
$stmt = preparar_consulta_inscripciones($conexion, $sqlUpdate);
$stmt->bind_param('sii', $fechaLimite, $idAdminActual, $id);
$stmt->execute();
$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Fecha limite actualizada.',
    'inscripcion' => ['id' => $id, 'fecha_limite' => $fechaLimite]
]);

?>
