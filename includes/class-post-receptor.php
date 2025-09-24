<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Requer os arquivos auxiliares
require_once 'class-post-utils.php';
require_once 'class-post-translation.php';
require_once 'class-post-media.php';
require_once 'class-post-terms.php';
require_once 'class-post-author.php';

class Post_Receptor {

    private $utils;
    private $translator;
    private $media_manager;
    private $terms_manager;
    private $author_manager;

    public function __construct() {
        $this->utils         = new Post_Utils();
        $this->translator    = new Post_Translation();
        $this->media_manager = new Post_Media();
        $this->terms_manager = new Post_Terms();
        $this->author_manager = new Post_Author();
    }

    /**
     * Inicializa a classe.
     */
    public function init() {
        // Inicializações adicionais, se necessárias.
    }

    /**
     * Recebe os dados e cria/atualiza o post no WP, incluindo categorias, tags, Yoast e imagem destacada.
     */
    public function process_received_post( $data ) {
        // Opções de idioma
        $target_language = get_option( 'post_receptor_target_language', '' );
        $source_language = isset( $data['origin_language'] ) ? trim( $data['origin_language'] ) : '';

        // Título, conteúdo e excerpt (traduzidos se necessário)
        $title   = ( $source_language === $target_language )
                    ? $data['title']
                    : $this->translator->translate_text_context( $data['title'], $source_language, $target_language, 'title' );

        $content = ( $source_language === $target_language )
                    ? $data['content']
                    : $this->translator->translate_text_context( $data['content'], $source_language, $target_language, 'body' );

        $excerpt = ( $source_language === $target_language )
                    ? $data['excerpt']
                    : $this->translator->translate_text_context( $data['excerpt'], $source_language, $target_language, 'excerpt' );

        // Slug
        $slug    = $this->utils->generate_slug_from_title( $title );

        // Autor
        $post_author = $this->author_manager->get_author_id(
            isset( $data['author'] ) ? $data['author'] : '',
            isset( $data['author_data'] ) ? $data['author_data'] : array()
        );

        // Categorias e tags
        $category_ids = $this->terms_manager->process_categories( $data['categories'], $source_language, $target_language, $this->translator, $this->utils );
        $tag_ids      = $this->terms_manager->process_tags( $data['tags'], $source_language, $target_language, $this->translator, $this->utils );

        // Yoast
        $yoast_metadesc = '';
        if ( isset( $data['yoast_metadesc'] ) ) {
            $yoast_metadesc = ( $source_language === $target_language )
                ? $data['yoast_metadesc']
                : $this->translator->translate_text_context( $data['yoast_metadesc'], $source_language, $target_language );
        }

        // Elementor (sem tradução)
        $elementor_data = isset( $data['elementor'] ) ? $data['elementor'] : '';

        // Mídias
        $media = $this->media_manager->process_media( $data['media'], $source_language, $target_language, $this->translator );

        // Status
        $final_status = $data['status'];

        // Monta array para inserir/atualizar post
        $post_arr = array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status'  => $final_status,
            'post_type'    => 'post',
            'post_name'    => $slug,
            'post_author'  => $post_author,
        );

        // Verifica se já existe post com esse emissor_post_id
        $existing_post_id = $this->utils->find_existing_post( $data['ID'] );
        if ( $existing_post_id ) {
            $post_arr['ID'] = $existing_post_id;
            $post_id = wp_update_post( $post_arr );
        } else {
            $post_id = wp_insert_post( $post_arr );
            if ( ! is_wp_error( $post_id ) ) {
                update_post_meta( $post_id, 'emissor_post_id', $data['ID'] );
            }
        }

        // Yoast Focus Keyword
        if ( isset( $data['focus_keyword'] ) ) {
            $focus_keyword = ( $source_language === $target_language )
                ? $data['focus_keyword']
                : $this->translator->translate_text_context( $data['focus_keyword'], $source_language, $target_language, 'default' );
            update_post_meta( $post_id, '_yoast_wpseo_focuskw', $focus_keyword );
        }

        // Ajusta metas do post
        if ( ! is_wp_error( $post_id ) ) {
            if ( ! empty( $category_ids ) ) {
                wp_set_post_categories( $post_id, $category_ids );
            }
            if ( ! empty( $tag_ids ) ) {
                wp_set_post_tags( $post_id, $tag_ids );
            }
            if ( ! empty( $yoast_metadesc ) ) {
                update_post_meta( $post_id, '_yoast_wpseo_metadesc', $yoast_metadesc );
            }
            if ( ! empty( $elementor_data ) ) {
                update_post_meta( $post_id, '_elementor_data', $elementor_data );
            }
            if ( ! empty( $media ) ) {
                update_post_meta( $post_id, 'post_emissor_media', $media );
            }
            if ( isset( $media['featured_image'] ) && ! empty( $media['featured_image']['url'] ) ) {
                $new_image_url = $media['featured_image']['url'];
                $existing_thumbnail_id = get_post_thumbnail_id( $post_id );
                $update_image = true;
                if ( $existing_thumbnail_id ) {
                    $stored_url = get_post_meta( $existing_thumbnail_id, '_original_featured_image_url', true );
                    if ( $stored_url === $new_image_url ) {
                        $update_image = false;
                    }
                }
                if ( $update_image ) {
                    // Deleta a imagem de destaque anterior para evitar acúmulo na biblioteca
                    if ( $existing_thumbnail_id ) {
                        wp_delete_attachment( $existing_thumbnail_id, true );
                    }
                    $this->media_manager->set_featured_image( $post_id, $media['featured_image'] );
                }
            }
        }

        return $post_id;
    }

    /**
     * Atualiza status do post local.
     */
    public function update_post_status( $data ) {
        $existing_post_id = $this->utils->find_existing_post( $data['ID'] );
        if ( $existing_post_id ) {
            $post_arr = array(
                'ID'          => $existing_post_id,
                'post_status' => $data['status'],
            );
            wp_update_post( $post_arr );
        }
    }

    /**
     * Exclusão definitiva do post e imagem destacada.
     */
    public function delete_post( $data ) {
        error_log("[Post Receptor] delete_post() called with data: " . print_r($data, true));
        error_log("[Post Receptor] Iniciando exclusão para o emissor_post_id: " . $data['ID']);

        $existing_post_id = $this->utils->find_existing_post( $data['ID'] );
        if ( $existing_post_id ) {
            error_log("[Post Receptor] Post encontrado com ID: " . $existing_post_id);

            // Deleta imagem destacada
            $featured_image_id = get_post_thumbnail_id( $existing_post_id );
            if ( $featured_image_id ) {
                error_log("[Post Receptor] Imagem destacada encontrada (ID: $featured_image_id). Tentando exclusão...");
                $result_attachment = wp_delete_attachment( $featured_image_id, true );
                if ( $result_attachment ) {
                    error_log("[Post Receptor] Imagem destacada (ID: $featured_image_id) excluída.");
                } else {
                    error_log("[Post Receptor] Falha ao excluir a imagem destacada (ID: $featured_image_id).");
                }
            } else {
                error_log("[Post Receptor] Nenhuma imagem destacada encontrada para o post.");
            }

            // Exclui o post definitivamente
            error_log("[Post Receptor] Tentando excluir o post (ID: $existing_post_id) permanentemente.");
            $result_post = wp_delete_post( $existing_post_id, true );
            if ( $result_post === false || get_post_status( $existing_post_id ) === 'trash' ) {
                error_log("[Post Receptor] wp_delete_post não removeu o post permanentemente. Forçando via DB.");
                global $wpdb;
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE ID = %d", $existing_post_id ) );
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d", $existing_post_id ) );
                error_log("[Post Receptor] Removido via \$wpdb para o post ID: $existing_post_id.");
            } else {
                error_log("[Post Receptor] Post (ID: $existing_post_id) excluído definitivamente.");
            }
        } else {
            error_log("[Post Receptor] Nenhum post encontrado para emissor_post_id: " . $data['ID']);
        }
    }

}
