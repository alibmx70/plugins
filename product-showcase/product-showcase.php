<?php
/**
 * Plugin Name: Product Showcase (With Separate Gallery Meta Box)
 * Description: Adds a custom post type for products with a dedicated image gallery meta box.
 * Version: 1.7
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

register_activation_hook(__FILE__, 'ps_activate');
function ps_activate() {
    flush_rewrite_rules();
}

add_action('init', 'ps_create_post_type');
function ps_create_post_type() {
    register_post_type('product_showcase',
        array(
            'labels' => array(
                'name' => __('معرفی محصولات'), 'singular_name' => __('معرفی محصول'), 'add_new' => __('افزودن محصول جدید'), 'add_new_item' => __('افزودن محصول جدید'), 'edit_item' => __('ویرایش محصول'), 'new_item' => __('محصول جدید'), 'view_item' => __('مشاهده محصول'), 'search_items' => __('جستجو در محصولات'), 'not_found' => __('محصولی یافت نشد'), 'not_found_in_trash' => __('محصولی در زباله‌دان یافت نشد'), 'parent_item_colon' => ''
            ),
            'public' => true, 'has_archive' => true, 'supports' => array('title', 'editor', 'thumbnail', 'excerpt'), 'menu_icon' => 'dashicons-megaphone', 'rewrite' => array('slug' => 'product-showcase'),
        )
    );
}

// --- متاباکس‌ها در پیشخوان ---
add_action('add_meta_boxes', 'ps_add_meta_boxes');
function ps_add_meta_boxes() {
    add_meta_box('ps_catalog_meta_box', 'کاتالوگ محصول', 'ps_catalog_meta_box_callback', 'product_showcase', 'side', 'default');
    // *** متاباکس جدید برای گالری تصاویر ***
    add_meta_box('ps_gallery_meta_box', 'گالری تصاویر محصول', 'ps_gallery_meta_box_callback', 'product_showcase', 'normal', 'default');
}

// --- متاباکس کاتالوگ PDF (همان کد قبلی) ---
function ps_catalog_meta_box_callback($post) {
    wp_nonce_field(basename(__FILE__), 'ps_catalog_nonce');
    $pdf_url = get_post_meta($post->ID, '_ps_pdf_url', true);
    echo '<label for="ps_pdf_url">آدرس فایل PDF کاتالوگ:</label>';
    echo '<input type="text" id="ps_pdf_url" name="ps_pdf_url" value="' . esc_url($pdf_url) . '" style="width: 100%;" />';
    echo '<p><button type="button" id="ps_pdf_upload_button" class="button">انتخاب فایل PDF</button></p>';
}

// --- متاباکس گالری تصاویر (کد جدید) ---
function ps_gallery_meta_box_callback($post) {
    wp_nonce_field(basename(__FILE__), 'ps_gallery_nonce');
    $gallery_ids = get_post_meta($post->ID, '_ps_gallery_ids', true);
    $gallery_ids_array = $gallery_ids ? explode(',', $gallery_ids) : array();

    echo '<div id="ps-gallery-container">';
    echo '<input type="hidden" id="ps_gallery_ids" name="ps_gallery_ids" value="' . esc_attr($gallery_ids) . '" />';
    echo '<p><button type="button" id="ps_gallery_upload_button" class="button">مدیریت گالری تصاویر</button></p>';
    echo '<div id="ps-gallery-preview">';

    if (!empty($gallery_ids_array)) {
        foreach ($gallery_ids_array as $id) {
            $thumb_src = wp_get_attachment_image_src($id, 'thumbnail');
            if ($thumb_src) {
                echo '<div class="ps-gallery-thumb" data-id="' . esc_attr($id) . '"><img src="' . esc_url($thumb_src[0]) . '" /><span class="ps-gallery-remove">&times;</span></div>';
            }
        }
    }

    echo '</div></div>';
}

// --- ذخیره اطلاعات متاباکس‌ها ---
add_action('save_post', 'ps_save_meta_box_data');
function ps_save_meta_box_data($post_id) {
    if (!isset($_POST['ps_catalog_nonce']) || !wp_verify_nonce($_POST['ps_catalog_nonce'], basename(__FILE__))) return;
    if (!isset($_POST['ps_gallery_nonce']) || !wp_verify_nonce($_POST['ps_gallery_nonce'], basename(__FILE__))) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if ('product_showcase' !== $_POST['post_type']) return;
    if (current_user_can('edit_post', $post_id)) {
        update_post_meta($post_id, '_ps_pdf_url', esc_url_raw($_POST['ps_pdf_url']));
        update_post_meta($post_id, '_ps_gallery_ids', sanitize_text_field($_POST['ps_gallery_ids']));
    }
}

// --- شورت‌کد برای نمایش محصولات ---
add_shortcode('product_showcase', 'ps_render_shortcode');
function ps_render_shortcode() {
    wp_enqueue_style('ps-showcase-style', plugins_url('assets/showcase-style.css', __FILE__));
    wp_enqueue_script('ps-showcase-script', plugins_url('assets/showcase-script.js', __FILE__), array('jquery'), '1.6', true);

    $args = array('post_type' => 'product_showcase', 'posts_per_page' => -1, 'post_status' => 'publish');
    $products_query = new WP_Query($args);
    $products_data_array = array();

    if ($products_query->have_posts()) {
        while ($products_query->have_posts()) {
            $products_query->the_post();
            $product_id = get_the_ID();
            
            $all_image_urls = array();
            
            // 1. دریافت تصویر شاخص
            $thumbnail_id = get_post_thumbnail_id($product_id);
            if ($thumbnail_id) {
                $all_image_urls[] = wp_get_attachment_image_src($thumbnail_id, 'full')[0];
            }

            // 2. دریافت تصاویر گالری از متاباکس
            $gallery_ids = get_post_meta($product_id, '_ps_gallery_ids', true);
            if ($gallery_ids) {
                $gallery_ids_array = explode(',', $gallery_ids);
                foreach ($gallery_ids_array as $id) {
                    $img_src = wp_get_attachment_image_src($id, 'full');
                    if ($img_src) {
                        $all_image_urls[] = $img_src[0];
                    }
                }
            }

            $products_data_array[$product_id] = array(
                'title' => get_the_title(),
                'short_desc' => get_the_excerpt(),
                'content' => apply_filters('the_content', get_the_content()),
                'gallery' => $all_image_urls, // ارسال آرایه کامل تصاویر
                'pdf_url' => get_post_meta($product_id, '_ps_pdf_url', true),
            );
        }
        wp_reset_postdata();
    }

    wp_localize_script('ps-showcase-script', 'ps_products_data', $products_data_array);

    $output = '<div class="product-showcase-container">';
    if (!empty($products_data_array)) {
        foreach ($products_data_array as $product_id => $product) {
            // استفاده از اولین تصویر برای کارت
            $card_image = !empty($product['gallery']) ? $product['gallery'][0] : '';
            $output .= '<div class="product-card">';
            $output .= '<div class="product-card-images">';
            if ($card_image) {
                $output .= '<img src="' . esc_url($card_image) . '" alt="' . esc_attr($product['title']) . '">';
            }
            $output .= '</div>';
            $output .= '<div class="product-content">';
            $output .= '<h3>' . esc_html($product['title']) . '</h3>';
            $output .= '<p>' . esc_html($product['short_desc']) . '</p>';
            $output .= '<div class="product-buttons">';
            $output .= '<button class="button button-primary ps-view-details" data-id="' . $product_id . '">مشاهده جزئیات</button>';
            if (!empty($product['pdf_url'])) {
                $output .= '<a href="' . esc_url($product['pdf_url']) . '" target="_blank" class="button ps-catalog-btn">دانلود کاتالوگ</a>';
            }
            $output .= '</div>';
            $output .= '</div>';
            $output .= '</div>';
        }
    } else {
        $output .= '<p>هیچ محصولی برای نمایش یافت نشد.</p>';
    }
    $output .= '</div>';

    // مودال
    $output .= '<div id="ps-modal" class="ps-modal">';
    $output .= '<div class="ps-modal-content">';
    $output .= '<div class="ps-modal-header"><div id="ps-gallery-container"></div></div>';
    $output .= '<span class="ps-close">&times;</span>';
    $output .= '<div id="ps-modal-body"></div>';
    $output .= '</div>';
    $output .= '</div>';

    return $output;
}

// --- ستون تصویر در پیشخوان ---
add_filter('manage_product_showcase_posts_columns', 'ps_add_thumbnail_column', 5);
function ps_add_thumbnail_column($columns) {
    $columns['ps_thumbnail'] = __('تصویر');
    $title_column = $columns['title'];
    unset($columns['title']);
    $columns['title'] = $title_column;
    return $columns;
}
add_action('manage_product_showcase_posts_custom_column', 'ps_show_thumbnail_column', 5, 2);
function ps_show_thumbnail_column($column_name, $post_id) {
    if ($column_name == 'ps_thumbnail') {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $thumbnail_img = wp_get_attachment_image_src($thumbnail_id, 'thumbnail');
            echo '<img src="' . esc_url($thumbnail_img[0]) . '" width="60" height="60" style="border-radius: 8px; object-fit: cover;">';
        } else {
            echo '<span style="color: #ccc;">-</span>';
        }
    }
}

// --- اسکریپت‌های پیشخوان ---
add_action('admin_enqueue_scripts', 'ps_admin_scripts');
function ps_admin_scripts($hook) {
    global $post_type;
    if ('post.php' === $hook || 'post-new.php' === $hook) {
        if ('product_showcase' === $post_type) {
            wp_enqueue_media();
            wp_enqueue_script('ps-admin-script', plugins_url('assets/admin-upload.js', __FILE__), array('jquery'), '1.1', true);
        }
    }
}