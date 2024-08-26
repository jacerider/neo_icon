<?php

namespace Drupal\neo_icon;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Neo Icon Package entities.
 */
class IconLibraryListBuilder extends DraggableListBuilder {
  use IconTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'neo_icon_library_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = $this->t('Library');
    $header['preview'] = $this->t('Preview');
    $header['type'] = $this->t('Type');
    $header['global'] = $this->t('Global');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\neo_icon\IconLibraryInterface $entity */
    $row['title']['data'][] = [
      '#type' => 'link',
      '#title' => $entity->label(),
      '#url' => $entity->toUrl('canonical'),
    ];
    $row['title']['data'][]['#markup'] = ' <small>(' . $entity->id() . ')</small>';
    $row['preview']['#wrapper_attributes'] = ['class' => ['td--min']];
    foreach ($this->getRandomIconNames($entity) as $icon_name) {
      $row['preview']['data'][]['#markup'] = $this->icon(NULL, $icon_name, $entity->id(), [], TRUE);
    }
    $row['type'] = [
      '#wrapper_attributes' => ['class' => ['td--min', 'td--center']],
      'data' => [
        '#markup' => $this->icon($entity->getType())->iconOnly(),
      ],
    ];
    $row['global'] = [
      '#wrapper_attributes' => ['class' => ['td--min', 'td--center']],
      'data' => [
        '#markup' => $entity->isGlobal() ? $this->icon($this->t('Global'))->iconOnly() : $this->icon($this->t('Not Global'))->iconOnly(),
      ],
    ];
    $row['status'] = [
      '#wrapper_attributes' => ['class' => ['td--min', 'td--center']],
      'data' => [
        '#markup' => $entity->status() ? $this->icon($this->t('Published'))->iconOnly() : $this->icon($this->t('Unpublished'))->iconOnly(),
      ],
    ];
    return $row + parent::buildRow($entity);
  }

  /**
   * Get a random sampling of icons.
   *
   * @param IconLibraryInterface $entity
   *   The entity.
   *
   * @return string[]
   *   An array of icon names.
   */
  protected function getRandomIconNames(IconLibraryInterface $entity) {
    $names = array_keys($entity->getIcons());
    shuffle($names);
    return array_slice($names, 0, 12);
  }

}
