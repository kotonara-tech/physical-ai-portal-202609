<?php

declare(strict_types=1);

namespace Drupal\soarm_lerobot\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\FileInterface;
use Drupal\soarm_lerobot\TrajectoryFormatDetector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the SoarmTrajectoryFormat constraint.
 */
final class SoarmTrajectoryFormatValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a SoarmTrajectoryFormatValidator.
   *
   * @param \Drupal\soarm_lerobot\TrajectoryFormatDetector $formatDetector
   *   Detects the trajectory file's format by content.
   */
  public function __construct(
    private readonly TrajectoryFormatDetector $formatDetector,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('soarm_lerobot.trajectory_format_detector'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof SoarmTrajectoryFormat) {
      throw new UnexpectedTypeException($constraint, SoarmTrajectoryFormat::class);
    }
    if (!$value instanceof FieldItemListInterface) {
      return;
    }

    foreach ($value as $delta => $item) {
      $file = $item->entity ?? NULL;
      // Skip items whose file entity cannot be loaded: core already reports
      // those (e.g. via the entity reference "valid reference" constraint).
      if (!$file instanceof FileInterface) {
        continue;
      }

      if ($this->formatDetector->detect($file->getFileUri()) === NULL) {
        $this->context->buildViolation($constraint->message, ['%filename' => $file->getFilename()])
          ->atPath((string) $delta)
          ->addViolation();
      }
    }
  }

}
