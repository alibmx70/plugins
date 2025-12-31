jQuery(document).ready(function($) {
    $("#month").on("change", function() {
        let month = $("#month").val();
        let daysContainer = $("#days-container").empty();

        if (month) {
            $.ajax({
                url: foodReservation.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_user_reservations',
                    month: month,
                    user_id: foodReservation.user_id
                },
                success: function(response) {
                    let userReservations = response.data;
                    let daysInMonth = (month <= 6) ? 31 : 30;
                    
                    // محاسبه اولین روز ماه
                    const firstDayWeekday = getPersianWeekday(month, 1);
                    const weekdaysOrder = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
                    const firstDayIndex = weekdaysOrder.indexOf(firstDayWeekday);
                    
                    // محاسبه تعداد هفته‌ها
                    const totalWeeks = Math.ceil((daysInMonth + firstDayIndex) / 7);
                    
                    // دریافت تاریخ و ساعت فعلی از سرور
                    const currentDateTime = foodReservation.currentDateTime;
                    const currentPersianYear = currentDateTime.persianYear;
                    const currentPersianMonth = currentDateTime.persianMonth;
                    const currentPersianDay = currentDateTime.persianDay;
                    const currentHour = currentDateTime.hour;
                    
                    for (let week = 0; week < totalWeeks; week++) {
                        let rowHtml = `<div class="day-row">`;
                        
                        for (let dayOfWeek = 0; dayOfWeek < 7; dayOfWeek++) {
                            const dayNumber = (week * 7) + dayOfWeek - firstDayIndex + 1;
                            
                            if (dayNumber < 1 || dayNumber > daysInMonth) {
                                // خانه‌های خالی قبل و بعد از ماه
                                rowHtml += `<div class="day-container empty-day"></div>`;
                                continue;
                            }
                            
                            let weekday = getPersianWeekday(month, dayNumber);
                            let dayClass = '';
                            
                            if (weekday === 'پنج‌شنبه') {
                                dayClass = 'thursday-day';
                            } else if (weekday === 'جمعه') {
                                dayClass = 'friday-day';
                            }
                            
                            // بررسی آیا روز گذشته است یا خیر
                            let isPast = false;
                            if (month < currentPersianMonth) {
                                isPast = true;
                            } else if (month == currentPersianMonth) {
                                if (dayNumber < currentPersianDay) {
                                    isPast = true;
                                } else if (dayNumber == currentPersianDay && currentHour >= 9) {
                                    isPast = true;
                                }
                            }
                            
                            if (isPast) {
                                dayClass += ' past-day';
                            }
                            
                            let mealsForDay = mealData.filter(meal => 
                                meal.month == month && meal.day_of_month == dayNumber
                            );
                            
                            let userReservation = userReservations.find(res => 
                                res.day_of_month == dayNumber
                            );
                            
                            let mealOptions = mealsForDay.map(meal => `
                                <label>
                                    <input type="radio" name="meal_selected_${dayNumber}" 
                                        value="${meal.meal_1}" ${userReservation?.meal_selected === meal.meal_1 ? 'checked' : ''} ${isPast ? 'disabled' : ''}>
                                    ${meal.meal_1}
                                </label>
                                <label>
                                    <input type="radio" name="meal_selected_${dayNumber}" 
                                        value="${meal.meal_2}" ${userReservation?.meal_selected === meal.meal_2 ? 'checked' : ''} ${isPast ? 'disabled' : ''}>
                                    ${meal.meal_2}
                                </label>
                            `).join('');
                            
                            mealOptions += `
                                <label class="no-reservation">
                                    <input type="radio" name="meal_selected_${dayNumber}" 
                                        value="رزرو نمیکنم" ${userReservation?.meal_selected === "رزرو نمیکنم" ? 'checked' : ''} ${isPast ? 'disabled' : ''}>
                                    رزرو نمیکنم
                                </label>
                            `;
                            
                            rowHtml += `
                                <div class="day-container ${dayClass}">
                                    <div class="day-header">روز ${dayNumber} (${weekday})</div>
                                    ${isPast ? '<div class="reservation-expired">زمان رزرو تمام شده است</div>' : ''}
                                    <div class="meal-options">${mealOptions}</div>
                                </div>
                            `;
                        }
                        daysContainer.append(rowHtml + `</div>`);
                    }
                    
                    $(".meal-options label").on("dblclick", function() {
                        $(this).find("input[type='radio']").prop("checked", false);
                    });
                }
            });
        }
    });

    function getPersianWeekday(month, day) {
        const startDay = 7; // جمعه
        const daysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        
        let totalDays = day - 1;
        for (let i = 1; i < month; i++) {
            totalDays += daysInMonth[i - 1];
        }
        
        let weekday = (startDay + totalDays) % 7;
        weekday = weekday === 0 ? 7 : weekday;
        
        const weekdays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
        return weekdays[weekday - 1];
    }
});