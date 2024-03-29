<?php

namespace Drupal\entity_access\Plugin\views\filter;

use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filter by entity access records.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("entity_grant_access")
 */
class Access extends FilterPluginBase {

  public function adminSummary() {
  }

  protected function operatorForm(&$form, FormStateInterface $form_state) {
  }

  public function canExpose() {
    return FALSE;
  }

  /**
   * See _entity_access_access_where_sql() for a non-views query based implementation.
   */
  public function query() {
    $account = $this->view->getUser();
    if (!$account->hasPermission('bypass entity access')) {
      $table = $this->ensureMyTable();
      $or_group = new Condition('OR');
      foreach (entity_access_grants('view', $account) as $realm => $gids) {
        foreach ($gids as $gid) {
          $and_group = new Condition('AND');
          $or_group->condition($and_group
            ->condition($table . '.gid', $gid)
            ->condition($table . '.realm', $realm)
            ->condition($table . '.grant_view', 1, '>=')
          );
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();

    // entity_access access is potentially cacheable per user.
    $contexts[] = 'user';

    return $contexts;
  }

}
