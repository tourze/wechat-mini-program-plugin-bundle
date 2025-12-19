<?php

namespace WechatMiniProgramPluginBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Tourze\JsonRPC\Core\Event\RequestStartEvent;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramBundle\Repository\AccountRepository;
use WechatMiniProgramPluginBundle\EventSubscriber\HostSignCheckSubscriber;
use WechatMiniProgramPluginBundle\Exception\HostSignValidationException;
use Yiisoft\Json\Json;

/**
 * @internal
 */
#[CoversClass(HostSignCheckSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class HostSignCheckSubscriberTest extends AbstractEventSubscriberTestCase
{
    private HostSignCheckSubscriber $subscriber;

    private AccountRepository $accountRepository;

    protected function onSetUp(): void
    {
        // 使用真实的AccountRepository服务
        $this->accountRepository = self::getService(AccountRepository::class);
        $this->subscriber = self::getService(HostSignCheckSubscriber::class);
    }

    /**
     * 创建测试用的Account实体
     */
    private function createTestAccount(string $appId, ?string $pluginToken = null): Account
    {
        $account = new Account();
        $account->setName("测试小程序-{$appId}");
        $account->setAppId($appId);
        $account->setAppSecret('test-secret');
        $account->setValid(true);
        if (null !== $pluginToken) {
            $account->setPluginToken($pluginToken);
        }

        return $this->persistAndFlush($account);
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(HostSignCheckSubscriber::class, $this->subscriber);
    }

    public function testOnRequestStartNoRequest(): void
    {
        $event = new RequestStartEvent();

        $this->subscriber->onRequestStart($event);

        // 验证在没有请求时不会进行后续处理（没有异常抛出即为成功）
        $this->expectNotToPerformAssertions();
    }

    public function testOnRequestStartNoHostSign(): void
    {
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);
        $headers->method('get')->with('X-WECHAT-HOSTSIGN')->willReturn(null);
        $request->headers = $headers;

        $event = new RequestStartEvent($request);

        $this->subscriber->onRequestStart($event);

        // 验证在没有 HostSign 头时不会进行后续处理（没有异常抛出即为成功）
    }

    public function testOnRequestStartInvalidReferrer(): void
    {
        $hostSignData = [
            'noncestr' => 'test-nonce',
            'timestamp' => '1234567890',
            'signature' => 'test-signature',
        ];
        $hostSignJson = Json::encode($hostSignData);

        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);
        $headers->method('get')
            ->willReturnCallback(function ($key) use ($hostSignJson) {
                if ('X-WECHAT-HOSTSIGN' === $key) {
                    return $hostSignJson;
                }
                if ('referrer' === $key) {
                    return 'https://invalid-url.com';
                }

                return null;
            })
        ;
        $request->headers = $headers;

        $event = new RequestStartEvent($request);

        $this->subscriber->onRequestStart($event);

        // 验证在 referrer 无效时不会进行后续处理（没有异常抛出即为成功）
        $this->expectNotToPerformAssertions();
    }

    public function testOnRequestStartAccountNotFound(): void
    {
        $hostSignData = [
            'noncestr' => 'test-nonce',
            'timestamp' => '1234567890',
            'signature' => 'test-signature',
        ];
        $hostSignJson = Json::encode($hostSignData);

        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);
        $headers->method('get')
            ->willReturnCallback(function ($key) use ($hostSignJson) {
                if ('X-WECHAT-HOSTSIGN' === $key) {
                    return $hostSignJson;
                }
                if ('referrer' === $key) {
                    return 'https://servicewechat.com/wx123456/1/page-frame.html';
                }

                return null;
            })
        ;
        $request->headers = $headers;

        $event = new RequestStartEvent($request);

        $this->expectException(HostSignValidationException::class);
        $this->expectExceptionMessage('找不到小程序');

        $this->subscriber->onRequestStart($event);
    }

    public function testOnRequestStartInvalidSignature(): void
    {
        // 创建测试账号，设置pluginToken
        $this->createTestAccount('wx123456', 'plugin-token');

        $hostSignData = [
            'noncestr' => 'test-nonce',
            'timestamp' => '1234567890',
            'signature' => 'invalid-signature',
        ];
        $hostSignJson = Json::encode($hostSignData);

        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);
        $headers->method('get')
            ->willReturnCallback(function ($key) use ($hostSignJson) {
                if ('X-WECHAT-HOSTSIGN' === $key) {
                    return $hostSignJson;
                }
                if ('referrer' === $key) {
                    return 'https://servicewechat.com/wx123456/1/page-frame.html';
                }

                return null;
            })
        ;
        $request->headers = $headers;

        $event = new RequestStartEvent($request);

        $this->expectException(HostSignValidationException::class);
        $this->expectExceptionMessage('非法请求，请检查插件配置');

        $this->subscriber->onRequestStart($event);
    }

    public function testOnRequestStartValidSignature(): void
    {
        // 创建账号和签名相关数据
        $appId = 'wx123456';
        $nonceStr = 'test-nonce';
        $timestamp = '1234567890';
        $pluginToken = 'plugin-token';

        // 计算有效签名
        $list = [$appId, $nonceStr, $timestamp, $pluginToken];
        sort($list);
        $signStr = implode('', $list);
        $validSignature = sha1($signStr);

        // 创建测试账号
        $this->createTestAccount($appId, $pluginToken);

        $hostSignData = [
            'noncestr' => $nonceStr,
            'timestamp' => $timestamp,
            'signature' => $validSignature,
        ];
        $hostSignJson = Json::encode($hostSignData);

        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);
        $headers->method('get')
            ->willReturnCallback(function ($key) use ($hostSignJson, $appId) {
                if ('X-WECHAT-HOSTSIGN' === $key) {
                    return $hostSignJson;
                }
                if ('referrer' === $key) {
                    return "https://servicewechat.com/{$appId}/1/page-frame.html";
                }

                return null;
            })
        ;
        $request->headers = $headers;

        $event = new RequestStartEvent($request);

        // 执行签名验证，应该不抛出异常
        $this->expectNotToPerformAssertions();
        $this->subscriber->onRequestStart($event);
    }
}
