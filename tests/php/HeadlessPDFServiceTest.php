<?php

namespace ATWX\HeadlessPDF\Tests;

use ATWX\HeadlessPDF\Services\HeadlessPDFService;
use SilverStripe\Dev\SapphireTest;

class HeadlessPDFServiceTest extends SapphireTest
{
    public function testDefaultConfigValues(): void
    {
        $service = HeadlessPDFService::create();

        $this->assertNotEmpty(
            $service->config()->get('chrome_binary'),
            'chrome_binary config should not be empty'
        );

        $args = $service->config()->get('chrome_args');
        $this->assertIsArray($args, 'chrome_args should be an array');
        $this->assertNotEmpty($args, 'chrome_args should not be empty');

        $timeout = $service->config()->get('timeout');
        $this->assertGreaterThan(0, $timeout, 'timeout should be a positive integer');
    }

    public function testValidateUrlRejectsNonHttpSchemes(): void
    {
        $service = HeadlessPDFService::create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/scheme.*not allowed/i');

        // Use reflection to call the protected validateUrl method.
        $method = new \ReflectionMethod(HeadlessPDFService::class, 'validateUrl');
        $method->setAccessible(true);
        $method->invoke($service, 'file:///etc/passwd');
    }

    public function testValidateUrlRejectsInvalidUrl(): void
    {
        $service = HeadlessPDFService::create();

        $this->expectException(\InvalidArgumentException::class);

        $method = new \ReflectionMethod(HeadlessPDFService::class, 'validateUrl');
        $method->setAccessible(true);
        $method->invoke($service, 'not-a-url');
    }

    public function testValidateUrlAcceptsHttpAndHttps(): void
    {
        $service = HeadlessPDFService::create();
        $method = new \ReflectionMethod(HeadlessPDFService::class, 'validateUrl');
        $method->setAccessible(true);

        // Should not throw.
        $method->invoke($service, 'http://example.com/page');
        $method->invoke($service, 'https://example.com/page');

        // If we get here, no exception was thrown.
        $this->assertTrue(true);
    }
}
