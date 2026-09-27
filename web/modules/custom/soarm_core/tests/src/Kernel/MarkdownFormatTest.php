<?php

declare(strict_types=1);

namespace Drupal\Tests\soarm_core\Kernel;

use Drupal\filter\Entity\FilterFormat;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the soarm_markdown text format end to end.
 */
#[Group('soarm_core')]
final class MarkdownFormatTest extends SoarmCoreKernelTestBase {

  public function testFormatUsesTheMarkdownFilter(): void {
    $format = FilterFormat::load('soarm_markdown');
    $this->assertNotNull($format);
    $this->assertTrue($format->filters('soarm_markdown')->status);
  }

  public function testCheckMarkupRendersMarkdownSafely(): void {
    $html = (string) check_markup("## Steps\n\n<script>alert(1)</script>", 'soarm_markdown');

    $this->assertStringContainsString('<h2>Steps</h2>', $html);
    $this->assertStringNotContainsString('<script', $html);
  }

}
