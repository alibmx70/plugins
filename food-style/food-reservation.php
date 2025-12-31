<?php
/**
 * Plugin Name: Food Reservation Viewer
 * Description: نمایش غذای رزرو شده بر اساس نام کاربری و تاریخ.
 * Version: 1.0
 * Author: Your Name
 */

global $wpdb;

// ایجاد جداول پایگاه داده هنگام فعال‌سازی
function create_food_reservation_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // جدول ذخیره رزرو کاربران
    $reservations_table = $wpdb->prefix . 'food_reservations';
    $sql1 = "CREATE TABLE $reservations_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        month INT NOT NULL,
        day_of_month INT NOT NULL,
        meal_selected VARCHAR(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // جدول ذخیره غذاهای ماهانه
    $meals_table = $wpdb->prefix . 'food_menu';
    $sql2 = "CREATE TABLE $meals_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        month INT NOT NULL,
        day_of_month INT NOT NULL,
        meal_1 VARCHAR(255) NOT NULL,
        meal_2 VARCHAR(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql1);
    dbDelta($sql2);
}
register_activation_hook(__FILE__, 'create_food_reservation_tables');

// تابع تبدیل تاریخ میلادی به شمسی
function gregorian_to_persian($g_y, $g_m, $g_d) {
    $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);
    
    $gy = $g_y - 1600;
    $gm = $g_m - 1;
    $gd = $g_d - 1;
    
    $g_day_no = 365 * $gy + floor(($gy + 3) / 4) - floor(($gy + 99) / 100) + floor(($gy + 399) / 400);
    
    for ($i = 0; $i < $gm; ++$i)
        $g_day_no += $g_days_in_month[$i];
    
    if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)))
        $g_day_no++;
    
    $g_day_no += $gd;
    
    $j_day_no = $g_day_no - 79;
    
    $j_np = floor($j_day_no / 12053);
    $j_day_no %= 12053;
    
    $jy = 979 + 33 * $j_np + 4 * floor($j_day_no / 1461);
    $j_day_no %= 1461;
    
    if ($j_day_no >= 366) {
        $jy += floor(($j_day_no - 1) / 365);
        $j_day_no = ($j_day_no - 1) % 365;
    }
    
    for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i)
        $j_day_no -= $j_days_in_month[$i];
    
    $jm = $i + 1;
    $jd = $j_day_no + 1;
    
    return array($jy, $jm, $jd);
}

// تابع تولید فایل اکسل
function export_kitchen_orders_to_excel($month, $day) {
    global $wpdb;
    
    // دریافت لیست کاربران و غذای انتخاب شده آن‌ها
    $user_orders = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, u.user_login, um1.meta_value as first_name, um2.meta_value as last_name 
        FROM {$wpdb->prefix}food_reservations r 
        JOIN {$wpdb->users} u ON r.user_id = u.ID 
        LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
        LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
        WHERE r.month = %d AND r.day_of_month = %d
        ORDER BY u.user_login",
        $month, $day
    ), ARRAY_A);
    
    // دریافت منوی آن روز
    $menu = $wpdb->get_row($wpdb->prepare(
        "SELECT meal_1, meal_2 
        FROM {$wpdb->prefix}food_menu 
        WHERE month = %d AND day_of_month = %d",
        $month, $day
    ), ARRAY_A);
    
    // نام فایل با پسوند xls
    $filename = 'safarhat_rozane_' . $month . '_' . $day . '.xls';
    
    // تنظیم هدرهای مناسب برای فایل اکسل
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // ایجاد فایل اکسل با فرمت HTML
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<meta name="ProgId" content="Excel.Sheet">';
    echo '<style>';
    echo 'body { font-family: "B Nazanin", "Tahoma", sans-serif; direction: rtl; }';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #ccc; padding: 8px; text-align: right; }';
    echo 'th { background-color: #f2f2f2; font-weight: bold; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // تعریف ماه‌های شمسی
    $persian_months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور',
                       'مهر','آبان','آذر','دی','بهمن','اسفند'];
    
    // افزودن عنوان گزارش
    echo '<h2>گزارش سفارشات روزانه آشپزخانه</h2>';
    echo '<p><strong>تاریخ:</strong> ' . $day . ' ' . $persian_months[$month-1] . '</p>';
    echo '<br>';
    
    // افزودن منوی روز
    if($menu) {
        echo '<h3>منوی روز:</h3>';
        echo '<table>';
        echo '<tr><th>غذای اول</th><th>غذای دوم</th></tr>';
        echo '<tr><td>' . htmlspecialchars($menu['meal_1']) . '</td><td>' . htmlspecialchars($menu['meal_2']) . '</td></tr>';
        echo '</table>';
        echo '<br>';
    }
    
    // افزودن جدول کاربران
    echo '<h3>لیست کاربران و غذای انتخاب شده:</h3>';
    echo '<table>';
    echo '<tr><th>نام کاربری</th><th>نام</th><th>نام خانوادگی</th><th>غذای انتخاب شده</th></tr>';
    
    // افزودن داده‌های کاربران
    foreach ($user_orders as $user_order) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($user_order['user_login']) . '</td>';
        echo '<td>' . htmlspecialchars($user_order['first_name']) . '</td>';
        echo '<td>' . htmlspecialchars($user_order['last_name']) . '</td>';
        echo '<td>' . htmlspecialchars($user_order['meal_selected']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    
    exit;
}

function kitchen_daily_orders_report() {
    global $wpdb;
    
    // بررسی درخواست خروجی اکسل
    if(isset($_POST['export_excel']) && isset($_POST['kitchen_month']) && isset($_POST['kitchen_day'])) {
        $month = intval($_POST['kitchen_month']);
        $day = intval($_POST['kitchen_day']);
        export_kitchen_orders_to_excel($month, $day);
    }
    
    ob_start(); ?>
    <div class="kitchen-orders-report">
        <h3>گزارش سفارشات روزانه (آشپزخانه)</h3>
        <form method="post">
            <p>
                <label for="kitchen_month">ماه:</label>
                <select id="kitchen_month" name="kitchen_month" required>
                    <option value="">ماه را انتخاب کنید</option>
                    <?php
                    $persian_months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور',
                                      'مهر','آبان','آذر','دی','بهمن','اسفند'];
                    foreach ($persian_months as $index => $name) {
                        echo "<option value='".($index+1)."'>$name</option>";
                    }
                    ?>
                </select>
            </p>
            <p>
                <label for="kitchen_day">روز:</label>
                <select id="kitchen_day" name="kitchen_day" required>
                    <option value="">روز را انتخاب کنید</option>
                    <?php for($i=1; $i<=31; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </p>
            <p><input type="submit" name="kitchen_view_orders" value="نمایش سفارشات"></p>
        </form>
        
        <?php
        if(isset($_POST['kitchen_view_orders'])) {
            $month = intval($_POST['kitchen_month']);
            $day = intval($_POST['kitchen_day']);
            
            // دریافت تعداد سفارشات هر غذا
            $orders = $wpdb->get_results($wpdb->prepare(
                "SELECT meal_selected, COUNT(*) as count 
                FROM {$wpdb->prefix}food_reservations 
                WHERE month = %d AND day_of_month = %d 
                GROUP BY meal_selected",
                $month, $day
            ), ARRAY_A);
            
            // دریافت منوی آن روز
            $menu = $wpdb->get_row($wpdb->prepare(
                "SELECT meal_1, meal_2 
                FROM {$wpdb->prefix}food_menu 
                WHERE month = %d AND day_of_month = %d",
                $month, $day
            ), ARRAY_A);
            
            // دریافت لیست کاربران و غذای انتخاب شده آن‌ها
            $user_orders = $wpdb->get_results($wpdb->prepare(
                "SELECT r.*, u.user_login, um1.meta_value as first_name, um2.meta_value as last_name 
                FROM {$wpdb->prefix}food_reservations r 
                JOIN {$wpdb->users} u ON r.user_id = u.ID 
                LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
                LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
                WHERE r.month = %d AND r.day_of_month = %d
                ORDER BY u.user_login",
                $month, $day
            ), ARRAY_A);
            
            if($menu || $orders || $user_orders): ?>
                <div class="orders-summary">
                    <h4>گزارش روز <?php echo $day; ?> <?php echo $persian_months[$month-1]; ?></h4>
                    
                    <?php if($menu): ?>
                    <div class="daily-menu">
                        <p><strong>منوی روز:</strong> <?php echo $menu['meal_1']; ?> - <?php echo $menu['meal_2']; ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>نام غذا</th>
                                <th>تعداد سفارش</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($orders): ?>
                                <?php foreach($orders as $order): ?>
                                <tr>
                                    <td><?php echo esc_html($order['meal_selected']); ?></td>
                                    <td><?php echo $order['count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2">هیچ سفارشی برای این روز ثبت نشده است</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <!-- جدول جدید برای نمایش لیست کاربران و غذای انتخاب شده -->
                    <?php if($user_orders): ?>
                    <h4 style="margin-top: 20px;">لیست کاربران و غذای انتخاب شده</h4>
                    <table class="user-orders-table">
                        <thead>
                            <tr>
                                <th>نام کاربری</th>
                                <th>نام و نام خانوادگی</th>
                                <th>غذای انتخاب شده</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($user_orders as $user_order): ?>
                                <tr>
                                    <td><?php echo esc_html($user_order['user_login']); ?></td>
                                    <td><?php echo esc_html($user_order['first_name'] . ' ' . $user_order['last_name']); ?></td>
                                    <td><?php echo esc_html($user_order['meal_selected']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- دکمه خروجی اکسل -->
                    <div style="margin-top: 20px;">
                        <form method="post">
                            <input type="hidden" name="kitchen_month" value="<?php echo $month; ?>">
                            <input type="hidden" name="kitchen_day" value="<?php echo $day; ?>">
                            <input type="hidden" name="export_excel" value="1">
                            <button type="submit" class="excel-export-btn">دریافت خروجی اکسل</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="no-orders">هیچ اطلاعاتی برای روز انتخابی یافت نشد</p>
            <?php endif;
        }
        ?>
    </div>
    <?php
    
    return ob_get_clean();
}
add_shortcode('kitchen_daily_orders', 'kitchen_daily_orders_report');

// تابع محاسبه روز هفته شمسی (اصلاح شده برای هماهنگی با جاوااسکریپت)
function get_persian_weekday($month, $day) {
    $start_day = 7; // جمعه (شنبه=1, ..., جمعه=7) - اصلاح شد
    $days_in_month = [31,31,31,31,31,31,30,30,30,30,30,29]; // تعداد روزهای ماه
    
    $total_days = 0;
    for ($i=1; $i<$month; $i++) $total_days += $days_in_month[$i-1];
    $total_days += $day - 1;
    
    $weekday = ($start_day + $total_days) % 7;
    $weekday = $weekday == 0 ? 7 : $weekday;
    
    $weekdays = ['شنبه','یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنج‌شنبه','جمعه'];
    return $weekdays[$weekday-1];
}

// مدیریت بارگذاری اسکریپت‌ها و استایل‌ها
function food_reservation_assets_manager() {
    // بارگذاری استایل در تمام صفحات برای اطمینان از نمایش صحیح
    wp_enqueue_style('food-reservation-style', plugin_dir_url(__FILE__) . 'style.css');

    // بررسی اینکه آیا شورت‌کد فرم رزرو استفاده شده است یا خیر
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'food_reservation')) {
        
        $user_id = get_current_user_id();
        $meals_data = [];
        if ($user_id) {
            global $wpdb;
            $meals_table = $wpdb->prefix . 'food_menu';
            $meals_data = $wpdb->get_results("SELECT * FROM $meals_table", ARRAY_A);
        }

        // دریافت تاریخ و ساعت فعلی
        $current_time = current_time('timestamp');
        $current_gregorian_year = date('Y', $current_time);
        $current_gregorian_month = date('m', $current_time);
        $current_gregorian_day = date('d', $current_time);
        $current_hour = date('H', $current_time);
        
        list($current_persian_year, $current_persian_month, $current_persian_day) = gregorian_to_persian($current_gregorian_year, $current_gregorian_month, $current_gregorian_day);

        wp_enqueue_script('food-reservation-script', plugin_dir_url(__FILE__) . 'food-reservation.js', ['jquery'], null, true);
        wp_localize_script('food-reservation-script', 'mealData', $meals_data);
        wp_localize_script('food-reservation-script', 'foodReservation', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'user_id' => $user_id,
            'currentDateTime' => [
                'persianYear' => $current_persian_year,
                'persianMonth' => $current_persian_month,
                'persianDay' => $current_persian_day,
                'hour' => $current_hour
            ]
        ]);
    }
}
add_action('wp_enqueue_scripts', 'food_reservation_assets_manager');

// فرم رزرو غذا
function food_reservation_form() {
    if (!is_user_logged_in()) {
        return '<p style="color:red;">برای رزرو غذا باید ابتدا وارد سیستم شوید.</p>';
    }

    if (isset($_POST['submit_reservation'])) {
        global $wpdb;
        $reservations_table = $wpdb->prefix . 'food_reservations';
        $user_id = get_current_user_id();
        $month = sanitize_text_field($_POST['month']);
        $days_in_month = [31,31,31,31,31,31,30,30,30,30,30,29];
        $max_days = $days_in_month[$month-1];
        
        // دریافت تاریخ و ساعت فعلی
        $current_time = current_time('timestamp');
        $current_gregorian_year = date('Y', $current_time);
        $current_gregorian_month = date('m', $current_time);
        $current_gregorian_day = date('d', $current_time);
        $current_hour = date('H', $current_time);
        list($current_persian_year, $current_persian_month, $current_persian_day) = gregorian_to_persian($current_gregorian_year, $current_gregorian_month, $current_gregorian_day);
        
        for ($day=1; $day<=$max_days; $day++) {
            if (isset($_POST["meal_selected_$day"])) {
                // بررسی آیا روز گذشته است یا خیر
                $is_past = false;
                if ($month < $current_persian_month) {
                    $is_past = true;
                } else if ($month == $current_persian_month) {
                    if ($day < $current_persian_day) {
                        $is_past = true;
                    } else if ($day == $current_persian_day && $current_hour >= 9) {
                        $is_past = true;
                    }
                }
                
                // اگر روز گذشته باشد، از ذخیره رزرو صرف نظر می‌کنیم
                if ($is_past) {
                    continue;
                }
                
                $meal_selected = sanitize_text_field($_POST["meal_selected_$day"]);
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $reservations_table 
                    WHERE user_id=%d AND month=%d AND day_of_month=%d",
                    $user_id, $month, $day
                ));
                if ($existing) {
                    $wpdb->update($reservations_table, 
                        ['meal_selected' => $meal_selected], 
                        ['id' => $existing->id]
                    );
                } else {
                    $wpdb->insert($reservations_table, [
                        'user_id' => $user_id,
                        'month' => $month,
                        'day_of_month' => $day,
                        'meal_selected' => $meal_selected
                    ]);
                }
            }
        }
        echo '<p style="color:green;">رزرو با موفقیت ثبت شد!</p>';
    }

    ob_start(); ?>
    <div class="food-reservation-form">
        <form method="POST">
            <p>
                <label for="month">ماه:</label>
                <select id="month" name="month" required>
                    <option value="">ماه را انتخاب کنید</option>
                    <?php
                    $persian_months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور',
                                      'مهر','آبان','آذر','دی','بهمن','اسفند'];
                    foreach ($persian_months as $index => $name) {
                        echo "<option value='".($index+1)."'>$name</option>";
                    }
                    ?>
                </select>
            </p>
            <div id="days-container"><!-- روزها اینجا نمایش داده می‌شوند --></div>
            <p><input type="submit" name="submit_reservation" value="ثبت رزرو"></p>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('food_reservation', 'food_reservation_form');

// شورت‌کد برای مشاهده رزروهای قبلی
function view_previous_reservations() {
    global $wpdb;
    $reservations_table = $wpdb->prefix . 'food_reservations';

    if (!is_user_logged_in()) {
        return '<p style="color:red;">برای مشاهده رزروهای قبلی باید ابتدا وارد سیستم شوید.</p>';
    }

    $user_id = get_current_user_id();

    if (isset($_POST['view_reservations'])) {
        $month = sanitize_text_field($_POST['month']);
        $reservations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $reservations_table 
            WHERE user_id=%d AND month=%d",
            $user_id, $month
        ), ARRAY_A);

        if ($reservations) {
            ob_start(); ?>
            <div class="previous-reservations">
                <h3>رزروهای قبلی شما برای ماه <?php echo $month; ?></h3>
                <table border="1" cellpadding="5" cellspacing="0">
                    <thead>
                        <tr>
                            <th>روز</th>
                            <th>غذای انتخاب شده</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $reservation): ?>
                            <tr>
                                <td><?php echo $reservation['day_of_month']; ?></td>
                                <td><?php echo $reservation['meal_selected']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
            return ob_get_clean();
        } else {
            return '<p style="color:red;">هیچ رزروی برای این ماه یافت نشد.</p>';
        }
    }

    ob_start(); ?>
    <div class="view-reservations-form">
        <form method="POST">
            <p>
                <label for="month">ماه:</label>
                <select id="month" name="month" required>
                    <option value="">ماه را انتخاب کنید</option>
                    <?php
                    $persian_months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور',
                                      'مهر','آبان','آذر','دی','بهمن','اسفند'];
                    foreach ($persian_months as $index => $name) {
                        echo "<option value='".($index+1)."'>$name</option>";
                    }
                    ?>
                </select>
            </p>
            <p><input type="submit" name="view_reservations" value="مشاهده رزروها"></p>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('view_previous_reservations', 'view_previous_reservations');

// شورت‌کد جدید برای ورود اطلاعات غذا (با چیدمان هفتگی)
function food_selection_form() {
    global $wpdb;
    $meals_table = $wpdb->prefix . 'food_menu';

    // بررسی دسترسی کاربر
    if (!current_user_can('manage_options')) {
        return '<p style="color:red;">شما دسترسی لازم برای ورود اطلاعات غذا را ندارید.</p>';
    }

    if (isset($_POST['save_meals'])) {
        $month = sanitize_text_field($_POST['month']);
        $days_in_month = [31,31,31,31,31,31,30,30,30,30,30,29];
        $max_days = $days_in_month[$month-1];
        
        // حذف رکوردهای قبلی برای این ماه
        $wpdb->query($wpdb->prepare("DELETE FROM $meals_table WHERE month = %d", $month));
        
        // ذخیره غذاهای جدید
        for ($i=1; $i<=$max_days; $i++) {
            $meal_1 = sanitize_text_field($_POST["meal_1_$i"]);
            $meal_2 = sanitize_text_field($_POST["meal_2_$i"]);
            
            if (!empty($meal_1) || !empty($meal_2)) {
                $wpdb->insert($meals_table, [
                    'month' => $month,
                    'day_of_month' => $i,
                    'meal_1' => $meal_1,
                    'meal_2' => $meal_2
                ]);
            }
        }
        echo '<div class="food-selection-success"><p>اطلاعات غذا با موفقیت ذخیره شد!</p></div>';
    }

    ob_start(); ?>
    <div class="food-selection-container">
        <div class="food-selection-header">
            <h2>ورود اطلاعات غذاهای ماهانه</h2>
            <p>در این بخش می‌توانید منوی غذایی هر ماه را وارد کنید.</p>
        </div>
        
        <form method="post" class="food-selection-form">
            <div class="food-selection-month">
                <label for="month">ماه:</label>
                <select id="month" name="month" required onchange="this.form.submit()">
                    <option value="">ماه را انتخاب کنید</option>
                    <?php
                    $persian_months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور',
                                      'مهر','آبان','آذر','دی','بهمن','اسفند'];
                    foreach ($persian_months as $index => $name) {
                        $selected = (isset($_POST['month']) && $_POST['month'] == ($index+1)) ? 'selected' : '';
                        echo "<option value='".($index+1)."' $selected>$name</option>";
                    }
                    ?>
                </select>
            </div>
            
            <?php
            $month = isset($_POST['month']) ? intval($_POST['month']) : 0;
            if ($month) {
                $days_in_month = [31,31,31,31,31,31,30,30,30,30,30,29];
                $max_days = $days_in_month[$month-1];
                
                // دریافت غذاهای ذخیره شده برای این ماه
                $meals = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $meals_table WHERE month = %d GROUP BY day_of_month ORDER BY day_of_month", $month
                ), ARRAY_A);
                $meals_by_day = [];
                foreach ($meals as $meal) $meals_by_day[$meal['day_of_month']] = $meal;
                
                // --- شروع منطق جدید برای چیدمان هفتگی ---
                $weekdays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
                $firstDayOfMonth_name = get_persian_weekday($month, 1);
                $firstDayOfMonth_index = array_search($firstDayOfMonth_name, $weekdays);

                $all_days_in_grid = [];
                // اضافه کردن خانه‌های خالی در ابتدای ماه
                for ($i = 0; $i < $firstDayOfMonth_index; $i++) {
                    $all_days_in_grid[] = ['is_empty' => true];
                }
                // اضافه کردن روزهای واقعی ماه
                for ($i = 1; $i <= $max_days; $i++) {
                    $all_days_in_grid[] = ['is_empty' => false, 'day_number' => $i];
                }

                echo '<div class="food-selection-days">';
                echo '<h3>منوی ماه ' . $persian_months[$month-1] . '</h3>';
                echo '<div class="food-selection-weekly-grid">'; // استفاده از یک کلاس جدید
                
                $total_cells = count($all_days_in_grid);
                for ($i = 0; $i < $total_cells; $i++) {
                    $cell_data = $all_days_in_grid[$i];
                    
                    // شروع یک ردیف جدید برای هر هفته
                    if ($i % 7 == 0) {
                        echo '<div class="day-row">';
                    }
                    
                    if ($cell_data['is_empty']) {
                        echo '<div class="day-container empty-day"></div>';
                    } else {
                        $day_num = $cell_data['day_number'];
                        $weekday = get_persian_weekday($month, $day_num);
                        
                        $meal_1 = $meals_by_day[$day_num]['meal_1'] ?? '';
                        $meal_2 = $meals_by_day[$day_num]['meal_2'] ?? '';
                        
                        $day_class = '';
                        if ($weekday === 'پنج‌شنبه' || $weekday === 'جمعه') {
                            $day_class = 'weekend-day';
                        }
                        
                        echo "<div class='food-selection-day $day_class'>";
                        echo "<div class='food-selection-day-header'>";
                        echo "<span class='day-number'>روز $day_num</span>";
                        echo "<span class='day-weekday'>($weekday)</span>";
                        echo "</div>";
                        echo "<div class='food-selection-meals'>";
                        echo "<div class='meal-input'>";
                        echo "<label for='meal_1_$day_num'>غذای اول:</label>";
                        echo "<input type='text' id='meal_1_$day_num' name='meal_1_$day_num' value='$meal_1' placeholder='نام غذای اول'>";
                        echo "</div>";
                        echo "<div class='meal-input'>";
                        echo "<label for='meal_2_$day_num'>غذای دوم:</label>";
                        echo "<input type='text' id='meal_2_$day_num' name='meal_2_$day_num' value='$meal_2' placeholder='نام غذای دوم'>";
                        echo "</div>";
                        echo "</div>";
                        echo "</div>";
                    }
                    
                    // پایان ردیف
                    if (($i + 1) % 7 == 0 || $i == $total_cells - 1) {
                        echo '</div>';
                    }
                }
                
                echo '</div>'; // .food-selection-weekly-grid
                echo '</div>'; // .food-selection-days
                
                echo '<div class="food-selection-actions">';
                echo '<input type="submit" name="save_meals" value="ذخیره اطلاعات غذا" class="food-selection-submit">';
                echo '</div>';
            }
            ?>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('food_selection', 'food_selection_form');

// Ajax handler
add_action('wp_ajax_get_user_reservations', 'get_user_reservations');
function get_user_reservations() {
    global $wpdb;
    $reservations_table = $wpdb->prefix . 'food_reservations';

    $month = intval($_POST['month'] ?? 0);
    $user_id = intval($_POST['user_id'] ?? 0);

    if ($month && $user_id) {
        $reservations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $reservations_table 
            WHERE user_id=%d AND month=%d",
            $user_id, $month
        ), ARRAY_A);
        wp_send_json_success($reservations);
    } else {
        wp_send_json_error('داده نامعتبر');
    }
}

// صفحه مدیریت تنظیمات غذا
function food_meal_settings_menu() {
    add_menu_page(
        'تنظیمات غذا',
        'غذا',
        'manage_options',
        'meal-settings',
        'food_meal_settings_page',
        'dashicons-admin-generic',
        25
    );

    add_submenu_page(
        'meal-settings',
        'گزارش رزرو غذا',
        'گزارش رزرو غذا',
        'manage_options',
        'food-reservation-report',
        'food_reservation_report_page'
    );
}
add_action('admin_menu', 'food_meal_settings_menu');

// صفحه تنظیمات غذا
function food_meal_settings_page() {
    global $wpdb;
    $meals_table = $wpdb->prefix . 'food_menu';

    if (isset($_POST['save_meals'])) {
        $month = sanitize_text_field($_POST['month']);
        $days_in_month = [31,31,31,31,31,31,30,30,30,30,30,29];
        $max_days = $days_in_month[$month-1];
        
        $wpdb->query($wpdb->prepare("DELETE FROM $meals_table WHERE month = %d", $month));
        for ($i=1; $i<=$max_days; $i++) {
            $meal_1 = sanitize_text_field($_POST["meal_1_$i"]);
            $meal_2 = sanitize_text_field($_POST["meal_2_$i"]);
            
            if (!empty($meal_1) || !empty($meal_2)) {
                $wpdb->insert($meals_table, [
                    'month' => $month,
                    'day_of_month' => $i,
                    'meal_1' => $meal_1,
                    'meal_2' => $meal_2
                ]);
            }
        }
        echo '<p style="color:green;">تنظیمات غذا با موفقیت به‌روزرسانی شد!</p>';
    }

    echo '<div class="wrap"><h1>تنظیمات غذاهای ماهانه</h1>';
    echo '<form method="post">';
    echo '<p><label for="month">ماه:</label>';
    echo '<select id="month" name="month" required onchange="this.form.submit()">';
    echo '<option value="">ماه را انتخاب کنید</option>';
    $persian_months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور',
                       'مهر','آبان','آذر','دی','بهمن','اسفند'];
    foreach ($persian_months as $index => $name) {
        $selected = (isset($_POST['month']) && $_POST['month'] == ($index+1)) ? 'selected' : '';
        echo "<option value='".($index+1)."' $selected>$name</option>";
    }
    echo '</select></p>';
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr><th>روز</th><th>غذای اول</th><th>غذای دوم</th></tr>';

    $month = isset($_POST['month']) ? intval($_POST['month']) : 0;
    if ($month) {
        $days_in_month = [31,31,31,31,31,31,30,30,30,30,30,29];
        $max_days = $days_in_month[$month-1];
        
        $meals = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $meals_table WHERE month = %d ORDER BY day_of_month", $month
        ), ARRAY_A);
        $meals_by_day = [];
        foreach ($meals as $meal) $meals_by_day[$meal['day_of_month']] = $meal;

        for ($i=1; $i<=$max_days; $i++) {
            $meal_1 = $meals_by_day[$i]['meal_1'] ?? '';
            $meal_2 = $meals_by_day[$i]['meal_2'] ?? '';
            echo "<tr><td>روز $i</td>";
            echo "<td><input type='text' name='meal_1_$i' value='$meal_1' required></td>";
            echo "<td><input type='text' name='meal_2_$i' value='$meal_2' required></td></tr>";
        }
    }
    echo '</table>';
    echo '<p><input type="submit" name="save_meals" value="ذخیره غذاها"></p>';
    echo '</form></div>';
}

// صفحه گزارش رزرو غذا
function food_reservation_report_page() {
    global $wpdb;
    $reservations_table = $wpdb->prefix . 'food_reservations';

    $month = isset($_POST['month']) ? intval($_POST['month']) : 0;
    $from_day = isset($_POST['from_day']) ? intval($_POST['from_day']) : 0;
    $to_day = isset($_POST['to_day']) ? intval($_POST['to_day']) : 0;

    echo '<div class="wrap"><h1>گزارش رزرو غذا</h1>';
    echo '<form method="post">';
    echo '<p><label for="month">ماه:</label>';
    echo '<select id="month" name="month" required>';
    echo '<option value="">ماه را انتخاب کنید</option>';
    $persian_months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور',
                       'مهر','آبان','آذر','دی','بهمن','اسفند'];
    foreach ($persian_months as $index => $name) {
        $selected = ($month == ($index+1)) ? 'selected' : '';
        echo "<option value='".($index+1)."' $selected>$name</option>";
    }
    echo '</select></p>';
    echo '<p><label for="from_day">از روز:</label>';
    echo '<select id="from_day" name="from_day">';
    echo '<option value="">از روز</option>';
    
    $days_in_month = [31,31,31,31,31,31,30,30,30,30,30,29];
    $max_days = $month ? $days_in_month[$month-1] : 31;
    
    for ($i=1; $i<=$max_days; $i++) {
        $selected = ($from_day == $i) ? 'selected' : '';
        echo "<option value='$i' $selected>روز $i</option>";
    }
    echo '</select>';
    echo '<label for="to_day">تا روز:</label>';
    echo '<select id="to_day" name="to_day">';
    echo '<option value="">تا روز</option>';
    for ($i=1; $i<=$max_days; $i++) {
        $selected = ($to_day == $i) ? 'selected' : '';
        echo "<option value='$i' $selected>روز $i</option>";
    }
    echo '</select>';
    echo '<input type="submit" value="فیلتر"></p>';
    echo '</form>';

    if ($month && $from_day && $to_day) {
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.user_login, um1.meta_value as first_name, um2.meta_value as last_name 
            FROM $reservations_table r 
            JOIN {$wpdb->users} u ON r.user_id = u.ID 
            LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
            LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
            WHERE r.month = %d AND r.day_of_month BETWEEN %d AND %d",
            $month, $from_day, $to_day
        ), ARRAY_A);

        $meal_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT meal_selected, COUNT(*) as count FROM $reservations_table 
            WHERE month = %d AND day_of_month BETWEEN %d AND %d 
            GROUP BY meal_selected",
            $month, $from_day, $to_day
        ), ARRAY_A);

        if ($results) {
            echo '<h3>جزئیات رزروها:</h3>';
            echo '<table border="1" cellpadding="5" cellspacing="0">';
            echo '<thead><tr><th>کاربر</th><th>نام</th><th>نام خانوادگی</th><th>روز</th><th>غذا</th></tr></thead>';
            echo '<tbody>';
            foreach ($results as $row) {
                echo "<tr>
                        <td>{$row['user_login']}</td>
                        <td>{$row['first_name']}</td>
                        <td>{$row['last_name']}</td>
                        <td>{$row['day_of_month']}</td>
                        <td>{$row['meal_selected']}</td>
                      </tr>";
            }
            echo '</tbody></table>';

            echo '<h3>تعداد کل غذاها:</h3>';
            echo '<table border="1" cellpadding="5" cellspacing="0">';
            echo '<thead><tr><th>غذا</th><th>تعداد</th></tr></thead>';
            echo '<tbody>';
            foreach ($meal_counts as $meal) {
                echo "<tr><td>{$meal['meal_selected']}</td><td>{$meal['count']}</td></tr>";
            }
            echo '</tbody></table>';
        } else {
            echo '<p>هیچ رزروی برای بازه زمانی انتخاب شده یافت نشد.</p>';
        }
    }
    echo '</div>';
}