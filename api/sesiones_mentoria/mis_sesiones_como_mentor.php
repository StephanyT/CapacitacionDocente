<?php

require_once __DIR__ . '/_helpers.php';

// Distinto de mis_sesiones.php (sesiones donde el usuario es el docente que
// asiste): aqui se listan las sesiones donde el usuario es el MENTOR — no
// existia ningun endpoint para esto, asi que un docente que tambien es
// mentor nunca podia ver quien le agendo una sesion.
$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

$stmtMentor = $conexion->prepare('SELECT id FROM mentores WHERE id_docente = ? LIMIT 1');
$stmtMentor->bind_param('i', $idDocente);
$stmtMentor->execute();
$mentor = $stmtMentor->get_result()->fetch_assoc();
$stmtMentor->close();

if (!$mentor) {
    // No es mentor — lista vacia, no es un error.
    $conexion->close();
    responder_json(['ok' => true, 'sesiones' => []]);
}

$idMentor = (int) $mentor['id'];

// El correo del docente SI se incluye aqui (a diferencia de
// docentes/directorio.php, que lo excluye a proposito): ese endpoint es un
// directorio publico entre pares, este es una relacion 1 a 1 ya confirmada
// (te eligio como mentor y agendo contigo) — mismo criterio que usa el
// admin para ver el contacto de un docente y poder actuar.
$sql = "SELECT
            s.id, s.slot, s.meta, s.estado, s.resenia_enviada, s.fecha, s.fecha_creacion,
            d.id AS docente_id, d.nombres AS docente_nombres, d.apellidos AS docente_apellidos, d.correo AS docente_correo
        FROM sesiones_mentoria s
        INNER JOIN docentes d ON d.id = s.id_docente
        WHERE s.id_mentor = ?
        ORDER BY s.fecha_creacion DESC, s.id DESC";

$stmt = preparar_consulta_sesiones($conexion, $sql);
$stmt->bind_param('i', $idMentor);
$stmt->execute();
$resultado = $stmt->get_result();
$sesiones = [];

while ($fila = $resultado->fetch_assoc()) {
    $sesiones[] = [
        'id' => (int) $fila['id'],
        'slot' => $fila['slot'],
        'meta' => $fila['meta'],
        'estado' => $fila['estado'],
        'resenia_enviada' => (int) $fila['resenia_enviada'] === 1,
        'fecha' => $fila['fecha'],
        'fecha_creacion' => $fila['fecha_creacion'],
        'docente_nombres' => $fila['docente_nombres'] . ' ' . $fila['docente_apellidos'],
        'docente_correo' => $fila['docente_correo']
    ];
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'sesiones' => $sesiones
]);

?>
