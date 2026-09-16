<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

function preparar_consulta_sesiones(mysqli $conexion, string $sql): mysqli_stmt
{
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
    }

    return $stmt;
}

function payload_sesion_request(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);

    if (is_array($json)) {
        return $json;
    }

    return $_POST;
}

function normalizar_sesion_admin(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'slot' => $fila['slot'],
        'meta' => $fila['meta'],
        'estado' => $fila['estado'],
        'resenia_enviada' => (int) $fila['resenia_enviada'] === 1,
        'fecha' => $fila['fecha'],
        'fecha_creacion' => $fila['fecha_creacion'],
        'docente' => [
            'id' => (int) $fila['docente_id'],
            'nombres' => $fila['docente_nombres'],
            'apellidos' => $fila['docente_apellidos']
        ],
        'mentor' => [
            'id' => (int) $fila['mentor_id'],
            'nombres' => $fila['mentor_nombres']
        ]
    ];
}

function normalizar_sesion_docente(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'id_mentor' => (int) $fila['mentor_id'],
        'slot' => $fila['slot'],
        'meta' => $fila['meta'],
        'estado' => $fila['estado'],
        'resenia_enviada' => (int) $fila['resenia_enviada'] === 1,
        'fecha' => $fila['fecha'],
        'fecha_creacion' => $fila['fecha_creacion'],
        'mentor_nombres' => $fila['mentor_nombres']
    ];
}

?>
