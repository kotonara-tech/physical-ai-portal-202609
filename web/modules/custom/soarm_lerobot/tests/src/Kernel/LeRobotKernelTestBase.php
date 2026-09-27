<?php

declare(strict_types=1);

namespace Drupal\Tests\soarm_lerobot\Kernel;

use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\soarm_core\Kernel\SoarmCoreKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Base class with helpers to build posts that carry LeRobot files.
 */
abstract class LeRobotKernelTestBase extends SoarmCoreKernelTestBase {

  use UserCreationTrait;

  protected const PARQUET = 'PAR1' . "\0\0\0\0\0\0\0\0" . 'PAR1';
  protected const HDF5 = "\x89HDF\r\n\x1a\n\0\0\0\0\0\0\0\0";
  protected const VALID_YAML = "robot_type: so-arm101\nfps: 30\ntask: pick\nphases:\n  - name: grasp\n    start_frame: 0\n    end_frame: 40\n  - name: move\n  - name: release\n    start_frame: 121\n    end_frame: 150\n";

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['soarm_lerobot'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Burn uid 1 (bypasses access), then act as an ordinary author: file
    // references are only valid for users who may use the referenced file.
    $this->createUser();
    $this->setCurrentUser($this->createUser(['access content', 'create robot_knowledge content']));
  }

  /**
   * Creates a saved file entity in public:// with the given bytes.
   */
  protected function createFile(string $filename, string $bytes): FileInterface {
    $uri = 'public://' . $filename;
    file_put_contents($uri, $bytes);
    $file = File::create(['uri' => $uri, 'filename' => $filename, 'uid' => \Drupal::currentUser()->id()]);
    $file->setPermanent();
    $file->save();
    return $file;
  }

  /**
   * Creates an unsaved post referencing the given files.
   *
   * @param \Drupal\file\FileInterface[] $files
   *   Files keyed by field name.
   */
  protected function buildPost(array $files): NodeInterface {
    $values = ['type' => 'robot_knowledge', 'title' => 'post', 'field_outcome' => 'success', 'status' => 1];
    foreach ($files as $field => $file) {
      $values[$field] = ['target_id' => $file->id()];
    }
    return Node::create($values);
  }

  /**
   * Returns the violation messages whose path starts with the given field.
   *
   * @return string[]
   *   The messages.
   */
  protected function violationsOn(NodeInterface $node, string $field): array {
    $messages = [];
    foreach ($node->validate() as $violation) {
      if (str_starts_with($violation->getPropertyPath(), $field)) {
        $messages[] = (string) $violation->getMessage();
      }
    }
    return $messages;
  }

}
