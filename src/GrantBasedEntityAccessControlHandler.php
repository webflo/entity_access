<?php

/**
 * @file
 * Contains \Drupal\entity\GrantBasedEntityAccessControlHandler.
 */

namespace Drupal\entity_access;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;

class GrantBasedEntityAccessControlHandler extends EntityAccessControlHandler implements GrantBasedEntityAccessControlHandlerInterface {

  use GrantBasedEntityAccessTrait;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, $langcode, AccountInterface $account) {
    $result = parent::checkAccess($entity, $operation, $langcode, $account);

    if ($result->isNeutral()) {
      // Evaluate entity grants.

      /**
       * @todo: Not sure about this logic,
       */
      if ($langcode == LanguageInterface::LANGCODE_DEFAULT) {
        $langcode = $entity->language()->getId();
      }

      $result = $this->grantStorage()->access($entity, $operation, $langcode, $account);
      return $result;
    }

    return $result;
  }


}
