<?php
/*
Plugin Name: Post Receptor
Plugin URI: https://example.com/post-receptor
Description: Plugin receptor para receber posts via REST API, aplicar traduções, criar/atualizar publicações, categorias, tags, mídias, dados do Yoast SEO e do Elementor.
Version: 1.0.0
Author: Alexandre Chaves
License: GPL2
Text Domain: post-receptor
Domain Path: /languages
*/

// Impede o acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Carrega os arquivos de tradução do plugin
function post_receptor_load_textdomain() {
    load_plugin_textdomain( 'post-receptor', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'post_receptor_load_textdomain' );

// Define constantes para o diretório e URL do plugin
if ( ! defined( 'POST_RECEPTOR_DIR' ) ) {
    define( 'POST_RECEPTOR_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'POST_RECEPTOR_URL' ) ) {
    define( 'POST_RECEPTOR_URL', plugin_dir_url( __FILE__ ) );
}

// Inclui os arquivos necessários
require_once POST_RECEPTOR_DIR . 'includes/class-post-receptor.php';
require_once POST_RECEPTOR_DIR . 'includes/rest-endpoints.php';
require_once POST_RECEPTOR_DIR . 'includes/admin-settings.php';
require_once POST_RECEPTOR_DIR . 'includes/admin-api-settings.php';

// Inicializa o plugin receptor
function init_post_receptor() {
    $post_receptor = new Post_Receptor();
    $post_receptor->init();
}
add_action( 'plugins_loaded', 'init_post_receptor' );
