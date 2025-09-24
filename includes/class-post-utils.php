<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Post_Utils {

    /**
     * Encontra um post que tenha o meta 'emissor_post_id' igual ao ID do emissor.
     *
     * @param int $emissor_post_id ID do post no emissor.
     * @return int|false           ID do post receptor ou false se não encontrado.
     */
    public function find_existing_post( $emissor_post_id ) {
        $args = array(
            'post_type'  => 'post',
            'meta_query' => array(
                array(
                    'key'   => 'emissor_post_id',
                    'value' => $emissor_post_id,
                ),
            ),
            'fields' => 'ids',
        );

        $posts = get_posts( $args );
        return ! empty( $posts ) ? $posts[0] : false;
    }

    /**
     * Gera um slug a partir de um título.
     * Remove espaços, caracteres especiais, emojis, etc.
     * Garante que não inicie com números/caracteres especiais.
     *
     * @param  string $title
     * @return string $slug
     */
    public function generate_slug_from_title( $title ) {
        $slug = sanitize_title( $title );
        $slug = preg_replace( '/[^a-z0-9\-]/', '', $slug );
        $slug = preg_replace( '/-+/', '-', $slug );
        $slug = trim( $slug, '-' );
        $slug = preg_replace( '/^[^a-z]+/', '', $slug );

        return $slug;
    }
}
