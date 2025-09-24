<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Post_Author {

    /**
     * Retorna o ID do autor a partir do login e dos dados completos do autor.
     * Se o autor não for encontrado, cria um novo usuário com os dados recebidos (caso exista user_login).
     *
     * @param string $author_login Login do autor recebido no payload.
     * @param array  $author_data  Dados completos do autor (opcional).
     *
     * @return int ID do autor a ser utilizado.
     */
    public function get_author_id( $author_login, $author_data = array() ) {
        // Tenta pegar usuário pelo login
        if ( ! empty( $author_login ) ) {
            $user = get_user_by( 'login', $author_login );
            if ( $user ) {
                return $user->ID;
            }
        }

        // Se não encontrou e temos dados do autor com user_login, cria um usuário novo
        if ( ! empty( $author_data ) && isset( $author_data['user_login'] ) ) {
            $default_email = ! empty( $author_data['user_email'] )
                ? $author_data['user_email']
                : $author_data['user_login'] . '@example.com';

            $user_id = wp_insert_user( array(
                'user_login'   => $author_data['user_login'],
                'user_pass'    => wp_generate_password( 12, false ),
                'first_name'   => isset( $author_data['first_name'] )   ? $author_data['first_name']   : '',
                'last_name'    => isset( $author_data['last_name'] )    ? $author_data['last_name']    : '',
                'nickname'     => isset( $author_data['nickname'] )     ? $author_data['nickname']     : '',
                'display_name' => isset( $author_data['display_name'] ) ? $author_data['display_name'] : $author_data['user_login'],
                'user_email'   => $default_email,
                'user_url'     => isset( $author_data['user_url'] )     ? $author_data['user_url']     : '',
                'description'  => isset( $author_data['description'] )  ? $author_data['description']  : '',
                'role'         => 'author',
            ));

            if ( ! is_wp_error( $user_id ) ) {
                return $user_id;
            }
        }

        // Tenta usar usuário admin ou administrador
        $user = get_user_by( 'login', 'admin' );
        if ( $user ) {
            return $user->ID;
        }
        $user = get_user_by( 'login', 'administrador' );
        if ( $user ) {
            return $user->ID;
        }

        // Caso contrário, retorna o user_id do current user
        return get_current_user_id();
    }

}
