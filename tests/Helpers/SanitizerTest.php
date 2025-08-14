<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Helpers\Sanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SanitizerTest extends TestCase
{
    public function testSanitizeCaptionTruncatesAndPreservesPlaceholder(): void
    {
        $caption = "Hello   \r\nworld {{link_url}} tail";
        $sanitized = Sanitizer::sanitizeCaption($caption, 20);
        $this->assertSame("Hello\nwo{{link_url}}", $sanitized);
        $this->assertSame(20, mb_strlen($sanitized));
    }

    public function testValidateImageUrl(): void
    {
        $url = 'http://example.com/a.jpg';
        $this->assertSame($url, Sanitizer::validateImageUrl($url));
    }

    public function testValidateImageUrlInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Sanitizer::validateImageUrl('ftp://example.com/image.bmp');
    }
}
