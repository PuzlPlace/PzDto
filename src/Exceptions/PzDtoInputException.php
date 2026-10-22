<?php

declare(strict_types=1);

namespace Puzl\PzDto\Exceptions;

/**
 * A entrada do `from()` não pode ser lida: JSON inválido, escalar no lugar de
 * array/objeto. Na raiz sobe como está; dentro de um DTO aninhado vira erro de
 * validação do campo (input ruim do usuário não é erro de uso do pacote).
 */
class PzDtoInputException extends PzDtoException
{
}
