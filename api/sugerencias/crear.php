<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$datos = payload_sugerencia_request();
$titulo = trim((string) ($datos['titulo'] ?? ''));
$detalle = trim((string) ($datos['detalle'] ?? ''));

if ($titulo === '') {
    responder_json(['ok' => false, 'mensaje' => 'Escribe un titulo para tu sugerencia.'], 422);
}

$estado = 'pendiente';
$sqlInsert = 'INSERT INTO sugerencias_capacitacion (id_docente, titulo, detalle, estado, fecha)
              VALUES (?, ?, ?, ?, CURRENT_DATE)';
$stmt = preparar_consulta_sugerencias($conexion, $sqlInsert);
$stmt->bind_param('isss', $idDocente, $titulo, $detalle, $estado);
$stmt->execute();
$idSugerencia = (int) $conexion->insert_id;
$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Sugerencia enviada. Gracias por tu aporte.',
    'sugerencia' => ['id' => $idSugerencia]
], 201);

?>
