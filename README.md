# Repo Layout

Este repo queda separado en dos partes:

- `wordpress-plugin/telegram-wp-chat`: plugin instalable de WordPress.
- `telegram-bot`: scripts y documentacion para preparar el bot de Telegram.

## Plugin

El archivo principal del plugin esta en:

```text
wordpress-plugin/telegram-wp-chat/telegram-wp-chat.php
```

Si lo vas a instalar manualmente en WordPress, copia la carpeta completa `telegram-wp-chat` dentro de `wp-content/plugins/`.

## Bot

La parte del bot no se instala en WordPress. Sirve para:

- obtener el `chat_id`
- registrar el webhook
- hacer pruebas de envio
