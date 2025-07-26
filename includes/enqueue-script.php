<?php
defined('ABSPATH') || exit;

add_action('acf/input/admin_enqueue_scripts', function () {
  $screen = get_current_screen();
  if ($screen && $screen->post_type === 'servizi_cliente') {
    wp_enqueue_script(
      'spm-acf-dynamic-values',
      plugin_dir_url(__FILE__) . 'assets/js/acf-dynamic-values.js', // ✅ senza ../
      ['acf-input'],
      '1.0',
      true
    );
  }
});
