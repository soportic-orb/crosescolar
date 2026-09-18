<?php
/**
 * Proves del motor de PDF: generació, mesures, importació de maquetes
 * (incloent-hi fitxers fets amb altres programes) i dorsals.
 *
 * Ús:  php tests/pdf.php
 */
declare(strict_types=1);

require __DIR__ . '/env.php';

use Cros\Core\Pdf;
use Cros\Core\PdfImport;
use Cros\Core\Settings;
use Cros\Models\Bib;

$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $name . ($ok || $detail === '' ? '' : " → $detail") . "\n";
}

/** Text d'un PDF (fa servir pdftotext si hi és; si no, es comprova l'estructura). */
function pdf_text(string $pdf): ?string
{
    $file = sys_get_temp_dir() . '/cros-test-' . bin2hex(random_bytes(4)) . '.pdf';
    file_put_contents($file, $pdf);
    $output = null;
    $binary = trim((string) @shell_exec('command -v pdftotext 2>/dev/null'));
    if ($binary !== '') {
        $output = (string) @shell_exec('pdftotext ' . escapeshellarg($file) . ' - 2>/dev/null');
    }
    @unlink($file);
    return $output;
}

echo "\n== Generació de PDF ==\n";
$pdf = new Pdf(['title' => 'Prova']);
$pdf->addPage('a4');
$pdf->setFont('helvetica-bold', 24);
$pdf->text(105, 40, 'Cros Escolar La Granada', ['align' => 'center']);
$pdf->setFont('helvetica', 12);
$pdf->text(20, 60, 'Accents: àèéíòóúüïç');
$pdf->line(20, 70, 190, 70);
$pdf->rect(20, 80, 50, 20, 'F');
$document = $pdf->output();
check('Genera un document PDF', str_starts_with($document, '%PDF-') && str_contains($document, '%%EOF'));
check('Té una pàgina', substr_count($document, '/Type /Page') >= 1);
$text = pdf_text($document);
if ($text !== null) {
    check('El text es pot llegir', str_contains($text, 'Cros Escolar La Granada'));
    check('Conserva els accents', str_contains($text, 'àèéíòóúüïç'), trim($text));
} else {
    echo "  (pdftotext no disponible: no es comprova el text)\n";
}

$pdf2 = new Pdf();
$pdf2->addPage('a5', 'landscape');
check('Mida A5 apaïsada', abs($pdf2->pageWidth() - 210.0) < 0.1 && abs($pdf2->pageHeight() - 148.0) < 0.1);
$pdf2->setFont('helvetica', 10);
$width = $pdf2->textWidth('Cros');
check('Calcula l\'amplada del text', $width > 5 && $width < 15, (string) $width);

echo "\n== Importació de maquetes ==\n";
foreach (['maqueta' => 'normal', 'maqueta-fluxos' => 'amb fluxos d\'objectes', 'maqueta-girada' => 'girada 90°'] as $file => $label) {
    $path = __DIR__ . '/fixtures/pdf/' . $file . '.pdf';
    if (!is_file($path)) {
        continue;
    }
    try {
        $import = new PdfImport($path);
        $size = $import->pageSize(1);
        $out = new Pdf();
        $out->addPage([$size['width'], $size['height']]);
        $template = $import->page($out, 1);
        $out->useTemplate($template);
        $out->setFont('helvetica-bold', 40);
        $out->text($size['width'] / 2, $size['height'] / 2, '042', ['align' => 'center']);
        $document = $out->output();
        $text = pdf_text($document);
        $ok = str_starts_with($document, '%PDF-')
            && ($text === null || (str_contains($text, 'CROS ESCOLAR LA GRANADA') && str_contains($text, '042')));
        check('Importa una maqueta ' . $label, $ok, $text === null ? '' : trim(str_replace("\n", ' ', $text)));
        check('Mida correcta de la maqueta ' . $label, $size['width'] > 50 && $size['height'] > 50,
            sprintf('%.0f×%.0f mm', $size['width'], $size['height']));
    } catch (Throwable $e) {
        check('Importa una maqueta ' . $label, false, $e->getMessage());
    }
}

try {
    new PdfImport('això no és un pdf');
    check('Rebutja fitxers que no són PDF', false);
} catch (Throwable $e) {
    check('Rebutja fitxers que no són PDF', str_contains($e->getMessage(), 'no és un PDF'));
}

echo "\n== Dorsals ==\n";
Settings::set('bib_template', '');
check('Número amb tres xifres', Bib::number(['bib_number' => 7]) === '007', Bib::number(['bib_number' => 7]));
check('Número de tres xifres sencer', Bib::number(['bib_number' => 128]) === '128');
check('Sense dorsal assignat', Bib::number(['bib_number' => null]) === '—');

$document = Bib::pdf([
    ['first_name' => 'Laia', 'last_name' => 'Ferrer Miró', 'bib_number' => 1, 'category_name' => 'Aleví (3r-4t)'],
    ['first_name' => 'Pau', 'last_name' => 'Vidal', 'bib_number' => 2, 'category_name' => 'Benjamí (1r-2n)'],
]);
check('Genera un dorsal per participant', substr_count($document, '/Type /Page') >= 2);
$text = pdf_text($document);
if ($text !== null) {
    check('El dorsal porta el número', str_contains($text, '001') && str_contains($text, '002'), trim(str_replace("\n", ' ', $text)));
    check('El dorsal porta el nom', str_contains($text, 'Laia Ferrer Miró'));
    check('El dorsal porta la categoria', str_contains($text, 'Aleví (3r-4t)'));
}

// Amb maqueta
$templatePath = CROS_ROOT . '/uploads/documents/maqueta-de-prova.pdf';
@mkdir(dirname($templatePath), 0775, true);
copy(__DIR__ . '/fixtures/pdf/maqueta.pdf', $templatePath);
Settings::set('bib_template', 'documents/maqueta-de-prova.pdf');
$document = Bib::sample();
$text = pdf_text($document);
check('El dorsal es dibuixa sobre la maqueta', $text === null || str_contains($text, 'CROS ESCOLAR LA GRANADA'),
    $text === null ? '' : trim(str_replace("\n", ' ', $text)));
Settings::set('bib_template', '');
@unlink($templatePath);

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
