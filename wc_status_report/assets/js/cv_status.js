jQuery(document).ready(function ($) {

    $(document).on('click', '.button-cv_set-edit', function () {
        var rowKey = $(this).closest('.cv_set_email').data('key');
        $(this).val('Save');
        $(this).toggleClass('button-cv_set-save button-primary');
        $(this).closest('.cv_set_email').find('input[name=cv_email_'+rowKey+']').prop('disabled',false);
    });
	
	$(document).on('click', '.button-cv_set-delete', function () {
        var rowKey = $(this).closest('.cv_set_email').data('key');
        $.ajax({
            url: cv_status.ajax_url,
            type: 'POST',
            dataType: 'html',
            data: {action: 'cv_delete_item',
					key:rowKey},
            success: function (data) {
                if (data === "1") {
					$('.cv_set_email-'+rowKey).remove();
				}
            }
        });
    });
	$(document).on('click', '.button-cv_set-save', function () {
        var rowKey = $(this).closest('.cv_set_email').data('key');
		var this_obj = $(this);
        $.ajax({
            url: cv_status.ajax_url,
            type: 'POST',
            dataType: 'html',
            data: {action: 'cv_save_item',
					key:rowKey,
					value: $('input[name=cv_email_'+rowKey+']').val()},
            success: function (data) {
                if (data === "1") {
					this_obj.val('Edit');
					this_obj.removeClass('button-cv_set-save').removeClass('button-primary');
					this_obj.closest('.cv_set_email').find('input[type="text"]').prop('disabled',true);
				}
            }
        });
    });
	
	$(document).on('click', '.button-cv_add_new', function (e) {
	e.preventDefault();	
       var this_obj = $(this);
        $.ajax({
            url: cv_status.ajax_url,
            type: 'POST',
            dataType: 'html',
            data: {action: 'cv_add_item',
					value: $('input[name=cv_email_add]').val()},
            success: function (data) {
				$('input[name=cv_email_add]').val('');
				$('.email_array').html(data);
            }
        });
    });

    $(document).on('click', '.media-menu-item', function () {

        $.ajax({
            url: cv_status.ajax_url,
            type: 'POST',
            dataType: 'html',
            data: {action: 'check_quote'},
            success: function (data) {
                $('.enya-progressbarcontainer').html(data);
            }
        });

    });

});