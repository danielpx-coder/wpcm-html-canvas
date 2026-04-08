<?php
/**
 * Sanitização segura de HTML para o WPCM Canvas.
 *
 * @package WPCM\HTMLCanvas
 */

namespace WPCM;

if ( ! defined( 'ABSPATH' ) ) exit;

class Sanitizer {

    public static function clean( string $html ): string {
        if ( current_user_can( 'unfiltered_html' ) ) {
            return $html;
        }

        $html = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $html );
        $html = preg_replace( '/<svg\b[^>]*>.*?<\/svg>/is', '', $html );
        $html = preg_replace( '/<math\b[^>]*>.*?<\/math>/is', '', $html );

        $html = preg_replace( '/\s+on[a-z]+\s*=\s*["\'][^"\']*["\']/i', '', $html );
        $html = preg_replace( '/\s+on[a-z]+\s*=\s*\S+/i', '', $html );

        $dangerous_attrs = [
            'href', 'src', 'xlink:href', 'formaction', 'action', 'poster', 'data', 'srcdoc',
        ];
        foreach ( $dangerous_attrs as $attr ) {
            $html = preg_replace( '/\b' . preg_quote( $attr, '/' ) . '\s*=\s*["\']\s*(javascript:|vbscript:|data:text\/html)[^"\']*["\']/i', $attr . '="#"', $html );
        }

        $html = preg_replace( '/\s+src\s*=\s*["\']data:[^"\']*["\']/i', '', $html );
        $html = preg_replace( '/\s+srcdoc\s*=\s*["\'][^"\']*["\']/i', '', $html );

        return $html;
    }

    public static function clean_id( string $id ): string {
        return sanitize_key( $id );
    }
}
