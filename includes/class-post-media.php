<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Post_Media {

    /**
     * Processa as mídias enviadas (imagem destacada e anexos) e traduz metadados.
     *
     * @param array           $media_data     Dados das mídias.
     * @param string          $source_lang    Idioma de origem.
     * @param string          $target_lang    Idioma de destino.
     * @param Post_Translation $translator    Objeto de tradução.
     *
     * @return array
     */
    public function process_media( $media_data, $source_lang, $target_lang, $translator ) {
        $processed_media = array();

        if ( isset( $media_data['featured_image'] ) ) {
            $processed_media['featured_image'] = $this->translate_media_details( $media_data['featured_image'], $source_lang, $target_lang, $translator );
        }

        if ( isset( $media_data['attachments'] ) && is_array( $media_data['attachments'] ) ) {
            $processed_media['attachments'] = array();
            foreach ( $media_data['attachments'] as $attachment ) {
                $processed_media['attachments'][] = $this->translate_media_details( $attachment, $source_lang, $target_lang, $translator );
            }
        }

        return $processed_media;
    }

    /**
     * Traduz metadados de uma mídia (alt, title, caption, description).
     */
    private function translate_media_details( $media_details, $source_lang, $target_lang, $translator ) {
        $translated = $media_details;

        if ( isset( $media_details['alt'] ) && ! empty( $media_details['alt'] ) ) {
            $translated['alt'] = $translator->translate_text_context( $media_details['alt'], $source_lang, $target_lang, 'media' );
        }
        if ( isset( $media_details['title'] ) && ! empty( $media_details['title'] ) ) {
            $translated['title'] = $translator->translate_text_context( $media_details['title'], $source_lang, $target_lang, 'media' );
        }
        if ( isset( $media_details['caption'] ) && ! empty( $media_details['caption'] ) ) {
            $translated['caption'] = $translator->translate_text_context( $media_details['caption'], $source_lang, $target_lang, 'media' );
        }
        if ( isset( $media_details['description'] ) && ! empty( $media_details['description'] ) ) {
            $translated['description'] = $translator->translate_text_context( $media_details['description'], $source_lang, $target_lang, 'media' );
        }

        return $translated;
    }

    /**
     * Baixa a imagem destacada e a define como destaque do post, atualizando metadados.
     *
     * @param int   $post_id
     * @param array $featured_image
     */
    public function set_featured_image( $post_id, $featured_image ) {
        if ( empty( $featured_image['url'] ) ) {
            return;
        }

        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        $image_url = $featured_image['url'];
        $tmp = download_url( $image_url );

        if ( is_wp_error( $tmp ) ) {
            error_log( 'Error downloading featured image: ' . $tmp->get_error_message() );
            return;
        }

        $file_array = array(
            'name'     => basename( $image_url ),
            'tmp_name' => $tmp
        );
        $attachment_id = media_handle_sideload( $file_array, $post_id );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $file_array['tmp_name'] );
            error_log( 'Error creating attachment: ' . $attachment_id->get_error_message() );
            return;
        }

        set_post_thumbnail( $post_id, $attachment_id );

        // Atualiza metadados
        $attachment_post = array(
            'ID'           => $attachment_id,
            'post_title'   => isset( $featured_image['title'] ) ? $featured_image['title'] : '',
            'post_excerpt' => isset( $featured_image['caption'] ) ? $featured_image['caption'] : '',
            'post_content' => isset( $featured_image['description'] ) ? $featured_image['description'] : ''
        );
        wp_update_post( $attachment_post );

        if ( isset( $featured_image['alt'] ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $featured_image['alt'] );
        }

        // Salva URL original pra comparar futuramente
        update_post_meta( $attachment_id, '_original_featured_image_url', $featured_image['url'] );
    }
}
