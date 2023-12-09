<?php

declare(strict_types=1);

namespace Lsp\Protocol\Exception;

use Lsp\Contracts\Message\Factory\IdFactoryInterface;
use Lsp\Contracts\Message\Factory\RequestFactoryInterface;
use Lsp\Contracts\Message\Factory\ResponseFactoryInterface;

class DependencyRequiredException extends ProtocolException
{
    final public const CODE_NO_REQUEST_FACTORY = 0x01;

    final public const CODE_NO_RESPONSE_FACTORY = 0x02;

    final public const CODE_NO_ID_FACTORY = 0x03;

    protected const ERROR_CODE_LAST = self::CODE_NO_ID_FACTORY;

    /**
     * @param non-empty-string $interface
     * @param non-empty-string $package
     *
     * @return static
     */
    protected static function fromMissingFactoryImplementation(string $interface, string $package, int $code): self
    {
        $message = 'Unable to find available factory implementation: '
            . 'Please specify the %s explicitly or install the "%s" package';

        return new static(\sprintf($message, $interface, $package), $code);
    }

    public static function fromMissingRequestFactoryImplementation(): self
    {
        return static::fromMissingFactoryImplementation(
            RequestFactoryInterface::class,
            'php-lsp/rpc-message-factory',
            self::CODE_NO_REQUEST_FACTORY,
        );
    }

    public static function fromMissingResponseFactoryImplementation(): self
    {
        return static::fromMissingFactoryImplementation(
            ResponseFactoryInterface::class,
            'php-lsp/rpc-message-factory',
            self::CODE_NO_RESPONSE_FACTORY,
        );
    }

    public static function fromMissingIdFactoryImplementation(): self
    {
        return static::fromMissingFactoryImplementation(
            IdFactoryInterface::class,
            'php-lsp/rpc-message-factory',
            self::CODE_NO_ID_FACTORY,
        );
    }
}
