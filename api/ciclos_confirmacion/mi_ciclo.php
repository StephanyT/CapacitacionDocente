<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

$sql = 'SELECT id, id_docente, periodo, estado, fecha_solicitud, confirmado_por, fecha_confirmacion
        FROM ciclos_confirmacion
        WHERE id_docente = ? AND periodo = ?
        LIMIT 1';
$periodoActivo = PERIODO_ACTIVO_CICLO;
$stmt = preparar_consulta_ciclos($conexion, $sql);
$stmt->bind_param('is', $idDocente, $periodoActivo);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'ciclo' => $fila ? normalizar_ciclo($fila) : null
]);

?>
