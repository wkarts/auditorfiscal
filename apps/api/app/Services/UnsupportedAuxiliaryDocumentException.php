<?php

namespace App\Services;

use RuntimeException;

class UnsupportedAuxiliaryDocumentException extends RuntimeException
{
    public function __construct(string $model)
    {
        parent::__construct(sprintf('Não há renderizador NFePHP configurado para o documento fiscal modelo %s.', $model ?: 'não identificado'));
    }
}
