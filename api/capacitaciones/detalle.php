<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol(['docente', 'admin'], true);
$id = id_request();
$soloActivas = $usuario['rol'] === 'docente';

$capacitacion = obtener_capacitacion_por_id($conexion, $id, $soloActivas);
$conexion->close();

if (!$capacitacion) {
    responder_json([
        'ok' => false,
        'mensaje' => 'Capacitacion no encontrada.'
    ], 404);
}

responder_json([
    'ok' => true,
    'capacitacion' => $capacitacion
]);

?>
