<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

$sql = "SELECT id, nombres, apellidos, dni, correo, telefono,
               especialidad, anios_experiencia, bio, activo, fecha_creacion
        FROM usuarios
        WHERE rol = 'docente'
        ORDER BY apellidos ASC, nombres ASC, id ASC";

$stmt = preparar_consulta_docentes($conexion, $sql);
$stmt->execute();
$resultado = $stmt->get_result();
$docentes = [];

while ($fila = $resultado->fetch_assoc()) {
    $docentes[] = normalizar_docente($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'docentes' => $docentes
]);

?>
