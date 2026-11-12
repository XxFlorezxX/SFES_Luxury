<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * Modelo Envio
 * Tarifas de envío por ciudad y regla de envío gratis.
 * El costo se resuelve siempre en el servidor a partir de la ciudad
 * elegida: el navegador no decide cuánto cuesta el domicilio.
 */
class Envio
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerConexion();
    }

    public function ciudades(): array
    {
        return $this->db
            ->query('SELECT * FROM tarifas_envio WHERE activo = 1 ORDER BY costo ASC, ciudad ASC')
            ->fetchAll();
    }

    public function tarifaDe(string $ciudad): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tarifas_envio WHERE ciudad = :ciudad AND activo = 1 LIMIT 1');
        $stmt->execute([':ciudad' => $ciudad]);
        $tarifa = $stmt->fetch();

        if ($tarifa) {
            return $tarifa;
        }

        // Ciudad no listada: se cobra la tarifa nacional más alta.
        $stmt = $this->db->prepare('SELECT * FROM tarifas_envio WHERE activo = 1 ORDER BY costo DESC LIMIT 1');
        $stmt->execute();

        return $stmt->fetch() ?: ['ciudad' => $ciudad, 'costo' => 0.0, 'dias_estimados' => 'Por confirmar'];
    }

    /**
     * Costo final del envío considerando el umbral de envío gratis.
     *
     * @return array{costo:float, gratis:bool, dias:string, falta_para_gratis:float}
     */
    public function calcular(string $ciudad, float $subtotal): array
    {
        require_once __DIR__ . '/Configuracion.php';
        $umbral = (float) (new Configuracion())->obtener('envio_gratis_desde', '200000');

        $tarifa = $this->tarifaDe($ciudad);
        $gratis = $umbral > 0 && $subtotal >= $umbral;

        return [
            'costo'             => $gratis ? 0.0 : (float) $tarifa['costo'],
            'gratis'            => $gratis,
            'dias'              => $tarifa['dias_estimados'] ?? 'Por confirmar',
            'falta_para_gratis' => max(0, $umbral - $subtotal),
            'umbral'            => $umbral,
        ];
    }
}
