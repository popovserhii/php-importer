# PHP Importer
Universal importer for different table formats like excel or csv

## Installation

Install it with ``composer``
```sh
composer require popov/php-importer -o
```

## Supported drivers
1. LibXl ([commercial](http://www.libxl.com/))
1. Soap
1. Csv (not implemented yet)

## Usage
### Example import File
|Nominal  | Serial  |
|---------|---------|
|3%       | 3002345 |
|3%       | 3002346 |
|3%       | 3002346 |
|5%       | 5002344 |
|5%       | 5002345 |

### Standalone
```php
use Popov\Importer\Factory\DriverCreator;
use Popov\Importer\Importer;
use Popov\Db\Db;

$config = [
    'tasks' => [
        'discount-card' => [
            'driver' => 'libxl',
            'fields' => [
                [
                    // mapping fields in file to db fields with apply filters
                    'Nominal' => ['name' => 'discount', '__filter' => ['percentToInt']],
                    'Serial' => 'serial',
                    
                    // table where save imported data
                    '__table' => 'discount_card',
                    // shortcut name
                    '__codename' => 'discount',
                    // unique field name for avoid duplicate
                    '__identifier' => 'serial'
                ],
            ],
        ],
    ],
];

$pdo = new PDO('mysql:host=myhost;dbname=mydb', 'login', 'password'); 
$db = (new Db())->setPdo($pdo);

$factory = new DriverCreator($config);
$importer = new Importer($factory, $db);

if ($importer->import('discount-card', '/path/to/file.xls')) {
  echo 'Success import!';
} else {
  var_dump($importer->getErrors());
}
```

### Advanced Usage
Most popular PHP frameworks implement IoC pattern and they also implement standard interface `Interop\Container\ContainerInterface`.
This library support this functionality. You can pass your own IoC to *Factory* and be happy with creating objects. 
```
$pdo = new PDO('mysql:host=myhost;dbname=mydb', 'login', 'password'); 
$db = (new Db())->setPdo($pdo);

$container = /* getYourContainer */;
$factory = new DriverCreator($config, $container);
$importer = new Importer($factory, $db);
```

## Fields
Mapping fields from one resource to new (MySQL, CSV, Excel)

The simples mapping can be written as:
```php
// from   =>   to
['Serial' => 'serial']
```
 
 
Fields filtration and preparation can be grouped in chain
```php
[
    'Nominal' => ['name' => 'discount', '__filter' => ['trim', 'percentToInt']]
]
```
*__filter* - reserved name for filtration

*__prepare* - reserved name for preparation
 

## Configuration
All reserved options begin with "__" (double underscore).

**__table**
```php
'__table' => 'discount_card',
```
*Required*. A table where to save imported data.


**__codename**
```php
'__codename' => 'discount',
```
*Required*. Shortcut unique name for config related to table.

**__identifier**
```php
'__identifier' => 'serial',
// or
'__identifier' => ['asin', 'marketplace'],
```
Unique field name for avoid duplicated items. Identifier can be as one field such as multiple fields.
                    
**__ignore**
```php
'__ignore' => ['comment'],
```
Fields which should be ignored in save operation. These fields can be used in data filtration.  
                    
**__exclude**       
```php
'__exclude' => false,
```             
*Bool*. Exclude table from save operation. All fields can be used in data filtration. 

### Options
**mode**
```php
'__options' => [
    'mode' => 'save'
]
```
*save* - save new and excited data

*update* - only update excited data
 

## Integration with ZF2

There's a [module](https://github.com/popovserhii/zfc-importer) for that!
