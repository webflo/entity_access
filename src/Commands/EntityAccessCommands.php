<?php

namespace Drupal\entity_access\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_access\EntityAccessHelper;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 */
class EntityAccessCommands extends DrushCommands {

  /**
   * The 'entity_type.manager' service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new instance.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Rebuild entity access data.
   *
   * @param string $entity_type
   *   The entity type.
   *
   * @command entity-access:rebuild
   * @aliases entity-access-rebuild
   */
  public function accessRebuild($entity_type) {
    $definition = $this->entityTypeManager->getDefinition($entity_type, FALSE);

    if (!$entity_type) {
      $this->logger->error('The specified entity type does not exist.');
      return;
    }

    $storage = $this->entityTypeManager->getStorage($entity_type);
    $id_key = $definition->getKey('id');
    $limit = 50;

    do {
      if (isset($entity_ids)) {
        $storage->resetCache();
      }

      $query = $storage->getQuery()
        ->sort($id_key, 'DESC')
        ->range(0, $limit);

      if (isset($last_id)) {
        $query->condition($id_key, $last_id, '<');
      }

      $entity_ids = $query->execute();
      $last_id = end($entity_ids);
      $entities = $storage->loadMultiple($entity_ids);

      foreach ($entities as $entity) {
        EntityAccessHelper::update($entity);
      }
    } while (count($entity_ids) > 0);
  }

}
