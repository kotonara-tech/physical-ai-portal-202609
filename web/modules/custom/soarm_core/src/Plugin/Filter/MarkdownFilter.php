<?php

declare(strict_types=1);

namespace Drupal\soarm_core\Plugin\Filter;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\soarm_core\Markdown\SafeMarkdownConverter;

/**
 * Converts Markdown to safe HTML.
 */
#[Filter(
  id: "soarm_markdown",
  title: new TranslatableMarkup("Markdown（安全な変換）"),
  type: FilterInterface::TYPE_MARKUP_LANGUAGE,
  description: new TranslatableMarkup("Markdown を HTML に変換します。生の HTML はエスケープされ、危険なリンクは無効化されます。"),
)]
final class MarkdownFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $converter = new SafeMarkdownConverter();
    return new FilterProcessResult($converter->toHtml($text));
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    return $this->t('Markdown 記法（見出し・箇条書き・コードブロックなど）が使えます。HTML タグはそのまま書いても実行されず、文字として表示されます。');
  }

}
