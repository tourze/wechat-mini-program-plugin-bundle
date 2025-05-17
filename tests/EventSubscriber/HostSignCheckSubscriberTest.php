<?php

namespace WechatMiniProgramPluginBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Tourze\JsonRPC\Core\Event\RequestStartEvent;
use Tourze\JsonRPC\Core\Exception\ApiException;
use WechatMiniProgramBundle\Entity\Account;
use WechatMiniProgramBundle\Repository\AccountRepository;
use WechatMiniProgramPluginBundle\EventSubscriber\HostSignCheckSubscriber;
use Yiisoft\Json\Json;

class HostSignCheckSubscriberTest extends TestCase
{
    private LoggerInterface $logger;
    private AccountRepository $accountRepository;
    private HostSignCheckSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->accountRepository = $this->createMock(AccountRepository::class);
        $this->subscriber = new HostSignCheckSubscriber(
            $this->logger,
            $this->accountRepository
        );
    }

    public function testOnRequestStart_NoRequest(): void
    {
        $event = $this->createMock(RequestStartEvent::class);
        $event->method('getRequest')->willReturn(null);
        
        $this->logger->expects($this->never())->method('debug');
        
        $this->subscriber->onRequestStart($event);
    }

    public function testOnRequestStart_NoHostSign(): void
    {
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);
        $headers->method('get')->with('X-WECHAT-HOSTSIGN')->willReturn(null);
        $request->headers = $headers;
        
        $event = $this->createMock(RequestStartEvent::class);
        $event->method('getRequest')->willReturn($request);
        
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('找不到X-WECHAT-HOSTSIGN，非微信小程序插件请求', $this->anything());
        
        $this->subscriber->onRequestStart($event);
    }

    public function testOnRequestStart_InvalidReferrer(): void
    {
        $hostSignData = [
            'noncestr' => 'test-nonce',
            'timestamp' => '1234567890',
            'signature' => 'test-signature'
        ];
        $hostSignJson = Json::encode($hostSignData);
        
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);
        $headers->method('get')
            ->willReturnCallback(function ($key) use ($hostSignJson) {
                if ($key === 'X-WECHAT-HOSTSIGN') {
                    return $hostSignJson;
                }
                if ($key === 'referrer') {
                    return 'https://invalid-url.com';
                }
                return null;
            });
        $request->headers = $headers;
        
        $event = $this->createMock(RequestStartEvent::class);
        $event->method('getRequest')->willReturn($request);
        
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('有HOSTSIGN，但是找不到AppID，请求不合法', $this->anything());
        
        $this->subscriber->onRequestStart($event);
    }

    public function testOnRequestStart_AccountNotFound(): void
    {
        $hostSignData = [
            'noncestr' => 'test-nonce',
            'timestamp' => '1234567890',
            'signature' => 'test-signature'
        ];
        $hostSignJson = Json::encode($hostSignData);
        
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);
        $headers->method('get')
            ->willReturnCallback(function ($key) use ($hostSignJson) {
                if ($key === 'X-WECHAT-HOSTSIGN') {
                    return $hostSignJson;
                }
                if ($key === 'referrer') {
                    return 'https://servicewechat.com/wx123456/1/page-frame.html';
                }
                return null;
            });
        $request->headers = $headers;
        
        $event = $this->createMock(RequestStartEvent::class);
        $event->method('getRequest')->willReturn($request);
        
        $this->accountRepository->method('findOneBy')
            ->with(['appId' => 'wx123456'])
            ->willReturn(null);
        
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('找不到小程序');
        
        $this->subscriber->onRequestStart($event);
    }

    public function testOnRequestStart_InvalidSignature(): void
    {
        $hostSignData = [
            'noncestr' => 'test-nonce',
            'timestamp' => '1234567890',
            'signature' => 'invalid-signature'
        ];
        $hostSignJson = Json::encode($hostSignData);
        
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);
        $headers->method('get')
            ->willReturnCallback(function ($key) use ($hostSignJson) {
                if ($key === 'X-WECHAT-HOSTSIGN') {
                    return $hostSignJson;
                }
                if ($key === 'referrer') {
                    return 'https://servicewechat.com/wx123456/1/page-frame.html';
                }
                return null;
            });
        $request->headers = $headers;
        
        $event = $this->createMock(RequestStartEvent::class);
        $event->method('getRequest')->willReturn($request);
        
        $account = $this->createMock(Account::class);
        $account->method('getAppId')->willReturn('wx123456');
        $account->method('getPluginToken')->willReturn('plugin-token');
        
        $this->accountRepository->method('findOneBy')
            ->with(['appId' => 'wx123456'])
            ->willReturn($account);
        
        // 计算正确的签名
        $list = ['wx123456', 'test-nonce', '1234567890', 'plugin-token'];
        sort($list);
        $signStr = implode('', $list);
        $serverSign = sha1($signStr);
        
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('生成服务端签名字符串', $this->callback(function ($data) use ($serverSign) {
                return $data['serverSign'] === $serverSign && $data['requestSign'] === 'invalid-signature';
            }));
        
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('非法请求，请检查插件配置');
        
        $this->subscriber->onRequestStart($event);
    }

    public function testOnRequestStart_ValidSignature(): void
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
        
        $hostSignData = [
            'noncestr' => $nonceStr,
            'timestamp' => $timestamp,
            'signature' => $validSignature
        ];
        $hostSignJson = Json::encode($hostSignData);
        
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);
        $headers->method('get')
            ->willReturnCallback(function ($key) use ($hostSignJson, $appId) {
                if ($key === 'X-WECHAT-HOSTSIGN') {
                    return $hostSignJson;
                }
                if ($key === 'referrer') {
                    return "https://servicewechat.com/{$appId}/1/page-frame.html";
                }
                return null;
            });
        $request->headers = $headers;
        
        $event = $this->createMock(RequestStartEvent::class);
        $event->method('getRequest')->willReturn($request);
        
        $account = $this->createMock(Account::class);
        $account->method('getAppId')->willReturn($appId);
        $account->method('getPluginToken')->willReturn($pluginToken);
        
        $this->accountRepository->method('findOneBy')
            ->with(['appId' => $appId])
            ->willReturn($account);
        
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('生成服务端签名字符串', $this->callback(function ($data) use ($validSignature) {
                return $data['serverSign'] === $validSignature && $data['requestSign'] === $validSignature;
            }));
        
        // 不应抛出异常
        $this->subscriber->onRequestStart($event);
        $this->assertTrue(true);
    }
} 