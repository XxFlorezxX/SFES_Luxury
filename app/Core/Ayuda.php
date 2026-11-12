<?php
/**
 * Ayudas globales
 * Funciones cortas que se usan en todas las vistas. Se cargan una sola
 * vez desde el front controller.
 *
 * `e()` es la más importante: TODO dato que venga de la base de datos o
 * del usuario debe pasar por ella antes de imprimirse en HTML. Es la
 * defensa principal contra XSS.
 */

if (!function_exists('e')) {
    /** Escapa texto para imprimirlo con seguridad dentro de HTML. */
    function e(?string $texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /** Construye una URL interna respetando la carpeta donde está instalado el sitio. */
    function url(string $ruta = ''): string
    {
        return BASE_URL . ltrim($ruta, '/');
    }
}

if (!function_exists('activo_si')) {
    /** Devuelve la clase "activo" cuando la página actual coincide. */
    function activo_si(?string $actual, string $esperado, string $clase = 'activo'): string
    {
        return $actual === $esperado ? $clase : '';
    }
}

if (!function_exists('moneda')) {
    /** Formatea un valor como precio en pesos colombianos: $350.000 */
    function moneda(float|int|string|null $valor, bool $conSimbolo = true): string
    {
        $numero = number_format((float) $valor, 0, ',', '.');
        return $conSimbolo ? '$' . $numero : $numero;
    }
}

if (!function_exists('moneda_corta')) {
    /** Versión compacta para tarjetas del panel: $1,2 M / $850 K */
    function moneda_corta(float|int|null $valor): string
    {
        $valor = (float) $valor;
        if (abs($valor) >= 1000000) {
            return '$' . rtrim(rtrim(number_format($valor / 1000000, 1, ',', '.'), '0'), ',') . ' M';
        }
        if (abs($valor) >= 1000) {
            return '$' . number_format($valor / 1000, 0, ',', '.') . ' K';
        }
        return '$' . number_format($valor, 0, ',', '.');
    }
}

if (!function_exists('fecha_corta')) {
    /** 26 ago 2026 */
    function fecha_corta(?string $fecha): string
    {
        if (empty($fecha)) {
            return '—';
        }
        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $ts = strtotime($fecha);
        return date('j', $ts) . ' ' . $meses[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
    }
}

if (!function_exists('fecha_relativa')) {
    /** "hace 3 días" — para listados de actividad. */
    function fecha_relativa(?string $fecha): string
    {
        if (empty($fecha)) {
            return '—';
        }
        $segundos = time() - strtotime($fecha);
        if ($segundos < 60)    return 'ahora';
        if ($segundos < 3600)  return 'hace ' . floor($segundos / 60) . ' min';
        if ($segundos < 86400) return 'hace ' . floor($segundos / 3600) . ' h';
        if ($segundos < 2592000) return 'hace ' . floor($segundos / 86400) . ' d';
        return fecha_corta($fecha);
    }
}

if (!function_exists('slugify')) {
    /** Convierte "Anillo Solitario Clásico" en "anillo-solitario-clasico". */
    function slugify(string $texto): string
    {
        $texto = strtr(
            mb_strtolower(trim($texto), 'UTF-8'),
            ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']
        );
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
        return trim($texto, '-') ?: 'pieza';
    }
}

if (!function_exists('recortar')) {
    /** Corta un texto largo sin partir palabras. */
    function recortar(?string $texto, int $limite = 120): string
    {
        $texto = trim((string) $texto);
        if (mb_strlen($texto) <= $limite) {
            return $texto;
        }
        return rtrim(mb_substr($texto, 0, $limite), " .,;:") . '…';
    }
}

if (!function_exists('imagen_producto')) {
    /** URL de la imagen de un producto, con respaldo si falta el archivo. */
    function imagen_producto(?string $ruta): string
    {
        $ruta = trim((string) $ruta);
        if ($ruta === '') {
            return BASE_URL . 'assets/img/productos/no-image.png';
        }
        if (str_starts_with($ruta, 'http://') || str_starts_with($ruta, 'https://')) {
            return $ruta;
        }
        return BASE_URL . ltrim($ruta, '/');
    }
}

if (!function_exists('estado_pedido_texto')) {
    /** Etiqueta legible de cada estado de pedido. */
    function estado_pedido_texto(string $estado): string
    {
        return [
            'pendiente'  => 'Pendiente',
            'confirmado' => 'Confirmado',
            'procesando' => 'En preparación',
            'enviado'    => 'Enviado',
            'entregado'  => 'Entregado',
            'cancelado'  => 'Cancelado',
        ][$estado] ?? ucfirst($estado);
    }
}

if (!function_exists('porcentaje')) {
    /** Porcentaje seguro (evita división por cero). */
    function porcentaje(float $parte, float $total, int $decimales = 1): float
    {
        return $total == 0.0 ? 0.0 : round(($parte / $total) * 100, $decimales);
    }
}
