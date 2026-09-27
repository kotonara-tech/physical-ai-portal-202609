<?php

declare(strict_types=1);

namespace Drupal\Tests\soarm_core\Kernel;

use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;

/**
 * Tests the comment permissions granted to the built-in roles on install.
 *
 * The standard profile does not enable Comment, so nobody can even read
 * comments unless soarm_core grants it. Kernel tests do not run
 * hook_install(), so the work lives in a service that hook_install() calls.
 */
#[Group('soarm_core')]
#[Medium]
final class DefaultPermissionsInstallerTest extends SoarmCoreKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['user']);
  }

  /**
   * Tests that anonymous visitors can read comments but not post them.
   */
  public function testVisitorsCanReadCommentsButNotPost(): void {
    $this->container->get('soarm_core.default_permissions')->install();

    $anonymous = Role::load(RoleInterface::ANONYMOUS_ID);
    $this->assertTrue($anonymous->hasPermission('access comments'));
    $this->assertFalse($anonymous->hasPermission('post comments'));
  }

  /**
   * Tests that authenticated users can read and post comments.
   */
  public function testLoggedInUsersCanReadAndPostComments(): void {
    $this->container->get('soarm_core.default_permissions')->install();

    $authenticated = Role::load(RoleInterface::AUTHENTICATED_ID);
    foreach (['access comments', 'post comments', 'skip comment approval'] as $permission) {
      $this->assertTrue($authenticated->hasPermission($permission), $permission);
    }
  }

  /**
   * Tests that permissions already granted before install() are kept.
   */
  public function testExistingPermissionsAreKept(): void {
    Role::load(RoleInterface::ANONYMOUS_ID)->grantPermission('access content')->save();

    $this->container->get('soarm_core.default_permissions')->install();

    $this->assertTrue(Role::load(RoleInterface::ANONYMOUS_ID)->hasPermission('access content'));
  }

  /**
   * Tests that install() skips roles that do not exist.
   */
  public function testMissingRolesAreSkipped(): void {
    Role::load(RoleInterface::ANONYMOUS_ID)->delete();

    $this->container->get('soarm_core.default_permissions')->install();

    $this->assertNull(Role::load(RoleInterface::ANONYMOUS_ID));
    $this->assertTrue(Role::load(RoleInterface::AUTHENTICATED_ID)->hasPermission('access comments'));
  }

}
