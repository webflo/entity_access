<?php

/**
 * @file
 * Contains \Drupal\entity_access\EntityGrantDatabaseStorage.
 */

namespace Drupal\entity_access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines a controller class that handles the entity grants system.
 *
 * This is used to build entity query access.
 *
 * @ingroup entity_access
 */
class EntityGrantDatabaseStorage implements EntityGrantDatabaseStorageInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a EntityGrantDatabaseStorage object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(Connection $database, ModuleHandlerInterface $module_handler, LanguageManagerInterface $language_manager) {
    $this->database = $database;
    $this->moduleHandler = $module_handler;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function access(ContentEntityInterface $entity, $operation, $langcode, AccountInterface $account) {
    // If no module implements the hook or the entity does not have an id there is
    // no point in querying the database for access grants.
    if (!$this->moduleHandler->getImplementations('entity_grants') || !$entity->id()) {
      return AccessResult::neutral();
    }

    // Check the database for potential access grants.
    $query = $this->database->select('entity_access');
    $query->addExpression('1');
    $query->condition('entity_type', $entity->getEntityTypeId());
    // Only interested for granting in the current operation.
    $query->condition('grant_' . $operation, 1, '>=');
    // Check for grants for this entity and the correct langcode.
    $entity_ids = $query->andConditionGroup()
      ->condition('entity_id', $entity->id())
      ->condition('langcode', $langcode);
    // If the entity is published, also take the default grant into account. The
    // default is saved with a entity ID of 0.
    $status = $entity->isPublished();
    if ($status) {
      $entity_ids = $query->orConditionGroup()
        ->condition($entity_ids)
        ->condition('entity_id', 0);
    }
    $query->condition($entity_ids);
    $query->range(0, 1);

    $grants = static::buildGrantsQueryCondition(entity_access_grants($operation, $account));

    if (count($grants) > 0) {
      $query->condition($grants);
    }

    // Only the 'view' entity grant can currently be cached; the others currently
    // don't have any cacheability metadata. Hopefully, we can add that in the
    // future, which would allow this access check result to be cacheable in all
    // cases. For now, this must remain marked as uncacheable, even when it is
    // theoretically cacheable, because we don't have the necessary metadata to
    // know it for a fact.
    $set_cacheability = function (AccessResult $access_result) use ($operation) {
      // $access_result->addCacheContexts(['user.entity_grants:' . $operation]);
      if ($operation !== 'view') {
        $access_result->setCacheMaxAge(0);
      }
      return $access_result;
    };

    /*
    debug($langcode);
    debug($entity_ids);
    debug((string) $query);
    */

    if ($query->execute()->fetchField()) {
      return $set_cacheability(AccessResult::allowed());
    }
    else {
      return $set_cacheability(AccessResult::neutral());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkAll(AccountInterface $account) {
    $query = $this->database->select('entity_access');
    $query->addExpression('COUNT(*)');
    $query
      ->condition('entity_id', 0)
      ->condition('grant_view', 1, '>=');

    $grants = static::buildGrantsQueryCondition(entity_access_grants('view', $account));

    if (count($grants) > 0 ) {
      $query->condition($grants);
    }
    return $query->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function alterQuery($query, array $tables, $op, AccountInterface $account, $base_table) {
    if (!$langcode = $query->getMetaData('langcode')) {
      $langcode = FALSE;
    }

    // Find all instances of the base table being joined -- could appear
    // more than once in the query, and could be aliased. Join each one to
    // the entity_access table.
    $grants = entity_access_grants($op, $account);
    foreach ($tables as $nalias => $tableinfo) {
      $table = $tableinfo['table'];
      if (!($table instanceof SelectInterface) && $table == $base_table) {
        // Set the subquery.
        $subquery = $this->database->select('entity_access', 'na')
          ->fields('na', array('entity_id'));

        // If any grant exists for the specified user, then user has access to the
        // entity for the specified operation.
        $grant_conditions = static::buildGrantsQueryCondition($grants);

        // Attach conditions to the subquery for entitys.
        if (count($grant_conditions->conditions())) {
          $subquery->condition($grant_conditions);
        }
        $subquery->condition('na.grant_' . $op, 1, '>=');

        // Add langcode-based filtering if this is a multilingual site.
        if (\Drupal::languageManager()->isMultilingual()) {
          // If no specific langcode to check for is given, use the grant entry
          // which is set as a fallback.
          // If a specific langcode is given, use the grant entry for it.
          if ($langcode === FALSE) {
            $subquery->condition('na.fallback', 1, '=');
          }
          else {
            $subquery->condition('na.langcode', $langcode, '=');
          }
        }

        $field = 'entity_id';
        // Now handle entities.
        $subquery->where("$nalias.$field = na.nid");

        $query->exists($subquery);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function write(ContentEntityInterface $entity, array $grants, $realm = NULL, $delete = TRUE) {
    if ($delete) {
      $query = $this->database->delete('entity_access')->condition('entity_type', $entity->getEntityTypeId())->condition('entity_id', $entity->id());
      if ($realm) {
        $query->condition('realm', array($realm, 'all'), 'IN');
      }
      $query->execute();
    }
    // Only perform work when entity_access modules are active.
    if (!empty($grants) && count($this->moduleHandler->getImplementations('entity_grants'))) {
      $query = $this->database->insert('entity_access')->fields(array('entity_type', 'entity_id', 'langcode', 'fallback', 'realm', 'gid', 'grant_view', 'grant_update', 'grant_delete'));
      // If we have defined a granted langcode, use it. But if not, add a grant
      // for every language this entity is translated to.
      foreach ($grants as $grant) {
        if ($realm && $realm != $grant['realm']) {
          continue;
        }
        if (isset($grant['langcode'])) {
          $grant_languages = array($grant['langcode'] => $this->languageManager->getLanguage($grant['langcode']));
        }
        else {
          $grant_languages = $entity->getTranslationLanguages(TRUE);
        }
        foreach ($grant_languages as $grant_langcode => $grant_language) {
          // Only write grants; denies are implicit.
          if ($grant['grant_view'] || $grant['grant_update'] || $grant['grant_delete']) {
            $grant['entity_type'] = $entity->getEntityTypeId();
            $grant['entity_id'] = $entity->id();
            $grant['langcode'] = $grant_langcode;
            // The record with the original langcode is used as the fallback.
            if ($grant['langcode'] == $entity->language()->getId()) {
              $grant['fallback'] = 1;
            }
            else {
              $grant['fallback'] = 0;
            }
            $query->values($grant);
          }
        }
      }
      $query->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    $this->database->truncate('entity_access')->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function writeDefault() {
    $this->database->insert('entity_access')
      ->fields(array(
          'entity_id' => 0,
          'realm' => 'all',
          'gid' => 0,
          'grant_view' => 1,
          'grant_update' => 0,
          'grant_delete' => 0,
        ))
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function count() {
    return $this->database->query('SELECT COUNT(*) FROM {entity_access}')->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteNodeRecords(array $entity_ids) {
    $this->database->delete('entity_access')
      ->condition('entity_id', $entity_ids, 'IN')
      ->execute();
  }

  /**
   * Creates a query condition from an array of entity access grants.
   *
   * @param array $entity_access_grants
   *   An array of grants, as returned by entity_access_grants().
   * @return \Drupal\Core\Database\Query\Condition
   *   A condition object to be passed to $query->condition().
   *
   * @see entity_access_grants()
   */
  protected static function buildGrantsQueryCondition(array $entity_access_grants) {
    $grants = new Condition("OR");
    foreach ($entity_access_grants as $realm => $gids) {
      if (!empty($gids)) {
        $and = new Condition('AND');
        $grants->condition($and
          ->condition('gid', $gids, 'IN')
          ->condition('realm', $realm)
        );
      }
    }

    return $grants;
  }

}
