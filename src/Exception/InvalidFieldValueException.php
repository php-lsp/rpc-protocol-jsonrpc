<?php

declare(strict_types=1);

namespace Lsp\Protocol\Exception;

class InvalidFieldValueException extends DecodingException
{
    final public const CODE_FIELD_CONTAINS_INVALID_VALUE = parent::ERROR_CODE_LAST + 0x01;

    protected const ERROR_CODE_LAST = self::CODE_FIELD_CONTAINS_INVALID_VALUE;

    /**
     * @param non-empty-string $field Invalid field name
     * @param non-empty-string $expected Expected value string representation
     * @param mixed $given
     */
    public static function fromValueOfField(string $field, string $expected, $given): self
    {
        $message = 'Received data must contain field "%s" with value %s, but %s given';
        $message = \sprintf($message, $field, $expected, self::valueToString($given));

        return new self($message, self::CODE_FIELD_CONTAINS_INVALID_VALUE);
    }

    /**
     * @param mixed $value
     * @return non-empty-string
     */
    private static function valueToString($value): string
    {
        switch (true) {
            case \is_string($value):
                /** @var non-empty-string */
                return \sprintf('"%s"', \addslashes($value));
            case $value === true:
                return 'true';
            case $value === false:
                return 'false';
            case $value === null:
                return 'null';
            case \is_int($value):
            case \is_float($value):
                /** @var non-empty-string */
                return (string)$value;
            case \is_object($value):
                return 'object';
            default:
                /** @var non-empty-string */
                return \get_debug_type($value);
        }
    }
}
