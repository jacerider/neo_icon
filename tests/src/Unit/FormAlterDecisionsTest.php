<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\neo_icon\Hook\NeoIconFormHooks;
use Drupal\neo_icon\IconEntityTypeManager;
use PHPUnit\Framework\Attributes\Group;

/**
 * What the form alter decides, without a bootstrap.
 *
 * The alter reached the entity icon manager through `\Drupal::service()`, so
 * every branch in it — the two early returns, the three form ids it accepts and
 * the media-type special case — used to need a booted site to exercise. On a
 * class that manager is the one constructor argument, which is what makes a
 * stubbed form state and a stubbed manager enough to drive all of it.
 *
 * Discovery is not asked here. Whether the hook system found the method at all,
 * and whether the entity builder string it registers resolves, is
 * `Drupal\Tests\neo_icon\Kernel\FormHooksTest`, which spends a bootstrap on the
 * questions a bootstrap can answer.
 */
#[Group('neo_icon')]
final class FormAlterDecisionsTest extends UnitTestCase {

  /**
   * The three form ids get the select, seeded with the entity's own icon.
   *
   * Acceptance criterion: *it adds the icon select to a supported config
   * entity's add form, edit form and own-id form, seeded with the entity's
   * current icon.*
   *
   * All three are asserted together because they are one condition with three
   * arms, and each arm is the only thing standing between a bundle form and no
   * icon field at all. The element is asserted whole rather than by presence:
   * the parents key is what keeps the value out of the entity's own values, and
   * the weight is what puts the select at the top of the form.
   */
  public function testAddsTheIconSelectToSupportedConfigEntityAddEditAndOwnFormsSeededWithItsCurrentIcon(): void {
    $hooks = $this->hooks(['node_type']);

    foreach (['node_type_add_form', 'node_type_edit_form', 'node_type_form'] as $formId) {
      $form = ['#form_id' => $formId];
      $hooks->formAlter($form, $this->formState($this->entity('node_type', 'article', 'star')), $formId);

      $this->assertArrayHasKey('neo_icon', $form, $formId . ' carries the select.');
      $this->assertSame('neo_icon_select', $form['neo_icon']['#type']);
      $this->assertSame('Icon', (string) $form['neo_icon']['#title']);
      $this->assertSame('star', $form['neo_icon']['#default_value'], 'Seeded with the entity icon.');
      $this->assertSame(['neo_icon'], $form['neo_icon']['#parents']);
      $this->assertSame(0, $form['neo_icon']['#weight']);
    }
  }

  /**
   * Three forms this alter has no business touching come back untouched.
   *
   * Acceptance criterion: *it leaves an unsupported entity's form, a
   * non-config-entity form and a non-entity form untouched.*
   *
   * All three are one guard read from the outside, and each of them is a form
   * belonging to somebody else — a config entity nobody registered an icon
   * plugin for, a content entity's form, and a form with no entity behind it at
   * all. The alter's second early return is asserted in the same breath,
   * because a supported entity on a form that is neither an add form, an edit
   * form nor its own is the same promise: the array comes back as it went in.
   */
  public function testLeavesUnsupportedNonConfigAndNonEntityFormsUntouched(): void {
    $hooks = $this->hooks(['node_type']);

    // A config entity whose type nothing registered an icon plugin for.
    $form = $untouched = ['#form_id' => 'block_edit_form'];
    $hooks->formAlter($form, $this->formState($this->entity('block', 'sidebar')), 'block_edit_form');
    $this->assertSame($untouched, $form, 'An unsupported entity type is left alone.');

    // An entity form over something that is not a config entity.
    $node = $this->createMock(EntityInterface::class);
    $node->method('getEntityTypeId')->willReturn('node_type');
    $form = $untouched = ['#form_id' => 'node_type_edit_form'];
    $hooks->formAlter($form, $this->formState($node), 'node_type_edit_form');
    $this->assertSame($untouched, $form, 'A content entity form is left alone.');

    // A form with no entity behind it at all.
    $form = $untouched = ['#form_id' => 'system_site_information_settings'];
    $hooks->formAlter($form, $this->formState(NULL), 'system_site_information_settings');
    $this->assertSame($untouched, $form, 'A form that is not an entity form is left alone.');

    // And the second early return: the right entity on the wrong form.
    $form = $untouched = ['#form_id' => 'node_type_delete_form'];
    $hooks->formAlter($form, $this->formState($this->entity('node_type', 'article')), 'node_type_delete_form');
    $this->assertSame($untouched, $form, 'A form id that is neither add, edit nor its own is left alone.');
  }

  /**
   * A media type keeps the select inside its source group, or not at all.
   *
   * Acceptance criterion: *it relocates the select into the media type's
   * source-dependent group, and drops it when there is no source
   * configuration.*
   *
   * The comment the moved body carries calls this avoiding a core error, and
   * both arms are the avoidance: a media type form rebuilds the
   * source-dependent group over AJAX, so a select left beside it is rebuilt out
   * of existence, and a media type with no source configured has no group to
   * put it in. The entity builder is asserted in both arms, because the alter
   * registers it before it decides where the select goes and a dropped select
   * must not take the builder with it.
   */
  public function testRelocatesTheSelectIntoTheMediaTypeSourceGroupOrDropsItWithoutSourceConfiguration(): void {
    $hooks = $this->hooks(['media_type']);
    $builder = NeoIconFormHooks::class . '::configEntityBuild';

    // With a source configured, the select moves inside the group.
    $form = [
      '#form_id' => 'media_type_edit_form',
      'source_dependent' => ['source_configuration' => ['field' => 'value']],
    ];
    $hooks->formAlter($form, $this->formState($this->entity('media_type', 'image', 'star')), 'media_type_edit_form');

    $this->assertArrayNotHasKey('neo_icon', $form, 'Nothing is left at the top level.');
    $this->assertSame('neo_icon_select', $form['source_dependent']['neo_icon']['#type']);
    $this->assertSame('star', $form['source_dependent']['neo_icon']['#default_value']);
    $this->assertSame([$builder], $form['#entity_builders']);

    // With no source configuration there is no group, so the select is dropped.
    $form = [
      '#form_id' => 'media_type_add_form',
      'source_dependent' => ['source_configuration' => []],
    ];
    $hooks->formAlter($form, $this->formState($this->entity('media_type', 'image', 'star')), 'media_type_add_form');

    $this->assertArrayNotHasKey('neo_icon', $form);
    $this->assertArrayNotHasKey('neo_icon', $form['source_dependent']);
    $this->assertSame([$builder], $form['#entity_builders'], 'The builder survives the drop.');
  }

  /**
   * Builds the hook class over an entity icon manager holding the ids given.
   *
   * @param string[] $supported
   *   The entity type ids the icon entity type manager holds a plugin for.
   *
   * @return \Drupal\neo_icon\Hook\NeoIconFormHooks
   *   The hook class under test, constructed rather than fetched.
   */
  private function hooks(array $supported): NeoIconFormHooks {
    $definitions = [];
    foreach ($supported as $entityTypeId) {
      $definitions[$entityTypeId] = ['id' => $entityTypeId, 'label' => $entityTypeId];
    }
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')->willReturn((object) ['data' => $definitions]);
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('getModuleDirectories')->willReturn([]);
    return new NeoIconFormHooks(new IconEntityTypeManager($moduleHandler, $cache));
  }

  /**
   * A form state whose form object is an entity form over the entity given.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity the form is for, or NULL for a form that is not an entity
   *   form at all.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The form state.
   */
  private function formState(?EntityInterface $entity): FormStateInterface {
    if ($entity === NULL) {
      $formObject = $this->createMock(FormInterface::class);
    }
    else {
      $formObject = $this->createMock(EntityFormInterface::class);
      $formObject->method('getEntity')->willReturn($entity);
    }
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($formObject);
    return $formState;
  }

  /**
   * A config entity of the type given, carrying the icon given.
   *
   * @param string $entityTypeId
   *   The entity type id, which is what the support question is asked about.
   * @param string $id
   *   The entity id, which the icon is keyed under.
   * @param string $icon
   *   The icon the entity already carries.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   The entity.
   */
  private function entity(string $entityTypeId, string $id, string $icon = ''): ConfigEntityInterface {
    $entity = $this->createMock(ConfigEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn($entityTypeId);
    $entity->method('id')->willReturn($id);
    $entity->method('getThirdPartySetting')->willReturn($icon);
    return $entity;
  }

  /**
   * {@inheritdoc}
   *
   * The select's title goes through the translation service, which the moved
   * body reaches as `$this->t()`, so a container carrying nothing but that
   * service stands in for the one a real form build would have had.
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

}
