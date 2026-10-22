<?php

declare(strict_types=1);

namespace Puzl\PzDto\Exceptions;

/**
 * Erro de uso do PzDto: declaração inválida (atributo apontando para classe
 * inexistente, `array` sem `namespace`…) ou entrada que não pode ser lida
 * (JSON inválido, escalar no lugar de array).
 */
class PzDtoException extends \InvalidArgumentException
{
}
