<?php
include_once 'functions.php';
$set_domain = get_option('msf_domain', '');
$user_api = get_option('msf_user_api', '');

$api_result = msf_api_get_content($set_domain, $user_api, 'contactfields');
if ($api_result != false) {
    $contact_fields = $api_result->objects;
}

if (!empty($_POST)) {
    if (isset($_POST['talent-list-set'])) {


    } elseif (isset($_POST['submission-forms-settings'])) {

        $msf_forms = json_decode(get_option('msf_forms', ''));
        $form = array();
        $visibility_menu_items = array();
        if (!empty($msf_forms)) {
            foreach ($msf_forms as $form) {
                $id_tab = str_replace(' ', '-', strtolower($form->name));

                $form->description = wp_kses_post(wpautop($_POST['msf_description_' . $id_tab]));
                $form->email = sanitize_email($_POST['msf_agent_email_' . $id_tab]);
                $form->from_email = sanitize_email($_POST['msf_from_email_' . $id_tab]);
                $form->success = sanitize_textarea_field($_POST['msf_success_' . $id_tab]);

                $visibility_menu_items['m-' . $id_tab] = sanitize_text_field($_POST['add-to-menu-' . $id_tab]);
            }
        }
        update_option('msf_forms', json_encode($msf_forms));
        update_option('msf_visibility_menu_items', json_encode($visibility_menu_items));

        require_once(ABSPATH . 'wp-admin/includes/nav-menu.php');

        $menu_locations = get_nav_menu_locations();
        $selected_menu = get_option('msf_select_menu', 'main');
        $id_menu = $menu_locations[$selected_menu];
        $menu_items = wp_get_nav_menu_items($id_menu);

        foreach ((array)$menu_items as $key => $menu_item) {
            if (is_nav_menu_item($menu_item->ID)) {
                if (isset($visibility_menu_items['m-' . $menu_item->post_name]) && empty($visibility_menu_items['m-' . $menu_item->post_name])) {
                    wp_delete_post($menu_item->ID);
                }
            }
        }

        $fl_search = 0;
        $post_id = get_option('msf_post_id', 0);

        if (!empty($msf_forms)) {
            foreach ($msf_forms as $form) {
                $id_tab = str_replace(' ', '-', strtolower($form->name));

                if (is_array($visibility_menu_items) && $visibility_menu_items['m-' . $id_tab] == 'checked') {
                    foreach ((array)$menu_items as $key => $menu_item) {
                        if (is_nav_menu_item($menu_item->ID) && $menu_item->post_name == $id_tab) {
                            $fl_search++;
                        }
                    }
                    if ($fl_search == 0) {
                        $args = array(
                            array(
                                'menu-item-db-id' => '',
                                'menu-item-object-id' => '2',
                                'menu-item-object' => 'custom',
                                'menu-item-parent-id' => 0,
                                'menu-item-position' => 0,
                                'menu-item-type' => 'custom',
                                'menu-item-title' => $form->name,
                                'menu-item-url' => get_permalink($post_id) . $id_tab,
                                'menu-item-description' => '',
                                'menu-item-attr-title' => '',
                                'menu-item-target' => '',
                                'menu-item-classes' => '',
                                'menu-item-xfn' => ''
                            )
                        );
                        $items_id = wp_save_nav_menu_items($id_menu, $args);
                        if ($items_id) {
                            $items_post = array(
                                'ID' => $items_id[0],
                                'post_status' => 'publish',
                                'post_name' => $form->name
                            );
                            wp_update_post($items_post);
                        }
                    }
                    $fl_search = 0;
                }
            }
        }
    } elseif (isset($_POST['submission-form-create'])) {
        $msf_forms = json_decode(get_option('msf_forms', ''));
        $form = array();
        $msf_name = sanitize_text_field($_POST['msf_name']);
        $msf_description = sanitize_textarea_field($_POST['msf_description']);
        $msf_email = sanitize_email($_POST['msf_agent_email']);
        $msf_from_email = sanitize_email($_POST['msf_from_email']);
        $msf_success = sanitize_textarea_field($_POST['msf_success']);

        $form['name'] = $msf_name;
        $form['description'] = $msf_description;
        $form['email'] = $msf_email;
        $form['from_email'] = $msf_from_email;
        $form['success'] = $msf_success;
        $form['fields'] = array();
        $msf_forms[] = $form;
        update_option('msf_forms', json_encode($msf_forms));
    } else {
        update_option('msf_domain', sanitize_text_field($_POST['domain']));
        update_option('msf_user_api', sanitize_text_field($_POST['login']));
        update_option('msf_select_menu', sanitize_text_field($_POST['msf_select_menu']));
        update_option('msf_site_key', sanitize_text_field($_POST['msf-site-key']));
        update_option('msf_secret_key', sanitize_text_field($_POST['msf-secret-key']));
        $user_api = sanitize_text_field($_POST['login']);
        $set_domain = get_option('msf_domain', '');
    }
} else {
    $page_title = get_the_title(get_option('msf_post_id', 0));
}
?>
<style>
    .ui-sortable-placeholder {
        background-color: #fdffe3 !important;
        visibility: visible !important;
    }

    .ui-sortable-helper {
        border: 2px solid #aaa !important;
        display: table;
    }

    .ui-sortable-helper > td {
        border: 0 !important;
    }
</style>
<div class="msf-admin">
    <div class="row ">
        <div class="col-md-4">
            <div class="card" style="max-width: 100%;">
                <div class="card-header mb-3">Auth</div>
                <form method="post">
                    <div class="form-row">
                        <div class="form-group col-md-12">
                            <?php $set_domain = get_option('msf_domain', ''); ?>
                            <label for="domain">Domain</label>
                            <input type="text" class="form-control" id="domain" name="domain"
                                   value="<?= esc_attr($set_domain) ?>"
                                   placeholder="Enter domain">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-12">
                            <label for="user">User</label>
                            <input type="text" class="form-control" id="user" name="login" aria-describedby="userHelp"
                                   value="<?= esc_attr($user_api) ?>" placeholder="Enter user">
                            <small id="userHelp" class="form-text text-muted">We'll never share your user with anyone
                                else.
                            </small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-12">
                            <label for="msf-select-menu">Select menu</label>
                            <select class="form-control custom-select msf-field-select" name="msf_select_menu"
                                    id="msf-select-menu">
                                <option value=""></option>
                                <?php
                                $selected_menu = get_option('msf_select_menu', 'main');
                                $locations = get_nav_menu_locations();
                                foreach ($locations as $key_menu => $value_menu) {
                                    if ($key_menu == $selected_menu) {
                                        echo '<option selected value="' . $key_menu . '">' . $key_menu . '</option>';
                                    } else {
                                        echo '<option value="' . $key_menu . '">' . $key_menu . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-12">
                            <?php $msf_site_key = get_option('msf_site_key', ''); ?>
                            <label for="msf-site-key">Site key</label>
                            <input type="text" class="form-control" id="msf-site-key" name="msf-site-key"
                                   value="<?= esc_attr($msf_site_key) ?>"
                                   placeholder="reCAPTCHA v3. Enter site key">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-12">
                            <?php $msf_secret_key = get_option('msf_secret_key', ''); ?>
                            <label for="msf-secret-key">Secret key</label>
                            <input type="text" class="form-control" id="msf-secret-key" name="msf-secret-key"
                                   value="<?= esc_attr($msf_secret_key) ?>"
                                   placeholder="reCAPTCHA v3. Enter secret key">
                        </div>
                    </div>
                    <button type="submit" name="auth-set" class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card" style="max-width: 100%;">
                <form method="post">
                    <div class="card-header mb-3">
                        Submission forms
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="msf_name">Name</label>
                                    <input type="text" class="form-control" id="msf_name" name="msf_name"
                                           value=""
                                           placeholder="Enter name for submission form" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="msf_description">Description</label>
                                    <textarea id="msf_description" class="form-control"
                                              rows="3" name="msf_description"></textarea>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="msf_agent_email">Email</label>
                                    <input type="email" id="msf_agent_email" class="form-control"
                                           name="msf_agent_email" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="msf_from_email">From email</label>
                                    <input type="email" id="msf_from_email" class="form-control"
                                           name="msf_from_email" value="@<?= msf_get_domain() ?>" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="msf_success">Submission success</label>
                                    <textarea id="msf_success" class="form-control" rows="3"
                                              name="msf_success"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 border-left">
                            <table class="table table-sm">
                                <tbody>
                                <?php
                                $msf_forms = json_decode(get_option('msf_forms', ''));
                                foreach ($msf_forms as $form) {
                                    $form_id = str_replace(' ', '-', strtolower($form->name));
                                    ?>
                                    <tr>
                                        <td><?= $form->name ?></td>
                                        <td class="text-right">
                                            <button type="button" id="msf-del-form-<?= $form_id ?>"
                                                    class="btn btn-danger btn-sm msf-del-submission-form"
                                                    data-form="<?= $form_id ?>">Delete
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <button type="submit" name="submission-form-create" class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
    </div>
    <script>
        jQuery('.msf-del-submission-form').on('click', function () {
            if (confirm('Are you sure?')) {
                jQuery.ajax({
                    url: '<?php echo admin_url("admin-ajax.php") ?>',
                    type: 'POST',
                    data: 'action=msf_del_form&msf_id=' + jQuery(this).data("form"),
                    dataType: "html",
                    success: function (html) {
                        response = html.split(":", 2);
                        if (response[0] == 1) {
                            jQuery('#msf-del-form-' + response[1]).parent().parent().remove();
                            jQuery('#nav-' + response[1] + '-tab').remove();
                            jQuery('#nav-' + response[1]).remove();
                        }
                    },
                    error: function (html) {
                        alert(html.error);
                    }
                });
            }
        });
    </script>
    <div class="row">
        <div class="col-md-12">
            <div class="card" style="max-width: 100%;">
                <form method="post">
                    <div class="card-header mb-3">
                        Submission forms settings
                    </div>
                    <nav>
                        <div class="nav nav-tabs" id="nav-tab" role="tablist">
                            <?php
                            $json_forms = get_option('msf_forms', '');
                            $msf_forms = json_decode(get_option('msf_forms', ''));
                            $active_tab_link = 'active';
                            $tab_aria_selected = 'true';
                            if (!empty($msf_forms)) {
                                foreach ($msf_forms as $form) {
                                    $id_tab = str_replace(' ', '-', strtolower($form->name));
                                    ?>
                                    <a class="nav-item nav-link <?= $active_tab_link ?>"
                                       id="nav-<?= $id_tab ?>-tab"
                                       data-toggle="tab" href="#nav-<?= $id_tab ?>" role="tab"
                                       aria-controls="nav-<?= $id_tab ?>"
                                       aria-selected="<?= $tab_aria_selected ?>"><?= $form->name ?></a>
                                    <?php
                                    $active_tab_link = '';
                                    $tab_aria_selected = 'false';

                                }
                            }
                            $active_tab_link = 'show active';
                            ?>
                        </div>
                    </nav>
                    <div class="tab-content mt-3" id="nav-tabContent">
                        <?php
                        if (!empty($msf_forms)) {
                            foreach ($msf_forms as $form) {
                                $id_tab = str_replace(' ', '-', strtolower($form->name));
                                ?>
                                <div class="tab-pane fade <?= $active_tab_link ?>" id="nav-<?= $id_tab ?>"
                                     role="tabpanel" aria-labelledby="nav-<?= $id_tab ?>-tab">
                                    <div class="form-row">
                                        <div class="form-group col-md-12">
                                            <label for="msf_description_<?= $id_tab ?>">Description</label>
                                            <?php
                                            $editor_content = wp_unslash($form->description);
                                            $editor_id = 'msfdescription' . str_replace('-', '', $id_tab);
                                            $editor_name = 'msf_description_' . $id_tab;
                                            wp_editor($editor_content, $editor_id, msf_get_editor_settings($editor_name, 'msf-description'));
                                            ?>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-3 mt-2">
                                            <div class="form-row">
                                                <div class="form-group col-md-12">
                                                    <label for="msf_agent_email_<?= $id_tab ?>">Email</label>
                                                    <input class="form-control" type="email" required="required"
                                                           id="msf_agent_email_<?= $id_tab ?>"
                                                           name="msf_agent_email_<?= $id_tab ?>"
                                                           value="<?= $form->email ?>">
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-md-12">
                                                    <label for="msf_from_email_<?= $id_tab ?>">From email</label>
                                                    <input class="form-control" type="email" required="required"
                                                           id="msf_from_email_<?= $id_tab ?>"
                                                           name="msf_from_email_<?= $id_tab ?>"
                                                           value="<?= $form->from_email ?>">
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-md-12">
                                                    <label for="msf_success_<?= $id_tab ?>">Submission success</label>
                                                    <textarea id="msf_success_<?= $id_tab ?>" class="form-control"
                                                              rows="7"
                                                              name="msf_success_<?= $id_tab ?>"><?= wp_unslash($form->success) ?></textarea>
                                                </div>
                                            </div>
                                            <?php
                                            $msf_visibility_menu = get_option('msf_visibility_menu_items', '');
                                            if ($msf_visibility_menu != '') $msf_visibility_menu = (array)json_decode($msf_visibility_menu);
                                            ?>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox"
                                                       name="add-to-menu-<?= $id_tab ?>" value="checked"
                                                       id="add-to-menu-<?= $id_tab ?>"
                                                    <?php
                                                    if (is_array($msf_visibility_menu)) echo $msf_visibility_menu['m-' . $id_tab];
                                                    ?>
                                                >
                                                <label class="form-check-label" for="add-to-menu-<?= $id_tab ?>">
                                                    Add to main menu
                                                </label>
                                            </div>
                                        </div>
                                        <div class="form-group col-md-9 pl-4">
                                            <table class="table table-sm" id="field_table_<?= $id_tab ?>">
                                                <thead>
                                                <tr>
                                                    <td class="border-top-0 align-middle">
                                                        Add field
                                                    </td>
                                                    <td class="border-top-0">
                                                        <select class="form-control msf-field-select"
                                                                id="new_field_<?= $id_tab ?>"
                                                                name="new_field_<?= $id_tab ?>">
                                                            <optgroup label="Default field">
                                                                <option data-label="First name"
                                                                        data-fname="first_name"
                                                                        data-type="CharField"
                                                                        data-config='[null]'
                                                                        value="df01">First name
                                                                </option>
                                                                <option data-label="Last name"
                                                                        data-fname="last_name"
                                                                        data-type="CharField"
                                                                        data-config='[null]'
                                                                        value="df02">Last name
                                                                </option>
                                                                <option data-label="Email"
                                                                        data-fname="email"
                                                                        data-type="EmailField"
                                                                        data-config='[null]'
                                                                        value="df03">Email
                                                                </option>
                                                                <option data-label="Phone"
                                                                        data-fname="phone"
                                                                        data-type="PhoneField"
                                                                        data-config='[null]'
                                                                        value="df04">Phone
                                                                </option>
                                                            </optgroup>
                                                            <optgroup label="Other field">
                                                                <?php
                                                                if (isset($contact_fields)) {
                                                                    asort($contact_fields);
                                                                    foreach ($contact_fields as $field) {
                                                                        $format_config = array();
                                                                        foreach ($field->config as $key_c => $fl_c) {
                                                                            $format_config[$key_c] = htmlspecialchars(str_replace('"', '``', $fl_c), ENT_QUOTES);
                                                                        }
                                                                        ?>
                                                                        <option data-label="<?= $field->label ?>"
                                                                                data-fname="<?= $field->name ?>"
                                                                                data-type="<?= $field->type ?>"
                                                                                data-config='[<?= json_encode($format_config) ?>]'
                                                                                value="<?= $field->id ?>">
                                                                            <?= $field->label ?>
                                                                        </option>
                                                                        <?php
                                                                    }
                                                                }
                                                                ?>
                                                            </optgroup>
                                                        </select>
                                                    </td>
                                                    <td class="border-top-0">
                                                        <input class="form-control" type="text"
                                                               id="new_help_text_<?= $id_tab ?>"
                                                               name="new_help_text_<?= $id_tab ?>"
                                                               placeholder="Help text">
                                                    </td>
                                                    <td class="border-top-0 align-middle">
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="checkbox"
                                                                   id="new_required_<?= $id_tab ?>"
                                                                   name="new_required_<?= $id_tab ?>" value="required">
                                                            <label class="form-check-label"
                                                                   for="new_required_<?= $id_tab ?>">required</label>
                                                        </div>
                                                    </td>
                                                    <td class="border-top-0 align-middle">
                                                        <button type="button" id="add-field-<?= $id_tab ?>"
                                                                class="btn btn-sm btn-info msf-add-field"
                                                                data-form="<?= $id_tab ?>">Add
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th class="field-name">Field name</th>
                                                    <th class="field-help-text" colspan="2">Help text</th>
                                                    <th class="field-required">Required</th>
                                                    <th></th>
                                                </tr>
                                                </thead>
                                                <tbody class="ui-sortable" data-form="<?= $id_tab ?>">
                                                <?php
                                                for ($i = 1; $i <= count($form->fields); $i++) {
                                                    foreach ($form->fields as $field) {
                                                        if ($field->sort == $i) {
                                                            ?>
                                                            <tr id="field-<?= $field->id ?>" data-id="<?= $field->id ?>"
                                                                class="ui-sortable-handle">
                                                                <td>
                                                                    <span class="j_form_label">
                                                                        <span class="j_form_field hidden">
                                                                            <input type="text" class="msf-edit-label"
                                                                                   name="<?= $id_tab ?>_field_label_<?= $field->id ?>"
                                                                                   id="<?= $id_tab ?>_field_label_<?= $field->id ?>"
                                                                                   data-form="<?= $id_tab ?>"
                                                                                   data-field="<?= $field->id ?>"
                                                                                   value="<?= $field->label ?>">
                                                                            </span>
                                                                        <span class="j_form_value"><?= $field->label ?></span>
                                                                    </span>
                                                                </td>
                                                                <td colspan="2">
                                                                    <span class="j_form_help">
                                                                        <span class="j_form_field hidden">
                                                                            <input type="text"
                                                                                   class="msf-edit-help-text"
                                                                                   name="<?= $id_tab ?>_help_text_<?= $field->id ?>"
                                                                                   id="<?= $id_tab ?>_help_text_<?= $field->id ?>"
                                                                                   data-form="<?= $id_tab ?>"
                                                                                   data-field="<?= $field->id ?>"
                                                                                   value="<?= $field->help_text ?>">
                                                                        </span>
                                                                        <span class="j_form_value"><?php
                                                                            if ($field->help_text > '') echo $field->help_text;
                                                                            else echo 'None'; ?>
                                                                        </span>
                                                                    </span>
                                                                </td>
                                                                <td class="align-middle">
                                                            <span class="hidden"
                                                                  id="<?= $id_tab ?>_form_required_<?= $field->id ?>">
                                                                <input class="form-check-input"
                                                                       type="checkbox"
                                                                       value="required"
                                                                       data-form="<?= $id_tab ?>"
                                                                       data-field="<?= $field->id ?>"
                                                                       id="<?= $id_tab ?>_required_<?= $field->id ?>"
                                                                        <?php if (($field->required) == 'required') echo 'checked'; ?>>
                                                            </span>
                                                                    <span class="required-status"
                                                                          data-form="<?= $id_tab ?>"
                                                                          data-field="<?= $field->id ?>">
                                                            <?php
                                                            if ($field->required == 'required')
                                                                echo 'Yes';
                                                            else
                                                                echo 'No';
                                                            ?>
                                                            </span>
                                                                </td>
                                                                <td>
                                                                    <button type="button"
                                                                            class="btn btn-danger btn-sm msf-delete-field"
                                                                            data-key="<?= $field->id ?>"
                                                                            data-form="<?= $id_tab ?>"
                                                                            id="<?= $id_tab ?>-msf-del-<?= $field->id ?>">
                                                                        Delete
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                            <?php
                                                        }
                                                    }
                                                }
                                                ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <?php
                                $active_tab_link = '';
                            }
                        }
                        ?>
                    </div>
                    <button type="submit" name="submission-forms-settings" class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function() {
            jQuery('.msf-add-field').on('click', function () {
                var field_required = '';
                var form_id = jQuery(this).data('form');
                if (jQuery('#new_required_' + form_id).prop('checked')) {
                    field_required = 'required';
                }
                jQuery.ajax({
                    url: '<?php echo admin_url("admin-ajax.php") ?>',
                    type: 'POST',
                    data: 'action=msf_add_field&msf=' + form_id + '&label_field=' + jQuery('#new_field_' + form_id).find(':selected').data('label')
                        + '&fname_field=' + jQuery('#new_field_' + form_id).find(':selected').data('fname')
                        + '&type=' + jQuery('#new_field_' + form_id).find(':selected').data('type')
                        + '&config=' + JSON.stringify(jQuery('#new_field_' + form_id).find(':selected').data('config'))
                        + '&id_field=' + jQuery('#new_field_' + form_id).find(':selected').val()
                        + '&help_text=' + jQuery('#new_help_text_' + form_id).val()
                        + '&frequired=' + field_required,
                    dataType: "html",
                    success: function (html) {
                        response = html.split(":", 6);
                        if (response[4] == 0) {
                            var req_status;
                            var tick_checkbox = '';
                            if (response[3] === 'required') {
                                req_status = 'Yes';
                                tick_checkbox = 'checked';
                            } else req_status = 'No';
                            jQuery('#new_help_text_' + form_id).val('');
                            jQuery('#new_required_' + form_id).attr('checked', false);
                            jQuery("#field_table_" + form_id + " > tbody").append('<tr id="field-' + response[0] + '" data-id="' + response[0] + '" class="ui-sortable-handle">' +
                                '<td><span class="j_form_label"><span class="j_form_field hidden">' +
                                '<input type="text" class="msf-edit-label"' +
                                'name="' + form_id + '_field_label_' + response[0] + '"' +
                                'id="' + form_id + '_field_label_' + response[0] + '"' +
                                'data-form="' + form_id + '"' +
                                'data-field="' + response[0] + '"' +
                                'value="' + response[1] + '">' +
                                '</span><span class="j_form_value">' + response[1] + '</span></span></td>' +
                                '<td colspan="2"><span class="j_form_help"><span class="j_form_field hidden">' +
                                '<input type="text" class="msf-edit-help-text" ' +
                                'name="' + form_id + '_help_text_' + response[0] + '" ' +
                                'id="' + form_id + '_help_text_' + response[0] + '" ' +
                                'data-form="' + form_id + '" ' +
                                'data-field="' + response[0] + '" ' +
                                'value="' + response[2] + '">' +
                                '</span><span class="j_form_value">' + response[2] + '</span></span></td>' +
                                '<td class="align-middle">' +
                                '<span class="hidden" id="' + form_id + '_form_required_' + response[0] + '">' +
                                '<input class="form-check-input" type="checkbox" value="required" ' +
                                'data-form="' + form_id + '" data-field="' + response[0] + '" ' +
                                'id="' + form_id + '_required_' + response[0] + '"' + tick_checkbox + '></span>' +
                                '<span class="required-status" data-form="' + form_id + '" data-field="' + response[0] + '">' +
                                req_status + '</span></td>' +
                                '<td><button type="button" class="btn btn-danger btn-sm msf-delete-field" data-key="' + response[0] + '" data-form="' + form_id + '" id="' + form_id + '-msf-del-' + response[0] + '">Delete</button></td></tr>');
                        } else {
                            alert('Field has been added!');
                        }
                    },
                    error: function (html) {
                        alert(html.error);
                    }
                });
            });

            jQuery('table').on('click', '.msf-delete-field', function () {
                if (confirm('Are you sure?')) {
                    var form_id = jQuery(this).data('form');
                    jQuery.ajax({
                        url: '<?php echo admin_url("admin-ajax.php") ?>',
                        type: 'POST',
                        data: 'action=msf_del_field&msf=' + form_id + '&id_field=' + jQuery(this).data('key'),
                        dataType: "html",
                        success: function (html) {
                            response = html.split(":", 2);
                            if (response[0] == 1) {
                                jQuery('#' + form_id + '-msf-del-' + response[1]).parent().parent().remove();
                            }
                        },
                        error: function (html) {
                            alert(html.error);
                        }
                    });
                }
            });

            jQuery('table').on('dblclick', '.j_form_help', function () {
                jQuery(this).children('.j_form_field').removeClass('hidden');
                jQuery(this).children('.j_form_value').addClass('hidden');
                jQuery(this).children('.j_form_field').children('input').focus();
            });

            jQuery('table').on('dblclick', '.j_form_label', function () {
                jQuery(this).children('.j_form_field').removeClass('hidden');
                jQuery(this).children('.j_form_value').addClass('hidden');
                jQuery(this).children('.j_form_field').children('input').focus();
            });

            jQuery('table').on('dblclick', '.required-status', function () {
                jQuery(this).addClass('hidden');
                var form_id = jQuery(this).data('form');
                var field_id = jQuery(this).data('field');
                jQuery('#' + form_id + '_form_required_' + field_id).removeClass('hidden');
                jQuery('#' + form_id + '_form_required_' + field_id).children('input').focus();
            });

            function msf_change_help_text(edit_input) {
                jQuery.ajax({
                    url: '<?php echo admin_url("admin-ajax.php") ?>',
                    type: 'POST',
                    data: 'action=msf_change_help_text&msf_form_id=' + edit_input.data("form") + '&msf_field_id=' + edit_input.data("field") + '&msf_field_help_text=' + edit_input.val(),
                    dataType: "html",
                    success: function (html) {
                        response = html.split(":", 4);
                        if (response[0] == 1) {
                            jQuery('#' + response[1] + '_help_text_' + response[2]).val(response[3]).parent().addClass('hidden');
                            if (response[3] == '') response[3] = 'None';
                            jQuery('#' + response[1] + '_help_text_' + response[2]).parent().next().removeClass('hidden').addClass('text-success').text(response[3]);
                        }
                    },
                    error: function (html) {
                        alert(html.error);
                    }
                });
            }

            function msf_change_label_text(edit_input) {
                jQuery.ajax({
                    url: '<?php echo admin_url("admin-ajax.php") ?>',
                    type: 'POST',
                    data: 'action=msf_change_label_text&msf_form_id=' + edit_input.data("form") + '&msf_field_id=' + edit_input.data("field") + '&msf_field_label_text=' + edit_input.val(),
                    dataType: "html",
                    success: function (html) {
                        response = html.split(":", 4);
                        if (response[0] == 1) {
                            jQuery('#' + response[1] + '_field_label_' + response[2]).val(response[3]).parent().addClass('hidden');
                            jQuery('#' + response[1] + '_field_label_' + response[2]).parent().next().removeClass('hidden').addClass('text-success').text(response[3]);
                        }
                    },
                    error: function (html) {
                        alert(html.error);
                    }
                });
            }

            function msf_change_required_status(edit_input) {
                jQuery.ajax({
                    url: '<?php echo admin_url("admin-ajax.php") ?>',
                    type: 'POST',
                    data: 'action=msf_change_required&msf_form_id=' + edit_input.data("form") + '&msf_field_id=' + edit_input.data("field") + '&msf_field_required=' + edit_input.prop('checked'),
                    dataType: "html",
                    success: function (html) {
                        response = html.split(":", 4);
                        if (response[0] == 1) {
                            if (response[3] == 'true') {
                                jQuery('#' + response[1] + '_form_required_' + response[2]).next().html('Yes');
                                jQuery('#' + response[1] + '_required_' + response[2]).attr('checked', true);
                            } else {
                                jQuery('#' + response[1] + '_form_required_' + response[2]).next().html('No');
                                jQuery('#' + response[1] + '_required_' + response[2]).attr('checked', false);
                            }
                        }
                        jQuery('#' + response[1] + '_form_required_' + response[2]).addClass('hidden');
                        jQuery('#' + response[1] + '_form_required_' + response[2]).next().removeClass('hidden');
                    },
                    error: function (html) {
                        alert(html.error);
                    }
                });
            }

            jQuery('table').on('focusout', '.msf-edit-help-text', function () {
                msf_change_help_text(jQuery(this));
            });

            jQuery('table').on('focusout', '.msf-edit-label', function () {
                msf_change_label_text(jQuery(this));
            });

            jQuery('table').on('focusout', '.form-check-input', function () {
                msf_change_required_status(jQuery(this));
            });

            jQuery(function (jQuery) {
                jQuery('.tab-content').click(function (e) {
                    var edit_input = jQuery("input:focus");
                    if (!edit_input.is(e.target) && edit_input.has(e.target).length === 0) {
                        if (edit_input.attr('class') == 'msf-edit-help-text') {
                            msf_change_help_text(edit_input);
                        }
                        if (edit_input.attr('class') == 'msf-edit-label') {
                            msf_change_label_text(edit_input);
                        }
                        if (edit_input.attr('class') == 'form-check-input') {
                            msf_change_required_status(edit_input);
                        }
                    }
                });
            });

            jQuery('tbody').sortable({
                axis: "y",
                update: function (event, ui) {
                    var data = jQuery(this).sortable('serialize');
                    var form = jQuery(this).data("form");
                    jQuery.ajax({
                        url: '<?php echo admin_url("admin-ajax.php") ?>',
                        type: 'POST',
                        data: 'action=msf_sort_fields&msf_form_id=' + form + '&' + data,
                        dataType: "html",
                        // success: function (html) {
                        //
                        //
                        // },
                        error: function (html) {
                            alert(html.error);
                        }
                    });
                }
            });
        });
    </script>
</div>