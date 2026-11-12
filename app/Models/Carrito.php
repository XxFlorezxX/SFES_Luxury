<?php
require_once __DIR__ . '/Producto.php';
require_once __DIR__ . '/Cupon.php';
require_once __DIR__ . '/Envio.php';

/**
 * Modelo Carrito
 * El carrito vive en la sesión del servidor, no en el navegador: así el
 * cliente no puede editar precios ni cantidades desde las herramientas
 * de desarrollo. En la sesión solo se guardan IDs y cantidades; precios,
 * stock y descuentos se resuelven siempre contra la base de datos.
 */
class Carrito
{
    private Producto $productos;

    public function __construct()
    {
        $_SESSION['carrito'] ??= [];
        $this->productos = new Producto();
    }

    /* ═══════════════════════ LÍNEAS DEL CARRITO ══════════════════ */

    /**
     * Agrega unidades sin superar el stock disponible.
     * @return array{ok:bool, mensaje:string}
     */
    public function agregar(int $idProducto, int $cantidad = 1): array
    {
        $producto = $this->productos->buscarPorId($idProducto);

        if (!$producto || (int) $producto['activo'] !== 1) {
            return ['ok' => false, 'mensaje' => 'Esta pieza ya no está disponible.'];
        }

        $stock = (int) $producto['stock'];
        if ($stock <= 0) {
            return ['ok' => false, 'mensaje' => 'Esta pieza está agotada por ahora.'];
        }

        $actual  = (int) ($_SESSION['carrito'][$idProducto] ?? 0);
        $deseado = $actual + max(1, $cantidad);
        $nuevo   = min($deseado, $stock);

        $_SESSION['carrito'][$idProducto] = $nuevo;

        if ($nuevo < $deseado) {
            return ['ok' => true, 'mensaje' => 'Solo quedan ' . $stock . ' unidad(es) disponibles.'];
        }

        return ['ok' => true, 'mensaje' => $producto['nombre'] . ' se agregó a tu bolsa.'];
    }

    /** Fija una cantidad exacta (botones + / −), respetando el stock. */
    public function actualizarCantidad(int $idProducto, int $cantidad): array
    {
        if (!isset($_SESSION['carrito'][$idProducto])) {
            return ['ok' => false, 'mensaje' => 'Esa pieza no está en tu bolsa.'];
        }

        if ($cantidad <= 0) {
            $this->eliminar($idProducto);
            return ['ok' => true, 'mensaje' => 'Pieza retirada de la bolsa.'];
        }

        $producto = $this->productos->buscarPorId($idProducto);
        if (!$producto) {
            $this->eliminar($idProducto);
            return ['ok' => false, 'mensaje' => 'Esa pieza ya no existe.'];
        }

        $stock = (int) $producto['stock'];
        $_SESSION['carrito'][$idProducto] = min($cantidad, $stock);

        if ($cantidad > $stock) {
            return ['ok' => true, 'mensaje' => 'Ajustamos la cantidad al stock disponible (' . $stock . ').'];
        }

        return ['ok' => true, 'mensaje' => 'Cantidad actualizada.'];
    }

    public function eliminar(int $idProducto): void
    {
        unset($_SESSION['carrito'][$idProducto]);
        if (empty($_SESSION['carrito'])) {
            $this->quitarCupon();
        }
    }

    public function vaciar(): void
    {
        $_SESSION['carrito'] = [];
        $this->quitarCupon();
        unset($_SESSION['envio_ciudad']);
    }

    public function estaVacio(): bool
    {
        return empty($_SESSION['carrito']);
    }

    public function totalArticulos(): int
    {
        return (int) array_sum($_SESSION['carrito'] ?? []);
    }

    public function idsProductos(): array
    {
        return array_map('intval', array_keys($_SESSION['carrito'] ?? []));
    }

    /**
     * Detalle completo cruzando con la base de datos. Si una pieza fue
     * desactivada o se quedó sin stock mientras el carrito esperaba, se
     * marca para avisarle al cliente antes de que intente pagar.
     */
    public function obtenerDetalle(): array
    {
        if ($this->estaVacio()) {
            return [];
        }

        $detalle = [];

        foreach ($_SESSION['carrito'] as $idProducto => $cantidad) {
            $producto = $this->productos->buscarPorId((int) $idProducto);

            if (!$producto || (int) $producto['activo'] !== 1) {
                unset($_SESSION['carrito'][$idProducto]);
                continue;
            }

            $cantidad = (int) $cantidad;
            $stock    = (int) $producto['stock'];

            $producto['cantidad']       = $cantidad;
            $producto['subtotal']       = (float) $producto['precio'] * $cantidad;
            $producto['excede_stock']   = $cantidad > $stock;
            $producto['stock_restante'] = $stock;

            $detalle[] = $producto;
        }

        return $detalle;
    }

    /** ¿Alguna línea quedó por encima del stock disponible? */
    public function problemasDeStock(): array
    {
        $problemas = [];
        foreach ($this->obtenerDetalle() as $item) {
            if ($item['excede_stock']) {
                $problemas[] = sprintf('%s: solo quedan %d unidad(es).', $item['nombre'], $item['stock_restante']);
            }
        }
        return $problemas;
    }

    public function subtotal(): float
    {
        $subtotal = 0.0;
        foreach ($this->obtenerDetalle() as $item) {
            $subtotal += (float) $item['subtotal'];
        }
        return $subtotal;
    }

    /** Alias histórico usado por vistas antiguas. */
    public function total(): float
    {
        return $this->totales()['total'];
    }

    /* ═══════════════════════════ CUPÓN ═══════════════════════════ */

    /** Guarda solo el código; el descuento se recalcula en cada vista. */
    public function aplicarCupon(string $codigo): array
    {
        $resultado = (new Cupon())->validar($codigo, $this->subtotal());

        if ($resultado['ok']) {
            $_SESSION['cupon'] = strtoupper(trim($codigo));
        }

        return $resultado;
    }

    public function quitarCupon(): void
    {
        unset($_SESSION['cupon']);
    }

    public function cuponActivo(): ?string
    {
        return $_SESSION['cupon'] ?? null;
    }

    /* ═══════════════════════════ ENVÍO ═══════════════════════════ */

    public function fijarCiudad(string $ciudad): void
    {
        $_SESSION['envio_ciudad'] = mb_substr(trim($ciudad), 0, 100);
    }

    public function ciudadEnvio(): string
    {
        return $_SESSION['envio_ciudad'] ?? '';
    }

    /* ══════════════════════════ TOTALES ══════════════════════════ */

    /**
     * Cálculo único y autoritativo del carrito. Tanto la vista del
     * carrito como el checkout usan este mismo método, así lo que ve el
     * cliente y lo que se guarda en el pedido nunca pueden diferir.
     *
     * @return array{subtotal:float, descuento:float, cupon:?string, envio:float,
     *               envio_gratis:bool, total:float, articulos:int, falta_para_envio_gratis:float}
     */
    public function totales(): array
    {
        $subtotal  = $this->subtotal();
        $descuento = 0.0;
        $codigo    = $this->cuponActivo();

        if ($codigo !== null) {
            $validacion = (new Cupon())->validar($codigo, $subtotal);
            if ($validacion['ok']) {
                $descuento = (float) $validacion['descuento'];
            } else {
                // El cupón dejó de ser válido (venció, o el carrito bajó
                // del mínimo): se retira en silencio en vez de descontar mal.
                $this->quitarCupon();
                $codigo = null;
            }
        }

        $baseEnvio = max(0, $subtotal - $descuento);
        $ciudad    = $this->ciudadEnvio();

        $envioInfo = $ciudad !== ''
            ? (new Envio())->calcular($ciudad, $baseEnvio)
            : ['costo' => 0.0, 'gratis' => false, 'dias' => '', 'falta_para_gratis' => 0.0, 'umbral' => 0.0];

        return [
            'subtotal'                => round($subtotal, 2),
            'descuento'               => round($descuento, 2),
            'cupon'                   => $codigo,
            'envio'                   => round((float) $envioInfo['costo'], 2),
            'envio_gratis'            => (bool) $envioInfo['gratis'],
            'envio_dias'              => $envioInfo['dias'],
            'ciudad'                  => $ciudad,
            'falta_para_envio_gratis' => round((float) $envioInfo['falta_para_gratis'], 2),
            'umbral_envio_gratis'     => (float) ($envioInfo['umbral'] ?? 0),
            'total'                   => round($baseEnvio + (float) $envioInfo['costo'], 2),
            'articulos'               => $this->totalArticulos(),
        ];
    }
}
