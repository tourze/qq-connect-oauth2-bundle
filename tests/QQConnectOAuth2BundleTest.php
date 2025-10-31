<?php

declare(strict_types=1);

namespace Tourze\QQConnectOAuth2Bundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;
use Tourze\QQConnectOAuth2Bundle\QQConnectOAuth2Bundle;

/**
 * @internal
 */
#[CoversClass(QQConnectOAuth2Bundle::class)]
#[RunTestsInSeparateProcesses]
final class QQConnectOAuth2BundleTest extends AbstractBundleTestCase
{
}
