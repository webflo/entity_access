<?php

/**
 * @file
 * Contains \Drupal\entity\GrantBasedEntityAccessControlHandler.
 */

namespace Drupal\entity_access;

use Drupal\Core\Entity\EntityAccessControlHandler;

class GrantBasedEntityAccessControlHandler extends EntityAccessControlHandler implements GrantBasedEntityAccessControlHandlerInterface {

  use GrantBasedEntityAccessTrait;

}
