<?php

declare(strict_types=1);

namespace Drupal\Tests\soarm_core\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;

/**
 * Tests the "robot knowledge post" content model shipped as config.
 */
#[Group('soarm_core')]
#[Medium]
final class ContentModelTest extends SoarmCoreKernelTestBase {

  /**
   * Tests that the robot_knowledge content type exists.
   */
  public function testContentTypeExists(): void {
    $this->assertNotNull(NodeType::load('robot_knowledge'));
  }

  /**
   * Tests that the given vocabulary exists.
   */
  #[DataProvider('vocabularyProvider')]
  public function testVocabularyExists(string $vid): void {
    $this->assertNotNull(Vocabulary::load($vid), "Vocabulary $vid is missing.");
  }

  /**
   * Data provider for testVocabularyExists().
   */
  public static function vocabularyProvider(): array {
    return [
      ['task_category'],
      ['difficulty'],
      ['robot_model'],
      ['tech_tags'],
      ['difficulty_factor'],
    ];
  }

  /**
   * Tests that a node field has the expected type and cardinality.
   *
   * @param string $field
   *   Field name on node.robot_knowledge.
   * @param string $type
   *   Expected field type.
   * @param int $cardinality
   *   Expected cardinality (-1 = unlimited).
   */
  #[DataProvider('nodeFieldProvider')]
  public function testNodeField(string $field, string $type, int $cardinality): void {
    $config = FieldConfig::loadByName('node', 'robot_knowledge', $field);
    $this->assertNotNull($config, "Field $field is missing.");
    $this->assertSame($type, $config->getType());
    $this->assertSame($cardinality, FieldStorageConfig::loadByName('node', $field)->getCardinality());
  }

  /**
   * Data provider for testNodeField().
   */
  public static function nodeFieldProvider(): array {
    return [
      'task category' => ['field_task_category', 'entity_reference', 1],
      'difficulty' => ['field_difficulty', 'entity_reference', 1],
      'robot model (100 / 101 / both)' => ['field_robot_model', 'entity_reference', -1],
      'tech tags' => ['field_tech_tags', 'entity_reference', -1],
      'difficulty factors (fixed list)' => ['field_difficulty_factors', 'entity_reference', -1],
      'difficulty notes (free text)' => ['field_difficulty_notes', 'string_long', 1],
      'outcome' => ['field_outcome', 'list_string', 1],
      'resolved' => ['field_resolved', 'boolean', 1],
      'video' => ['field_video', 'file', 1],
      'trajectory' => ['field_trajectory', 'file', 1],
      'metadata yaml' => ['field_metadata_yaml', 'file', 1],
      'camera images' => ['field_camera_images', 'image', -1],
      'gripper' => ['field_gripper', 'string', 1],
      'sensors' => ['field_sensors', 'string', -1],
      'calibration' => ['field_calibration', 'string_long', 1],
      'environment' => ['field_environment', 'string_long', 1],
      'procedure (Markdown)' => ['field_procedure', 'text_long', 1],
      'HuggingFace repo' => ['field_hf_repo', 'string', 1],
      'links' => ['field_links', 'link', -1],
      'comments' => ['field_comments', 'comment', 1],
    ];
  }

  /**
   * Tests that the task category and outcome fields are required.
   */
  public function testRequiredFields(): void {
    foreach (['field_task_category', 'field_outcome'] as $field) {
      $this->assertTrue(FieldConfig::loadByName('node', 'robot_knowledge', $field)->isRequired(), "$field must be required.");
    }
  }

  /**
   * Tests that entity reference fields target the right vocabulary.
   */
  public function testReferenceFieldsTargetTheRightVocabulary(): void {
    $expected = [
      'field_task_category' => 'task_category',
      'field_difficulty' => 'difficulty',
      'field_robot_model' => 'robot_model',
      'field_tech_tags' => 'tech_tags',
      'field_difficulty_factors' => 'difficulty_factor',
    ];
    foreach ($expected as $field => $vid) {
      $settings = FieldConfig::loadByName('node', 'robot_knowledge', $field)->getSetting('handler_settings');
      $this->assertSame([$vid => $vid], $settings['target_bundles'], $field);
    }
  }

  /**
   * Tests that tech tags can be created on the fly.
   */
  public function testTechTagsCanBeCreatedOnTheFly(): void {
    $settings = FieldConfig::loadByName('node', 'robot_knowledge', 'field_tech_tags')->getSetting('handler_settings');
    $this->assertTrue($settings['auto_create']);
  }

  /**
   * Tests the allowed file extensions on the file fields.
   */
  public function testFileExtensions(): void {
    $expected = [
      'field_video' => ['mp4', 'webm'],
      'field_trajectory' => ['hdf5', 'h5', 'parquet'],
      'field_metadata_yaml' => ['yaml', 'yml'],
    ];
    foreach ($expected as $field => $extensions) {
      $actual = explode(' ', FieldConfig::loadByName('node', 'robot_knowledge', $field)->getSetting('file_extensions'));
      $this->assertEqualsCanonicalizing($extensions, $actual, $field);
    }
  }

  /**
   * Tests the allowed values list on field_outcome.
   */
  public function testOutcomeAllowedValues(): void {
    $values = FieldStorageConfig::loadByName('node', 'field_outcome')->getSetting('allowed_values');
    $this->assertSame(['success', 'partial', 'failure'], array_keys($values));
  }

  /**
   * Tests that an unknown outcome value fails validation.
   */
  public function testUnknownOutcomeFailsValidation(): void {
    $this->assertNotEmpty($this->outcomeViolations('maybe'));
  }

  /**
   * Tests that a known outcome value passes validation.
   */
  public function testKnownOutcomePassesValidation(): void {
    $this->assertSame([], $this->outcomeViolations('partial'));
  }

  /**
   * Returns the property paths of violations on field_outcome.
   *
   * Core reports an unknown list value on the field item ("field_outcome.0"),
   * so match on the field rather than on one exact path.
   *
   * @return string[]
   *   The property paths.
   */
  private function outcomeViolations(string $outcome): array {
    $node = Node::create(['type' => 'robot_knowledge', 'title' => 'x', 'field_outcome' => $outcome]);
    $paths = [];
    foreach ($node->validate() as $violation) {
      if (str_starts_with($violation->getPropertyPath(), 'field_outcome')) {
        $paths[] = $violation->getPropertyPath();
      }
    }
    return $paths;
  }

  /**
   * Tests that field_procedure only allows the soarm_markdown format.
   */
  public function testProcedureOnlyAllowsTheMarkdownFormat(): void {
    $config = FieldConfig::loadByName('node', 'robot_knowledge', 'field_procedure');
    $this->assertSame(['soarm_markdown'], $config->getSetting('allowed_formats'));
  }

  /**
   * Tests that the knowledge_comment comment type targets nodes.
   */
  public function testCommentTypeTargetsNodes(): void {
    $type = $this->container->get('entity_type.manager')->getStorage('comment_type')->load('knowledge_comment');
    $this->assertNotNull($type);
    $this->assertSame('node', $type->getTargetEntityTypeId());
    $this->assertNotNull(FieldConfig::loadByName('comment', 'knowledge_comment', 'comment_body'));
  }

  /**
   * Tests that the comment body is shown on view and editable on the form.
   */
  public function testCommentBodyIsShownAndEditable(): void {
    $displays = $this->container->get('entity_display.repository');

    $view = $displays->getViewDisplay('comment', 'knowledge_comment');
    $this->assertFalse($view->isNew(), 'The comment view display must ship as config.');
    $this->assertSame('text_default', $view->getComponent('comment_body')['type'] ?? NULL);

    $form = $displays->getFormDisplay('comment', 'knowledge_comment');
    $this->assertFalse($form->isNew(), 'The comment form display must ship as config.');
    $this->assertSame('text_textarea', $form->getComponent('comment_body')['type'] ?? NULL);
  }

  /**
   * Tests that the user profile fields exist with the expected type.
   */
  public function testUserProfileFields(): void {
    foreach (['field_affiliation' => 'string', 'field_expertise' => 'string'] as $field => $type) {
      $config = FieldConfig::loadByName('user', 'user', $field);
      $this->assertNotNull($config, "User field $field is missing.");
      $this->assertSame($type, $config->getType());
    }
  }

  /**
   * Tests the permissions granted to the soarm_contributor role.
   */
  public function testContributorRolePermissions(): void {
    $role = Role::load('soarm_contributor');
    $this->assertNotNull($role);
    foreach ([
      'create robot_knowledge content',
      'edit own robot_knowledge content',
      'delete own robot_knowledge content',
      'use text format soarm_markdown',
      'post comments',
      'skip comment approval',
    ] as $permission) {
      $this->assertTrue($role->hasPermission($permission), "Missing permission: $permission");
    }
    $this->assertFalse($role->hasPermission('administer nodes'));
  }

}
