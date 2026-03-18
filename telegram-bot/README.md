# Telegram Bot Setup

Esta carpeta contiene lo necesario para preparar el bot de Telegram que usa el plugin de WordPress.

El plugin de WordPress vive aparte en:

```text
../wordpress-plugin/telegram-wp-chat
```

## Contenido

- `.env.example`: variables base para el bot.
- `set-webhook.ps1`: registra el webhook del bot hacia tu WordPress.
- `get-updates.ps1`: ayuda a obtener tu `chat_id`.
- `send-test-message.ps1`: envia un mensaje de prueba al chat configurado.

## Requisitos

- Un bot creado con [@BotFather](https://t.me/BotFather).
- El `BOT_TOKEN` del bot.
- Tu sitio de WordPress accesible por HTTPS.
- El plugin activo en WordPress.

## Flujo recomendado

1. Crea el bot con `@BotFather`.
2. Copia `.env.example` a `.env`.
3. Completa `BOT_TOKEN`, `WP_BASE_URL` y luego consigue el `TELEGRAM_CHAT_ID`.
4. Ejecuta `get-updates.ps1` para revisar los updates del bot.
5. Escribe al bot desde tu cuenta de Telegram.
6. Vuelve a ejecutar `get-updates.ps1` y toma el `message.chat.id`.
7. Coloca ese valor en `.env` y tambien en los settings del plugin.
8. Ejecuta `set-webhook.ps1`.
9. Guarda el mismo `BOT_TOKEN` y `TELEGRAM_CHAT_ID` en WordPress.
10. Ejecuta `send-test-message.ps1` para validar.

## Variables

### `BOT_TOKEN`

Token entregado por `@BotFather`.

### `TELEGRAM_CHAT_ID`

ID del chat o usuario que recibira las solicitudes de chat. Normalmente se obtiene desde `getUpdates`.

### `WP_BASE_URL`

URL base de WordPress, por ejemplo:

```text
https://midominio.com
```

El webhook final queda en:

```text
https://midominio.com/wp-json/twc/v1/telegram/webhook
```

## Como obtener el chat ID

1. Abre Telegram.
2. Busca tu bot.
3. Presiona `Start` o envia cualquier mensaje.
4. Corre:

```powershell
powershell -ExecutionPolicy Bypass -File .\get-updates.ps1
```

5. Busca una estructura como esta:

```json
{
  "message": {
    "chat": {
      "id": 123456789
    }
  }
}
```

Ese `id` es el que debes usar.

## Registrar el webhook

```powershell
powershell -ExecutionPolicy Bypass -File .\set-webhook.ps1
```

Si todo sale bien, Telegram responde con `ok: true`.

## Enviar un mensaje de prueba

```powershell
powershell -ExecutionPolicy Bypass -File .\send-test-message.ps1
```

## Notas

- Si usas `getUpdates`, conviene hacerlo antes de registrar el webhook o limpiar updates viejos.
- Cuando el webhook ya esta activo, Telegram entrega eventos a WordPress en lugar de mantenerlos en `getUpdates`.
- Si tu WordPress no esta publico o no tiene HTTPS valido, Telegram no podra entregar eventos.
- El plugin ya intenta registrar el webhook cuando guardas el token en settings, pero estos scripts sirven para verificar y operar manualmente.
