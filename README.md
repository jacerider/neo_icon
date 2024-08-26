CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Usage within PHP
 * Usage within SCSS
 * Icon Definitions


INTRODUCTION
------------

Allow Icomoon icon packages to be utilized within Drupal.


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

Install as you would normally install a contributed Drupal module. Visit
https://www.drupal.org/node/1897420 for further information.


USAGE WITHIN SCSS
-----

A PHP trait has been provided that can be a dropin replacement for the Drupal
core StringTranslationTrait.

```php
use Drupal\neo_icon\IconTranslationTrait;

// Dynamic icon using icon repository.
$this->icon('Angel');
// Specific icon by name using weighted library match.
$this->icon('Angel', 'drupal');
// Specific icon by name from specific library (if published). If library does
// not exist or is not published, a weighted library match will be used.
$this->icon('Angel', 'drupal', 'solid');
// Supplying a prefix will filter the dynamic icon repository lookup to only
// those definitions that support that prefix. It allows, for example, an icon
// to be scoped to only be available in the admin.
$this->icon('Angel', NULL, NULL, ['admin']);
// If you want to get an get a dynamic icon regardless of the prefix, you can
// pass 'any' as the prefix.
$this->icon('Angel', NULL, NULL, ['all']);
```


USAGE WITHIN SCSS
-----

Global icon sets can be utilized directly within .scss files via a mixin.

```scss
@use 'neo-icon';

@include neo-icon.icon('drupal', before);
@include neo-icon.icon('drupal', after);
```

This mixin intentionally uses only the icon name and ignore the library. This
allows swapping out different icons libraries without having to rename the
icons. It can result in a conflict is two libraries and marked as global when
both contain an icon with the same name.


ICON DEFINITIONS
-----

Dynamic icons can be defined via a `MODULE_NAME.neo.icon.yml` file.

```yaml
# A definition that will match any string starting with "drupal".
drupal.start:
  start: icon
  icon: drupal
# A definition that will match any string ending with "drupal".
drupal.end:
  end: icon
  icon: drupal
# A definition that will match any string containing the word "drupal".
drupal.word:
  word: icon
  icon: drupal
# A definition that will match the exact string "drupal".
drupal.exact:
  exact: icon
  icon: drupal
# A weight can be added to the definition to control their lookup order.
drupal.weight:
  exact: icon
  icon: drupal
  weight: 10
# A definition with a prefix. Prefix can also be an array of strings.
drupal.prefix:
  start: icon
  icon: drupal
  prefix: admin
```
