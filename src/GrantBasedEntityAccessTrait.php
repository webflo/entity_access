<?php

namespace Drupal\entity_access;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

trait GrantBasedEntityAccessTrait {

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface;
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\entity_access\EntityGrantDatabaseStorageInterface
   */
  protected $grantStorage;

  /**
   * {@inheritdoc}
   */
  public function acquireGrants(ContentEntityInterface $entity) {
    $grants = $this->moduleHandler()->invokeAll('entity_access_records', array($entity));
    // Let modules alter the grants.
    $this->moduleHandler()->alter('entity_access_records', $grants, $entity);
    if (empty($grants)) {
      $grants[] = array(
        'realm' => 'all',
        'gid' => 0,
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0
      );
    }
    return $grants;
  }

  /**
   * {@inheritdoc}
   */
  public function writeGrants(ContentEntityInterface $entity, $delete = TRUE) {
    $grants = $this->acquireGrants($entity);
    $this->grantStorage()->write($entity, $grants, NULL, $delete);
  }

  /**
   * {@inheritdoc}
   */
  public function writeDefaultGrant() {
    $this->grantStorage()->writeDefault();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteGrants() {
    $this->grantStorage()->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function countGrants() {
    return $this->grantStorage()->count();
  }

  /**
   * {@inheritdoc}
   */
  public function checkAllGrants(AccountInterface $account) {
    return $this->grantStorage()->checkAll($account);
  }

  /**
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected function moduleHandler() {
    if (!isset($this->moduleHandler)) {
      $this->moduleHandler = \Drupal::service('module_handler');
    }
    return $this->moduleHandler;
  }

  protected function grantStorage() {
    if (!isset($this->grantStorage)) {
      $this->grantStorage = \Drupal::service('entity_access.grant_storage');
    }
    return $this->grantStorage;
  }

}
