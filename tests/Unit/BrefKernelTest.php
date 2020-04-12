<?php

declare(strict_types=1);

namespace Bref\SymfonyBridge\Test\Unit;

use Bref\SymfonyBridge\Test\TestKernel;
use PHPUnit\Framework\TestCase;

class BrefKernelTest extends TestCase
{
    public function testIsLambda()
    {
        $kernel = new TestKernel();
        $this->assertFalse($kernel->isLambda());

        putenv('LAMBDA_TASK_ROOT=/var/task');
        $this->assertTrue($kernel->isLambda());
    }
}
