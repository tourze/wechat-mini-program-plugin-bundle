# WeChat Mini Program Plugin Bundle

[English](README.md) | [中文](README.zh-CN.md)

A Symfony bundle for WeChat Mini Program plugin request signature verification.

## Installation

Install the package via Composer:

```bash
composer require tourze/wechat-mini-program-plugin-bundle
```

## Quick Start

1. Add the bundle to your `config/bundles.php`:

```php
<?php

return [
    // ... other bundles
    WechatMiniProgramPluginBundle\WechatMiniProgramPluginBundle::class => ['all' => true],
];
```

2. The bundle will automatically register the `HostSignCheckSubscriber` to verify WeChat Mini Program plugin requests.

## Features

- **Request Signature Verification**: Automatically validates `X-WECHAT-HOSTSIGN` header
- **AppID Extraction**: Extracts AppID from referrer URL
- **Security**: Ensures request authenticity using SHA1 signature verification
- **Logging**: Comprehensive logging for debugging and monitoring

## Usage

The bundle automatically handles WeChat Mini Program plugin request verification through the `HostSignCheckSubscriber`. When a request contains the `X-WECHAT-HOSTSIGN` header, the subscriber will:

1. Extract the signature data from the header
2. Parse the AppID from the referrer URL
3. Verify the signature using the configured plugin token
4. Throw an exception if the signature is invalid

### Example Request Headers

```
X-WECHAT-HOSTSIGN: {"noncestr":"RANDOM_STRING", "timestamp":"1234567890", "signature":"SHA1_SIGNATURE"}
Referrer: https://servicewechat.com/wx1234567890abcdef/1/page-frame.html
```

## Configuration

The bundle requires the `tourze/wechat-mini-program-bundle` package to be properly configured with WeChat Mini Program accounts and plugin tokens.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
