<?php

declare(strict_types=1);

namespace Drupal\Tests\soarm_core\Unit;

use Drupal\soarm_core\Markdown\SafeMarkdownConverter;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;

/**
 * Tests Markdown to safe HTML conversion for the procedure field.
 */
#[CoversClass(SafeMarkdownConverter::class)]
#[Group('soarm_core')]
#[Small]
final class SafeMarkdownConverterTest extends UnitTestCase {

  /**
   * The converter under test.
   */
  private SafeMarkdownConverter $converter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->converter = new SafeMarkdownConverter();
  }

  /**
   * Tests that headings and ordered lists are converted to HTML.
   */
  public function testHeadingsAndOrderedListsAreConverted(): void {
    $html = $this->converter->toHtml("## Steps\n\n1. grasp\n2. move\n3. release");

    $this->assertStringContainsString('<h2>Steps</h2>', $html);
    $this->assertStringContainsString('<ol>', $html);
    $this->assertStringContainsString('<li>release</li>', $html);
  }

  /**
   * Tests that a fenced code block is converted to a pre/code element.
   */
  public function testFencedCodeBlockIsConverted(): void {
    $html = $this->converter->toHtml("```bash\nros2 launch so_arm bringup.launch.py\n```");

    $this->assertStringContainsString('<pre><code', $html);
    $this->assertStringContainsString('ros2 launch so_arm bringup.launch.py', $html);
  }

  /**
   * Tests that raw HTML in the input is escaped, not executed.
   */
  public function testRawHtmlIsEscapedNotExecuted(): void {
    $html = $this->converter->toHtml("before\n\n<script>alert(1)</script>\n\n<img src=x onerror=alert(2)>");

    $this->assertStringNotContainsString('<script', $html);
    $this->assertStringNotContainsString('<img', $html);
    $this->assertStringContainsString('&lt;script&gt;', $html);
  }

  /**
   * Tests that javascript: links are neutralized while https links pass.
   */
  public function testJavascriptLinksAreNeutralized(): void {
    $html = $this->converter->toHtml('[click](javascript:alert(1)) and [ok](https://huggingface.co/lerobot)');

    $this->assertStringNotContainsString('javascript:', $html);
    $this->assertStringContainsString('href="https://huggingface.co/lerobot"', $html);
  }

  /**
   * Tests that empty input gives empty output.
   */
  public function testEmptyInputGivesEmptyOutput(): void {
    $this->assertSame('', $this->converter->toHtml(''));
  }

}
