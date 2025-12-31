<?php
/**
 * Plugin Name: My Front-End Support Panel
 * Description: A front-end dashboard to manage Contact Form 7 submissions with search, reporting, and archiving.
 * Version: 2.2.5
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

// --- تابع کمکی برای تبدیل تاریخ شمسی (با استفاده از افزونه ParsDate) ---
if (function_exists('parsidate')) {
    function msp_get_jdate($format, $timestamp = '') {
        return parsidate($format, $timestamp);
    }
} else {
    // اگر افزونه ParsDate فعال نباشد، از تاریخ میلادی استفاده می‌شود
    function msp_get_jdate($format, $timestamp = '') {
        return date_i18n($format, $timestamp);
    }
}

// --- تابع برای تبدیل نام فیلدها به فارسی و مدیریت مقادیر آرایه‌ای ---
function msp_format_field_data($data) {
    $field_labels = array(
        'your-name'      => 'نام و نام خانوادگی',
        'your-email'     => 'ایمیل',
        'your-subject'   => 'موضوع',
        'your-message'   => 'پیام',
        'fullname'       => 'نام و نام خانوادگی',
        'phone'          => 'شماره تماس',
        'email'          => 'ایمیل',
        'state'          => 'استان',
        'city'           => 'شهر',
        'tracking'       => 'کد پیگیری',
        'serial'         => 'سریال',
        'actor'          => 'نام مجری',
        'operation'      => 'نوع عملیات',
        'service-desc'   => 'توضیحات سرویس',
        'mc4wp_checkbox' => 'عضویت در خبرنامه',
        'national_code'  => 'کد ملی',
        'device_type'    => 'نوع دستگاه خریداری شده',
        'device_category' => 'یخچال یا ماشین لباسشویی',
        'secondary_phone' => 'شماره تلفن دوم یا منزل',
        'address_street' => 'خیابان',
        'address_alley'  => 'کوچه',
        'address_plaque' => 'پلاک',
        'address_unit'   => 'واحد'
    );

    $formatted_data = array();
    foreach ($data as $key => $value) {
        if ($key === '_wpcf7_unit_tag') {
            continue;
        }
        if (is_array($value)) {
            $value = implode(', ', $value);
        }
        if (isset($field_labels[$key])) {
            $formatted_data[$field_labels[$key]] = $value;
        } else {
            $formatted_data[ucfirst(str_replace('-', ' ', $key))] = $value;
        }
    }
    return $formatted_data;
}

// --- تابعی برای تبدیل تاریخ شمسی به میلادی (نسخه دقیق و استاندارد) ---
function msp_shamsi_to_miladi($shamsi_date) {
    $date_parts = explode('/', $shamsi_date);
    if (count($date_parts) !== 3) {
        return null;
    }
    $j_y = intval($date_parts[0]);
    $j_m = intval($date_parts[1]);
    $j_d = intval($date_parts[2]);

    // الگوریتم تبدیل شمسی به میلادی (بسیار دقیق)
    $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);

    $jy = $j_y - 979;
    $jm = $j_m - 1;
    $jd = $j_d - 1;

    $j_day_no = 365 * $jy + (int)($jy / 33) * 8 + (int)(($jy % 33 + 3) / 4);
    for ($i = 0; $i < $jm; ++$i) {
        $j_day_no += $j_days_in_month[$i];
    }
    $j_day_no += $jd;

    $g_day_no = $j_day_no + 79;

    $gy = 1600 + 400 * (int)($g_day_no / 146097);
    $g_day_no %= 146097;

    $leap = true;
    if ($g_day_no > 36524) {
        $g_day_no--;
        $gy += 100 * (int)($g_day_no / 36524);
        $g_day_no %= 36524;
        $leap = false;
    }
    $gy += 4 * (int)($g_day_no / 1461);
    $g_day_no %= 1461;

    if ($g_day_no > 365) {
        $leap = false;
        $g_day_no--;
        $gy += (int)($g_day_no / 365);
        $g_day_no %= 365;
    }

    for ($i = 0; $g_day_no >= $g_days_in_month[$i] + ($i == 1 && $leap); $i++) {
        $g_day_no -= $g_days_in_month[$i] + ($i == 1 && $leap);
    }
    $gm = $i + 1;
    $gd = $g_day_no + 1;

    return sprintf("%04d-%02d-%02d", $gy, $gm, $gd);
}

// --- تابعی برای تبدیل تاریخ میلادی به شمسی (برای استفاده در گزارش‌گیری) ---
function msp_miladi_to_shamsi($gregorian_date) {
    $g_y = substr($gregorian_date, 0, 4);
    $g_m = substr($gregorian_date, 5, 2);
    $g_d = substr($gregorian_date, 8, 2);
    
    $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);
    
    $gy = $g_y - 1600;
    $gm = $g_m - 1;
    $gd = $g_d - 1;
    
    $g_day_no = 365 * $gy + (int)($gy / 4) - (int)($gy / 100) + (int)($gy / 400) + $gd;
    
    for ($i = 0; $i < $gm; ++$i) {
        $g_day_no += $g_days_in_month[$i];
    }
    
    if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)))
        $g_day_no++;
        
    $j_day_no = $g_day_no - 79;
    
    $j_np = (int)($j_day_no / 12053);
    $j_day_no %= 12053;
    
    $jy = 979 + 33 * $j_np + 4 * (int)($j_day_no / 1461);
    $j_day_no %= 1461;
    
    if ($j_day_no >= 366) {
        $jy += (int)(($j_day_no - 1) / 365);
        $j_day_no = ($j_day_no - 1) % 365;
    }
    
    for ($i = 0; $i < 11 && $j_day_no >= 31 + ($i % 2); $i++) {
        $j_day_no -= 31 + ($i % 2);
    }
    
    $jm = $i + 1;
    $jd = $j_day_no + 1;
    
    return sprintf("%04d/%02d/%02d", $jy, $jm, $jd);
}

// --- فعال‌سازی افزونه ---
register_activation_hook(__FILE__, 'msp_activate');
function msp_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'support_messages';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        form_title varchar(255) NOT NULL,
        submission_data longtext NOT NULL,
        submission_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        status varchar(20) DEFAULT 'new' NOT NULL,
        is_archived TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    $column = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        DB_NAME, $table_name, 'is_archived'
    ));
    if (empty($column)) {
        $wpdb->query("ALTER TABLE $table_name ADD is_archived TINYINT(1) NOT NULL DEFAULT 0");
    }

    add_role('support_agent', 'اپراتور پشتیبانی', array('read' => true, 'read_support_messages' => true));
    $admin_role = get_role('administrator');
    if ($admin_role && ! $admin_role->has_cap('read_support_messages')) {
        $admin_role->add_cap('read_support_messages');
    }
}

// --- غیرفعال‌سازی افزونه ---
register_deactivation_hook(__FILE__, 'msp_deactivate');
function msp_deactivate() {
    remove_role('support_agent');
}

// --- اتصال به Contact Form 7 و ذخیره اطلاعات ---
add_action('wpcf7_before_send_mail', 'msp_save_submission');
function msp_save_submission($contact_form) {
    if (!class_exists('WPCF7_Submission')) return;
    
    $submission = WPCF7_Submission::get_instance();
    if ($submission) {
        global $wpdb;
        $posted_data = $submission->get_posted_data();
        $table_name = $wpdb->prefix . 'support_messages';

        $wpdb->insert(
            $table_name,
            array(
                'form_title' => $contact_form->title(),
                'submission_data' => maybe_serialize($posted_data),
                'submission_date' => current_time('mysql'),
                'status' => 'new',
                'is_archived' => 0
            )
        );
    }
}

// --- شورت‌کد اصلی داشبورد ---
add_shortcode('support_dashboard', 'msp_render_shortcode');
function msp_render_shortcode() {
    if (current_user_can('manage_options') || current_user_can('read_support_messages')) {
        return msp_render_frontend_dashboard();
    } else {
        ob_start();
        ?>
        <!-- پاپ آپ تایید -->
        <div id="msp-confirmation-modal" class="msp-modal">
            <div class="msp-modal-content msp-confirmation-content">
                <h3>تایید درخواست</h3>
                <p>در صورت اطمینان از سلامت ظاهری و قرار داشتن دستگاه در محل مناسب نصب،با دراختیار داشتن کارت گارانتی نسبت به ثبت درخواست اقدام بفرمایید.</p>
                <div class="msp-confirmation-actions">
                    <button id="msp-confirm-yes" class="button button-primary">تایید</button>
                    <button id="msp-confirm-no" class="button">انصراف</button>
                </div>
            </div>
        </div>

        <div class="msp-login-container">
            <div class="msp-login-card">
                <div class="msp-login-header">
                    <div class="msp-login-logo">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2L2 7L12 12L22 7L12 2Z"></path>
                            <path d="M2 17L12 22L22 17"></path>
                            <path d="M2 12L12 17L22 12"></path>
                        </svg>
                    </div>
                    <h2>پنل پشتیبانی</h2>
                    <p>برای دسترسی به پنل، لطفاً وارد شوید</p>
                </div>
                
                <form id="msp-ajax-login-form" class="msp-login-form" action="login" method="post">
                    <div class="msp-form-group">
                        <div class="msp-input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <input type="text" name="msp_username" id="msp_username" placeholder="نام کاربری" required>
                    </div>
                    
                    <div class="msp-form-group">
                        <div class="msp-input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </div>
                        <input type="password" name="msp_password" id="msp_password" placeholder="رمز عبور" required>
                    </div>
                    
                    <div class="msp-form-options">
                        <label class="msp-checkbox-container">
                            <input type="checkbox" name="msp_rememberme" id="msp_rememberme">
                            <span class="msp-checkmark"></span>
                            مرا به خاطر بسپار
                        </label>
                        <a href="#" class="msp-forgot-password">فراموشی رمز عبور؟</a>
                    </div>
                    
                    <button type="submit" name="msp_submit" class="msp-login-btn">
                        <span class="msp-btn-text">ورود به پنل</span>
                        <span class="msp-btn-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </span>
                    </button>
                </form>
                
                <div id="msp-login-message" class="msp-login-message"></div>
                
                <div class="msp-login-footer">
                    <p>پنل پشتیبانی نسخه 2.2.5</p>
                    <p>&copy; <?php echo date('Y'); ?> تمامی حقوق محفوظ است</p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// --- تابع رندر کردن داشبورد جلویی ---
function msp_render_frontend_dashboard($search_term = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'support_messages';

    $current_user = wp_get_current_user();
    $output = '<div class="msp-welcome-box">';
    $output .= '<h2>خوش آمدید، ' . esc_html($current_user->display_name) . '!</h2>';
    $output .= '<p>در اینجا می‌توانید تمام پیام‌های ارسال شده را مدیریت کنید.</p>';
    $output .= '<a href="' . wp_logout_url(get_permalink()) . '" class="msp-logout-btn">خروج</a>';
    $output .= '</div>';

    $output .= '<div class="msp-tabs">';
    $output .= '<a href="#msp-dashboard-content" class="msp-tab-link active">پیام‌ها</a>';
    $output .= '<a href="#msp-reports-content" class="msp-tab-link">گزارش‌گیری</a>';
    $output .= '<a href="#msp-archive-content" class="msp-tab-link">بایگانی</a>';
    $output .= '</div>';

    $output .= '<div id="msp-dashboard-content" class="msp-tab-content active">';
    $output .= '<div class="msp-search-container">';
    $output .= '<input type="text" id="msp-search-input" placeholder="جستجو در پیام‌ها..." value="' . esc_attr($search_term) . '">';
    $output .= '</div>';
    
    $output .= '<div class="msp-table-container">';
    $sql = "SELECT * FROM $table_name WHERE is_archived = 0";
    if (!empty($search_term)) {
        $sql .= $wpdb->prepare(" AND (form_title LIKE %s OR submission_data LIKE %s)", '%' . $wpdb->esc_like($search_term) . '%', '%' . $wpdb->esc_like($search_term) . '%');
    }
    $sql .= " ORDER BY submission_date DESC";
    $messages = $wpdb->get_results($sql);

    if (!empty($messages)) {
        $output .= '<table class="msp-table">';
        $output .= '<thead><tr><th>ردیف</th><th>شناسه</th><th>عنوان فرم</th><th>تاریخ ارسال</th><th>وضعیت</th><th>عملیات</th></tr></thead>';
        $output .= '<tbody id="msp-message-table-body">';
        
        $row_number = 1;
        foreach ($messages as $message) {
            $status_class = $message->status == 'new' ? 'status-new' : 'status-read';
            $status_text = $message->status == 'new' ? 'جدید' : 'خوانده شده';
            $output .= '<tr class="msp-row">';
            $output .= '<td>' . $row_number . '</td>';
            $output .= '<td>' . $message->id . '</td>';
            $output .= '<td><strong>' . esc_html($message->form_title) . '</strong></td>';
            $output .= '<td>' . msp_get_jdate('Y/m/d - H:i', strtotime($message->submission_date)) . '</td>';
            $output .= '<td><span class="msp-status ' . $status_class . '">' . $status_text . '</span></td>';
            $output .= '<td>';
            $output .= '<button class="button button-primary msp-view-btn" data-id="' . $message->id . '">مشاهده</button> ';
            $output .= '<button class="button msp-archive-btn" data-id="' . $message->id . '">بایگانی</button>';
            $output .= '</td>';
            $output .= '</tr>';
            $row_number++;
        }
        $output .= '</tbody></table>';
    } else {
        $output .= '<p class="msp-no-messages">هیچ پیامی یافت نشد.</p>';
    }
    $output .= '</div>';
    $output .= '</div>';

    $output .= '<div id="msp-reports-content" class="msp-tab-content">';
    $output .= msp_render_report_form();
    $output .= '</div>';

    $output .= '<div id="msp-archive-content" class="msp-tab-content">';
    $output .= msp_render_archive_dashboard();
    $output .= '</div>';

    $output .= '<div id="msp-modal" class="msp-modal">';
    $output .= '<div class="msp-modal-content">';
    $output .= '<span class="msp-close">&times;</span>';
    $output .= '<div id="msp-modal-body"></div>';
    $output .= '</div>';
    $output .= '</div>';

    return $output;
}

// --- تابع رندر کردن فرم گزارش‌گیری ---
function msp_render_report_form() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'support_messages';
    $form_titles = $wpdb->get_col("SELECT DISTINCT form_title FROM $table_name ORDER BY form_title ASC");

    $output = '<div class="msp-report-form-container">';
    $output .= '<h3>گزارش‌گیری پیام‌ها</h3>';
    $output .= '<form id="msp-report-form">';
    $output .= '<div class="msp-form-row"><label for="msp-start-date">از تاریخ:</label><input type="text" id="msp-start-date" name="start_date" placeholder="انتخاب کنید" readonly></div>';
    $output .= '<div class="msp-form-row"><label for="msp-end-date">تا تاریخ:</label><input type="text" id="msp-end-date" name="end_date" placeholder="انتخاب کنید" readonly></div>';
    $output .= '<div class="msp-form-row"><label for="msp-form-filter">نوع فرم:</label><select id="msp-form-filter" name="form_filter"><option value="">همه فرم‌ها</option>';
    if ($form_titles) {
        foreach ($form_titles as $title) {
            $output .= '<option value="' . esc_attr($title) . '">' . esc_html($title) . '</option>';
        }
    }
    $output .= '</select></div>';
    $output .= '<div class="msp-form-actions">';
    $output .= '<button type="button" id="msp-generate-report-btn" class="button button-primary">نمایش گزارش</button>';
    $output .= '<button type="button" id="msp-export-csv-btn" class="button">خروجی CSV</button>';
    $output .= '</div></form>';
    $output .= '<div id="msp-report-results-container"></div>';
    $output .= '</div>';
    return $output;
}

// --- تابع رندر کردن داشبورد بایگانی (با تغییرات جدید) ---
function msp_render_archive_dashboard($start_date = '', $end_date = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'support_messages';
    
    $output = '<div class="msp-archive-filter-container">';
    $output .= '<h3>بایگانی پیام‌ها</h3>';
    
    // تغییر: استفاده از فیلدهای متنی تقویم به جای Select
    $output .= '<form id="msp-archive-filter-form">';
    $output .= '<div class="msp-archive-date-inputs">';
    $output .= '<div class="msp-form-row msp-form-inline">';
    $output .= '<label for="msp-archive-start-date">از تاریخ:</label>';
    $output .= '<input type="text" id="msp-archive-start-date" name="archive_start_date" placeholder="انتخاب کنید" readonly value="' . esc_attr($start_date) . '">';
    $output .= '</div>';
    
    $output .= '<div class="msp-form-row msp-form-inline">';
    $output .= '<label for="msp-archive-end-date">تا تاریخ:</label>';
    $output .= '<input type="text" id="msp-archive-end-date" name="archive_end_date" placeholder="انتخاب کنید" readonly value="' . esc_attr($end_date) . '">';
    $output .= '</div>';
    
    $output .= '<div class="msp-form-actions msp-form-inline-actions">';
    $output .= '<button type="button" id="msp-filter-archive-btn" class="button button-primary">اعمال فیلتر</button>';
    $output .= '<button type="button" id="msp-reset-archive-btn" class="button">نمایش همه</button>';
    $output .= '</div>';
    $output .= '</div>'; // End .msp-archive-date-inputs
    $output .= '</form>';
    $output .= '</div>';

    $output .= '<div class="msp-table-container">';
    $sql = "SELECT * FROM $table_name WHERE is_archived = 1";
    
    // تغییر: منطق SQL برای پشتیبانی از بازه تاریخ
    $where_conditions = array();
    if (!empty($start_date)) { 
        $miladi_start_date = msp_shamsi_to_miladi($start_date);
        if ($miladi_start_date) {
            $where_conditions[] = $wpdb->prepare("submission_date >= %s", $miladi_start_date . ' 00:00:00');
        }
    }
    if (!empty($end_date)) { 
        $miladi_end_date = msp_shamsi_to_miladi($end_date);
        if ($miladi_end_date) {
            $where_conditions[] = $wpdb->prepare("submission_date <= %s", $miladi_end_date . ' 23:59:59');
        }
    }
    
    if (!empty($where_conditions)) {
        $sql .= " AND " . implode(' AND ', $where_conditions);
    }
    
    $sql .= " ORDER BY submission_date DESC";
    $messages = $wpdb->get_results($sql);

    if (!empty($messages)) {
        $output .= '<table class="msp-table">';
        $output .= '<thead><tr><th>ردیف</th><th>شناسه</th><th>عنوان فرم</th><th>تاریخ ارسال</th><th>وضعیت</th><th>عملیات</th></tr></thead>';
        $output .= '<tbody>';
        
        $row_number = 1;
        foreach ($messages as $message) {
            $status_class = $message->status == 'new' ? 'status-new' : 'status-read';
            $status_text = $message->status == 'new' ? 'جدید' : 'خوانده شده';
            $output .= '<tr class="msp-row">';
            $output .= '<td>' . $row_number . '</td>';
            $output .= '<td>' . $message->id . '</td>';
            $output .= '<td><strong>' . esc_html($message->form_title) . '</strong></td>';
            $output .= '<td>' . msp_get_jdate('Y/m/d - H:i', strtotime($message->submission_date)) . '</td>';
            $output .= '<td><span class="msp-status ' . $status_class . '">' . $status_text . '</span></td>';
            $output .= '<td><button class="button button-primary msp-view-btn" data-id="' . $message->id . '">مشاهده</button></td>';
            $output .= '</tr>';
            $row_number++;
        }
        $output .= '</tbody></table>';
    } else {
        $output .= '<p class="msp-no-messages">هیچ پیامی در بایگانی یافت نشد.</p>';
    }
    $output .= '</div>';

    return $output;
}

// --- بارگذاری اسکریپت‌ها و استایل‌های اصلی ---
add_action('wp_enqueue_scripts', 'msp_frontend_assets');
function msp_frontend_assets() {
    wp_enqueue_style('msp-frontend-style', plugins_url('assets/style.css', __FILE__));
    wp_enqueue_script('msp-frontend-script', plugins_url('assets/script.js', __FILE__), array('jquery'), '2.2.5', true);
    
    // دریافت تاریخ شمسی دقیق امروز از سرور
    $current_persian_date = msp_get_jdate('Y/m/d');
    
    // دریافت تاریخ میلادی امروز برای استفاده در تقویم جاوا اسکریپت
    $current_gregorian_date = date('Y-m-d');
    
    // تجزیه تاریخ شمسی به اجزای سال، ماه و روز
    $persian_date_parts = explode('/', $current_persian_date);
    $persian_year = intval($persian_date_parts[0]);
    $persian_month = intval($persian_date_parts[1]);
    $persian_day = intval($persian_date_parts[2]);

    // ارسال تاریخ به همراه سایر متغیرهای مورد نیاز به جاوا اسکریپت
    wp_localize_script('msp-frontend-script', 'msp_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'), 
        'nonce' => wp_create_nonce('msp-ajax-nonce'),
        'current_persian_date' => $current_persian_date,
        'current_gregorian_date' => $current_gregorian_date,
        'persian_year' => $persian_year,
        'persian_month' => $persian_month,
        'persian_day' => $persian_day
    ));
}

// --- اکشن‌های AJAX ---
add_action('wp_ajax_msp_ajax_login', 'msp_ajax_login');
add_action('wp_ajax_nopriv_msp_ajax_login', 'msp_ajax_login');
function msp_ajax_login() {
    check_ajax_referer('msp-ajax-nonce', 'security');
    $info = array('user_login' => $_POST['username'], 'user_password' => $_POST['password'], 'remember' => isset($_POST['rememberme']));
    $user_signon = wp_signon($info, false);
    if (is_wp_error($user_signon)) {
        wp_send_json_error(array('message' => '<strong>خطا:</strong> ' . $user_signon->get_error_message()));
    } else {
        wp_send_json_success(array('message' => 'ورود با موفقیت انجام شد. در حال انتقال...'));
    }
}

add_action('wp_ajax_msp_search_messages', 'msp_search_messages');
function msp_search_messages() {
    check_ajax_referer('msp-ajax-nonce', 'security');
    if (!current_user_can('read_support_messages')) { wp_send_json_error('شما دسترسی لازم برای این کار را ندارید.'); }
    $search_term = sanitize_text_field($_POST['search_term']);
    $dashboard_html = msp_render_frontend_dashboard($search_term);
    $doc = new DOMDocument(); libxml_use_internal_errors(true);
    $doc->loadHTML(mb_convert_encoding($dashboard_html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($doc);
    $table_container = $xpath->query('//div[@class="msp-table-container"]')->item(0);
    $table_html = $doc->saveHTML($table_container);
    wp_send_json_success(array('html' => $table_html));
}

add_action('wp_ajax_msp_get_message', 'msp_ajax_get_message');
function msp_ajax_get_message() {
    check_ajax_referer('msp-ajax-nonce', 'security');
    if (!current_user_can('read_support_messages')) { wp_send_json_error('شما دسترسی لازم برای این کار را ندارید.'); }
    global $wpdb; $message_id = intval($_POST['message_id']);
    $table_name = $wpdb->prefix . 'support_messages';
    $message = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $message_id));
    if ($message) {
        $wpdb->update($table_name, array('status' => 'read'), array('id' => $message_id));
        $data = maybe_unserialize($message->submission_data);
        $formatted_data = msp_format_field_data($data);

        $output = '<h3>جزئیات درخواست</h3><table class="widefat">';
        $output .= '<tr><td><strong>عنوان فرم:</strong></td><td>' . esc_html($message->form_title) . '</td></tr>';
        $output .= '<tr><td><strong>تاریخ ارسال:</strong></td><td>' . msp_get_jdate('Y/m/d - H:i', strtotime($message->submission_date)) . '</td></tr>';
        
        foreach ($formatted_data as $label => $value) {
            $output .= '<tr><td><strong>' . esc_html($label) . ':</strong></td><td>' . esc_html($value) . '</td></tr>';
        }
        
        // نمایش آدرس به صورت ترکیبی
        $address_parts = array();
        if (isset($data['address_street']) && !empty($data['address_street'])) {
            $address_parts[] = 'خیابان: ' . esc_html($data['address_street']);
        }
        if (isset($data['address_alley']) && !empty($data['address_alley'])) {
            $address_parts[] = 'کوچه: ' . esc_html($data['address_alley']);
        }
        if (isset($data['address_plaque']) && !empty($data['address_plaque'])) {
            $address_parts[] = 'پلاک: ' . esc_html($data['address_plaque']);
        }
        if (isset($data['address_unit']) && !empty($data['address_unit'])) {
            $address_parts[] = 'واحد: ' . esc_html($data['address_unit']);
        }
        
        if (!empty($address_parts)) {
            $output .= '<tr><td><strong>آدرس کامل:</strong></td><td>' . implode('، ', $address_parts) . '</td></tr>';
        }
        
        $output .= '</table>';
        wp_send_json_success($output);
    } else {
        wp_send_json_error('پیام یافت نشد.');
    }
}

add_action('wp_ajax_msp_archive_message', 'msp_archive_message');
function msp_archive_message() {
    check_ajax_referer('msp-ajax-nonce', 'security');
    if (!current_user_can('read_support_messages')) { wp_send_json_error('شما دسترسی لازم برای این کار را ندارید.'); }
    global $wpdb; $message_id = intval($_POST['message_id']);
    $table_name = $wpdb->prefix . 'support_messages';
    $result = $wpdb->update($table_name, array('is_archived' => 1), array('id' => $message_id));
    if ($result !== false) {
        wp_send_json_success(array('message' => 'پیام با موفقیت بایگانی شد.'));
    } else {
        wp_send_json_error('خطا در بایگانی پیام.');
    }
}

add_action('wp_ajax_msp_filter_archive', 'msp_filter_archive');
function msp_filter_archive() {
    check_ajax_referer('msp-ajax-nonce', 'security');
    if (!current_user_can('read_support_messages')) { wp_send_json_error('شما دسترسی لازم برای این کار را ندارید.'); }
    
    // تغییر: دریافت دو تاریخ شروع و پایان
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
    
    $archive_html = msp_render_archive_dashboard($start_date, $end_date);
    $doc = new DOMDocument(); libxml_use_internal_errors(true);
    $doc->loadHTML(mb_convert_encoding($archive_html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($doc);
    $table_container = $xpath->query('//div[@class="msp-table-container"]')->item(0);
    $table_html = $doc->saveHTML($table_container);
    wp_send_json_success(array('html' => $table_html));
}

add_action('wp_ajax_msp_generate_report', 'msp_generate_report');
function msp_generate_report() {
    check_ajax_referer('msp-ajax-nonce', 'security');
    if (!current_user_can('read_support_messages')) { wp_send_json_error('شما دسترسی لازم برای این کار را ندارید.'); }
    global $wpdb; $table_name = $wpdb->prefix . 'support_messages';
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
    $form_filter = isset($_POST['form_filter']) ? sanitize_text_field($_POST['form_filter']) : '';
    $where_conditions = array();

    if (!empty($start_date)) { 
        $miladi_start_date = msp_shamsi_to_miladi($start_date);
        if ($miladi_start_date) {
            $where_conditions[] = $wpdb->prepare("submission_date >= %s", $miladi_start_date . ' 00:00:00');
        }
    }
    if (!empty($end_date)) { 
        $miladi_end_date = msp_shamsi_to_miladi($end_date);
        if ($miladi_end_date) {
            $where_conditions[] = $wpdb->prepare("submission_date <= %s", $miladi_end_date . ' 23:59:59');
        }
    }
    if (!empty($form_filter)) { $where_conditions[] = $wpdb->prepare("form_title = %s", $form_filter); }
    
    $sql = "SELECT * FROM $table_name";
    if (!empty($where_conditions)) { $sql .= " WHERE " . implode(' AND ', $where_conditions); }
    $sql .= " ORDER BY submission_date DESC";
    $messages = $wpdb->get_results($sql);
    ob_start();
    if ($messages) {
        echo '<h4>نتایج گزارش (' . count($messages) . ' مورد یافت شد)</h4>';
        echo '<table class="msp-table msp-report-table"><thead><tr><th>ردیف</th><th>شناسه</th><th>عنوان فرم</th><th>تاریخ ارسال</th><th>وضعیت</th></tr></thead><tbody>';
        
        $row_number = 1;
        foreach ($messages as $message) {
            $status_class = $message->status == 'new' ? 'status-new' : 'status-read';
            $status_text = $message->status == 'new' ? 'جدید' : 'خوانده شده';
            echo '<tr><td>' . $row_number . '</td><td>' . $message->id . '</td><td>' . esc_html($message->form_title) . '</td><td>' . msp_get_jdate('Y/m/d - H:i', strtotime($message->submission_date)) . '</td><td><span class="msp-status ' . $status_class . '">' . $status_text . '</span></td></tr>';
            $row_number++;
        }
        echo '</tbody></table>';
    } else {
        echo '<p class="msp-no-messages">هیچ گزارشی با این فیلترها یافت نشد.</p>';
    }
    $html = ob_get_clean();
    wp_send_json_success(array('html' => $html));
}

add_action('wp_ajax_msp_export_report_csv', 'msp_export_report_csv');
function msp_export_report_csv() {
    check_ajax_referer('msp-ajax-nonce', 'security');
    if (!current_user_can('read_support_messages')) { wp_die('شما دسترسی لازم برای این کار را ندارید.'); }
    global $wpdb; $table_name = $wpdb->prefix . 'support_messages';
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
    $form_filter = isset($_POST['form_filter']) ? sanitize_text_field($_POST['form_filter']) : '';
    $where_conditions = array();

    if (!empty($start_date)) { 
        $miladi_start_date = msp_shamsi_to_miladi($start_date);
        if ($miladi_start_date) {
            $where_conditions[] = $wpdb->prepare("submission_date >= %s", $miladi_start_date . ' 00:00:00');
        }
    }
    if (!empty($end_date)) { 
        $miladi_end_date = msp_shamsi_to_miladi($end_date);
        if ($miladi_end_date) {
            $where_conditions[] = $wpdb->prepare("submission_date <= %s", $miladi_end_date . ' 23:59:59');
        }
    }
    if (!empty($form_filter)) { $where_conditions[] = $wpdb->prepare("form_title = %s", $form_filter); }

    $sql = "SELECT * FROM $table_name";
    if (!empty($where_conditions)) { $sql .= " WHERE " . implode(' AND ', $where_conditions); }
    $sql .= " ORDER BY submission_date DESC";
    $messages = $wpdb->get_results($sql);
    
    $all_fields = array();
    foreach ($messages as $message) {
        $data = maybe_unserialize($message->submission_data);
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($key !== '_wpcf7_unit_tag') {
                    $all_fields[$key] = true;
                }
            }
        }
    }
    
    $field_labels = array(
        'your-name'      => 'نام و نام خانوادگی',
        'your-email'     => 'ایمیل',
        'your-subject'   => 'موضوع',
        'your-message'   => 'پیام',
        'fullname'       => 'نام و نام خانوادگی',
        'phone'          => 'شماره تماس',
        'email'          => 'ایمیل',
        'state'          => 'استان',
        'city'           => 'شهر',
        'tracking'       => 'کد پیگیری',
        'serial'         => 'سریال',
        'actor'          => 'نام مجری',
        'operation'      => 'نوع عملیات',
        'service-desc'   => 'توضیحات سرویس',
        'mc4wp_checkbox' => 'عضویت در خبرنامه',
        'national_code'  => 'کد ملی',
        'device_type'    => 'نوع دستگاه خریداری شده',
        'device_category' => 'یخچال یا ماشین لباسشویی',
        'secondary_phone' => 'شماره تلفن دوم یا منزل',
        'address_street' => 'خیابان',
        'address_alley'  => 'کوچه',
        'address_plaque' => 'پلاک',
        'address_unit'   => 'واحد'
    );
    
    $header = array('ردیف', 'شناسه', 'عنوان فرم', 'تاریخ ارسال (شمسی)', 'وضعیت');
    foreach (array_keys($all_fields) as $field) {
        if (isset($field_labels[$field])) {
            $header[] = $field_labels[$field];
        } else {
            $header[] = ucfirst(str_replace('-', ' ', $field));
        }
    }
    $header[] = 'آدرس کامل';
    
    $filename = 'support_report_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Cache-Control: max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $file = fopen('php://output', 'w');
    fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($file, $header);
    
    $row_number = 1;
    foreach ($messages as $message) {
        $data = maybe_unserialize($message->submission_data);
        $row = array(
            $row_number,
            $message->id,
            $message->form_title,
            msp_get_jdate('Y/m/d - H:i', strtotime($message->submission_date)),
            ($message->status == 'new' ? 'جدید' : 'خوانده شده')
        );
        
        foreach (array_keys($all_fields) as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $row[] = $value;
            } else {
                $row[] = '';
            }
        }
        
        $address_parts = array();
        if (isset($data['address_street']) && !empty($data['address_street'])) {
            $address_parts[] = 'خیابان: ' . $data['address_street'];
        }
        if (isset($data['address_alley']) && !empty($data['address_alley'])) {
            $address_parts[] = 'کوچه: ' . $data['address_alley'];
        }
        if (isset($data['address_plaque']) && !empty($data['address_plaque'])) {
            $address_parts[] = 'پلاک: ' . $data['address_plaque'];
        }
        if (isset($data['address_unit']) && !empty($data['address_unit'])) {
            $address_parts[] = 'واحد: ' . $data['address_unit'];
        }
        $row[] = implode('، ', $address_parts);
        
        fputcsv($file, $row);
        $row_number++;
    }
    
    fclose($file);
    exit;
}