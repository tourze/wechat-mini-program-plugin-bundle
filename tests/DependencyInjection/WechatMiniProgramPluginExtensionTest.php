<?php

namespace WechatMiniProgramPluginBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use WechatMiniProgramPluginBundle\DependencyInjection\WechatMiniProgramPluginExtension;
use WechatMiniProgramPluginBundle\EventSubscriber\HostSignCheckSubscriber;

class WechatMiniProgramPluginExtensionTest extends TestCase
{
    public function testLoad(): void
    {
        $container = new ContainerBuilder();
        $extension = new WechatMiniProgramPluginExtension();
        
        $extension->load([], $container);
        
        // 验证服务是否已注册
        $this->assertTrue($container->hasDefinition(HostSignCheckSubscriber::class));
        
        // 验证服务的自动装配和自动配置
        $definition = $container->getDefinition(HostSignCheckSubscriber::class);
        $this->assertTrue($definition->isAutowired());
        $this->assertTrue($definition->isAutoconfigured());
    }
    
    public function testLoadWithEmptyConfiguration(): void
    {
        $container = new ContainerBuilder();
        $extension = new WechatMiniProgramPluginExtension();
        
        $extension->load([], $container);
        
        // 验证即使配置为空，加载过程也不应抛出异常
        $this->assertTrue(true);
    }
} 