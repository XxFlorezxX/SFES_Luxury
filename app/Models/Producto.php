<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Modelo Producto
 * Todo el acceso a datos del catálogo. Las consultas usan siempre
 * sentencias preparadas: ningún valor del usuario se concatena al SQL.
 */
class Producto
{
    /** Criterios de ordenamiento permitidos (lista blanca: evita inyección por ORDER BY). */
    public const ORDENES = [
        'relevancia' => 'p.destacado DESC, p.creado_en DESC',
        'nuevo'      => 'p.creado_en DESC',
        'precio_asc' => 'p.precio ASC',
        'precio_desc'=> 'p.precio DESC',
        'nombre'     => 'p.nombre ASC',
        'vendidos'   => 'vendidos DESC, p.creado_en DESC',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerConexion();
    }

    /* ═══════════════════════ CONSULTAS PÚBLICAS ══════════════════════ */

    /**
     * Búsqueda con filtros avanzados y paginación.
     *
     * @param array $f  categoria, material, busqueda, precio_min, precio_max,
     *                  disponibilidad ('todos'|'disponible'|'agotado'), orden,
     *                  pagina, por_pagina, solo_destacados, incluir_inactivos
     * @return array{productos: array, total: int, pagina: int, paginas: int, por_pagina: int}
     */
    public function filtrar(array $f = []): array
    {
        $pagina    = max(1, (int) ($f['pagina'] ?? 1));
        $porPagina = min(48, max(4, (int) ($f['por_pagina'] ?? 12)));
        $offset    = ($pagina - 1) * $porPagina;

        $where  = [];
        $params = [];

        if (empty($f['incluir_inactivos'])) {
            $where[] = 'p.activo = 1';
        }

        if (!empty($f['categoria']) && $f['categoria'] !== 'Todos') {
            $where[] = 'p.categoria = :categoria';
            $params[':categoria'] = $f['categoria'];
        }

        if (!empty($f['material']) && $f['material'] !== 'Todos') {
            $where[] = 'p.material = :material';
            $params[':material'] = $f['material'];
        }

        if (!empty($f['busqueda'])) {
            $where[] = '(p.nombre LIKE :q1 OR p.descripcion LIKE :q2 OR p.categoria LIKE :q3 OR p.material LIKE :q4 OR p.sku LIKE :q5)';
            $termino = '%' . $f['busqueda'] . '%';
            $params[':q1'] = $params[':q2'] = $params[':q3'] = $params[':q4'] = $params[':q5'] = $termino;
        }

        if (isset($f['precio_min']) && $f['precio_min'] !== '' && (float) $f['precio_min'] > 0) {
            $where[] = 'p.precio >= :pmin';
            $params[':pmin'] = (float) $f['precio_min'];
        }

        if (isset($f['precio_max']) && $f['precio_max'] !== '' && (float) $f['precio_max'] > 0) {
            $where[] = 'p.precio <= :pmax';
            $params[':pmax'] = (float) $f['precio_max'];
        }

        if (($f['disponibilidad'] ?? '') === 'disponible') {
            $where[] = 'p.stock > 0';
        } elseif (($f['disponibilidad'] ?? '') === 'agotado') {
            $where[] = 'p.stock <= 0';
        } elseif (($f['disponibilidad'] ?? '') === 'oferta') {
            $where[] = 'p.precio_antes IS NOT NULL AND p.precio_antes > p.precio';
        }

        if (!empty($f['solo_destacados'])) {
            $where[] = 'p.destacado = 1';
        }

        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $orden    = self::ORDENES[$f['orden'] ?? 'relevancia'] ?? self::ORDENES['relevancia'];

        // Total (para la paginación)
        $stmtTotal = $this->db->prepare("SELECT COUNT(*) FROM productos p {$sqlWhere}");
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();

        // Página actual, con calificación media y unidades vendidas
        $sql = "SELECT p.*,
                    COALESCE((SELECT AVG(r.calificacion) FROM resenas r WHERE r.producto_id = p.id AND r.aprobada = 1), 0) AS calificacion,
                    COALESCE((SELECT COUNT(*)            FROM resenas r WHERE r.producto_id = p.id AND r.aprobada = 1), 0) AS total_resenas,
                    COALESCE((SELECT SUM(dp.cantidad)    FROM detalle_pedidos dp WHERE dp.producto_id = p.id), 0) AS vendidos
                FROM productos p
                {$sqlWhere}
                ORDER BY {$orden}
                LIMIT :limite OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'productos'  => $stmt->fetchAll(),
            'total'      => $total,
            'pagina'     => $pagina,
            'paginas'    => max(1, (int) ceil($total / $porPagina)),
            'por_pagina' => $porPagina,
        ];
    }

    /** Compatibilidad con el código anterior: lista simple filtrada. */
    public function obtenerTodos(string $categoria = 'Todos', string $busqueda = ''): array
    {
        return $this->filtrar([
            'categoria'         => $categoria,
            'busqueda'          => $busqueda,
            'por_pagina'        => 48,
            'incluir_inactivos' => true,
        ])['productos'];
    }

    public function destacados(int $limite = 8): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*,
                COALESCE((SELECT AVG(r.calificacion) FROM resenas r WHERE r.producto_id = p.id AND r.aprobada = 1), 0) AS calificacion,
                COALESCE((SELECT COUNT(*)            FROM resenas r WHERE r.producto_id = p.id AND r.aprobada = 1), 0) AS total_resenas
             FROM productos p
             WHERE p.activo = 1 AND p.stock > 0
             ORDER BY p.destacado DESC, p.creado_en DESC
             LIMIT :limite'
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Piezas más vendidas de verdad (según los pedidos registrados). */
    public function masVendidos(int $limite = 4): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, COALESCE(SUM(dp.cantidad), 0) AS vendidos
             FROM productos p
             JOIN detalle_pedidos dp ON dp.producto_id = p.id
             WHERE p.activo = 1
             GROUP BY p.id
             ORDER BY vendidos DESC
             LIMIT :limite'
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM productos WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function buscarPorSlug(string $slug): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT p.*,
                COALESCE((SELECT AVG(r.calificacion) FROM resenas r WHERE r.producto_id = p.id AND r.aprobada = 1), 0) AS calificacion,
                COALESCE((SELECT COUNT(*)            FROM resenas r WHERE r.producto_id = p.id AND r.aprobada = 1), 0) AS total_resenas,
                COALESCE((SELECT SUM(dp.cantidad)    FROM detalle_pedidos dp WHERE dp.producto_id = p.id), 0) AS vendidos
             FROM productos p
             WHERE p.slug = :slug
             LIMIT 1'
        );
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch();
    }

    public function registrarVista(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE productos SET vistas = vistas + 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /** Sugerencias para el autocompletado del buscador del header. */
    public function sugerencias(string $termino, int $limite = 6): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, slug, precio, imagen, categoria
             FROM productos
             WHERE activo = 1 AND (nombre LIKE :q1 OR categoria LIKE :q2 OR material LIKE :q3)
             ORDER BY destacado DESC, nombre ASC
             LIMIT :limite'
        );
        $like = '%' . $termino . '%';
        $stmt->bindValue(':q1', $like);
        $stmt->bindValue(':q2', $like);
        $stmt->bindValue(':q3', $like);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /* ═════════════════════════ FACETAS ══════════════════════════ */

    public function categorias(): array
    {
        return $this->db
            ->query('SELECT DISTINCT categoria FROM productos WHERE activo = 1 ORDER BY categoria ASC')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function materiales(): array
    {
        return $this->db
            ->query("SELECT DISTINCT material FROM productos WHERE activo = 1 AND material <> '' ORDER BY material ASC")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Rango de precios del catálogo, para los topes del filtro deslizante. */
    public function rangoPrecios(): array
    {
        $fila = $this->db
            ->query('SELECT COALESCE(MIN(precio),0) AS minimo, COALESCE(MAX(precio),0) AS maximo FROM productos WHERE activo = 1')
            ->fetch();
        return ['minimo' => (float) $fila['minimo'], 'maximo' => (float) $fila['maximo']];
    }

    /** Conteo por categoría, para mostrar "(12)" junto a cada filtro. */
    public function conteoPorCategoria(): array
    {
        $filas = $this->db
            ->query('SELECT categoria, COUNT(*) AS total FROM productos WHERE activo = 1 GROUP BY categoria')
            ->fetchAll();
        $salida = [];
        foreach ($filas as $fila) {
            $salida[$fila['categoria']] = (int) $fila['total'];
        }
        return $salida;
    }

    /* ═══════════════════════ ADMINISTRACIÓN ═════════════════════ */

    public function contar(bool $soloActivos = false): int
    {
        $sql = 'SELECT COUNT(*) FROM productos' . ($soloActivos ? ' WHERE activo = 1' : '');
        return (int) $this->db->query($sql)->fetchColumn();
    }

    public function sumaStock(): int
    {
        return (int) $this->db->query('SELECT COALESCE(SUM(stock),0) FROM productos')->fetchColumn();
    }

    /** Valor del inventario a precio de costo y a precio de venta. */
    public function valorInventario(): array
    {
        $fila = $this->db->query(
            'SELECT COALESCE(SUM(stock * costo),0) AS costo,
                    COALESCE(SUM(stock * precio),0) AS venta
             FROM productos WHERE activo = 1'
        )->fetch();

        return [
            'costo'     => (float) $fila['costo'],
            'venta'     => (float) $fila['venta'],
            'potencial' => (float) $fila['venta'] - (float) $fila['costo'],
        ];
    }

    /** Piezas por debajo de su stock mínimo (alerta del panel). */
    public function bajoStock(int $limite = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, sku, nombre, categoria, stock, stock_minimo, imagen, precio, costo
             FROM productos
             WHERE activo = 1 AND stock <= stock_minimo
             ORDER BY stock ASC, nombre ASC
             LIMIT :limite'
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function contarBajoStock(): int
    {
        return (int) $this->db
            ->query('SELECT COUNT(*) FROM productos WHERE activo = 1 AND stock <= stock_minimo')
            ->fetchColumn();
    }

    /** Genera un slug único (agrega -2, -3… si ya existe). */
    public function slugUnico(string $nombre, int $idExcluir = 0): string
    {
        $base  = slugify($nombre);
        $slug  = $base;
        $sufijo = 2;

        while (true) {
            $stmt = $this->db->prepare('SELECT id FROM productos WHERE slug = :slug AND id <> :id LIMIT 1');
            $stmt->execute([':slug' => $slug, ':id' => $idExcluir]);
            if (!$stmt->fetch()) {
                return $slug;
            }
            $slug = $base . '-' . $sufijo++;
        }
    }

    public function crear(array $d): int
    {
        $sql = 'INSERT INTO productos
                    (sku, nombre, slug, descripcion, precio, precio_antes, costo, categoria,
                     material, peso_gramos, stock, stock_minimo, imagen, destacado, activo, creado_en)
                VALUES
                    (:sku, :nombre, :slug, :descripcion, :precio, :precio_antes, :costo, :categoria,
                     :material, :peso_gramos, :stock, :stock_minimo, :imagen, :destacado, :activo, NOW())';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sku'          => $d['sku'] ?: null,
            ':nombre'       => $d['nombre'],
            ':slug'         => $d['slug'],
            ':descripcion'  => $d['descripcion'],
            ':precio'       => $d['precio'],
            ':precio_antes' => $d['precio_antes'] ?: null,
            ':costo'        => $d['costo'],
            ':categoria'    => $d['categoria'],
            ':material'     => $d['material'],
            ':peso_gramos'  => $d['peso_gramos'] ?: null,
            ':stock'        => $d['stock'],
            ':stock_minimo' => $d['stock_minimo'],
            ':imagen'       => $d['imagen'],
            ':destacado'    => $d['destacado'],
            ':activo'       => $d['activo'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $d): bool
    {
        $sql = 'UPDATE productos SET
                    sku = :sku, nombre = :nombre, slug = :slug, descripcion = :descripcion,
                    precio = :precio, precio_antes = :precio_antes, costo = :costo,
                    categoria = :categoria, material = :material, peso_gramos = :peso_gramos,
                    stock = :stock, stock_minimo = :stock_minimo, imagen = :imagen,
                    destacado = :destacado, activo = :activo
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id'           => $id,
            ':sku'          => $d['sku'] ?: null,
            ':nombre'       => $d['nombre'],
            ':slug'         => $d['slug'],
            ':descripcion'  => $d['descripcion'],
            ':precio'       => $d['precio'],
            ':precio_antes' => $d['precio_antes'] ?: null,
            ':costo'        => $d['costo'],
            ':categoria'    => $d['categoria'],
            ':material'     => $d['material'],
            ':peso_gramos'  => $d['peso_gramos'] ?: null,
            ':stock'        => $d['stock'],
            ':stock_minimo' => $d['stock_minimo'],
            ':imagen'       => $d['imagen'],
            ':destacado'    => $d['destacado'],
            ':activo'       => $d['activo'],
        ]);
    }

    /**
     * Baja lógica si la pieza ya se vendió (para no romper el historial
     * de pedidos), borrado físico si nunca se vendió.
     */
    public function eliminar(int $id): string
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM detalle_pedidos WHERE producto_id = :id');
        $stmt->execute([':id' => $id]);

        if ((int) $stmt->fetchColumn() > 0) {
            $this->db->prepare('UPDATE productos SET activo = 0, stock = 0 WHERE id = :id')->execute([':id' => $id]);
            return 'desactivado';
        }

        $this->db->prepare('DELETE FROM productos WHERE id = :id')->execute([':id' => $id]);
        return 'eliminado';
    }

    /* ═══════════════════════ RECOMENDACIONES ════════════════════ */

    /**
     * "También te puede gustar":
     *  1) piezas compradas junto a las de referencia (co-compra real),
     *  2) si faltan, se completa con la misma categoría por cercanía de precio.
     */
    public function recomendaciones(array $idsReferencia, int $limite = 4): array
    {
        $idsReferencia = array_values(array_unique(array_filter(array_map('intval', $idsReferencia))));
        if (empty($idsReferencia)) {
            return $this->destacados($limite);
        }

        $ph           = implode(',', array_fill(0, count($idsReferencia), '?'));
        $recomendados = [];

        // 1) Co-compra
        $stmt = $this->db->prepare(
            "SELECT dp2.producto_id, COUNT(*) AS veces
             FROM detalle_pedidos dp1
             JOIN detalle_pedidos dp2 ON dp1.pedido_id = dp2.pedido_id AND dp2.producto_id <> dp1.producto_id
             WHERE dp1.producto_id IN ($ph)
             GROUP BY dp2.producto_id
             ORDER BY veces DESC
             LIMIT 20"
        );
        $stmt->execute($idsReferencia);
        $coCompra = array_diff(array_column($stmt->fetchAll(), 'producto_id'), $idsReferencia);

        if ($coCompra) {
            $phCo = implode(',', array_fill(0, count($coCompra), '?'));
            $stmt2 = $this->db->prepare("SELECT * FROM productos WHERE id IN ($phCo) AND activo = 1 LIMIT " . (int) $limite);
            $stmt2->execute(array_values($coCompra));
            foreach ($stmt2->fetchAll() as $p) {
                $recomendados[$p['id']] = $p;
            }
        }

        // 2) Misma categoría, precio cercano
        if (count($recomendados) < $limite) {
            $faltan = (int) ($limite - count($recomendados));

            $stmtCat = $this->db->prepare(
                "SELECT categoria, AVG(precio) AS precio_ref
                 FROM productos WHERE id IN ($ph)
                 GROUP BY categoria ORDER BY COUNT(*) DESC LIMIT 1"
            );
            $stmtCat->execute($idsReferencia);
            $cat = $stmtCat->fetch();

            if ($cat) {
                $excluidos = array_merge($idsReferencia, array_keys($recomendados));
                $phExc     = implode(',', array_fill(0, count($excluidos), '?'));

                $stmt3 = $this->db->prepare(
                    "SELECT *, ABS(precio - ?) AS dist
                     FROM productos
                     WHERE categoria = ? AND activo = 1 AND id NOT IN ($phExc)
                     ORDER BY dist ASC LIMIT {$faltan}"
                );
                $stmt3->execute(array_merge([$cat['precio_ref'], $cat['categoria']], $excluidos));
                foreach ($stmt3->fetchAll() as $p) {
                    $recomendados[$p['id']] = $p;
                }
            }
        }

        return array_slice(array_values($recomendados), 0, $limite);
    }
}
