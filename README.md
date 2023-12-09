# RPC JSON-RPC (v2) Protocol Implementation

---

<p align="center">
    <a href="https://packagist.org/packages/php-lsp/rpc-protocol-jsonrpc"><img src="https://poser.pugx.org/php-lsp/rpc-protocol-jsonrpc/require/php?style=for-the-badge" alt="PHP 8.1+"></a>
    <a href="https://packagist.org/packages/php-lsp/rpc-protocol-jsonrpc"><img src="https://poser.pugx.org/php-lsp/rpc-protocol-jsonrpc/version?style=for-the-badge" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/php-lsp/rpc-protocol-jsonrpc"><img src="https://poser.pugx.org/php-lsp/rpc-protocol-jsonrpc/v/unstable?style=for-the-badge" alt="Latest Unstable Version"></a>
    <a href="https://raw.githubusercontent.com/php-lsp/rpc-protocol-jsonrpc/blob/master/LICENSE"><img src="https://poser.pugx.org/php-lsp/rpc-protocol-jsonrpc/license?style=for-the-badge" alt="License MIT"></a>
</p>
<p align="center">
    <a href="https://github.com/php-lsp/rpc-protocol-jsonrpc/actions"><img src="https://github.com/php-lsp/rpc-protocol-jsonrpc/workflows/tests/badge.svg"></a>
</p>

A set of classes for the JSON-RPC protocol implementation.

## Requirements

- PHP 8.1+

## Installation

- Add `php-lsp/rpc-protocol-jsonrpc` as composer dependency.

```json
{
    "require": {
        "php-lsp/rpc-protocol-jsonrpc": "^1.0"
    }
}
```

## Usage

### Decoding

```php
$protocol = new Lsp\Protocol\JsonRPCv2();

$notification = $protocol->decode('{"jsonrpc": "2.0", "method": "test"}');
//
// Expected Output:
//
// Lsp\Message\Notification {
//   #method: "test"
//   #parameters: []
// }
//

$request = $protocol->decode('{"jsonrpc": "2.0", "method": "test", "id": 42}');
//
// Expected Output:
//
// Lsp\Message\Request {
//   #method: "test"
//   #parameters: []
//   #id: Lsp\Message\IntIdentifier {
//     -value: 42
//   }
// }
//

$success = $protocol->decode('{"jsonrpc": "2.0", "data": "test", "id": 42}');
//
// Expected Output:
//
// Lsp\Message\SuccessfulResponse {
//   #id: Lsp\Message\IntIdentifier {
//     -value: 42
//   }
//   #result: null
// }
//

$failure = $protocol->decode('{"jsonrpc": "2.0", "error": {"message": "fail"}, "id": 42}');
//
// Expected Output:
//
// Lsp\Message\FailureResponse {
//   #id: Lsp\Message\IntIdentifier {
//     -value: 42
//   }
//   #code: 0
//   #message: "fail"
//   #data: null
// }
//
```

#### Decoding Flags

```php
// Allow no protocol version ("jsonrpc" key).
$protocol = new \Lsp\Protocol\JsonRPCv2(
    signature: \Lsp\Protocol\JsonRPC\Signature::NO_VALIDATE,
    // or signature: \Lsp\Protocol\JsonRPC\Signature::NONE,
);

// Set JSON depth limit
$protocol = new \Lsp\Protocol\JsonRPCv2(
    jsonMaxDepth: 1
);

// Add JSON_* decoding options
$protocol = new \Lsp\Protocol\JsonRPCv2(
    jsonDecodingFlags: \JSON_NUMERIC_CHECK,
);
```

### Encoding

```php
use Lsp\Message\EmptyIdentifier;
use Lsp\Message\Request;
use Lsp\Protocol\JsonRPCv2;

$protocol = new JsonRPCv2();

$json = $protocol->encode(new Request(
    id: new StringIdentifier('test'),
    method: 'method',
));

//
// Expected Output:
//
// {"id": "test", "method": "method", "params": [], "jsonrpc": "2.0"}
//
```

#### Encoding Flags

```php
// Do not insert protocol version ("jsonrpc" key).
$protocol = new \Lsp\Protocol\JsonRPCv2(
    signature: \Lsp\Protocol\JsonRPC\Signature::NO_INSERT,
    // or signature: \Lsp\Protocol\JsonRPC\Signature::NONE,
);
// Expected Behaviour:
// - ALL (by default):    {"id": "test", "method": "method", "params": [], "jsonrpc": "2.0"}
// - NO_INSERT or NONE:   {"id": "test", "method": "method", "params": []}


// Add JSON_* encoding options
$protocol = new \Lsp\Protocol\JsonRPCv2(
    jsonEncodingFlags: \JSON_PRETTY_PRINT,
);
```
