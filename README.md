# Bundle Subclass IDE Helper

Since Drupal 9.3, entity bundle subclasses can be created [(see change record)](https://www.drupal.org/node/3191609).

This module provides a Drush command that attempts to work similarly as `barryvdh/laravel-ide-helper` (it creates a file with all the fields of each bundle subclass).

## Installation

Normal installation with composer:

**TEMPORARY NOTE:** The module is not yet uploaded to drupal.org.

```
composer require --dev drupal/subclass_ide_helper
drush en -y subclass_ide_helper
```

## How to use

The ide helper file can be generated using the following command:

```
drush subclass_ide_helper:generate [entity,types] [--result-file=/foo/bar]
```

Or using the alias:

```
drush sih [entity,types] [--result-file=/foo/bar]
```

### Specific entity types

By default, the file generates docblocks for node bundles subclasses. Additional or different entity types can be specified, separated with a comma. Example:

```
drush subclass_ide_helper:generate node,media
```

### File path

By default, the file is created at the project root. The path and filename can be customized using the `result-file` option. Example:

```
drush subclass_ide_helper:generate --result-file=/foo/bar
```
