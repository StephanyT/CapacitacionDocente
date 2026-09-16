<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

$sql = 'SELECT id, codigo, fecha_emision, id_inscripcion FROM constancias ORDER BY fecha_emision DESC, id DESC';

$stmt = preparar_consulta_constancias($conexion, $sql);
$stmt->execute();
$resultado = $stmt->get_result();
$constancias = [];

while ($fila = $resultado->fetch_assoc()) {
    $constancias[] = normalizar_constancia($fila);
}

$stmt->close();
$conexion->close();

responder_json(['ok' => true, 'constancias' => $constancias]);

?>
