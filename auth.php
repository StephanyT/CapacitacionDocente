<?php

function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function usuario_actual(): ?array
{
    iniciar_sesion_segura();

    if (
        empty($_SESSION['usuario_id']) ||
        empty($_SESSION['nombre']) ||
        empty($_SESSION['correo']) ||
        empty($_SESSION['rol'])
    ) {
        return null;
    }

    return [
        'id' => (int) $_SESSION['usuario_id'],
        'nombre' => $_SESSION['nombre'],
        'correo' => $_SESSION['correo'],
        'rol' => $_SESSION['rol']
    ];
}

function responder_json(array $datos, int $estado = 200): void
{
    http_response_code($estado);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($datos);
    exit;
}

function exigir_sesion(bool $json = false): array
{
    $usuario = usuario_actual();

    if ($usuario !== null) {
        return $usuario;
    }

    if ($json) {
        responder_json([
            'ok' => false,
            'mensaje' => 'Sesion no iniciada.'
        ], 401);
    }

    header('Location: login.html');
    exit;
}

function exigir_rol($roles, bool $json = false): array
{
    $usuario = exigir_sesion($json);
    $rolesPermitidos = is_array($roles) ? $roles : [$roles];

    if (in_array($usuario['rol'], $rolesPermitidos, true)) {
        return $usuario;
    }

    if ($json) {
        responder_json([
            'ok' => false,
            'mensaje' => 'Acceso no autorizado.'
        ], 403);
    }

    header('Location: login.html');
    exit;
}

?>
