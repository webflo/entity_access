<?php

/**
 * @file
 * Contains \Drupal\entity\EntityAccessHelper.
 */

namespace Drupal\entity_access;

use Drupal\Core\Entity\EntityInterface;

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
    if (self::hasGrantAwareAccessController($entity)) {
      \Drupal::entityManager()->getAccessControlHandler($entity->getEntityTypeId())->writeGrants($entity);
    }
  }

  /**
   * Delete grants after entity delete.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public static function delete(EntityInterface $entity) {
    if (self::hasGrantAwareAccessController($entity)) {
      \Drupal::entityManager()->getAccessControlHandler($entity->getEntityTypeId())->deleteGrants($entity);
    }
  }

  /**
   * Checks if the entity access control handler supports grants.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  protected static function hasGrantAwareAccessController(EntityInterface $entity) {
    $type = $entity->getEntityType();
    return is_subclass_of($type->getAccessControlClass(), '\Drupal\entity_access\GrantBasedEntityAccessControlHandlerInterface');
  }

}
