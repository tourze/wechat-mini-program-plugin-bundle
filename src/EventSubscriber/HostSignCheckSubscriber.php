<?php

namespace WechatMiniProgramPluginBundle\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tourze\JsonRPC\Core\Event\RequestStartEvent;
use Tourze\JsonRPC\Core\Exception\ApiException;
use WechatMiniProgramBundle\Repository\AccountRepository;
use Yiisoft\Json\Json;

/**
 * 微信小程序插件header检查
 *
 * @see https://developers.weixin.qq.com/miniprogram/dev/framework/plugin/development.html
 */
class HostSignCheckSubscriber
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AccountRepository $accountRepository,
    ) {
    }

    #[AsEventListener]
    public function onRequestStart(RequestStartEvent $event): void
    {
        $request = $event->getRequest();
        if ($request === null) {
            return;
        }

        $hostSign = $request->headers->get('X-WECHAT-HOSTSIGN');
        if ($hostSign === null) {
            $this->logger->debug('找不到X-WECHAT-HOSTSIGN，非微信小程序插件请求', [
                'request' => $request,
            ]);

            return;
        }

        // {"noncestr":"NONCESTR", "timestamp":"TIMESTAMP", "signature":"SIGNATURE"}
        $hostSign = Json::decode($hostSign);

        $referrer = $request->headers->get('referrer');
        preg_match('@https://servicewechat.com/(.*?)/(.*?)/page-frame.html@', $referrer, $match);
        if (empty($match)) {
            $this->logger->warning('有HOSTSIGN，但是找不到AppID，请求不合法', [
                'request' => $request,
                'referrer' => $referrer,
            ]);

            return;
        }

        $appId = $match[1];

        $account = $this->accountRepository->findOneBy(['appId' => $appId]);
        if ($account === null) {
            throw new ApiException('找不到小程序');
        }

        $list = [
            $account->getAppId(),
            $hostSign['noncestr'],
            $hostSign['timestamp'],
            $account->getPluginToken(),
        ];
        sort($list);
        $list = implode('', $list);
        $serverSign = sha1($list);
        $this->logger->debug('生成服务端签名字符串', [
            'str' => $list,
            'serverSign' => $serverSign,
            'requestSign' => $hostSign['signature'],
        ]);

        if ($hostSign['signature'] !== $serverSign) {
            throw new ApiException('非法请求，请检查插件配置');
        }
    }
}
