<?php

namespace WechatMiniProgramPluginBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use WechatMiniProgramPluginBundle\Exception\HostSignValidationException;

/**
 * @internal
 */
#[CoversClass(HostSignValidationException::class)]
final class HostSignValidationExceptionTest extends AbstractExceptionTestCase
{
    public function testInheritance(): void
    {
        $exception = new HostSignValidationException('Test message', 500);

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Test message', $exception->getMessage());
        $this->assertSame(500, $exception->getCode());
    }
}
