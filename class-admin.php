<?php
/**
 * Admin: metabox, assets, AJAX e salvamento.
 *
 * @package WPCM\HTMLCanvas
 */

namespace WPCM;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {

    private static ?self $instance = null;

    public static function boot(): self {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes',        [ $this, 'register_metabox' ] );
        add_action( 'save_post',             [ $this, 'save_metabox' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_wpcm_new_id',   [ $this, 'ajax_new_id' ] );
    }

    public function ajax_new_id(): void {
        check_ajax_referer( 'wpcm_ajax', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error();
        wp_send_json_success( [ 'id' => wpcm_make_id() ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;

        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->post_type, apply_filters( 'wpcm_post_types', [ 'post', 'page' ] ), true ) ) {
            return;
        }

        $settings = wp_enqueue_code_editor( [
            'type'       => 'text/html',
            'codemirror' => [
                'mode'               => 'htmlmixed',
                'lineNumbers'        => true,
                'lineWrapping'       => true,
                'indentUnit'         => 2,
                'tabSize'            => 2,
                'indentWithTabs'     => false,
                'autoCloseTags'      => true,
                'autoCloseBrackets'  => true,
                'matchBrackets'      => true,
                'matchTags'          => [ 'bothTags' => true ],
                'foldGutter'         => true,
                'gutters'            => [ 'CodeMirror-linenumbers', 'CodeMirror-foldgutter' ],
            ],
        ] );

        wp_enqueue_style( 'wpcm-admin', WPCM_URL . 'assets/css/admin.css', [], WPCM_VERSION );
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_script( 'wpcm-admin', WPCM_URL . 'assets/js/admin.js', [ 'jquery', 'jquery-ui-sortable', 'wp-theme-plugin-editor' ], WPCM_VERSION, true );
        wp_localize_script( 'wpcm-admin', 'WPCM', [
            'ajax'   => admin_url( 'admin-ajax.php' ),
            'nonce'  => wp_create_nonce( 'wpcm_ajax' ),
            'prefix' => wpcm_site_prefix(),
            'cm'     => $settings !== false ? $settings : false,
            'postId' => get_the_ID(),
            'i18n'   => [
                'confirm_remove'    => esc_html__( 'Remover este bloco?', 'wpcm-html-canvas' ),
                'copied'            => esc_html__( 'Copiado!', 'wpcm-html-canvas' ),
                'copy'              => esc_html__( 'Copiar', 'wpcm-html-canvas' ),
                'copy_shortcode'    => esc_html__( 'Copiar shortcode', 'wpcm-html-canvas' ),
                'insert_editor'     => esc_html__( 'Inserir no editor', 'wpcm-html-canvas' ),
                'inserted'          => esc_html__( 'Inserido!', 'wpcm-html-canvas' ),
                'duplicate'         => esc_html__( 'Duplicar', 'wpcm-html-canvas' ),
                'duplicated'        => esc_html__( 'Duplicado!', 'wpcm-html-canvas' ),
                'generating'        => esc_html__( 'gerando…', 'wpcm-html-canvas' ),
                'preview_empty'     => esc_html__( 'Cole o HTML acima para visualizar.', 'wpcm-html-canvas' ),
                'label_default'     => esc_html__( 'Novo bloco', 'wpcm-html-canvas' ),
                'catalog_empty'     => esc_html__( 'Crie blocos para gerar os shortcodes de inserção.', 'wpcm-html-canvas' ),
                'reorder_help'      => esc_html__( 'Arraste para reordenar.', 'wpcm-html-canvas' ),
                'classic_only'      => esc_html__( 'No editor em blocos, cole o shortcode em um bloco “Shortcode” ou “Parágrafo”.', 'wpcm-html-canvas' ),
                'saved_tip'         => esc_html__( 'Dica: depois de salvar a postagem, você poderá reutilizar estes shortcodes em outras áreas e até em outras páginas usando post_id.', 'wpcm-html-canvas' ),
                'external_copy'     => esc_html__( 'Copie a versão com post_id para usar este bloco fora da postagem atual.', 'wpcm-html-canvas' ),
                'enable_editor'     => esc_html__( 'Ativar editor avançado', 'wpcm-html-canvas' ),
                'editor_enabled'    => esc_html__( 'Editor avançado ativo', 'wpcm-html-canvas' ),
            ],
        ] );
    }

    public function register_metabox(): void {
        $types = apply_filters( 'wpcm_post_types', [ 'post', 'page' ] );
        foreach ( $types as $pt ) {
            add_meta_box(
                'wpcm_metabox',
                esc_html__( 'WPCM HTML Canvas', 'wpcm-html-canvas' ),
                [ $this, 'render_metabox' ],
                $pt,
                'normal',
                'high'
            );
        }
    }

    public function render_metabox( \WP_Post $post ): void {
        wp_nonce_field( 'wpcm_save', '_wpcm_nonce' );

        $snippets = get_post_meta( $post->ID, '_wpcm_snippets', true );
        $mode     = get_post_meta( $post->ID, '_wpcm_mode', true ) ?: 'shortcode';
        if ( ! is_array( $snippets ) ) $snippets = [];

        $prefix = esc_html( wpcm_site_prefix() );
        ?>
        <div class="wpcm-wrap" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
            <ul class="wpcm-tabs">
                <li class="active" data-tab="blocks"><?php esc_html_e( 'Blocos HTML / CSS / JS', 'wpcm-html-canvas' ); ?></li>
                <li data-tab="library"><?php esc_html_e( 'Inserção no conteúdo', 'wpcm-html-canvas' ); ?></li>
                <li data-tab="config"><?php esc_html_e( 'Configurações', 'wpcm-html-canvas' ); ?></li>
            </ul>

            <div id="wpcm-tab-blocks" class="wpcm-panel active">
                <div class="wpcm-hero">
                    <div>
                        <h3><?php esc_html_e( 'Monte vários blocos e distribua-os ao longo da postagem', 'wpcm-html-canvas' ); ?></h3>
                        <p><?php esc_html_e( 'Você pode mesclar texto normal do WordPress com cards, tabelas, layouts, HTML, CSS e JS em vários pontos do conteúdo usando um shortcode por bloco.', 'wpcm-html-canvas' ); ?></p>
                    </div>
                    <div class="wpcm-hero-actions">
                        <button type="button" class="button button-primary" id="wpcm-add"><?php esc_html_e( '+ Novo bloco', 'wpcm-html-canvas' ); ?></button>
                    </div>
                </div>

                <div class="wpcm-notice wpcm-notice-grid">
                    <div>
                        <strong><?php esc_html_e( 'IDs automáticos', 'wpcm-html-canvas' ); ?></strong>
                        <p><?php printf(
                            esc_html__( 'Cada bloco recebe um ID único com o prefixo do site: %sXXXXXX', 'wpcm-html-canvas' ),
                            '<code>' . $prefix . '</code>'
                        ); ?></p>
                    </div>
                    <div>
                        <strong><?php esc_html_e( 'Shortcodes limpos em SEO/social', 'wpcm-html-canvas' ); ?></strong>
                        <p><?php esc_html_e( 'Os shortcodes são removidos automaticamente de OG, Twitter, RSS e descrições para evitar ruído nas prévias.', 'wpcm-html-canvas' ); ?></p>
                    </div>
                    <div>
                        <strong><?php esc_html_e( 'Fluxo editorial recomendado', 'wpcm-html-canvas' ); ?></strong>
                        <p><?php esc_html_e( 'Crie os blocos aqui, salve/rascunhe a postagem e depois distribua os shortcodes onde quiser no conteúdo normal.', 'wpcm-html-canvas' ); ?></p>
                    </div>
                </div>

                <div id="wpcm-list">
                    <?php foreach ( $snippets as $i => $s ) $this->render_block( $i, $s, $post->ID ); ?>
                </div>

                <script type="text/html" id="tmpl-wpcm-block">
                    <?php $this->render_block( '{idx}', [ 'id' => '', 'label' => '', 'code_raw' => '' ], $post->ID ); ?>
                </script>
            </div>

            <div id="wpcm-tab-library" class="wpcm-panel">
                <div class="wpcm-library-head">
                    <div>
                        <h3><?php esc_html_e( 'Shortcodes para inserir no conteúdo da postagem', 'wpcm-html-canvas' ); ?></h3>
                        <p><?php esc_html_e( 'Use os shortcodes abaixo para espalhar os blocos no meio do texto normal da postagem. No editor clássico, você pode inseri-los automaticamente. No editor em blocos, cole em um bloco “Shortcode” ou “Parágrafo”.', 'wpcm-html-canvas' ); ?></p>
                    </div>
                    <div class="wpcm-library-tip">
                        <span class="dashicons dashicons-editor-help"></span>
                        <span><?php esc_html_e( 'Para usar o mesmo bloco em outra página/post, prefira a versão com post_id.', 'wpcm-html-canvas' ); ?></span>
                    </div>
                </div>
                <div id="wpcm-shortcode-catalog" class="wpcm-catalog" aria-live="polite"></div>
            </div>

            <div id="wpcm-tab-config" class="wpcm-panel">
                <h3><?php esc_html_e( 'Modo de renderização', 'wpcm-html-canvas' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Exibição', 'wpcm-html-canvas' ); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="wpcm_mode" value="shortcode" <?php checked( $mode, 'shortcode' ); ?>>
                                    <strong><?php esc_html_e( 'Shortcode', 'wpcm-html-canvas' ); ?></strong> —
                                    <?php esc_html_e( 'Os blocos são renderizados dentro do tema, preservando cabeçalho, rodapé e permitindo mescla com o conteúdo da postagem.', 'wpcm-html-canvas' ); ?>
                                </label><br><br>
                                <label>
                                    <input type="radio" name="wpcm_mode" value="isolated" <?php checked( $mode, 'isolated' ); ?>>
                                    <strong><?php esc_html_e( 'Página isolada', 'wpcm-html-canvas' ); ?></strong> —
                                    <?php esc_html_e( 'A postagem passa a exibir o primeiro bloco como uma página HTML independente.', 'wpcm-html-canvas' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Para usar vários blocos ao longo do texto, mantenha o modo Shortcode.', 'wpcm-html-canvas' ); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    private function render_block( $idx, array $s, int $post_id = 0 ): void {
        $id        = isset( $s['id'] ) ? Sanitizer::clean_id( (string) $s['id'] ) : '';
        $label_raw = isset( $s['label'] ) ? (string) $s['label'] : '';
        $label     = '' !== trim( $label_raw ) ? $label_raw : __( 'Novo bloco', 'wpcm-html-canvas' );
        $code      = isset( $s['code_raw'] ) ? (string) $s['code_raw'] : '';
        $shortcode = $id ? '[wpcm_canvas id="' . $id . '"]' : '[wpcm_canvas id=""]';
        $external_shortcode = $id ? '[wpcm_canvas id="' . $id . '" post_id="' . absint( $post_id ) . '"]' : '[wpcm_canvas id="" post_id="' . absint( $post_id ) . '"]';
        ?>
        <div class="wpcm-block" data-index="<?php echo esc_attr( $idx ); ?>" data-block-id="<?php echo esc_attr( $id ); ?>">
            <div class="wpcm-block-head">
                <div class="wpcm-drag" title="<?php esc_attr_e( 'Arraste para reordenar', 'wpcm-html-canvas' ); ?>">
                    <span class="dashicons dashicons-move"></span>
                </div>
                <div class="wpcm-head-main">
                    <div class="wpcm-label-row">
                        <label class="wpcm-label-field">
                            <span><?php esc_html_e( 'Nome do bloco', 'wpcm-html-canvas' ); ?></span>
                            <input type="text" name="wpcm_s[<?php echo esc_attr( $idx ); ?>][label]" class="regular-text wpcm-label-input" value="<?php echo esc_attr( $label_raw ); ?>" placeholder="<?php esc_attr_e( 'Ex.: Card de destaque, tabela, banner, chamada, galeria…', 'wpcm-html-canvas' ); ?>">
                        </label>
                        <div class="wpcm-id-area">
                            <label><?php esc_html_e( 'ID', 'wpcm-html-canvas' ); ?></label>
                            <input type="text" name="wpcm_s[<?php echo esc_attr( $idx ); ?>][id]" class="wpcm-id" value="<?php echo esc_attr( $id ); ?>" readonly>
                            <button type="button" class="button button-small wpcm-newid"><?php esc_html_e( 'Novo ID', 'wpcm-html-canvas' ); ?></button>
                        </div>
                    </div>
                    <div class="wpcm-shortcode-stack">
                        <div class="wpcm-shortcode-line">
                            <span class="wpcm-shortcode-label"><?php esc_html_e( 'Shortcode da postagem atual', 'wpcm-html-canvas' ); ?></span>
                            <code class="wpcm-shortcode-current"><?php echo esc_html( $shortcode ); ?></code>
                        </div>
                        <div class="wpcm-shortcode-line wpcm-shortcode-line-secondary">
                            <span class="wpcm-shortcode-label"><?php esc_html_e( 'Shortcode com post_id (uso externo)', 'wpcm-html-canvas' ); ?></span>
                            <code class="wpcm-shortcode-external"><?php echo esc_html( $external_shortcode ); ?></code>
                        </div>
                    </div>
                </div>
                <div class="wpcm-actions">
                    <button type="button" class="button button-secondary wpcm-copy"><?php esc_html_e( 'Copiar shortcode', 'wpcm-html-canvas' ); ?></button>
                    <button type="button" class="button button-secondary wpcm-insert-editor"><?php esc_html_e( 'Inserir no editor', 'wpcm-html-canvas' ); ?></button>
                    <button type="button" class="button button-secondary wpcm-duplicate"><?php esc_html_e( 'Duplicar', 'wpcm-html-canvas' ); ?></button>
                    <button type="button" class="button button-secondary wpcm-enable-editor"><?php esc_html_e( 'Ativar editor avançado', 'wpcm-html-canvas' ); ?></button>
                    <button type="button" class="button button-secondary wpcm-preview-btn"><?php esc_html_e( 'Prévia', 'wpcm-html-canvas' ); ?></button>
                    <button type="button" class="button button-link-delete wpcm-del"><?php esc_html_e( 'Remover', 'wpcm-html-canvas' ); ?></button>
                </div>
            </div>
            <div class="wpcm-block-body">
                <textarea name="wpcm_s[<?php echo esc_attr( $idx ); ?>][code]" class="wpcm-editor" rows="20" spellcheck="false" data-cm-init="0" placeholder="<?php esc_attr_e( 'Cole aqui o HTML/CSS/JS completo.', 'wpcm-html-canvas' ); ?>"><?php echo esc_textarea( $code ); ?></textarea>
                <p class="description wpcm-editor-help"><?php esc_html_e( 'Para evitar travamentos em postagens grandes, o editor avançado só é ativado quando você clicar no botão correspondente.', 'wpcm-html-canvas' ); ?></p>
            </div>
            <div class="wpcm-block-preview" style="display:none;">
                <p class="wpcm-preview-label"><?php esc_html_e( 'Pré-visualização (sandbox):', 'wpcm-html-canvas' ); ?></p>
                <iframe class="wpcm-iframe" sandbox="allow-scripts allow-same-origin" style="width:100%;min-height:500px;border:1px solid #ccc;background:#fff;border-radius:10px;"></iframe>
            </div>
        </div>
        <?php
    }

    public function save_metabox( int $post_id, \WP_Post $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( ! isset( $_POST['_wpcm_nonce'] ) ) return;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpcm_nonce'] ) ), 'wpcm_save' ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $allowed_types = apply_filters( 'wpcm_post_types', [ 'post', 'page' ] );
        if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
            return;
        }

        $mode = sanitize_key( wp_unslash( $_POST['wpcm_mode'] ?? 'shortcode' ) );
        if ( ! in_array( $mode, [ 'shortcode', 'isolated' ], true ) ) $mode = 'shortcode';
        update_post_meta( $post_id, '_wpcm_mode', $mode );

        $items = isset( $_POST['wpcm_s'] ) ? wp_unslash( $_POST['wpcm_s'] ) : [];
        if ( ! is_array( $items ) ) $items = [];

        $snippets = [];
        $used_ids = [];

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $raw = isset( $item['code'] ) ? (string) $item['code'] : '';
            if ( '' === trim( $raw ) ) {
                continue;
            }

            $label = isset( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '';
            $sid   = ! empty( $item['id'] ) ? Sanitizer::clean_id( (string) $item['id'] ) : wpcm_make_id();

            if ( '' === $sid || isset( $used_ids[ $sid ] ) || wpcm_id_exists( $sid, $post_id ) ) {
                $sid = wpcm_make_id();
            }
            $used_ids[ $sid ] = true;

            $safe      = Sanitizer::clean( $raw );
            $processed = $safe;

            if ( 'shortcode' === $mode ) {
                $scoper    = new CSS_Scoper( '#wpcm-block-' . $sid );
                $processed = $this->transform( $safe, $sid, $scoper );
            }

            $snippets[] = [
                'id'       => $sid,
                'label'    => $label,
                'code'     => $processed,
                'code_raw' => $raw,
            ];
        }

        update_post_meta( $post_id, '_wpcm_snippets', $snippets );
    }

    private function transform( string $html, string $id, CSS_Scoper $scoper ): string {
        $html = trim( $html );
        if ( '' === $html ) return '';

        $w = '#wpcm-block-' . $id;

        $css_chunks = [];
        if ( preg_match_all( '/<style[^>]*>(.*?)<\/style>/is', $html, $m ) ) {
            $css_chunks = $m[1];
        }

        $css_links = [];
        if ( preg_match_all( '/<link[^>]+rel=["\']stylesheet["\'][^>]*\/?>/i', $html, $m ) ) {
            $css_links = array_unique( $m[0] );
        }

        $scripts = [];
        if ( preg_match_all( '/<script\b[^>]*>.*?<\/script>/is', $html, $m ) ) {
            $scripts = $m[0];
        }

        if ( preg_match( '/<body[^>]*>(.*)<\/body>/is', $html, $m ) ) {
            $body = $m[1];
        } else {
            $body = $html;
            $body = preg_replace( '/<!DOCTYPE[^>]*>/i', '', $body );
            $body = preg_replace( '/<html[^>]*>|<\/html>/i', '', $body );
            $body = preg_replace( '/<head[^>]*>.*?<\/head>/is', '', $body );
            $body = preg_replace( '/<\/?body[^>]*>/i', '', $body );
        }

        $body = preg_replace( '/<style[^>]*>.*?<\/style>/is', '', $body );
        $body = preg_replace( '/<link[^>]+rel=["\']stylesheet["\'][^>]*\/?>/i', '', $body );
        $body = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $body );

        $scoped_css = '';
        foreach ( $css_chunks as $chunk ) {
            $scoped_css .= $scoper->scope( $chunk ) . "\n";
        }

        $out = '';

        if ( $css_links ) {
            $out .= implode( "\n", $css_links ) . "\n";
        }

        if ( '' !== trim( $scoped_css ) ) {
            $out .= '<style data-wpcm="' . esc_attr( $id ) . '">' . "\n";
            $out .= "{$w} {\n  all: initial;\n  display: block;\n  box-sizing: border-box;\n}\n";
            $out .= "{$w}, {$w} * {\n  font-family: inherit;\n}\n";
            $out .= "{$w} *, {$w} *::before, {$w} *::after {\n  box-sizing: inherit;\n}\n";
            $out .= $scoped_css;
            $out .= "</style>\n";
        }

        $out .= trim( $body );

        if ( $scripts ) {
            $out .= "\n" . implode( "\n", $scripts );
        }

        return $out;
    }
}
