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
 *   '#type' => 'neo_icon_select',
 * ];
 * @endcode
 */
#[RenderElement('neo_icon_select')]
final class IconSelect extends FormElementBase {

  use CompositeFormElementTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#tree' => TRUE,
      '#process' => [
        [$class, 'processNeoIcon'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#element_validate' => [
        [$class, 'validateNeoIcon'],
      ],
      '#pre_render' => [
        [$class, 'preRenderCompositeFormElement'],
        [$class, 'preRenderGroup'],
      ],
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
    $defaultValue = $element['#default_value'] ?? NULL;
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
      '#type' => 'submit',
      '#title' => new IconElement(t('Browse'), 'search'),
      '#libraries' => $element['#libraries'],
      '#format' => $element['#format'],
      '#value' => 'search',
      '#attributes' => [
        'class' => ['ml-2', 'btn-xs'],
      ],
      '#limit_validation_errors' => [],
      '#field_id' => $id,
      '#submit' => [
        [self::class, 'submitBrowse'],
      ],
      '#ajax' => [
        'callback' => [self::class, 'ajaxCallback'],
      ],
    ];
    return $element;
  }

  /**
   * Submit browse button.
   */
  public static function submitBrowse(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function ajaxCallback(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    if ($form_state->hasAnyErrors()) {
      // Remove all form validation messages.
      \Drupal::messenger()->deleteByType('error');
    }
    $response = new AjaxResponse();
    $response->addCommand(new NeoModalCommand([
      '#type' => 'neo_icon_browser',
      '#libraries' => $trigger['#libraries'],
      '#update_input' => '#' . $trigger['#field_id'] . '-value',
      '#update_input_format' => $trigger['#format'],
      '#update_icon' => '#' . $trigger['#field_id'] . '-icon i',
    ], [
      'width' => '100%',
      'height' => '100%',
      'contentScroll' => TRUE,
      'nest' => TRUE,
    ]));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateNeoIcon($element, FormStateInterface $form_state, $form) {
    $value = $form_state->getValue($element['#parents']);
    $value = $value['value'] ?? NULL;
    $form_state->setValueForElement($element, $value);
  }

}
