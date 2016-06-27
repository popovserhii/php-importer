# PHP Importer
Universal importer for different table formats like excel or csv

## Supported drivers
1. LibXl ([commercial](http://www.libxl.com/))
2. Csv (not implemented yet)

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
use Agere\Importer\Factory\DriverFactory;
use Agere\Importer\Importer;
use Agere\Db\Db;

$config = [
    'tasks' => [
        'discount-card' => [
            'driver' => 'libxl',
            'fields_map' => [
                [
                    // mapping fields in file to db fields with apply filters
                    'Nominal' => ['name' => 'discount', '__filter' => ['percentToInt']],
                    'Serial' => 'serial',
                    
                    // table where save imported data
                    '__table' => 'discount_card',
                    // shortcut name
                    '__codename' => 'discount',
                    // unique field name for avoid duplicate
                    '__identifier' => 'code'
                ],
            ],
        ],
    ],
];

$pdo = new PDO('mysql:host=myhost;dbname=mydb', 'login', 'password'); 
$db = (new Db())->setPdo($pdo);

$factory = new DriverFactory($config);
$importer = new Importer($factory, $db);

if ($importer->import('discount-card', '/path/to/file.xls')) {
  echo 'Success import!';
} else {
  var_dump($importer->getErrors());
}
```

### With ZF2

There's a [module](https://github.com/agerecompany/zfc-importer-module) for that!
