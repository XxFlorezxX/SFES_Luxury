<?php
require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/Producto.php';

/**
 * ChatbotController
 * Adaptado de controlador/chatbot.php de Cerámica Illusions.
 *
 * Diferencia deliberada con el proyecto original: ahí el proxy PHP
 * intentaba primero un microservicio FastAPI en localhost:5001 y solo
 * caía a la llamada directa a Claude si ese servidor no respondía.
 * Aquí se llama directo a la API de Claude — evita depender de un
 * segundo proceso Python que no aporta nada que PHP no pueda hacer
 * para este caso de uso, y así hay un componente menos que desplegar.
 *
 * Sin ANTHROPIC_API_KEY configurada, responde en "modo demo" con un
 * mensaje de ejemplo en vez de fallar — el widget del chat funciona
 * igual, solo que sin respuestas generadas.
 */
class ChatbotController extends Controller
{
    private const LIMITE_MENSAJES_HORA = 40;

    public function responder(): void
    {
        $datos      = $this->entradaJson();
        $mensaje    = trim($datos['mensaje'] ?? '');
        $historial  = is_array($datos['historial'] ?? null) ? $datos['historial'] : [];
        $sessionId  = trim($datos['session_id'] ?? '') ?: uniqid('chat_');

        if ($mensaje === '') {
            $this->json(['ok' => false, 'mensaje' => 'El mensaje no puede estar vacío.']);
            return;
        }
        if (mb_strlen($mensaje) > 1000) {
            $this->json(['ok' => false, 'mensaje' => 'Mensaje demasiado largo (máx. 1000 caracteres).']);
            return;
        }

        if (!$this->dentroDelLimite()) {
            $this->json([
                'ok'         => false,
                'rate_limit' => true,
                'mensaje'    => 'Has enviado muchos mensajes. Espera un momento o escríbenos por WhatsApp.',
            ]);
            return;
        }

        if (ANTHROPIC_API_KEY === '' || ANTHROPIC_API_KEY === 'TU_API_KEY_AQUI') {
            $this->json([
                'ok'         => true,
                'respuesta'  => '¡Hola! Soy el asistente de SFES Luxury 💎. Tenemos anillos, collares, '
                              . 'aretes y pulseras bañados en oro. Este chat está en modo demo — agrega tu '
                              . 'ANTHROPIC_API_KEY en el archivo .env para activar respuestas reales. '
                              . 'Mientras tanto, escríbenos por WhatsApp o usa el formulario de contacto.',
                'session_id' => $sessionId,
                'demo'       => true,
            ]);
            return;
        }

        $respuesta = $this->llamarClaude($mensaje, $historial);

        $this->json([
            'ok'         => $respuesta !== null,
            'respuesta'  => $respuesta ?? 'No pude conectarme con el asistente. Escríbenos por WhatsApp o usa el formulario de contacto.',
            'session_id' => $sessionId,
        ]);
    }

    private function dentroDelLimite(): bool
    {
        $_SESSION['chat_msgs']  = $_SESSION['chat_msgs']  ?? 0;
        $_SESSION['chat_reset'] = $_SESSION['chat_reset'] ?? time();

        if (time() - $_SESSION['chat_reset'] > 3600) {
            $_SESSION['chat_msgs']  = 0;
            $_SESSION['chat_reset'] = time();
        }

        if ($_SESSION['chat_msgs'] >= self::LIMITE_MENSAJES_HORA) {
            return false;
        }

        $_SESSION['chat_msgs']++;
        return true;
    }

    private function llamarClaude(string $mensaje, array $historial): ?string
    {
        $productoModelo = new Producto();
        $categorias = implode(', ', $productoModelo->categorias());

        $system = "Eres el asistente virtual de SFES Luxury, una joyería en línea. "
                . "Respondes en español, en máximo 3 oraciones, con un tono cálido y elegante. "
                . "Categorías disponibles: {$categorias}. "
                . 'Si preguntan por un pedido específico o precios exactos, invítalos a revisar el catálogo o escribir por el formulario de contacto. '
                . 'No inventes precios ni disponibilidad que no conozcas con certeza.';

        $messages = [];
        foreach (array_slice($historial, -8) as $turno) {
            $rol = ($turno['role'] ?? '') === 'user' ? 'user' : 'assistant';
            $texto = trim((string) ($turno['text'] ?? ''));
            if ($texto !== '') {
                $messages[] = ['role' => $rol, 'content' => $texto];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $mensaje];

        $payload = json_encode([
            'model'      => 'claude-sonnet-4-20250514',
            'max_tokens' => 300,
            'system'     => $system,
            'messages'   => $messages,
        ]);

        $contexto = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\n"
                                 . 'x-api-key: ' . ANTHROPIC_API_KEY . "\r\n"
                                 . "anthropic-version: 2023-06-01\r\n",
                'content'       => $payload,
                'timeout'       => 20,
                'ignore_errors' => true,
            ],
        ]);

        $respuesta = @file_get_contents('https://api.anthropic.com/v1/messages', false, $contexto);
        if (!$respuesta) {
            return null;
        }

        $obj = json_decode($respuesta, true);
        return $obj['content'][0]['text'] ?? null;
    }
}
