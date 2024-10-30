<?php
include_once 'functions.php';
$set_domain = get_option('msf_domain', '');
$user_api = get_option('msf_user_api', '');

$msf_name = get_query_var('msf_name');
$msf_forms = json_decode(get_option('msf_forms', ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recaptcha_response'])) {

    $msf_recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
    $msf_recaptcha_secret = get_option('msf_secret_key', '');
    $msf_recaptcha_response = sanitize_text_field($_POST['recaptcha_response']);
    $msf_recaptcha = wp_remote_post($msf_recaptcha_url . '?secret=' . $msf_recaptcha_secret . '&response=' . $msf_recaptcha_response);

    if (!is_wp_error($msf_recaptcha) && $msf_recaptcha["response"]["code"] == 200) {
        $msf_recaptcha = json_decode($msf_recaptcha["body"]);
    }


    if ($msf_recaptcha->score >= 0.5) {
        $sb_first_name = sanitize_text_field($_POST['first_name']);
        $sb_last_name = sanitize_text_field($_POST['last_name']);
        $sb_email = sanitize_text_field($_POST['email']);

        $contact_first_send_data = array(
            'submitted' => '1',
            'name' => $sb_first_name,
            'surname' => $sb_last_name,
            'email' => $sb_email
        );

        if (isset($_POST['phone'])) {
            $sb_phone = (string)filter_var($_POST['phone'], FILTER_SANITIZE_NUMBER_INT);
            $contact_first_send_data['phone'] = $sb_phone;
        }

        $create_talent = msf_api_get_content($set_domain, $user_api, 'create_contact', $contact_first_send_data);
        if ($create_talent) {
            $home_path = wp_get_upload_dir();
            if (!is_dir($home_path['basedir'] . "/msf_talent_img/" . $create_talent->id)) {
                mkdir($home_path['basedir'] . "/msf_talent_img/" . $create_talent->id);
            }

            $success_message = 'Talent created!';
            $form_fields = '';
            $contact_second_send_data = '';
            $contact_third_send_data = array();

            foreach ($msf_forms as $form) {
                $id_form = str_replace(' ', '-', strtolower($form->name));
                if ($id_form == $msf_name) {
                    $form_fields = $form->fields;
                    foreach ($form_fields as $field) {
                        if ($field->id == 'df01' || $field->id == 'df02' || $field->id == 'df03' || $field->id == 'df04') {
                            continue;
                        } else {
                            if (isset($_POST[$field->name])) {
                                $custom_field = msf_recursive_sanitize_text_field($_POST[$field->name]);
                                if (is_array($custom_field)) {
                                    foreach ($custom_field as $multi_f) {
                                        if ($contact_second_send_data == '') {
                                            $contact_second_send_data .= $field->name . '=' . $multi_f;
                                        } else {
                                            $contact_second_send_data .= '&' . $field->name . '=' . $multi_f;
                                        }
                                    }
                                } else {
                                    if ($contact_second_send_data == '') {
                                        $contact_second_send_data .= $field->name . '=' . $custom_field;
                                    } else {
                                        $contact_second_send_data .= '&' . $field->name . '=' . $custom_field;
                                    }
                                }
                            }
                            if (isset($_FILES[$field->name])) {
//                                $home_path = wp_get_upload_dir();
                                $upload_dir = $home_path['basedir'] . "/msf_talent_img/" . $create_talent->id . "/";
                                $upload_file = $upload_dir . basename($_FILES[$field->name]['name']);
                                if (move_uploaded_file($_FILES[$field->name]['tmp_name'], $upload_file)) {
                                    $contact_third_send_data[$field->name]['upload_file'] = $upload_file;
                                    $contact_third_send_data[$field->name]['name'] = basename($upload_file);
                                    $contact_third_send_data[$field->name]['url'] = '';
                                }
                            }
                        }
                    }
                    $subject_message = $form->name;
                    $success_message = $form->success;
                    $email_notification = $form->email;
                    $from_email = $form->from_email;
                }
            }

            $update_profile = msf_api_get_content($set_domain, $user_api, 'update_contact', $contact_second_send_data, $create_talent->id);

            if (!empty($contact_third_send_data)) {
                foreach ($contact_third_send_data as $fl_name => $fl) {
                    $update_profile_file = (array)msf_api_get_content($set_domain, $user_api, 'update_contact', $fl_name, $create_talent->id, 1, $fl['upload_file']);
                    if (!empty($update_profile_file[$fl_name])) {
                        $contact_third_send_data[$fl_name]['url'] = $update_profile_file[$fl_name]->url;
                    }
                }
            }

            if ($update_profile) {
                ?>
                <div class="alert alert-primary" role="alert">
                    <?= $success_message ?>
                </div>
                <?php
                if (isset($email_notification) && isset($subject_message) && isset($success_message)) {
                    $build_email = msf_build_email($form_fields, $contact_first_send_data, $contact_second_send_data, $contact_third_send_data, $create_talent->id);
                    if(empty($from_email)) {
                        $headers[] = 'Content-type: text/html; charset=utf-8';
                    }else{
                        $blog_title = get_bloginfo();
                        $headers = array(
                            'From: ' . $blog_title . ' <' . $from_email . '>',
                            'content-type: text/html charset=utf-8'
                        );
                    }
                    wp_mail($email_notification, $subject_message, $build_email['body'], $headers, $build_email['attachments']);
                }
            }
            msf_delete_img_dir($home_path['basedir'] . '/msf_talent_img/' . $create_talent->id);
        }
    } else {
        ?>
        <div class="alert alert-danger" role="alert">
            reCAPTCHA Error!<br>
        </div>
        <?php
    }
}


foreach ($msf_forms as $form) {
    $id_form = str_replace(' ', '-', strtolower($form->name));
    if ($id_form == $msf_name) {
        $success = $form->success;
        ?>
        <div class="container submission-header">
            <h2><?= $form->name ?></h2>
        </div>
        <div class="container submission-description">
            <p><?= wp_unslash($form->description) ?></p>
        </div>
        <div class="container submission-form">
            <form method="post" id="msf-form" enctype="multipart/form-data">
                <?php
                for ($i = 1; $i <= count($form->fields); $i++) {
                    foreach ($form->fields as $field) {
                        if ($field->sort == $i) {
                            ?>
                            <div class="form-group row">
                                <label for="field-<?= $field->id ?>"
                                       class="col-sm-2 col-form-label"><?= $field->label ?></label>
                                <div class="col-sm-5">
                                    <?php
                                    switch ($field->type) {
                                        case 'ChoiceField':
                                            ?>
                                            <select class="form-control"
                                                    id="field-<?= $field->id ?>"
                                                    name="<?= $field->name ?>" <?= $field->required ?>
                                                    aria-describedby="HelpBlock-<?= $field->id ?>">
                                                <option value=""></option>
                                                <?php
                                                foreach ($field->config[0] as $key => $option) {
                                                    ?>
                                                    <option value="<?= $key ?>"><?= $option ?></option>
                                                    <?php
                                                }
                                                ?>
                                            </select>
                                            <?php
                                            break;
                                        case 'MultiChoiceField':
                                            ?>
                                            <select multiple class="form-control selectpicker"
                                                    id="field-<?= $field->id ?>"
                                                    name="<?= $field->name ?>[]" <?= $field->required ?>>
                                                <option value=""></option>
                                                <?php
                                                foreach ($field->config[0] as $key => $option) {
                                                    ?>
                                                    <option value="<?= $key ?>"><?= $option ?></option>
                                                    <?php
                                                }
                                                ?>
                                            </select>
                                            <?php
                                            break;
                                        case 'NullBooleanField':
                                            ?>
                                            <select class="form-control"
                                                    id="field-<?= $field->id ?>"
                                                    name="<?= $field->name ?>" <?= $field->required ?>>
                                                <option value="True">Yes</option>
                                                <option value="False" selected>No</option>
                                            </select>
                                            <?php
                                            break;
                                        case 'AgeField':
                                            echo '<input type="date" class="form-control" id="field-' . $field->id . '" name="' . $field->name . '" ' . $field->required . '>';
                                            break;
                                        case 'CharField':
                                            echo '<input type="text" class="form-control" id="field-' . $field->id . '" name="' . $field->name . '" ' . $field->required . '>';
                                            break;
                                        case 'EmailField':
                                            echo '<input type="email" class="form-control" id="field-' . $field->id . '" name="' . $field->name . '" ' . $field->required . '>';
                                            break;
                                        case 'PhoneField':
                                            echo '<input type="tel" class="form-control" id="field-' . $field->id . '" name="' . $field->name . '" ' . $field->required . '>';
                                            break;
                                        case 'StorageField':
                                            echo '<input type="file" class="form-control-file" id="field-' . $field->id . '" name="' . $field->name . '" ' . $field->required . '>';
                                            break;
                                        case 'ImageStorageField':
                                            echo '<input type="file" class="form-control-file" id="field-' . $field->id . '" name="' . $field->name . '" ' . $field->required . '>';
                                            break;
                                        case 'DateField':
                                            echo '<input type="date" class="form-control" id="field-' . $field->id . '" name="' . $field->name . '" ' . $field->required . '>';
                                            break;
                                        case 'IntegerField':
                                            echo '<input type="number" class="form-control" id="field-' . $field->id . '" name="' . $field->name . '" ' . $field->required . '>';
                                            break;
                                        case 'TextField':
                                            echo '<textarea class="form-control" id="ifield-' . $field->id . '" rows="3" name="' . $field->name . '" ' . $field->required . '></textarea>';
                                            break;
                                        default:
                                            break;
                                    }
                                    ?>
                                    <small id="HelpBlock-<?= $field->id ?>" class="form-text text-muted">
                                        <?= $field->help_text ?>
                                    </small>
                                </div>
                            </div>
                            <?php
                        }
                    }
                }
                ?>
                <div class="form-group row">
                    <div class="col-sm-10">
                        <button type="submit" name="msf-form-submit" class="btn btn-primary">Submit</button>
                    </div>
                </div>
                <input type="hidden" name="recaptcha_response" id="recaptchaResponse">
            </form>
        </div>
        <?php
    }
}

$msf_site_key = get_option('msf_site_key', '');
?>
<script type="text/javascript">
    var flag = false;
    function sendData(form){
        grecaptcha.ready(function () {
            grecaptcha.execute('<?= $msf_site_key ?>', {action: 'contact'}).then(function (token) {
                var recaptchaResponse = document.getElementById('recaptchaResponse');
                recaptchaResponse.value = token;
                flag = true;
                jQuery(form).submit();
            });
        });
        return false;
    }
    jQuery("#msf-form").on('submit', function(e){
        if(!flag){
            e.preventDefault();
            sendData(this);
        }
    });
</script>