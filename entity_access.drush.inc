<?php

/**
 * Implements hook_drush_command().
 *
 * @return array
 */
function entity_access_drush_command() {
  $items = [];

  $items['entity-access-rebuild'] = [
    'description' => 'Rebuild access entity table',
    'arguments' => [
      'entity_type' => 'The entity type',
    ]
  ];

  return $items;
}

/**
 * Drush command callback for 'entity-access-rebuild'
 */
function drush_entity_access_rebuild($entity_type) {
  $entity_type = \Drupal::entityManager()->getDefinition($entity_type);

  $storage = \Drupal::entityManager()->getStorage($entity_type->id());
  $id_key = $entity_type->getKey('id');
  $limit = 20;

  do {
    if (isset($entity_ids)) {
      $storage->resetCache();
    }

    $query = \Drupal::entityQuery($entity_type->id())
      ->sort($id_key, 'DESC')
      ->range(0, $limit);

    if (isset($last_id)) {
      $query->condition($id_key, $last_id, '<');
    }

    $entity_ids = $query->execute();
    $last_id = end($entity_ids);
    $entities = $storage->loadMultiple($entity_ids);

    foreach ($entities as $entity) {
      \Drupal\entity_access\EntityAccessHelper::update($entity);
    }
  } while (count($entity_ids) > 0);
}
