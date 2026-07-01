<?php

declare(strict_types=1);

namespace Drupal\neo_icon\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Drush commands for Neo Icon.
 */
final class NeoIconCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Search the available icon names (for the icon() Twig function).
   */
  #[CLI\Command(name: 'neo:icon:list', aliases: ['neoi-list'])]
  #[CLI\Argument(name: 'search', description: 'Optional substring to filter icon names by.')]
  #[CLI\Option(name: 'limit', description: 'Maximum number of icons to return.')]
  #[CLI\FieldLabels(labels: [
    'name' => 'Name',
    'library' => 'Library',
    'usage' => 'Twig',
  ])]
  #[CLI\DefaultFields(fields: ['name', 'library', 'usage'])]
  #[CLI\Usage(name: 'drush neo:icon:list', description: 'List every available icon name.')]
  #[CLI\Usage(name: 'drush neo:icon:list arrow', description: 'Find icon names containing "arrow".')]
  public function listIcons(?string $search = NULL, array $options = ['limit' => 50, 'format' => 'table']): RowsOfFields {
    $rows = [];
    $limit = (int) $options['limit'];

    /** @var \Drupal\neo_icon\IconLibraryStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('neo_icon_library');

    $needle = $search !== NULL ? mb_strtolower($search) : NULL;
    $truncated = FALSE;
    foreach ($storage->loadAvailable() as $library) {
      foreach ($library->getIcons() as $key => $icon) {
        $name = $icon['id'] ?? (is_string($key) ? $key : NULL);
        if ($name === NULL) {
          continue;
        }
        if ($needle !== NULL && !str_contains(mb_strtolower((string) $name), $needle)) {
          continue;
        }
        if (count($rows) >= $limit) {
          $truncated = TRUE;
          break 2;
        }
        $rows[] = [
          'name' => $name,
          'library' => $library->id(),
          'usage' => sprintf("icon('%s')", $name),
        ];
      }
    }

    if ($truncated) {
      $this->io()->note(sprintf('Showing the first %d matches. Narrow with a search term or raise --limit.', $limit));
    }
    elseif (!$rows) {
      $this->io()->warning($needle !== NULL ? sprintf('No icons match "%s".', $search) : 'No icons found.');
    }
    return new RowsOfFields($rows);
  }

}
