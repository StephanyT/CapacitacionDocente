<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

function columnas_insignia(): string
{
    return 'id, nombre, icono, area, requiere_capacitacion, evidencia, requisito';
}

function normalizar_insignia(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'nombre' => $fila['nombre'],
        'icono' => $fila['icono'],
        'area' => $fila['area'],
        'requiere_capacitacion' => $fila['requiere_capacitacion'] !== null ? (int) $fila['requiere_capacitacion'] : null,
        'evidencia' => (int) $fila['evidencia'] === 1,
        'requisito' => $fila['requisito']
    ];
}

?>
