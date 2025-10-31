# 微信小程序插件 Bundle

[English](README.md) | [中文](README.zh-CN.md)

用于微信小程序插件请求签名验证的 Symfony Bundle。

## 安装

通过 Composer 安装：

```bash
composer require tourze/wechat-mini-program-plugin-bundle
```

## 快速开始

1. 将 Bundle 添加到 `config/bundles.php`：

```php
<?php

return [
    // ... 其他 bundles
    WechatMiniProgramPluginBundle\WechatMiniProgramPluginBundle::class => ['all' => true],
];
```

2. Bundle 会自动注册 `HostSignCheckSubscriber` 来验证微信小程序插件请求。

## 功能特性

- **请求签名验证**：自动验证 `X-WECHAT-HOSTSIGN` 头部信息
- **AppID 提取**：从 referrer URL 中提取 AppID
- **安全性**：使用 SHA1 签名验证确保请求真实性
- **日志记录**：全面的日志记录，便于调试和监控

## 使用方法

Bundle 通过 `HostSignCheckSubscriber` 自动处理微信小程序插件请求验证。当请求包含 `X-WECHAT-HOSTSIGN` 头部时，订阅者会：

1. 从头部提取签名数据
2. 从 referrer URL 解析 AppID
3. 使用配置的插件 token 验证签名
4. 如果签名无效则抛出异常

### 请求头示例

```
X-WECHAT-HOSTSIGN: {"noncestr":"RANDOM_STRING", "timestamp":"1234567890", "signature":"SHA1_SIGNATURE"}
Referrer: https://servicewechat.com/wx1234567890abcdef/1/page-frame.html
```

## 配置

Bundle 需要 `tourze/wechat-mini-program-bundle` 包被正确配置，包含微信小程序账户和插件 token。

## 许可证

本项目基于 MIT 许可证 - 详情请参阅 [LICENSE](LICENSE) 文件。
