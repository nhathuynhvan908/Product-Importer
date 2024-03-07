<?php
/**
 * Plugin Name: Product Importer
 * Description: Simple CSV Product Importer
 * Version: 1.5
 * Author: Huỳnh Văn Nhật
 * Author URI: http://nhathuynhvan.com/
*/

function product_import_page() {
    ob_start(); ?>

    <div class="product-import-container">
        <h2>Product CSV Import</h2>
        <form method="post" id="product_import_form" enctype="multipart/form-data">
            <label for="csv_file">Choose CSV file:</label>
            <input type="file" name="csv_file" id="csv_file" accept=".csv">
            <br>
            <label for="import_type">Import Type:</label>
            <select name="import_type" id="import_type">
                <option value="new">Import New</option>
                <option value="update">Update Existing</option>
                <option value="private">Private Existing</option>
                <option value="delete">Delete Existing</option>
            </select>
            <?php wp_nonce_field('product_import_nonce', 'product_import_nonce'); ?>
            <input type="submit" name="import_products" id="import_products" value="Import Products">
        </form>
        <div id="upload_status"></div>
        <div id="import_result"></div>
        <div id="import_errors"></div>
    </div>

    <?php
    $output = ob_get_clean();
    return $output;
}
add_shortcode('product_import', 'product_import_page');

function handle_csv_import_ajax() {
    // Check the nonce to ensure authenticity
    if (!isset($_POST['product_import_nonce']) || !wp_verify_nonce($_POST['product_import_nonce'], 'product_import_nonce')) {
        wp_send_json_error('Security check failed!');
    }

    // Check access permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied!');
    }

    // Check if there is processing going on
    if (get_transient('product_import_processing')) {
        wp_send_json_error('Import is currently in progress. Please wait until it completes.');
    }

    // Check if the file is selected or not
    if (isset($_FILES['csv_file']['error']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $csv_file_path = wp_normalize_path($_FILES['csv_file']['tmp_name']);

        // Check file type
        $file_info = wp_check_filetype(basename($_FILES['csv_file']['name']));
        if ($file_info['ext'] !== 'csv') {
            wp_send_json_error('Invalid file type. Please upload a CSV file.');
        }

        $import_type = sanitize_text_field($_POST['import_type']);

        //Handles import logic
        $import_results = handle_csv_import($csv_file_path, $import_type);

        wp_send_json_success($import_results);
    } else {
        wp_send_json_error('Error uploading file.');
    }
}
add_action('wp_ajax_handle_csv_import', 'handle_csv_import_ajax');
add_action('wp_ajax_nopriv_handle_csv_import', 'handle_csv_import_ajax');

function handle_csv_import($csv_file_path, $import_type) {
    global $wpdb;

    $import_results = array(
        'imports' => true,
        'success_count' => 0,
        'errors' => array() 
    );

    try {
      
        $file_handle = fopen($csv_file_path, 'r');
        if (!$file_handle) {
            throw new Exception('Error opening CSV file.');
        }

        $count_loop = 0;
        while (($data = fgetcsv($file_handle)) !== FALSE) {
            if ($data[2] == 'title') {
                continue;
            }

            $product_name = sanitize_text_field($data[2]);
            $product_description = iconv(mb_detect_encoding($data[7], mb_detect_order(), true), "UTF-8", $data[7]);
            $product_short_description = iconv(mb_detect_encoding($data[8], mb_detect_order(), true), "UTF-8", $data[8]);
            $product_price = number_format($data[4], 2); // "number_format() expects parameter 1 to be float, string given"
            $product_sku = sanitize_text_field($data[1]);
            $product_type = 'simple';

            if(!empty($data[5])) {
                $product_tags = array_map('sanitize_text_field', explode(',', $data[5]));
            } else {
                $product_tags = [];
            }

            if(!empty($data[6])) {
                $product_categories = array_map('sanitize_text_field', explode(',', $data[6]));
            } else {
                $product_categories = [];
            }
            
            $image_url = $data[3];
            $post_name = sanitize_title($product_name);
            $time = strtotime('tomorrow');
            $rank_math_focus_keyword = $data[9];

            $flag = 0;

            if ($import_type === 'update') {
                // In case of product updates
                $existing_product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s", $product_sku));
                if($existing_product_id) {
                    // Update product information
                    $update_data = array(
                        'post_title' => $product_name,
                        'post_content' => $product_description,
                        'post_excerpt' => $product_short_description,
                    );

                    $update_data = array_filter($update_data); // Remove fields with null values
                    
                    if (!empty($update_data)) {
                        $check_update = $wpdb->update(
                            $wpdb->posts,
                            $update_data,
                            array('ID' => $existing_product_id)
                        );

                        if ($check_update === false) {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update data';
                        } else {
                            $flag++;
                        }
                    }

                    // Update product price if valid
                    if ($product_price !== '') {
                        $existing_price = get_post_meta($existing_product_id, '_price', true);
                        if ($existing_price === '') {
                            add_post_meta($existing_product_id, '_price', $product_price);
                            add_post_meta($existing_product_id, '_regular_price', $product_price);
                            $flag++;
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update price';
                        }
                    }

                    // Update images from URL if available
                    if ($image_url !== '') {
                        $image_id = attachment_url_to_postid($image_url);
                        if ($image_id1) {
                            $get_thumbnail_id = get_post_meta($existing_product_id, '_thumbnail_id', true);
                            if($get_thumbnail_id != $image_id) {
                                $thub_check = update_post_meta($existing_product_id, '_thumbnail_id', $image_id);
                                if($thub_check) {
                                    $flag++;
                                } else {
                                    $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update thumbnail';
                                }
                            } else{
                                $flag++;
                            }
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not get image id';
                        }
                    }

                    // Update tags if any
                    if (!empty($product_tags)) {
                        $tag_check = wp_set_post_terms($existing_product_id, $product_tags, 'product_tag');
                        if($tag_check) {
                            $flag++;
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update tags';
                        }
                    }

                    // Update categories if any
                    if (!empty($product_categories)) {
                        $cat_check = wp_set_post_terms($existing_product_id, $product_categories, 'product_cat');
                        if($cat_check) {
                            $flag++;
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update categories';
                        }
                    }

                    // Update keyword Rank Math if any
                    if($rank_math_focus_keyword) {
                        $rank_math_keyword = update_post_meta($existing_product_id, 'rank_math_focus_keyword', $rank_math_focus_keyword);
                        if($rank_math_keyword) {
                            $flag++;
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update keyword Rank Math';
                        }
                    }

                    if($flag > 0) {
                        $import_results['success_count']++;
                    } else {
                        $import_results['errors'][$count_loop][] = 'Product update failed or empty';
                    }
                } else {
                    $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not found.';
                }
              
            } elseif ($import_type === 'delete') {
                // In case of product deletion
                if(!empty($product_sku)) {
                    // Find product ID based on SKU
                    $existing_product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s", $product_sku));
                    // If the product is found, delete it
                    if ($existing_product_id) {
                        $del_check = wp_delete_post($existing_product_id, true); // True to delete both meta data and term relationships
                        if($del_check) {
                            $import_results['success_count']++;
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' cannot deleted.';
                        }
                    } else {
                        $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not found.';
                    }
                }
            } elseif ($import_type === 'private') {
                // In case of updating product status to Private
                if(!empty($product_sku)) {
                    // Find product ID based on SKU
                    $existing_product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s", $product_sku));
                    // If the product is found, update it to Private
                    if ($existing_product_id) {
                        $check_update_status = $wpdb->update(
                            $wpdb->posts,
                            array('post_status' => 'private'),
                            array('ID' => $existing_product_id)
                        );

                        if($check_update_status) {
                            $import_results['success_count']++;
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update status to private';
                        }
                    } else {
                        $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not found';
                    }
                }
            } else {

                // Start trading
                $wpdb->query('START TRANSACTION');

                $sql = $wpdb->prepare(
                    "INSERT INTO {$wpdb->posts} (post_title, post_content, post_excerpt, post_status, post_type, post_name, post_modified, post_modified_gmt, post_date_gmt, post_date)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
                    $product_name,
                    $product_description,
                    $product_short_description,
                    'publish',
                    'product',
                    $post_name,
                    date("Y-m-d H:i:s"),
                    date("Y-m-d H:i:s"),
                    date("Y-m-d H:i:s"),
                    date("Y-m-d H:i:s"),
                );
    
                $wpdb->query($sql);
    
                $product_id = $wpdb->insert_id;
    
                if ($product_id) {
                    // SKU
                    $existing_sku = get_post_meta($product_id, '_sku', true);
                    if ($existing_sku === '') {
                        $check_sku = $wpdb->query(
                            $wpdb->prepare(
                                "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
                                VALUES (%d, %s, %s)",
                                $product_id,
                                '_sku',
                                $product_sku
                            )
                        );

                        if($check_sku) {
                            $flag++;
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update sku';
                        }
                    } else {
                        $import_results['errors'][$count_loop][] = 'The product already exists SKU '. $product_sku;
                    }
    
                    // Insert product price
                    $existing_price = get_post_meta($product_id, '_price', true);
                    if ($existing_price === '') {
                        $check_price = $wpdb->query(
                            $wpdb->prepare(
                                "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
                                VALUES (%d, %s, %s)",
                                $product_id,
                                '_price',
                                $product_price
                            )
                        );
    
                        $check_regular_price = $wpdb->query(
                            $wpdb->prepare(
                                "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
                                VALUES (%d, %s, %s)",
                                $product_id,
                                '_regular_price',
                                $product_price
                            )
                        );

                        if($check_price && $check_regular_price) {
                            $flag++;
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update price';
                        }
                    } else {
                        $import_results['errors'][$count_loop][] = 'The product already exists price with SKU'. $product_sku;
                    }
    
                    // Insert image from URL
                    $image_id = attachment_url_to_postid($image_url);
                    if ($image_id) {
                        $image_post = $wpdb->get_row(
                            $wpdb->prepare(
                                "SELECT * FROM {$wpdb->posts} WHERE ID = %d AND post_type = %s",
                                $image_id,
                                'attachment'
                            )
                        );

                        if ($image_post) {
                            $check_thumbnail_id = update_post_meta($product_id, '_thumbnail_id', $image_id);
                            if($check_thumbnail_id) {
                                $flag++;
                            } else {
                                $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update thumbnail';
                            }
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . 'Invalid image URL or not an attachment';
                        }
                    } else {
                        $import_results['errors'][$count_loop][] = 'Product not found image_id with SKU'. $product_sku;
                    }
    
                    // Insert tags
                    if(!empty($product_tags)) {
                        $flag_tags = 0;
                        foreach ($product_tags as $tag_id) {
                            if ($tag_id) {
                                $existing_relationship_tag = $wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = 'product_tag'", $tag_id));
                                if ($existing_relationship_tag) {
                                    $wpdb->query(
                                        $wpdb->prepare(
                                            "INSERT INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id)
                                            VALUES (%d, %d)",
                                            $product_id,
                                            $existing_relationship_tag
                                        )
                                    );

                                    $flag_tags++;
                                }
                            }
                        }

                        if($flag_tags > 0) {
                            $flag++;
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update tags';
                        }
                    } 
                    
                    // Insert categories
                    if(!empty($product_categories)) {
                        $flag_cat = 0;
                        foreach ($product_categories as $category_id) {
                            if ($category_id) {
                                $existing_relationship_cat = $wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = 'product_cat'", $category_id));
                                if ($existing_relationship_cat) {
                                    $wpdb->query(
                                        $wpdb->prepare(
                                            "INSERT INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id)
                                            VALUES (%d, %d)",
                                            $product_id,
                                            $existing_relationship_cat
                                        )
                                    );

                                    $flag_cat++;
                                }
                            }
                        }

                        if($flag_tags > 0) {
                            $flag++;
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update categories';
                        }
                    }
    
                    // Update keyword rank math
                    if ($rank_math_focus_keyword) {
                        $wpdb->query(
                            $wpdb->prepare(
                                "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
                                VALUES (%d, %s, %s)",
                                $product_id,
                                'rank_math_focus_keyword',
                                $rank_math_focus_keyword
                            )
                        );
                    }

                    $wpdb->query('COMMIT');

                    if($flag > 0) {
                        $import_results['success_count']++;
                    } else {
                        $import_results['errors'][$count_loop][] = 'Product update failed or empty';
                    }
                } else {
                    $import_results['errors'][$count_loop][] = "Error inserting product: $product_name";

                    // ROLLBACK again when Error inserting product
                    $wpdb->query('ROLLBACK');
                }
            }
        }

        $count_loop++;
        fclose($file_handle);
        return $import_results; // Returns import result information
    } catch (Exception $e) {
        $import_results['imports'] = false;
        $import_results['errors'][][] = "Error: " . $e->getMessage();
        return $import_results; // Returns import result information with errors
    }
}

add_action( 'init', 'product_import_admin_scripts' );
function product_import_admin_scripts() {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
    wp_enqueue_script('product-import-ajax', plugin_dir_url(__FILE__) . 'product-import-ajax.js', array('jquery'), '1.0', true);
    wp_localize_script('product-import-ajax', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}
