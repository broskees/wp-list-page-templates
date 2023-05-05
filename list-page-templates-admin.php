<?php

defined('ABSPATH') || exit;

function list_page_templates_admin_menu() {
    add_menu_page(
        'List Page Templates',
        'List Page Templates',
        'manage_options',
        'list-page-templates',
        'list_page_templates_admin_page'
    );
}
add_action('admin_menu', 'list_page_templates_admin_menu');

function list_page_templates_admin_page() {
    ?>
    <div class="wrap">
        <h1>List Page Templates</h1>
        <form id="list_page_templates_form" method="post" enctype="multipart/form-data">
            <p>
                <label for="list_page_templates_csv">Upload CSV file containing URLs:</label>
                <input type="file" name="list_page_templates_csv" id="list_page_templates_csv" required>
            </p>
            <p>
                <input type="submit" name="submit" class="button button-primary" value="Generate List">
            </p>
            <?php wp_nonce_field('list_page_templates_ajax', 'list_page_templates_nonce'); ?>
        </form>
        <div id="list_page_templates_output"></div>
    </div>
    <?php
}

class ListPageTemplatesOutput
{
    protected string $output = '';

    public function __construct($urls, $ajax_processing = false)
    {
        $this->output = '';

        if (!$ajax_processing) {
            $this->output .= LIST_PAGE_TEMPLATES_TABLE_OPEN;
        }

        foreach ($urls as $url_row) {
            $url = $this->prep_url($url_row[0]);
            if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
                $url = home_url('/') . ltrim($url, '/');
            }

            $post_id = url_to_postid($url);

            // check if its a custom permalink
            if (!$post_id && is_plugin_active('custom-permalinks/custom-permalinks.php')) {
                $post_id = $this->customPermalinkFallback($url);
            }

            // Check if the URL is the homepage
            if (!$post_id && $url === home_url()) {
                $post_id = (int) get_option('page_on_front');
            }

            // WP Query fallback
            if (!$post_id) {
                $post_id = $this->wpQueryFallback($url);
            }

            // Fallback to get_page_by_path() function
            if (!$post_id) {
                $post_id = $this->pageByPathFallback($url);
            }

            if (!$post_id) {
                $post_id = $this->customSqlFallback($url);
            }

            if ($post_id) {
                $template = get_page_template_slug($post_id);
                if ($template) {
                    $template_name = ucwords(str_replace(array('-', '.php'), array(' ', ''), $template));
                    $template_path = get_template_directory() . '/' . $template;
                } else {
                    $template_name = 'Default Template';
                    $template_path = get_template_directory() . '/index.php';
                }
                $this->output .= sprintf(
                    '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                    esc_url($url),
                    esc_html($template_name),
                    esc_html($template_path)
                );
            } else {
                $this->output .= sprintf('<tr><td>%s</td><td>%s</td><td>N/A</td></tr>', esc_url($url), 'URL not found');
            }
        }

        if (!$ajax_processing) {
            $this->output .= LIST_PAGE_TEMPLATES_TABLE_CLOSE;
        }
    }

    public function __toString()
    {
        return $this->output;
    }

    protected function prep_url(string $url)
    {
        return rtrim(trim($url), '/') . '/';
    }

    protected function get_path(string $url)
    {
        return wp_parse_url($url, PHP_URL_PATH);
    }

    protected function customPermalinkFallback(string $url)
    {
        global $wpdb;

        $path = $this->get_path($url);

        if (!empty($path)) {
            $sql = $wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'custom_permalink' AND meta_value = '%s' LIMIT 1",
                trim($path, '/') . '/'
            );

            return (int) $wpdb->get_var($sql);
        }

        return false;
    }

    protected function wpQueryFallback(string $url)
    {
        $path = $this->get_path($url);
        $slug = basename($path);

        $query = new WP_Query(array(
            'post_type' => 'any',
            'name' => $slug,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'post_status' => 'publish'
        ));

        if ($query->have_posts()) {
            return $query->posts[0];
        }

        return false;
    }

    protected function pageByPathFallback(string $url)
    {
        $path = $this->get_path($url);
        $post_types = get_post_types(array('public' => true), 'names');
        $page = get_page_by_path($path, OBJECT, $post_types);

        if (!$page) {
            $page = get_page_by_path(
                rtrim($path, '/'),
                OBJECT,
                $post_types
            );
        }

        if (!$page) {
            $page = get_page_by_path(
                basename(rtrim($path, '/')),
                OBJECT,
                $post_types
            );
        }

        if ($page) {
            return $page->ID;
        }

        return false;
    }

    protected function customSqlFallback(string $url)
    {
        global $wpdb;

        $path = $this->get_path($url);

        $sql = $wpdb->prepare(
            "SELECT p.ID FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta m ON p.ID = m.post_id
            WHERE m.meta_key = '_wp_attached_file' AND m.meta_value LIKE %s AND p.post_status = 'publish' LIMIT 1",
            '%' . $wpdb->esc_like($path) . '%'
        );

        return (int) $wpdb->get_var($sql);
    }
}

function list_page_templates_generate_output($urls, $ajax_processing = false) {
    $output = new ListPageTemplatesOutput($urls, $ajax_processing);
    return (string) $output;
}
