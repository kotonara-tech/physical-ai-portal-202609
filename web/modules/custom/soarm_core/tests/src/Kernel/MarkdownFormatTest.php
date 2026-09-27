<?php

declare(strict_types=1);

namespace Drupal\Tests\soarm_core\Kernel;

use Drupal\filter\Entity\FilterFormat;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;

/**
 * Tests the soarm_markdown text format end to end.
 */
#[Group('soarm_core')]
#[Medium]
final class MarkdownFormatTest extends SoarmCoreKernelTestBase {

  /**
   * Tests that the soarm_markdown format has the Markdown filter enabled.
   */
  public function testFormatUsesTheMarkdownFilter(): void {
    $format = FilterFormat::load('soarm_markdown');
    $this->assertNotNull($format);
    $this->assertTrue($format->filters('soarm_markdown')->status);
  }

  /**
   * Tests that check_markup() renders Markdown and leaves no raw script tag.
   */
  public function testCheckMarkupRendersMarkdownSafely(): void {
    $html = (string) check_markup("## Steps\n\n<script>alert(1)</script>", 'soarm_markdown');

    $this->assertStringContainsString('<h2>Steps</h2>', $html);
    $this->assertStringNotContainsString('<script', $html);
  }

}
