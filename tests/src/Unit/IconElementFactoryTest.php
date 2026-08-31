<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\neo_icon\IconElement;
use Drupal\neo_icon\IconElementFactory;
use PHPUnit\Framework\Attributes\Group;

/**
 * Specifies which label and which entity type an entity's icon element gets.
 *
 * Two things are decided here and both are visible to a site the moment an
 * entity is shown with an icon beside it: the text the element carries, and the
 * lookup prefix that picks the icon. The prefix is asserted beside every label
 * because they are set together and a correct label under the wrong prefix
 * renders the right words next to somebody else's glyph.
 *
 * Every assertion runs with no container. That is not a convenience of the test
 * base class, it is the point of the factory: the bundle information the
 * bundle-label branch needs arrives as a constructor argument, so the branch is
 * reachable with one stub rather than a booted site.
 */
#[Group('neo_icon')]
final class IconElementFactoryTest extends UnitTestCase {

  /**
   * It takes the bundle's label when the entity type has a bundle key.
   *
   * Acceptance criterion: *it takes the bundle's label when the entity type has
   * a bundle key.*
   *
   * The entity's own label is deliberately different from the bundle's, so an
   * assertion on the text says which of the two branches ran rather than
   * agreeing with both.
   */
  public function testItTakesTheBundleLabelWhenTheEntityTypeHasBundleKey(): void {
    $entity = $this->entity('node', 'Some node title', 'article', 'type');
    $factory = $this->factory(['node' => ['article' => ['label' => 'Article']]]);

    $element = $factory->build($entity);

    $this->assertSame('Article', $this->textOf($element));
    $this->assertSame(['entity.node'], $this->prefixOf($element));
  }

  /**
   * It takes the entity's own label when the entity type has no bundle key.
   *
   * Acceptance criterion: *it takes the entity's own label when the entity type
   * has no bundle key.*
   *
   * The bundle information stub answers with everything it holds, and here it
   * holds nothing: an entity type with no bundle key is never asked.
   */
  public function testItTakesTheEntityLabelWhenTheEntityTypeHasNoBundleKey(): void {
    $entity = $this->entity('user', 'Ada Lovelace');
    $factory = $this->factory();

    $element = $factory->build($entity);

    $this->assertSame('Ada Lovelace', $this->textOf($element));
    $this->assertSame(['entity.user'], $this->prefixOf($element));
  }

  /**
   * It uses the bundle-of entity type id when the type is a bundle of another.
   *
   * Acceptance criterion: *it uses the bundle-of entity type id when the entity
   * type is the bundle of another.*
   *
   * A node type is the case every site has: the element it gets is looked up
   * under `entity.node`, not `entity.node_type`, so a bundle entity in an admin
   * listing wears the icon of the thing it configures.
   */
  public function testItUsesTheBundleOfEntityTypeIdWhenTheTypeIsBundleOfAnother(): void {
    $entity = $this->entity('node_type', 'Article', '', FALSE, 'node');
    $factory = $this->factory();

    $element = $factory->build($entity);

    $this->assertSame(['entity.node'], $this->prefixOf($element));
    $this->assertSame('Article', $this->textOf($element));
  }

  /**
   * It prefers an explicit label override over either label.
   *
   * Acceptance criterion: *it prefers an explicit label override over either
   * label.*
   *
   * Both branches are driven, because an override that beat only the entity
   * label would still be wrong on every bundled entity — which is most of them.
   * The prefix is asserted alongside to pin that an override changes the text
   * and nothing else.
   */
  public function testItPrefersExplicitLabelOverrideOverEitherLabel(): void {
    $bundled = $this->entity('node', 'Some node title', 'article', 'type');
    $unbundled = $this->entity('user', 'Ada Lovelace');
    $factory = $this->factory(['node' => ['article' => ['label' => 'Article']]]);

    $overBundle = $factory->build($bundled, 'Chosen');
    $overEntity = $factory->build($unbundled, 'Chosen');

    $this->assertSame('Chosen', $this->textOf($overBundle));
    $this->assertSame('Chosen', $this->textOf($overEntity));
    $this->assertSame(['entity.node'], $this->prefixOf($overBundle));
    $this->assertSame(['entity.user'], $this->prefixOf($overEntity));
  }

  /**
   * It builds an element with no container present at all.
   *
   * Acceptance criterion: *it builds an element with no container present at
   * all.*
   *
   * The absence is asserted on both sides of the call rather than assumed: the
   * test base class unsets the container, so the same body reached through the
   * global helper it came from raised `\Drupal::$container is not initialized`
   * here. Nothing the factory does may put one back, either — a lazily set
   * container would be a container dependency wearing a later stack trace.
   */
  public function testItBuildsElementWithNoContainerPresent(): void {
    $this->assertFalse(\Drupal::hasContainer(), 'The test runs with no container.');

    $element = $this->factory(['node' => ['article' => ['label' => 'Article']]])
      ->build($this->entity('node', 'Some node title', 'article', 'type'));

    $this->assertInstanceOf(IconElement::class, $element);
    $this->assertSame('Article', $this->textOf($element));
    $this->assertFalse(\Drupal::hasContainer(), 'And leaves none behind.');
  }

  /**
   * The façade answers what the factory answers, and says so in its docblock.
   *
   * Acceptance criterion: *the entity icon façade answers what the factory
   * answers, with its signature unchanged and a docblock-only `@deprecated`
   * naming the factory — no runtime trigger.*
   *
   * Four separate promises, and the fourth is the one a test has to make
   * because a reviewer cannot see it: the façade sits on the path every
   * rendered icon takes, so a `@trigger_error` here would fire hundreds of
   * times per page in the logs of sites that did not ask for the move. The
   * error handler is installed around the call rather than trusted to the test
   * runner, so the assertion is about this function and not about how the suite
   * happens to be configured.
   *
   * The container the call runs through holds the factory and nothing else. If
   * the façade still reached for anything of its own it would not find it,
   * which is what makes this an assertion about delegation.
   */
  public function testTheEntityIconFacadeAnswersWhatTheFactoryAnswers(): void {
    require_once dirname(__DIR__, 3) . '/neo_icon.module';

    $entity = $this->entity('node', 'Some node title', 'article', 'type');
    $factory = $this->factory(['node' => ['article' => ['label' => 'Article']]]);
    $container = new ContainerBuilder();
    $container->set('neo_icon.element_factory', $factory);
    \Drupal::setContainer($container);

    $raised = [];
    set_error_handler(static function (int $errno, string $message) use (&$raised): bool {
      $raised[] = $message;
      return TRUE;
    }, E_USER_DEPRECATED);
    try {
      // The façade under test is the deprecated one; calling it is the point.
      // @phpstan-ignore function.deprecated
      $plain = neo_icon_entity($entity);
      // @phpstan-ignore function.deprecated
      $overridden = neo_icon_entity($entity, 'Chosen');
    }
    finally {
      restore_error_handler();
      \Drupal::unsetContainer();
    }

    $this->assertSame('Article', $this->textOf($plain));
    $this->assertSame(['entity.node'], $this->prefixOf($plain));
    $this->assertSame('Chosen', $this->textOf($overridden));
    $this->assertSame([], $raised, 'Nothing about the façade fires at runtime.');

    $function = new \ReflectionFunction('neo_icon_entity');
    $parameters = $function->getParameters();
    $this->assertSame('Drupal\neo_icon\IconElement', (string) $function->getReturnType());
    $this->assertCount(2, $parameters, 'The façade takes the two arguments it always took.');
    $this->assertSame('entity', $parameters[0]->getName());
    $this->assertSame('Drupal\Core\Entity\EntityInterface', (string) $parameters[0]->getType());
    $this->assertSame('labelOverride', $parameters[1]->getName());
    $this->assertSame('?string', (string) $parameters[1]->getType());
    $this->assertTrue($parameters[1]->isDefaultValueAvailable());
    $this->assertNull($parameters[1]->getDefaultValue());

    $docblock = (string) $function->getDocComment();
    $this->assertStringContainsString(
      '@deprecated in neo_icon:1.2.0 and is removed from neo_icon:2.0.0.',
      preg_replace('/\s+/', ' ', $docblock),
      'The tag states a major-version removal in the standard format.'
    );
    $this->assertStringContainsString(
      'Drupal\neo_icon\IconElementFactory::build()',
      preg_replace('/\s+/', ' ', $docblock),
      'The tag names the factory.'
    );
    $this->assertStringNotContainsString(
      'trigger_error',
      $this->facadeSource(),
      'The deprecation reaches a tool and not an operator.'
    );
  }

  /**
   * Builds the factory over a bundle information stub.
   *
   * @param array $bundleInfo
   *   Everything the bundle information service holds, keyed by entity type id
   *   and then by bundle, exactly as `getAllBundleInfo()` answers it.
   *
   * @return \Drupal\neo_icon\IconElementFactory
   *   The factory under test, constructed rather than fetched.
   */
  private function factory(array $bundleInfo = []): IconElementFactory {
    $info = $this->createMock(EntityTypeBundleInfoInterface::class);
    $info->method('getAllBundleInfo')->willReturn($bundleInfo);
    return new IconElementFactory($info);
  }

  /**
   * Builds an entity double over an entity type double.
   *
   * @param string $entityTypeId
   *   The entity type id the entity reports.
   * @param string $label
   *   The entity's own label.
   * @param string $bundle
   *   The entity's bundle, where it has one.
   * @param string|false $bundleKey
   *   The entity type's bundle key, or FALSE where it declares none.
   * @param string|null $bundleOf
   *   The entity type this one provides bundles for, or NULL.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity to build an icon element for.
   */
  private function entity(string $entityTypeId, string $label, string $bundle = '', string|false $bundleKey = FALSE, ?string $bundleOf = NULL): EntityInterface {
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getKey')->willReturn($bundleKey);
    $entityType->method('getBundleOf')->willReturn($bundleOf);

    $entity = $this->createMock(EntityInterface::class);
    $entity->method('label')->willReturn($label);
    $entity->method('bundle')->willReturn($bundle);
    $entity->method('getEntityTypeId')->willReturn($entityTypeId);
    $entity->method('getEntityType')->willReturn($entityType);
    return $entity;
  }

  /**
   * Reads the façade's docblock and body out of the module file.
   *
   * @return string
   *   Everything from the opening of the docblock above `neo_icon_entity()` to
   *   the end of its body, as written. Read from source because "no runtime
   *   trigger" is a statement about what is *not* there, which no call can
   *   demonstrate on its own.
   */
  private function facadeSource(): string {
    $module = (string) file_get_contents(dirname(__DIR__, 3) . '/neo_icon.module');
    $declaration = strpos($module, 'function neo_icon_entity(');
    $this->assertNotFalse($declaration, 'The façade is still declared in neo_icon.module.');
    $docblock = strrpos(substr($module, 0, $declaration), '/**');
    $this->assertNotFalse($docblock, 'The façade still carries a docblock.');
    return substr($module, $docblock, strpos($module, "\n}\n", $declaration) - $docblock);
  }

  /**
   * The text an icon element carries.
   *
   * @param \Drupal\neo_icon\IconElement $element
   *   The element the factory built.
   *
   * @return string
   *   The text, translated through a stub so that reading it needs no more of a
   *   container than building it did.
   */
  private function textOf(IconElement $element): string {
    $element->setStringTranslation($this->getStringTranslationStub());
    return (string) $element->getText();
  }

  /**
   * The icon lookup prefix an icon element carries.
   *
   * @param \Drupal\neo_icon\IconElement $element
   *   The element the factory built.
   *
   * @return array
   *   The prefix. It is read off the element rather than through `getIcon()`,
   *   because resolving an icon reaches the icon repository through a static
   *   and this suite has no container to answer with one.
   */
  private function prefixOf(IconElement $element): array {
    return (array) (new \ReflectionProperty(IconElement::class, 'prefix'))->getValue($element);
  }

}
