<?php

declare(strict_types=1);

namespace Lsp\Protocol\Exception;

use Lsp\Contracts\Protocol\Exception\DecodingExceptionInterface;

class DecodingException extends ProtocolException implements DecodingExceptionInterface
{
    final public const CODE_NO_FACTORY_IMPLEMENTATION = 0x01;

    protected const ERROR_CODE_LAST = self::CODE_NO_FACTORY_IMPLEMENTATION;

    public static function fromInternalDecodingError(string $message, int $code = 0x00): self
    {
        $error = '(0x%04X) An error occurred while decoding data, %s';
        $error = \sprintf($error, $code, \lcfirst($message));

        return new static($error, self::CODE_NO_FACTORY_IMPLEMENTATION);
    }
}
