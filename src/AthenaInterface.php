<?php

declare(strict_types=1);

namespace Drupal\hd_athena;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an athena entity type.
 */
interface AthenaInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
