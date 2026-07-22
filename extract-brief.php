<?php

$path = 'c:/Users/Imran/Downloads/fwdposcontinentalwholesale/Continental POS Clone-Developer Brief V2.docx';
$zip = new ZipArchive();
if ($zip->open($path) !== true) {
    fwrite(STDERR, "Cannot open docx\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$xml = preg_replace('/<\/w:p>/', "\n", $xml);
$text = strip_tags($xml);
$text = html_entity_decode($text);
$text = preg_replace('/[ \t]+/', ' ', $text);
$text = preg_replace("/\n{3,}/", "\n\n", $text);

file_put_contents(__DIR__.'/storage/app/brief-extract.txt', $text);
echo strlen($text)." chars\n";
