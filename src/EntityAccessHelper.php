<?php

/**
 * @file
 * Contains \Drupal\entity\EntityAccessHelper.
 */

namespace Drupal\entity_access;

use Drupal\Core\Entity\EntityInterface;

class EntityAccessHelper {

  public static function update(EntityInterface $entity) {
    if (self::hasGrantAwareAccessController($entity)) {
      \Drupal::entityManager()->getAccessControlHandler($entity->getEntityTypeId())->writeGrants($entity);
    }
  }

  public static function delete(EntityInterface $entity) {
    if (self::hasGrantAwareAccessController($entity)) {
      \Drupal::entityManager()->getAccessControlHandler($entity->getEntityTypeId())->deleteGrants($entity);
    }
  }

  protected static function hasGrantAwareAccessController(EntityInterface $entity) {
    $type = $entity->getEntityType();
    return is_subclass_of($type->getAccessControlClass(), '\Drupal\entity_access\GrantBasedEntityAccessControlHandlerInterface');
  }

}
