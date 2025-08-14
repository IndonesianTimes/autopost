<?php

declare(strict_types=1);

namespace Tests;

use App\Helpers\Sanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SanitizerTest extends TestCase
{
    public function testSanitizeCaptionTruncatesAndPreservesPlaceholder(): void
    {
        $caption = "Hello   \r\nworld {{link_url}} tail";
        $sanitized = Sanitizer::sanitizeCaption($caption, 20);
        $this->assertSame("Hello\nwo{{link_url}}", $sanitized);
    }

    public function testValidateImageUrlInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Sanitizer::validateImageUrl('ftp://example.com/image.bmp');
    }
}
