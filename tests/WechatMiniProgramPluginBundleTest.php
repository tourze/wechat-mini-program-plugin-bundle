<?php

namespace WechatMiniProgramPluginBundle\Tests;

use PHPUnit\Framework\TestCase;
use WechatMiniProgramPluginBundle\WechatMiniProgramPluginBundle;

class WechatMiniProgramPluginBundleTest extends TestCase
{
    public function testBundleInstanceCreation(): void
    {
        $bundle = new WechatMiniProgramPluginBundle();
        $this->assertInstanceOf(WechatMiniProgramPluginBundle::class, $bundle);
    }
} 