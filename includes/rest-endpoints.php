<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Função de autenticação para os endpoints REST do plugin receptor.
 *
 * Verifica se o header "Authorization" contém um token Bearer válido.
 *
 * @param WP_REST_Request $request
 * @return true|WP_Error Retorna true se autenticado; caso contrário, um WP_Error.
 */
function post_receptor_authenticate( $request ) {
    $headers = $request->get_headers();
    if ( ! isset( $headers['authorization'][0] ) ) {
        error_log('[Post Receptor] Erro de autenticação: Cabeçalho de autorização ausente');
        return new WP_Error( 'forbidden', __( 'Cabeçalho de autorização ausente.', 'post-receptor' ), array( 'status' => 403 ) );
    }
    $auth = $headers['authorization'][0];
    if ( strpos( $auth, 'Bearer ' ) !== 0 ) {
        error_log('[Post Receptor] Erro de autenticação: Formato de autorização inválido. Recebido: ' . $auth);
        return new WP_Error( 'forbidden', __( 'Formato de autorização inválido.', 'post-receptor' ), array( 'status' => 403 ) );
    }
    $token = substr( $auth, 7 );
    // Recupera o token esperado, definido na área administrativa do receptor.
    $expected_token = get_option( 'post_receptor_auth_token' );
    if ( empty( $expected_token ) ) {
        error_log('[Post Receptor] Erro de autenticação: Nenhum token configurado no receptor');
        return new WP_Error( 'forbidden', __( 'Nenhum token configurado no receptor.', 'post-receptor' ), array( 'status' => 403 ) );
    }
    if ( $token !== $expected_token ) {
        error_log('[Post Receptor] Erro de autenticação: Token inválido. Recebido: ' . substr($token, 0, 8) . '..., Esperado: ' . substr($expected_token, 0, 8) . '...');
        return new WP_Error( 'forbidden', __( 'Token inválido.', 'post-receptor' ), array( 'status' => 403 ) );
    }
    error_log('[Post Receptor] Autenticação bem sucedida para o token: ' . substr($token, 0, 8) . '...');
    return true;
}

/**
 * Endpoint para receber os dados do post enviado pelo plugin emissor.
 *
 * URL: /wp-json/post-receptor/v1/receive
 */
function post_receptor_receive_post( WP_REST_Request $request ) {
    // Autenticação
    $auth_result = post_receptor_authenticate( $request );
    if ( is_wp_error( $auth_result ) ) {
        return $auth_result;
    }

    $data = $request->get_json_params();
    if ( empty( $data ) ) {
        return new WP_Error( 'bad_request', __( 'Nenhum dado recebido.', 'post-receptor' ), array( 'status' => 400 ) );
    }

    // Processa o post recebido
    $post_receptor = new Post_Receptor();
    $post_id = $post_receptor->process_received_post( $data );

    if ( is_wp_error( $post_id ) ) {
        return $post_id;
    }

    return rest_ensure_response( array(
        'success' => true,
        'post_id' => $post_id,
    ) );
}

/**
 * Endpoint para atualizar o status de um post.
 *
 * URL: /wp-json/post-receptor/v1/update-status
 */
function post_receptor_update_status( WP_REST_Request $request ) {
    // Autenticação
    $auth_result = post_receptor_authenticate( $request );
    if ( is_wp_error( $auth_result ) ) {
        return $auth_result;
    }

    $data = $request->get_json_params();
    if ( empty( $data ) || ! isset( $data['ID'], $data['status'] ) ) {
        return new WP_Error( 'bad_request', __( 'Dados insuficientes para atualização de status.', 'post-receptor' ), array( 'status' => 400 ) );
    }

    $post_receptor = new Post_Receptor();
    $post_receptor->update_post_status( $data );

    return rest_ensure_response( array(
        'success' => true,
        'message' => __( 'Status atualizado com sucesso.', 'post-receptor' ),
    ) );
}

/**
 * Endpoint para excluir um post.
 *
 * URL: /wp-json/post-receptor/v1/delete
 */
function post_receptor_delete_post( WP_REST_Request $request ) {
    // Autenticação
    $auth_result = post_receptor_authenticate( $request );
    if ( is_wp_error( $auth_result ) ) {
        return $auth_result;
    }

    $data = $request->get_json_params();
    if ( empty( $data ) || ! isset( $data['ID'] ) ) {
        return new WP_Error( 'bad_request', __( 'ID do post ausente para exclusão.', 'post-receptor' ), array( 'status' => 400 ) );
    }

    $post_receptor = new Post_Receptor();
    $post_receptor->delete_post( $data );

    return rest_ensure_response( array(
        'success' => true,
        'message' => __( 'Post excluído com sucesso.', 'post-receptor' ),
    ) );
}

/**
 * Endpoint para verificar o token de autenticação
 *
 * URL: /wp-json/post-receptor/v1/check-token
 */
function post_receptor_check_token(WP_REST_Request $request) {
    $auth_header = $request->get_header('Authorization');
    $received_token = str_replace('Bearer ', '', $auth_header);
    $expected_token = get_option('post_receptor_auth_token');

    // Logging detalhado para depuração
    error_log('[Post Receptor] Token recebido: ' . $received_token);
    error_log('[Post Receptor] Token esperado: ' . $expected_token);

    if (empty($expected_token)) {
        error_log('[Post Receptor] Erro: Nenhum token configurado no receptor');
        return new WP_Error('no_token', 'Token não configurado no receptor', array('status' => 403));
    }

    if ($received_token !== $expected_token) {
        error_log('[Post Receptor] Erro: Token inválido. Recebido: ' . $received_token . ', Esperado: ' . $expected_token);
        return new WP_Error('invalid_token', 'Token de autenticação inválido', array('status' => 403));
    }

    return array('success' => true);
}

/**
 * Registra os endpoints REST do plugin receptor.
 */
function post_receptor_register_routes() {
    register_rest_route( 'post-receptor/v1', '/receive', array(
        'methods'  => 'POST',
        'callback' => 'post_receptor_receive_post',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'post-receptor/v1', '/update-status', array(
        'methods'  => 'POST',
        'callback' => 'post_receptor_update_status',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'post-receptor/v1', '/delete', array(
        'methods'  => 'POST',
        'callback' => 'post_receptor_delete_post',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'post-receptor/v1', '/check-token', array(
        'methods'  => 'GET',
        'callback' => 'post_receptor_check_token',
        'permission_callback' => '__return_true',
    ) );
    
    // Adiciona endpoint para verificação de status do receptor
    register_rest_route( 'post-receptor/v1', '/status', array(
        'methods'  => 'GET',
        'callback' => function() {
            $auth_token_set = !empty(get_option('post_receptor_auth_token'));
            return array(
                'status' => 'active',
                'auth_token_configured' => $auth_token_set,
                'timestamp' => current_time('mysql'),
                'version' => '1.0.0'
            );
        },
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', 'post_receptor_register_routes' );

// Adiciona suporte a CORS para permitir requisições do plugin Emissor
add_filter( 'rest_pre_serve_request', function( $served, $result, $request, $server ) {
    header( 'Access-Control-Allow-Origin: *' );
    header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
    header( 'Access-Control-Allow-Headers: Authorization, Content-Type' );
    if ( $request->get_method() === 'OPTIONS' ) {
        return true;
    }
    return $served;
}, 10, 4 );
