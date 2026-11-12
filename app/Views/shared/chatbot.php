<?php
/**
 * Widget flotante de asistente.
 * Funciona en modo demostración si no hay ANTHROPIC_API_KEY configurada:
 * responde con mensajes de ejemplo en lugar de fallar.
 */
?>
<button class="chat-burbuja" id="chat-burbuja" type="button" aria-label="Abrir asistente" aria-expanded="false">
    <i class="fa-solid fa-comment-dots"></i>
</button>

<div class="chat-panel" id="chat-panel" role="dialog" aria-label="Asistente de SFES Luxury">
    <div class="chat-panel__cabecera">
        <div>
            <strong>Asesora SFES</strong>
            <span><i class="fa-solid fa-circle" style="font-size:7px"></i> En línea</span>
        </div>
        <button class="modal__cerrar" id="chat-cerrar" type="button" aria-label="Cerrar asistente">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <div class="chat-mensajes" id="chat-mensajes" aria-live="polite">
        <div class="chat-msg bot">
            ¡Hola! Soy tu asesora de <?= e(TIENDA_NOMBRE) ?>. Cuéntame qué buscas — un regalo, una pieza para ti,
            algo en oro o en plata — y te ayudo a encontrarla.
        </div>
    </div>

    <form class="chat-form" id="chat-form">
        <label for="chat-entrada" class="solo-lectores">Escribe tu mensaje</label>
        <input type="text" id="chat-entrada" class="input" placeholder="Escribe tu mensaje…" autocomplete="off" maxlength="500">
        <button type="submit" aria-label="Enviar mensaje"><i class="fa-solid fa-paper-plane"></i></button>
    </form>
</div>
