<?php

namespace WechatMiniProgramPluginBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;
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
        // 创建AccountRepository的Mock用于测试
        $this->accountRepository = $this->createMock(AccountRepository::class);

        // 在容器中替换AccountRepository服务
        self::getContainer()->set(AccountRepository::class, $this->accountRepository);

        // 从容器获取被测试的事件订阅服务实例（会自动注入Mock的AccountRepository）
        $this->subscriber = self::getService(HostSignCheckSubscriber::class);
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(HostSignCheckSubscriber::class, $this->subscriber);
    }

    public function testOnRequestStartNoRequest(): void
    {
        $event = $this->createMock(RequestStartEvent::class);
        $event->method('getRequest')->willReturn(null);

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

        $event = $this->createMock(RequestStartEvent::class);
        $event->method('getRequest')->willReturn($request);

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

        $event = $this->createMock(RequestStartEvent::class);
        $event->method('getRequest')->willReturn($request);

        $this->subscriber->onRequestStart($event);

        // 验证在 referrer 无效时不会进行后续处理（没有异常抛出即为成功）
        $this->expectNotToPerformAssertions();
    }

    public function testOnRequestStartAccountNotFound(): void
    {
        // 配置 AccountRepository Mock 返回 null
        /** @var InvocationMocker $findOneByMethod */
        $findOneByMethod = $this->accountRepository->method('findOneBy');
        $findOneByMethod->with(['appId' => 'wx123456']);
        $findOneByMethod->willReturn(null);

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

        $event = $this->createMock(RequestStartEvent::class);
        $event->method('getRequest')->willReturn($request);

        $this->expectException(HostSignValidationException::class);
        $this->expectExceptionMessage('找不到小程序');

        $this->subscriber->onRequestStart($event);
    }

    public function testOnRequestStartInvalidSignature(): void
    {
        // 配置 AccountRepository Mock 返回模拟的 Account
        $account = $this->createMock(Account::class);
        $account->method('getAppId')->willReturn('wx123456');
        $account->method('getPluginToken')->willReturn('plugin-token');

        /** @var InvocationMocker $findOneByMethod */
        $findOneByMethod = $this->accountRepository->method('findOneBy');
        $findOneByMethod->with(['appId' => 'wx123456']);
        $findOneByMethod->willReturn($account);

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

        $event = $this->createMock(RequestStartEvent::class);
        $event->method('getRequest')->willReturn($request);

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

        // 配置 AccountRepository Mock 返回模拟的 Account
        $account = $this->createMock(Account::class);
        $account->method('getAppId')->willReturn($appId);
        $account->method('getPluginToken')->willReturn($pluginToken);

        /** @var InvocationMocker $findOneByMethod */
        $findOneByMethod = $this->accountRepository->method('findOneBy');
        $findOneByMethod->with(['appId' => $appId]);
        $findOneByMethod->willReturn($account);

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

        $event = $this->createMock(RequestStartEvent::class);
        // 验证：event.getRequest() 应该被调用一次
        $event->expects($this->once())
            ->method('getRequest')
            ->willReturn($request)
        ;

        // 执行签名验证
        $this->subscriber->onRequestStart($event);
    }
}
