<?php

require_once 'auth.php';

$usuario = usuario_actual();

if ($usuario === null) {
    responder_json([
        'ok' => false,
        'mensaje' => 'Sesion no iniciada.'
    ], 401);
}

responder_json([
    'ok' => true,
    'usuario' => $usuario
]);

?>
