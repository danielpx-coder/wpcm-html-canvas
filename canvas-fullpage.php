<?php
/**
 * Template: Página isolada (sem tema).
 *
 * Mantém wp_head()/wp_footer() para admin bar, analytics e SEO.
 *
 * @package WPCM\HTMLCanvas
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$post_id  = get_the_ID();
$snippets = get_post_meta( $post_id, '_wpcm_snippets', true );

if ( ! is_array( $snippets ) || empty( $snippets ) ) {
    wp_safe_redirect( home_url() );
    exit;
}

$snippet  = $snippets[0];
$raw_html = $snippet['code_raw'] ?? '';
$is_full  = (bool) preg_match( '/<!doctype\b|<html[\s>]/i', $raw_html );

if ( $is_full ) :
    $output = $raw_html;

    ob_start();
    wp_head();
    $wp_head = ob_get_clean();

    ob_start();
    wp_footer();
    $wp_foot = ob_get_clean();

    $admin_css = '';
    if ( is_admin_bar_showing() ) {
        $admin_css = '<style>html{margin-top:32px!important}@media screen and (max-width:782px){html{margin-top:46px!important}}</style>';
    }

    if ( stripos( $output, '</head>' ) !== false ) {
        $output = substr_replace(
            $output,
            $admin_css . "\n" . $wp_head . "\n</head>",
            stripos( $output, '</head>' ),
            strlen( '</head>' )
        );
    } elseif ( preg_match( '/<body[^>]*>/i', $output, $bm, PREG_OFFSET_CAPTURE ) ) {
        $pos    = $bm[0][1];
        $output = substr( $output, 0, $pos ) . "<head>\n{$admin_css}\n{$wp_head}\n</head>\n" . substr( $output, $pos );
    } else {
        $output = "<head>\n{$admin_css}\n{$wp_head}\n</head>\n" . $output;
    }

    if ( stripos( $output, '</body>' ) !== false ) {
        $output = substr_replace(
            $output,
            $wp_foot . "\n</body>",
            stripos( $output, '</body>' ),
            strlen( '</body>' )
        );
    } else {
        $output .= "\n" . $wp_foot;
    }

    echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML bruto por design.
else :
    $page_title = trim( wp_strip_all_tags( get_the_title( $post_id ) ) );
    if ( '' === $page_title ) {
        $page_title = get_bloginfo( 'name' );
    }
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $page_title ); ?></title>
    <?php wp_head(); ?>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #fff; min-height: 100vh; }
        <?php if ( is_admin_bar_showing() ) : ?>
        html { margin-top: 32px !important; }
        @media screen and (max-width: 782px) { html { margin-top: 46px !important; } }
        <?php endif; ?>
    </style>
</head>
<body <?php body_class( 'wpcm-isolated-page' ); ?>>
    <?php
    $wid  = 'wpcm-block-' . esc_attr( $snippet['id'] );
    $code = $snippet['code'] ?? $raw_html;
    echo '<div id="' . $wid . '" class="wpcm-canvas-block">';
    echo do_shortcode( $code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '</div>';
    ?>
    <?php wp_footer(); ?>
</body>
</html>
<?php endif; ?>
