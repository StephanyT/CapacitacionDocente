<?php

require_once __DIR__ . '/_helpers.php';

$usuarioActual = exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$estado = trim((string) ($payload['estado'] ?? ''));

if (!$id) {
    responder_json(['ok' => false, 'mensaje' => 'ID invalido.'], 422);
}

// 'vencida' es valida como estado (la transicion automatica la escribe),
// pero el dropdown del admin en gestion_capacitaciones.html no la ofrece
// como opcion seleccionable -- solo la usa para mostrarla quieta cuando ya
// esta ahi. Se acepta aqui igual por si el admin decide "reabrir" una
// vencida eligiendo pendiente/en curso/completada directamente.
if (!in_array($estado, ['pendiente', 'en curso', 'completada', 'vencida'], true)) {
    responder_json(['ok' => false, 'mensaje' => 'Estado invalido.'], 422);
}

$id = (int) $id;

if (!$conexion->begin_transaction()) {
    responder_json(['ok' => false, 'mensaje' => 'No se pudo iniciar la actualizacion.'], 500);
}

$sql = 'SELECT id, id_capacitacion, estado FROM inscripciones WHERE id = ? LIMIT 1 FOR UPDATE';
$stmt = preparar_consulta_inscripciones($conexion, $sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$resultado = $stmt->get_result();
$inscripcion = $resultado->fetch_assoc();
$stmt->close();

if (!$inscripcion) {
    $conexion->rollback();
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Inscripcion no encontrada.'], 404);
}

// Se registra que admin hizo el cambio (actualizado_por), ademas de la fecha
// que ya se guardaba sola via ON UPDATE -- necesario ahora que va a haber
// mas de un administrador con acceso.
$idAdminActual = (int) $usuarioActual['id'];
$sqlUpdate = 'UPDATE inscripciones SET estado = ?, actualizado_por = ? WHERE id = ?';
$stmt = preparar_consulta_inscripciones($conexion, $sqlUpdate);
$stmt->bind_param('sii', $estado, $idAdminActual, $id);
$stmt->execute();
$stmt->close();

$constancia = null;
$constanciaNueva = false;

// RF-13/RN-05: al completar, se genera la constancia automaticamente y una
// sola vez (si ya existia, se reutiliza en vez de crear otra).
if ($estado === 'completada') {
    $sqlExiste = 'SELECT id, codigo, fecha_emision FROM constancias WHERE id_inscripcion = ? LIMIT 1 FOR UPDATE';
    $stmt = preparar_consulta_inscripciones($conexion, $sqlExiste);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    $stmt->close();

    if ($fila) {
        $constancia = [
            'id' => (int) $fila['id'],
            'codigo' => $fila['codigo'],
            'fecha_emision' => $fila['fecha_emision']
        ];
    } else {
        $sqlInsert = 'INSERT INTO constancias (id_inscripcion, codigo, fecha_emision) VALUES (?, NULL, CURRENT_DATE)';
        $stmt = preparar_consulta_inscripciones($conexion, $sqlInsert);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $idConstancia = (int) $conexion->insert_id;
        $stmt->close();

        $codigo = sprintf('CERTUS-%s-%04d', date('Y'), $idConstancia);

        $sqlCodigo = 'UPDATE constancias SET codigo = ? WHERE id = ?';
        $stmt = preparar_consulta_inscripciones($conexion, $sqlCodigo);
        $stmt->bind_param('si', $codigo, $idConstancia);
        $stmt->execute();
        $stmt->close();

        $constancia = [
            'id' => $idConstancia,
            'codigo' => $codigo,
            'fecha_emision' => date('Y-m-d')
        ];
        $constanciaNueva = true;
    }
}

if (!$conexion->commit()) {
    $conexion->rollback();
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'No se pudo completar la actualizacion.'], 500);
}

$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Estado actualizado correctamente.',
    'inscripcion' => ['id' => $id, 'estado' => $estado],
    'constancia' => $constancia,
    'constancia_nueva' => $constanciaNueva
]);

?>
