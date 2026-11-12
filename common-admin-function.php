<?php


//--------------------------------Add policy----------------------------------------------

function validate_policy_file($file) {

    $allowed_mime_types = array(
        'application/pdf',
        'application/msword',
        'application/vnd.ms-excel',
        'text/csv',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );

    $filetype = wp_check_filetype_and_ext(
        $file['tmp_name'],
        $file['name']
    );

    if (
        empty($filetype['type']) ||
        !in_array($filetype['type'], $allowed_mime_types)
    ) {
        return 'Invalid file type. Please upload PDF, DOC, DOCX, XLS, XLSX or CSV only.';
    }

    if ($file['size'] > 4194304) {
        return 'File too big. Please upload file size less than 4 MB only.';
    }

    return true;
}

function alert($type, $message) {
    $type_class = strtolower($type) == 'error' ? 'alert-warning' : 'alert-success';
    return '<div class="alert ' . $type_class . ' alert-dismissible" role="alert">'
        . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
        . '<strong>' . ucfirst($type) . '!</strong> ' . $message . '</div>';
}


function wpshout_save_post_if_submitted() {
    if (!isset($_POST['title']) || !isset($_POST['post_status'])) return;

    if (!wp_verify_nonce($_POST['_wpnonce'], 'wps-frontend-post')) {
        echo alert('Error', 'Invalid form submission.');
        return;
    }
    if (strlen($_POST['title']) < 3) {
        echo alert('Error', 'Please enter a title. Titles must be at least three characters long.');
        return;
    }
    if (isset($_POST['cat']) && $_POST['cat'] == '') {
        echo alert('Error', 'Please select a category.');
        return;
    }
    if ((!isset($_FILES['post_file']) || $_FILES['post_file']['size'] == 0) && (!isset($_FILES['post_file1']) || $_FILES['post_file1']['size'] == 0)) {
        echo alert('Error', 'No file selected. Please select at least one file.');
        return;
    }
    // Validate both files if present
    foreach (['post_file', 'post_file1'] as $file_key) {
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['size'] > 0) {
            $validation = validate_policy_file($_FILES[$file_key]);
            if ($validation !== true) {
                echo alert('Error', $validation);
                return;
            }
        }
    }

    $post_status_val = ($_POST['post_status'] == 'public') ? 'publish' : 'private';
    $post = array(
        'post_title'    => $_POST['title'],
        'post_category' => array($_POST['cat']),
        'tags_input'    => $_POST['post_tags'],   // comma-separated string — correct
        'post_status'   => $post_status_val,
        'post_type'     => 'post',
        'page_template' => 'single-policy.php',
        'post_excerpt'  => $_POST['post_excerpt'],
    );
    $post_id = wp_insert_post($post);

    if (isset($_POST['post_policy_date'])) {
        update_post_meta($post_id, 'post_policy_date', $_POST['post_policy_date']);
    }

    // Document creation and first revision(s)
    if ((isset($_FILES['post_file']) && $_FILES['post_file']['size'] > 0) || (isset($_FILES['post_file1']) && $_FILES['post_file1']['size'] > 0)) {
        $doc = array(
            'post_title'   => $_POST['title'],
            'post_status'  => 'publish',
            'post_content' => '',
            'post_excerpt' => 'Frontend import',
            'post_type'    => 'document',
        );
        $doc_id = wp_insert_post($doc);
        $urls = [];
        foreach (['post_file', 'post_file1'] as $file_index => $file_key) {
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['size'] > 0 && $_FILES[$file_key]['error'] == 0) {
                // Rename file in $_FILES directly so media_handle_upload picks it up
                $extension = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
                $_FILES[$file_key]['name'] = md5($_FILES[$file_key]['name'] . microtime()) . '.' . $extension;

                $attach_id = media_handle_upload($file_key, $doc_id);
                if ($attach_id == false) wp_die('Error to load Attachment');
                $attachment_url = wp_get_attachment_url($attach_id);
                $urls[$file_key] = $attachment_url;
                if ($file_index === 0) {
                    wp_update_post(array(
                        'ID'           => $doc_id,
                        'post_content' => '<!-- WPDR ' . (int) $attach_id . ' -->',
                    ));
                }
            }
        }
        update_post_meta($post_id, 'document_revisions_details', $doc_id);
        // Download link = primary upload (post_file), else the PDF companion
        $download_src = isset($urls['post_file']) ? $urls['post_file'] : (isset($urls['post_file1']) ? $urls['post_file1'] : '');
        if ($download_src) {
            update_post_meta($post_id, 'policy_download_url', str_replace(site_url(), '', $download_src));
        }
        if (isset($urls['post_file1'])) {
            update_post_meta($post_id, 'doc_to_pdf', str_replace(site_url(), '', $urls['post_file1']));
        } elseif (isset($urls['post_file'])) {
            $ext = strtolower(pathinfo(parse_url($urls['post_file'], PHP_URL_PATH), PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                update_post_meta($post_id, 'doc_to_pdf', str_replace(site_url(), '', $urls['post_file']));
            }
        }
    }

    echo alert('Success', 'Policy has been uploaded!');
}

//--------------------------------Edit Policy----------------------------------------------

function wpshout_edit_save_post_if_submitted() {

    if (
        !isset($_POST['title']) ||
        !isset($_POST['add_data']) ||
        !isset($_POST['post_status'])
    ) {
        return;
    }

    // NONCE
    if (!wp_verify_nonce($_POST['_wpnonce'], 'wps-frontend-post-edit')) {

        echo alert('Error', 'Invalid form submission.');
        return;
    }

    // TITLE
    if (strlen(trim($_POST['title'])) < 3) {

        echo alert('Error', 'Please enter valid title.');
        return;
    }

    // FIND POLICY
    $post_details = get_post_id_by_name(
        sanitize_text_field($_POST['add_data']),
        'post'
    );

    if (!$post_details) {

        echo alert('Error', 'Policy not found.');
        return;
    }

    $post_id = $post_details->ID;

    // STATUS
    $post_status_val =
        ($_POST['post_status'] == 'public')
        ? 'publish'
        : 'private';

    // UPDATE POLICY
    $post = array(

        'ID'            => $post_id,
        'post_title'    => sanitize_text_field($_POST['title']),
        'post_category' => array($_POST['cat']),
        'tags_input'    => sanitize_text_field($_POST['post_tags']),
        'post_type'     => 'post',
        'post_status'   => $post_status_val,
        'page_template' => 'single-policy.php',
        'post_excerpt'  => sanitize_textarea_field($_POST['post_excerpt']),
    );

    $updated_id = wp_update_post($post, true);

    if (is_wp_error($updated_id)) {

        echo alert('Error', $updated_id->get_error_message());
        return;
    }

    // POLICY DATE
    if (isset($_POST['post_policy_date'])) {

        update_post_meta(
            $post_id,
            'post_policy_date',
            sanitize_text_field($_POST['post_policy_date'])
        );
    }

    // VALIDATE FILES
    foreach (['post_file', 'post_file1'] as $file_key) {

        if (
            isset($_FILES[$file_key]) &&
            $_FILES[$file_key]['size'] > 0
        ) {

            $validation = validate_policy_file($_FILES[$file_key]);

            if ($validation !== true) {

                echo alert('Error', $validation);
                return;
            }
        }
    }

    // CHECK NEW FILE
    $has_new_upload = false;

    foreach (['post_file', 'post_file1'] as $file_key) {

        if (
            isset($_FILES[$file_key]) &&
            $_FILES[$file_key]['size'] > 0 &&
            $_FILES[$file_key]['error'] == 0
        ) {

            $has_new_upload = true;
        }
    }

    // NO FILE
    if (!$has_new_upload) {

        echo alert('Success', 'Updated successfully!');
        return;
    }

    // GET DOCUMENT
    $doc_id = get_post_meta(
        $post_id,
        'document_revisions_details',
        true
    );

    // CREATE DOCUMENT IF NOT EXISTS
    if (!$doc_id || get_post_type($doc_id) != 'document') {

        $doc = array(

            'post_title'   => sanitize_text_field($_POST['title']),
            'post_status'  => 'publish',
            'post_content' => '',
            'post_excerpt' => 'Frontend import',
            'post_type'    => 'document',
        );

        $doc_id = wp_insert_post($doc);

        update_post_meta(
            $post_id,
            'document_revisions_details',
            $doc_id
        );
    }

    // LOAD WP FILES
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $latest_attach_id = 0;
    $latest_pdf_url   = '';
    $latest_download_url = '';

    foreach (['post_file', 'post_file1'] as $file_key) {

        if (
            isset($_FILES[$file_key]) &&
            $_FILES[$file_key]['size'] > 0 &&
            $_FILES[$file_key]['error'] == 0
        ) {

            // EXTENSION
            $extension = strtolower(
                pathinfo(
                    $_FILES[$file_key]['name'],
                    PATHINFO_EXTENSION
                )
            );

            // SAFE UNIQUE NAME
            $new_filename =
                sanitize_title($_POST['title'])
                . '-revision-'
                . current_time('timestamp')
                . '-'
                . wp_generate_password(5, false, false)
                . '.'
                . $extension;

            $_FILES[$file_key]['name'] = $new_filename;

            // IMPORTANT FIX
            add_filter('upload_dir', function($dirs) {

                $current_year  = date('Y');
                $current_month = date('m');

                $dirs['subdir'] = '/' . $current_year . '/' . $current_month;
                $dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
                $dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];

                return $dirs;
            });

            // UPLOAD
            $attach_id = media_handle_upload(
                $file_key,
                $doc_id
            );

            remove_all_filters('upload_dir');

            // ERROR
            if (is_wp_error($attach_id)) {

                echo alert(
                    'Error',
                    $attach_id->get_error_message()
                );

                return;
            }

            // SAVE LATEST
            $latest_attach_id = $attach_id;

            // FILE URL
            $attachment_url = wp_get_attachment_url($attach_id);

            // DOWNLOAD FILE = primary upload (post_file), else the PDF companion
            if (empty($latest_download_url)) {
                $latest_download_url = str_replace(site_url(), '', $attachment_url);
            }

            // PDF SAVE
            if ($extension === 'pdf') {

                $latest_pdf_url = str_replace(
                    site_url(),
                    '',
                    $attachment_url
                );
            }
        }
    }

    // UPDATE DOCUMENT
    if ($latest_attach_id) {

        wp_update_post(array(

            'ID'           => $doc_id,
            'post_title'   => sanitize_text_field($_POST['title']),
            'post_content' => '<!-- WPDR ' . (int)$latest_attach_id . ' -->',
        ));

        // IMPORTANT FOR WPDR
        update_post_meta(
            $doc_id,
            '_wpdr_document',
            $latest_attach_id
        );
    }

    // UPDATE DOWNLOAD META (always points at the newest upload)
    if (!empty($latest_download_url)) {
        update_post_meta($post_id, 'policy_download_url', $latest_download_url);
    }

    // UPDATE PDF META
    if (!empty($latest_pdf_url)) {

        update_post_meta(
            $post_id,
            'doc_to_pdf',
            $latest_pdf_url
        );
    }

    // CLEAR CACHE
    clean_post_cache($post_id);
    clean_post_cache($doc_id);

    echo alert('Success', 'Updated your post successfully!');
}