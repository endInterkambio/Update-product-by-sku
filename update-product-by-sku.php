<?php
/**
 * Plugin Name: Update Products by SKU (Batch Endpoint)
 * Plugin URI: https://gusanitolector.pe/
 * Description: Endpoint REST personalizado para actualizar uno o varios productos de WooCommerce por SKU.
 * Version: 1.3.3
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
 * @version 1.3.3
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
        'methods' => 'POST',
        'callback' => 'enma_wc_update_products_by_sku_batch',
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

    $items = isset($body[0]) ? $body : [$body];
    $results = [];

    foreach ($items as $entry) {
        $sku = $entry['sku'] ?? null;

        if (!$sku) {
            $results[] = ['sku' => null, 'error' => __('SKU no especificado.', 'update-products-by-sku')];
            continue;
        }

        $product_id = wc_get_product_id_by_sku($sku);

        if (!$product_id) {
            $results[] = ['sku' => $sku, 'error' => __('Producto no encontrado.', 'update-products-by-sku')];
            continue;
        }

        $product = wc_get_product($product_id);

        // =========================
        // Actualización de SKU segura
        // =========================
        if (isset($entry['new_sku'])) {
            $new_sku = $entry['new_sku'];
            if ($new_sku !== $product->get_sku()) {
                $existing_id = wc_get_product_id_by_sku($new_sku);
                if ($existing_id && $existing_id !== $product_id) {
                    $results[] = [
                        'sku' => $sku,
                        'error' => __('El nuevo SKU ya existe en otro producto.', 'update-products-by-sku')
                    ];
                    continue; // saltar este producto
                }
                $product->set_sku($new_sku);
            }
        }

        // =========================
        // Campos básicos
        // =========================
        if (isset($entry['regular_price']))
            $product->set_regular_price($entry['regular_price']);
        if (isset($entry['sale_price']))
            $product->set_sale_price($entry['sale_price']);
        if (isset($entry['stock_quantity']))
            $product->set_stock_quantity($entry['stock_quantity']);
        if (isset($entry['manage_stock']))
            $product->set_manage_stock((bool) $entry['manage_stock']);
        if (isset($entry['description']))
            $product->set_description($entry['description']);
        if (isset($entry['short_description']))
            $product->set_short_description($entry['short_description']);
        if (isset($entry['status']))
            $product->set_status($entry['status']);
        if (isset($entry['featured']))
            $product->set_featured((bool) $entry['featured']);

        // =========================
        // Fechas de creación/modificación
        // =========================
        if (!empty($entry['date_created'])) {
            $product->set_date_created(new WC_DateTime($entry['date_created']));
        }
        if (!empty($entry['date_modified'])) {
            $product->set_date_modified(new WC_DateTime($entry['date_modified']));
        }

        // =========================
        // Fechas de oferta
        // =========================
        if (isset($entry['date_on_sale_from']))
            $product->set_date_on_sale_from($entry['date_on_sale_from']);
        if (isset($entry['date_on_sale_to']))
            $product->set_date_on_sale_to($entry['date_on_sale_to']);
        if (isset($entry['on_sale'])) {
            if ($entry['on_sale']) {
                $product->set_sale_price($entry['sale_price'] ?? $product->get_regular_price());
            } else {
                $product->set_sale_price('');
            }
        }

        // =========================
        // Imágenes (usando URLs externas)
        // =========================
        if (!empty($entry['image'])) {
            // Si viene una sola imagen
            $image_url = esc_url_raw($entry['image']);
            $product->set_image_id(0); // limpiar cualquier imagen anterior
            $product->set_props([
                'image_id' => '',
                'images' => [
                    ['src' => $image_url]
                ],
            ]);
        } elseif (!empty($entry['images']) && is_array($entry['images'])) {
            // Si viene un array de imágenes (galería)
            $images = [];
            foreach ($entry['images'] as $img) {
                if (!empty($img['src'])) {
                    $images[] = ['src' => esc_url_raw($img['src'])];
                }
            }
            if (!empty($images)) {
                $product->set_props(['images' => $images]);
            }
        }


        // =========================
        // Categorías
        // =========================
        if (!empty($entry['categories']) && is_array($entry['categories'])) {
            $cat_ids = [];
            foreach ($entry['categories'] as $cat) {
                if (!empty($cat['id']))
                    $cat_ids[] = intval($cat['id']);
            }
            $product->set_category_ids($cat_ids);
        }

        // =========================
        // Tags
        // =========================
        if (!empty($entry['tags']) && is_array($entry['tags'])) {
            $tag_ids = [];
            foreach ($entry['tags'] as $tag) {
                if (!empty($tag['id'])) {
                    $tag_ids[] = intval($tag['id']);
                } elseif (!empty($tag['name'])) {
                    // Crear el tag si no existe
                    $term = term_exists($tag['name'], 'product_tag');
                    if (!$term) {
                        $term = wp_insert_term($tag['name'], 'product_tag');
                    }
                    if (!is_wp_error($term))
                        $tag_ids[] = intval($term['term_id']);
                }
            }
            $product->set_tag_ids($tag_ids);
        }

        // Guardar cambios
        $product->save();

        $results[] = [
            'message' => __('Producto actualizado correctamente.', 'update-products-by-sku'),
            'id' => $product_id,
            'sku' => $sku,
        ];
    }

    return rest_ensure_response([
        'updated' => count(array_filter($results, fn($r) => empty($r['error']))),
        'failed' => count(array_filter($results, fn($r) => !empty($r['error']))),
        'results' => $results,
    ]);
}
