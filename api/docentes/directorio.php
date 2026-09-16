<?php

require_once __DIR__ . '/_helpers.php';

// Directorio "publico" de docentes, para uso de otros docentes (no solo
// admin). A diferencia de listar.php (admin-only, trae correo/DNI/telefono
// para gestion), este endpoint solo expone lo que tiene sentido mostrarle a
// un colega: nombre, especialidad, experiencia y bio. Lo usan "Docentes de
// tu area" en perfil.html y la recomendacion de mentor en mentores.html —
// ninguna de las dos podia funcionar antes porque DOCENTES nunca se cargaba
// del lado del docente (el unico endpoint que existia era admin-only).
exigir_rol(['docente', 'admin'], true);

$sql = 'SELECT id, nombres, apellidos, especialidad, anios_experiencia, bio
        FROM docentes
        WHERE activo = 1
        ORDER BY apellidos ASC, nombres ASC, id ASC';
$stmt = $conexion->prepare($sql);

if (!$stmt) {
    responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
}

$stmt->execute();
$resultado = $stmt->get_result();
$docentes = [];

while ($fila = $resultado->fetch_assoc()) {
    $docentes[] = [
        'id' => (int) $fila['id'],
        'nombres' => $fila['nombres'],
        'apellidos' => $fila['apellidos'],
        'especialidad' => $fila['especialidad'],
        'anios_experiencia' => $fila['anios_experiencia'] !== null ? (int) $fila['anios_experiencia'] : 0,
        'bio' => $fila['bio']
    ];
}

$stmt->close();
$conexion->close();

responder_json(['ok' => true, 'docentes' => $docentes]);

?>
