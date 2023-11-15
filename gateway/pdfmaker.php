<?php
require_once __DIR__ . '/vendor/autoload.php';

class PDFMaker
{
    private $_maker;
    public function __construct()
    {
        $this->_maker = new mPDF();
    }
    public function make($template, $data, $fileName)
    {
        $patterns = [];
        $replacements = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($value[1] == 'select' || $value[1] == 'radio') {
                    $patterns[] = "{<{$key} value=\"{$value[0]}\"></{$key}>}";
                    $replacements[] = '√';
                } else {
                    $patterns[] = "{{{{$key}}}}";
                    $replacements[] = $value[0];
                }
            } else {
                $patterns[] = "{{{{$key}}}}";
                $replacements[] = $value;
            }
        }

        $content = preg_replace(
            $patterns,
            $replacements,
            $template
        );
        $this->_maker->WriteHTML($content);
        $fullPath = __DIR__ . '/../documents/' . $fileName . '.pdf';
        $this->_maker->Output($fullPath);
        return $fullPath;
    }
    private function _file_get_contents_utf8($fn)
    {
        $content = file_get_contents($fn);
        return mb_convert_encoding(
            $content,
            'UTF-8',
            mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true)
        );
    }

    public function printPdf($content, $fileName)
    {
        if (strpos($content, '<script') !== false || strpos($content, '<link') !== false) return null;

        $this->_maker->WriteHTML($content);
        $fullPath = __DIR__ . '/../documents/' . $fileName . '.pdf';
        $this->_maker->Output($fullPath);
        return $fullPath;
    }

    public function makeApplicationDocument($template, $data, $fileName)
    {
        $content = preg_replace(array_map(function ($key) {
            return "{{{" . $key . "}}}";
        }, array_keys($data)), array_values($data), $template);
        $this->_maker->WriteHTML($content);
        $fullPath = __DIR__ . '/../documents/' . $fileName . '.pdf';
        $this->_maker->Output($fullPath);
        return $fullPath;
    }
}
