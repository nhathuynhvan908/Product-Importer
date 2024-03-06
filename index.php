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
    </div>

    <?php
    $output = ob_get_clean();
    return $output;
}
add_shortcode('product_import', 'product_import_page');

function handle_csv_import_ajax() {
    // Kiểm tra nonce để đảm bảo tính xác thực
    if (!isset($_POST['product_import_nonce']) || !wp_verify_nonce($_POST['product_import_nonce'], 'product_import_nonce')) {
        wp_send_json_error('Security check failed!');
    }

    // Kiểm tra quyền truy cập
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied!');
    }

    // Kiểm tra xem có quá trình xử lý đang diễn ra không
    if (get_transient('product_import_processing')) {
        wp_send_json_error('Import is currently in progress. Please wait until it completes.');
    }

    // Kiểm tra file đã được chọn hay chưa
    if (isset($_FILES['csv_file']['error']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $csv_file_path = wp_normalize_path($_FILES['csv_file']['tmp_name']);

        // Kiểm tra loại file
        $file_info = wp_check_filetype(basename($_FILES['csv_file']['name']));
        if ($file_info['ext'] !== 'csv') {
            wp_send_json_error('Invalid file type. Please upload a CSV file.');
        }

        $import_type = sanitize_text_field($_POST['import_type']);

        // Xử lý logic import
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
            $product_price = number_format($data[4], 2);
            $product_sku = sanitize_text_field($data[1]);
            $product_type = 'simple';
            $product_tags = array_map('sanitize_text_field', explode(',', $data[5]));
            $product_categories = array_map('sanitize_text_field', explode(',', $data[6]));
            $image_url = $data[3];
            $post_name = sanitize_title($product_name);
            $time = strtotime('tomorrow');
            $rank_math_focus_keyword = $data[9];

            $flag = 0;

            if ($import_type === 'update') {
                // Trường hợp cập nhật sản phẩm
                $existing_product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s", $product_sku));
                if($existing_product_id) {
                    // Cập nhật thông tin sản phẩm
                    $update_data = array(
                        'post_title' => $product_name,
                        'post_content' => $product_description,
                        'post_excerpt' => $product_short_description,
                    );

                    $update_data = array_filter($update_data); // Loại bỏ các trường có giá trị null
                    
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

                    // Cập nhật giá sản phẩm nếu có giá trị
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

                    // Cập nhật hình ảnh từ URL nếu có
                    if ($image_url !== '') {
                        $image_id = attachment_url_to_postid($image_url);
                        if ($image_id) {
                            $thub_check = update_post_meta($existing_product_id, '_thumbnail_id', $image_id);
                            if($thub_check) {
                                $flag++;
                            } else {
                                $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update thumbnail';
                            }
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not get image id';
                        }
                    }

                    // Cập nhật tags nếu có
                    if (!empty($product_tags)) {
                        $tag_check = wp_set_post_terms($existing_product_id, $product_tags, 'product_tag');
                        if($tag_check) {
                            $flag++;
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update tags';
                        }
                    }

                    // Cập nhật categories nếu có
                    if (!empty($product_categories)) {
                        $cat_check = wp_set_post_terms($existing_product_id, $product_categories, 'product_cat');
                        if($cat_check) {
                            $flag++;
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update categories';
                        }
                    }

                    // Cập nhật keyword Rank Math nếu có
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
                // Trường hợp xóa sản phẩm
                if(!empty($product_sku)) {
                    // Tìm ID của sản phẩm dựa trên SKU
                    $existing_product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s", $product_sku));
                    // Nếu tìm thấy sản phẩm, xóa nó
                    if ($existing_product_id) {
                        $del_check = wp_delete_post($existing_product_id, true); // True để xóa luôn cả meta data và term relationship
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
                // Trường hợp cập nhập status sản phẩm thành Private
                if(!empty($product_sku)) {
                    // Tìm ID của sản phẩm dựa trên SKU
                    $existing_product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s", $product_sku));
                    // Nếu tìm thấy sản phẩm, cập nhật thành Private
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

                // Bắt đầu giao dịch
                $wpdb->query('START TRANSACTION');

                // Bắt đầu giao dịch
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

                        if($check_update_status) {
                            $flag++;
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update';
                        }
                    } else {
                        $import_results['errors'][$count_loop][] = 'The product already exists SKU '. $product_sku;
                    }
    
                    // Chèn giá sản phẩm
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
    
                    // Chèn hình ảnh từ URL
                    $image_id = attachment_url_to_postid($image_url);
                    if ($image_id) {
                        $check_thumbnail_id = update_post_meta($product_id, '_thumbnail_id', $image_id);
                        if($check_thumbnail_id) {
                            $flag++;
                        } else {
                            $import_results['errors'][$count_loop][] = 'Product with SKU ' . $product_sku . ' not update thumbnail';
                        }
                    } else {
                        $import_results['errors'][$count_loop][] = 'Product not found image_id with SKU'. $product_sku;
                    }
    
                    // Chèn tags
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
                    
                    // Chèn categories
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

                    // Lỗi khi chèn bài viết
                    $wpdb->query('ROLLBACK');
                }
            }
        }

        $count_loop++;
        fclose($file_handle);
        return $import_results; // Trả về thông tin kết quả import
    } catch (Exception $e) {
        $import_results['imports'] = false;
        $import_results['errors'] = "Error: " . $e->getMessage();
        return $import_results; // Trả về thông tin kết quả import với lỗi
    }
}

add_action( 'init', 'product_import_admin_scripts' );
function product_import_admin_scripts() {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
    wp_enqueue_script('product-import-ajax', plugin_dir_url(__FILE__) . 'product-import-ajax.js', array('jquery'), '1.0', true);
    wp_localize_script('product-import-ajax', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}
