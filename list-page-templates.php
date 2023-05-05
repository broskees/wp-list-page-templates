<?php
/*
Plugin Name: List Page Templates
Description: A simple plugin to display a list of URLs and their corresponding page templates.
Version: 1.0
Author: Joe Roberts
*/

defined('ABSPATH') || exit;

$template_markup = explode(
    '<!-- SPLIT HERE -->',
    file_get_contents(__DIR__ . '/table-markup.html')
);

defined('LIST_PAGE_TEMPLATES_TABLE_OPEN') || define('LIST_PAGE_TEMPLATES_TABLE_OPEN', $template_markup[0]);
defined('LIST_PAGE_TEMPLATES_TABLE_CLOSE') || define('LIST_PAGE_TEMPLATES_TABLE_CLOSE', $template_markup[1]);

require_once plugin_dir_path(__FILE__) . 'list-page-templates-admin.php';

function list_page_templates_ajax_handler() {
    if (!isset($_POST['url']) || !wp_verify_nonce($_POST['nonce'], 'list_page_templates_ajax')) {
        wp_send_json_error('Invalid request');
    }

    $url = stripslashes($_POST['url']);
    $output = list_page_templates_generate_output(array(array($url)), true);

    wp_send_json_success($output);
}
add_action('wp_ajax_list_page_templates_process_url', 'list_page_templates_ajax_handler');

function list_page_templates_admin_enqueue_scripts($hook) {
    if ('toplevel_page_list-page-templates' !== $hook) {
        return;
    }

    wp_enqueue_script('list-page-templates-ajax', plugin_dir_url(__FILE__) . 'list-page-templates-ajax.js', array('jquery'), '1.0.0', true);

    // Send the nonce to JavaScript
    wp_localize_script('list-page-templates-ajax', 'list_page_templates_vars', array(
        'nonce' => wp_create_nonce('list_page_templates_ajax'),
        'table_open' => LIST_PAGE_TEMPLATES_TABLE_OPEN,
        'table_close' => LIST_PAGE_TEMPLATES_TABLE_CLOSE,
    ));
}
add_action('admin_enqueue_scripts', 'list_page_templates_admin_enqueue_scripts');
