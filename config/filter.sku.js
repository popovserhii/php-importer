module.exports = {
  "default": {
    "pool": {
      "shop-it": {
        "helper": {
          "filter-sku": {
            "rules": [{
              "condition": "AND",
              "rules": [
                {
                  "field": "supplier",
                  "type": "string",
                  "operator": "not_equal",
                  "value": 1
                },
              ],
              "valid": true,
              "apply": {
                "normalize": {
                  "format": "{{sku}}-{{fields.supplier}}"
                }
              }
            }],
          },
        }
      }
    }
  }
};