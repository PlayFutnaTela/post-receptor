<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Post_Terms {

    /**
     * Processa categorias: traduz nomes, gera slug, verifica se existem e cria, se necessário.
     *
     * @param array            $categories_data  Dados das categorias vindas do emissor.
     * @param string           $source_lang      Idioma de origem.
     * @param string           $target_lang      Idioma de destino.
     * @param Post_Translation $translator       Objeto de tradução.
     * @param Post_Utils       $utils            Objeto de utilitários (para slug e afins).
     *
     * @return array IDs das categorias
     */
    public function process_categories( $categories_data, $source_lang, $target_lang, $translator, $utils ) {
        $category_ids = array();

        if ( is_array( $categories_data ) ) {
            foreach ( $categories_data as $cat ) {
                $original_name = '';

                if ( is_object( $cat ) && isset( $cat->name ) ) {
                    $original_name = $cat->name;
                } elseif ( is_array( $cat ) && isset( $cat['name'] ) ) {
                    $original_name = $cat['name'];
                }

                if ( empty( $original_name ) ) {
                    error_log( 'No category name provided for translation.' );
                    continue;
                }

                // Traduz
                $translated_name = trim(
                    $translator->translate_text_context(
                        $original_name,
                        $source_lang,
                        $target_lang,
                        'category'
                    )
                );
                if ( empty( $translated_name ) ) {
                    $translated_name = $original_name;
                }

                // Gera slug
                $slug = $utils->generate_slug_from_title( $translated_name );

                $term = term_exists( $slug, 'category' );
                if ( $term !== 0 && $term !== null ) {
                    $category_ids[] = (int) $term['term_id'];
                } else {
                    $new_term = wp_insert_term( $translated_name, 'category', array( 'slug' => $slug ) );
                    if ( ! is_wp_error( $new_term ) && isset( $new_term['term_id'] ) ) {
                        $category_ids[] = (int) $new_term['term_id'];
                    }
                }
            }
        }

        return $category_ids;
    }

    /**
     * Processa tags: traduz nomes, gera slug, verifica se existem e cria, se necessário.
     *
     * @param array            $tags_data   Dados das tags vindas do emissor.
     * @param string           $source_lang Idioma de origem.
     * @param string           $target_lang Idioma de destino.
     * @param Post_Translation $translator  Objeto de tradução.
     * @param Post_Utils       $utils       Objeto de utilitários (para slug e afins).
     *
     * @return array IDs das tags
     */
    public function process_tags( $tags_data, $source_lang, $target_lang, $translator, $utils ) {
        $tag_ids = array();

        if ( is_array( $tags_data ) ) {
            foreach ( $tags_data as $tag ) {
                // Normalmente, $tag pode ser objeto ou array
                $original_name = '';
                if ( is_object( $tag ) && isset( $tag->name ) ) {
                    $original_name = $tag->name;
                } elseif ( is_array( $tag ) && isset( $tag['name'] ) ) {
                    $original_name = $tag['name'];
                }

                if ( empty( $original_name ) ) {
                    continue;
                }

                $translated_name = trim(
                    $translator->translate_text_context(
                        $original_name,
                        $source_lang,
                        $target_lang,
                        'tag'
                    )
                );
                if ( empty( $translated_name ) ) {
                    $translated_name = $original_name;
                }

                $slug = $utils->generate_slug_from_title( $translated_name );
                $term = term_exists( $slug, 'post_tag' );

                if ( $term !== 0 && $term !== null ) {
                    $tag_ids[] = (int) $term['term_id'];
                } else {
                    $new_term = wp_insert_term( $translated_name, 'post_tag', array( 'slug' => $slug ) );
                    if ( ! is_wp_error( $new_term ) && isset( $new_term['term_id'] ) ) {
                        $tag_ids[] = (int) $new_term['term_id'];
                    }
                }
            }
        }

        return $tag_ids;
    }
}
