<?php

/*
Plugin Name: Modasphere Submission Forms
Description: Modasphere Submission Forms is official plugin developed by Casting Networks LLC for Modasphere's clients, who would like to use talent submission form on their web sites. This plugin is bridge between Modasphere and Wordpress systems. Its main feature is creating web site submission forms and sending filled out information to Modasphere CRM.
Version: 1.0.1
Author: Casting Networks
Author URI: https://modasphere.com/
License: GPL2

Copyright 2019 Modasphere by Casting Networks  (email: support@modasphere.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
include_once 'includes/functions.php';
function msf_install()
{
    $msf_post_id = get_option('msf_post_id', 0);

    if ($msf_post_id == 0) {
        $msf_post_data = array(
            'post_title' => 'Submission Form',
            'post_content' => '[msf]',
            'post_status' => 'publish',
            'menu_order' => 0,
            'comment_status' => 'closed',
            'post_author' => 1,
            'post_name' => 'msf',
            'post_parent' => 0,
            'post_type' => 'page'
        );

        $msf_post_id = wp_insert_post(wp_slash($msf_post_data));
        add_option('msf_post_id', $msf_post_id);

    } else {
        wp_publish_post($msf_post_id);
    }

    //    Auth options
    add_option('msf_domain', '');
    add_option('msf_user_api', '');
    add_option('msf_site_key', '');
    add_option('msf_secret_key', '');

    //    Menu options
    add_option('msf_select_menu', 'main');
    add_option('msf_visibility_menu_items', '');
    add_option('msf_forms', '');

    $home_path = wp_get_upload_dir();
    if (!is_dir($home_path['basedir'] . "/msf_talent_img")) {
        mkdir($home_path['basedir'] . "/msf_talent_img");
    }

    flush_rewrite_rules();
}

function msf_deactivate()
{
    $msf_post_id = get_option('msf_post_id', 0);
    wp_trash_post($msf_post_id);
    flush_rewrite_rules();
}

function msf_uninstall()
{
    $menu_locations = get_nav_menu_locations();
    $selected_menu = get_option('msf_select_menu', 'main');
    $id_menu = $menu_locations[$selected_menu];
    $menu_items = wp_get_nav_menu_items($id_menu);
    $visibility_menu_items = get_option('msf_visibility_menu_items', '');
    if ($visibility_menu_items != '') $visibility_menu_items = (array)json_decode($visibility_menu_items);
    foreach ((array)$menu_items as $key => $menu_item) {
        if (is_nav_menu_item($menu_item->ID)) {
            if ($visibility_menu_items['m' . $menu_item->post_name] == 'checked') {
                wp_delete_post($menu_item->ID);
            }
        }
    }

    $msf_post_id = get_option('msf_post_id', 0);
    wp_delete_post($msf_post_id, true);

    delete_option('msf_domain');
    delete_option('msf_site_key');
    delete_option('msf_secret_key');
    delete_option('msf_select_menu');
    delete_option('msf_visibility_menu_items');
    delete_option('msf_forms');
    delete_option('msf_post_id');

//    include_once 'includes/functions.php';
    $home_path = wp_get_upload_dir();
    msf_delete_img_dir($home_path['basedir'] . '/msf_talent_img');

    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'msf_install');
register_deactivation_hook(__FILE__, 'msf_deactivate');
register_uninstall_hook(__FILE__, 'msf_uninstall');

add_action('wp_enqueue_scripts', 'msf_scripts');
function msf_scripts()
{
    wp_enqueue_style('bootstrap.min.css', plugin_dir_url(__FILE__) . 'bootstrap/css/bootstrap.min.css');
    wp_enqueue_style('bootstrap-select.min.css', plugin_dir_url(__FILE__) . 'bootstrap/css/bootstrap-select.min.css');
    wp_enqueue_script('bootstrap.bundle.min.js', plugin_dir_url(__FILE__) . 'bootstrap/js/bootstrap.bundle.min.js', array(), false, true);
    wp_enqueue_script('bootstrap.min.js', plugin_dir_url(__FILE__) . 'bootstrap/js/bootstrap.min.js', array(), false, true);
    wp_enqueue_script('bootstrap-select.min.js', plugin_dir_url(__FILE__) . 'bootstrap/js/bootstrap-select.min.js', array(), false, true);
    wp_enqueue_style('msf.css', plugin_dir_url(__FILE__) . 'assets/css/msf.css');
}

add_action('admin_enqueue_scripts', 'msf_admin_scripts');

function msf_admin_scripts()
{
    wp_enqueue_style('bootstrap.min.css', plugin_dir_url(__FILE__) . 'bootstrap/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap.bundle.min.js', plugin_dir_url(__FILE__) . 'bootstrap/js/bootstrap.bundle.min.js');
    wp_enqueue_script('bootstrap.min.js', plugin_dir_url(__FILE__) . 'bootstrap/js/bootstrap.min.js');
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_style('msf.css', plugin_dir_url(__FILE__) . 'assets/css/msf.css');
}

function msf_admin_menu()
{
    add_menu_page('Submission Forms', 'Submission Forms', 8, 'msf', 'msf_editor', plugin_dir_url(__FILE__) . 'assets/img/m20x20.png');
}

function msf_editor()
{
    include_once("includes/settings.php");
}

add_action('admin_menu', 'msf_admin_menu');

function msf_short()
{
    ob_start();
    include_once("includes/msf.php");
    return ob_get_clean();
}

add_shortcode('msf', 'msf_short');

add_action('init', 'do_msf_rewrite');
function do_msf_rewrite()
{
    add_rewrite_rule('msf/([^/]+)', 'index.php?pagename=msf&msf_name=$matches[1]', 'top');

    add_filter('query_vars', function ($vars) {
        $vars[] = 'msf_name';
        return $vars;
    });
}

add_action('wp_ajax_msf_del_form', 'msf_del_form');
add_action('wp_ajax_msf_add_field', 'msf_add_field');
add_action('wp_ajax_msf_del_field', 'msf_del_field');
add_action('wp_ajax_msf_change_help_text', 'msf_change_help_text');
add_action('wp_ajax_msf_change_label_text', 'msf_change_label_text');
add_action('wp_ajax_msf_change_required', 'msf_change_required');
add_action('wp_ajax_msf_sort_fields', 'msf_sort_fields');

function msf_del_form()
{
    $msf_form_id = sanitize_text_field($_POST['msf_id']);
    $forms = array();
    $search_form = 0;

    $msf_forms = json_decode(get_option('msf_forms', ''));
    if (!empty($msf_forms)) {
        foreach ($msf_forms as $form) {
            $id_tab = str_replace(' ', '-', strtolower($form->name));
            if ($id_tab != $msf_form_id) {
                $forms[] = $form;
            } else {
                $search_form = 1;
            }
        }
        $msf_forms = $forms;

        update_option('msf_forms', json_encode($msf_forms));
    }

    echo $search_form . ':' . $msf_form_id;
    die;
}

function msf_add_field()
{
    $config = sanitize_text_field($_POST['config']);
    $msf_name = sanitize_text_field($_POST['msf']);
    $msf_field_label = sanitize_text_field($_POST['label_field']);
    $msf_field_name = sanitize_text_field($_POST['fname_field']);
    $msf_field_type = sanitize_text_field($_POST['type']);
    $msf_field_config = json_decode(stripslashes($config));
    $msf_field_id = sanitize_text_field($_POST['id_field']);
    $msf_field_help_text = sanitize_text_field($_POST['help_text']);
    $msf_field_required = sanitize_text_field($_POST['frequired']);
    $msf_sort = 0;

    $msf_field = [
        "id" => $msf_field_id,
        "label" => $msf_field_label,
        "name" => $msf_field_name,
        "type" => $msf_field_type,
        "config" => $msf_field_config,
        "help_text" => $msf_field_help_text,
        "required" => $msf_field_required,
        "sort" => 0,
    ];

    $search_field = 0;

    $msf_forms = json_decode(get_option('msf_forms', ''));
    if (!empty($msf_forms)) {
        foreach ($msf_forms as $form) {
            $id_tab = str_replace(' ', '-', strtolower($form->name));
            if ($id_tab == $msf_name) {
                foreach ($form->fields as $field) {
                    if ($field->id == $msf_field_id) {
                        $search_field = 1;
                    }
                }
                if ($search_field == 0) {
                    $msf_field["sort"] = count($form->fields) + 1;
                    $msf_sort = $msf_field["sort"];
                    $form->fields[] = $msf_field;
                }
            }
        }
    }
    if ($search_field == 0) {
        update_option('msf_forms', json_encode($msf_forms));
    }
    $msf_field_help_text = ($msf_field_help_text == '') ? 'None' : $msf_field_help_text;

    $response = $msf_field_id . ':' . $msf_field_label . ':' . $msf_field_help_text . ':' . $msf_field_required . ':' . $search_field . ':' . $msf_sort;
    echo $response;

    die;
}

function msf_del_field()
{
    $msf_name = sanitize_text_field($_POST['msf']);
    $msf_field_id = sanitize_text_field($_POST['id_field']);
    $msf_sort = 0;
    $fields = array();
    $search_field = 0;

    $msf_forms = json_decode(get_option('msf_forms', ''));
    if (!empty($msf_forms)) {
        foreach ($msf_forms as $form) {
            $id_tab = str_replace(' ', '-', strtolower($form->name));
            if ($id_tab == $msf_name) {
                foreach ($form->fields as $field) {
                    if ($field->id == $msf_field_id) {
                        $msf_sort = $field->sort;
                    }
                }
            }
        }

        foreach ($msf_forms as $form) {
            $id_tab = str_replace(' ', '-', strtolower($form->name));
            if ($id_tab == $msf_name) {
                foreach ($form->fields as $field) {
                    if ($field->id != $msf_field_id) {
                        if ($field->sort > $msf_sort) {
                            $field->sort--;
                        }
                        $fields[] = $field;
                    } else {
                        $search_field = 1;
                    }
                }
                $form->fields = array();
                $form->fields = $fields;
            }
        }
    }
    update_option('msf_forms', json_encode($msf_forms));

    echo $search_field . ':' . $msf_field_id;

    die;
}

function msf_change_help_text()
{
    $msf_form_id = sanitize_text_field($_POST['msf_form_id']);
    $msf_field_id = sanitize_text_field($_POST['msf_field_id']);
    $msf_field_help_text = sanitize_text_field($_POST['msf_field_help_text']);

    $search_field = 0;

    $msf_forms = json_decode(get_option('msf_forms', ''));
    if (!empty($msf_forms)) {
        foreach ($msf_forms as $form) {
            $id_tab = str_replace(' ', '-', strtolower($form->name));
            if ($id_tab == $msf_form_id) {
                foreach ($form->fields as $field) {
                    if ($field->id == $msf_field_id) {
                        $field->help_text = $msf_field_help_text;
                        $search_field = 1;
                    }
                }
            }
        }
        update_option('msf_forms', json_encode($msf_forms));
    }
    echo $search_field . ':' . $msf_form_id . ':' . $msf_field_id . ':' . $msf_field_help_text;

    die;
}

function msf_change_label_text()
{
    $msf_form_id = sanitize_text_field($_POST['msf_form_id']);
    $msf_field_id = sanitize_text_field($_POST['msf_field_id']);
    $msf_field_label_text = sanitize_text_field($_POST['msf_field_label_text']);

    $search_field = 0;

    $msf_forms = json_decode(get_option('msf_forms', ''));
    if (!empty($msf_forms)) {
        foreach ($msf_forms as $form) {
            $id_tab = str_replace(' ', '-', strtolower($form->name));
            if ($id_tab == $msf_form_id) {
                foreach ($form->fields as $field) {
                    if ($field->id == $msf_field_id) {
                        $field->label = $msf_field_label_text;
                        $search_field = 1;
                    }
                }
            }
        }
        update_option('msf_forms', json_encode($msf_forms));
    }
    echo $search_field . ':' . $msf_form_id . ':' . $msf_field_id . ':' . $msf_field_label_text;

    die;
}

function msf_change_required()
{
    $msf_form_id = sanitize_text_field($_POST['msf_form_id']);
    $msf_field_id = sanitize_text_field($_POST['msf_field_id']);
    $msf_field_required = sanitize_text_field($_POST['msf_field_required']);

    $search_field = 0;

    $msf_forms = json_decode(get_option('msf_forms', ''));
    if (!empty($msf_forms)) {
        foreach ($msf_forms as $form) {
            $id_tab = str_replace(' ', '-', strtolower($form->name));
            if ($id_tab == $msf_form_id) {
                foreach ($form->fields as $field) {
                    if ($field->id == $msf_field_id) {
                        if ($msf_field_required == 'true')
                            $field->required = 'required';
                        else
                            $field->required = '';
                        $search_field = 1;
                    }
                }
            }
        }
        update_option('msf_forms', json_encode($msf_forms));
    }
    echo $search_field . ':' . $msf_form_id . ':' . $msf_field_id . ':' . $msf_field_required;

    die;
}

function msf_sort_fields()
{
    $fields_id = msf_recursive_sanitize_text_field($_POST['field']);
    $msf_form_id = sanitize_text_field($_POST['msf_form_id']);

    $i = 0;
    $msf_forms = json_decode(get_option('msf_forms', ''));
    if (!empty($msf_forms)) {
        foreach ($msf_forms as $form) {
            $id_tab = str_replace(' ', '-', strtolower($form->name));
            if ($id_tab == $msf_form_id) {
                foreach ($fields_id as $id_field) {
                    $i++;
                    foreach ($form->fields as $field) {
                        if ($field->id == $id_field) {
                            $field->sort = $i;
                        }
                    }
                }
            }
        }
        update_option('msf_forms', json_encode($msf_forms));
    }
    die;
}


function h1_msf_styles()
{
    $post_id = get_option('msf_post_id', 0);
    $post = get_post();
    if ($post_id == $post->ID) {
        $custom_css = "
		h1{
			display: none;
		}";
        wp_add_inline_style('msf.css', $custom_css);
    }
}

add_action('wp_enqueue_scripts', 'h1_msf_styles');

add_action('wp_head', 'msf_insert_recaptcha_key');
function msf_insert_recaptcha_key()
{
    $post_id = get_option('msf_post_id', 0);
    $post = get_post();
    if ($post_id == $post->ID) {
        $msf_site_key = get_option('msf_site_key', '');
        echo '<script src="https://www.google.com/recaptcha/api.js?render=' . $msf_site_key . '"></script>';
    }
}

add_filter('wp_mail_content_type', function () {
    return "text/html";
});

add_action('phpmailer_init', $phpmailerInitAction);