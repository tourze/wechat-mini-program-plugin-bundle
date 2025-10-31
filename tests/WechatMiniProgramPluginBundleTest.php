<?php

declare(strict_types=1);

namespace WechatMiniProgramPluginBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;
use WechatMiniProgramPluginBundle\WechatMiniProgramPluginBundle;

/**
 * @internal
 */
#[CoversClass(WechatMiniProgramPluginBundle::class)]
#[RunTestsInSeparateProcesses]
final class WechatMiniProgramPluginBundleTest extends AbstractBundleTestCase
{
    public function testBundleCanBeRegisteredInContainer(): void
    {
        $container = new ContainerBuilder();
        $bundleClass = self::getBundleClass();
        $this->assertIsString($bundleClass, 'Bundle class should be a string');

        /** @var WechatMiniProgramPluginBundle $bundle */
        $bundle = new $bundleClass();
        $this->assertInstanceOf(WechatMiniProgramPluginBundle::class, $bundle);

        // Bundle 应该能够正确构建
        $bundle->build($container);

        // 验证 Bundle 名称
        $this->assertEquals('WechatMiniProgramPluginBundle', $bundle->getName());
    }

    public function testBundleHasCorrectPath(): void
    {
        $bundleClass = self::getBundleClass();
        $this->assertIsString($bundleClass, 'Bundle class should be a string');

        /** @var WechatMiniProgramPluginBundle $bundle */
        $bundle = new $bundleClass();
        $this->assertInstanceOf(WechatMiniProgramPluginBundle::class, $bundle);

        $path = $bundle->getPath();

        $this->assertStringContainsString('wechat-mini-program-plugin-bundle', $path);
        $this->assertDirectoryExists($path);
    }
}
