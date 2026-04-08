<?php
/**
 * Plugin Name:       WPCM HTML Canvas
 * Plugin URI:        https://saaecacoal.com.br/dev
 * Description:       Renderizador profissional de HTML/CSS/JS para WordPress. Dois modos: Shortcode (dentro do tema) e Página Isolada (HTML puro). IDs gerados automaticamente, proteção para compartilhamento social e motor de CSS escopado.
 * Version:           14.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Daniel Oliveira da Paixão
 * Author URI:        https://saaecacoal.com.br
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpcm-html-canvas
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WPCM_VERSION',  '14.1.0' );
define( 'WPCM_FILE',     __FILE__ );
define( 'WPCM_PATH',     plugin_dir_path( __FILE__ ) );
define( 'WPCM_URL',      plugin_dir_url( __FILE__ ) );
define( 'WPCM_SLUG',     'wpcm-html-canvas' );

require_once WPCM_PATH . 'includes/class-sanitizer.php';
require_once WPCM_PATH . 'includes/class-css-scoper.php';
require_once WPCM_PATH . 'includes/class-admin.php';
require_once WPCM_PATH . 'includes/class-front.php';

add_action( 'init', function() {
    load_plugin_textdomain( WPCM_SLUG, false, dirname( plugin_basename( WPCM_FILE ) ) . '/languages' );
});

function wpcm_site_prefix(): string {
    static $prefix = null;
    if ( null !== $prefix ) return $prefix;
    $host   = wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'site';
    $host   = preg_replace( '/^www\./i', '', $host );
    $part   = explode( '.', $host )[0] ?? 'site';
    $prefix = preg_replace( '/[^a-z0-9]/', '', strtolower( $part ) ) ?: 'site';
    return $prefix;
}

function wpcm_make_id(): string {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $max   = strlen( $chars ) - 1;
    do {
        $code = '';
        for ( $i = 0; $i < 6; $i++ ) {
            $code .= $chars[ random_int( 0, $max ) ];
        }
        $id = wpcm_site_prefix() . $code;
    } while ( wpcm_id_exists( $id ) );

    return $id;
}

function wpcm_id_exists( string $id, int $exclude_post_id = 0 ): bool {
    global $wpdb;

    $sql    = "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wpcm_snippets' AND meta_value LIKE %s";
    $params = [ '%' . $wpdb->esc_like( '"' . $id . '"' ) . '%' ];

    if ( $exclude_post_id > 0 ) {
        $sql      .= ' AND post_id != %d';
        $params[] = $exclude_post_id;
    }

    $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
    if ( empty( $rows ) ) {
        return false;
    }

    foreach ( $rows as $row ) {
        $snippets = maybe_unserialize( $row['meta_value'] ?? '' );
        if ( ! is_array( $snippets ) ) {
            continue;
        }
        foreach ( $snippets as $snippet ) {
            if ( isset( $snippet['id'] ) && $snippet['id'] === $id ) {
                return true;
            }
        }
    }

    return false;
}

if ( is_admin() ) {
    WPCM\Admin::boot();
}
WPCM\Front::boot();

register_activation_hook( WPCM_FILE, function() {
    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
        deactivate_plugins( plugin_basename( WPCM_FILE ) );
        wp_die(
            esc_html__( 'O WPCM HTML Canvas requer PHP 7.4 ou superior.', 'wpcm-html-canvas' ),
            esc_html__( 'Erro na ativação do plugin', 'wpcm-html-canvas' ),
            [ 'back_link' => true ]
        );
    }
    flush_rewrite_rules();
});

register_deactivation_hook( WPCM_FILE, function() {
    flush_rewrite_rules();
});
