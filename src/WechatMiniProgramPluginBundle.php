<?php

namespace WechatMiniProgramPluginBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use WechatMiniProgramBundle\WechatMiniProgramBundle;

final class WechatMiniProgramPluginBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            WechatMiniProgramBundle::class => ['all' => true],
        ];
    }
}
