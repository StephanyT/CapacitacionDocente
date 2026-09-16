<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

$sql = 'SELECT co.id, co.codigo, co.fecha_emision, co.id_inscripcion
        FROM constancias co
        INNER JOIN inscripciones i ON i.id = co.id_inscripcion
        WHERE i.id_docente = ?
        ORDER BY co.fecha_emision DESC, co.id DESC';

$stmt = preparar_consulta_constancias($conexion, $sql);
$stmt->bind_param('i', $idDocente);
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
