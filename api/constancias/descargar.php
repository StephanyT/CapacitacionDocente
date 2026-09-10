<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

$idInscripcion = filter_input(INPUT_GET, 'id_inscripcion', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$idInscripcion) {
    responder_json([
        'ok' => false,
        'mensaje' => 'id_inscripcion invalido.'
    ], 422);
}

$constancia = obtener_constancia_docente($conexion, $idDocente, (int) $idInscripcion);
$conexion->close();

if (!$constancia) {
    responder_json([
        'ok' => false,
        'mensaje' => 'Constancia no encontrada.'
    ], 404);
}

$pdf = generar_pdf_constancia($constancia);
$nombreArchivo = 'constancia-' . $constancia['codigo'] . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo $pdf;
exit;

?>
