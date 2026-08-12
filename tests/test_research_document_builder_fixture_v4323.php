<?php
if ($argc < 2) { fwrite(STDERR, "output directory required\n"); exit(2); }
$out = rtrim($argv[1], '/');
if (!is_dir($out)) { mkdir($out, 0777, true); }
define('ABSPATH', __DIR__ . '/');
function __($s, $domain = null) { return $s; }
require dirname(__DIR__) . '/sustainable-catalyst-library/includes/class-sc-library-research-document-builder.php';
$document = [
    'title' => 'Sustainable Systems Evidence Packet',
    'template' => 'evidence-packet',
    'research_question' => 'How should a public institution evaluate sustainability evidence across infrastructure, climate, and equity?',
    'notes' => str_repeat('Working evidence notes remain explicitly user supplied and distinguish source claims from interpretation. ', 4),
    'style' => 'apa-7',
    'include_source_notes' => true,
    'include_urls' => true,
];
$sources = [
    [
        'title' => 'Climate Change 2022: Impacts, Adaptation and Vulnerability',
        'citation' => 'IPCC. (2022). Climate Change 2022: Impacts, Adaptation and Vulnerability. Cambridge University Press.',
        'private_notes' => 'Use confidence language and chapter-specific evidence rather than collapsing all findings into one claim.',
        'doi' => '10.1017/9781009325844',
        'url' => 'https://www.ipcc.ch/report/ar6/wg2/',
    ],
    [
        'title' => 'Transforming Our World: The 2030 Agenda for Sustainable Development',
        'citation' => 'United Nations. (2015). Transforming Our World: The 2030 Agenda for Sustainable Development.',
        'private_notes' => 'Institutional framing for the SDGs and integrated social, environmental, and economic objectives.',
        'url' => 'https://sdgs.un.org/2030agenda',
    ],
];
$model = SC_Library_Research_Document_Builder::document_model($document, $sources);
$docx = SC_Library_Research_Document_Builder::build_docx_binary($model);
$pdf = SC_Library_Research_Document_Builder::build_pdf_binary($model);
file_put_contents($out . '/fixture.docx', $docx);
file_put_contents($out . '/fixture.pdf', $pdf);
echo json_encode([
    'schema' => $model['schema'],
    'blocks' => count($model['blocks']),
    'docx_bytes' => strlen($docx),
    'pdf_bytes' => strlen($pdf),
    'docx_sha256' => hash('sha256', $docx),
    'pdf_sha256' => hash('sha256', $pdf),
    'zip_backend' => class_exists('ZipArchive') ? 'ZipArchive' : 'PharData',
]), "\n";
