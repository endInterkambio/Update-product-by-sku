# 🧩 Update Products by SKU (Batch Endpoint for WooCommerce)

![WordPress](https://img.shields.io/badge/WordPress-5.8+-21759b?style=for-the-badge&logo=wordpress&logoColor=white)
![WooCommerce](https://img.shields.io/badge/WooCommerce-6.0+-96588a?style=for-the-badge&logo=woocommerce&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4+-777bb4?style=for-the-badge&logo=php&logoColor=white)
![Versión](https://img.shields.io/badge/Versión-1.7.2-success?style=for-the-badge)

Un plugin esencial diseñado para operar como puente de alta velocidad entre **WooCommerce** y sistemas ERP externos o motores de Inteligencia Artificial. Expone endpoints REST customizados que permiten realizar consultas masivas y actualizaciones de productos utilizando el **SKU** como llave primaria, evadiendo la necesidad de conocer los IDs internos de WordPress.

Desarrollado para el ecosistema de **Gusanito Lector**, este plugin es la pieza central que permite sincronizar inventario, precios y **recomendaciones calculadas por IA** (Upsells) directamente desde un backend de Spring Boot.

---

## ✨ Características Clave

*   **Operaciones Batch por SKU:** Actualiza múltiples productos en una sola petición HTTP.
*   **Comprobación de Existencias:** Endpoint dedicado (`exists-by-sku`) para mapear catálogos enteros y verificar si los SKUs están publicados en la tienda.
*   **Integración con MLOps (IA):** Recibe arrays de `iaRecommendations` basados en SKU, busca sus IDs correspondientes en WordPress y los inyecta como `upsell_ids` para venta cruzada.
*   **Sincronización de Media Inteligente:** Descarga imágenes desde URLs externas, las sube a la biblioteca de medios, asigna la principal como destacada y las restantes como galería, todo automáticamente.
*   **Gestión de Etiquetas Dinámica:** Si una etiqueta no existe, la crea al vuelo.

---

## ⚙️ Instalación

1.  Copia la carpeta del plugin en el directorio:
    `/wp-content/plugins/update-products-by-sku/`
2.  Activa el plugin desde el panel de administración de WordPress (`Plugins → Activar`).
3.  Verifica que WooCommerce esté activo. Si no lo está, el plugin mostrará una advertencia y abortará su inicialización para evitar errores fatales.

---

## 🔒 Autenticación

Los endpoints utilizan la **autenticación básica estándar de WooCommerce** (API Keys generadas desde `WooCommerce → Ajustes → Avanzado → API REST`).

**Cabeceras Requeridas:**
```http
Authorization: Basic base64_encode("ck_XXXX:cs_YYYY")
Content-Type: application/json
```

---

## 🌐 Endpoints REST

### 1️⃣ Comprobar Existencia de SKUs (`exists-by-sku`)

Endpoint ultrarrápido para validar qué productos de una lista de SKUs ya existen en WooCommerce y cuál es su estado actual de publicación.

*   **Método:** `POST`
*   **Ruta:** `/wp-json/wc/v3/products/exists-by-sku`

**Petición:**
```json
{
  "skus": ["ABC-001", "LIB-102", "INEXISTENTE-999"]
}
```

**Respuesta:**
```json
{
  "ABC-001": {
    "exists": true,
    "published": true,
    "status": "publish",
    "product_id": 345
  },
  "INEXISTENTE-999": {
    "exists": false
  }
}
```

---

### 2️⃣ Actualizar Productos por SKU (`update-by-sku`)

Permite actualizar uno o varios productos. Soporta tanto un objeto JSON simple (para un solo producto) como un Array de objetos (Batch).

*   **Método:** `POST`
*   **Ruta:** `/wp-json/wc/v3/products/update-by-sku`

**Petición Batch:**
```json
[
  {
    "sku": "ABC-001",
    "regular_price": "19.99",
    "stock_quantity": 10,
    "iaRecommendations": [
      { "sku": "LIB-102", "rankOrder": 1 },
      { "sku": "LIB-105", "rankOrder": 2 }
    ]
  },
  {
    "sku": "ABC-002",
    "status": "draft"
  }
]
```

---

## 🧩 Campos Soportados para Actualización

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `sku` | String | SKU actual del producto a modificar **(Obligatorio)** |
| `new_sku` | String | Nuevo SKU (El plugin valida que no colisione con otro producto) |
| `name` | String | Nombre / Título del producto |
| `slug` | String | Permalink / Slug personalizado |
| `regular_price` | String | Precio normal |
| `sale_price` | String | Precio de oferta |
| `on_sale` | Boolean | Activa/desactiva la oferta (`true/false`) |
| `stock_quantity` | Integer | Cantidad en inventario físico |
| `manage_stock` | Boolean | Habilitar gestión de stock (`true/false`) |
| `description` | HTML | Descripción larga |
| `short_description` | HTML | Descripción corta |
| `status` | String | Estado (`publish`, `draft`, `private`, etc.) |
| `featured` | Boolean | Marcar como producto destacado |
| `date_created` | Date | Fecha de creación (`YYYY-MM-DD HH:MM:SS`) |
| `date_modified` | Date | Fecha de modificación (`YYYY-MM-DD HH:MM:SS`) |
| `date_on_sale_from` / `to` | Date | Rango de fechas para el precio de oferta |
| `categories` | Array | Array de categorías (`[{ "id": 12 }]`) |
| `tags` | Array | Array de etiquetas (`[{ "name": "Infantil" }]` o `[{ "id": 5 }]`) |
| `images` | Array | Array de URLs para multimedia (`[{ "src": "https://..." }]`) |
| `iaRecommendations` | Array | Array de SKUs para Venta Cruzada / Upsells (`[{ "sku": "XYZ" }]`) |

---

## 🤖 Inyección de Recomendaciones IA (Upsells)

Una de las capacidades más avanzadas del plugin en la v1.7.x es la inyección de recomendaciones (Upsells) generadas por modelos de Machine Learning (ej. *TF-IDF*), las cuales operan con SKUs, mientras que WooCommerce nativamente requiere IDs.

Al recibir un payload en `iaRecommendations`, el plugin:
1. Ordena las recomendaciones respetando el `rankOrder`.
2. Traduce cada `sku` recomendado a su respectivo `product_id` en la base de datos de WordPress.
3. Descarta silenciosamente los SKUs que no existan en la tienda.
4. Asigna los IDs resultantes como `upsell_ids` del producto principal.

---

## 🧮 Respuestas y Manejo de Errores

El endpoint de actualización devuelve un resumen estadístico de la transacción batch, permitiendo al sistema orquestador saber exactamente qué falló.

**Respuesta Exitosa / Parcial:**
```json
{
  "updated": 1,
  "failed": 1,
  "results": [
    {
      "message": "Producto actualizado correctamente.",
      "id": 345,
      "sku": "ABC-001",
      "status": "publish"
    },
    {
      "sku": "ZZZ-999",
      "error": "Producto no encontrado."
    }
  ]
}
```

### Tabla de Errores Comunes
| Error | Significado |
| :--- | :--- |
| `"SKU no especificado."` | El objeto del batch no contiene la llave `sku`. |
| `"Producto no encontrado."` | El `sku` consultado no existe en WooCommerce. |
| `"El nuevo SKU ya existe en otro producto."` | Se intentó hacer un cambio a un `new_sku` que ya está tomado. |

---

## 🧱 Registro de Cambios (Changelog)

### 🆕 **v1.7.2**
- Agregado el nuevo endpoint `/products/exists-by-sku` para comprobaciones masivas previas a la sincronización.
- Integrado soporte para mapeo de SKUs a IDs en `iaRecommendations`, inyectando productos en el bloque de Upsells.
- Mejoras en la sanitización al guardar slugs (`sanitize_title`) y nombres de archivos de imagen únicos.
- Refactorización de la respuesta HTTP para incluir el estado (`status`) resultante del producto.

### **v1.5.0**
- Soporte completo para actualización por lote (batch).
- Manejo seguro de imágenes destacadas y galería desde URLs externas.
- Validación de SKU duplicado.
- Creación automática de etiquetas inexistentes.

---

## 🧑‍💻 Autoría y Licencia

**Desarrollado por:** [Enmanuel Nava](https://interkambio.pe/) para el backend del proyecto **Gusanito Lector**.

Este plugin es software libre distribuido bajo la licencia [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html).
