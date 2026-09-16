<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

// $incluirContacto controla si se trae correo/telefono del mentor. Antes se
// traian siempre para docente Y admin, aunque el docente solo los pida para
// "explorar" mentores (buscando/viendo perfiles, sin haber agendado nada
// todavia) — inconsistente con el criterio ya aplicado en
// mis_sesiones_como_mentor.php (el contacto solo se expone cuando ya existe
// una relacion 1 a 1 confirmada). El admin si los sigue recibiendo siempre,
// para poder gestionar mentores.
function columnas_mentor(bool $incluirContacto = true): string
{
    $contacto = $incluirContacto ? "d.correo AS correo, d.telefono AS telefono," : '';
    return "m.id, m.id_docente, m.tema, m.bio, m.disponibilidad, m.activo,
            COALESCE(CONCAT(d.nombres, ' ', d.apellidos), m.nombres) AS nombres,
            {$contacto} d.anios_experiencia AS anios_experiencia,
            (SELECT COUNT(*) FROM sesiones_mentoria sm WHERE sm.id_mentor = m.id AND sm.estado = 'completada') AS sesiones,
            (SELECT ROUND(AVG(r.rating), 1) FROM resenas_mentoria r WHERE r.id_mentor = m.id) AS rating";
}

function origen_mentor(): string
{
    return 'FROM mentores m LEFT JOIN docentes d ON d.id = m.id_docente';
}

function normalizar_mentor(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'id_docente' => $fila['id_docente'] !== null ? (int) $fila['id_docente'] : null,
        'nombres' => $fila['nombres'],
        'tema' => $fila['tema'],
        'bio' => $fila['bio'],
        'disponibilidad' => decodificar_arreglo_mentor($fila['disponibilidad'] ?? null),
        'activo' => (int) $fila['activo'] === 1,
        'correo' => $fila['correo'] ?? null,
        'telefono' => $fila['telefono'] ?? null,
        'anios_experiencia' => $fila['anios_experiencia'] !== null ? (int) $fila['anios_experiencia'] : 0,
        'sesiones' => (int) $fila['sesiones'],
        'rating' => $fila['rating'] !== null ? (float) $fila['rating'] : null
    ];
}

function decodificar_arreglo_mentor($valor): array
{
    if ($valor === null || $valor === '') {
        return [];
    }

    $datos = json_decode($valor, true);
    return is_array($datos) ? $datos : [];
}

function obtener_mentor_por_id(mysqli $conexion, int $id, bool $incluirContacto = true): ?array
{
    $sql = 'SELECT ' . columnas_mentor($incluirContacto) . ' ' . origen_mentor() . ' WHERE m.id = ? LIMIT 1';
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    $stmt->close();

    return $fila ? normalizar_mentor($fila) : null;
}

?>
