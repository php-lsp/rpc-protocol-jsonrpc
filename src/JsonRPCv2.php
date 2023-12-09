<?php

declare(strict_types=1);

namespace Lsp\Protocol;

use Lsp\Contracts\Message\Factory\IdFactoryInterface;
use Lsp\Contracts\Message\Factory\RequestFactoryInterface;
use Lsp\Contracts\Message\Factory\ResponseFactoryInterface;
use Lsp\Contracts\Message\FailureResponseInterface;
use Lsp\Contracts\Message\IdInterface;
use Lsp\Contracts\Message\MessageInterface;
use Lsp\Contracts\Message\NotificationInterface;
use Lsp\Contracts\Message\RequestInterface;
use Lsp\Contracts\Message\ResponseInterface;
use Lsp\Contracts\Message\SuccessfulResponseInterface;
use Lsp\Contracts\Protocol\DecoderInterface;
use Lsp\Contracts\Protocol\EncoderInterface;
use Lsp\Contracts\Protocol\Exception\DecodingExceptionInterface;
use Lsp\Message\Factory\IdFactory;
use Lsp\Message\Factory\RequestFactory;
use Lsp\Message\Factory\ResponseFactory;
use Lsp\Protocol\Exception\DecodingException;
use Lsp\Protocol\Exception\DependencyRequiredException;
use Lsp\Protocol\Exception\EncodingException;
use Lsp\Protocol\Exception\InvalidFieldTypeException;
use Lsp\Protocol\Exception\InvalidFieldValueException;
use Lsp\Protocol\Exception\RequiredFieldNotDefinedException;
use Lsp\Protocol\JsonRPC\Signature;

final class JsonRPCv2 implements EncoderInterface, DecoderInterface
{
    /**
     * - JSON_BIGINT_AS_STRING: This flag adds support for converting large
     *   numbers to strings. Such problems can arise, for example, if the
     *   code runs on the x86 platform and receives an int64 message
     *   identifier.
     */
    public const DEFAULT_JSON_FLAGS_DECODE = \JSON_BIGINT_AS_STRING;

    /**
     * - JSON_UNESCAPED_UNICODE: This flag allows UTF chars instead "\x0000"
     *   sequences which greatly reduces the amount of transmitted information
     *   in case unicode sequences are transmitted.
     */
    public const DEFAULT_JSON_FLAGS_ENCODE = \JSON_UNESCAPED_UNICODE;

    /**
     * @var int<1, 2147483647>
     */
    public const DEFAULT_JSON_DEPTH = 64;

    private readonly RequestFactoryInterface $requests;

    private readonly ResponseFactoryInterface $responses;

    private readonly IdFactoryInterface $ids;

    /**
     * @param int<1, 2147483647> $jsonMaxDepth
     */
    public function __construct(
        RequestFactoryInterface $requests = null,
        ResponseFactoryInterface $responses = null,
        IdFactoryInterface $ids = null,
        private readonly Signature $signature = Signature::ALL,
        private readonly int $jsonEncodingFlags = self::DEFAULT_JSON_FLAGS_ENCODE,
        private readonly int $jsonDecodingFlags = self::DEFAULT_JSON_FLAGS_DECODE,
        private readonly int $jsonMaxDepth = self::DEFAULT_JSON_DEPTH
    ) {
        $this->requests = $requests ?? $this->createRequestFactory();
        $this->responses = $responses ?? $this->createResponseFactory();
        $this->ids = $ids ?? $this->createIdFactory();
    }

    /**
     * @psalm-suppress MixedInferredReturnType
     * @psalm-suppress MixedReturnStatement
     */
    private function createRequestFactory(): RequestFactoryInterface
    {
        if (\class_exists(RequestFactory::class)) {
            return new RequestFactory();
        }

        throw DependencyRequiredException::fromMissingRequestFactoryImplementation();
    }

    /**
     * @psalm-suppress MixedInferredReturnType
     * @psalm-suppress MixedReturnStatement
     */
    private function createResponseFactory(): ResponseFactoryInterface
    {
        if (\class_exists(ResponseFactory::class)) {
            return new ResponseFactory();
        }

        throw DependencyRequiredException::fromMissingResponseFactoryImplementation();
    }

    /**
     * @psalm-suppress MixedInferredReturnType
     * @psalm-suppress MixedReturnStatement
     */
    private function createIdFactory(): IdFactoryInterface
    {
        if (\class_exists(IdFactory::class)) {
            return new IdFactory();
        }

        throw DependencyRequiredException::fromMissingIdFactoryImplementation();
    }

    public function encode(MessageInterface $message): string
    {
        $data = match (true) {
            $message instanceof RequestInterface => [
                'id' => $message->getId()->toPrimitive(),
                'method' => $message->getMethod(),
                'params' => $message->getParameters(),
            ],
            $message instanceof NotificationInterface => [
                'method' => $message->getMethod(),
                'params' => $message->getParameters(),
            ],
            $message instanceof FailureResponseInterface => [
                'id' => $message->getId()->toPrimitive(),
                'error' => [
                    'code' => $message->getErrorCode(),
                    'message' => $message->getErrorMessage(),
                    'data' => $message->getErrorData(),
                ],
            ],
            $message instanceof SuccessfulResponseInterface => [
                'id' => $message->getId()->toPrimitive(),
                'result' => $message->getResult(),
            ],
            default => throw new \InvalidArgumentException(
                \sprintf('Unsupported message type: %s', \get_class($message)),
            ),
        };

        if (Signature::shouldInsert($this->signature)) {
            $data['jsonrpc'] = '2.0';
        }

        return $this->toJson($data);
    }

    /**
     * Converts variant payload into json string.
     */
    private function toJson(array $data): string
    {
        try {
            return \json_encode($data, \JSON_THROW_ON_ERROR | $this->jsonEncodingFlags, $this->jsonMaxDepth);
        } catch (\Throwable $e) {
            throw EncodingException::fromInternalEncodingError($e->getMessage(), (int)$e->getCode());
        }
    }

    public function decode(string $data): MessageInterface
    {
        $array = $this->fromJson($data);

        if (Signature::shouldValidate($this->signature)) {
            if (!\array_key_exists('jsonrpc', $array)) {
                throw RequiredFieldNotDefinedException::fromField('jsonrpc');
            }

            if ($array['jsonrpc'] !== '2.0') {
                throw InvalidFieldValueException::fromValueOfField('jsonrpc', '"2.0"', $array['jsonrpc']);
            }
        }

        // Check "method" field existing
        if (\array_key_exists('method', $array)) {
            try {
                /** @psalm-suppress InvalidArgument */
                return $this->tryDecodeRequest($array);
            } catch (DecodingExceptionInterface $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw DecodingException::fromInternalDecodingError($e->getMessage(), (int)$e->getCode());
            }
        }

        /** @psalm-suppress MixedArgumentTypeCoercion */
        return $this->tryDecodeResponse($array);
    }

    private function fromJson(string $json): array
    {
        try {
            $flags = \JSON_THROW_ON_ERROR | $this->jsonDecodingFlags;

            return (array)\json_decode($json, true, $this->jsonMaxDepth, $flags);
        } catch (\Throwable $e) {
            throw DecodingException::fromInternalDecodingError($e->getMessage(), (int)$e->getCode());
        }
    }

    /**
     * @param array{
     *  id?: mixed,
     *  method: mixed,
     *  params?: mixed
     * } $data
     *
     * @throws DecodingException
     * @throws \Throwable
     */
    private function tryDecodeRequest(array $data): NotificationInterface
    {
        // The "method" must be a string
        if (!\is_string($data['method']) || $data['method'] === '') {
            throw InvalidFieldTypeException::fromTypeOfField('method', 'non-empty-string', $data['method']);
        }

        // The "params" required
        if (!\array_key_exists('params', $data)) {
            $data['params'] = [];
        }

        // The "params" must be an array or object
        if (!\is_object($data['params']) && !\is_array($data['params'])) {
            throw InvalidFieldTypeException::fromTypeOfField('params', 'array|object', $data['params']);
        }

        if (\array_key_exists('id', $data)) {
            return $this->requests->createRequest(
                $data['method'],
                (array)$data['params'],
                $this->tryDecodeId($data['id']),
            );
        }

        return $this->requests->createNotification(
            $data['method'],
            (array)$data['params'],
        );
    }

    /**
     * @throws DecodingException
     */
    private function tryDecodeId(mixed $id): IdInterface
    {
        switch (true) {
            case $id === '':
                throw InvalidFieldTypeException::fromTypeOfField('id', 'non-empty-string', $id);
            case \is_string($id):
                /** @var non-empty-string $id */ return $this->ids->createFromString($id);
            case \is_int($id):
                return $this->ids->createFromInt($id);
            default:
                throw InvalidFieldTypeException::fromTypeOfField('id', 'int|string', $id);
        }
    }

    /**
     * @param array{
     *  id?: mixed,
     *  result?: mixed,
     *  error?: (mixed | array{
     *      code?: mixed,
     *      message?: mixed,
     *      data?: mixed
     *  })
     * } $data
     *
     * @return ResponseInterface
     *
     * @throws DecodingException
     */
    private function tryDecodeResponse(array $data): ResponseInterface
    {
        // The "id" required
        if (!\array_key_exists('id', $data)) {
            throw RequiredFieldNotDefinedException::fromField('id');
        }

        if (\array_key_exists('error', $data)) {
            /** @psalm-suppress InvalidArgument */
            return $this->tryDecodeErrorResponse($data);
        }

        /** @psalm-suppress InvalidArgument */
        return $this->tryDecodeSuccessResponse($data);
    }

    /**
     * @param array{
     *  id: mixed,
     *  error: mixed | array{
     *      code?: mixed,
     *      message?: mixed,
     *      data?: mixed
     *  }
     * } $data
     *
     * @throws DecodingException
     */
    private function tryDecodeErrorResponse(array $data): FailureResponseInterface
    {
        // The "error" must be an object
        if (!\is_array($data['error'])) {
            throw InvalidFieldTypeException::fromTypeOfField('error', 'object', $data['error']);
        }

        // The "error.code" must be an int
        if (\array_key_exists('code', $data['error']) && !\is_int($data['error']['code'])) {
            throw InvalidFieldTypeException::fromTypeOfField('error.code', 'int', $data['error']['code']);
        }

        // The "error.message" must be a string
        if (\array_key_exists('message', $data['error']) && !\is_string($data['error']['message'])) {
            throw InvalidFieldTypeException::fromTypeOfField('error.message', 'string', $data['error']['message']);
        }

        return $this->responses->createFailure(
            $this->tryDecodeNullableId($data['id'] ?? null),
            $data['error']['code'] ?? 0,
            $data['error']['message'] ?? '',
            $data['error']['data'] ?? null,
        );
    }

    /**
     * @throws DecodingException
     */
    private function tryDecodeNullableId(mixed $id): IdInterface
    {
        if ($id === null) {
            return $this->ids->createEmpty();
        }

        return $this->tryDecodeId($id);
    }

    /**
     * @param array{
     *  id: mixed,
     *  result?: mixed
     * } $data
     *
     * @throws DecodingException
     */
    private function tryDecodeSuccessResponse(array $data): SuccessfulResponseInterface
    {
        return $this->responses->createSuccess(
            $this->tryDecodeId($data['id']),
            $data['result'] ?? null,
        );
    }
}
