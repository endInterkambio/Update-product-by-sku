<?php
/**
 * Plugin Name: Update Products by SKU (Batch Endpoint)
 * Plugin URI: https://gusanitolector.pe/
 * Description: Endpoint REST personalizado para actualizar uno o varios productos de WooCommerce por SKU.
 * Version: 1.3.1
 * Author: Enmanuel Nava
 * Author URI: https://interkambio.com/
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.3
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: update-products-by-sku
 * Domain Path: /languages
 *
 * @package UpdateProductsBySKU
 * @version 1.3.1
 * @author Enmanuel
 */

if (!defined('ABSPATH')) {
    exit; // Evita acceso directo.
}

// Verifica que WooCommerce esté activo.
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>Update Products by SKU:</strong> WooCommerce debe estar activo para que este plugin funcione.</p></div>';
    });
    return;
}

// Espera a que WooCommerce esté cargado antes de registrar el endpoint.
add_action('rest_api_init', function () {
    if (!class_exists('WooCommerce')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('⚠️ WooCommerce aún no está disponible al registrar la ruta.');
        }
        return;
    }

    register_rest_route('wc/v3', '/products/update-by-sku', [
        'methods'             => 'POST',
        'callback'            => 'enma_wc_update_products_by_sku_batch',
        'permission_callback' => '__return_true', // Usa Basic Auth (WooCommerce maneja la autenticación)
    ]);

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Ruta /wc/v3/products/update-by-sku registrada correctamente (batch).');
    }
}, 99);

/**
 * Actualiza uno o varios productos por SKU.
 *
 * Permite enviar un objeto o un array JSON:
 * [
 *   { "sku": "ABC-001", "stock_quantity": 10 },
 *   { "sku": "ABC-002", "regular_price": "19.99" }
 * ]
 */
function enma_wc_update_products_by_sku_batch(WP_REST_Request $request)
{
    $body = $request->get_json_params();

    if (empty($body)) {
        return new WP_REST_Response([
            'error' => __('Se requiere al menos un objeto con SKU y datos a actualizar.', 'update-products-by-sku')
        ], 400);
    }

    // Acepta un solo objeto o un array de objetos
    $items   = isset($body[0]) ? $body : [$body];
    $results = [];

    foreach ($items as $entry) {
        $sku = $entry['sku'] ?? null;

        if (!$sku) {
            $results[] = [
                'sku'   => null,
                'error' => __('SKU no especificado.', 'update-products-by-sku')
            ];
            continue;
        }

        $product_id = wc_get_product_id_by_sku($sku);

        if (!$product_id) {
            $results[] = [
                'sku'   => $sku,
                'error' => __('Producto no encontrado.', 'update-products-by-sku')
            ];
            continue;
        }

        $product = wc_get_product($product_id);

        // Actualizar campos permitidos
        if (isset($entry['regular_price']))
            $product->set_regular_price($entry['regular_price']);
        if (isset($entry['sale_price']))
            $product->set_sale_price($entry['sale_price']);
        if (isset($entry['stock_quantity']))
            $product->set_stock_quantity($entry['stock_quantity']);
        if (isset($entry['manage_stock']))
            $product->set_manage_stock((bool) $entry['manage_stock']);

        // Guardar los cambios
        $product->save();

        // Preparar respuesta simple
        $results[] = [
            'message' => __('Producto actualizado correctamente.', 'update-products-by-sku'),
            'id'      => $product_id,
            'sku'     => $sku,
        ];
    }

    // Respuesta final
    return rest_ensure_response([
        'updated' => count(array_filter($results, fn($r) => empty($r['error']))),
        'failed'  => count(array_filter($results, fn($r) => !empty($r['error']))),
        'results' => $results,
    ]);
}
