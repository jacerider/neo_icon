<?php

declare(strict_types = 1);

namespace Drupal\neo_icon\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\CompositeFormElementTrait;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\neo_icon\IconElement;
use Drupal\neo_modal\Ajax\NeoModalCommand;

/**
 * Provides a render element to display a neo scheme.
 *
 * Usage Example:
 * @code
 * $build['neo_icon'] = [
 *   '#type' => 'neo_icon',
 * ];
 * @endcode
 */
#[RenderElement('neo_icon')]
final class Icon extends FormElementBase {

  use CompositeFormElementTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
        [$class, 'processNeoIcon'],
      ],
      '#pre_render' => [
        [$class, 'preRenderCompositeFormElement'],
        [$class, 'preRenderGroup'],
      ],
      // '#theme_wrappers' => ['form_element'],
      '#libraries' => [],
      // Can be name, selector.
      '#format' => 'name',
      '#empty_icon' => 'ban',
    ];
  }

  /**
   * Neo color element process callback.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The modified element.
   */
  public static function processNeoIcon(&$element, FormStateInterface $form_state, &$complete_form): array {
    $id = Html::getId('neo-icon-' . implode('_', $element['#parents']));
    $defaultValue = $element['#default_value'];
    $defaultIconName = $defaultValue;
    switch ($element['#format']) {
      case 'selector':
        if ($defaultValue) {
          /** @var \Drupal\neo_icon\IconRepositoryInterface $iconRepository */
          $iconRepository = \Drupal::service('neo_icon.repository');
          if ($icon = $iconRepository->getIconBySelector($defaultValue)) {
            $defaultIconName = $icon->getName();
          }
        }
        break;
    }
    $element['#element_validate'][] = [static::class, 'elementValidate'];
    $element['value'] = [
      '#attributes' => [
        'id' => $id . '-value',
      ],
      '#type' => 'hidden',
      '#default_value' => $defaultValue,
    ];
    $element['icon'] = [
      '#prefix' => '<div class="neo-icon-element flex items-center text-base">',
      '#suffix' => '</div>',
    ];
    $element['icon']['preview'] = [
      '#theme' => 'neo_icon',
      '#icon' => $defaultIconName ?: $element['#empty_icon'],
      '#prefix' => '<span id="' . $id . '-icon" class="neo-icon-element--icon text-2xl">',
      '#suffix' => '</span>',
    ];
    if (!$defaultIconName) {
      $element['icon']['preview']['#attributes']['class'][] = 'text-alert-500';
      $element['icon']['preview']['#attributes']['class'][] = 'opacity-60';
    }
    $element['icon']['browse'] = [
      '#type' => 'button',
      '#title' => new IconElement(t('Browse'), 'search'),
      '#libraries' => $element['#libraries'],
      '#format' => $element['#format'],
      '#value' => 'search',
      '#attributes' => [
        'class' => ['ml-2', 'btn-xs'],
      ],
      '#field_id' => $id,
      '#ajax' => [
        'callback' => [self::class, 'ajaxCallback'],
      ],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function ajaxCallback(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $response = new AjaxResponse();
    $response->addCommand(new NeoModalCommand([
      '#type' => 'neo_icon_browser',
      '#libraries' => $trigger['#libraries'],
      '#update_input' => '#' . $trigger['#field_id'] . '-value',
      '#update_input_format' => $trigger['#format'],
      '#update_icon' => '#' . $trigger['#field_id'] . '-icon i',
    ], [
      'width' => '100%',
      'contentScroll' => TRUE,
    ]));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public static function elementValidate($element, FormStateInterface $form_state, $form) {
    $value = $form_state->getValue($element['#parents']);
    $value = $value['value'] ?? NULL;
    $form_state->setValueForElement($element, $value);
  }

}
