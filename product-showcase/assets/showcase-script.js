jQuery(document).ready(function($) {

    $(document.body).on('click', '.ps-view-details', function() {
        var productId = $(this).data('id');
        var productData = ps_products_data[productId];
        
        if (productData && productData.gallery && productData.gallery.length > 0) {
            
            var galleryContainer = $('#ps-gallery-container');
            var modalBody = $('#ps-modal-body');

            // --- ساخت HTML گالری ---
            var galleryHtml = '<div class="ps-main-image-container">';
            galleryHtml += '<img id="ps-modal-main-image" src="' + productData.gallery[0] + '" alt="' + productData.title + '">';
            galleryHtml += '</div>';
            galleryHtml += '<div class="ps-thumbnails-container">';

            // ساخت بندانگشتی‌ها
            productData.gallery.forEach(function(imageUrl, index) {
                var activeClass = (index === 0) ? 'active' : '';
                galleryHtml += '<img class="ps-thumbnail ' + activeClass + '" src="' + imageUrl + '" data-full-src="' + imageUrl + '">';
            });

            galleryHtml += '</div>';
            
            // قرار دادن گالری در مودال
            galleryContainer.html(galleryHtml);

            // --- قرار دادن محتوای متنی ---
            var contentHtml = '<h2>' + productData.title + '</h2>';
            contentHtml += '<div class="ps-modal-body-content">' + productData.content + '</div>';
            modalBody.html(contentHtml);
            
            // --- نمایش مودال ---
            $('#ps-modal').fadeIn();
        } else {
            alert('برای این محصول تصویری یافت نشد.');
        }
    });

    // --- مدیریت کلیک روی بندانگشتی‌ها برای تعویض تصویر بزرگ ---
    $(document.body).on('click', '.ps-thumbnail', function() {
        var newImageUrl = $(this).data('full-src');
        
        // افکت محو شدن برای تعویض روان تصویر
        $('#ps-modal-main-image').fadeOut(200, function() {
            $(this).attr('src', newImageUrl).fadeIn(200);
        });
        
        // تغییر کلاس فعال به بندانگشتی کلیک شده
        $('.ps-thumbnail').removeClass('active');
        $(this).addClass('active');
    });

    // --- بستن مودال ---
    $(document.body).on('click', '.ps-close', function() {
        $('#ps-modal').fadeOut();
    });

    $(document.body).on('click', '#ps-modal', function(e) {
        if (e.target === this) {
            $('#ps-modal').fadeOut();
        }
    });
});