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

$texto = trim((string) ($datos['texto'] ?? ''));

if ($texto === '') {
    responder_json(['ok' => false, 'mensaje' => 'Escribe una meta antes de agregar.'], 422);
}

$idPlan = obtener_o_crear_id_plan($conexion, $idDocente, PERIODO_ACTIVO_PLAN);

$estado = 'en progreso';
$sqlInsert = 'INSERT INTO metas_desarrollo (id_plan, texto, estado) VALUES (?, ?, ?)';
$stmt = preparar_consulta_planes($conexion, $sqlInsert);
$stmt->bind_param('iss', $idPlan, $texto, $estado);
$stmt->execute();
$stmt->close();

$plan = obtener_plan_docente($conexion, $idDocente, PERIODO_ACTIVO_PLAN);
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Meta agregada a tu plan de desarrollo.',
    'plan' => $plan
], 201);

?>
