<?php

namespace App\Services;

class DocxTextExtractor
{
    /**
     * Word .docx files are a zip archive of XML — this reads word/document.xml
     * directly via PHP's built-in ZipArchive (already available; the Dockerfile
     * installs the zip extension) rather than pulling in a new composer
     * dependency just for this. Paragraph breaks become newlines before
     * stripping tags, so each line of the original document comes through as
     * one line of text — suitable for the same "one row per line" parsing
     * used by every bulk-import feature in the app.
     */
    public function extract(string $path): string
    {
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return '';
        }

        $xml = str_replace(['</w:p>', '<w:br/>', '<w:br />'], "\n", $xml);
        $text = strip_tags($xml);

        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
