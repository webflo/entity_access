<?php

namespace Drupal\entity_access\Plugin\search_api\processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\entity_access\EntityAccessHelper;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\SearchApiException;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @SearchApiProcessor(
 *   id = "entity_access",
 *   label = @Translation("Entity access"),
 *   description = @Translation("Adds entity access checks for all enabled entity types."),
 *   stages = {
 *     "add_properties" = 0,
 *     "pre_index_save" = -10,
 *     "preprocess_query" = -30,
 *   }
 * )
 */
class EntityAccess extends ProcessorPluginBase {

  /**
   * The logger to use for logging messages.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface|null
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $processor->setLogger($container->get('logger.channel.search_api'));

    return $processor;
  }

  /**
   * Retrieves the logger to use.
   *
   * @return \Drupal\Core\Logger\LoggerChannelInterface
   *   The logger to use.
   */
  public function getLogger() {
    return $this->logger ?: \Drupal::service('logger.factory')->get('search_api');
  }

  /**
   * Sets the logger to use.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger to use.
   */
  public function setLogger(LoggerChannelInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      $entity_type_id = $datasource->getEntityTypeId();
      if ($entity_type_id && EntityAccessHelper::hasGrantAwareAccessController(\Drupal::entityTypeManager()->getDefinition($entity_type_id))) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $properties['search_api_entity_grants'] = new ProcessorProperty([
        'label' => $this->t('Entity access information'),
        'description' => $this->t('Data needed to apply entity access.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
        'is_list' => TRUE
      ]);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    static $anonymous_user;

    if (!isset($anonymous_user)) {
      // Load the anonymous user.
      $anonymous_user = new AnonymousUserSession();
    }

    if (!$this->entityTypeHasGrants($item->getDatasource()->getEntityTypeId())) {
      // Datasource (entity type) does not support grants.
      return;
    }

    // Get the entity object.
    $entity = $this->getEntity($item->getOriginalObject());
    if (!$entity) {
      // Apparently we were active for a wrong item.
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, 'search_api_entity_grants');
    foreach ($fields as $field) {
      // Collect grant information for the node.
      if (!$entity->access('view', $anonymous_user)) {
        // If anonymous user has no permission we collect all grants with their
        // realms in the item.
        $result = \Drupal::database()->query('SELECT * FROM {entity_access} WHERE entity_type = :entity_type AND (entity_id = 0 OR entity_id = :entity_id) AND grant_view = 1', array(':entity_type' => $entity->getEntityTypeId(), ':entity_id' => $entity->id()));
        foreach ($result as $grant) {
          $field->addValue("entity_access_{$grant->realm}:{$grant->gid}");
        }
      }
      else {
        // Add the generic pseudo view grant if we are not using node access or
        // the node is viewable by anonymous users.
        $field->addValue('entity_access__all');
      }
    }
  }

  /**
   * Retrieves the node related to an indexed search object.
   *
   * Will be either the node itself, or the node the comment is attached to.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   A search object that is being indexed.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The node related to that search object.
   */
  protected function getEntity(ComplexDataInterface $item) {
    $item = $item->getValue();
    if ($item instanceof ContentEntityInterface) {
      return $item;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    if (!$query->getOption('search_api_bypass_access')) {
      $account = $query->getOption('search_api_access_account', \Drupal::currentUser());
      if (is_numeric($account)) {
        $account = User::load($account);
      }
      if (is_object($account)) {
        try {
          $this->addEntityAccess($query, $account);
        }
        catch (SearchApiException $e) {
          watchdog_exception('search_api', $e);
        }
      }
      else {
        $account = $query->getOption('search_api_access_account', \Drupal::currentUser());
        if ($account instanceof AccountInterface) {
          $account = $account->id();
        }
        if (!is_scalar($account)) {
          $account = var_export($account, TRUE);
        }
        $this->getLogger()->warning('An illegal user UID was given for entity access: @uid.', array('@uid' => $account));
      }
    }
  }

  /**
   * Adds a node access filter to a search query, if applicable.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to which a node access filter should be added, if applicable.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for whom the search is executed.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   If not all necessary fields are indexed on the index.
   */
  protected function addEntityAccess(QueryInterface $query, AccountInterface $account) {
    // Gather the affected datasources, grouped by entity type, as well as the
    // unaffected ones.
    $affected_datasources = array();
    $unaffected_datasources = array();
    foreach ($this->index->getDatasources() as $datasource_id => $datasource) {
      $entity_type = $datasource->getEntityTypeId();
      if (static::entityTypeHasGrants($entity_type)) {
        $affected_datasources[$entity_type][] = $datasource_id;
      }
      else {
        $unaffected_datasources[] = $datasource_id;
      }
    }

    // The filter structure we want looks like this:
    //   [belongs to other datasource]
    //   OR
    //   (
    //     [is enabled (or was created by the user, if applicable)]
    //     AND
    //     [grants view access to one of the user's gid/realm combinations]
    //   )
    // If there are no "other" datasources, we don't need the nested OR,
    // however, and can add the inner conditions directly to the query.
    if ($unaffected_datasources) {
      $outer_conditions = $query->createConditionGroup('OR', array('entity_access'));
      $query->addConditionGroup($outer_conditions);
      foreach ($unaffected_datasources as $datasource_id) {
        $outer_conditions->addCondition('search_api_datasource', $datasource_id);
      }
      $access_conditions = $query->createConditionGroup('AND');
      $outer_conditions->addConditionGroup($access_conditions);
    }
    else {
      $access_conditions = $query;
    }

    // Filter by the user's node access grants.
    $entity_grants_field = $this->findField(NULL, 'search_api_entity_grants', 'string');
    if (!$entity_grants_field) {
      return;
    }
    $entity_grants_field_id = $entity_grants_field->getFieldIdentifier();
    $grants_conditions = $query->createConditionGroup('OR', array('entity_access_grants'));
    $grants = entity_access_grants('view', $account);
    foreach ($grants as $realm => $gids) {
      foreach ($gids as $gid) {
        $grants_conditions->addCondition($entity_grants_field_id, "entity_access_$realm:$gid");
      }
    }
    // Also add items that are accessible for everyone by checking the "access
    // all" pseudo grant.
    $grants_conditions->addCondition($entity_grants_field_id, 'entity_access__all');
    $access_conditions->addConditionGroup($grants_conditions);
  }

  protected function entityTypeHasGrants($entity_type) {
    static $cache;

    if (!isset($cache[$entity_type])) {
      $type = \Drupal::entityTypeManager()->getDefinition($entity_type);
      $cache[$entity_type] = EntityAccessHelper::hasGrantAwareAccessController($type);
    }

    return $cache[$entity_type];
  }

}
