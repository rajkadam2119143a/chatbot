// ================= FILE UPLOAD =================

jQuery(document).ready(function ($) {

	var fileupload = $("#post_file");
	var fileupload1 = $("#post_file1");

	var filePath = $("#spnFilePath");
	var filePath1 = $("#spnFilePath1");

	var maxSize = 100 * 1024 * 1024;

	function isOfficeFile(ext) {
		return ['doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx'].includes(ext);
	}

	function isAllowedFile(ext) {
		return ['doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'pdf'].includes(ext);
	}

	// ================= OPEN FILE PICKER =================

	$("#imgFileUpload").click(function () {
		fileupload.trigger('click');
	});

	$("#imgFileUpload1").click(function () {
		fileupload1.trigger('click');
	});



	// ================= PRIMARY FILE =================

	fileupload.change(function () {

		let file = this.files[0];

		if (!file) return;

		let fileName = file.name;
		let extension = fileName.split('.').pop().toLowerCase();

		// INVALID FILE
		if (!isAllowedFile(extension)) {

			alert('Please choose PDF, Excel, Word, or PowerPoint files only.');

			$(this).val('');
			filePath.html('');

			return;
		}

		// SIZE VALIDATION
		if (file.size >= maxSize) {

			alert("Allowed file size exceeded. (Max. 100 MB)");

			$(this).val('');
			filePath.html('');

			return;
		}

		// SHOW FILE NAME
		filePath.html(fileName);


		// ================= OFFICE FILE =================

		if (isOfficeFile(extension)) {

			$('.mappwithdocpdf').show();

			$('#post_file1').prop('required', true);

		}

		// ================= PDF FILE =================

		else if (extension === 'pdf') {

			$('.mappwithdocpdf').hide();

			$('#post_file1').prop('required', false);

			$('#post_file1').val('');

			filePath1.html('');
		}
	});



	// ================= PDF FILE =================

	fileupload1.change(function () {

		let file = this.files[0];

		if (!file) return;

		let fileName = file.name;

		let extension = fileName.split('.').pop().toLowerCase();

		// ONLY PDF ALLOWED
		if (extension !== 'pdf') {

			alert('Please choose PDF file only.');

			$(this).val('');

			filePath1.html('');

			return;
		}

		// SIZE VALIDATION
		if (file.size >= maxSize) {

			alert("Allowed file size exceeded. (Max. 100 MB)");

			$(this).val('');

			filePath1.html('');

			return;
		}

		// SHOW PDF FILE NAME
		filePath1.html(fileName);

	});



	// ================= ADD POST VALIDATION =================

$('#new_post').submit(function () {

	// ONLY validate if upload field exists
	if ($('#post_file').length === 0) {
		return true;
	}

	let mainFile = $('#post_file').val();
	let pdfFile = $('#post_file1').val();

	// MAIN FILE REQUIRED
	if (!mainFile) {
		alert("Please select a file.");
		return false;
	}

	let extension = mainFile.split('.').pop().toLowerCase();

	// REQUIRE PDF FOR OFFICE FILES
	if (isOfficeFile(extension) && !pdfFile) {
		alert("Please upload the pdf version of the same file.");
		return false;
	}

	return true;
});


// ================= EDIT POST VALIDATION =================

$('#edit_post').submit(function () {

	// FORM NOT PRESENT
	if ($('#post_file').length === 0) {
		return true;
	}

	let mainFile = $('#post_file').val();
	let pdfFile = $('#post_file1').val();

	// ALLOW UPDATE WITHOUT NEW FILE
	if (!mainFile) {
		return true;
	}

	let extension = mainFile.split('.').pop().toLowerCase();

	// REQUIRE PDF FOR OFFICE FILES
	if (isOfficeFile(extension) && !pdfFile) {
		alert("Please upload the pdf version of the same file.");
		return false;
	}

	return true;
});
	//Go to Top
	var offset = 100;
	var speed = 250;
	var duration = 500;
	$(window).scroll(function () {
		if ($(this).scrollTop() < offset) {
			$('.topbutton').fadeOut(duration);
		} else {
			$('.topbutton').fadeIn(duration);
		}
	});

	$('.topbutton').on('click', function () {
		$('html, body').animate({ scrollTop: 0 }, speed);
		return false;
	});


	//Load More
	let currentPage = 1;
	$('#load-more').on('click', function (e) {
		e.preventDefault();
		currentPage++;
		// Do currentPage + 1, because we want to load the next page
		var excluded_ids = $('#load-more').attr('data-exclude');
		$.ajax({
			type: 'POST',
			url: my_ajax_object.ajax_url,
			dataType: 'json',
			data: {
				action: 'weichie_load_more',
				paged: currentPage,
				exclude: excluded_ids,
				//security: my_ajax_object.ajax_nonce,
			},
			success: function (res) {
				if (currentPage >= res.max || res.max == 0) {
					$('.post-listing').append("<div>No more posts found.</div>");
					$('#load-more').hide();
				} else {
					$('.post-listing').append(res.html).fadeIn('slow');
				}
			}
		});
	});


	$('.archive-post').click(function (e) {
		if (confirm("Are you sure you want to archive this policy?")) {
			e.preventDefault();
			var this_post = $(this);
			var slug = $(this).data('slug') || '';
			if (!slug) {
				var slug_href = $(this).attr('href');
				slug = slug_href.substring(slug_href.lastIndexOf('?slug=') + 6);
			}

			$.ajax({
				type: 'POST',
				url: my_ajax_object.ajax_url,
				dataType: 'json',
				data: {
					action: 'archive_post',
					slug: slug,
					//security: my_ajax_object.ajax_nonce,
				},
				success: function (res, xhr) {
					if (res != 0) {
						alert("Policy archived successfully");
						this_post.closest('.formarginTop1').fadeOut('slow');
					} else {
						alert("Policy archive failed. Please try again after sometime.");
					}
				}
			});
		} else {
			return false;
		}
	})

	$('.restore-post').click(function (e) {
		if (confirm("Restore this policy from archives?")) {
			e.preventDefault();
			var this_post = $(this);
			var slug = $(this).data('slug') || '';
			if (!slug) {
				var slug_href = $(this).attr('href');
				slug = slug_href.substring(slug_href.lastIndexOf('?slug=') + 6);
			}

			$.ajax({
				type: 'POST',
				url: my_ajax_object.ajax_url,
				dataType: 'json',
				data: {
					action: 'restore_post',
					slug: slug,
				},
				success: function (res, xhr) {
					if (res != 0) {
						alert("Policy restored successfully");
						this_post.closest('.formarginTop').fadeOut('slow');
					} else {
						alert("Policy restore failed. Please try again after sometime.");
					}
				}
			});
		} else {
			return false;
		}
	})

	$(".p3-comment-moderation").click(function () {
		var this_click = $(this);
		//console.log(this_click.html());
		comment_id = $(this).attr("data-comment_id");
		//nonce = $(this).attr("data-nonce")

		$.ajax({
			type: "POST",
			dataType: "json",
			url: my_ajax_object.ajax_url,
			data: { action: "p3_comment_approve", comment_id: comment_id },
			success: function (response) {
				//console.log(response);
				if (response.type == "success") {
					alert("Comment approved successfully!");
					this_click.prev('em').html('');
					this_click.html('');
				}
				else {
					alert("Something went wrong.");
					window.location.reload();
				}
			}
		})

	})

	$('.input-group.date').datepicker({ format: "dd/mm/yyyy" });

	$('[data-bs-toggle="collapse"]').click(function () {
		var $span = $(this).find('span'); // Select the <span> element
		if ($span.hasClass('sidebar-icon-open')) {
			$span.removeClass('sidebar-icon-open').addClass('sidebar-icon-close');
			$span.html('<i class="fa fa-angle-down"></i>'); // Update the icon
		} else if ($span.hasClass('sidebar-icon-close')) {
			$span.removeClass('sidebar-icon-close').addClass('sidebar-icon-open');
			$span.html('<i class="fa fa-angle-up"></i>'); // Update the icon
		}
	});
	
	
	/*Comment Section - Close the Thank you message box on click of the cross icon*/
    $(".alert .close").click(function(e) {
        e.preventDefault(); // Prevent default anchor behavior
        $(this).closest(".alert").fadeOut("slow", function() {
            $(this).remove(); // Remove the alert div after fadeOut
        });
    });
 

});