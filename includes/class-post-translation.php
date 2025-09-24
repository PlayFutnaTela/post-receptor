<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Post_Translation {

    /**
     * Construtor (pode ser vazio ou usado pra inicializações, se necessário).
     */
    public function __construct() {
        // Inicializações, se houver
    }

    /**
     * Faz a tradução de um texto usando API externa (OpenAI) de acordo com o contexto.
     *
     * @param string $text         Texto a ser traduzido.
     * @param string $source_lang  Idioma de origem (ex: 'pt_BR').
     * @param string $target_lang  Idioma de destino (ex: 'en_US').
     * @param string $context      Contexto da tradução ('title', 'body', etc.).
     *
     * @return string Texto traduzido ou o original, se houver falha/igualdade de idioma.
     */
    public function translate_text_context( $text, $source_lang, $target_lang, $context = 'default' ) {
        // Se os idiomas forem iguais, retorna o texto original
        if ( $source_lang === $target_lang ) {
            return $text;
        }

        // Mapeamento de idiomas
        $language_names = array(
            'pt_BR' => 'Brazilian Portuguese',
            'pt_PT' => 'Portuguese from Portugal',
            'en_US' => 'American English',
            'en_GB' => 'British English',
            'es_ES' => 'Spanish (Spain)',
            'fr_FR' => 'French (France)',
            'de_DE' => 'German (Germany)'
        );

        $source_name = isset( $language_names[ $source_lang ] ) ? $language_names[ $source_lang ] : $source_lang;
        $target_name = isset( $language_names[ $target_lang ] ) ? $language_names[ $target_lang ] : $target_lang;

        // Pega a chave da OpenAI armazenada nas opções
        $openai_api_key = get_option( 'post_receptor_openai_api_key', '' );
        if ( empty( $openai_api_key ) ) {
            // Se não tem chave, não traduz
            return $text;
        }

        // Prompt base que pode estar guardado nas opções
        $stored_system_prompt = get_option( 'post_receptor_system_prompt', '' );
        $fluency_statement = "You are fluent in {$source_name} and {$target_name}.";

        // Define prompt de acordo com o contexto
        switch ( $context ) {
            case 'title':
                $custom_prompt = "$fluency_statement, specializing in WordPress blog translations. Translate the title from {$source_name} to {$target_name} using the " . $stored_system_prompt . ". Ensure full conversion.";
                break;
            case 'body':
                $custom_prompt = "$fluency_statement, specializing in WordPress blog translations. Translate the content from {$source_name} to {$target_name} using the " . $stored_system_prompt . ". IMPORTANT: The content may contain HTML markup; preserve all markup and translate only the visible text.";
                break;
            case 'excerpt':
                $custom_prompt = "$fluency_statement, specializing in WordPress blog translations. Translate the excerpt from {$source_name} to {$target_name} using the " . $stored_system_prompt . ".";
                break;
            case 'category':
                $custom_prompt = "$fluency_statement, specializing in WordPress blog translations. Translate the following category title from {$source_name} to {$target_name}:";
                break;
            case 'tag':
                $custom_prompt = "$fluency_statement, specializing in WordPress blog translations. Translate the following tag from {$source_name} to {$target_name}:";
                break;
            case 'media':
                $custom_prompt = "$fluency_statement, specializing in WordPress blog translations. Translate the media metadata from {$source_name} to {$target_name}.";
                break;
            default:
                $custom_prompt = "$fluency_statement, specializing in WordPress blog translations. Translate the text from {$source_name} to {$target_name}, preserving tone and style.";
                break;
        }

        // Junta prompt + texto
        $prompt = $custom_prompt . "\n\n" . $text;
        error_log("Translation Request [Context: $context] from $source_lang to $target_lang. Prompt: " . $prompt);

        // Tenta até 3 vezes
        $max_retries      = 3;
        $translated_text  = '';
        for ( $attempt = 0; $attempt < $max_retries; $attempt++ ) {
            $body_payload = wp_json_encode([
                'model'       => 'gpt-4o-mini',
                'messages'    => [
                    [ 'role' => 'system', 'content' => $custom_prompt ],
                    [ 'role' => 'user',   'content' => $prompt ]
                ],
                'temperature' => 0.3,
                'max_tokens'  => 6000,
            ]);

            // Faz a requisição
            $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $openai_api_key,
                ],
                'body'    => $body_payload,
                'timeout' => 30,
            ]);

            if ( is_wp_error( $response ) ) {
                error_log("Translation API error ($context) attempt " . ($attempt + 1) . ": " . $response->get_error_message());
                continue;
            }

            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $data['choices'][0]['message']['content'] ) ) {
                $translated_text = trim( $data['choices'][0]['message']['content'] );
                if ( ! empty( $translated_text ) && strtolower( trim( $translated_text ) ) !== strtolower( trim( $text ) ) ) {
                    break;
                }
            }
        }

        // Se não obteve resultado útil, retorna texto original
        return $translated_text ? $translated_text : $text;
    }
}
