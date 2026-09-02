<?php

namespace App\Imports;

use RuntimeException;

class ImportRowsException extends RuntimeException
{
    public function __construct(public readonly array $rowErrors)
    {
        parent::__construct('Le fichier contient des erreurs de validation.');
    }
}
