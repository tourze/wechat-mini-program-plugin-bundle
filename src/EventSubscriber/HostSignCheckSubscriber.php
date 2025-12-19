<?php

namespace WechatMiniProgramPluginBundle\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Monolog\Attribute\WithMonologChannel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Tourze\JsonRPC\Core\Event\RequestStartEvent;
use WechatMiniProgramBundle\Repository\AccountRepository;
use WechatMiniProgramPluginBundle\Exception\HostSignValidationException;
use Yiisoft\Json\Json;

/**
 * 微信小程序插件header检查
 *
 * @see https://developers.weixin.qq.com/miniprogram/dev/framework/plugin/development.html
 */
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'wechat_mini_program_plugin')]
final class HostSignCheckSubscriber
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
        if (null === $request) {
            return;
        }

        $hostSign = $request->headers->get('X-WECHAT-HOSTSIGN');
        if (null === $hostSign) {
            $this->logger->debug('找不到X-WECHAT-HOSTSIGN，非微信小程序插件请求', [
                'request' => $request,
            ]);

            return;
        }

        $hostSignData = Json::decode($hostSign);
        if (
            !is_array($hostSignData)
            || !isset($hostSignData['noncestr'], $hostSignData['timestamp'], $hostSignData['signature'])
            || !is_string($hostSignData['noncestr'])
            || !is_string($hostSignData['timestamp'])
            || !is_string($hostSignData['signature'])
        ) {
            $this->logger->warning('HOSTSIGN格式不合法', [
                'hostSign' => $hostSign,
            ]);

            return;
        }

        $appId = $this->extractAppIdFromReferrer($request);

        if (null === $appId) {
            return;
        }

        $this->validateSignature($hostSignData, $appId);
    }

    private function extractAppIdFromReferrer(Request $request): ?string
    {
        $referrer = $request->headers->get('referrer');
        if (null === $referrer) {
            $this->logger->warning('有HOSTSIGN，但是找不到referrer，请求不合法', [
                'request' => $request,
            ]);

            return null;
        }

        $matches = [];
        $pattern = '@https://servicewechat.com/(.*?)/(.*?)/page-frame.html@';
        if (1 !== preg_match($pattern, $referrer, $matches)) {
            $this->logger->warning('有HOSTSIGN，但是找不到AppID，请求不合法', [
                'request' => $request,
                'referrer' => $referrer,
            ]);

            return null;
        }

        return $matches[1];
    }

    /**
     * @param array{noncestr: string, timestamp: string, signature: string} $hostSignData
     */
    private function validateSignature(array $hostSignData, string $appId): void
    {
        $account = $this->accountRepository->findOneBy(['appId' => $appId]);
        if (null === $account) {
            throw new HostSignValidationException('找不到小程序');
        }

        $list = [
            $account->getAppId(),
            $hostSignData['noncestr'],
            $hostSignData['timestamp'],
            $account->getPluginToken(),
        ];
        sort($list);
        $signatureString = implode('', $list);
        $serverSign = sha1($signatureString);

        $this->logger->debug('生成服务端签名字符串', [
            'str' => $signatureString,
            'serverSign' => $serverSign,
            'requestSign' => $hostSignData['signature'],
        ]);

        if ($hostSignData['signature'] !== $serverSign) {
            throw new HostSignValidationException('非法请求，请检查插件配置');
        }
    }
}
