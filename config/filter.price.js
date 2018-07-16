module.exports = {
  "default": {
    "pool": {
      "shop-it": {
        "helper": {
          "filter-price": {
            "fixed": 0,
            "rules": [
              { // 6373327
                "condition": "OR",
                "rules": [
                  {
                    "field": "sku",
                    "type": "string",
                    "operator": "equal",
                    "value": "6373327"
                  },
                  /*{
                    "field": "subcategory",
                    "type": "string",
                    "operator": "contains",
                    "value": "Ноутбуки"
                  },*/
                ],
                "valid": true,
                "apply": {
                  "operand": "+0",
                  "to": "$fields.price" // optional
                }
              },
              { // монітори, ноутбуки
                "condition": "OR",
                "rules": [
                  {
                    "field": "subcategory",
                    "type": "string",
                    "operator": "contains",
                    "value": "Мониторы"
                  },
                  {
                    "field": "subcategory",
                    "type": "string",
                    "operator": "contains",
                    "value": "Ноутбуки"
                  },
                ],
                "valid": true,
                "apply": {
                  "operand": "+300",
                  "to": "$fields.price_purchase" // optional
                }
              },
              { // холодильники, дрібна побутова техніка
                "condition": "AND",
                "rules": [
                  {
                    "field": "price_purchase",
                    "type": "double",
                    "operator": "greater_or_equal",
                    "value": "700.00"
                  },
                  {
                    "condition": "OR",
                    "rules": [

                      {
                        "field": "category",
                        "type": "string",
                        "operator": "equal",
                        "value": "БЫТОВАЯ ТЕХНИКА МЕЛКАЯ"
                      },
                      {
                        "field": "subcategory",
                        "type": "string",
                        "operator": "contains",
                        "value": "Холодильники"
                      },
                    ],
                  }
                ],
                "valid": true,
                "apply": {
                  "operand": "+4%",
                  "to": "$fields.price_purchase" // optional
                }
              },
              { // мед.техніка
                "condition": "AND",
                "rules": [
                  {
                    "field": "category",
                    "type": "string",
                    "operator": "equal",
                    "value": "МЕДТЕХНИКА"
                  }
                ],
                "valid": true,
                "apply": {
                  "operand": "+8%",
                  "to": "$fields.price_purchase" // optional
                }
              },
              { // RONDELL && TRUST
                "condition": "AND",
                "rules": [
                  {
                    "field": "manufacturer",
                    "type": "string",
                    "operator": "in",
                    "value": ["RONDELL", "TRUST"]
                  },
                ],
                "valid": true,
                "apply": {
                  "operand": "-7",
                  "to": "$fields.price" // optional
                }
              },
              { // 800-5000; 500-800
                "condition": "AND",
                "rules": [
                  {
                    "field": "price_purchase",
                    "type": "double",
                    "operator": "greater_or_equal",
                    "value": "500.00"
                  },
                  {
                    "field": "price_purchase",
                    "type": "double",
                    "operator": "less_or_equal",
                    "value": "5000.00"
                  },
                ],
                "valid": true,
                "apply": {
                  "operand": "+5%",
                  "to": "$fields.price_purchase" // optional
                }
              },
              { // 5k-10K
                "condition": "AND",
                "rules": [
                  {
                    "field": "price_purchase",
                    "type": "double",
                    "operator": "greater",
                    "value": "5000.00"
                  },
                  {
                    "field": "price_purchase",
                    "type": "double",
                    "operator": "less_or_equal",
                    "value": "10000.00"
                  },
                ],
                "valid": true,
                "apply": {
                  "operand": "+400",
                  "to": "$fields.price_purchase" // optional
                }
              },
              { // >10K
                "condition": "AND",
                "rules": [
                  {
                    "field": "price_purchase",
                    "type": "double",
                    "operator": "greater",
                    "value": "10000.00"
                  },
                ],
                "valid": true,
                "apply": {
                  "operand": "+500",
                  "to": "$fields.price_purchase" // optional
                }
              },
              { // РРЦ не вказано
                "condition": "OR",
                "rules": [
                  {
                    "field": "price",
                    "type": "double",
                    "operator": "less_or_equal",
                    "value": "0"
                  },
                  {
                    "field": "price",
                    "type": "double",
                    "operator": "is_empty",
                    //"value": "0"
                  },
                ],
                "valid": true,
                "apply": {
                  "operand": "+5%",
                  "to": "$fields.price_purchase" // optional
                }
              },
              { // other products
                "condition": "AND",
                "rules": [
                  {
                    "field": "price",
                    "type": "double",
                    "operator": "greater",
                    "value": "150.00"
                  },
                ],
                " valid": true,
                "apply": {
                  "operand": "-7",
                  "to": "$fields.price" // optional
                }
              },
            ]
          },
        }
      }
    }
  }
};