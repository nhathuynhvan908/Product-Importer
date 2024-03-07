## Description
If you want to import simple woocommerce products with images uploaded to wordpress media, this is the plugin you need.

It's best to take a look at the demo.csv file included with the plugin to better understand the required fields.

Currently you can import Type, SKU, title, Images, Regular price, Tags, Categories, Description, Short Description, meta:rank_math_focus_keyword. If you don't want any field, you can leave it blank, but it's best not to leave the SKU blank because the delete, update, and private features for CSV check the condition that there is a SKU.

The plugin's goal is to be fast, lightweight, and easy to use.

## Features
- **Import New Products:** Easily add new products to your WooCommerce store by uploading a CSV file.
- **Update Existing Products:** Update existing product details by providing a CSV file with updated information. The plugin checks for unique SKUs to ensure accurate updates.
- **Delete Products:** Remove products from your store by specifying their SKUs in the CSV file.
- **Change Status to Private:** Change the status of products to "Private" by including their SKUs in the CSV file.

## Usage
1. Install and activate the plugin.
2. Use the shortcode [product_import] to access the product import feature.
3. Make sure to be logged in with an admin account to use the import functionality.

## Shortcode
Use the following shortcode to access the product import feature:
```
[product_import]
``` 

## Note
> For successful imports, ensure the CSV file format matches the provided demo.csv file. Images should be accessible via direct URLs to the WordPress media library. Tags and categories should be provided as IDs for efficient categorization.

> When using the "Update" feature, ensure each product has a unique SKU. The plugin checks for the SKU to update existing product data. Other fields will be updated if they contain new data, and empty fields will be skipped.

> The "Delete" and "Private" functionalities also require a SKU for each product.

> A sample CSV file named demo.csv is provided within the plugin. You can use this file as a template for importing your products. Make sure to populate the necessary fields such as product name, SKU, description, price, image URLs, tags, categories, and Rank Math focus keywords.

## Changelog
- Version 1.0:
	- Initial release with basic product import functionality.
- Version 1.1:
	- Added new product features.
- Version 1.2:
	- Added update product features.
- Version 1.3:
	- Added delete product features.
- Version 1.4:
	- Added private product features.
- Version 1.5:
	- Added log features.

## Feedback
This plugin's purpose is only for work so it is FREE. 

But if you need to fix bug or give suggestions, please sebd msg :left_speech_bubble: to me via FB [https://www.facebook.com/nhat.huynhvan.3](https://www.facebook.com/nhat.huynhvan.3)

Thank you for using :heart_on_fire:!
