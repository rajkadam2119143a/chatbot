jQuery(function($) {
	// All code in here

    $('.search-field').autocomplete({
		source: function(request, response) {
			$.ajax({
				dataType: 'json',
				url: my_ajax_object.ajax_url,
				data: {
					term: request.term,
					action: 'autocompleteSearch',
					security: my_ajax_object.ajax_nonce,
				},
				success: function(data) {
					response(data);
				}
			});
		},
        select: function(event, ui) {
        	if(ui.item.add_data) {
        		$('#add_data').val(ui.item.add_data);
        	}else {
        		$('#add_data').val('');
        	}

        	if(ui.item.label) {
        		$('.search-field').val(ui.item.label);
        	}else {
        		$('.search-field').val('');
        	}

			if(ui.item.post_tags.length > 0) {
				var post_tags = '';
				Object.entries(ui.item.post_tags).forEach(([key, value]) => {
					if(key == ui.item.post_tags.length - 1 || ui.item.post_tags.length < 2) {
			          post_tags += value.name;
					}else {
					  post_tags += value.name + ',';
					}
			    });
				$('#post_tags').val(post_tags);
			}else {
				$('#post_tags').val('');
			}
			
			if(ui.item.post_category){
				$('#cat_search').val(ui.item.post_category);
			}else {
				$('#cat_search').val('');
			}
			
			if(ui.item.post_excerpt) {
				$('#post_excerpt').val(ui.item.post_excerpt);
			}else {
				$('#post_excerpt').val('');
			}
			
			if(ui.item.archive_html) {
				$('.archives').css('display','block');
				$('.archives').html(ui.item.archive_html);

				if($('.archives-sidebar').length > 0) {
					$('.archives-sidebar').css('display','none');
				}
			}else {
				$('.archives').css('display','none');
				$('.archives').html('');
			}
		},
	});
});