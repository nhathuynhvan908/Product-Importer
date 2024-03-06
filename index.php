<?php
/**
 * Plugin Name: Product Importer
 * Description: Simple CSV Product Importer
 * Version: 1.1
 * Author: Huỳnh Văn Nhật
 * Author URI: http://nhathuynhvan.com/
*/

function product_import_page() {
    ob_start(); ?>

    <div class="product-import-container">
        <h2>Product CSV Import</h2>
        <form id="upload_form" method="post" enctype="multipart/form-data">
            <label for="csv_file">Choose CSV file:</label>
            <input type="file" name="csv_file" id="csv_file" accept=".csv">
            <input type="hidden" name="action" value="handle_csv_import">
            <?php wp_nonce_field('product_import_nonce', 'product_import_nonce'); ?>
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

    if (isset($_FILES['csv_file']['error']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $csv_file_path = wp_normalize_path($_FILES['csv_file']['tmp_name']);
        // Xử lý logic import
        handle_csv_import($csv_file_path);
        wp_send_json_success('Import completed successfully.');
    } else {
        wp_send_json_error('Error uploading file.');
    }
}
add_action('wp_ajax_handle_csv_import', 'handle_csv_import_ajax');
add_action('wp_ajax_nopriv_handle_csv_import', 'handle_csv_import_ajax');

function handle_csv_import($csv_file_path) {
    global $wpdb;

    try {
        // Bắt đầu giao dịch
        $wpdb->query('START TRANSACTION');

        $file_handle = fopen($csv_file_path, 'r');
        if (!$file_handle) {
            throw new Exception('Error opening CSV file.');
        }

        while (($data = fgetcsv($file_handle)) !== FALSE) {
            if ($data[2] == 'title') {
                continue;
            }

            $product_name = sanitize_text_field($data[2]);
            $product_description = iconv('ISO-8859-1', 'UTF-8', $data[7]);
            $product__short_description = iconv('ISO-8859-1', 'UTF-8', $data[8]);
            $product_price = number_format($data[4], 2);
            $product_sku = sanitize_text_field($data[1]);
            $product_type = 'simple';
            $product_tags = array_map('sanitize_text_field', explode(',', $data[5]));
            $product_categories = array_map('sanitize_text_field', explode(',', $data[6]));
            $image_url = $data[3];
            $post_name = sanitize_title($product_name);
            $time = strtotime('tomorrow');
            $rank_math_focus_keyword = $data[9];

            $sql = $wpdb->prepare(
                "INSERT INTO {$wpdb->posts} (post_title, post_content, post_excerpt, post_status, post_type, post_name, post_modified, post_modified_gmt, post_date_gmt, post_date)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
                $product_name,
                $product_description,
                $product__short_description,
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
                    $wpdb->query(
                        $wpdb->prepare(
                            "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
                            VALUES (%d, %s, %s)",
                            $product_id,
                            '_sku',
                            $product_sku
                        )
                    );
                }

                // Chèn giá sản phẩm
                $existing_price = get_post_meta($product_id, '_price', true);
                if ($existing_price === '') {
                    $wpdb->query(
                        $wpdb->prepare(
                            "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
                            VALUES (%d, %s, %s)",
                            $product_id,
                            '_price',
                            $product_price
                        )
                    );

                    $wpdb->query(
                        $wpdb->prepare(
                            "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
                            VALUES (%d, %s, %s)",
                            $product_id,
                            '_regular_price',
                            $product_price
                        )
                    );
                }

                // Chèn hình ảnh từ URL
                $image_id = attachment_url_to_postid($image_url);
                if ($image_id) {
                    update_post_meta($product_id, '_thumbnail_id', $image_id);
                }

                // Chèn tags
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
                        }
                    }
                }

                // Chèn categories
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
                        }
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
            }
        }

        fclose($file_handle);

        // Kết thúc giao dịch và lưu thay đổi vào cơ sở dữ liệu
        $wpdb->query('COMMIT');
        return true; // Trả về true khi import thành công
    } catch (Exception $e) {
        // Nếu có lỗi xảy ra, quay lại trạng thái ban đầu và rollback giao dịch
        $wpdb->query('ROLLBACK');
        return false; // Trả về false nếu có lỗi
    }
}

add_action( 'init', 'product_import_admin_scripts' );
function product_import_admin_scripts() {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
    wp_enqueue_script('product-import-ajax', plugin_dir_url(__FILE__) . 'product-import-ajax.js', array('jquery'), '1.0', true);
    wp_localize_script('product-import-ajax', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}
