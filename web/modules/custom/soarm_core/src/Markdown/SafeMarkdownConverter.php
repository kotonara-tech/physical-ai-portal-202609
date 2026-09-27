<?php

declare(strict_types=1);

namespace Drupal\soarm_core\Markdown;

use League\CommonMark\CommonMarkConverter;

/**
 * Converts Markdown to safe HTML for the "procedure" field.
 *
 * Raw HTML in the source is escaped rather than rendered, and links using
 * unsafe schemes (such as "javascript:") are stripped.
 */
final class SafeMarkdownConverter {

  /**
   * The underlying CommonMark converter.
   */
  private CommonMarkConverter $converter;

  public function __construct() {
    $this->converter = new CommonMarkConverter([
      'html_input' => 'escape',
      'allow_unsafe_links' => FALSE,
    ]);
  }

  /**
   * Converts Markdown source text to safe HTML.
   *
   * @param string $markdown
   *   The Markdown source.
   *
   * @return string
   *   The resulting HTML.
   */
  public function toHtml(string $markdown): string {
    if ($markdown === '') {
      return '';
    }

    return (string) $this->converter->convert($markdown);
  }

}
