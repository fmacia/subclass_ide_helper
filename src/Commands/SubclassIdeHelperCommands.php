<?php

declare(strict_types = 1);

namespace Drupal\subclass_ide_helper\Commands;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\field\FieldConfigInterface;
use Drush\Commands\DrushCommands;
use Twig\Environment;

/**
 * A Drush commandfile.
 */
class SubclassIdeHelperCommands extends DrushCommands {

  const DEFAULT_FILENAME = '_ide_helper_subclassed_bundles.php';

  /**
   * Class constructor.
   */
  public function __construct(
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected FileSystemInterface $fileSystem,
    protected TypedDataManagerInterface $typedDataManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected Environment $twig
  ) {
  }

  /**
   * Generate class properties file.
   *
   * @command subclass_ide_helper:generate
   * @aliases sih
   */
  public function generateBundleProperties(
    $entity_types = 'node',
    $options = ['result-file' => NULL]
  ): void {
    $properties = $this->generateFieldProperties($entity_types);

    // Render directly with twig to prevent debug html comments.
    $output = $this->twig->loadTemplate('subclass-ide-helper.html.twig')->render(['namespaces' => $properties]);

    $this->writeFile((string) $output, $options['result-file']);
  }

  /**
   * Generate the field properties for each subclassed bundle.
   */
  protected function generateFieldProperties(string $entity_types): array {
    $namespaces = [];
    foreach ($this->splitEntityTypes($entity_types) as $entity_type) {
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
      foreach ($bundles as $bundle_name => $bundle) {
        if (empty($bundle['class'])) {
          continue;
        }

        $class_array = explode('\\', $bundle['class']);
        $class_name = array_pop($class_array);
        $namespace = implode('\\', $class_array);

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
          $namespaces[$namespace][$class_name][] = "\\{$field_class}{$null} {$field_name} {$field_definition->label()}";
        }
      }
    }
    return $namespaces;
  }

  /**
   * Splits the entity types string.
   */
  protected function splitEntityTypes(string $entity_types_string): array {
    return array_map(
      'trim',
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
