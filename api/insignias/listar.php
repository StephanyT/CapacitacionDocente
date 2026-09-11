<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol(['docente', 'admin'], true);

$sql = 'SELECT
            id,
            nombre,
            codigo_icono,
            requisito,
            area,
            id_capacitacion_requerida,
            requiere_evidencia,
            activo,
            fecha_creacion
        FROM insignias
        WHERE activo = 1
        ORDER BY id ASC';

$stmt = preparar_consulta_insignias($conexion, $sql);
$stmt->execute();
$resultado = $stmt->get_result();
$insignias = [];

while ($fila = $resultado->fetch_assoc()) {
    $insignias[] = normalizar_insignia($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'insignias' => $insignias
]);

?>
