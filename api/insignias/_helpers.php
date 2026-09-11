<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

$conexion->set_charset('utf8mb4');

function preparar_consulta_insignias(mysqli $conexion, string $sql): mysqli_stmt
{
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json([
            'ok' => false,
            'mensaje' => 'No se pudo preparar la consulta.'
        ], 500);
    }

    return $stmt;
}

function normalizar_insignia(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'nombre' => $fila['nombre'],
        'codigo_icono' => $fila['codigo_icono'],
        'requisito' => $fila['requisito'],
        'area' => $fila['area'],
        'id_capacitacion_requerida' => $fila['id_capacitacion_requerida'] !== null
            ? (int) $fila['id_capacitacion_requerida']
            : null,
        'requiere_evidencia' => (int) $fila['requiere_evidencia'] === 1,
        'activo' => (int) $fila['activo'] === 1,
        'fecha_creacion' => $fila['fecha_creacion']
    ];
}

function normalizar_insignia_calculada(array $fila): array
{
    $insignia = normalizar_insignia($fila);
    $cumpleRequisito = (int) $fila['cumple_requisito'] === 1;
    $evidenciaAprobada = (int) $fila['evidencia_aprobada'] === 1;

    $insignia['cumple_requisito'] = $cumpleRequisito;
    $insignia['evidencia_aprobada'] = $evidenciaAprobada;
    $insignia['estado_evidencia'] = $fila['estado_evidencia'];
    $insignia['obtenida'] = $cumpleRequisito
        && (!$insignia['requiere_evidencia'] || $evidenciaAprobada);

    return $insignia;
}

?>
