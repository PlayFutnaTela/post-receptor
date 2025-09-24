<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Post_Receptor_API_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_api_settings_menu' ) );
        add_action( 'admin_init', array( $this, 'register_api_settings' ) );
    }

    public function add_api_settings_menu() {
        add_submenu_page(
            'post-receptor',
            __( 'Configurações da API GPT', 'post-receptor' ),
            __( 'API GPT', 'post-receptor' ),
            'manage_options',
            'post-receptor-api-settings',
            array( $this, 'render_api_settings_page' )
        );
    }

    public function register_api_settings() {
        // Ajustamos a sanitization_callback
        register_setting( 'post_receptor_api_settings_group', 'post_receptor_openai_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'post_receptor_sanitize_openai_key', // Callback definida abaixo
            'default'           => '',
        ) );

        // Mantemos o nome antigo do prompt para não perder o valor
        register_setting( 'post_receptor_api_settings_group', 'post_receptor_system_prompt', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => 'You are a professional translator. Translate the text...',
        ) );
    }

    public function render_api_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Você não tem permissão para acessar esta página.', 'post-receptor' ) );
        }

        $api_key       = get_option( 'post_receptor_openai_api_key', '' );
        $system_prompt = get_option( 'post_receptor_system_prompt', '' );

        $revoke_nonce = wp_create_nonce( 'post_receptor_revoke_openai_key_nonce' );
        ?>
        <div class="wrap">
            <h1><?php _e( 'Configurações da API GPT', 'post-receptor' ); ?></h1>
            <form method="post" action="options.php">
                <?php 
                settings_fields( 'post_receptor_api_settings_group' );
                do_settings_sections( 'post_receptor_api_settings_group' );
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e( 'OpenAI API Key', 'post-receptor' ); ?></th>
                        <td>
                            <?php if ( ! empty( $api_key ) ) : ?>
                                <p style="color:green;font-weight:bold;">
                                    <?php _e( 'Chave cadastrada com sucesso!', 'post-receptor' ); ?>
                                </p>
                                <button type="button" class="button button-danger" id="revoke_openai_key" style="background-color:#a00;color:#fff;">
                                    <?php _e( 'Revogar Chave', 'post-receptor' ); ?>
                                </button>
                                <p class="description">
                                    <?php _e( 'Se revogar, a tradução via OpenAI parará de funcionar até inserir nova chave.', 'post-receptor' ); ?>
                                </p>
                            <?php else : ?>
                                <input type="text" name="post_receptor_openai_api_key" value="" style="width:100%;" placeholder="<?php _e( 'Insira sua API Key', 'post-receptor' ); ?>" />
                                <p class="description">
                                    <?php _e( 'Esta chave será usada para realizar as traduções via GPT. Não será exibida novamente após salvar.', 'post-receptor' ); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e( 'System Prompt', 'post-receptor' ); ?></th>
                        <td>
                            <!-- Remover espaços extras no textarea para não gerar indentação -->
                            <textarea name="post_receptor_system_prompt" rows="5" style="width:100%;"><?php
                                echo esc_textarea($system_prompt);
                            ?></textarea>
                            <p class="description">
                                <?php _e( 'Defina aqui o texto que será usado para orientar o tradutor GPT (tom, estilo, personalidade).', 'post-receptor' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>

        <script>
        (function($){
            $(document).ready(function(){
                $('#revoke_openai_key').on('click', function(e){
                    e.preventDefault();
                    if( confirm('<?php _e( 'Tem certeza? Esta operação é irreversível e a tradução via OpenAI será interrompida.', 'post-receptor' ); ?>') ) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'post_receptor_revoke_openai_key',
                                _ajax_nonce: '<?php echo esc_js( $revoke_nonce ); ?>'
                            },
                            dataType: 'json'
                        }).done(function(response){
                            if(response.success){
                                alert('<?php _e( 'Chave revogada com sucesso.', 'post-receptor' ); ?>');
                                location.reload();
                            } else {
                                alert('<?php _e( 'Erro ao revogar a chave.', 'post-receptor' ); ?>');
                            }
                        }).fail(function(){
                            alert('<?php _e( 'Erro ao revogar a chave via AJAX.', 'post-receptor' ); ?>');
                        });
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }
}

new Post_Receptor_API_Settings();

/**
 * Callback p/ sanitize a OpenAI Key.
 * Se já existe uma key e o usuário não forneceu nada, mantemos a antiga.
 */
function post_receptor_sanitize_openai_key( $new_value ) {
    $old_value = get_option( 'post_receptor_openai_api_key', '' );
    // Se já havia uma chave e agora veio string vazia, mantemos a antiga
    if ( ! empty( $old_value ) && empty( $new_value ) ) {
        return $old_value;
    }
    // Caso contrário, usamos a nova (ou vazia)
    return sanitize_text_field( $new_value );
}

/**
 * Revoga a OpenAI Key (apaga do banco).
 */
function post_receptor_revoke_openai_key() {
    check_ajax_referer( 'post_receptor_revoke_openai_key_nonce', '_ajax_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Acesso negado.' );
    }
    // Remove a chave do banco
    update_option('post_receptor_openai_api_key', '');
    wp_send_json_success();
}
add_action( 'wp_ajax_post_receptor_revoke_openai_key', 'post_receptor_revoke_openai_key' );
