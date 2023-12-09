<?php

declare(strict_types=1);

namespace Lsp\Protocol\JsonRPC;

enum Signature
{
    /**
     * Add and validate "jsonrpc" section.
     */
    case ALL;

    /**
     * Add "jsonrpc" section while encoding and do not
     * validate during decoding.
     */
    case NO_VALIDATE;

    /**
     * Validate "jsonrpc" section during decoding, but not add
     * it while encoding.
     */
    case NO_INSERT;

    /**
     * Do not add and validate "jsonrpc" section.
     */
    case NONE;

    public static function shouldValidate(self $value): bool
    {
        return $value === self::ALL || $value === self::NO_INSERT;
    }

    public static function shouldInsert(self $value): bool
    {
        return $value === self::ALL || $value === self::NO_VALIDATE;
    }
}
