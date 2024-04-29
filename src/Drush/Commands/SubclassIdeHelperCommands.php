<?php

declare(strict_types=1);

namespace Drupal\subclass_ide_helper\Drush\Commands;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\field\FieldConfigInterface;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

/**
 * Subclass IDE Helper command file.
 */
class SubclassIdeHelperCommands extends DrushCommands {

  use AutowireTrait;

  const DEFAULT_FILENAME = '_ide_helper_subclassed_bundles.php';

  public function __construct(
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected FileSystemInterface $fileSystem,
    protected TypedDataManagerInterface $typedDataManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    #[Autowire('twig')]
    protected Environment $twig,
  ) {
  }

  /**
   * Generate class properties file.
   */
  #[Command(name: 'subclass_ide_helper:generate', aliases: ['sih'])]
  public function generateBundleProperties(
    $entity_types = 'node',
    $options = [
      'result-file' => NULL,
      'excluded-classes' => '',
    ]
  ): void {
    $properties = $this->generateFieldProperties(
      $this->splitString($entity_types),
      $this->splitString($options['excluded-classes']),
    );

    // Render directly with twig to prevent debug html comments.
    $output = $this->twig->load('subclass-ide-helper.html.twig')->render(['namespaces' => $properties]);

    $this->writeFile((string) $output, $options['result-file']);
  }

  /**
   * Generate the field properties for each subclassed bundle.
   */
  protected function generateFieldProperties(array $entity_types, array $excluded_classes): array {
    $namespaces = [];
    foreach ($entity_types as $entity_type) {
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
      foreach ($bundles as $bundle_name => $bundle) {
        if (empty($bundle['class'])) {
          continue;
        }

        $class_array = explode('\\', $bundle['class']);
        $class_name = array_pop($class_array);
        if (in_array($class_name, $excluded_classes)) {
          continue;
        }

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
   * Splits a string.
   */
  protected function splitString(string $string): array {
    return array_map('trim', explode(',', $string));
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
