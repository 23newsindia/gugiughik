<?php
/**
 * Plugin Name: WP Minify Assets
 * Description: Minify HTML, CSS, and JavaScript assets using the minify package.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL-2.0+
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_MINIFY_ASSETS_VERSION', '1.0.0');
define('WP_MINIFY_ASSETS_PATH', plugin_dir_path(__FILE__));
define('WP_MINIFY_ASSETS_URL', plugin_dir_url(__FILE__));

// Check if composer autoload exists
$composer_autoload = WP_MINIFY_ASSETS_PATH . 'vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
} else {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo __('WP Minify Assets requires Composer dependencies to be installed. Please run <code>composer install</code> in the plugin directory.', 'wp-minify-assets');
        echo '</p></div>';
    });
    return;
}

class WP_Minify_Assets {
    private $options;
    private $minify_enabled;

    public function __construct() {
        // Default options
        $this->options = array(
            'minify_html' => false,
            'minify_css' => true,
            'minify_js' => true,
            'exclude_css' => array(),
            'exclude_js' => array(),
            'enable_logging' => false
        );

        // Load saved options
        $saved_options = get_option('wp_minify_assets_options');
        if ($saved_options) {
            $this->options = wp_parse_args($saved_options, $this->options);
        }

        // Check if minification should be enabled
        $this->minify_enabled = !(defined('WP_MINIFY_DISABLE') && WP_MINIFY_DISABLE);
        
        // Correct option keys:
    if ($this->minify_enabled) {
       

        if ($this->options['minify_css']) {
            add_filter('style_loader_tag', array($this, 'minify_style_tag'), 10, 4);
        }

        if ($this->options['minify_js']) {
    add_filter('script_loader_tag', array($this, 'minify_script_tag'), 9, 3);
}
    }

        // Admin interface
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_bar_menu', array($this, 'admin_bar_menu'), 100);
        add_action('admin_init', array($this, 'handle_clear_cache'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));

        // Cache management
        add_action('wp_minify_assets_cleanup', array($this, 'cleanup_cache'));
        $this->schedule_cleanup();
    }

   
   
   
   
   
   
   
   
   

    private function should_minify($type, $handle) {
    $exclude_list = $this->options["exclude_{$type}"] ?? array();
    
    // Ensure exclude_list is always an array
    if (!is_array($exclude_list)) {
        $exclude_list = array();
    }
    
    return !in_array($handle, $exclude_list);
}

    public function add_admin_menu() {
        add_options_page(
            'WP Minify Assets',
            'Minify Assets',
            'manage_options',
            'wp_minify_assets',
            array($this, 'options_page')
        );
    }

    public function settings_init() {
    register_setting(
        'wp_minify_assets', 
        'wp_minify_assets_options',
        array($this, 'sanitize_options') // Add this line
    );

        add_settings_section(
            'wp_minify_assets_section',
            __('Minification Settings', 'wp-minify-assets'),
            array($this, 'settings_section_callback'),
            'wp_minify_assets'
        );

      

        add_settings_field(
            'minify_css',
            __('Minify CSS', 'wp-minify-assets'),
            array($this, 'checkbox_field_render'),
            'wp_minify_assets',
            'wp_minify_assets_section',
            array('name' => 'minify_css')
        );

        add_settings_field(
            'minify_js',
            __('Minify JavaScript', 'wp-minify-assets'),
            array($this, 'checkbox_field_render'),
            'wp_minify_assets',
            'wp_minify_assets_section',
            array('name' => 'minify_js')
        );

        add_settings_field(
            'exclude_css',
            __('Exclude CSS Handles', 'wp-minify-assets'),
            array($this, 'text_field_render'),
            'wp_minify_assets',
            'wp_minify_assets_section',
            array('name' => 'exclude_css', 'description' => __('Comma-separated list of CSS handles to exclude', 'wp-minify-assets'))
        );

        add_settings_field(
            'exclude_js',
            __('Exclude JS Handles', 'wp-minify-assets'),
            array($this, 'text_field_render'),
            'wp_minify_assets',
            'wp_minify_assets_section',
            array('name' => 'exclude_js', 'description' => __('Comma-separated list of JavaScript handles to exclude', 'wp-minify-assets'))
        );

        add_settings_field(
            'enable_logging',
            __('Enable Logging', 'wp-minify-assets'),
            array($this, 'checkbox_field_render'),
            'wp_minify_assets',
            'wp_minify_assets_section',
            array('name' => 'enable_logging', 'description' => __('Log minification results for debugging', 'wp-minify-assets'))
        );
    }
    
    public function sanitize_options($input) {
    $output = array(
        'minify_html' => false,
        'minify_css' => false,
        'minify_js' => false,
        'enable_logging' => false,
        'exclude_css' => array(),
        'exclude_js' => array(),
    );

    // Process checkboxes
    if (!empty($input['minify_html'])) {
        $output['minify_html'] = true;
    }
    if (!empty($input['minify_css'])) {
        $output['minify_css'] = true;
    }
    if (!empty($input['minify_js'])) {
        $output['minify_js'] = true;
    }
    if (!empty($input['enable_logging'])) {
        $output['enable_logging'] = true;
    }

    // Process exclude lists
    if (!empty($input['exclude_css'])) {
        $value = $input['exclude_css'];
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }
        $output['exclude_css'] = array_filter($value);
    }

    if (!empty($input['exclude_js'])) {
        $value = $input['exclude_js'];
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }
        $output['exclude_js'] = array_filter($value);
    }

    return $output;
}

    public function checkbox_field_render($args) {
        $name = $args['name'];
        ?>
        <input type="checkbox" name="wp_minify_assets_options[<?php echo esc_attr($name); ?>]" <?php checked($this->options[$name], true); ?> value="1">
        <?php
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function text_field_render($args) {
    $name = $args['name'];
    $value = is_array($this->options[$name]) ? implode(', ', $this->options[$name]) : $this->options[$name];
    ?>
    <input type="text" name="wp_minify_assets_options[<?php echo esc_attr($name); ?>]" value="<?php echo esc_attr($value); ?>" class="regular-text">
    <?php
    if (!empty($args['description'])) {
        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }
}

    public function settings_section_callback() {
        echo __('Configure which assets should be minified and any exclusions.', 'wp-minify-assets');
    }

    public function options_page() {
        ?>
        <div class="wrap">
            <h1>WP Minify Assets</h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('wp_minify_assets');
                do_settings_sections('wp_minify_assets');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
  
  
  
  
  

  
  
  
  
  

    public function minify_style_tag($tag, $handle, $href, $media) {
        if (!$this->options['minify_css'] || !$this->should_minify('css', $handle)) {
            return $tag;
        }

        if (strpos($href, site_url()) === false && !wp_http_validate_url($href)) {
            return $tag;
        }

        $file_path = $this->get_local_path_from_url($href);
        if (!$file_path || !file_exists($file_path)) {
            return $tag;
        }

        try {
            $minifier = new \MatthiasMullie\Minify\CSS($file_path);
            $minified = $minifier->minify();
            
              // Convert relative paths to absolute paths
        $minified = $this->convert_relative_paths($minified, $file_path);
            
            $cache_dir = WP_CONTENT_DIR . '/cache/wp-minify-assets/';
            if (!file_exists($cache_dir)) {
                wp_mkdir_p($cache_dir);
            }
            
            $cache_file = $cache_dir . 'style-' . $handle . '-' . md5($minified) . '.min.css';
            
            if (!file_exists($cache_file)) {
                file_put_contents($cache_file, $minified);
            }
            
            $cache_url = content_url('/cache/wp-minify-assets/' . basename($cache_file));
            $tag = str_replace($href, $cache_url, $tag);
            
            if ($this->options['enable_logging']) {
                error_log('CSS minification completed for: ' . $handle . ' (' . $href . ')');
            }
        } catch (Exception $e) {
            if ($this->options['enable_logging']) {
                error_log('CSS minification error for ' . $handle . ': ' . $e->getMessage());
            }
        }

        return $tag;
    }
    
  
  
  
  
  
    
    
    
     private function convert_relative_paths($css, $original_css_path) {
        $css_dir = dirname($original_css_path);
        $css_url = content_url(str_replace(WP_CONTENT_DIR, '', $css_dir));

        return preg_replace_callback(
            '/url\(\s*[\'"]?(?![a-z]+:|\/)([^\'"\)]+)[\'"]?\s*\)/i',
            function($matches) use ($css_url) {
                $relative_path = trim($matches[1]);
                $absolute_url = trailingslashit($css_url) . ltrim($relative_path, '/');
                return "url('{$absolute_url}')";
            },
            $css
        );
    }
    
    
    
    
    
    
    

    public function minify_script_tag($tag, $handle, $src) {
    // Preserve delayed script attributes
    if (strpos($tag, 'data-macp-delayed="true"') !== false ||
        strpos($tag, 'type="rocketlazyloadscript"') !== false || 
        strpos($tag, 'data-rocket-src=') !== false) {
        return $tag;
    }

    if (!$this->options['minify_js'] || !$this->should_minify('js', $handle)) {
        return $tag;
    }

    if (strpos($src, site_url()) === false || !wp_http_validate_url($src)) {
        return $tag;
    }

    $file_path = $this->get_local_path_from_url($src);
    if (!$file_path || !file_exists($file_path)) {
        return $tag;
    }

    try {
        $minifier = new \MatthiasMullie\Minify\JS($file_path);
        $minified = $minifier->minify();

        $cache_dir = WP_CONTENT_DIR . '/cache/wp-minify-assets/';
        wp_mkdir_p($cache_dir);

        $cache_file = $cache_dir . 'script-' . $handle . '-' . md5($minified) . '.min.js';

        if (!file_exists($cache_file)) {
            file_put_contents($cache_file, $minified);
        }

        $cache_url = content_url('/cache/wp-minify-assets/' . basename($cache_file));
        $tag = str_replace($src, $cache_url, $tag);

        if ($this->options['enable_logging']) {
            error_log("JS minification completed for: $handle ($src)");
        }
    } catch (Exception $e) {
        if ($this->options['enable_logging']) {
            error_log("JS minification error for $handle: " . $e->getMessage());
        }
    }

    return $tag;
}





    private function schedule_cleanup() {
        if (!wp_next_scheduled('wp_minify_assets_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'wp_minify_assets_cleanup');
        }
    }

    public function cleanup_cache() {
        $cache_dir = WP_CONTENT_DIR . '/cache/wp-minify-assets/';
        
        if (!file_exists($cache_dir)) {
            return;
        }

        $files = glob($cache_dir . '*');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $filemtime = filemtime($file);
                if ($filemtime && (time() - $filemtime) >= 30 * DAY_IN_SECONDS) {
                    unlink($file);
                    $deleted++;
                }
            }
        }

        if ($this->options['enable_logging'] && $deleted > 0) {
            error_log('WP Minify Assets: Cleaned up ' . $deleted . ' cached files');
        }
    }

    public function clear_all_cache() {
        $cache_dir = WP_CONTENT_DIR . '/cache/wp-minify-assets/';
        
        if (!file_exists($cache_dir)) {
            return false;
        }

        $files = glob($cache_dir . '*');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $deleted++;
            }
        }

        if ($this->options['enable_logging']) {
            error_log('WP Minify Assets: Cleared all cache (' . $deleted . ' files)');
        }

        return $deleted;
    }

    public function admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $wp_admin_bar->add_node(array(
            'id'    => 'wp_minify_assets',
            'title' => 'Minify Assets',
            'href'  => admin_url('options-general.php?page=wp_minify_assets'),
            'meta'  => array(
                'title' => __('Minify Assets Settings'),
            )
        ));

        $wp_admin_bar->add_node(array(
            'parent' => 'wp_minify_assets',
            'id'     => 'wp_minify_assets_clear_cache',
            'title'  => __('Clear Cache'),
            'href'   => wp_nonce_url(admin_url('options-general.php?page=wp_minify_assets&clear_cache=1'), 'clear_minify_cache'),
            'meta'   => array(
                'title' => __('Clear all minified assets cache'),
            )
        ));
    }

    public function handle_clear_cache() {
        if (isset($_GET['clear_cache']) && isset($_GET['page']) && 
            $_GET['page'] === 'wp_minify_assets' &&
            check_admin_referer('clear_minify_cache')) {
            
            $cleared = $this->clear_all_cache();
            add_settings_error(
                'wp_minify_assets_messages',
                'wp_minify_assets_message',
                sprintf(__('Cache cleared successfully. %d files removed.', 'wp-minify-assets'), $cleared),
                'success'
            );
        }
    }

    public function plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=wp_minify_assets'),
            __('Settings')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    private function get_local_path_from_url($url) {
        $parsed_url = parse_url($url);
        if (!isset($parsed_url['path'])) {
            return false;
        }

        $site_url = parse_url(site_url());
        if (isset($parsed_url['host']) && $parsed_url['host'] !== $site_url['host']) {
            return false;
        }

        $path = ltrim($parsed_url['path'], '/');
        $abs_path = ABSPATH . $path;
        
        if (file_exists($abs_path)) {
            return $abs_path;
        }

        $content_path = trailingslashit(WP_CONTENT_DIR) . $path;
        if (file_exists($content_path)) {
            return $content_path;
        }

        $plugins_path = trailingslashit(WP_PLUGIN_DIR) . $path;
        if (file_exists($plugins_path)) {
            return $plugins_path;
        }

        $themes_path = trailingslashit(get_theme_root()) . $path;
        if (file_exists($themes_path)) {
            return $themes_path;
        }

        return false;
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    if (class_exists('MatthiasMullie\\Minify\\CSS') && 
        class_exists('MatthiasMullie\\Minify\\JS') && 
        class_exists('voku\\helper\\HtmlMin')) {
        new WP_Minify_Assets();
    }
});

// Register deactivation hook
register_deactivation_hook(__FILE__, function() {
    $minify = new WP_Minify_Assets();
    $minify->cleanup_cache();
    wp_clear_scheduled_hook('wp_minify_assets_cleanup');
});