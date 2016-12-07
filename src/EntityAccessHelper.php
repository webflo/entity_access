<?php

/**
 * @file
 * Contains \Drupal\entity\EntityAccessHelper.
 */

namespace Drupal\entity_access;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Helper class to dispatch entity hooks and easily save grants after entity
 * updates.
 *
 * @see entity_access_entity_insert
 * @sse entity_access_entity_update
 * @sse entity_access_entity_delete
 */
class EntityAccessHelper {

  /**
   * Write grants after entity update.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public static function update(EntityInterface $entity) {
    if (static::hasGrantAwareAccessController($entity->getEntityType())) {
      \Drupal::entityManager()->getAccessControlHandler($entity->getEntityTypeId())->writeGrants($entity);
    }
  }

  /**
   * Delete grants after entity delete.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public static function delete(EntityInterface $entity) {
    if (static::hasGrantAwareAccessController($entity->getEntityType())) {
      \Drupal::entityManager()->getAccessControlHandler($entity->getEntityTypeId())->deleteEntityRecords($entity);
    }
  }

  /**
   * Checks if the entity access control handler supports grants.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *
   * @return bool
   */
  public static function hasGrantAwareAccessController(EntityTypeInterface $entity_type) {
    return is_subclass_of($entity_type->getAccessControlClass(), '\Drupal\entity_access\GrantBasedEntityAccessControlHandlerInterface');
  }

}
