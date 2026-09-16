<?php

// Endpoint publico (sin sesion): verificar.html lo usa para que cualquiera
// con el codigo de una constancia pueda confirmar su autenticidad, igual
// que un "Educator Lookup" real.
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

$codigo = trim((string) ($_GET['codigo'] ?? ''));

if ($codigo === '') {
    responder_json(['ok' => false, 'mensaje' => 'Codigo requerido.'], 422);
}

$sql = "SELECT
            co.codigo,
            co.fecha_emision,
            d.nombres,
            d.apellidos,
            c.titulo,
            c.duracion,
            c.sede
        FROM constancias co
        INNER JOIN inscripciones i ON i.id = co.id_inscripcion
        INNER JOIN docentes d ON d.id = i.id_docente
        INNER JOIN capacitaciones c ON c.id = i.id_capacitacion
        WHERE co.codigo = ?
        LIMIT 1";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
}

$stmt->bind_param('s', $codigo);
$stmt->execute();
$resultado = $stmt->get_result();
$fila = $resultado->fetch_assoc();
$stmt->close();
$conexion->close();

if (!$fila) {
    responder_json(['ok' => false, 'mensaje' => 'No se encontro ninguna constancia con ese codigo.'], 404);
}

responder_json([
    'ok' => true,
    'constancia' => [
        'codigo' => $fila['codigo'],
        'fecha_emision' => $fila['fecha_emision'],
        'docente' => trim($fila['nombres'] . ' ' . $fila['apellidos']),
        'capacitacion' => $fila['titulo'],
        'duracion' => $fila['duracion'],
        'sede' => $fila['sede']
    ]
]);

?>
