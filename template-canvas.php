<?php
/**
 * Template Name: WPCM Canvas
 * Description: Template limpo que carrega Header/Footer do tema mas substitui o miolo.
 */

if (!defined('ABSPATH')) exit;

use WPCM\HTMLCanvas\Plugin;

get_header();

// CSS inline para tentar limpar containers do tema e garantir largura total
// Isso é necessário porque cada tema tem classes diferentes (container, wrapper, site-main, etc)
?>
<style>
    /* Força reset de containers comuns de temas WordPress para dar liberdade ao Canvas */
    .site-content, #content, .site-main, .entry-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        border: none !important;
        background: transparent !important;
    }
    /* Esconde títulos automáticos */
    .entry-header, .page-header, .entry-title { display: none !important; }
</style>

<div id="wpcm-canvas-wrapper" class="wpcm-canvas-area">
    <?php
    // Renderiza o corpo extraído
    echo Plugin::get_body_content();
    ?>
</div>

<?php
get_footer();
