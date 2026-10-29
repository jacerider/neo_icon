<?php

/**
 * @file
 * Post update functions for Neo Icon.
 */

use Drupal\neo_icon\Entity\IconLibrary;
use Drupal\neo_icon\IconLibraryInterface;

/**
 * Keep existing icon libraries rendering as a font; swap in their fonts.
 *
 * Icon libraries gained a render setting, font or inline SVG. Every library a
 * site already has is written as font explicitly, so nothing it renders
 * changes until the site chooses otherwise. Its stylesheet's fonts swap in
 * rather than block the text, as newly imported packages' do.
 */
function neo_icon_post_update_render_setting(): string {
  $storage = \Drupal::entityTypeManager()->getStorage('neo_icon_library');
  $config_factory = \Drupal::configFactory();
  $written = [];
  foreach ($storage->loadMultiple() as $library) {
    /** @var \Drupal\neo_icon\IconLibraryInterface $library */
    $stylesheet = $library->getUri() . '/style.css';
    if (file_exists($stylesheet)) {
      $css = (string) file_get_contents($stylesheet);
      $swapped = IconLibrary::swapFontDisplay($css);
      if ($swapped !== $css) {
        file_put_contents($stylesheet, $swapped);
      }
    }
    if ($config_factory->get($library->getConfigDependencyName())->get('render') === NULL) {
      $library->setRender(IconLibraryInterface::RENDER_FONT)->save();
      $written[] = $library->id();
    }
  }
  return $written
    ? (string) t('Icon libraries kept rendering as a font: @ids.', ['@ids' => implode(', ', $written)])
    : (string) t('No icon library needed a render setting.');
}
