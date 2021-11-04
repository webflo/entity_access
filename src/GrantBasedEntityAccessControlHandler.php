<?php

namespace Drupal\entity_access;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

class GrantBasedEntityAccessControlHandler extends EntityAccessControlHandler implements GrantBasedEntityAccessControlHandlerInterface {

  use GrantBasedEntityAccessTrait;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $result = parent::checkAccess($entity, $operation, $account);

    if ($result->isNeutral()) {
      // Evaluate entity grants.
      $result = $this->grantStorage()->access($entity, $operation, $entity->language()->getId(), $account);
    }

    return $result;
  }


}
