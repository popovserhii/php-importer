# New Driver creation

We will create new Driver for get ranks of products on Amazon.

## Create Driver
Create our driver which implements `Popov\Importer\Driver\DriverInterface`
```php
namespace Stagem\Amazon\Parser;

use Popov\Importer\Driver\DriverInterface;

class RankParser implements DriverInterface {}
```
Next step we will implement methods from interface.

The Driver will be based on `MarketplaceWebServiceProducts API`.
Here is nothing interesting to driver, only simple API configuration.
> Notice. In example API data is hardcoded in constructor for simplification. 
In real code you have to pass configuration as argument to driver constructor.  

Now, create the main method which will get data from API, structure them and return to `Importer` for further processing.
This method doesn't implement any interface requirement. It's only for inner usage.

Take a notice about first element of rows (index 0), we will put columns there.  
```php
protected function parse()
{
    return $this->rows = [
		[],
		[
			'asin' => 'B06VTJVVNN',
			'marketplace' => 'A1PA6795UKMFR9',
			'listingPrice' => '14.99',
			'landedPrice' => '14.99',
			'category0' => '364919031',
			'category1' => '569866',
			'category2' => '',
			'rank0' => '64',
			'rank1' => '6464',
			'rank2' => '',
			'updatedAt' => '2018-06-26 15:42:02',
		],
		[
			'asin' => 'B06X6F6W6G',
			'marketplace' => 'A1PA6795UKMFR9',
			'listingPrice' => '19.95',
			'landedPrice' => '19.95',
			'category0' => '316905011',
			'category1' => '364919031',
			'category2' => '569866',
			'rank0' => '67',
			'rank1' => '104',
			'rank2' => '9253',
			'updatedAt' => '2018-06-26 15:42:02',
		],
		[
			'asin' => 'B06W9H4SG7',
			'marketplace' => 'A1PA6795UKMFR9',
			'listingPrice' => '19.95',
			'landedPrice' => '19.95',
			'category0' => '364919031',
			'category1' => '569866',
			'category2' => '',
			'rank0' => '217',
			'rank1' => '19235',
			'rank2' => '',
			'updatedAt' => '2018-06-26 15:42:02',
		],
	];
}
```

Further we will implement interface methods.

The `source` method must set value if it is passed and return itself. Otherwise, if method is called without any argument
return value which was set previously.
```php
/**
 * {@inheritDoc}
 */
public function source($source = null)
{
    if ($source) {
        $this->source = $source;

        return $this;
    }

    return $this->source;
}
```

The `firstColumn` method must return index of first column of retrieved data.
In most cases it will be 0.
```php
/**
 * {@inheritDoc}
 */
public function firstColumn()
{
    static $first;

    if (!$first) {
        $columns = $this->columns();
        reset($columns);
        $first = key($columns);
    }
    return $first;
}
```


The `lastColumn` method must return index of last column of retrieved data.
```php
/**
 * {@inheritDoc}
 */
public function lastColumn()
{
    static $last;

    if (!$last) {
        $columns = $this->columns();
        end($columns);
        $last = key($columns) + 1;
    }

    return $last;
}
```


The `firstRow` method must return index of first row of retrieved data.
In most cases it will be 0.
```php
/**
 * {@inheritDoc}
 */
public function firstRow()
{
    static $first;

    if (!$first) {
        $rows = $this->rows();
        $first = key($rows);
    }

    return $first;
}
```

The `lastRow` method must return index of last row of retrieved data.
```php
/**
 * {@inheritDoc}
 */
public function lastRow()
{
    static $last;

    if (!$last) {
        $sheet = $this->rows();
        end($sheet);
        $last = key($sheet) + 1;
    }

    return $last;
}
```

The `read` method get *row* and *column* index and return value.
If *column* index doesn't pass than return entire row.
```php
/**
 * {@inheritDoc}
 */
public function read($row, $column = null)
{
    $rows = $this->rows();
    if (is_null($column)) {
        return $rows[$row];
    }

    // skip header and convert column index to column name
    if ($row !== $this->firstRow()) {
        $column = $this->columns()[$column];
    }

    return $rows[$row][$column];
}
```

The `config` method must set value if it is passed and return itself. Otherwise, if method is called without any argument
return value which was set previously.
> Notice. Value is returned by reference. You can change any config value if need some specific logic. 
```php
/**
 * {@inheritDoc}
 */
public function &config(array $config = null)
{
    if ($config) {
        $this->config = $config;

        return $this;
    }

    return $this->config;
}
```

The `columns` relate to inner logic and prepare column names with in indexes.
We add empty array at firs position in our `parse` method, here we fill that with values. 
```php
protected function columns()
{
    if (!isset($this->rows[0])) {
        $rows = $this->rows();
        $row = next($rows);
        foreach ($row as $name => $value) {
            $this->rows[0][] = $name;
        }
    }

    return $this->rows[0];
}
```

The `rows` relate to inner logic and fetch data once. On next call return previously fetched data.
```php
protected function rows()
{
    if (!$this->rows) {
        $this->parse();
    }

    return $this->rows;
}
```


## Setup Configuration

We have to explain `Importer` what to do with retrieved data and where to save them.
```php
$config = [
	// Register our driver
    'drivers' => [
        'RankParser' => Stagem\Amazon\Parser\RankParser::class,
    ],
    'tasks' => [
		// Task name. This will be converted to 'amazon-rank' and this name you must put to Importer
        'Amazon\\Rank' => [
            'driver' => 'RankParser',
            'fields_map' => [
                [
					// Field mapping. We didn't add any filtration for simplification
                    'asin' => 'asin',
                    'marketplace' => 'marketplace',
                    'listingPrice' => 'listingPrice',
                    'landedPrice' => 'landedPrice',
                    'category0' => 'category0',
                    'category1' => 'category1',
                    'category2' => 'category2',
                    'rank0' => 'rank0',
                    'rank1' => 'rank1',
                    'rank2' => 'rank2',
                    'updatedAt' => 'updatedAt',

					// Table name. Here data will be saved.
                    '__table' => 'amazon_product_rank',
					// Unique name of this scope.
                    '__codename' => 'rank',
					// Fields which do row unique. 
					// We have two fields but if you have only one field you can opt out an array and assign a string only.
                    '__identifier' => ['asin', 'marketplace'],
					// We want only update data and skip new.
                    '__options' => [
                        'mode' => 'update'
                    ]
                ],
            ],
        ],
    ],
];

$pdo = new PDO('mysql:host=myhost;dbname=mydb', 'login', 'password'); 
$db = (new Db())->setPdo($pdo);

$factory = new DriverCreator($config);
$importer = new Importer($factory, $db);

if ($importer->import('amazon-rank', 'MARKETPLACE_ID')) { // We skip API call but in real code, you should pass marketplace ID for retrieving data
  echo 'Success import!';
} else {
  var_dump($importer->getErrors());
}
```
