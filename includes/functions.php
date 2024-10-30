<?php

function msf_api_get_content($domain, $user, $type_api, $body = null, $id_contact = 0, $file = 0, $upl_file = '')
{

    switch ($type_api) {
        case "contactfields":
            $url = 'https://' . $domain . '/api2/contact/field/list/';
            break;
        case "create_contact":
            $url = 'https://' . $domain . '/api2/contact/create/';
            break;
        case "update_contact":
            $url = 'https://' . $domain . '/api2/contact/' . $id_contact . '/profile/';
            break;
    }

    if (!empty($url) && !empty($user)) {
        if ($file == 0) {
            $args = array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => "Basic " . base64_encode($user)
                ),
                'body' => $body
            );
        } else {
            $local_file = $upl_file;
            $boundary = wp_generate_password(24);
            $payload = '';

            if ($local_file) {
                $payload .= '--' . $boundary;
                $payload .= "\r\n";
                $payload .= 'Content-Disposition: form-data; name="' . $body .
                    '"; filename="' . basename($local_file) . '"' . "\r\n";
                $payload .= "\r\n";
                $payload .= file_get_contents($local_file);
                $payload .= "\r\n";
            }
            $payload .= '--' . $boundary . '--';

            $args = array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => "Basic " . base64_encode($user),
                    'content-type' => 'multipart/form-data; boundary=' . $boundary,
                ),
                'body' => $payload
            );
        }

        if ($body) {
            $results = wp_remote_post($url, $args);
        } else {
            $results = wp_remote_get($url, $args);
        }

        if (!is_wp_error($results) && $results["response"]["code"] == 200) {
            $result = json_decode($results["body"]);
            return $result;
        }
    }
    return false;
}

function msf_get_talent_info($api_result)
{
    $talent_info = array();

    foreach ($api_result as $key => $field) {
        if (is_object($field)) {
            if ($field->choice) {
                $talent_info[$key] = $field->choice;
            } elseif ($field->choices) {
                $talent_info[$key] = $field->choices[0];
            }
        } else {
            if (!empty($field)) {
                $talent_info[$key] = $field;
            }
        }
    }
    return $talent_info;
}

function msf_delete_img_dir($path)
{
    if (is_dir($path) === true) {
        $files = array_diff(scandir($path), array('.', '..'));

        foreach ($files as $file) {
            msf_delete_img_dir(realpath($path) . '/' . $file);
        }

        return rmdir($path);
    } else if (is_file($path) === true) {
        return unlink($path);
    }

    return false;
}

function msf_get_editor_settings($msf_editor_name, $msf_editor_class)
{

    $editor_settings = array(
        'wpautop' => 1,
        'media_buttons' => 0,
        'textarea_name' => $msf_editor_name,
        'textarea_rows' => 7,
        'tabindex' => null,
        'editor_css' => '',
        'editor_class' => $msf_editor_class,
        'teeny' => 0,
        'dfw' => 0,
        'tinymce' => 1,
        'quicktags' => 1,
        'drag_drop_upload' => false
    );

    return $editor_settings;
}

$phpmailerInitAction = function (&$phpmailer) {
    global $contact_files;
    foreach ($contact_files as $fl_name => $upl_file) {
        $phpmailer->AddEmbeddedImage($upl_file['upload_file'], $upl_file['name']);
    }
};

function msf_build_email($form_fields, $contact_first_data, $contact_second_data, $contact_third_data, $contact_id)
{

    $contact_link = get_option('msf_domain', '') . '/contact/contact_show/' . $contact_id . '/';
    $contacl_fields = explode('&', $contact_second_data);
    $email_body = '
        <table style="max-width:100%;width:100%; font-size: 14px;" width="100%" border="0" cellpadding="0" cellspacing="0">
            <tbody>
                <tr><td style="text-align: center;">' . get_custom_logo() . '</td></tr>
                <tr><td style="line-height: 100px; vertical-align: center">' . $contact_link . '</td></tr>
                <tr><td>First Name: ' . $contact_first_data['name'] . '</td></tr>
                <tr><td>Last Name: ' . $contact_first_data['surname'] . '</td></tr>
                <tr><td>Email: ' . $contact_first_data['email'] . '</td></tr>';
    if (!empty($contact_first_data['phone'])) $email_body .= '<tr><td>Phone: ' . $contact_first_data['phone'] . '</td></tr>';
    foreach ($contacl_fields as $field) {
        $field_label = '';
        $field_value = '';
        $field_attrs = explode('=', $field);
        if ($field_attrs[1] === 'True') {
            $field_attrs[1] = 'Yes';
        }
        if ($field_attrs[1] === 'False') {
            $field_attrs[1] = 'No';
        }
        foreach ($form_fields as $fl) {
            if ($fl->name == $field_attrs[0]) {
                $field_label = $fl->label;
                if ($fl->type == 'AgeField') {
                    $field_attrs[1] = date("m/d/Y ", strtotime($field_attrs[1]));
                }

                if (empty($fl->config)) {
                    $field_value = $field_attrs[1];
                } else {
                    if (empty($fl->config[0])) {
                        $field_value = $field_attrs[1];
                    } else {
                        foreach ($fl->config[0] as $key => $conf) {
                            if ($key == $field_attrs[1]) {
                                $field_value = $conf;
                            }
                        }
                    }
                }
            }
        }
        if ($field_label != '') {
            $email_body .= '<tr><td>' . $field_label . ': ' . $field_value . '</td></tr>';
        }
    }
    $embed_files = '<tr><td><table><tbody><tr>';
    $attachments = array();
    if (!empty($contact_third_data)) {
        global $contact_files;
        $contact_files = $contact_third_data;
        foreach ($contact_third_data as $fl_name => $upl_file) {
            $email_body .= '<tr><td>' . $fl_name . ': ' . $upl_file['name'] . '</td></tr>';
            $embed_files .= '<td style="width:31%;max-width:188px;">
                <a target="_blank" href="' . $upl_file['url'] . '">';
            if (exif_imagetype($upl_file['upload_file'])) {
                $embed_files .= '<img style="width:180px; padding-top: 20px;" src="cid:' . $upl_file['name'] . '">';
            } else {
                $embed_files .= $upl_file['name'];
                $attachments[] = $upl_file['upload_file'];
            }
            $embed_files .= '</a></td>';
        }
    }
    $embed_files .= '</tr></tbody></table></td></tr>';
    $email_body .= $embed_files . '<tr><td style="text-align: center;">
            <span style="border-top: 1px solid #808080; margin-top: 50px; display: block; width: 100%;">
                <a href="https://modasphere.com/">Modasphere</a> by Casting Networks</span>
            </td></tr>
            </tbody>
        </table>';


    $build_email = array(
        'body' => $email_body,
        'attachments' => $attachments
    );

    return $build_email;
}

function msf_recursive_sanitize_text_field($array) {
    foreach ( $array as $key => &$value ) {
        if ( is_array( $value ) ) {
            $value = recursive_sanitize_text_field($value);
        }
        else {
            $value = sanitize_text_field( $value );
        }
    }

    return $array;
}

function msf_get_domain() {
    $protocols = array('https://', 'https://www.', 'http://', 'http://www.', 'www.');
    return str_replace($protocols, '', get_bloginfo('wpurl'));
}