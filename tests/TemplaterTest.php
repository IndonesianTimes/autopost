<?php

declare(strict_types=1);

namespace Tests;

use App\Templater;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class TemplaterTest extends TestCase
{
    private string $variantFile;

    protected function setUp(): void
    {
        $this->variantFile = dirname(__DIR__) . '/storage/ab_variant_abtest.txt';
        if (file_exists($this->variantFile)) {
            unlink($this->variantFile);
        }
        $ref = new ReflectionClass(Templater::class);
        $prop = $ref->getProperty('templates');
        $prop->setAccessible(true);
        $prop->setValue([
            'greeting' => [
                'A' => 'A {{name}}',
                'B' => 'B {{name}}',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->variantFile)) {
            unlink($this->variantFile);
        }
    }

    public function testRoundRobinVariant(): void
    {
        $data = ['name' => 'John', 'job' => ['platform' => 'abtest']];
        $first = Templater::render('greeting', $data);
        $second = Templater::render('greeting', $data);
        $this->assertSame('A John', $first);
        $this->assertSame('B John', $second);
    }
}
