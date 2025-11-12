# 🧩 Plugin WordPress – Update Products by SKU (Batch Endpoint)

**Versión:** 1.5.0  
**Autor:** [Enmanuel Nava](https://interkambio.pe/)  
**Sitio oficial:** [https://gusanitolector.pe](https://gusanitolector.pe)  
**Licencia:** [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)  
**Compatibilidad:**  
- WordPress ≥ 5.8  
- PHP ≥ 7.4  
- WooCommerce ≥ 6.0 (probado hasta 9.3)

---

## 📘 Descripción

Este plugin crea un **endpoint REST personalizado** para **actualizar uno o varios productos de WooCommerce por SKU**, ideal para integraciones externas, sincronización con catálogos u operaciones masivas.

Permite actualizar campos básicos, precios, stock, descripciones, categorías, etiquetas, imágenes y más — todo mediante una única solicitud `POST` al endpoint REST `/wc/v3/products/update-by-sku`.

---

## ⚙️ Instalación

1. Copia el archivo del plugin en el directorio:
   ```
   /wp-content/plugins/update-products-by-sku/
   ```
2. Activa el plugin desde el panel de administración de WordPress (`Plugins → Activar`).

3. Verifica que WooCommerce esté activo. Si no lo está, el plugin mostrará una advertencia y no se ejecutará.

---

## 🔒 Autenticación

El endpoint usa la **autenticación básica de WooCommerce** (por ejemplo, claves de API de WooCommerce REST).

**Cabeceras recomendadas:**

```bash
Authorization: Basic base64_encode("ck_XXXX:cs_YYYY")
Content-Type: application/json
```

---

## 🌐 Endpoint REST

| Método | URL |
|--------|-----|
| `POST` | `/wp-json/wc/v3/products/update-by-sku` |

### Descripción

Permite actualizar uno o varios productos existentes en WooCommerce utilizando su **SKU** como identificador principal.

---

## 🧾 Ejemplo de Petición

### 🔹 Actualizar un solo producto

```bash
POST /wp-json/wc/v3/products/update-by-sku
```

**Body JSON:**
```json
{
  "sku": "ABC-001",
  "regular_price": "19.99",
  "stock_quantity": 5,
  "description": "Nueva descripción del producto.",
  "on_sale": true,
  "sale_price": "14.99"
}
```

---

### 🔹 Actualización masiva (batch)

```bash
POST /wp-json/wc/v3/products/update-by-sku
```

**Body JSON:**
```json
[
  {
    "sku": "ABC-001",
    "regular_price": "19.99",
    "stock_quantity": 10
  },
  {
    "sku": "ABC-002",
    "sale_price": "12.50",
    "on_sale": true,
    "featured": true
  }
]
```

---

## 🧩 Campos Soportados

| Campo | Descripción |
|--------|-------------|
| `sku` | SKU actual del producto a modificar (obligatorio) |
| `new_sku` | Nuevo SKU (valida duplicados antes de cambiar) |
| `name` | Nombre del producto |
| `slug` | Slug personalizado |
| `regular_price` | Precio normal |
| `sale_price` | Precio de oferta |
| `on_sale` | Booleano (`true/false`) para activar o desactivar oferta |
| `stock_quantity` | Cantidad en stock |
| `manage_stock` | Habilitar gestión de stock (`true/false`) |
| `description` | Descripción larga |
| `short_description` | Descripción corta |
| `status` | Estado del producto (`publish`, `draft`, etc.) |
| `featured` | Marcar como destacado (`true/false`) |
| `date_created` | Fecha de creación (`YYYY-MM-DD`) |
| `date_modified` | Fecha de modificación (`YYYY-MM-DD`) |
| `date_on_sale_from` | Fecha de inicio de oferta |
| `date_on_sale_to` | Fecha de fin de oferta |
| `categories` | Array de categorías (`[{ "id": 12 }]`) |
| `tags` | Array de etiquetas (`[{ "name": "Infantil" }]`) |
| `images` | Array de imágenes (`[{ "src": "https://..." }]`) |

---

## 🖼️ Manejo de Imágenes

El plugin descarga y asigna las imágenes desde URLs externas:
- La **primera imagen** se usa como **imagen destacada**.
- Las siguientes se agregan a la **galería del producto**.
- Si el producto tenía imágenes previas, estas se eliminan.

**Ejemplo:**
```json
{
  "sku": "LIB-002",
  "images": [
    { "src": "https://example.com/portada.webp" },
    { "src": "https://example.com/pagina1.jpg" },
    { "src": "https://example.com/pagina2.jpg" }
  ]
}
```

---

## 🧮 Respuesta

### ✅ Ejemplo de Respuesta Exitosa

```json
{
  "updated": 2,
  "failed": 0,
  "results": [
    {
      "message": "Producto actualizado correctamente.",
      "id": 345,
      "sku": "ABC-001"
    },
    {
      "message": "Producto actualizado correctamente.",
      "id": 346,
      "sku": "ABC-002"
    }
  ]
}
```

---

### ⚠️ Ejemplo con Errores

```json
{
  "updated": 1,
  "failed": 1,
  "results": [
    {
      "message": "Producto actualizado correctamente.",
      "id": 345,
      "sku": "ABC-001"
    },
    {
      "sku": "ZZZ-999",
      "error": "Producto no encontrado."
    }
  ]
}
```

---

## 🧠 Lógica Interna del Plugin

1. **Valida la carga JSON:** acepta un objeto o un array.  
2. **Busca el producto por SKU:** usando `wc_get_product_id_by_sku`.  
3. **Valida conflictos de SKU:** si se intenta asignar uno nuevo.  
4. **Actualiza campos básicos:** precios, stock, nombre, estado, etc.  
5. **Gestiona imágenes:** descarga y asigna featured + galería.  
6. **Actualiza categorías y etiquetas:** creando términos si no existen.  
7. **Guarda el producto:** usando `$product->save()`.  
8. **Devuelve un resumen JSON:** con productos actualizados o errores.

---

## 🧩 Manejo de Errores

| Error | Causa |
|-------|--------|
| `"SKU no especificado"` | Faltó el campo `sku` en el objeto |
| `"Producto no encontrado"` | No existe un producto con ese SKU |
| `"El nuevo SKU ya existe en otro producto"` | Intento de duplicar un SKU existente |
| `"Se requiere al menos un objeto con SKU y datos a actualizar."` | El cuerpo JSON está vacío |

---

## 🧰 Registro de Depuración (`WP_DEBUG`)

Si `WP_DEBUG` está activado, el plugin registrará mensajes como:
```
⚠️ WooCommerce aún no está disponible al registrar la ruta.
Ruta /wc/v3/products/update-by-sku registrada correctamente (batch).
```

---

## 📦 Ejemplo de Uso con cURL

```bash
curl -X POST https://tusitio.com/wp-json/wc/v3/products/update-by-sku   -u ck_XXXX:cs_YYYY   -H "Content-Type: application/json"   -d '[
    { "sku": "LIB-101", "stock_quantity": 5, "regular_price": "22.50" },
    { "sku": "LIB-102", "sale_price": "18.90", "on_sale": true }
  ]'
```

---

## 🧱 Registro de Cambios

### 🆕 **v1.5.0**
- Soporte completo para actualización por lote (batch).
- Manejo seguro de imágenes destacadas y galería.
- Validación de SKU duplicado.
- Creación automática de etiquetas inexistentes.
- Compatibilidad extendida con WooCommerce 9.x.
- Log de depuración activado con `WP_DEBUG`.

---

## 🧑‍💻 Autor

**Desarrollado por:** [Enmanuel Nava](https://interkambio.pe/)  
**Proyecto:** [Gusanito Lector](https://gusanitolector.pe)

---

## ⚖️ Licencia

Este plugin es software libre distribuido bajo la licencia [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html).
