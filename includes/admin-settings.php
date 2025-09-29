<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Post_Receptor_Admin_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Adiciona o menu do plugin no painel administrativo.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Post Receptor', 'post-receptor' ),
            __( 'Post Receptor', 'post-receptor' ),
            'manage_options',
            'post-receptor',
            array( $this, 'settings_page' ),
            'dashicons-download'
        );
    }

    /**
     * Registra as configurações do plugin utilizando a Settings API do WordPress.
     */
    public function register_settings() {
        register_setting( 'post_receptor_settings_group', 'post_receptor_emissor_url' );
        register_setting( 'post_receptor_settings_group', 'post_receptor_target_language' );
        register_setting( 'post_receptor_settings_group', 'post_receptor_system_prompt' );
        // Não precisamos registrar 'post_receptor_auth_token' para exibir,
        // pois não mostramos mais o token antigo.
    }

    /**
     * Renderiza a página de configurações do plugin.
     */
    public function settings_page() {
        $emissor_url    = get_option( 'post_receptor_emissor_url', '' );
        $current_locale = get_locale();
        $allowed_languages = array( 'pt_BR', 'pt_PT', 'en_US', 'en_GB', 'es_ES', 'fr_FR', 'de_DE' );
        
        // Recupera a opção já salva ou usa o locale atual como padrão
        $target_language = get_option( 'post_receptor_target_language', $current_locale );
        if ( ! in_array( $target_language, $allowed_languages ) ) {
            $target_language = $current_locale;
        }
        
        $system_prompt  = get_option( 'post_receptor_system_prompt', '' );
        // Observação: não buscamos o token aqui para exibir ao usuário
        // O token ficará armazenado no banco, mas oculto.

        // Nonce para geração do token via AJAX
        $token_nonce = wp_create_nonce( 'post_receptor_generate_token_nonce' );
        ?>
        <div class="wrap">
            <h1><?php _e( 'Configurações do Post Receptor', 'post-receptor' ); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'post_receptor_settings_group' ); ?>
                <?php do_settings_sections( 'post_receptor_settings_group' ); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e( 'URL do Emissor', 'post-receptor' ); ?></th>
                        <td>
                            <input type="url" name="post_receptor_emissor_url" value="<?php echo esc_attr( $emissor_url ); ?>" placeholder="https://exemplo.com" style="width: 100%;" required />
                            <p class="description"><?php _e( 'Digite a URL do site emissor.', 'post-receptor' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e( 'Idioma de Destino', 'post-receptor' ); ?></th>
                        <td>
                            <?php if ( in_array( $current_locale, $allowed_languages ) ) : ?>
                                <select name="post_receptor_target_language" style="width: 100%;">
                                    <option value="pt_BR" <?php selected( $target_language, 'pt_BR' ); ?>><?php _e( 'Português do Brasil', 'post-receptor' ); ?></option>
                                    <option value="pt_PT" <?php selected( $target_language, 'pt_PT' ); ?>><?php _e( 'Português de Portugal', 'post-receptor' ); ?></option>
                                    <option value="en_US" <?php selected( $target_language, 'en_US' ); ?>><?php _e( 'Inglês Americano', 'post-receptor' ); ?></option>
                                    <option value="en_GB" <?php selected( $target_language, 'en_GB' ); ?>><?php _e( 'Inglês Britânico', 'post-receptor' ); ?></option>
                                    <option value="es_ES" <?php selected( $target_language, 'es_ES' ); ?>><?php _e( 'Espanhol da Espanha', 'post-receptor' ); ?></option>
                                    <option value="fr_FR" <?php selected( $target_language, 'fr_FR' ); ?>><?php _e( 'Francês da França', 'post-receptor' ); ?></option>
                                    <option value="de_DE" <?php selected( $target_language, 'de_DE' ); ?>><?php _e( 'Alemão da Alemanha', 'post-receptor' ); ?></option>
                                </select>
                            <?php else : ?>
                                <input type="text" name="post_receptor_target_language" value="<?php echo esc_attr( $target_language ); ?>" style="width:100%;" />
                            <?php endif; ?>
                            <p class="description"><?php _e( 'Selecione ou insira o idioma de destino para as traduções.', 'post-receptor' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e( 'System Prompt', 'post-receptor' ); ?></th>
                        <td>
                            <textarea name="post_receptor_system_prompt" rows="5" style="width: 100%;" placeholder="<?php _e( 'Digite o texto do system prompt para a tradução.', 'post-receptor' ); ?>"><?php echo esc_textarea( $system_prompt ); ?></textarea>
                            <p class="description"><?php _e( 'Defina o texto do system prompt que será usado na tradução.', 'post-receptor' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e( 'Token de Autenticação', 'post-receptor' ); ?></th>
                        <td>
                            <!-- Exibir o token atual para depuração (apenas os primeiros 8 caracteres) -->
                            <p><?php _e( 'Token atual:', 'post-receptor' ); ?> <code><?php echo esc_html( substr( get_option( 'post_receptor_auth_token', '' ), 0, 8 ) . '...' ); ?></code></p>
                            <button type="button" class="button" id="generate_receptor_token">
                                <?php _e( 'Gerar Novo Token', 'post-receptor' ); ?>
                            </button>
                            <p class="description">
                                <?php _e( 'O token anterior deixará de funcionar assim que um novo for gerado. Copie-o e substitua no plugin Emissor.', 'post-receptor' ); ?>
                            </p>

                            <!-- Campo para exibir apenas o NOVO token gerado via AJAX, não carregamos do DB -->
                            <input type="text" id="receptor_token" value="" readonly style="width: 100%; display:none; margin-top:8px;" />

                            <!-- Botão de copiar, só aparece após gerar. -->
                            <button type="button" class="button" id="copy_token_btn" style="display:none; margin-top:5px;">
                                <?php _e( 'Copiar Token', 'post-receptor' ); ?>
                            </button>

                            <p id="token_notice" class="description" style="display:none;color:#dc3232;font-weight:bold;">
                                <?php _e( 'Token gerado. Copie e informe no Emissor!', 'post-receptor' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <script>
            (function($){
                $(document).ready(function(){
                    // Botão "Copiar Token"
                    $('#copy_token_btn').on('click', function(){
                        var tokenField = $('#receptor_token');
                        tokenField.select();
                        document.execCommand('copy');
                        alert('<?php _e( 'Token copiado!', 'post-receptor' ); ?>');
                    });
                    
                    // Botão "Gerar Novo Token" via AJAX
                    $('#generate_receptor_token').on('click', function(){
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'post_receptor_generate_token',
                                _ajax_nonce: '<?php echo esc_js( $token_nonce ); ?>'
                            },
                            dataType: 'json'
                        }).done(function(response){
                            if(response.success){
                                // Exibe o novo token
                                $('#receptor_token').val(response.data).show();
                                // Mostra o botão de copiar e a mensagem
                                $('#copy_token_btn').show();
                                $('#token_notice').show();
                            } else {
                                alert('<?php _e( 'Erro ao gerar token.', 'post-receptor' ); ?>');
                            }
                        }).fail(function(){
                            alert('<?php _e( 'Erro ao gerar token via AJAX.', 'post-receptor' ); ?>');
                        });
                    });
                });
            })(jQuery);
            </script>
        </div>
        <?php
    }
}

new Post_Receptor_Admin_Settings();

/**
 * Gera um token seguro para o receptor (chamado via AJAX).
 */
function post_receptor_generate_token() {
    check_ajax_referer( 'post_receptor_generate_token_nonce', '_ajax_nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Acesso negado.' );
    }
    
    try {
        // Gera um token de 32 caracteres
        $token = bin2hex( random_bytes(16) );
        // Atualiza a opção no banco
        update_option( 'post_receptor_auth_token', $token );
        // Retorna via JSON (exibimos apenas agora, uma vez)
        wp_send_json_success( $token );
    } catch ( Exception $e ) {
        wp_send_json_error( 'Erro ao gerar token.' );
    }
}
add_action( 'wp_ajax_post_receptor_generate_token', 'post_receptor_generate_token' );
