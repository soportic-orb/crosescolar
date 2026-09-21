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
use Cros\Models\Content;

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

/** Mida en mil·límetres de la primera pàgina d'un PDF generat. */
function pdf_page_size(string $pdf): array
{
    if (!preg_match('#/MediaBox \[0 0 ([0-9.]+) ([0-9.]+)\]#', $pdf, $m)) {
        return [0.0, 0.0];
    }
    return [round((float) $m[1] / Pdf::MM, 1), round((float) $m[2] / Pdf::MM, 1)];
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

// Només el nom de pila: va bé quan el dorsal és petit o es vol llegir de lluny.
Settings::set('bib_name_first_only', '1');
check('Sense cognoms, el nom és només el de pila',
    Bib::name(['first_name' => 'Laia', 'last_name' => 'Ferrer Miró']) === 'Laia');
$short = pdf_text(Bib::pdf([
    ['first_name' => 'Laia', 'last_name' => 'Ferrer Miró', 'bib_number' => 1, 'category_name' => 'Aleví (3r-4t)'],
]));
if ($short !== null) {
    check('I al dorsal tampoc hi surten',
        str_contains($short, 'Laia') && !str_contains($short, 'Ferrer'),
        trim(str_replace("\n", ' ', $short)));
    check('La resta del dorsal no canvia',
        str_contains($short, '001') && str_contains($short, 'Aleví (3r-4t)'));
}
Settings::set('bib_name_first_only', '0');
check('En tornar-ho a desactivar, hi surt el nom sencer',
    Bib::name(['first_name' => 'Laia', 'last_name' => 'Ferrer Miró']) === 'Laia Ferrer Miró');

echo "\n== La categoria del dorsal ==\n";
check('Porta el gènere i els anys',
    Bib::category(['category_name' => 'Infantil', 'category_gender' => 'masculi',
        'category_year_from' => 2013, 'category_year_to' => 2014]) === 'Infantil masculí (2013–2014)',
    Bib::category(['category_name' => 'Infantil', 'category_gender' => 'masculi',
        'category_year_from' => 2013, 'category_year_to' => 2014]));
check('Amb un sol any, no en repeteix dos',
    Bib::category(['category_name' => 'Prebenjamí', 'category_gender' => 'femeni',
        'category_year_from' => 2020, 'category_year_to' => 2020]) === 'Prebenjamí femení (2020)');
check('Una categoria mixta no porta gènere',
    Bib::category(['category_name' => 'Famílies', 'category_gender' => 'mixt',
        'category_year_from' => 1950, 'category_year_to' => 2015]) === 'Famílies (1950–2015)');
check('No repeteix el gènere si ja és al nom',
    Bib::category(['category_name' => 'Infantil masculí', 'category_gender' => 'masculi',
        'category_year_from' => 2013, 'category_year_to' => 2014]) === 'Infantil masculí (2013–2014)');
check('Si el nom ja acaba amb parèntesi, els anys hi van amb un punt volat',
    Bib::category(['category_name' => 'Aleví (3r-4t)', 'category_gender' => 'mixt',
        'category_year_from' => 2016, 'category_year_to' => 2017]) === 'Aleví (3r-4t) · 2016–2017');
check('Sense anys, només el nom',
    Bib::category(['category_name' => 'Famílies', 'category_gender' => 'mixt']) === 'Famílies');
check('Sense categoria, res', Bib::category([]) === '');

$withYears = pdf_text(Bib::pdf([[
    'first_name' => 'Ona', 'last_name' => 'Vila', 'bib_number' => 9,
    'category_name' => 'Prebenjamí', 'category_gender' => 'femeni',
    'category_year_from' => 2020, 'category_year_to' => 2020,
]]));
if ($withYears !== null) {
    check('I al dorsal imprès hi surt tot', str_contains($withYears, 'Prebenjamí femení (2020)'),
        trim(str_replace("\n", ' ', $withYears)));
}

echo "\n== L'escola al dorsal ==\n";
$participant = ['first_name' => 'Laia', 'last_name' => 'Ferrer', 'bib_number' => 4,
    'school' => 'Escola La Granada', 'category_name' => 'Aleví',
    'category_gender' => 'femeni', 'category_year_from' => 2016, 'category_year_to' => 2017];

Settings::set('bib_school_show', '0');
$without = pdf_text(Bib::pdf([$participant]));
if ($without !== null) {
    check('Desactivada, l\'escola no surt al dorsal', !str_contains($without, 'Escola La Granada'),
        trim(str_replace("\n", ' ', $without)));
}

Settings::set('bib_school_show', '1');
$with = pdf_text(Bib::pdf([$participant]));
if ($with !== null) {
    check('Activada, hi surt', str_contains($with, 'Escola La Granada'),
        trim(str_replace("\n", ' ', $with)));
    check('I la resta del dorsal no canvia',
        str_contains($with, '004') && str_contains($with, 'Laia Ferrer') && str_contains($with, 'Aleví'));
}
check('El dorsal de prova també l\'ensenya',
    ($sample = pdf_text(Bib::sample())) === null || str_contains($sample, 'Escola La Granada'));

// Sense escola a la inscripció, no hi ha cap línia buida.
$noSchool = pdf_text(Bib::pdf([['first_name' => 'Pau', 'last_name' => 'Vidal', 'bib_number' => 5]]));
if ($noSchool !== null) {
    check('Sense escola, no s\'hi dibuixa res', !str_contains($noSchool, 'Escola'),
        trim(str_replace("\n", ' ', $noSchool)));
}
Settings::set('bib_school_show', '0');

echo "\n== Dos dorsals per full ==\n";
// Es desen els valors d'ara sense posar-hi cap valor per defecte: si es
// restaurés una cadena buida, l'opció es quedaria en blanc al panell.
$before = [];
foreach (['bib_template', 'bib_orientation', 'bib_page_size',
          'bib_number_y', 'bib_name_y', 'bib_category_y'] as $key) {
    $before[$key] = (string) Settings::get($key);
}
Settings::setMany([
    'bib_template' => '', 'bib_page_size' => 'a5', 'bib_orientation' => 'landscape',
    'bib_number_y' => '55', 'bib_name_y' => '95', 'bib_category_y' => '115',
]);
$three = [
    ['first_name' => 'Laia', 'last_name' => 'Duran', 'bib_number' => 1],
    ['first_name' => 'Pau', 'last_name' => 'Duran', 'bib_number' => 2],
    ['first_name' => 'Roc', 'last_name' => 'Soler', 'bib_number' => 3],
];

Settings::set('bib_two_per_sheet', '0');
check('Desactivat, un dorsal per pàgina', substr_count(Bib::pdf($three), '/Type /Page') === 4);
check('I la pàgina té la mida del dorsal',
    Bib::sheet([210.0, 148.0])['per_sheet'] === 1);

Settings::set('bib_two_per_sheet', '1');
$sheet = Bib::sheet([210.0, 148.0]);
check('Un A5 apaïsat en deixa posar dos', $sheet['per_sheet'] === 2, (string) $sheet['per_sheet']);
check('I el full passa a ser A4 vertical',
    abs($sheet['sheet'][0] - 210.0) < 0.5 && abs($sheet['sheet'][1] - 297.0) < 0.5,
    implode('×', $sheet['sheet']));
check('El segon dorsal va a la meitat de baix', abs($sheet['offset'] - 148.5) < 0.5, (string) $sheet['offset']);
check('Un dorsal A4 sencer no es parteix', Bib::sheet([210.0, 297.0])['per_sheet'] === 1);
check('Ni un A5 vertical, que no hi cabria', Bib::sheet([148.0, 210.0])['per_sheet'] === 1);

$document = Bib::pdf($three);
check('Tres dorsals ocupen dos fulls', substr_count($document, '/Type /Page') === 3,
    (string) substr_count($document, '/Type /Page'));
$text = pdf_text($document);
if ($text !== null) {
    $pages = explode("\f", $text);
    check('Al primer full hi ha els dos primers',
        str_contains($pages[0] ?? '', 'Laia Duran') && str_contains($pages[0] ?? '', 'Pau Duran'),
        trim(str_replace("\n", ' ', $pages[0] ?? '')));
    check('I el tercer va al segon full',
        str_contains($pages[1] ?? '', 'Roc Soler') && !str_contains($pages[1] ?? '', 'Laia'),
        trim(str_replace("\n", ' ', $pages[1] ?? '')));
    check('Tots tres hi són', substr_count($text, 'Duran') === 2 && str_contains($text, 'Soler'));
}

// Amb maqueta, cada meitat en porta una.
$sample = CROS_ROOT . '/uploads/documents/maqueta-per-full.pdf';
@mkdir(dirname($sample), 0775, true);
copy(__DIR__ . '/fixtures/pdf/maqueta.pdf', $sample);
Settings::set('bib_template', 'documents/maqueta-per-full.pdf');
$withTemplate = Bib::pdf([$three[0], $three[1]]);
// «/Type /Page» també apareix al node «/Type /Pages»: per això n'hi ha una de més.
check('Amb maqueta, els dos dorsals caben en un full',
    substr_count($withTemplate, '/Type /Page') === 2, (string) substr_count($withTemplate, '/Type /Page'));
check('I el full és A4 vertical',
    preg_match('#/MediaBox \[0 0 595\.\d+ 841\.\d+\]#', $withTemplate) === 1);
$text = pdf_text($withTemplate);
if ($text !== null) {
    check('Amb els dos participants al mateix full',
        str_contains($text, 'Laia Duran') && str_contains($text, 'Pau Duran'));
}

@unlink($sample);
Settings::set('bib_two_per_sheet', '0');
Settings::setMany($before);

echo "\n== Anys de les categories ==\n";
check('Dos anys diferents es mostren tots dos',
    Content::years(['year_from' => 2013, 'year_to' => 2014]) === '2013–2014');
check('Un sol any es mostra un cop',
    Content::years(['year_from' => 2020, 'year_to' => 2020]) === '2020');
check('Els anys al revés es posen en ordre',
    Content::years(['year_from' => 2014, 'year_to' => 2013]) === '2013–2014');
check('Si només n\'hi ha un de posat, val per als dos',
    Content::years(['year_from' => 2020, 'year_to' => null]) === '2020');
check('Sense anys, cadena buida', Content::years(['year_from' => null, 'year_to' => null]) === '');

// Amb maqueta
$templatePath = CROS_ROOT . '/uploads/documents/maqueta-de-prova.pdf';
@mkdir(dirname($templatePath), 0775, true);
copy(__DIR__ . '/fixtures/pdf/maqueta.pdf', $templatePath);
Settings::set('bib_template', 'documents/maqueta-de-prova.pdf');
$document = Bib::sample();
$text = pdf_text($document);
check('El dorsal es dibuixa sobre la maqueta', $text === null || str_contains($text, 'CROS ESCOLAR LA GRANADA'),
    $text === null ? '' : trim(str_replace("\n", ' ', $text)));
echo "\n== Orientació del dorsal ==\n";
// La maqueta de prova és A5 vertical (148×210 mm).
Settings::set('bib_orientation', 'auto');
Settings::set('bib_template_rotate', '0');
$size = pdf_page_size(Bib::sample());
check('Per defecte el dorsal té la mida de la maqueta', $size === [148.0, 210.0], implode('×', $size));

Settings::set('bib_orientation', 'landscape');
$document = Bib::sample();
$size = pdf_page_size($document);
$text = pdf_text($document);
check('Marcant horitzontal, el dorsal surt apaïsat', $size === [210.0, 148.0], implode('×', $size));
$layout = Bib::layout([148.0, 210.0]);
check('La maqueta vertical es gira per omplir el dorsal apaïsat',
    $layout['rotate'] === 90 && $layout['size'] === [210.0, 148.0], json_encode($layout));
check('La maqueta continua sortint al dorsal apaïsat',
    $text === null || str_contains($text, 'CROS ESCOLAR LA GRANADA'),
    $text === null ? '' : trim(str_replace("\n", ' ', $text)));

Settings::set('bib_orientation', 'portrait');
check('Marcant vertical, el dorsal surt dret', pdf_page_size(Bib::sample()) === [148.0, 210.0]);
check('Una maqueta que ja és vertical no es gira', Bib::layout([148.0, 210.0])['rotate'] === 0);
check('Una maqueta apaïsada es gira per fer-la vertical', Bib::layout([210.0, 148.0])['rotate'] === 90);

Settings::set('bib_orientation', 'auto');
Settings::set('bib_template_rotate', '90');
$document = Bib::sample();
check('Girar la maqueta 90° també gira la pàgina', pdf_page_size($document) === [210.0, 148.0],
    implode('×', pdf_page_size($document)));
$text = pdf_text($document);
check('La maqueta girada conserva el contingut',
    $text === null || str_contains($text, 'CROS ESCOLAR LA GRANADA'));
Settings::set('bib_orientation', 'landscape');
Settings::set('bib_template_rotate', '180');
check('El gir de 180° es combina amb l\'orientació', Bib::layout([148.0, 210.0])['rotate'] === 270,
    (string) Bib::layout([148.0, 210.0])['rotate']);
Settings::set('bib_orientation', 'auto');
Settings::set('bib_template_rotate', '0');

Settings::set('bib_template', '');
@unlink($templatePath);

Settings::set('bib_orientation', 'landscape');
check('Sense maqueta també es pot fer apaïsat', pdf_page_size(Bib::sample()) === [210.0, 148.0],
    implode('×', pdf_page_size(Bib::sample())));
Settings::set('bib_orientation', 'auto');
check('Sense maqueta i en automàtic, la mida configurada', pdf_page_size(Bib::sample()) === [148.0, 210.0]);

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
