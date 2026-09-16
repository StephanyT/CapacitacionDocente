<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

$plan = obtener_plan_docente($conexion, $idDocente, PERIODO_ACTIVO_PLAN);
$conexion->close();

responder_json([
    'ok' => true,
    'plan' => $plan
]);

?>
