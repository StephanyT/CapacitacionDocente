<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$idCapacitacion = filter_var($payload['id_capacitacion'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$idsDocentes = $payload['ids_docentes'] ?? [];

if (!$idCapacitacion) {
    responder_json(['ok' => false, 'mensaje' => 'id_capacitacion invalido.'], 422);
}

if (!is_array($idsDocentes)) {
    $idsDocentes = [];
}

$idsDocentes = array_values(array_unique(array_filter(array_map(function ($valor) {
    return filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}, $idsDocentes))));

$idCapacitacion = (int) $idCapacitacion;

$sqlCap = 'SELECT id, fecha FROM capacitaciones WHERE id = ? LIMIT 1';
$stmt = preparar_consulta_inscripciones($conexion, $sqlCap);
$stmt->bind_param('i', $idCapacitacion);
$stmt->execute();
$resultado = $stmt->get_result();
$capacitacion = $resultado->fetch_assoc();
$stmt->close();

if (!$capacitacion) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Capacitacion no encontrada.'], 404);
}

$creadas = 0;

foreach ($idsDocentes as $idDocente) {
    $sqlExiste = 'SELECT id FROM inscripciones WHERE id_docente = ? AND id_capacitacion = ? LIMIT 1';
    $stmt = preparar_consulta_inscripciones($conexion, $sqlExiste);
    $stmt->bind_param('ii', $idDocente, $idCapacitacion);
    $stmt->execute();
    $existe = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existe) {
        continue;
    }

    $estado = 'pendiente';
    $sqlInsert = 'INSERT INTO inscripciones (id_docente, id_capacitacion, estado, fecha_limite) VALUES (?, ?, ?, ?)';
    $stmt = preparar_consulta_inscripciones($conexion, $sqlInsert);
    $stmt->bind_param('iiss', $idDocente, $idCapacitacion, $estado, $capacitacion['fecha']);

    if ($stmt->execute()) {
        $creadas++;
    }

    $stmt->close();
}

$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => "Se asignaron {$creadas} docente(s) a la capacitacion.",
    'creadas' => $creadas
]);

?>
