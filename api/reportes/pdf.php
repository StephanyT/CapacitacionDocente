<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

exigir_rol('admin', true);
$conexion->set_charset('utf8mb4');

function preparar_consulta_reporte_pdf(mysqli $conexion, string $sql): mysqli_stmt
{
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json([
            'ok' => false,
            'mensaje' => 'No se pudo preparar la consulta.'
        ], 500);
    }

    return $stmt;
}

function bind_parametros_reporte_pdf(mysqli_stmt $stmt, string $tipos, array &$parametros): void
{
    if ($tipos === '') {
        return;
    }

    $referencias = [];
    $referencias[] = &$tipos;

    foreach ($parametros as &$valor) {
        $referencias[] = &$valor;
    }

    call_user_func_array([$stmt, 'bind_param'], $referencias);
}

function ejecutar_consulta_reporte_pdf(mysqli_stmt $stmt): mysqli_result
{
    if (!$stmt->execute()) {
        $stmt->close();
        responder_json([
            'ok' => false,
            'mensaje' => 'No se pudo obtener el reporte.'
        ], 500);
    }

    return $stmt->get_result();
}

function parametro_reporte_pdf(string $nombre): string
{
    return trim((string) ($_GET[$nombre] ?? ''));
}

function filtro_reporte_pdf(string $nombre): string
{
    $valor = parametro_reporte_pdf($nombre);
    return $valor === 'todas' ? '' : $valor;
}

function validar_fecha_reporte_pdf(string $fecha, string $campo): void
{
    if ($fecha === '') {
        return;
    }

    $fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);

    if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
        responder_json([
            'ok' => false,
            'mensaje' => "{$campo} invalido."
        ], 422);
    }
}

function obtener_docente_filtro_pdf(mysqli $conexion, int $idDocente): string
{
    $stmt = preparar_consulta_reporte_pdf(
        $conexion,
        "SELECT CONCAT(nombres, ' ', apellidos) AS docente
         FROM usuarios
         WHERE id = ? AND rol = 'docente'
         LIMIT 1"
    );
    $stmt->bind_param('i', $idDocente);
    $resultado = ejecutar_consulta_reporte_pdf($stmt);
    $fila = $resultado->fetch_assoc();
    $stmt->close();

    return $fila ? $fila['docente'] : 'Docente #' . $idDocente;
}

function fecha_reporte_pdf(?string $fecha): string
{
    if (!$fecha) {
        return '-';
    }

    $timestamp = strtotime($fecha);
    return $timestamp ? date('d/m/Y', $timestamp) : $fecha;
}

function numero_pdf(float $numero): string
{
    return rtrim(rtrim(number_format($numero, 2, '.', ''), '0'), '.');
}

function texto_pdf_cp1252(string $texto): string
{
    $convertido = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $texto);
    return $convertido === false ? $texto : $convertido;
}

function escapar_texto_pdf(string $texto): string
{
    $texto = texto_pdf_cp1252($texto);
    $texto = str_replace('\\', '\\\\', $texto);
    $texto = str_replace('(', '\\(', $texto);
    $texto = str_replace(')', '\\)', $texto);
    return str_replace(["\r", "\n"], ' ', $texto);
}

function agregar_texto_pdf(array &$ops, string $texto, float $x, float $y, int $tamano, string $fuente = 'F1', string $alineacion = 'left'): void
{
    $textoConvertido = texto_pdf_cp1252($texto);
    $anchoEstimado = strlen($textoConvertido) * $tamano * 0.48;

    if ($alineacion === 'center') {
        $x -= $anchoEstimado / 2;
    } elseif ($alineacion === 'right') {
        $x -= $anchoEstimado;
    }

    $ops[] = 'BT /' . $fuente . ' ' . $tamano . ' Tf 1 0 0 1 '
        . numero_pdf($x) . ' ' . numero_pdf($y) . ' Tm ('
        . escapar_texto_pdf($texto) . ') Tj ET';
}

function texto_corto_pdf(string $texto, int $maximo): string
{
    $texto = trim(preg_replace('/\s+/', ' ', $texto));

    $longitud = function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen(texto_pdf_cp1252($texto));

    if ($longitud <= $maximo) {
        return $texto;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($texto, 0, max(0, $maximo - 3), 'UTF-8') . '...';
    }

    return substr(texto_pdf_cp1252($texto), 0, max(0, $maximo - 3)) . '...';
}

function crear_pagina_reporte_pdf(array $registros, array $filtros, int $pagina, int $totalPaginas, int $totalRegistros): string
{
    $ops = [];
    $ops[] = 'q';
    $ops[] = '1 1 1 rg 0 0 842 595 re f';
    $ops[] = '0.02 0.32 0.27 rg 0 540 842 55 re f';
    $ops[] = '0.91 0.72 0.24 rg 0 534 842 4 re f';
    $ops[] = '1 1 1 rg';
    agregar_texto_pdf($ops, 'CERTUS', 42, 563, 22, 'F2');
    agregar_texto_pdf($ops, 'Reporte de Capacitaciones Docentes', 421, 563, 18, 'F2', 'center');
    agregar_texto_pdf($ops, 'Generado: ' . date('d/m/Y H:i'), 800, 563, 10, 'F1', 'right');

    $ops[] = '0 0 0 rg';
    agregar_texto_pdf($ops, 'Filtros aplicados: ' . implode(' | ', $filtros), 42, 510, 9, 'F1');
    agregar_texto_pdf($ops, 'Total de registros: ' . $totalRegistros, 42, 494, 9, 'F2');

    $x = 36;
    $y = 466;
    $altoFila = 24;
    $columnas = [
        ['Docente', 92, 18],
        ['Capacitacion', 176, 34],
        ['Estado', 60, 12],
        ['Inicio', 58, 10],
        ['Limite', 58, 10],
        ['Finalizacion', 72, 12],
        ['Sede', 74, 14],
        ['Programa', 142, 26]
    ];

    $ops[] = '0.94 0.96 0.98 rg ' . numero_pdf($x) . ' ' . numero_pdf($y - 5) . ' 732 24 re f';
    $ops[] = '0.02 0.32 0.27 RG 0.8 w';
    $cursorX = $x;

    foreach ($columnas as $columna) {
        agregar_texto_pdf($ops, $columna[0], $cursorX + 4, $y + 2, 8, 'F2');
        $cursorX += $columna[1];
    }

    $y -= $altoFila;

    if (!$registros) {
        agregar_texto_pdf($ops, 'No hay registros para mostrar con los filtros seleccionados.', 421, 350, 12, 'F1', 'center');
    }

    foreach ($registros as $indice => $registro) {
        if ($indice % 2 === 0) {
            $ops[] = '0.985 0.985 0.985 rg ' . numero_pdf($x) . ' ' . numero_pdf($y - 6) . ' 732 22 re f';
        }

        $cursorX = $x;
        $valores = [
            $registro['docente'],
            $registro['capacitacion'],
            ucfirst($registro['estado']),
            fecha_reporte_pdf($registro['fecha_inscripcion']),
            fecha_reporte_pdf($registro['fecha_limite']),
            fecha_reporte_pdf($registro['fecha_finalizacion']),
            $registro['sede'],
            $registro['programa']
        ];

        foreach ($columnas as $indiceColumna => $columna) {
            agregar_texto_pdf($ops, texto_corto_pdf((string) $valores[$indiceColumna], $columna[2]), $cursorX + 4, $y + 2, 7, 'F1');
            $cursorX += $columna[1];
        }

        $y -= $altoFila;
    }

    $ops[] = '0.02 0.32 0.27 rg';
    agregar_texto_pdf($ops, 'Pagina ' . $pagina . ' de ' . $totalPaginas, 800, 30, 9, 'F1', 'right');
    agregar_texto_pdf($ops, 'Sistema de Capacitacion Docente CERTUS', 42, 30, 9, 'F3');
    $ops[] = 'Q';

    return implode("\n", $ops) . "\n";
}

function generar_pdf_reporte(array $registros, array $filtros): string
{
    $porPagina = 16;
    $paginasDatos = $registros ? array_chunk($registros, $porPagina) : [[]];
    $totalPaginas = count($paginasDatos);
    $contenidos = [];

    foreach ($paginasDatos as $indice => $registrosPagina) {
        $contenidos[] = crear_pagina_reporte_pdf($registrosPagina, $filtros, $indice + 1, $totalPaginas, count($registros));
    }

    $objetos = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique >>'
    ];

    $pageObjectIds = [];
    $siguienteObjeto = 6;

    foreach ($contenidos as $contenido) {
        $pageId = $siguienteObjeto++;
        $contentId = $siguienteObjeto++;
        $pageObjectIds[] = $pageId . ' 0 R';
        $objetos[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
        $objetos[$contentId] = '<< /Length ' . strlen($contenido) . " >>\nstream\n" . $contenido . "endstream";
    }

    $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageObjectIds) . '] /Count ' . count($pageObjectIds) . ' >>';
    ksort($objetos);

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];

    foreach ($objetos as $numero => $objeto) {
        $offsets[$numero] = strlen($pdf);
        $pdf .= $numero . " 0 obj\n" . $objeto . "\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objetos) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objetos); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }

    $pdf .= "trailer\n<< /Size " . (count($objetos) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xref . "\n%%EOF";

    return $pdf;
}

$idDocente = filtro_reporte_pdf('docente');
$sede = filtro_reporte_pdf('sede');
$programa = filtro_reporte_pdf('programa');
$estado = filtro_reporte_pdf('estado');
$desde = parametro_reporte_pdf('desde');
$hasta = parametro_reporte_pdf('hasta');

if ($idDocente !== '') {
    $idDocenteFiltrado = filter_var($idDocente, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    if (!$idDocenteFiltrado) {
        responder_json([
            'ok' => false,
            'mensaje' => 'docente invalido.'
        ], 422);
    }

    $idDocente = (int) $idDocenteFiltrado;
}

if ($estado !== '' && !in_array($estado, ['pendiente', 'en curso', 'completada'], true)) {
    responder_json([
        'ok' => false,
        'mensaje' => 'estado invalido.'
    ], 422);
}

validar_fecha_reporte_pdf($desde, 'desde');
validar_fecha_reporte_pdf($hasta, 'hasta');

if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
    responder_json([
        'ok' => false,
        'mensaje' => 'El periodo seleccionado es invalido.'
    ], 422);
}

$condiciones = ["u.rol = 'docente'"];
$tipos = '';
$parametros = [];

if ($idDocente !== '') {
    $condiciones[] = 'u.id = ?';
    $tipos .= 'i';
    $parametros[] = $idDocente;
}

if ($sede !== '') {
    $condiciones[] = 'c.sede = ?';
    $tipos .= 's';
    $parametros[] = $sede;
}

if ($programa !== '') {
    $condiciones[] = 'c.programa = ?';
    $tipos .= 's';
    $parametros[] = $programa;
}

if ($estado !== '') {
    $condiciones[] = 'i.estado = ?';
    $tipos .= 's';
    $parametros[] = $estado;
}

if ($desde !== '' || $hasta !== '') {
    $condiciones[] = "i.estado = 'completada'";

    if ($desde !== '') {
        $condiciones[] = 'DATE(i.fecha_actualizacion) >= ?';
        $tipos .= 's';
        $parametros[] = $desde;
    }

    if ($hasta !== '') {
        $condiciones[] = 'DATE(i.fecha_actualizacion) <= ?';
        $tipos .= 's';
        $parametros[] = $hasta;
    }
}

$where = implode(' AND ', $condiciones);
$sqlReporte = "SELECT
                    i.id AS inscripcion_id,
                    i.estado,
                    i.fecha_inscripcion,
                    i.fecha_limite,
                    CASE
                        WHEN i.estado = 'completada' THEN DATE(i.fecha_actualizacion)
                        ELSE NULL
                    END AS fecha_finalizacion,
                    CONCAT(u.nombres, ' ', u.apellidos) AS docente,
                    c.titulo AS capacitacion,
                    c.sede,
                    c.programa
                FROM inscripciones i
                INNER JOIN usuarios u ON u.id = i.id_docente
                INNER JOIN capacitaciones c ON c.id = i.id_capacitacion
                WHERE {$where}
                ORDER BY i.fecha_inscripcion DESC, i.id DESC";

$stmtReporte = preparar_consulta_reporte_pdf($conexion, $sqlReporte);
bind_parametros_reporte_pdf($stmtReporte, $tipos, $parametros);
$resultadoReporte = ejecutar_consulta_reporte_pdf($stmtReporte);
$registros = [];

while ($fila = $resultadoReporte->fetch_assoc()) {
    $registros[] = [
        'docente' => $fila['docente'],
        'capacitacion' => $fila['capacitacion'],
        'estado' => $fila['estado'],
        'fecha_inscripcion' => $fila['fecha_inscripcion'],
        'fecha_limite' => $fila['fecha_limite'],
        'fecha_finalizacion' => $fila['fecha_finalizacion'],
        'sede' => $fila['sede'],
        'programa' => $fila['programa']
    ];
}

$stmtReporte->close();

$filtros = [];
$filtros[] = 'Sede: ' . ($sede !== '' ? $sede : 'Todas');
$filtros[] = 'Programa: ' . ($programa !== '' ? $programa : 'Todos');
$filtros[] = 'Docente: ' . ($idDocente !== '' ? obtener_docente_filtro_pdf($conexion, (int) $idDocente) : 'Todos');
$filtros[] = 'Estado: ' . ($estado !== '' ? ucfirst($estado) : 'Todos');
$filtros[] = 'Periodo: ' . (($desde !== '' || $hasta !== '') ? (($desde !== '' ? $desde : 'inicio') . ' a ' . ($hasta !== '' ? $hasta : 'hoy')) : 'Todos');

$conexion->close();

$pdf = generar_pdf_reporte($registros, $filtros);
$nombreArchivo = 'reporte-capacitaciones-docentes-' . date('Y-m-d') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo $pdf;
exit;

?>
