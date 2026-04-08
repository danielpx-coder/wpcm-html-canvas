<?php
/**
 * WPCM HTML Canvas — Limpeza ao deletar o plugin.
 *
 * Remove todos os post_meta criados pelo plugin.
 * Só executa quando o usuário deleta via WP Admin.
 *
 * @package WPCM\HTMLCanvas
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

// Remove metas em lote (eficiente para sites grandes)
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_wpcm_snippets', '_wpcm_mode')" );

// Limpa cache de objetos
wp_cache_flush();
