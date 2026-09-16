<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

function preparar_consulta_constancias(mysqli $conexion, string $sql): mysqli_stmt
{
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
    }

    return $stmt;
}

function normalizar_constancia(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'codigo' => $fila['codigo'],
        'fecha_emision' => $fila['fecha_emision'],
        'id_inscripcion' => (int) $fila['id_inscripcion']
    ];
}

?>
