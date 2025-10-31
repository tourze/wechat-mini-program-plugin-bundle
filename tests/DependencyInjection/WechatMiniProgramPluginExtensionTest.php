<?php

namespace WechatMiniProgramPluginBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;
use WechatMiniProgramPluginBundle\DependencyInjection\WechatMiniProgramPluginExtension;
use WechatMiniProgramPluginBundle\EventSubscriber\HostSignCheckSubscriber;

/**
 * @internal
 */
#[CoversClass(WechatMiniProgramPluginExtension::class)]
final class WechatMiniProgramPluginExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private WechatMiniProgramPluginExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension = new WechatMiniProgramPluginExtension();
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'test');
    }

    protected function getExtension(): WechatMiniProgramPluginExtension
    {
        return $this->extension;
    }

    protected function getContainer(): ContainerBuilder
    {
        return $this->container;
    }

    public function testLoadWithEmptyConfiguration(): void
    {
        // 加载扩展配置（空配置）
        $this->extension->load([], $this->container);

        // 验证即使配置为空，加载过程也能成功并注册核心服务
        $this->assertTrue($this->container->hasDefinition(HostSignCheckSubscriber::class));

        // 验证服务定义正确
        $serviceDefinition = $this->container->getDefinition(HostSignCheckSubscriber::class);
        $this->assertTrue($serviceDefinition->isAutowired());
        $this->assertTrue($serviceDefinition->isAutoconfigured());
    }
}
