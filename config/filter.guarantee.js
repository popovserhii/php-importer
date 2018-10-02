module.exports = {
  "default": {
    "pool": {
      "shop-it": {
        "helper": {
          "filter-guarantee": {
            "convertTo": "month",
            "map": {
              "month": [
                "(\\d+) м",
                "(\\d+)",
              ],
              "year": [
                "((\\d+)[.,]?((\\d?))) г",
                "((\\d+)[.,]?((\\d?))) л",
              ],
              "day": [
                "(\\d+) д",
              ],
            }
          },
        }
      }
    }
  }
};