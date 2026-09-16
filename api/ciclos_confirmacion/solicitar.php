<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

// Se re-valida del lado servidor lo mismo que ya oculta/muestra el boton en
// el front: el docente debe tener al menos una meta en su plan del periodo
// activo, y todas deben estar cumplidas, antes de poder pedir la
// confirmacion del ciclo.
$periodoActivo = PERIODO_ACTIVO_CICLO;
$sqlPlan = 'SELECT id FROM planes_desarrollo WHERE id_docente = ? AND periodo = ? LIMIT 1';
$stmt = preparar_consulta_ciclos($conexion, $sqlPlan);
$stmt->bind_param('is', $idDocente, $periodoActivo);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$plan) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Define tus metas de este periodo antes de solicitar la confirmacion.'], 422);
}

$idPlan = (int) $plan['id'];
$sqlMetas = "SELECT COUNT(*) AS total, SUM(estado = 'cumplida') AS cumplidas FROM metas_desarrollo WHERE id_plan = ?";
$stmt = preparar_consulta_ciclos($conexion, $sqlMetas);
$stmt->bind_param('i', $idPlan);
$stmt->execute();
$conteo = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total = (int) ($conteo['total'] ?? 0);
$cumplidas = (int) ($conteo['cumplidas'] ?? 0);

if ($total === 0 || $cumplidas < $total) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Completa todas tus metas antes de solicitar la confirmacion del ciclo.'], 422);
}

$estadoSolicitado = 'solicitado';
$sqlUpsert = "INSERT INTO ciclos_confirmacion (id_docente, periodo, estado, fecha_solicitud)
              VALUES (?, ?, ?, CURRENT_DATE)
              ON DUPLICATE KEY UPDATE estado = VALUES(estado), fecha_solicitud = VALUES(fecha_solicitud),
                                      confirmado_por = NULL, fecha_confirmacion = NULL";
$stmt = preparar_consulta_ciclos($conexion, $sqlUpsert);
$stmt->bind_param('iss', $idDocente, $periodoActivo, $estadoSolicitado);
$stmt->execute();
$stmt->close();

$sqlCiclo = 'SELECT id, id_docente, periodo, estado, fecha_solicitud, confirmado_por, fecha_confirmacion
             FROM ciclos_confirmacion WHERE id_docente = ? AND periodo = ? LIMIT 1';
$stmt = preparar_consulta_ciclos($conexion, $sqlCiclo);
$stmt->bind_param('is', $idDocente, $periodoActivo);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Solicitud enviada al administrador.',
    'ciclo' => normalizar_ciclo($fila)
]);

?>
