<?php
/**
 * Generador de datos de demostración — SFES Luxury
 *
 * Crea clientes, pedidos, reseñas y movimientos de inventario de los
 * últimos meses para que el panel de administración muestre gráficas
 * con información real en vez de paneles vacíos.
 *
 * Úsalo solo en desarrollo o para presentar el proyecto:
 *
 *     php database/generar_demo.php
 *
 * Para borrar los datos de demostración y dejar la tienda limpia:
 *
 *     php database/generar_demo.php --limpiar
 *
 * No toca el catálogo ni el usuario administrador.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Core/Ayuda.php';
require_once __DIR__ . '/../app/Config/Database.php';

$db = Database::obtenerConexion();

/** Elige N elementos al azar y devuelve SIEMPRE un arreglo de claves. */
function elegir(array $lista, int $cuantos): array
{
    $cuantos = max(0, min($cuantos, count($lista)));
    if ($cuantos === 0) {
        return [];
    }
    $claves = array_rand($lista, $cuantos);
    return is_array($claves) ? $claves : [$claves];
}

$limpiar = in_array('--limpiar', $argv ?? [], true);

/* ───────────────────────── LIMPIEZA ───────────────────────── */
if ($limpiar) {
    $db->exec("DELETE FROM detalle_pedidos WHERE pedido_id IN (SELECT id FROM pedidos)");
    $db->exec("DELETE FROM pedidos");
    $db->exec("DELETE FROM resenas");
    $db->exec("DELETE FROM favoritos");
    $db->exec("DELETE FROM direcciones");
    $db->exec("DELETE FROM movimientos_inventario WHERE pedido_id IS NOT NULL");
    $db->exec("DELETE FROM usuarios WHERE rol = 'cliente'");
    $db->exec("UPDATE cupones SET usos_actuales = 0");
    echo "Datos de demostración eliminados. El catálogo y el administrador quedan intactos.\n";
    exit(0);
}

/* ───────────────────────── CLIENTES ───────────────────────── */
$nombres = [
    'Laura Restrepo', 'Valentina Ospina', 'Camila Zapata', 'Manuela Arango',
    'Daniela Cardona', 'Sara Betancur', 'Isabella Mejía', 'Juliana Vélez',
    'Mariana Gómez', 'Antonia Ríos', 'Salomé Henao', 'Emilia Duque',
];

$ciudades = ['Medellín', 'Bogotá', 'Cali', 'Barranquilla', 'Pereira', 'Bucaramanga'];

$clientes = [];
$stmtCliente = $db->prepare(
    'INSERT INTO usuarios (nombre, email, password_hash, telefono, rol, creado_en)
     VALUES (:nombre, :email, :hash, :telefono, :rol, :creado)'
);

foreach ($nombres as $i => $nombre) {
    $correo = slugify(explode(' ', $nombre)[0]) . ($i + 1) . '@ejemplo.com';

    $existe = $db->prepare('SELECT id FROM usuarios WHERE email = :email');
    $existe->execute([':email' => $correo]);
    if ($fila = $existe->fetch()) {
        $clientes[] = (int) $fila['id'];
        continue;
    }

    $stmtCliente->execute([
        ':nombre'   => $nombre,
        ':email'    => $correo,
        ':hash'     => password_hash('Demo2026', PASSWORD_BCRYPT),
        ':telefono' => '30' . random_int(0, 9) . ' ' . random_int(100, 999) . ' ' . random_int(1000, 9999),
        ':rol'      => 'cliente',
        ':creado'   => date('Y-m-d H:i:s', strtotime('-' . random_int(20, 180) . ' days')),
    ]);
    $clientes[] = (int) $db->lastInsertId();
}

echo count($clientes) . " clientes de demostración listos.\n";

/* ───────────────────────── PEDIDOS ───────────────────────── */
$productos = $db->query('SELECT id, nombre, precio, costo, imagen FROM productos WHERE activo = 1')->fetchAll();

if (empty($productos)) {
    fwrite(STDERR, "No hay productos en el catálogo. Importa primero database/sfes.sql.\n");
    exit(1);
}

$estados = ['entregado', 'entregado', 'entregado', 'enviado', 'procesando', 'confirmado', 'pendiente', 'cancelado'];
$cupones = ['', '', '', 'BIENVENIDA10', '', 'ORO20', ''];

$stmtPedido = $db->prepare(
    'INSERT INTO pedidos
        (codigo, usuario_id, nombres, email, telefono, direccion, ciudad,
         subtotal, descuento, cupon_codigo, envio, total, costo_total,
         metodo_pago, estado, creado_en)
     VALUES
        (:codigo, :usuario, :nombres, :email, :telefono, :direccion, :ciudad,
         :subtotal, :descuento, :cupon, :envio, :total, :costo,
         :metodo, :estado, :creado)'
);

$stmtItem = $db->prepare(
    'INSERT INTO detalle_pedidos (pedido_id, producto_id, nombre, precio, costo, cantidad, imagen)
     VALUES (:pedido, :producto, :nombre, :precio, :costo, :cantidad, :imagen)'
);

$stmtMov = $db->prepare(
    'INSERT INTO movimientos_inventario (producto_id, tipo, cantidad, stock_resultante, motivo, pedido_id, creado_en)
     VALUES (:producto, :tipo, :cantidad, :stock, :motivo, :pedido, :creado)'
);

$creados = 0;

for ($dia = 120; $dia >= 0; $dia--) {
    // Más ventas los fines de semana y en los meses recientes
    $fecha    = strtotime("-{$dia} days");
    $finDeSemana = in_array((int) date('N', $fecha), [5, 6, 7], true);
    $reciente = $dia < 45;

    $cantidadPedidos = random_int(0, ($finDeSemana ? 3 : 2) + ($reciente ? 1 : 0));

    for ($n = 0; $n < $cantidadPedidos; $n++) {
        $cliente = $clientes[array_rand($clientes)];
        $datos   = $db->prepare('SELECT nombre, email, telefono FROM usuarios WHERE id = :id');
        $datos->execute([':id' => $cliente]);
        $info = $datos->fetch();

        $lineas   = [];
        $subtotal = 0.0;
        $costo    = 0.0;

        foreach (elegir($productos, random_int(1, 3)) as $indice) {
            $p        = $productos[$indice];
            $unidades = random_int(1, 2);
            $lineas[] = ['p' => $p, 'cantidad' => $unidades];
            $subtotal += (float) $p['precio'] * $unidades;
            $costo    += (float) $p['costo'] * $unidades;
        }

        $codigoCupon = $cupones[array_rand($cupones)];
        $descuento   = 0.0;

        if ($codigoCupon === 'BIENVENIDA10' && $subtotal >= 150000) {
            $descuento = round($subtotal * 0.10);
        } elseif ($codigoCupon === 'ORO20' && $subtotal >= 400000) {
            $descuento = round($subtotal * 0.20);
        } else {
            $codigoCupon = '';
        }

        $base   = $subtotal - $descuento;
        $envio  = $base >= 200000 ? 0.0 : (float) [8000, 12000, 15000][array_rand([8000, 12000, 15000])];
        $ciudad = $ciudades[array_rand($ciudades)];
        $estado = $estados[array_rand($estados)];
        // Los pedidos antiguos ya están entregados
        if ($dia > 30 && $estado !== 'cancelado') {
            $estado = 'entregado';
        }

        $marca = date('Y-m-d', $fecha) . ' ' . str_pad((string) random_int(8, 21), 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) random_int(0, 59), 2, '0', STR_PAD_LEFT) . ':00';

        $stmtPedido->execute([
            ':codigo'    => null,
            ':usuario'   => $cliente,
            ':nombres'   => $info['nombre'],
            ':email'     => $info['email'],
            ':telefono'  => $info['telefono'],
            ':direccion' => 'Calle ' . random_int(1, 90) . ' # ' . random_int(1, 80) . '-' . random_int(10, 99),
            ':ciudad'    => $ciudad,
            ':subtotal'  => $subtotal,
            ':descuento' => $descuento,
            ':cupon'     => $codigoCupon ?: null,
            ':envio'     => $envio,
            ':total'     => $base + $envio,
            ':costo'     => $costo,
            ':metodo'    => random_int(0, 4) === 0 ? 'transferencia' : 'contraentrega',
            ':estado'    => $estado,
            ':creado'    => $marca,
        ]);

        $pedidoId = (int) $db->lastInsertId();
        $db->prepare('UPDATE pedidos SET codigo = :codigo WHERE id = :id')
           ->execute([':codigo' => 'SFS-' . str_pad((string) $pedidoId, 5, '0', STR_PAD_LEFT), ':id' => $pedidoId]);

        foreach ($lineas as $linea) {
            $stmtItem->execute([
                ':pedido'   => $pedidoId,
                ':producto' => $linea['p']['id'],
                ':nombre'   => $linea['p']['nombre'],
                ':precio'   => $linea['p']['precio'],
                ':costo'    => $linea['p']['costo'],
                ':cantidad' => $linea['cantidad'],
                ':imagen'   => $linea['p']['imagen'],
            ]);

            if ($estado !== 'cancelado') {
                $stmtMov->execute([
                    ':producto' => $linea['p']['id'],
                    ':tipo'     => 'salida',
                    ':cantidad' => $linea['cantidad'],
                    ':stock'    => random_int(4, 20),
                    ':motivo'   => 'Venta pedido #' . $pedidoId,
                    ':pedido'   => $pedidoId,
                    ':creado'   => $marca,
                ]);
            }
        }

        if ($codigoCupon !== '') {
            $db->prepare('UPDATE cupones SET usos_actuales = usos_actuales + 1 WHERE codigo = :c')
               ->execute([':c' => $codigoCupon]);
        }

        $creados++;
    }
}

echo "{$creados} pedidos generados en los últimos 120 días.\n";

/* ───────────────────────── RESEÑAS ───────────────────────── */
$comentarios = [
    5 => ['Llegó impecable y el brillo es real, no se ve de fantasía.', 'Superó lo que esperaba. El empaque es precioso.', 'La uso todos los días y sigue igual de linda.'],
    4 => ['Muy bonita, aunque llegó un día después de lo estimado.', 'Buena calidad por el precio. Repetiría.'],
    3 => ['Está bien, pero la esperaba un poco más grande.'],
];

$stmtResena = $db->prepare(
    'INSERT INTO resenas (producto_id, usuario_id, calificacion, titulo, comentario, verificada, aprobada, creado_en)
     VALUES (:producto, :usuario, :calificacion, :titulo, :comentario, 1, 1, :creado)'
);

$resenas = 0;
foreach ($productos as $p) {
    foreach (elegir($clientes, random_int(0, 3)) as $indice) {
        $cliente = $clientes[$indice];

        $yaExiste = $db->prepare('SELECT 1 FROM resenas WHERE producto_id = :p AND usuario_id = :u');
        $yaExiste->execute([':p' => $p['id'], ':u' => $cliente]);
        if ($yaExiste->fetchColumn()) {
            continue;
        }

        $nota = [5, 5, 5, 4, 4, 3][array_rand([5, 5, 5, 4, 4, 3])];

        try {
            $stmtResena->execute([
                ':producto'     => $p['id'],
                ':usuario'      => $cliente,
                ':calificacion' => $nota,
                ':titulo'       => null,
                ':comentario'   => $comentarios[$nota][array_rand($comentarios[$nota])],
                ':creado'       => date('Y-m-d H:i:s', strtotime('-' . random_int(1, 90) . ' days')),
            ]);
            $resenas++;
        } catch (Throwable $e) {
            // reseña duplicada: se ignora
        }
    }
}

echo "{$resenas} reseñas generadas.\n";
echo "\nListo. Entra a /admin/panel para ver el dashboard con datos.\n";
echo "Contraseña de los clientes de demostración: Demo2026\n";
