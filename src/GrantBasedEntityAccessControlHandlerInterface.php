<?php
/**
 * @file
 * Contains \Drupal\entity\GrantBasedEntityAccessControlHandlerInterface.
 */

namespace Drupal\entity_access;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Grant based entity access control methods.
 *
 * @ingroup entity_access
 */
interface GrantBasedEntityAccessControlHandlerInterface {

  /**
   * Gets the list of entity access grants.
   *
   * This function is called to check the access grants for a entity. It collects
   * all entity access grants for the entity from hook_entity_access_records()
   * implementations, allows these grants to be altered via
   * hook_entity_access_records_alter() implementations, and returns the grants to
   * the caller.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The $entity to acquire grants for.
   *
   * @return array $grants
   *   The access rules for the entity.
   */
  public function acquireGrants(ContentEntityInterface $entity);

  /**
   * Writes a list of grants to the database, deleting any previously saved ones.
   *
   * If a realm is provided, it will only delete grants from that realm, but it
   * will always delete a grant from the 'all' realm. Modules that use entity
   * access can use this function when doing mass updates due to widespread
   * permission changes.
   *
   * Note: Don't call this function directly from a contributed module. Call
   * entity_access_acquire_grants() instead.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity whose grants are being written.
   * @param $delete
   *   (optional) If false, does not delete records. This is only for optimization
   *   purposes, and assumes the caller has already performed a mass delete of
   *   some form. Defaults to TRUE.
   */
  public function writeGrants(ContentEntityInterface $entity, $delete = TRUE);

  /**
   * Creates the default entity access grant entry on the grant storage.
   */
  public function writeDefaultGrant();

  /**
   * Deletes all entity access entries.
   */
  public function deleteGrants();

  /**
   * Counts available entity grants.
   *
   * @return int
   *   Returns the amount of entity grants.
   */
  public function countGrants();

  /**
   * Checks all grants for a given account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A user object representing the user for whom the operation is to be
   *   performed.
   *
   * @return int.
   *   Status of the access check.
   */
  public function checkAllGrants(AccountInterface $account);

}
