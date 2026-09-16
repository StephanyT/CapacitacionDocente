<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

function preparar_consulta_sugerencias(mysqli $conexion, string $sql): mysqli_stmt
{
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
    }

    return $stmt;
}

function payload_sugerencia_request(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);

    if (is_array($json)) {
        return $json;
    }

    return $_POST;
}

function normalizar_sugerencia(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'id_docente' => (int) $fila['id_docente'],
        'titulo' => $fila['titulo'],
        'detalle' => $fila['detalle'],
        'estado' => $fila['estado'],
        'fecha' => $fila['fecha']
    ];
}

?>
