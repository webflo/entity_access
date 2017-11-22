<?php

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Implements hook_views_data_alter().
 */
function entity_access_views_data_alter(array &$data) {
  /**
   * @var EntityTypeInterface[] $entity_types
   */
  $entity_types = array_filter(\Drupal::entityManager()->getDefinitions(), function (EntityTypeInterface $type) {
    return is_subclass_of($type->getAccessControlClass(), '\Drupal\entity_access\GrantBasedEntityAccessControlHandlerInterface');
  });

  $data['entity_access']['table']['group'] = t('Entity access');
  $data['entity_access']['table']['provider'] = 'entity_access';

  $data['entity_access']['entity_id'] = array(
    'title' => t('Entity ID'),
    'field' => array(
      'id' => 'standard',
    ),
  );
  $data['entity_access']['entity_type'] = array(
    'title' => t('Entity type'),
    'field' => array(
      'id' => 'standard',
    ),
  );

  foreach ($entity_types as $type) {
    $base_table = $type->getDataTable();
    if (isset($data[$base_table])) {
      $data['entity_access']['table']['join'][$base_table] = [
        'left_field' => $type->getKey('id'),
        'field' => 'entity_id',
        'extra' => [
          0 => [
            'field' => 'entity_type',
            'value' => $type->id(),
          ]
        ],
      ];
    }

    $data['entity_access']['entity_access_' . $type->id()] = [
      'title' => 'Access: ' . $type->id(),
      'filter' => [
        'id' => 'entity_grant_access',
        'real field' => 'entity_id',
      ]
    ];
  }
}
