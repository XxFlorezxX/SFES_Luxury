# SFES Luxury — Joyería en oro y plata

E-commerce completo en **PHP con arquitectura MVC** y **MySQL**: catálogo dinámico,
carrito, cupones, envíos, pedidos con inventario transaccional, cuentas de cliente,
reseñas, lista de deseos y un panel de administración con gráficas de ventas,
inventario y contabilidad.

---

## 1. Instalación

### Requisitos
- PHP **8.1** o superior (con `pdo_mysql`, `gd`, `mbstring`)
- MySQL 5.7+ o MariaDB 10.4+
- Apache con `mod_rewrite` (XAMPP, WAMP, LAMP o cualquier hosting)

### Instalación rápida en XAMPP (Windows, Mac o Linux)

1. **Copia la carpeta** `SFES_Luxury` dentro de `htdocs`
   (Windows: `C:\xampp\htdocs\SFES_Luxury`, Mac: `/Applications/XAMPP/htdocs/SFES_Luxury`).
2. Abre el **XAMPP Control Panel** y pulsa **Start** en **Apache** y en **MySQL**.
3. Entra a <http://localhost/phpmyadmin> → pestaña **Importar** → elige
   `database/sfes.sql` → **Importar**. Se crea la base `sfes_luxury` con el
   catálogo, el administrador, cupones y tarifas de envío.
   *(El script se puede volver a ejecutar sin errores ni datos duplicados.)*
4. El archivo `.env` ya viene listo para XAMPP (`DB_HOST=127.0.0.1`,
   `DB_USER=root`, `DB_PASS=` vacío). Solo cámbialo si tu MySQL tiene
   contraseña o usa otro puerto (`DB_PORT`, p. ej. 3307).
5. Abre **<http://localhost/SFES_Luxury/>** — el `.htaccess` de la raíz
   reenvía todo a `public/` y bloquea el acceso a `.env`, `app/`, `config/`,
   `database/` y `storage/`. (También funciona `http://localhost/SFES_Luxury/public/`.)

Si algo falla al conectar, la página muestra qué revisar (MySQL apagado,
base no importada o credenciales incorrectas).

### Instalación manual (línea de comandos / otros servidores)

**1. Copia el proyecto** dentro de tu servidor, por ejemplo `htdocs/SFES_Luxury`.

**2. Crea la base de datos.**

Instalación limpia:

```bash
mysql -u root -p < database/sfes.sql
```

¿Ya tenías la versión anterior con datos que quieres conservar? Usa la migración
en lugar del script anterior — agrega las columnas y tablas nuevas **sin borrar
nada**:

```bash
mysql -u root -p sfes_luxury < database/migracion_v2_a_v3.sql
```

**3. Configura tus credenciales** en `.env` (si no existe, copia `.env.example`).

**4. Punto de entrada.** En un hosting propio, apunta el dominio a la carpeta
`public/`. En XAMPP no hace falta: el `.htaccess` de la raíz se encarga.

**5. Da permisos de escritura** a `public/uploads/productos/` y `storage/logs/`.

**Acceso** al panel y a la tienda:

| | |
|---|---|
| **Administrador** | `admin@sfesluxury.com` / `1234` → `/login` |
| **Clientes** | se registran solos en `/registro` → entran por `/ingresar` |

> Cambia la contraseña del administrador antes de publicar el sitio.

### Datos de demostración (opcional)

Para ver el dashboard con gráficas llenas en vez de paneles vacíos:

```bash
php database/generar_demo.php
```

En XAMPP para Windows, desde la carpeta del proyecto:
`C:\xampp\php\php.exe database\generar_demo.php`

Crea clientes, ~150 pedidos repartidos en los últimos 4 meses y reseñas.
Para dejarlo limpio otra vez (sin tocar el catálogo ni el administrador):

```bash
php database/generar_demo.php --limpiar
```

---

## 2. Qué incluye

### Tienda

| Módulo | Detalle |
|---|---|
| **Catálogo** | Filtros por categoría, material, rango de precio y disponibilidad; orden por precio, novedad o más vendidos; paginación real; buscador global con sugerencias mientras escribes. |
| **Ficha de producto** | Galería, ficha técnica, disponibilidad real, reseñas con estrellas y piezas relacionadas por co-compra. |
| **Carrito** | Cantidades validadas contra el stock, cupones, cálculo de envío por ciudad, barra de progreso hacia el envío gratis y recomendaciones. |
| **Checkout** | Datos de entrega, pago contraentrega o transferencia, y confirmación con el detalle del pedido. |
| **Cuenta** | Perfil, dirección guardada, cambio de contraseña, historial de pedidos con seguimiento de estado y lista de deseos. |
| **Reseñas** | Un cliente puede calificar cada pieza; si la compró, la reseña se marca como *compra verificada*. |
| **Personalizar con IA** | Genera un concepto de pieza a la medida y una vista previa de imagen. Funciona en modo demostración sin claves. |
| **Asistente** | Chat flotante en todo el sitio. |

### Panel de administración

| Módulo | Detalle |
|---|---|
| **Dashboard** | Ingresos, pedidos, utilidad bruta y ticket promedio con variación contra el periodo anterior; ventas día a día; reparto por estado; evolución de 12 meses; ventas por línea; ranking de piezas; alertas de inventario y mejores clientas. |
| **Pedidos** | Listado con búsqueda y filtro por estado, detalle completo y cambio de estado (que ajusta el inventario). |
| **Productos** | CRUD con margen calculado en vivo, control de publicación, destacados, SKU, material y peso. |
| **Inventario** | Valor a costo y a precio de venta, utilidad potencial, alertas de reposición, ajustes manuales con motivo obligatorio y **kardex** por pieza. |
| **Contabilidad** | Estado de resultados del periodo (ventas brutas → descuentos → CMV → utilidad bruta → gastos → utilidad neta), evolución mensual, reparto de gastos por categoría y registro de egresos. |
| **Cupones** | Alta, edición y baja, con límites de uso, mínimo de compra y vigencia. |
| **Clientes** | Listado con resumen de compras y ficha individual. |
| **Mensajes** | Bandeja del formulario de contacto. |

---

## 3. Arquitectura

```
SFES_Luxury/
├── app/
│   ├── Config/Database.php      Conexión PDO única (singleton)
│   ├── Core/
│   │   ├── Router.php           Enrutador con parámetros tipados {id} {slug}
│   │   ├── Controller.php       Base: vistas, JSON, CSRF, sesión, saneo
│   │   ├── Seguridad.php        Sesión, cabeceras, fuerza bruta, subidas
│   │   └── Ayuda.php            Funciones de vista (e, moneda, fecha…)
│   ├── Controllers/             Uno por sección
│   ├── Models/                  Acceso a datos, una clase por entidad
│   └── Views/
│       ├── layouts/             Encabezado y pie de tienda y de panel
│       ├── shared/              Componentes reutilizables
│       └── …                    Vistas por sección
├── config/config.php            Configuración + cargador de .env
├── database/
│   ├── sfes.sql                 Esquema completo + datos semilla
│   ├── migracion_v2_a_v3.sql    Actualización sin pérdida de datos
│   └── generar_demo.php         Datos de demostración
├── storage/logs/                Registro de errores
└── public/                      Document root — único punto de entrada
    ├── index.php                Front controller y tabla de rutas
    ├── .htaccess                URLs limpias, caché, cabeceras
    ├── assets/css/              sfes.css (tienda) · admin.css (panel)
    ├── assets/js/               Módulos por página, sin librerías externas
    └── uploads/productos/       Imágenes subidas (con ejecución bloqueada)
```

### Base de datos

17 tablas relacionadas con claves foráneas e índices:

`usuarios` · `intentos_login` · `direcciones` · `categorias` · `productos` ·
`producto_imagenes` · `resenas` · `favoritos` · `cupones` · `tarifas_envio` ·
`pedidos` · `detalle_pedidos` · `movimientos_inventario` · `gastos` ·
`mensajes_contacto` · `configuracion` · `disenos_personalizados`

Dos decisiones de diseño que vale la pena conocer:

- **`productos.costo`** guarda lo que le cuesta cada pieza al negocio. Es lo que
  permite calcular margen y utilidad de verdad, no solo contar ventas.
- **`pedidos.costo_total` y `detalle_pedidos.costo`** congelan el costo en el
  momento de la venta. Si mañana sube el proveedor, la utilidad de los meses ya
  cerrados no cambia.

---

## 4. Seguridad

| Riesgo | Cómo se mitiga |
|---|---|
| **Inyección SQL** | Todas las consultas usan sentencias preparadas con parámetros. Los criterios de orden salen de una lista blanca, nunca del navegador. |
| **XSS** | Todo dato impreso en HTML pasa por `e()`. Cabecera `Content-Security-Policy` que solo permite los orígenes que el sitio realmente usa. |
| **CSRF** | Token en sesión, obligatorio en todos los formularios y peticiones JSON, incluido el CRUD del panel. Cookies con `SameSite=Lax`. |
| **Contraseñas** | `password_hash()` con bcrypt y rehash automático. Mínimo 8 caracteres con letras y números. Nunca se almacenan ni se envían en texto plano. |
| **Fuerza bruta** | 5 intentos fallidos por correo o IP bloquean el acceso 15 minutos (tabla `intentos_login`). |
| **Sesiones** | Cookies `HttpOnly` y `Secure` bajo HTTPS, identificador renovado al iniciar y cerrar sesión, cierre por inactividad a las 2 horas. |
| **Subida de archivos** | Se valida el contenido real con `getimagesize()`, no la extensión. Nombre generado por el servidor, límite de 3 MB y `.htaccess` que impide ejecutar cualquier cosa en `uploads/`. |
| **Acceso indebido** | Panel y cuenta protegidos por guardas; las consultas del cliente filtran por su ID, así que nadie ve pedidos ajenos cambiando el número en la URL. |
| **Manipulación de precios** | Precios, descuentos, envío y totales se recalculan en el servidor al confirmar. Del navegador solo se aceptan los datos de entrega. |
| **Redirecciones abiertas** | Solo se aceptan rutas internas del sitio. |
| **Secretos** | Credenciales y claves de API viven en `.env`, fuera del control de versiones y fuera del document root. |
| **Errores** | Con `APP_DEBUG=false` no se muestra nada al visitante; todo va a `storage/logs/error.log`. |

---

## 5. Diseño

**Ónix & Champaña** — negro carbón, oro champaña y marfil. Es la paleta que usa
hoy la joyería premium: sobre fondo oscuro las piezas de oro y plata resaltan
mucho más que sobre blanco.

- **Tipografía:** Cormorant Garamond para títulos (serif editorial) e Inter para
  la interfaz. Escala fluida con `clamp()`.
- **Tema claro y oscuro** con interruptor en el encabezado; la tienda arranca en
  oscuro y el panel en claro, y la preferencia se recuerda.
- **Un solo sistema de estilos:** `sfes.css` reemplaza los 12 archivos CSS por
  página que existían antes, donde el mismo encabezado y las mismas tarjetas
  estaban copiados —y ligeramente distintos— en cada uno.
- **Responsive real:** menú lateral en móvil, filtros del catálogo en panel
  deslizante, tablas con desplazamiento propio y ningún desbordamiento
  horizontal en 390 px.
- **Sin librerías externas:** ni jQuery ni frameworks. Solo las fuentes de Google
  y los iconos de Font Awesome.

### Gráficas

El panel dibuja sus gráficas con un módulo SVG propio (`assets/js/graficas.js`)
en vez de Chart.js. Pesa unos pocos KB, no depende de un CDN —el taller puede
trabajar sin internet— y usa los colores de la marca.

La paleta de series se verificó con un validador de accesibilidad cromática:
separación suficiente para daltonismo (ΔE ≥ 8), piso de visión normal (ΔE ≥ 15) y
contraste contra la superficie, **en tema claro y oscuro por separado**. Cada
gráfica trae leyenda, valores directos y una tabla equivalente, de modo que la
información nunca depende solo del color. Los estados de pedido usan colores con
significado (entregado verde, cancelado rojo), los mismos de las insignias del
resto del panel.

---

## 6. Rutas

### Tienda
`/` · `/catalogo` · `/buscar` · `/producto/{slug}` · `/carrito` · `/checkout` ·
`/pedido/confirmado/{id}` · `/favoritos` · `/personalizar` · `/contacto` ·
`/sobre-nosotros`

### Cliente
`/registro` · `/ingresar` · `/salir` · `/mi-cuenta` · `/mis-pedidos` ·
`/mis-pedidos/{id}`

### Administración
`/login` · `/logout` · `/admin/panel` · `/admin/pedidos` · `/admin/productos` ·
`/admin/inventario` · `/admin/contabilidad` · `/admin/cupones` ·
`/admin/usuarios` · `/admin/mensajes`

---

## 7. Pruebas realizadas

El proyecto se ejecutó completo contra un servidor real y se verificaron **104
comprobaciones automatizadas**:

- **34 rutas** — todas las páginas públicas responden 200, las protegidas
  redirigen sin sesión, las APIs del panel devuelven 401 y las direcciones
  inexistentes dan 404. Ninguna página emite avisos ni errores de PHP.
- **30 del flujo de compra** — registro con validación de contraseña, rechazo de
  peticiones sin token CSRF, límite de stock en el carrito, cupones válidos e
  inválidos, cálculo de envío, checkout, descuento de inventario, registro en el
  kardex, aritmética de totales, congelado del costo, uso del cupón, vaciado del
  carrito, aislamiento entre cuentas, reseñas verificadas, favoritos y la trampa
  antispam del formulario de contacto.
- **41 del panel** — acceso, bloqueo por fuerza bruta, las ocho pantallas, las
  ocho APIs (comprobando que ningún JSON expone hashes de contraseña), CRUD de
  productos con validación de costo, ajustes de inventario con motivo obligatorio,
  cancelación de pedido con devolución de stock y su reactivación, cupones,
  contabilidad y baja lógica de piezas con ventas.

Además se revisaron las pantallas renderizadas en Chromium a 1440 px, 1600 px y
390 px: sin desbordes horizontales y sin errores de consola.

### Errores corregidos durante el proceso

- El stock **nunca se descontaba** al confirmar un pedido: el inventario era
  puramente decorativo. Ahora el descuento ocurre dentro de la misma transacción
  que crea el pedido, con bloqueo de fila, y se revierte entero si algo falla.
- El CRUD de productos del panel **no tenía protección CSRF** y el borrado se
  hacía por enlace GET: bastaba visitar una URL para eliminar una pieza.
- Las imágenes subidas se validaban **solo por la extensión** del nombre.
- `requerirCliente()` redirigía al login de *administrador*, donde un cliente
  nunca podía entrar.
- No existía ninguna **ficha de producto**: desde el inicio, "Ver más" agregaba
  la pieza al carrito sin que el cliente pudiera leer nada sobre ella.
- Las barras de progreso (reseñas, envío gratis, rankings del panel) no se veían:
  el elemento interno era `inline` y `height: 100%` no aplicaba.
- Los mensajes de contacto se guardaban en la base de datos pero **no había
  ninguna pantalla** donde leerlos.

---

## 8. Limitaciones conocidas

- **No hay pasarela de pago en línea.** El checkout registra el pedido con pago
  contraentrega o transferencia. Integrar Wompi, PayU o Mercado Pago requiere una
  cuenta de comercio y credenciales que este proyecto no incluye.
- **No hay envío de correos.** No se manda confirmación por email porque
  requiere un servidor SMTP configurado. Los pedidos se confirman por WhatsApp.
- **No hay recuperación de contraseña**, por la misma razón.
- **Una sola imagen por producto.** La tabla `producto_imagenes` ya existe para
  la galería múltiple, pero el panel todavía sube una sola.
- **Las funciones de IA necesitan claves.** Sin ellas funcionan en modo
  demostración, que es útil para presentar el proyecto pero no genera contenido
  real.
