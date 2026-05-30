<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RouterSmokeTest extends TestCase
{
    public function testBasePathHelperExists(): void
    {
        self::assertTrue(function_exists('base_path'));
        self::assertSame(dirname(__DIR__), base_path());
    }
}
