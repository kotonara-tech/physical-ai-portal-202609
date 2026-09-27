<?php

declare(strict_types=1);

namespace Drupal\soarm_core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\RoleInterface;

/**
 * Grants the default comment permissions to the built-in roles.
 */
final class DefaultPermissionsInstaller {

  /**
   * Permissions to grant, keyed by role machine name.
   */
  private const ROLE_PERMISSIONS = [
    RoleInterface::ANONYMOUS_ID => [
      'access comments',
    ],
    RoleInterface::AUTHENTICATED_ID => [
      'access comments',
      'post comments',
      'skip comment approval',
    ],
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Grants the default comment permissions, keeping existing ones.
   *
   * Roles that do not exist are silently skipped.
   */
  public function install(): void {
    $storage = $this->entityTypeManager->getStorage('user_role');

    foreach (self::ROLE_PERMISSIONS as $roleId => $permissions) {
      $role = $storage->load($roleId);

      if ($role === NULL) {
        continue;
      }

      foreach ($permissions as $permission) {
        $role->grantPermission($permission);
      }

      $role->save();
    }
  }

}
