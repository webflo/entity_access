<?php

namespace Drupal\entity_access;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Helper class to dispatch entity hooks and easily save grants after entity
 * updates.
 *
 * @see entity_access_entity_insert
 * @see entity_access_entity_update
 * @see entity_access_entity_delete
 */
class EntityAccessHelper {

  /**
   * Write grants after entity update.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public static function update(EntityInterface $entity) {
    $access_handler = \Drupal::entityTypeManager()->getAccessControlHandler($entity->getEntityTypeId());

    if ($access_handler instanceof GrantBasedEntityAccessControlHandlerInterface && $entity instanceof ContentEntityInterface) {
      $access_handler->writeGrants($entity);
    }
  }

  /**
   * Delete grants after entity delete.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public static function delete(EntityInterface $entity) {
    $access_handler = \Drupal::entityTypeManager()->getAccessControlHandler($entity->getEntityTypeId());

    if ($access_handler instanceof GrantBasedEntityAccessControlHandlerInterface && $entity instanceof ContentEntityInterface) {
      $access_handler->deleteEntityRecords($entity);
    }
  }

}
