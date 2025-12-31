jQuery(document).ready(function($) {

    // --- بخش مدیریت تب‌ها ---
    $('.msp-tab-link').on('click', function(e) {
        e.preventDefault();
        var targetId = $(this).attr('href');
        $('.msp-tab-link').removeClass('active');
        $('.msp-tab-content').removeClass('active');
        $(this).addClass('active');
        $(targetId).addClass('active');
    });

    // --- بخش اول: مدیریت فرم ورود AJAX ---
    $('#msp-ajax-login-form').on('submit', function(e) {
        e.preventDefault();
        var submitButton = $(this).find('input[type="submit"]');
        var messageDiv = $('#msp-login-message');
        submitButton.prop('disabled', true).val('در حال ورود...');
        messageDiv.removeClass('msp-error msp-success').html('');

        $.ajax({
            type: 'POST', url: msp_ajax.ajax_url,
            data: { action: 'msp_ajax_login', username: $('#msp_username').val(), password: $('#msp_password').val(), rememberme: $('#msp_rememberme').is(':checked'), security: msp_ajax.nonce },
            success: function(response) {
                if (response.success) {
                    messageDiv.addClass('msp-success').html(response.data.message);
                    setTimeout(function() { window.location.reload(); }, 1000);
                } else {
                    messageDiv.addClass('msp-error').html(response.data.message);
                    submitButton.prop('disabled', false).val('ورود');
                }
            },
            error: function() {
                messageDiv.addClass('msp-error').html('<strong>خطا:</strong> خطایی در ارتباط با سرور رخ داد.');
                submitButton.prop('disabled', false).val('ورود');
            }
        });
    });

    // --- بخش دوم: مدیریت جستجوی پیام‌ها ---
    var searchTimeout;
    $('#msp-search-input').on('input', function() {
        var searchTerm = $(this).val();
        clearTimeout(searchTimeout);
        $('.msp-table-container').html('<p class="msp-loading">در حال جستجو...</p>');
        searchTimeout = setTimeout(function() {
            $.ajax({
                type: 'POST', url: msp_ajax.ajax_url,
                data: { action: 'msp_search_messages', search_term: searchTerm, security: msp_ajax.nonce },
                success: function(response) {
                    if (response.success) { $('.msp-table-container').html(response.data.html); }
                    else { $('.msp-table-container').html('<p class="msp-no-messages">خطا در جستجو: ' + response.data + '</p>'); }
                },
                error: function() { $('.msp-table-container').html('<p class="msp-no-messages">خطا در ارتباط با سرور برای جستجو.</p>'); }
            });
        }, 500);
    });

    // --- بخش سوم: مدیریت مودال جزئیات پیام ---
    $(document.body).on('click', '.msp-view-btn', function(e) {
        e.preventDefault();
        var messageId = $(this).data('id');
        var $button = $(this);
        $button.prop('disabled', true).text('در حال بارگذاری...');

        $.ajax({
            url: msp_ajax.ajax_url, type: 'POST',
            data: { action: 'msp_get_message', message_id: messageId, security: msp_ajax.nonce },
            success: function(response) {
                $button.prop('disabled', false).text('مشاهده');
                if (response.success) {
                    $('#msp-modal-body').html(response.data);
                    $('#msp-modal').fadeIn();
                    $button.closest('tr').find('.msp-status').removeClass('status-new').addClass('status-read').text('خوانده شده');
                } else {
                    alert('خطایی در بارگذاری اطلاعات رخ داد: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                $button.prop('disabled', false).text('مشاهده');
                alert('خطا در ارتباط با سرور. وضعیت: ' + status + ', خطا: ' + error);
            }
        });
    });

    // --- بخش چهارم: بستن مودال ---
    $(document.body).on('click', '.msp-close', function() { $('#msp-modal').fadeOut(); });
    $(document.body).on('click', '#msp-modal', function(e) { if (e.target === this) { $('#msp-modal').fadeOut(); } });

    // --- بخش پنجم: مدیریت بایگانی ---
    $(document.body).on('click', '.msp-archive-btn', function(e) {
        e.preventDefault();
        if (!confirm('آیا از بایگانی این پیام مطمئن هستید؟')) { return; }
        var $button = $(this); var $row = $button.closest('tr'); var messageId = $button.data('id');
        $.ajax({
            url: msp_ajax.ajax_url, type: 'POST',
            data: { action: 'msp_archive_message', message_id: messageId, security: msp_ajax.nonce },
            success: function(response) {
                if (response.success) { $row.fadeOut(400, function() { $(this).remove(); }); }
                else { alert('خطا: ' + response.data); }
            },
            error: function() { alert('خطا در ارتباط با سرور.'); }
        });
    });

    // --- بخش ششم: مدیریت فیلتر بایگانی (تغییر یافته) ---
    $('#msp-filter-archive-btn').on('click', function() {
        var startDate = $('#msp-archive-start-date').val();
        var endDate = $('#msp-archive-end-date').val();
        
        $('#msp-archive-content .msp-table-container').html('<p class="msp-loading">در حال بارگذاری...</p>');
        
        $.ajax({
            type: 'POST', url: msp_ajax.ajax_url,
            data: { 
                action: 'msp_filter_archive', 
                start_date: startDate, 
                end_date: endDate, 
                security: msp_ajax.nonce 
            },
            success: function(response) {
                if (response.success) { 
                    $('#msp-archive-content .msp-table-container').html(response.data.html); 
                } else { 
                    $('#msp-archive-content .msp-table-container').html('<p class="msp-no-messages">خطا: ' + response.data + '</p>'); 
                }
            },
            error: function() { $('#msp-archive-content .msp-table-container').html('<p class="msp-no-messages">خطا در ارتباط با سرور.</p>'); }
        });
    });

    // دکمه ریست فیلتر بایگانی
    $('#msp-reset-archive-btn').on('click', function() {
        $('#msp-archive-start-date').val('');
        $('#msp-archive-end-date').val('');
        $('#msp-filter-archive-btn').trigger('click');
    });

    // --- بخش هفتم: مدیریت گزارش‌گیری ---
    $('#msp-generate-report-btn').on('click', function() {
        var reportData = { action: 'msp_generate_report', start_date: $('#msp-start-date').val(), end_date: $('#msp-end-date').val(), form_filter: $('#msp-form-filter').val(), security: msp_ajax.nonce };
        $('#msp-report-results-container').html('<p class="msp-loading">در حال تولید گزارش...</p>');
        $.post(msp_ajax.ajax_url, reportData, function(response) {
            if (response.success) { $('#msp-report-results-container').html(response.data.html); }
            else { $('#msp-report-results-container').html('<p class="msp-no-messages">خطا: ' + response.data + '</p>'); }
        }).fail(function() { $('#msp-report-results-container').html('<p class="msp-no-messages">خطا در ارتباط با سرور.</p>'); });
    });

    // --- بخش هشتم: مدیریت خروجی CSV ---
    $('#msp-export-csv-btn').on('click', function() {
        var reportData = { action: 'msp_export_report_csv', start_date: $('#msp-start-date').val(), end_date: $('#msp-end-date').val(), form_filter: $('#msp-form-filter').val(), security: msp_ajax.nonce };
        var $form = $('<form>', { 'method': 'POST', 'action': msp_ajax.ajax_url });
        $.each(reportData, function(key, value) { $('<input>', { 'type': 'hidden', 'name': key, 'value': value }).appendTo($form); });
        $form.appendTo('body').submit();
    });

    // --- بخش نهم: تقویم شمسی سفارشی ---
    (function() {
        var datePicker = {
            $picker: null,
            $input: null,
            currentYear: null,
            currentMonth: null,
            selectedDay: null,

            monthNames: ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'],
            weekDayNames: ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'],

            init: function() {
                // تغییر: اضافه کردن فیلدهای بایگانی به انتخاب‌گر
                $('body').on('click', '#msp-start-date, #msp-end-date, #msp-archive-start-date, #msp-archive-end-date', function(e) {
                    e.preventDefault();
                    datePicker.show($(this));
                });

                $(document).on('click', function(e) {
                    if (datePicker.$picker && !$(e.target).closest('.msp-custom-datepicker, #msp-start-date, #msp-end-date, #msp-archive-start-date, #msp-archive-end-date').length) {
                        datePicker.hide();
                    }
                });
            },

            show: function($input) {
                if (datePicker.$picker) datePicker.hide();

                datePicker.$input = $input;
                var date = datePicker.parseDate($input.val()) || datePicker.getToday();
                datePicker.currentYear = date.year;
                datePicker.currentMonth = date.month;
                datePicker.selectedDay = date.day;

                datePicker.create();
                datePicker.render();
                datePicker.position();
                datePicker.$picker.fadeIn(200);
            },

            hide: function() {
                if (datePicker.$picker) {
                    datePicker.$picker.hide();
                    datePicker.$input = null;
                }
            },

            create: function() {
                datePicker.$picker = $('<div class="msp-custom-datepicker"></div>').appendTo('body');
                datePicker.$picker.html(`
                    <div class="msp-datepicker-header">
                        <button type="button" class="msp-datepicker-nav msp-datepicker-prev">&lsaquo;</button>
                        <div class="msp-datepicker-title"></div>
                        <button type="button" class="msp-datepicker-nav msp-datepicker-next">&rsaquo;</button>
                    </div>
                    <table class="msp-datepicker-table">
                        <thead><tr></tr></thead>
                        <tbody></tbody>
                    </table>
                `);

                datePicker.$picker.on('click', '.msp-datepicker-prev', () => datePicker.changeMonth(-1));
                datePicker.$picker.on('click', '.msp-datepicker-next', () => datePicker.changeMonth(1));
                datePicker.$picker.on('click', '.msp-datepicker-day', (e) => {
                    var day = $(e.target).data('day');
                    datePicker.selectDate(day);
                });
            },

            render: function() {
                datePicker.$picker.find('.msp-datepicker-title').text(datePicker.monthNames[datePicker.currentMonth - 1] + ' ' + datePicker.currentYear);
                
                var $thead = datePicker.$picker.find('thead tr');
                $thead.empty();
                datePicker.weekDayNames.forEach(day => $thead.append(`<th>${day}</th>`));

                var $tbody = datePicker.$picker.find('tbody');
                $tbody.empty();
                
                var firstDayWeekday = datePicker.getDayOfWeek(datePicker.currentYear, datePicker.currentMonth, 1);
                var daysInMonth = datePicker.getDaysInMonth(datePicker.currentYear, datePicker.currentMonth);

                var dayCounter = 1;
                for (var i = 0; i < 6; i++) {
                    var $row = $('<tr></tr>');
                    for (var j = 0; j < 7; j++) {
                        if ((i === 0 && j < firstDayWeekday) || dayCounter > daysInMonth) {
                            $row.append('<td></td>');
                        } else {
                            var $day = $(`<td class="msp-datepicker-day" data-day="${dayCounter}">${dayCounter}</td>`);
                            if (dayCounter === datePicker.selectedDay) {
                                $day.addClass('is-selected');
                            }
                            $row.append($day);
                            dayCounter++;
                        }
                    }
                    $tbody.append($row);
                    if (dayCounter > daysInMonth) break;
                }
            },

            position: function() {
                var inputOffset = datePicker.$input.offset();
                datePicker.$picker.css({
                    top: inputOffset.top + datePicker.$input.outerHeight(),
                    left: inputOffset.left
                });
            },

            changeMonth: function(direction) {
                datePicker.currentMonth += direction;
                if (datePicker.currentMonth > 12) {
                    datePicker.currentMonth = 1;
                    datePicker.currentYear++;
                } else if (datePicker.currentMonth < 1) {
                    datePicker.currentMonth = 12;
                    datePicker.currentYear--;
                }
                datePicker.render();
            },

            selectDate: function(day) {
                datePicker.selectedDay = day;
                var dateString = datePicker.currentYear + '/' + String(datePicker.currentMonth).padStart(2, '0') + '/' + String(day).padStart(2, '0');
                datePicker.$input.val(dateString);
                datePicker.hide();
            },

            getDaysInMonth: function(year, month) {
                if (month <= 6) return 31;
                if (month <= 11) return 30;
                return datePicker.isLeapYear(year) ? 30 : 29;
            },

            isLeapYear: function(year) {
                var ary = [0, 1, 1, 12, 1, 1, 1, 1, 1, 1, 1, 1, 1];
                return (ary[year % 33] == 1);
            },

            getDayOfWeek: function(j_y, j_m, j_d) {
                var g_y = datePicker.toGregorian(j_y, j_m, j_d).y;
                var g_m = datePicker.toGregorian(j_y, j_m, j_d).m;
                var g_d = datePicker.toGregorian(j_y, j_m, j_d).d;
                var d = new Date(g_y, g_m - 1, g_d);
                return (d.getDay() + 1) % 7;
            },
            
            toGregorian: function(j_y, j_m, j_d) {
                var gy, gm, gd;
                var g_d_n, j_day_no;
                var j_y_b = j_y;
                if (j_y < 100) j_y_b += 1300;
                var g_y_b = j_y_b + 621;
                var days = g_y_b % 4;
                if (days > 1) {
                    g_d_n = (j_y_b - 1) * 365 + Math.floor((j_y_b - 1) / 4) + 78;
                    j_day_no = j_d + (j_m < 7 ? (j_m - 1) * 31 : (j_m - 7) * 30 + 186) + (j_y_b > 1159 ? 1 : 0);
                } else {
                    g_d_n = (j_y_b - 1) * 365 + Math.floor((j_y_b - 1) / 4);
                    j_day_no = j_d + (j_m < 7 ? (j_m - 1) * 31 : (j_m - 7) * 30 + 186);
                }
                g_d_n += g_y_b % 4 - 1;
                var g_a = g_d_n % 1461;
                if (g_a == 0) {
                    gy = g_y_b - 1 + Math.floor(g_d_n / 1461);
                    gm = 3;
                    gd = 29;
                } else {
                    gy = g_y_b - 1 + Math.floor(g_d_n / 1461);
                    g_a = g_d_n % 1461;
                    var g_b = g_a - 1;
                    var g_c = g_b % 365;
                    if (g_c == 0) {
                        gm = g_b / 365 + 3;
                        gd = 29;
                    } else {
                        gm = Math.floor(g_b / 365) + 3;
                        gd = g_c + 1;
                    }
                }
                return { y: gy, m: gm, d: gd };
            },

            parseDate: function(dateString) {
                if (!dateString) return null;
                var parts = dateString.split('/');
                if (parts.length === 3) {
                    return { year: parseInt(parts[0]), month: parseInt(parts[1]), day: parseInt(parts[2]) };
                }
                return null;
            },

            getToday: function() {
                return {
                    year: msp_ajax.persian_year,
                    month: msp_ajax.persian_month,
                    day: msp_ajax.persian_day
                };
            },

            toPersian: function(gy, gm, gd) {
                var g_d_n = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
                var jy = (gy > 1600) ? (979 + gy - 1600) : 0;
                var gy2 = (gy > 1600) ? (1600 + ((gy - 1600) % 2820)) : (621 + ((gy - 621) % 2820));
                var days = (365 * (gy - 1)) + Math.floor((gy - 1) / 4) - Math.floor((gy - 1) / 100) + Math.floor((gy - 1) / 400) + gd;
                for (var i = 0; i < gm - 1; i++) days += g_d_n[i];
                if (gm > 2 && ((gy % 100 != 0 && gy % 4 == 0) || (gy % 400 == 0))) days++;
                var j_day_no = days - 79;
                var j_np = Math.floor(j_day_no / 12053);
                j_day_no %= 12053;
                var jy1 = 979 + 33 * j_np + 4 * Math.floor(j_day_no / 1461);
                j_day_no %= 1461;
                if (j_day_no >= 366) {
                    jy1 += Math.floor((j_day_no - 1) / 365);
                    j_day_no = (j_day_no - 1) % 365;
                }
                var i = 0;
                for (; i < 11 && j_day_no >= 31 + (i % 2); i++) j_day_no -= 31 + (i % 2);
                var jm = i + 1;
                var jd = j_day_no + 1;
                return { year: jy + jy1, month: jm, day: jd };
            }
        };

        datePicker.init();
    })();

    // --- مدیریت پاپ‌آپ تایید قبل از فرم خدمات ---
    var $serviceForm = $('#service-form-wrapper');
    
    if ($serviceForm.length > 0) {
        // ساخت ساختار HTML مودال و تزریق آن به بدنه صفحه
        var modalHTML = `
            <div id="msp-service-confirmation-modal" class="msp-modal" style="display: flex;">
                <div class="msp-modal-content msp-confirmation-content">
                    <h3>تایید ثبت درخواست</h3>
                    <p>در صورت اطمینان از سلامت ظاهری و قرار داشتن دستگاه در محل مناسب نصب،با دراختیار داشتن کارت گارانتی نسبت به ثبت درخواست اقدام بفرمایید.</p>
                    <div class="msp-confirmation-actions">
                        <button id="msp-service-confirm-btn" class="button button-primary">تایید</button>
                    </div>
                </div>
            </div>
        `;
        
        // اضافه کردن مودال به صفحه
        $('body').append(modalHTML);

        // رویداد کلیک دکمه تایید
        $(document).on('click', '#msp-service-confirm-btn', function(e) {
            e.preventDefault();
            
            // محو شدن مودال
            $('#msp-service-confirmation-modal').fadeOut(300, function() {
                $(this).remove(); // حذف مودال از DOM پس از محو شدن
            });
            
            // نمایش فرم
            $serviceForm.fadeIn(500);
        });
    }

});