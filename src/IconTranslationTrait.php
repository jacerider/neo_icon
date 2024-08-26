<?php

namespace Drupal\neo_icon;

/**
 * Wrapper methods for \Drupal\neo_icon\NeoIconTranslatableMarkup.
 */
trait IconTranslationTrait {

  /**
   * Get an icon.
   *
   * @param string $text
   *   The icon text.
   * @param string $icon
   *   The icon id.
   * @param string $library
   *   The library id.
   * @param array $prefix
   *   An array of prefixes.
   * @param bool $ignore_status
   *   If TRUE, the status will be ignored.
   */
  protected function icon($text = NULL, $icon = NULL, $library = NULL, array $prefix = [], $ignore_status = FALSE) {
    return new IconElement($text, $icon, $library, $prefix, $ignore_status);
  }

}
