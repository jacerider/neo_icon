<?php

declare(strict_types=1);

namespace Drupal\neo_icon\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\neo_icon\IconEntityTypeManager;

/**
 * The module's form alter and the entity builder it registers.
 *
 * The last hook `neo_icon.module` carried, split from the behavioural and theme
 * classes the way core splits its own `…Hooks` / `…FormHooks` sets. It does one
 * thing: it puts an icon select on the form of every config entity whose type
 * has an icon plugin, and registers the builder that stores what the select
 * answered.
 *
 * The body below is what stood in the module file, with one substitution — the
 * `\Drupal::service('neo_icon.entity_type.manager')` call became the
 * constructor argument, and `t()` became `$this->t()`, which is core's
 * convention on a class and renders the same string. Nothing about what it
 * decides moved with it: not which entity types are supported, not the three
 * form ids it accepts, not what the select is seeded with, and not the
 * media-type special case that relocates the select into the source-dependent
 * group or drops it when there is no source configuration.
 *
 * That substitution is the whole gain. As a function, the two early returns and
 * the media-type branch could only be exercised with a booted site; as a method
 * on a constructed object they are reachable from a stubbed manager and a
 * stubbed form state, which is `docs/adr/0007`'s test answering yes.
 *
 * **The entity builder is a public static method named by string.** It is
 * stored in `#entity_builders` and core invokes those entries with
 * `call_user_func_array()`, so `static::class . '::configEntityBuild'` is a
 * drop-in for the function name that used to sit there. It keeps its one
 * container static, because a static method has nowhere to inject anything —
 * which is what `neo`'s four converted form callbacks do. No forwarder is kept
 * for the old function name: nothing outside this module named it, and the only
 * other mention anywhere is a `@see` line in one of the module's own field
 * formatters, which names the method now.
 *
 * With this hook moved the module has no procedural hook implementation left,
 * which is what lets `neo_icon.services.yml` declare the scan skip.
 *
 * This is not an API and it is not `final`. The methods are public because
 * core's hook collector only reads public methods, and because an
 * `#entity_builders` entry reaches the static one by name.
 */
class NeoIconFormHooks {

  use StringTranslationTrait;

  /**
   * Constructs a NeoIconFormHooks object.
   *
   * @param \Drupal\neo_icon\IconEntityTypeManager $iconEntityTypeManager
   *   The entity icon manager, which answers whether an entity type has icons
   *   at all and what icon an entity already carries. It is the concrete class
   *   because it is `final` and declares no interface.
   */
  public function __construct(
    protected readonly IconEntityTypeManager $iconEntityTypeManager,
  ) {}

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if ($form_state->getFormObject() instanceof EntityFormInterface) {
      $entity = $form_state->getFormObject()->getEntity();
      if ($entity instanceof ConfigEntityInterface) {
        if (!$this->iconEntityTypeManager->isSupportedEntity($entity)) {
          return;
        }
        if (
          strpos($form['#form_id'], '_edit_form') === FALSE &&
          strpos($form['#form_id'], '_add_form') === FALSE &&
          $form['#form_id'] !== $entity->getEntityTypeId() . '_form'
        ) {
          return;
        }
        $form['neo_icon'] = [
          '#type' => 'neo_icon_select',
          '#title' => $this->t('Icon'),
          '#default_value' => $this->iconEntityTypeManager->getEntityIcon($entity),
          '#parents' => ['neo_icon'],
          '#weight' => 0,
        ];
        $form['#entity_builders'][] = static::class . '::configEntityBuild';
        // Special handling for media types to avoid core error.
        if ($entity->getEntityTypeId() === 'media_type') {
          if (empty($form['source_dependent']['source_configuration'])) {
            unset($form['neo_icon']);
          }
          else {
            $form['source_dependent']['neo_icon'] = $form['neo_icon'];
            unset($form['neo_icon']);
          }
        }
      }
    }
  }

  /**
   * Entity form builder for config entities that support icons.
   *
   * @param string $entity_type
   *   The entity type id of the entity being built.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity being built, which the icon is stored on.
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function configEntityBuild($entity_type, ConfigEntityInterface $entity, &$form, FormStateInterface $form_state) {
    /** @var \Drupal\neo_icon\IconEntityTypeManager $iconPluginManager */
    $iconPluginManager = \Drupal::service('neo_icon.entity_type.manager');
    $icon = $form_state->getValue('neo_icon');
    if (!empty($icon) && is_string($icon)) {
      $iconPluginManager->setEntityIcon($entity, $icon);
    }
    else {
      $iconPluginManager->unsetEntityIcon($entity);
    }
    Cache::invalidateTags(['neo_icon']);
  }

}
