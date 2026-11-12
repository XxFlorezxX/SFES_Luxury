<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/DisenoPersonalizado.php';

/**
 * PersonalizarController
 * Adaptado de servicios/personalizar_ia.php + controlador/generar_imagen.php
 * de Cerámica Illusions, llevado de "personaliza tu figura de yeso" a
 * "personaliza tu joya": tipo de pieza, metal, piedra y estilo.
 *
 * Dos pasos independientes, igual que el proyecto original:
 *   1) generarConcepto()  → texto (nombre, descripción, precio y tiempo estimado) vía Claude.
 *   2) generarImagenPreview() → imagen conceptual vía DALL-E 3 (OpenAI).
 * Cada uno funciona en "modo demo" si no hay clave configurada, en vez
 * de romper la página — así el formulario siempre da una respuesta útil.
 */
class PersonalizarController extends Controller
{
    private const LIMITE_IMAGENES_DIA = 3;

    public function index(): void
    {
        $this->vista('personalizar/index', [
            'titulo'       => 'Personalizar con IA | SFES Luxury',
            'paginaActual' => 'personalizar',
            
            'jsPagina'     => ['personalizar'],
        ]);
    }

    public function generarConcepto(): void
    {
        $datos       = $this->entradaJson();
        $tipo        = trim($datos['tipo']        ?? '');
        $metal       = trim($datos['metal']       ?? '');
        $piedra      = trim($datos['piedra']      ?? '');
        $estilo      = trim($datos['estilo']      ?? '');
        $descripcion = trim($datos['descripcion'] ?? '');

        if ($tipo === '') {
            $this->json(['ok' => false, 'mensaje' => 'Describe qué joya quieres.']);
            return;
        }

        if (ANTHROPIC_API_KEY === '' || ANTHROPIC_API_KEY === 'TU_API_KEY_AQUI') {
            $resultado = [
                'ok'          => true,
                'demo'        => true,
                'nombre'      => "$tipo personalizado en $metal",
                'descripcion' => "Pieza artesanal única: $tipo en $metal" . ($piedra ? " con $piedra" : '')
                               . ", estilo $estilo. Elaborada a mano con acabados de alta joyería."
                               . ' (Modo demo — agrega tu ANTHROPIC_API_KEY en .env para descripciones generadas por IA.)',
                'precio_est'  => rand(280000, 950000),
                'tiempo_est'  => rand(5, 12) . ' días hábiles',
                'sugerencias' => ['Grabado personalizado', 'Estuche de regalo premium', 'Certificado de autenticidad'],
            ];
        } else {
            $prompt = "Eres un experto joyero. Un cliente quiere personalizar una joya:\n"
                    . "- Tipo: {$tipo}\n- Metal: {$metal}\n- Piedra: {$piedra}\n- Estilo: {$estilo}\n- Detalle: {$descripcion}\n\n"
                    . 'Responde SOLO con JSON válido (sin markdown ni texto extra):'
                    . '{"nombre":"...","descripcion":"...","precio_est":450000,"tiempo_est":"...","sugerencias":["...","...","..."]}'
                    . ' El precio_est debe estar en pesos colombianos (COP), como número entero realista para joyería fina.';

            $payload = json_encode([
                'model'      => 'claude-sonnet-4-20250514',
                'max_tokens' => 500,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

            $contexto = stream_context_create(['http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\n"
                                 . 'x-api-key: ' . ANTHROPIC_API_KEY . "\r\n"
                                 . "anthropic-version: 2023-06-01\r\n",
                'content'       => $payload,
                'timeout'       => 20,
                'ignore_errors' => true,
            ]]);

            $respuesta = @file_get_contents('https://api.anthropic.com/v1/messages', false, $contexto);
            if (!$respuesta) {
                $this->json(['ok' => false, 'mensaje' => 'No se pudo conectar con el servicio de IA. Intenta de nuevo.']);
                return;
            }

            $obj  = json_decode($respuesta, true);
            $text = $obj['content'][0]['text'] ?? '';

            $resultado = json_decode(trim($text), true);
            if (!is_array($resultado)) {
                $this->json(['ok' => false, 'mensaje' => 'No se pudo interpretar la respuesta de la IA. Intenta de nuevo.']);
                return;
            }

            $resultado['ok'] = true;
        }

        // Se guarda el concepto en ambos casos (demo y real) — antes el modo
        // demo respondía y salía antes de llegar aquí, así que nunca quedaba
        // registro de los diseños generados mientras no hubiera API key.
        try {
            $disenoModelo = new DisenoPersonalizado();
            $nuevoId = $disenoModelo->crear($this->clienteId(), [
                'tipo'        => $tipo,
                'nombre_ia'   => $resultado['nombre'] ?? null,
                'metal'       => $metal,
                'piedra'      => $piedra,
                'estilo'      => $estilo,
                'descripcion' => $resultado['descripcion'] ?? null,
            ]);
            $resultado['diseno_id'] = $nuevoId;
        } catch (Throwable $e) {
            error_log('[PersonalizarController::generarConcepto] ' . $e->getMessage());
        }

        $this->json($resultado);
    }

    public function generarImagenPreview(): void
    {
        $datos       = $this->entradaJson();
        $tipo        = trim($datos['tipo']        ?? '');
        $metal       = trim($datos['metal']       ?? '');
        $piedra      = trim($datos['piedra']      ?? '');
        $estilo      = trim($datos['estilo']      ?? '');
        $descripcion = trim($datos['descripcion'] ?? '');
        $nombreIa    = trim($datos['nombre_ia']   ?? '');

        if ($tipo === '') {
            $this->json(['ok' => false, 'mensaje' => 'Genera primero el concepto.']);
            return;
        }

        $_SESSION['img_count'] = $_SESSION['img_count'] ?? 0;
        $_SESSION['img_reset'] = $_SESSION['img_reset'] ?? time();
        if (time() - $_SESSION['img_reset'] > 86400) {
            $_SESSION['img_count'] = 0;
            $_SESSION['img_reset'] = time();
        }

        if ($_SESSION['img_count'] >= self::LIMITE_IMAGENES_DIA) {
            $this->json([
                'ok'         => false,
                'rate_limit' => true,
                'mensaje'    => 'Límite de ' . self::LIMITE_IMAGENES_DIA . ' imágenes por sesión alcanzado. Vuelve más tarde o contáctanos.',
            ]);
            return;
        }

        if (OPENAI_API_KEY === '' || OPENAI_API_KEY === 'TU_OPENAI_KEY_AQUI') {
            $this->json([
                'ok'      => true,
                'demo'    => true,
                'mensaje' => 'Agrega tu OPENAI_API_KEY en el archivo .env para activar la vista previa de imagen.',
                'imagen_url' => '',
            ]);
            return;
        }

        $nombrePrompt = $nombreIa ?: $tipo;
        $partes = ["Fine jewelry product photograph of a {$nombrePrompt}", "made of {$metal}"];
        if ($piedra)      $partes[] = "set with {$piedra}";
        if ($estilo)      $partes[] = "{$estilo} style";
        if ($descripcion) $partes[] = mb_substr($descripcion, 0, 200);
        $partes[] = 'luxury studio product photography, white clean background, soft shadows, sharp focus, high detail, elegant lighting';
        $prompt = implode(', ', $partes);

        $payload = json_encode([
            'model' => 'dall-e-3', 'prompt' => $prompt,
            'size' => '1024x1024', 'quality' => 'standard', 'n' => 1,
        ]);

        $contexto = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nAuthorization: Bearer " . OPENAI_API_KEY . "\r\n",
            'content'       => $payload,
            'timeout'       => 35,
            'ignore_errors' => true,
        ]]);

        $respuesta = @file_get_contents('https://api.openai.com/v1/images/generations', false, $contexto);
        if (!$respuesta) {
            $this->json(['ok' => false, 'mensaje' => 'No se pudo conectar con el servicio de imágenes. Intenta de nuevo.']);
            return;
        }

        $obj = json_decode($respuesta, true);
        if (isset($obj['error'])) {
            $this->json(['ok' => false, 'mensaje' => 'Error del servicio de imágenes: ' . ($obj['error']['message'] ?? 'desconocido')]);
            return;
        }

        $imagenUrl = $obj['data'][0]['url'] ?? '';
        if (!$imagenUrl) {
            $this->json(['ok' => false, 'mensaje' => 'No se recibió la imagen. Intenta de nuevo.']);
            return;
        }

        $_SESSION['img_count']++;

        try {
            $disenoModelo = new DisenoPersonalizado();
            $disenoModelo->crear($this->clienteId(), [
                'tipo' => $tipo, 'nombre_ia' => $nombreIa ?: $tipo, 'metal' => $metal,
                'piedra' => $piedra, 'estilo' => $estilo, 'descripcion' => $descripcion,
                'prompt_imagen' => $prompt, 'imagen_url' => $imagenUrl,
            ]);
        } catch (Throwable $e) {
            error_log('[PersonalizarController::generarImagenPreview] ' . $e->getMessage());
        }

        $this->json([
            'ok'         => true,
            'imagen_url' => $imagenUrl,
            'restantes'  => max(0, self::LIMITE_IMAGENES_DIA - $_SESSION['img_count']),
        ]);
    }
}
