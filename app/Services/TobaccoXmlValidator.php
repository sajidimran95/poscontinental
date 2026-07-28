<?php

namespace App\Services;

use DOMDocument;
use LibXMLError;

class TobaccoXmlValidator
{
    /**
     * @return array{valid: bool, errors: list<string>}
     */
    public function validate(string $xml, ?string $schemaPath = null): array
    {
        $schemaPath ??= resource_path('xsd/michigan-tobacco/MichiganTobaccoReturn.xsd');

        if (! is_file($schemaPath)) {
            return [
                'valid' => false,
                'errors' => ['Schema file not found: '.$schemaPath],
            ];
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument;
        if (! $dom->loadXML($xml, LIBXML_NONET)) {
            $errors = $this->formatLibxmlErrors(libxml_get_errors());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return [
                'valid' => false,
                'errors' => $errors !== [] ? $errors : ['XML is not well-formed.'],
            ];
        }

        $valid = $dom->schemaValidate($schemaPath);
        $errors = $this->formatLibxmlErrors(libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return [
            'valid' => $valid && $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<LibXMLError>  $errors
     * @return list<string>
     */
    private function formatLibxmlErrors(array $errors): array
    {
        return array_values(array_map(function (LibXMLError $error): string {
            $line = (int) $error->line;
            $message = trim($error->message);

            return $line > 0 ? "Line {$line}: {$message}" : $message;
        }, $errors));
    }
}
