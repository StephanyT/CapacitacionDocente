<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

// Vista "colaborativa": autoevaluaciones de OTROS docentes (mis_autoevaluaciones.php
// sigue siendo solo las tuyas), para poder leerlas y dejar un comentario -- es lo
// que hace real el "coaches y colegas pueden comentar tu reflexion" que ya
// prometia el texto de la pagina sin que existiera ningun endpoint para verlo.
$sql = "SELECT a.id, a.id_docente, a.id_capacitacion, a.video_link, a.checklist, a.nota_propia, a.fecha,
               CONCAT(d.nombres, ' ', d.apellidos) AS docente_nombre,
               d.especialidad AS docente_especialidad,
               c.titulo AS capacitacion_titulo
        FROM autoevaluaciones a
        INNER JOIN docentes d ON d.id = a.id_docente
        INNER JOIN capacitaciones c ON c.id = a.id_capacitacion
        WHERE a.id_docente != ?
        ORDER BY a.fecha DESC, a.id DESC
        LIMIT 30";

$stmt = preparar_consulta_autoeval($conexion, $sql);
$stmt->bind_param('i', $idDocente);
$stmt->execute();
$resultado = $stmt->get_result();
$autoevaluaciones = [];

while ($fila = $resultado->fetch_assoc()) {
    $item = normalizar_autoevaluacion($fila);
    $item['docente_nombre'] = $fila['docente_nombre'];
    $item['docente_especialidad'] = $fila['docente_especialidad'];
    $item['capacitacion_titulo'] = $fila['capacitacion_titulo'];
    $autoevaluaciones[] = $item;
}

$stmt->close();
$autoevaluaciones = adjuntar_comentarios($conexion, $autoevaluaciones);
$conexion->close();

responder_json([
    'ok' => true,
    'autoevaluaciones' => $autoevaluaciones
]);

?>
