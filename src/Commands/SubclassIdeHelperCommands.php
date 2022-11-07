<?php

declare(strict_types = 1);

namespace Drupal\subclass_ide_helper\Commands;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\field\FieldConfigInterface;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 */
class SubclassIdeHelperCommands extends DrushCommands {

  const DEFAULT_FILENAME = '_ide_helper_subclassed_bundles.php';

  /**
   * Constructs a new SubclassIdeHelperCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file handler.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typedDataManager
   *   The typed configuration manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected FileSystemInterface $fileSystem,
    protected TypedDataManagerInterface $typedDataManager,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {
  }

  /**
   * Get field properties docblock.
   *
   * @command subclass_ide_helper:generate
   * @aliases sih
   */
  public function generateBundleProperties(
    $entity_types = 'node',
    $options = ['result-file' => NULL]
  ): void {
    $output = $this->initialOutput();

    $output .= $this->generateFieldProperties($entity_types);

    $this->writeFile($output, $options['result-file']);
  }

  /**
   * Adds the file scaffolding.
   */
  protected function initialOutput(): string {
    return <<<EOF
    <?php

    // phpcs:ignoreFile

    /**
     * A helper file for node bundle classes.
     */


    EOF;
  }

  /**
   * Adds a line to the output.
   *
   * Indentation and trailing blank lines can be specified.
   */
  protected function addLine(string $content, ?int $extra_blank_lines = 0, ?int $indentation = 0): string {
    $output = '';
    for ($i = 0; $i < $indentation; $i++) {
      $output .= ' ';
    }

    $output .= "{$content}\n";

    for ($i = 0; $i < $extra_blank_lines; $i++) {
      $output .= "\n";
    }

    return $output;
  }

  /**
   * Generate the field properties for each subclassed bundle.
   */
  protected function generateFieldProperties(string $entity_types): string {
    $output = '';
    foreach ($this->splitEntityTypes($entity_types) as $entity_type) {
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
      foreach ($bundles as $bundle_name => $bundle) {
        if (empty($bundle['class'])) {
          continue;
        }

        $class_array = explode('\\', $bundle['class']);
        $class_name = array_pop($class_array);
        $namespace = implode('\\', $class_array);
        $output .= $this->addLine("namespace {$namespace} {", 1);
        $output .= $this->addLine('/**', 0, 2);

        $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle_name);
        foreach ($field_definitions as $key => $field_definition) {
          if (!$field_definition instanceof FieldConfigInterface) {
            continue;
          }

          $required = $field_definition->isRequired();
          $null = !$required
            ? '|null'
            : '';

          $field_class = get_class($this->typedDataManager->create($field_definition));
          $field_name = '$' . $key;
          $output .= $this->addLine("* @property \\{$field_class}{$null} {$field_name} {$field_definition->label()}", 0, 3);
        }
        $output .= $this->addLine('*/', 0, 3);
        $output .= $this->addLine("class {$class_name} {}", 1, 2);
        $output .= $this->addLine('}', 1);
      }
    }
    return $output;
  }

  /**
   * Splits the entity types string.
   */
  protected function splitEntityTypes(string $entity_types_string): array {
    return array_map(
      fn (string $entity_type) => trim($entity_type),
      explode(',', $entity_types_string)
    );
  }

  /**
   * Writes the output to a file.
   */
  protected function writeFile(string $output, ?string $filepath = NULL): void {
    $filepath = empty($filepath)
      ? sprintf("../%s", static::DEFAULT_FILENAME)
      : $filepath;

    try {
      $this->fileSystem->saveData($output, $filepath, FileSystemInterface::EXISTS_REPLACE);
      $this->io()->success("Successfully saved file '{$filepath}'.");
    }
    catch (\Exception $e) {
      $this->io()->error("There was an error saving file '{$filepath}'.");
    }
  }

}
