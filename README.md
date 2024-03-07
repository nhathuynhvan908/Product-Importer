Description
The Product Import plugin provides a convenient way to import products using a CSV file. It allows you to efficiently upload product data, including images from the WordPress media library. You can also specify tags and categories using their IDs for efficient categorization.

Usage
Install and activate the plugin.
Use the shortcode [product_import] to access the product import feature.
Make sure to be logged in with an admin account to use the import functionality.
When using the "Update" feature, ensure each product has a unique SKU. The plugin checks for the SKU to update existing product data. Other fields will be updated if they contain new data, and empty fields will be skipped.
The "Delete" and "Private" functionalities also require a SKU for each product.
Sample CSV File
A sample CSV file named demo.csv is provided within the plugin. You can use this file as a template for importing your products. Make sure to populate the necessary fields such as product name, SKU, description, price, image URLs, tags, categories, and Rank Math focus keywords.

Shortcode
Use the following shortcode to access the product import feature:
[product_import]

Note
For successful imports, ensure the CSV file format matches the provided demo.csv file.
Images should be accessible via direct URLs to the WordPress media library.
Tags and categories should be provided as IDs for efficient categorization.

Changelog
Version 1.0:
Initial release with basic product import functionality.
Version 1.1:
Added new product features.
Version 1.2:
Added update product features.
Version 1.3:
Added delete  product features.
Version 1.4:
Added private product features.
Version 1.5:
Added log features.
