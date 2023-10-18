<?php

namespace LSNepomuceno\LaravelA1PdfSign\Exceptions;

use Exception;
use Stringable;

class InvalidPdfSignModeTypeException extends Exception implements Stringable
{
    public function __construct(string $mode, int $code = 0, Exception $previous = null)
    {
        $message = "Error: Invalid mode type, use available modes: \"PDFSigner::MODE_RESOURCE\" or \"PDFSigner::MODE_DOWNLOAD\". Current mode: {$mode}";
        parent::__construct($message, $code, $previous);
    }

    public function __toString(): string
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
