<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol(['docente', 'admin'], true);

$sql = 'SELECT ' . columnas_mentor($usuario['rol'] === 'admin') . ' ' . origen_mentor();

// El docente solo ve mentores activos (igual que solo ve capacitaciones
// activas); el admin ve todos, para poder reactivar uno si hace falta.
if ($usuario['rol'] === 'docente') {
    $sql .= ' WHERE m.activo = 1';
}

$sql .= ' ORDER BY m.id ASC';

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
}

$stmt->execute();
$resultado = $stmt->get_result();
$mentores = [];

while ($fila = $resultado->fetch_assoc()) {
    $mentores[] = normalizar_mentor($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'mentores' => $mentores
]);

?>
