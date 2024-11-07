<?php

namespace Drupal\neo_icon\Element;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a render element for Neo icon browser.
 */
#[RenderElement('neo_icon_browser')]
class IconBrowser extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#theme' => 'neo_icon_browser',
      '#libraries' => [],
      '#show_info' => FALSE,
      // A selector to an input field that will store the selected icon.
      '#update_input' => NULL,
      // The the value format that will be set to the input field.
      // Can be name, selector.
      '#update_input_format' => 'name',
      // A selector to an input field that will store the selected icon.
      '#update_icon' => NULL,
      '#pre_render' => [
        [$class, 'preRenderIconBrowser'],
      ],
    ];
  }

  /**
   * Pre render neo icon browsers.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   container.
   *
   * @return array
   *   The modified element.
   */
  public static function preRenderIconBrowser(array $element) {
    $element['#attributes']['class'][] = 'neo-icon-browser';
    $element['#loader'] = [
      '#type' => 'neo_loader',
      '#loader' => 'wave',
      '#title' => t('Loading'),
    ];
    $element['#attached']['library'][] = 'neo_icon/icon-browser';
    /** @var \Drupal\neo_icon\IconLibraryStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('neo_icon_library');
    $ignore_status = !empty($element['#libraries']);
    $libraries = $storage->loadAvailable($element['#libraries'], [], $ignore_status);
    if (!empty($element['#update_input'])) {
      $element['#attributes']['data-update-input'] = $element['#update_input'];
      $element['#attributes']['data-update-input-format'] = $element['#update_input_format'];
    }
    if (!empty($element['#update_icon'])) {
      $element['#attributes']['data-update-icon'] = $element['#update_icon'];
    }
    if (!empty($element['#show_info'])) {
      $element['#attributes']['data-show-info'] = 'true';
    }
    $element['#libraries'] = [];
    foreach ($libraries as $library) {
      $element['#libraries'][] = $library->id();
      $element['#attached']['library'][] = 'neo_icon/' . $library->getLibraryName();
      $element['#library_options'][$library->id()] = $library->label();
    }
    $element['#attributes']['data-libraries'][] = Json::encode($element['#libraries']);
    return $element;
  }

}
