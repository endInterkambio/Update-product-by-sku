<?php
/**
 * Plugin Name: Update Product by SKU (Custom Endpoint)
 * Description: Endpoint REST personalizado para actualizar productos por SKU usando la API de WooCommerce.
 * Version: 1.0
 * Author: Enmanuel
 */

if (!defined('ABSPATH')) exit;

add_action('init', function () {
  if (!class_exists('WooCommerce')) {
    error_log('⚠️ WooCommerce no está cargado todavía.');
    return;
  }
}, 20);

add_action('rest_api_init', function () {
  if (!class_exists('WooCommerce')) {
    error_log('⚠️ WooCommerce aún no está disponible al registrar la ruta.');
    return;
  }

  // Confirmar carga de WooCommerce REST API Controller
  if (!class_exists('WC_REST_Products_Controller')) {
    error_log('⚠️ Controlador REST de productos no encontrado.');
  } else {
    error_log('✅ Controlador REST de productos cargado correctamente.');
  }

  // Registrar ruta personalizada dentro del namespace oficial de WooCommerce
  register_rest_route('wc/v3', '/products/update-by-sku', [
    'methods'  => 'POST',
    'callback' => 'wc_update_product_by_sku',
    'permission_callback' => '__return_true', // Permite Basic Auth
  ]);

  error_log('✅ Ruta /wc/v3/products/update-by-sku registrada correctamente.');
}, 99); // prioridad alta

function wc_update_product_by_sku(WP_REST_Request $request) {
  $sku = $request->get_param('sku');
  $data = $request->get_json_params();

  if (!$sku) {
    return new WP_REST_Response(['error' => 'SKU requerido'], 400);
  }

  $product_id = wc_get_product_id_by_sku($sku);

  if (!$product_id) {
    return new WP_REST_Response(['error' => 'Producto no encontrado'], 404);
  }

  $product = wc_get_product($product_id);

  if (isset($data['regular_price'])) $product->set_regular_price($data['regular_price']);
  if (isset($data['sale_price'])) $product->set_sale_price($data['sale_price']);
  if (isset($data['stock_quantity'])) $product->set_stock_quantity($data['stock_quantity']);
  if (isset($data['manage_stock'])) $product->set_manage_stock((bool)$data['manage_stock']);

  $product->save();

  return new WP_REST_Response([
    'message' => 'Producto actualizado correctamente',
    'id' => $product_id,
    'sku' => $sku,
  ], 200);
}
