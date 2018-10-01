# Convert column value to table

You can skip `name` key in field mapping (`tag` field in example). This will be signal that field value must be handled with `preparation`.
Value returned from preparation will use as new `row`.

```php
[
    'helpers' => [
        'CustomTagsPrepare' => 'Your\Module\Importer\CustomTagPrepare',
    ],
    'tasks' => [
        'Stagem\\ProductWatcher\\Parse' => [
            'driver' => 'ProductParser',
            'fields' => [
                [
                    'asin' => 'asin',
                    'name' => 'name',

                    '__table' => Model\Detail::TABLE,
                    '__codename' => Model\Detail::MNEMO,
                    '__identifier' => 'asin',
                    '__exclude' => false,
                    '__options' => [
                        'mode' => 'save'
                    ]
                ],
                [
                    'tag' => ['__prepare' => ['customTags']], // this field will be expand to row

                    '__table' => 'amazon_product_detail_watcher',
                    '__codename' => 'productWatcher', // code name of position
                ],
            ],
        ],
    ],
];
``` 

Assume your in `tag` we have value such as 'bike,trek,carbon,exclusive'. In `customTags` preparation you can explode value
by comma, remove old values from database and insert new.

Schematic we can describe this as  
```php
input: 'bike,trek,carbon'
output: [
    [
        'name' => 'bike',
        'productId' => 123,
    ],
    [
        'name' => 'trek',
        'productId' => 123,
    ],
    [
        'name' => 'carbon',
        'productId' => 123,
    ],
]
```