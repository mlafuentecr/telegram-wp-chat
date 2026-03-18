<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWC_Plugin {
	const OPTION_KEY = 'twc_settings';

	/**
	 * @var TWC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var string
	 */
	private $sessions_table = '';

	/**
	 * @var string
	 */
	private $messages_table = '';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$sessions_table   = $wpdb->prefix . 'twc_sessions';
		$messages_table   = $wpdb->prefix . 'twc_messages';

		$sql_sessions = "CREATE TABLE {$sessions_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			visitor_token VARCHAR(64) NOT NULL,
			visitor_name VARCHAR(190) NOT NULL,
			visitor_email VARCHAR(190) NOT NULL,
			subject VARCHAR(190) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			telegram_chat_id BIGINT NULL,
			telegram_message_id BIGINT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			last_message_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY visitor_token (visitor_token),
			KEY status (status)
		) {$charset_collate};";

		$sql_messages = "CREATE TABLE {$messages_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id BIGINT UNSIGNED NOT NULL,
			sender_type VARCHAR(20) NOT NULL,
			message_text TEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY session_id (session_id)
		) {$charset_collate};";

		dbDelta( $sql_sessions );
		dbDelta( $sql_messages );
	}

	private function __construct() {
		global $wpdb;

		$this->sessions_table = $wpdb->prefix . 'twc_sessions';
		$this->messages_table = $wpdb->prefix . 'twc_messages';

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_widget' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	public function register_admin_menu() {
		add_options_page(
			'Telegram WP Chat',
			'Telegram WP Chat',
			'manage_options',
			'telegram-wp-chat',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'twc_settings_group',
			self::OPTION_KEY,
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'twc_general_section',
			'Configuracion general',
			array( $this, 'render_general_section' ),
			'telegram-wp-chat'
		);

		$fields = array(
			'support_phone' => 'Celular',
			'telegram_bot_token' => 'Telegram Bot Token',
			'telegram_chat_id' => 'Telegram Chat ID',
			'widget_title' => 'Titulo del chat',
			'widget_subtitle' => 'Subtitulo',
			'welcome_text' => 'Texto de bienvenida',
		);

		foreach ( $fields as $field_key => $label ) {
			add_settings_field(
				$field_key,
				$label,
				array( $this, 'render_text_field' ),
				'telegram-wp-chat',
				'twc_general_section',
				array(
					'label_for' => $field_key,
					'field_key' => $field_key,
				)
			);
		}
	}

	public function sanitize_settings( $input ) {
		$existing = $this->get_settings();

		$settings = array(
			'support_phone'       => isset( $input['support_phone'] ) ? sanitize_text_field( $input['support_phone'] ) : '',
			'telegram_bot_token'  => isset( $input['telegram_bot_token'] ) ? sanitize_text_field( $input['telegram_bot_token'] ) : '',
			'telegram_chat_id'    => isset( $input['telegram_chat_id'] ) ? sanitize_text_field( $input['telegram_chat_id'] ) : '',
			'widget_title'        => isset( $input['widget_title'] ) ? sanitize_text_field( $input['widget_title'] ) : 'Atencion en linea',
			'widget_subtitle'     => isset( $input['widget_subtitle'] ) ? sanitize_text_field( $input['widget_subtitle'] ) : 'Te respondemos desde Telegram',
			'welcome_text'        => isset( $input['welcome_text'] ) ? sanitize_textarea_field( $input['welcome_text'] ) : 'Dejanos tus datos y te pedimos autorizacion para comenzar.',
			'telegram_webhook_ok' => ! empty( $existing['telegram_webhook_ok'] ) ? (bool) $existing['telegram_webhook_ok'] : false,
		);

		if ( ! empty( $settings['telegram_bot_token'] ) ) {
			$settings['telegram_webhook_ok'] = $this->register_telegram_webhook( $settings['telegram_bot_token'] );
		}

		return $settings;
	}

	public function render_general_section() {
		echo '<p>Configura tu numero visible, el bot de Telegram y los textos del widget.</p>';
		echo '<p>Para recibir mensajes necesitas crear un bot con BotFather y colocar aqui el Chat ID de tu cuenta o grupo.</p>';
	}

	public function render_text_field( $args ) {
		$settings  = $this->get_settings();
		$field_key = $args['field_key'];
		$value     = isset( $settings[ $field_key ] ) ? $settings[ $field_key ] : '';
		$type      = 'text';

		if ( 'telegram_bot_token' === $field_key ) {
			$type = 'password';
		}

		if ( 'welcome_text' === $field_key ) {
			printf(
				'<textarea id="%1$s" name="%2$s[%1$s]" rows="4" class="large-text">%3$s</textarea>',
				esc_attr( $field_key ),
				esc_attr( self::OPTION_KEY ),
				esc_textarea( $value )
			);
			return;
		}

		printf(
			'<input id="%1$s" name="%2$s[%1$s]" type="%3$s" value="%4$s" class="regular-text" />',
			esc_attr( $field_key ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $type ),
			esc_attr( $value )
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->get_settings();
		?>
		<div class="wrap">
			<h1>Telegram WP Chat</h1>
			<h2 class="nav-tab-wrapper">
				<span class="nav-tab nav-tab-active">Settings</span>
			</h2>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'twc_settings_group' );
				do_settings_sections( 'telegram-wp-chat' );
				submit_button();
				?>
			</form>
			<hr />
			<h2>Estado</h2>
			<ul>
				<li><?php echo ! empty( $settings['support_phone'] ) ? 'Celular configurado' : 'Falta configurar tu celular'; ?></li>
				<li><?php echo ! empty( $settings['telegram_bot_token'] ) ? 'Bot Token configurado' : 'Falta Bot Token'; ?></li>
				<li><?php echo ! empty( $settings['telegram_chat_id'] ) ? 'Chat ID configurado' : 'Falta Chat ID'; ?></li>
				<li><?php echo ! empty( $settings['telegram_webhook_ok'] ) ? 'Webhook de Telegram registrado' : 'Webhook pendiente o no disponible'; ?></li>
			</ul>
			<p>Webhook esperado: <code><?php echo esc_html( rest_url( 'twc/v1/telegram/webhook' ) ); ?></code></p>
		</div>
		<?php
	}

	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'twc-frontend',
			TWC_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			TWC_VERSION
		);

		wp_enqueue_script(
			'twc-frontend',
			TWC_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			TWC_VERSION,
			true
		);

		wp_localize_script(
			'twc-frontend',
			'twcConfig',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'twc/v1' ) ),
				'settings' => array(
					'title'       => $this->get_setting( 'widget_title', 'Atencion en linea' ),
					'subtitle'    => $this->get_setting( 'widget_subtitle', 'Te respondemos desde Telegram' ),
					'welcomeText' => $this->get_setting( 'welcome_text', 'Dejanos tus datos y te pedimos autorizacion para comenzar.' ),
					'phone'       => $this->get_setting( 'support_phone', '' ),
				),
			)
		);
	}

	public function render_widget() {
		if ( is_admin() ) {
			return;
		}

		?>
		<div id="twc-widget-root"></div>
		<?php
	}

	public function register_rest_routes() {
		register_rest_route(
			'twc/v1',
			'/session',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_session' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'twc/v1',
			'/session/(?P<token>[a-zA-Z0-9]+)/state',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_session_state' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'twc/v1',
			'/session/(?P<token>[a-zA-Z0-9]+)/message',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_visitor_message' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'twc/v1',
			'/telegram/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_telegram_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function create_session( WP_REST_Request $request ) {
		global $wpdb;

		$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$email   = sanitize_email( (string) $request->get_param( 'email' ) );
		$subject = sanitize_text_field( (string) $request->get_param( 'subject' ) );

		if ( empty( $name ) || empty( $email ) || empty( $subject ) ) {
			return new WP_REST_Response(
				array( 'message' => 'Nombre, email y asunto son obligatorios.' ),
				422
			);
		}

		if ( ! is_email( $email ) ) {
			return new WP_REST_Response(
				array( 'message' => 'El email no es valido.' ),
				422
			);
		}

		if ( empty( $this->get_setting( 'telegram_bot_token', '' ) ) || empty( $this->get_setting( 'telegram_chat_id', '' ) ) ) {
			return new WP_REST_Response(
				array( 'message' => 'El chat no esta configurado todavia en WordPress.' ),
				503
			);
		}

		$token = wp_generate_password( 24, false, false );
		$now   = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$this->sessions_table,
			array(
				'visitor_token'    => $token,
				'visitor_name'     => $name,
				'visitor_email'    => $email,
				'subject'          => $subject,
				'status'           => 'pending',
				'created_at'       => $now,
				'updated_at'       => $now,
				'last_message_at'  => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_REST_Response(
				array( 'message' => 'No se pudo crear la sesion.' ),
				500
			);
		}

		$session_id = (int) $wpdb->insert_id;

		$this->insert_message( $session_id, 'system', 'Solicitud enviada. Esperando aprobacion.' );
		$this->notify_telegram_pending_session( $session_id );

		return new WP_REST_Response(
			array(
				'token'  => $token,
				'status' => 'pending',
			),
			201
		);
	}

	public function get_session_state( WP_REST_Request $request ) {
		$session = $this->get_session_by_token( (string) $request['token'] );

		if ( ! $session ) {
			return new WP_REST_Response( array( 'message' => 'Sesion no encontrada.' ), 404 );
		}

		return new WP_REST_Response(
			array(
				'status'   => $session->status,
				'messages' => $this->get_messages_for_session( (int) $session->id ),
			)
		);
	}

	public function create_visitor_message( WP_REST_Request $request ) {
		global $wpdb;

		$session = $this->get_session_by_token( (string) $request['token'] );
		$text    = sanitize_textarea_field( (string) $request->get_param( 'message' ) );

		if ( ! $session ) {
			return new WP_REST_Response( array( 'message' => 'Sesion no encontrada.' ), 404 );
		}

		if ( 'accepted' !== $session->status ) {
			return new WP_REST_Response( array( 'message' => 'El chat aun no fue aceptado.' ), 409 );
		}

		if ( empty( $text ) ) {
			return new WP_REST_Response( array( 'message' => 'El mensaje esta vacio.' ), 422 );
		}

		$this->insert_message( (int) $session->id, 'visitor', $text );

		$wpdb->update(
			$this->sessions_table,
			array(
				'updated_at'      => current_time( 'mysql' ),
				'last_message_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $session->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$this->send_telegram_message(
			sprintf(
				"[%1\$s] Nuevo mensaje de %2\$s:\n\n%3\$s",
				$session->visitor_token,
				$session->visitor_name,
				$text
			)
		);

		return new WP_REST_Response( array( 'ok' => true ), 201 );
	}

	public function handle_telegram_webhook( WP_REST_Request $request ) {
		$payload = $request->get_json_params();

		if ( ! is_array( $payload ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}

		if ( ! empty( $payload['callback_query'] ) ) {
			$this->handle_telegram_callback( $payload['callback_query'] );
		}

		if ( ! empty( $payload['message'] ) ) {
			$this->handle_telegram_message( $payload['message'] );
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	private function handle_telegram_callback( $callback_query ) {
		$data = isset( $callback_query['data'] ) ? (string) $callback_query['data'] : '';

		if ( 0 !== strpos( $data, 'twc_' ) ) {
			return;
		}

		$parts = explode( '_', $data, 3 );

		if ( 3 !== count( $parts ) ) {
			return;
		}

		list( , $action, $token ) = $parts;

		$session = $this->get_session_by_token( $token );

		if ( ! $session ) {
			$this->telegram_api_request(
				'answerCallbackQuery',
				array(
					'callback_query_id' => $callback_query['id'],
					'text'              => 'La sesion ya no existe.',
				)
			);
			return;
		}

		if ( 'accept' === $action ) {
			$this->update_session_status( (int) $session->id, 'accepted' );
			$this->insert_message( (int) $session->id, 'system', 'Chat aceptado por soporte.' );
			$this->send_telegram_message(
				sprintf(
					'Chat aceptado para %1$s. Token: %2$s. Usa /chat %2$s tu mensaje para responder desde Telegram.',
					$session->visitor_name,
					$session->visitor_token
				)
			);
			$this->telegram_api_request(
				'answerCallbackQuery',
				array(
					'callback_query_id' => $callback_query['id'],
					'text'              => 'Chat aceptado.',
				)
			);
			return;
		}

		if ( 'reject' === $action ) {
			$this->update_session_status( (int) $session->id, 'rejected' );
			$this->insert_message( (int) $session->id, 'system', 'La solicitud fue rechazada.' );
			$this->telegram_api_request(
				'answerCallbackQuery',
				array(
					'callback_query_id' => $callback_query['id'],
					'text'              => 'Chat rechazado.',
				)
			);
		}
	}

	private function handle_telegram_message( $message ) {
		global $wpdb;

		$configured_chat_id = (string) $this->get_setting( 'telegram_chat_id', '' );
		$chat_id            = isset( $message['chat']['id'] ) ? (string) $message['chat']['id'] : '';
		$text               = isset( $message['text'] ) ? sanitize_textarea_field( (string) $message['text'] ) : '';

		if ( empty( $text ) || $configured_chat_id !== $chat_id ) {
			return;
		}

		if ( 0 === strpos( $text, '/start' ) ) {
			return;
		}

		$session = null;

		if ( preg_match( '/^\/chat\s+([a-zA-Z0-9]+)\s+(.+)$/s', $text, $matches ) ) {
			$session = $this->get_session_by_token( $matches[1] );
			$text    = sanitize_textarea_field( $matches[2] );
		} else {
			$active_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->sessions_table} WHERE status = %s",
					'accepted'
				)
			);

			if ( $active_count > 1 ) {
				$this->send_telegram_message( 'Hay varios chats activos. Usa /chat TOKEN tu mensaje para responder al correcto.' );
				return;
			}

			$session = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$this->sessions_table} WHERE status = %s ORDER BY last_message_at DESC LIMIT 1",
					'accepted'
				)
			);
		}

		if ( ! $session || 'accepted' !== $session->status ) {
			$this->send_telegram_message( 'No hay chats aceptados activos para responder.' );
			return;
		}

		$this->insert_message( (int) $session->id, 'admin', $text );

		$wpdb->update(
			$this->sessions_table,
			array(
				'updated_at'      => current_time( 'mysql' ),
				'last_message_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $session->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	private function notify_telegram_pending_session( $session_id ) {
		$session = $this->get_session_by_id( $session_id );

		if ( ! $session ) {
			return;
		}

		$message = sprintf(
			"Solicitud nueva de chat\n\nToken: %s\nNombre: %s\nEmail: %s\nAsunto: %s",
			$session->visitor_token,
			$session->visitor_name,
			$session->visitor_email,
			$session->subject
		);

		$response = $this->telegram_api_request(
			'sendMessage',
			array(
				'chat_id'      => $this->get_setting( 'telegram_chat_id', '' ),
				'text'         => $message,
				'reply_markup' => wp_json_encode(
					array(
						'inline_keyboard' => array(
							array(
								array(
									'text'          => 'Aceptar',
									'callback_data' => 'twc_accept_' . $session->visitor_token,
								),
								array(
									'text'          => 'Rechazar',
									'callback_data' => 'twc_reject_' . $session->visitor_token,
								),
							),
						),
					)
				),
			)
		);

		if ( ! empty( $response['result']['message_id'] ) ) {
			global $wpdb;

			$wpdb->update(
				$this->sessions_table,
				array( 'telegram_message_id' => (int) $response['result']['message_id'] ),
				array( 'id' => (int) $session->id ),
				array( '%d' ),
				array( '%d' )
			);
		}
	}

	private function update_session_status( $session_id, $status ) {
		global $wpdb;

		$wpdb->update(
			$this->sessions_table,
			array(
				'status'          => $status,
				'updated_at'      => current_time( 'mysql' ),
				'last_message_at' => current_time( 'mysql' ),
			),
			array( 'id' => $session_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	private function insert_message( $session_id, $sender_type, $message_text ) {
		global $wpdb;

		$wpdb->insert(
			$this->messages_table,
			array(
				'session_id'   => $session_id,
				'sender_type'  => $sender_type,
				'message_text' => $message_text,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	private function get_messages_for_session( $session_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sender_type, message_text, created_at FROM {$this->messages_table} WHERE session_id = %d ORDER BY id ASC",
				$session_id
			)
		);

		return array_map(
			function( $row ) {
				return array(
					'sender'    => $row->sender_type,
					'text'      => $row->message_text,
					'createdAt' => mysql2date( DATE_ATOM, $row->created_at ),
				);
			},
			$rows
		);
	}

	private function get_session_by_token( $token ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->sessions_table} WHERE visitor_token = %s LIMIT 1",
				$token
			)
		);
	}

	private function get_session_by_id( $session_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->sessions_table} WHERE id = %d LIMIT 1",
				$session_id
			)
		);
	}

	private function get_settings() {
		$defaults = array(
			'support_phone'       => '',
			'telegram_bot_token'  => '',
			'telegram_chat_id'    => '',
			'widget_title'        => 'Atencion en linea',
			'widget_subtitle'     => 'Te respondemos desde Telegram',
			'welcome_text'        => 'Dejanos tus datos y te pedimos autorizacion para comenzar.',
			'telegram_webhook_ok' => false,
		);

		return wp_parse_args( get_option( self::OPTION_KEY, array() ), $defaults );
	}

	private function get_setting( $key, $default = '' ) {
		$settings = $this->get_settings();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	private function send_telegram_message( $text ) {
		return $this->telegram_api_request(
			'sendMessage',
			array(
				'chat_id' => $this->get_setting( 'telegram_chat_id', '' ),
				'text'    => $text,
			)
		);
	}

	private function telegram_api_request( $method, $body ) {
		$token = $this->get_setting( 'telegram_bot_token', '' );

		if ( empty( $token ) ) {
			return array();
		}

		$url      = 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/' . $method;
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $decoded ) ? $decoded : array();
	}

	private function register_telegram_webhook( $token ) {
		$url      = 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/setWebhook';
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => array(
					'url' => rest_url( 'twc/v1/telegram/webhook' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		return ! empty( $decoded['ok'] );
	}
}
