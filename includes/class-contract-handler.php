<?php
/**
 * Contract Handler - Gestione Migliorata Contratti
 *
 * Migliorie implementate:
 * 1. Data scadenza sempre calcolata automaticamente
 * 2. Storico completo di tutte le operazioni
 * 3. Log dettagliato con data/ora/utente
 * 4. Precompilazione automatica da servizio
 * 5. Aggiornamento KPI/MRR/ARR/ARPU (materialized) ad ogni evento rilevante
 */

defined('ABSPATH') || exit;

class SPM_Contract_Handler {

	private static $is_saving = false; // Previeni loop infiniti
	private static $pending_diffs = [];

	// --- POLICY CONTRATTI (lettura da settings) ---
	private static function get_tolleranza_scaduto_giorni() {
		if (class_exists('SPM_Settings_Page')) {
			return (int) SPM_Settings_Page::get('tolleranza_scaduto_giorni');
		}
		return 60; // fallback
	}
	private static function get_auto_cessazione_giorni() {
		if (class_exists('SPM_Settings_Page')) {
			return (int) SPM_Settings_Page::get('auto_cessazione_giorni');
		}
		return 90; // fallback
	}

	/**
	 * Aggiorna la riga storico del mese corrente e il KPI per il mese (idempotente).
	 * Chiama SPM_Statistics_Handler solo se presente.
	 */
	// private static function stats_touch_month(int $post_id, ?string $yyyymm = null) : void {
	// 	if (!class_exists('SPM_Statistics_Handler')) return;
	// 	$ym = $yyyymm ?: current_time('Y-m');
	// 	SPM_Statistics_Handler::instance()->materialize_month($post_id, $ym);
	// }
	// 
	private static function stats_touch_month(int $post_id, ?string $yyyymm = null) : void {
		if (!class_exists('SPM_Statistics_Handler')) return;
	
		$ym = $yyyymm ?: current_time('Y-m');
	
		// Esegui backfill completo solo la prima volta per questo contratto
		$already = get_post_meta($post_id, '_spm_stats_backfilled', true);
	
		if (!$already) {
			// backfill_contract deduce da sola data_attivazione/post_date e ferma a oggi
			SPM_Statistics_Handler::instance()->backfill_contract((int)$post_id, null, null);
			update_post_meta($post_id, '_spm_stats_backfilled', 1);
		} else {
			// normale materializzazione del mese corrente
			SPM_Statistics_Handler::instance()->materialize_month((int)$post_id, $ym);
		}
	}

	/**
	 * Avanza la scadenza di N periodi finché non è > oggi.
	 * Ritorna array [$nuovaData, $passiEffettuati]. Limite 120 per evitare loop.
	 */
	private static function roll_forward_due_date($from_date, $frequenza) {
		$next  = $from_date;
		$steps = 0;
		for ($i = 0; $i < 120; $i++) {
			// se NON è scaduta (<= oggi è scaduta), interrompi
			if (!SPM_Date_Helper::is_expired($next)) break;
			$next = SPM_Date_Helper::calculate_next_due_date($next, $frequenza);
			$steps++;
		}
		// se era nel futuro già all'inizio, garantisci almeno 1 passo per casi attivi
		if ($steps === 0 && !SPM_Date_Helper::is_expired($from_date)) {
			$next  = SPM_Date_Helper::calculate_next_due_date($from_date, $frequenza);
			$steps = 1;
		}
		return [$next, $steps];
	}

	/**
	 * Inizializza la classe
	 */
	public static function init() {

		add_action('acf/save_post', [__CLASS__, 'pre_acf_capture_diffs'], 1);

		// Hook salvataggio contratto
		add_action('acf/save_post', [__CLASS__, 'on_contract_save'], 20);
		add_action('wp_ajax_spm_acf_save', [__CLASS__, 'ajax_acf_save']);

		// AJAX per recupero dati servizio
		add_action('wp_ajax_spm_get_servizio_defaults', [__CLASS__, 'ajax_get_servizio_defaults']);

		// Cron giornaliero
		add_action('spm_daily_check', [__CLASS__, 'daily_check']);

		// Azioni AJAX per pulsanti
		add_action('wp_ajax_spm_contract_action', [__CLASS__, 'handle_ajax_action']);

		// Colonne admin
		add_filter('manage_contratti_posts_columns', [__CLASS__, 'add_admin_columns']);
		add_action('manage_contratti_posts_custom_column', [__CLASS__, 'render_admin_columns'], 10, 2);

		add_filter('manage_edit-contratti_sortable_columns', [__CLASS__, 'add_sortable_columns']);
		add_action('pre_get_posts', [__CLASS__, 'handle_sortable_and_filters']);

		add_action('restrict_manage_posts', [__CLASS__, 'add_admin_filters']); // UI filtri
		add_filter('parse_query', [__CLASS__, 'apply_admin_filters']);         // Logica filtri

		// --- LOCK UI ACF (cliente/servizio dopo creazione; stato se cessato)
		add_filter('acf/prepare_field/name=cliente',  [__CLASS__, 'acf_lock_cliente']);
		add_filter('acf/prepare_field/name=servizio', [__CLASS__, 'acf_lock_servizio']);
		add_filter('acf/prepare_field/name=stato',    [__CLASS__, 'acf_lock_stato_if_cessato']);
		add_action('admin_head',                      [__CLASS__, 'admin_css_locked_fields']);

		// --- ENFORCEMENT SERVER-SIDE (ignora modifiche non permesse)
		add_filter('acf/update_value/name=cliente',   [__CLASS__, 'acf_enforce_cliente'], 10, 3);
		add_filter('acf/update_value/name=servizio',  [__CLASS__, 'acf_enforce_servizio'], 10, 3);
		add_filter('acf/update_value/name=stato',     [__CLASS__, 'acf_enforce_stato'],    10, 3);

		// --- Rimuovi "Modifica rapida" dalla lista (può bypassare ACF)
		add_filter('post_row_actions',                [__CLASS__, 'remove_quick_edit'], 10, 2);

		// Sync statistiche (servizio + KPI) quando il post cambia "esistenza"
		add_action('wp_trash_post',      [__CLASS__, 'on_trash_untrash_delete']);
		add_action('untrash_post',       [__CLASS__, 'on_trash_untrash_delete']);
		add_action('before_delete_post', [__CLASS__, 'on_trash_untrash_delete']);

		// Metabox azioni
		add_action('add_meta_boxes_contratti', [__CLASS__, 'add_action_metabox']);
		add_action('admin_head-post.php', function(){
			global $post;
			if ($post && $post->post_type === 'contratti') {
				echo '<style>#submitdiv{display:none!important;}</style>';
			}
		});
		add_action('admin_head-post-new.php', function(){
			$screen = get_current_screen();
			if ($screen && $screen->post_type === 'contratti') {
				echo '<style>#submitdiv{display:none!important;}</style>';
			}
		});

		// Nascondi/lock pulsante Aggiorna (Classic + Gutenberg) quando cessato
		add_action('admin_head-post.php', [__CLASS__, 'admin_head_hide_update_for_cessati']);

		// Disattiva autosave/heartbeat (evita salvataggi impliciti) quando cessato
		add_action('admin_enqueue_scripts', [__CLASS__, 'disable_autosave_for_cessati']);

		// Script admin
		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);

		// Schedula cron se non esiste
		if (!wp_next_scheduled('spm_daily_check')) {
			wp_schedule_event(strtotime('tomorrow 8am'), 'daily', 'spm_daily_check');
		}
	}

	/**
	 * Enqueue script admin
	 */
	public static function enqueue_admin_scripts($hook) {
		global $post_type;

		if ($post_type === 'contratti') {
			wp_enqueue_script(
				'spm-acf-dynamic',
				plugin_dir_url(__FILE__) . '../assets/js/acf-dynamic-values.js',
				['jquery', 'acf'],
				'2.0.0',
				true
			);

			wp_enqueue_script(
				'spm-native-save',
				plugin_dir_url(__FILE__) . '../assets/js/spm-native-save.js',
				['jquery','acf-input'],
				'1.0.0',
				true
			);

			// Ricava l'ID del post in modo robusto
			$post_id = 0;
			if (!empty($_GET['post'])) {
				$post_id = (int) $_GET['post']; // post.php
			} elseif (!empty($GLOBALS['post']->ID)) {
				$post_id = (int) $GLOBALS['post']->ID; // fallback
			}

			wp_localize_script('spm-native-save', 'SPM_VARS', [
				'postId'              => $post_id,
				'ajaxUrl'             => admin_url('admin-ajax.php'),
				'nonceAcfSave'        => wp_create_nonce('spm_acf_save'),
				'nonceContractAction' => wp_create_nonce('spm_contract_action'),
			]);
		}
	}

	/**
	 * AJAX: Recupera defaults dal servizio selezionato
	 */
	public static function ajax_get_servizio_defaults() {
		// Verifica permessi
		if (!current_user_can('edit_posts')) {
			wp_send_json_error(['message' => 'Non autorizzato']);
		}

		$servizio_id = intval($_POST['servizio_id'] ?? 0);
		if (!$servizio_id) {
			wp_send_json_error(['message' => 'ID servizio non valido']);
		}

		// Recupera dati servizio
		$data = [
			'prezzo_base'                 => get_field('prezzo_base', $servizio_id),
			'cadenza_fatturazione_default'=> get_field('cadenza_fatturazione_default', $servizio_id),
			'frequenza_ricorrenza'        => get_field('frequenza_ricorrenza', $servizio_id),
			'giorni_pre_reminder'         => get_field('giorni_pre_reminder', $servizio_id),
			'descrizione_admin'           => get_field('descrizione_admin', $servizio_id),
		];

		// Filtra valori nulli/vuoti
		$data = array_filter($data, function($value) {
			return $value !== null && $value !== '';
		});

		wp_send_json_success($data);
	}

	/**
	 * Quando si salva un contratto
	 */
	public static function on_contract_save($post_id) {
		if (get_post_type($post_id) !== 'contratti' || self::$is_saving) {
			return;
		}

		// Evita salvataggi "fantasma"
		if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
			return;
		}

		self::$is_saving = true;

		// 1) Normalizza date
		self::normalize_dates($post_id);

		// 2) Ricalcolo scadenza condizionale
		self::maybe_calculate_scadenza($post_id);

		// 3) Primo log?
		$storico = get_field('storico_contratto', $post_id);
		$is_first_log = empty($storico) || (is_array($storico) && count($storico) === 0);

		if ($is_first_log) {
			$ctx = [
				'data_inizio'       => get_field('data_attivazione', $post_id),
				'scadenza_iniziale' => get_field('data_prossima_scadenza', $post_id),
			];
			self::log_operazione($post_id, 'creazione', null, '', $ctx);
		}

		// 4) Aggiorna stato
		self::update_stato($post_id);

		// 5) Titolo automatico
		self::set_contract_title($post_id);

		// 6) Log "modifica" se non era il primo
		if (!$is_first_log) {
			$diff = self::$pending_diffs[$post_id]['changes'] ?? null;
			if ($diff) {
				self::log_operazione($post_id, 'modifica', null, '', ['changes' => $diff]);
				unset(self::$pending_diffs[$post_id]);
			} else {
				self::log_operazione($post_id, 'modifica', null, 'Contratto modificato');
			}
		}

		// 7) Aggiorna KPI mese corrente
		self::stats_touch_month((int)$post_id);

		// 8) Aggiorna stats servizio (se usate)
		self::touch_servizio_stats($post_id);

		self::$is_saving = false;
	}

	private static function touch_servizio_stats($contract_id){
		$servizio_id = get_field('servizio', $contract_id);
		if ($servizio_id && function_exists('spm_update_servizio_stats')) {
			spm_update_servizio_stats($servizio_id);
		}
	}

	/**
	 * Normalizza le date al formato standard
	 */
	private static function normalize_dates($post_id) {
		$date_fields = ['data_attivazione', 'data_prossima_scadenza'];

		foreach ($date_fields as $field) {
			$value = get_field($field, $post_id);
			if ($value) {
				$normalized = SPM_Date_Helper::to_db_format($value);
				if ($normalized && $normalized !== $value) {
					update_field($field, $normalized, $post_id);
				}
			}
		}
	}

	/**
	 * FORZA il calcolo della scadenza (sempre, anche se presente)
	 */
	private static function force_calculate_scadenza($post_id) {
		$data_attivazione = get_field('data_attivazione', $post_id);
		$frequenza = get_field('frequenza', $post_id);

		if ($data_attivazione && $frequenza) {
			$nuova_scadenza = SPM_Date_Helper::calculate_next_due_date($data_attivazione, $frequenza);
			update_field('data_prossima_scadenza', $nuova_scadenza, $post_id);
		}
	}

	/**
	 * Aggiorna stato basato su scadenza (con auto-cessazione oltre soglia)
	 */
	private static function update_stato($post_id) {
		$scadenza = get_field('data_prossima_scadenza', $post_id);
		$stato    = get_field('stato', $post_id);

		// Non toccare stati finali manuali
		if (in_array($stato, ['cessato'], true)) {
			return;
		}

		if ($scadenza) {
			$is_expired = SPM_Date_Helper::is_expired($scadenza);
			$days_since = SPM_Date_Helper::days_since_due($scadenza);

			// 1) Se troppo arretrato -> auto-cessato e stop
			if ($is_expired && $days_since !== null && $days_since > self::get_auto_cessazione_giorni()) {
				update_field('stato', 'cessato', $post_id);
				// AGGIUNTA: data_cessazione alla data odierna (solo se non già presente)
				if (!get_field('data_cessazione', $post_id)) {
					update_field('data_cessazione', current_time('Y-m-d'), $post_id);
				}
				self::log_operazione($post_id, 'cessazione', 0, '', ['days_since' => $days_since]);
				self::stats_touch_month((int)$post_id);
				return;
			}

			// 2) Stato coerente con la data
			if ($is_expired) {
				if ($stato !== 'scaduto' && $stato !== 'sospeso') {
					update_field('stato', 'scaduto', $post_id);
					self::log_operazione($post_id, 'scadenza', null, '', ['trigger' => 'auto']);
					self::stats_touch_month((int)$post_id);
				}
			} else {
				if ($stato === 'scaduto') {
					update_field('stato', 'attivo', $post_id);
					self::log_operazione($post_id, 'riattivazione', null, '', ['nuovo_stato' => 'attivo']);
					self::stats_touch_month((int)$post_id);
				}
			}
		}
	}

	/**
	 * Imposta titolo automatico del contratto
	 */
	private static function set_contract_title($post_id) {
		$cliente_id  = get_field('cliente',  $post_id);
		$servizio_id = get_field('servizio', $post_id);

		if ($cliente_id && $servizio_id) {
			$cliente_nome  = get_the_title($cliente_id);
			$servizio_nome = get_the_title($servizio_id);

			$title = sprintf('#%d - %s - %s', $post_id, $cliente_nome, $servizio_nome);

			// Evita loop infinito
			remove_action('acf/save_post', [__CLASS__, 'on_contract_save'], 20);
			wp_update_post(['ID' => $post_id, 'post_title' => $title]);
			add_action('acf/save_post', [__CLASS__, 'on_contract_save'], 20);
		}
	}

	private static function log_operazione($post_id, $tipo_operazione, $importo = null, $note = '', $context = []) {
		$storico = get_field('storico_contratto', $post_id) ?: [];

		// Importo robusto
		if ($importo === null) {
			$val = get_field('prezzo_contratto', $post_id);
			if ($val !== '' && $val !== null) {
				$importo = $val;
			} else {
				$servizio_id = get_field('servizio', $post_id);
				$val = $servizio_id ? get_field('prezzo_base', $servizio_id) : null;
				$importo = ($val !== '' && $val !== null) ? $val : 0;
			}
		}

		// Autogenera nota se non fornita
		if ($note === '' && !empty($context)) {
			$note = self::build_note_from_context($tipo_operazione, $context, $post_id);
		}

		$current_user = wp_get_current_user();
		$utente = $current_user->display_name ?: 'Sistema';

		$storico_entry = [
			'data_operazione' => wp_date('Y-m-d'),
			'ora_operazione'  => wp_date('H:i'),
			'tipo_operazione' => $tipo_operazione,
			'importo'         => $importo,
			'utente'          => $utente,
			'note'            => $note
		];

		array_unshift($storico, $storico_entry);
		$storico = array_slice($storico, 0, 50);

		update_field('storico_contratto', $storico, $post_id);
	}

	private static function build_note_from_context($tipo, array $ctx, $post_id) {
		$fmt = function($ymd) { return $ymd ? SPM_Date_Helper::to_display_format($ymd) : '—'; };

		switch ($tipo) {
			case 'creazione': {
				$inizio = $fmt($ctx['data_inizio']        ?? get_field('data_attivazione', $post_id));
				$scad   = $fmt($ctx['scadenza_iniziale']   ?? get_field('data_prossima_scadenza', $post_id));
				return "Creato. Inizio: {$inizio}. Prima scadenza: {$scad}.";
			}
			case 'rinnovo_automatico':
			case 'rinnovo_manuale': {
				$old   = $fmt($ctx['scadenza_precedente'] ?? null);
				$new   = $fmt($ctx['scadenza_nuova']      ?? null);
				$steps = isset($ctx['steps']) ? (int)$ctx['steps'] : 1;
				$stepTxt = $steps > 1 ? " (+{$steps} periodi)" : "";
				return "Rinnovo{$stepTxt}. Da {$old} a {$new}.";
			}
			case 'cessazione': {
				if (isset($ctx['days_since'])) return "Cessato. Scaduto da {$ctx['days_since']} giorni.";
				return "Cessato.";
			}
			case 'riattivazione': {
				if (isset($ctx['nuovo_stato'])) return "Riattivato (stato: {$ctx['nuovo_stato']}).";
				return "Riattivato.";
			}
			case 'scadenza': {
				if (!empty($ctx['trigger'])) return "Scadenza rilevata ({$ctx['trigger']}).";
				return "Scadenza rilevata.";
			}
			case 'modifica': {
				$labels = [
					'prezzo_contratto'     => 'Importo',
					'frequenza'            => 'Frequenza',
					'cadenza_fatturazione' => 'Cadenza fatturazione',
					'rinnovo_automatico'   => 'Rinnovo automatico',
					'giorni_preavviso'     => 'Preavviso',
					'note_interne'         => 'Note interne',
				];
				$parts = [];
				foreach (($ctx['changes'] ?? []) as $k => $delta) {
					$from = is_scalar($delta['from']) ? (string)$delta['from'] : json_encode($delta['from']);
					$to   = is_scalar($delta['to'])   ? (string)$delta['to']   : json_encode($delta['to']);
					if ($k === 'prezzo_contratto') { $from = ($from !== '' ? "€ {$from}" : '—'); $to = ($to !== '' ? "€ {$to}" : '—'); }
					if ($from === '') $from = '—';
					if ($to   === '') $to   = '—';
					$parts[] = ($labels[$k] ?? ucfirst($k)) . ": {$from} → {$to}";
				}
				return $parts ? ('Modifiche: ' . implode('; ', $parts)) : 'Modifica effettuata.';
			}
			default: return '';
		}
	}

	/**
	 * RINNOVA CONTRATTO - Policy: attivo = +1; scaduto poco = allinea; scaduto troppo = cessato, no rinnovo
	 */
	public static function rinnova_contratto($post_id) {
		$stato = get_field('stato', $post_id);

		// Verifica se rinnovabile
		if ($stato === 'cessato') {
			return ['success' => false, 'message' => 'Contratto cessato: rinnovo non consentito'];
		}

		$scadenza_attuale = get_field('data_prossima_scadenza', $post_id);
		$frequenza        = get_field('frequenza', $post_id);

		if (!$scadenza_attuale || !$frequenza) {
			return ['success' => false, 'message' => 'Dati mancanti per il rinnovo'];
		}

		$days_since = SPM_Date_Helper::days_since_due($scadenza_attuale);

		// CASO 3: SCADUTO TROPPO -> consideralo cessato e blocca
		if ($days_since !== null && $days_since > self::get_auto_cessazione_giorni()) {
			update_field('stato', 'cessato', $post_id);
			// AGGIUNTA
			if (!get_field('data_cessazione', $post_id)) {
				update_field('data_cessazione', current_time('Y-m-d'), $post_id);
			}
			self::log_operazione($post_id, 'cessazione', 0, "Blocco rinnovo: scaduto da {$days_since} giorni (soglia " . self::get_auto_cessazione_giorni() . ").");
			self::stats_touch_month((int)$post_id);
			self::touch_servizio_stats($post_id);
			return ['success' => false, 'message' => 'Contratto scaduto da troppo tempo: considerato cessato. Non rinnovabile.'];
		}

		// Importo robusto (mai vuoto)
		$importo = get_field('prezzo_contratto', $post_id);
		if ($importo === '' || $importo === null) {
			$servizio_id = get_field('servizio', $post_id);
			$val = $servizio_id ? get_field('prezzo_base', $servizio_id) : null;
			$importo = ($val !== '' && $val !== null) ? $val : 0;
		}

		$old_scad = $scadenza_attuale;

		// CASO 1: ATTIVO (scadenza oggi o futuro) -> +1 periodo
		if ($days_since !== null && $days_since <= 0) {
			$nuova_scadenza = SPM_Date_Helper::calculate_next_due_date($scadenza_attuale, $frequenza);
			update_field('data_prossima_scadenza', $nuova_scadenza, $post_id);
			update_field('stato', 'attivo', $post_id);

			self::log_operazione(
				$post_id,
				'rinnovo_manuale',
				$importo,
				'',
				['scadenza_precedente' => $old_scad, 'scadenza_nuova' => $nuova_scadenza, 'steps' => 1]
			);

			self::stats_touch_month((int)$post_id);
			self::touch_servizio_stats($post_id);

			return ['success' => true, 'message' => 'Contratto rinnovato. Scadenza: ' . SPM_Date_Helper::to_display_format($nuova_scadenza)];
		}

		// CASO 2: SCADUTO POCO (entro tolleranza) -> allinea oltre oggi (catch-up)
		if ($days_since !== null && $days_since > 0 && $days_since <= self::get_tolleranza_scaduto_giorni()) {
			[$nuova_scadenza, $steps] = self::roll_forward_due_date($scadenza_attuale, $frequenza);

			update_field('data_prossima_scadenza', $nuova_scadenza, $post_id);
			update_field('stato', 'attivo', $post_id);

			self::log_operazione(
				$post_id,
				'rinnovo_manuale',
				$importo,
				'',
				['scadenza_precedente' => $old_scad, 'scadenza_nuova' => $nuova_scadenza, 'steps' => (int)max(1,$steps)]
			);

			self::stats_touch_month((int)$post_id);
			self::touch_servizio_stats($post_id);

			return ['success' => true, 'message' => 'Contratto allineato. Scadenza: ' . SPM_Date_Helper::to_display_format($nuova_scadenza)];
		}

		// Fallback teorico
		return ['success' => false, 'message' => 'Impossibile rinnovare in questo stato.'];
	}

	/**
	 * SOSPENDI CONTRATTO
	 */
	public static function sospendi_contratto($post_id) {
		$stato_precedente = get_field('stato', $post_id);

		update_field('stato', 'sospeso', $post_id);
		self::log_operazione($post_id, 'sospensione', null, "Contratto sospeso (era: $stato_precedente)");

		self::stats_touch_month((int)$post_id);
		self::touch_servizio_stats($post_id);

		return ['success' => true, 'message' => 'Contratto sospeso con successo'];
	}

	/**
	 * RIATTIVA CONTRATTO
	 */
	public static function riattiva_contratto($post_id) {
		$scadenza = get_field('data_prossima_scadenza', $post_id);

		// Verifica se scaduto
		$nuovo_stato = SPM_Date_Helper::is_expired($scadenza) ? 'scaduto' : 'attivo';

		update_field('stato', $nuovo_stato, $post_id);
		self::log_operazione($post_id, 'riattivazione', null, "Contratto riattivato con stato: $nuovo_stato");

		self::stats_touch_month((int)$post_id);
		self::touch_servizio_stats($post_id);

		return ['success' => true, 'message' => "Contratto riattivato (stato: $nuovo_stato)"];
	}

	/**
	 * CESSA CONTRATTO
	 */
	public static function cessa_contratto($post_id) {
		$stato_precedente = get_field('stato', $post_id);

		update_field('stato', 'cessato', $post_id);
		// AGGIUNTA: fissa la data del click
		update_field('data_cessazione', current_time('Y-m-d'), $post_id);
		self::log_operazione($post_id, 'cessazione', null, "Contratto cessato definitivamente (era: $stato_precedente)");

		self::stats_touch_month((int)$post_id);
		self::touch_servizio_stats($post_id);

		return ['success' => true, 'message' => 'Contratto cessato definitivamente'];
	}

	/**
	 * CHECK GIORNALIERO
	 */
	public static function daily_check() {
		// 1. Aggiorna stati contratti scaduti
		self::check_contratti_scaduti();

		// 2. Processa rinnovi automatici
		self::processa_rinnovi_automatici();

		// 3. Invia reminder (disabilitato per ora)
		self::invia_reminder();
	}

	/**
	 * Controlla contratti scaduti
	 */
	private static function check_contratti_scaduti() {
		$args = [
			'post_type'      => 'contratti',
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'     => 'stato',
					'value'   => ['attivo', 'scaduto'],
					'compare' => 'IN',
				],
			],
		];

		$query = new WP_Query($args);

		if ($query->have_posts()) {
			foreach ($query->posts as $post) {
				$old_stato = get_field('stato', $post->ID);
				self::update_stato($post->ID);

				// Se cambiato stato, logga e aggiorna KPI
				$new_stato = get_field('stato', $post->ID);
				if ($old_stato !== $new_stato) {
					if ($new_stato !== 'cessato') {
						self::log_operazione(
							$post->ID,
							'scadenza',
							null,
							'Stato aggiornato automaticamente da controllo giornaliero'
						);
					}
					self::stats_touch_month((int)$post->ID);
					self::touch_servizio_stats($post->ID);
				}
			}
		}
	}

	/**
	 * Processa rinnovi automatici
	 */
	private static function processa_rinnovi_automatici() {
		$args = [
			'post_type'      => 'contratti',
			'posts_per_page' => -1,
			'meta_query'     => [
				'relation' => 'AND',
				[ 'key' => 'rinnovo_automatico', 'value' => '1' ],
				[ 'key' => 'stato', 'value' => ['attivo', 'scaduto'], 'compare' => 'IN' ],
				[ 'key' => 'data_prossima_scadenza', 'value' => current_time('Y-m-d'), 'compare' => '<=' ],
			]
		];

		$query = new WP_Query($args);

		if ($query->have_posts()) {
			foreach ($query->posts as $post) {
				$scadenza_attuale = get_field('data_prossima_scadenza', $post->ID);
				$frequenza        = get_field('frequenza', $post->ID);

				if (!$scadenza_attuale || !$frequenza) {
					continue;
				}

				$old_scad   = $scadenza_attuale;
				$days_since = SPM_Date_Helper::days_since_due($scadenza_attuale);

				// 1) BLOCCO OLTRE SOGLIA: auto-cessa invece di rinnovare
				if ($days_since !== null && $days_since > self::get_auto_cessazione_giorni()) {
					update_field('stato', 'cessato', $post->ID);
					// AGGIUNTA
					if (!get_field('data_cessazione', $post->ID)) {
						update_field('data_cessazione', current_time('Y-m-d'), $post->ID);
					}

					self::log_operazione($post->ID, 'cessazione', 0, '', ['days_since' => $days_since]);
					self::stats_touch_month((int)$post->ID);
					self::touch_servizio_stats($post->ID);
					continue;
				}

				// 2) RINNOVO AUTOMATICO (policy semplice: +1 periodo)
				$nuova_scadenza = SPM_Date_Helper::calculate_next_due_date($scadenza_attuale, $frequenza);

				update_field('data_prossima_scadenza', $nuova_scadenza, $post->ID);
				update_field('stato', 'attivo', $post->ID);
				self::touch_servizio_stats($post->ID);

				// importo robusto
				$importo = get_field('prezzo_contratto', $post->ID);
				if ($importo === '' || $importo === null) {
					$servizio_id = get_field('servizio', $post->ID);
					$val = $servizio_id ? get_field('prezzo_base', $servizio_id) : null;
					$importo = ($val !== '' && $val !== null) ? $val : 0;
				}

				self::log_operazione(
					$post->ID,
					'rinnovo_automatico',
					$importo,
					'',
					['scadenza_precedente' => $old_scad, 'scadenza_nuova' => $nuova_scadenza, 'steps' => 1]
				);

				self::stats_touch_month((int)$post->ID);

				// log di sistema
				error_log('SPM: Rinnovo automatico contratto #' . $post->ID . ' fino al ' . $nuova_scadenza);
			}

		}
	}

	/**
	 * Invia reminder scadenze (disabilitato per ora)
	 */
	private static function invia_reminder() {
		return; // disabilitato temporaneamente

		$args = [
			'post_type'      => 'contratti',
			'posts_per_page' => -1,
			'meta_query'     => [
				[ 'key' => 'stato', 'value' => 'attivo' ]
			]
		];

		$query = new WP_Query($args);

		if ($query->have_posts()) {
			foreach ($query->posts as $post) {
				$scadenza         = get_field('data_prossima_scadenza', $post->ID);
				$giorni_preavviso = get_field('giorni_preavviso', $post->ID) ?: 30;

				$giorni_mancanti = SPM_Date_Helper::days_until_due($scadenza);

				// Invia reminder se corrisponde ai giorni preavviso
				if ($giorni_mancanti == $giorni_preavviso) {
					$sent = self::send_reminder_email($post->ID);
					if ($sent) {
						self::log_operazione($post->ID, 'modifica', null, "Reminder email inviato ($giorni_mancanti giorni alla scadenza)");
					}
				}
			}
		}
	}

	/**
	 * Invia email reminder
	 */
	private static function send_reminder_email($post_id) {
		$cliente_id = get_field('cliente', $post_id);
		$email      = get_field('email', $cliente_id);

		if (!$email) return false;

		$servizio_id = get_field('servizio', $post_id);
		$scadenza    = get_field('data_prossima_scadenza', $post_id);
		$giorni      = SPM_Date_Helper::days_until_due($scadenza);

		$subject = 'Promemoria scadenza servizio';
		$message = sprintf(
			"Gentile %s,\n\nIl servizio %s scadrà tra %d giorni (%s).\n\nCordiali saluti,\nStudio 121",
			get_the_title($cliente_id),
			get_the_title($servizio_id),
			$giorni,
			SPM_Date_Helper::to_display_format($scadenza)
		);

		return wp_mail($email, $subject, $message);
	}

	/**
	 * COLONNE ADMIN
	 */
	public static function add_admin_columns($columns) {
		$new_columns = [
			'cb'        => $columns['cb'],
			'title'     => 'Contratto',
			'cliente'   => 'Cliente',
			'servizio'  => 'Servizio',
			'scadenza'  => 'Scadenza',
			'frequenza' => 'Frequenza',
			'stato'     => 'Stato',
			// 'azioni'  => 'Azioni Rapide'
		];
		return $new_columns;
	}

	public static function render_admin_columns($column, $post_id) {
		switch ($column) {
			case 'cliente':
				$cliente_id = get_field('cliente', $post_id);
				echo $cliente_id ? esc_html(get_the_title($cliente_id)) : '—';
				break;

			case 'servizio':
				$servizio_id = get_field('servizio', $post_id);
				echo $servizio_id ? esc_html(get_the_title($servizio_id)) : '—';
				break;

			case 'scadenza':
				$scadenza = get_field('data_prossima_scadenza', $post_id);
				if ($scadenza) {
					$giorni  = SPM_Date_Helper::days_until_due($scadenza);
					$display = SPM_Date_Helper::to_display_format($scadenza);

					if ($giorni < 0) {
						echo '<span style="color:red">⚠️ ' . $display . '</span>';
					} elseif ($giorni <= 30) {
						echo '<span style="color:orange">⏰ ' . $display . '</span>';
					} else {
						echo '<span style="color:green">✓ ' . $display . '</span>';
					}
				} else {
					echo '—';
				}
				break;

			case 'frequenza':
				$freq = get_field('frequenza', $post_id);
				$map  = [
					'mensile'        => 'Mensile',
					'trimestrale'    => 'Trimestrale',
					'quadrimestrale' => 'Quadrimestrale',
					'semestrale'     => 'Semestrale',
					'annuale'        => 'Annuale',
				];
				$label = $map[$freq] ?? ( $freq ? ucfirst((string)$freq) : '—' );
				echo esc_html($label);
				break;

			case 'stato':
				$stato  = get_field('stato', $post_id);
				$colors = ['attivo'=>'green','sospeso'=>'orange','scaduto'=>'red','cessato'=>'gray'];
				$emoji  = ['attivo'=>'🟢','sospeso'=>'🟡','scaduto'=>'🔴','cessato'=>'⚫'];
				$color  = $colors[$stato] ?? 'gray';
				$icon   = $emoji[$stato] ?? '';
				$label  = ($stato !== null && $stato !== '') ? ucfirst((string)$stato) : '—';
				echo '<span style="color:' . esc_attr($color) . '">' . $icon . ' ' . esc_html($label) . '</span>';
				break;

			case 'azioni':
				$stato    = get_field('stato', $post_id);
				$scadenza = get_field('data_prossima_scadenza', $post_id);

				$oltre_soglia = false;
				$days_since   = null;
				if ($scadenza) {
					$days_since   = SPM_Date_Helper::days_since_due($scadenza);
					$oltre_soglia = ($days_since !== null && $days_since > self::get_auto_cessazione_giorni()); // fix costante
				}

				if ($stato !== 'cessato') {
					if ($oltre_soglia) {
						echo '<span style="color:#dc3232;font-weight:bold;">Scaduto da ' . (int)$days_since . ' giorni → Cessazione automatica</span>';
					} else {
						echo '<button class="button button-small spm-action" data-action="rinnova" data-id="' . $post_id . '">Rinnova</button> ';
					}
				}

				if ($stato === 'attivo') {
					echo '<button class="button button-small spm-action" data-action="sospendi" data-id="' . $post_id . '">Sospendi</button>';
				} elseif ($stato === 'sospeso') {
					echo '<button class="button button-small spm-action" data-action="riattiva" data-id="' . $post_id . '">Riattiva</button>';
				}
				break;
		}
	}

	/**
	 * METABOX AZIONI
	 */
	public static function add_action_metabox($post) {
		add_meta_box(
			'spm_contract_actions',
			'⚡ Azioni Contratto',
			[__CLASS__, 'render_action_metabox'],
			'contratti',
			'side',
			'high'
		);
	}

	public static function render_action_metabox($post) {
		$status = get_post_status($post);

		// Caso 1: contratto nuovo (draft / auto-draft)
		if (in_array($status, ['auto-draft','draft'], true)) {
			?>
			<div id="spm-actions-box">
				<p>
					<button type="button" class="button button-primary button-large"
						onclick="spmNativePrimaryClick()">
						💾 Salva Contratto
					</button>
				</p>
				<div style="background:#f9f9f9;padding:10px;border-left:4px solid #777;">
					Dopo il primo salvataggio saranno disponibili le azioni rapide.
				</div>
			</div>
			<?php
			return;
		}

		// Caso 2: contratto già salvato
		$stato           = get_field('stato', $post->ID);
		$scadenza        = get_field('data_prossima_scadenza', $post->ID);
		$giorni_mancanti = $scadenza ? SPM_Date_Helper::days_until_due($scadenza) : null;
		$days_since      = $scadenza ? SPM_Date_Helper::days_since_due($scadenza) : null;
		$oltre_soglia    = ($days_since !== null && $days_since > self::get_auto_cessazione_giorni());
		?>
		<div id="spm-actions-box">

			<!-- Info -->
			<div style="background:#f9f9f9;padding:10px;margin-bottom:15px;border-left:4px solid #0073aa;">
				<strong>Stato:</strong> <?php echo esc_html($stato ?: '—'); ?><br>
				<?php if ($scadenza): ?>
					<strong>Scadenza:</strong> <?php echo SPM_Date_Helper::to_display_format($scadenza); ?><br>
					<strong>Giorni mancanti:</strong> <?php echo $giorni_mancanti; ?>
				<?php endif; ?>
			</div>

			<!-- Pulsante Salva -->
			<p>
				<button type="button" class="button button-primary button-large" onclick="spmNativePrimaryClick()">
					💾 Salva Contratto
				</button>
			</p>

			<!-- Azioni -->
			<?php if ($stato !== 'cessato'): ?>
				<?php if ($stato === 'attivo'): ?>
					<?php if ($oltre_soglia): ?>
						<div style="background:#fff3f3;border-left:4px solid #dc3232;padding:8px;margin-bottom:10px;">
							Scaduto da <?php echo (int)$days_since; ?> giorni (oltre soglia).
							Verrà marcato <strong>cessato</strong> automaticamente; rinnovo non consentito.
						</div>
					<?php else: ?>
						<p><button class="button button-primary spm-action" data-action="rinnova" data-id="<?php echo $post->ID; ?>">
							🔄 Rinnova Contratto
						</button></p>
					<?php endif; ?>
					<p><button class="button spm-action" data-action="sospendi" data-id="<?php echo $post->ID; ?>">
						⏸️ Sospendi
					</button></p>

				<?php elseif ($stato === 'scaduto' && !$oltre_soglia): ?>
					<p><button class="button button-primary spm-action" data-action="rinnova" data-id="<?php echo $post->ID; ?>">
						🔄 Rinnova Contratto
					</button></p>

				<?php elseif ($stato === 'sospeso'): ?>
					<p><button class="button button-primary spm-action" data-action="riattiva" data-id="<?php echo $post->ID; ?>">
						▶️ Riattiva
					</button></p>
				<?php endif; ?>

				<hr>
				<p><button class="button spm-action" data-action="cessa" data-id="<?php echo $post->ID; ?>"
					onclick="return confirm('Cessare definitivamente il contratto?');">
					⛔ Cessa Contratto
				</button></p>
			<?php endif; ?>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('.spm-action').on('click', function(e) {
				e.preventDefault();
				var btn = $(this);
				var action = btn.data('action');
				var id = btn.data('id');

				// rinnovo delegato allo script esterno (Salva ACF → Rinnova)
				if (action === 'rinnova') return;

				if (action === 'cessa' && !confirm('Sei sicuro di voler cessare definitivamente il contratto?')) {
					return;
				}

				var oldLabel = btn.text();
				btn.prop('disabled', true).text('Elaborazione…');

				$.post(ajaxurl, {
					action: 'spm_contract_action',
					contract_action: action,
					post_id: id,
					_wpnonce: '<?php echo wp_create_nonce('spm_contract_action'); ?>'
				})
				.done(function(response) {
					if (response.success) {
						alert(response.data.message);
						location.reload();
					} else {
						var msg = (response.data && response.data.message) ? response.data.message : 'Operazione fallita';
						alert('Errore: ' + msg);
						btn.prop('disabled', false).text(oldLabel);
					}
				})
				.fail(function() {
					alert('Errore di rete o permessi');
					btn.prop('disabled', false).text(oldLabel);
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * HANDLE AJAX
	 */
	public static function handle_ajax_action() {

		check_ajax_referer('spm_contract_action', '_wpnonce');

		$post_id = intval($_POST['post_id']);
		$action  = sanitize_text_field($_POST['contract_action']);

		if (!current_user_can('edit_post', $post_id)) {
			wp_send_json_error(['message' => 'Non autorizzato']);
		}

		$status = get_post_status($post_id);
		if (in_array($status, ['auto-draft','draft'], true)) {
			wp_send_json_error(['message' => 'Contratto non ancora inizializzato. Salva prima.']);
		}

		$result = false;

		switch ($action) {
			case 'rinnova':
				$result = self::rinnova_contratto($post_id);
				break;
			case 'sospendi':
				$result = self::sospendi_contratto($post_id);
				break;
			case 'riattiva':
				$result = self::riattiva_contratto($post_id);
				break;
			case 'cessa':
				$result = self::cessa_contratto($post_id);
				break;
		}

		if ($result && $result['success']) {
			wp_send_json_success($result);
		} else {
			wp_send_json_error($result ?: ['message' => 'Azione non valida']);
		}
	}

	/**
	 * Helper: è un contratto cessato?
	 */
	private static function is_contratto_cessato($post_id) {
		if (!$post_id || get_post_type($post_id) !== 'contratti') return false;
		$stato = get_field('stato', $post_id);
		return ($stato === 'cessato');
	}

	/**
	 * UI editor: nasconde il pulsante "Aggiorna" e blocca il salvataggio se cessato.
	 */
	public static function admin_head_hide_update_for_cessati() {
		global $post, $pagenow;
		if ($pagenow !== 'post.php' || !$post || $post->post_type !== 'contratti') return;
		if (!self::is_contratto_cessato($post->ID)) return;

		// Classic editor: nascondi il pulsante "Aggiorna"
		echo '<style>
			#publishing-action .button.button-primary.button-large { display:none !important; }
			#submitpost #major-publishing-actions { display:flex; justify-content:space-between; align-items:center; }
		</style>';

		// Gutenberg: blocca il salvataggio + nascondi il primario in header
		?>
		<script>
		(function(){
			function lockSaving(){
				if (window.wp && wp.data && wp.data.dispatch) {
					try { wp.data.dispatch('core/editor').lockPostSaving('spm-contract-locked'); } catch(e){}
				}
			}
			function hidePrimaryButton(){
				var btn = document.querySelector('.edit-post-header__settings .components-button.is-primary');
				if (btn) btn.style.display = 'none';
			}
			var i = setInterval(function(){ lockSaving(); hidePrimaryButton(); }, 300);
			setTimeout(function(){ clearInterval(i); }, 3000);
			if (window.wp && wp.domReady) wp.domReady(function(){ lockSaving(); hidePrimaryButton(); });
		})();
		</script>
		<?php
	}

	/**
	 * Evita salvataggi impliciti (autosave, heartbeat) quando cessato.
	 */
	public static function disable_autosave_for_cessati($hook) {
		global $post;
		if ($hook !== 'post.php' || !$post || $post->post_type !== 'contratti') return;
		if (!self::is_contratto_cessato($post->ID)) return;

		wp_deregister_script('autosave');
		wp_dequeue_script('autosave');
		wp_dequeue_script('heartbeat');
	}

	/**
	 * Rende ordinabili alcune colonne della lista contratti.
	 */
	public static function add_sortable_columns($columns) {
		$columns['servizio']  = 'servizio';
		$columns['scadenza']  = 'data_prossima_scadenza';
		$columns['frequenza'] = 'frequenza';
		$columns['stato']     = 'stato';
		return $columns;
	}

	/**
	 * Gestisce l'ordinamento quando si cliccano le intestazioni.
	 */
	public static function handle_sortable_and_filters($query) {
		if (!is_admin() || !$query->is_main_query()) return;
		if ($query->get('post_type') !== 'contratti') return;

		$orderby = $query->get('orderby');

		// SCADENZA (campo meta YYYY-MM-DD)
		if ($orderby === 'data_prossima_scadenza' || $orderby === 'scadenza') {
			$query->set('meta_key', 'data_prossima_scadenza');
			$query->set('orderby', 'meta_value');
		}

		// FREQUENZA (meta testuale)
		if ($orderby === 'frequenza') {
			$query->set('meta_key', 'frequenza');
			$query->set('orderby', 'meta_value');
		}

		// STATO (meta testuale)
		if ($orderby === 'stato') {
			$query->set('meta_key', 'stato');
			$query->set('orderby', 'meta_value');
		}

		// SERVIZIO (ACF post object: salviamo l'ID nel meta 'servizio')
		if ($orderby === 'servizio') {
			$query->set('meta_key', 'servizio');
			$query->set('orderby', 'meta_value_num');
		}
	}

	/**
	 * Aggiunge i filtri sopra la tabella della lista contratti.
	 */
	public static function add_admin_filters($post_type) {
		if ($post_type !== 'contratti') return;

		$servizio_post_type = 'servizi';
		$sel_servizio = isset($_GET['filter_servizio']) ? intval($_GET['filter_servizio']) : 0;

		wp_dropdown_pages([
			'post_type'         => $servizio_post_type,
			'name'              => 'filter_servizio',
			'show_option_all'   => 'Tutti i servizi',
			'option_none_value' => '',
			'selected'          => $sel_servizio,
			'number'            => 0,
		]);

		$scad_from = isset($_GET['filter_scadenza_from']) ? esc_attr($_GET['filter_scadenza_from']) : '';
		$scad_to   = isset($_GET['filter_scadenza_to'])   ? esc_attr($_GET['filter_scadenza_to'])   : '';
		echo '<input type="date" name="filter_scadenza_from" value="' . $scad_from . '" placeholder="Scadenza da" style="margin-left:8px" />';
		echo '<input type="date" name="filter_scadenza_to"   value="' . $scad_to   . '" placeholder="Scadenza a"  style="margin-left:4px" />';

		$freq_options = [
			''               => 'Tutte le frequenze',
			'mensile'        => 'Mensile',
			'trimestrale'    => 'Trimestrale',
			'quadrimestrale' => 'Quadrimestrale',
			'semestrale'     => 'Semestrale',
			'annuale'        => 'Annuale',
		];
		$sel_freq = isset($_GET['filter_frequenza']) ? sanitize_text_field($_GET['filter_frequenza']) : '';
		echo '<select name="filter_frequenza" style="margin-left:8px">';
		foreach ($freq_options as $val => $label) {
			printf('<option value="%s"%s>%s</option>',
				esc_attr($val),
				selected($sel_freq, $val, false),
				esc_html($label)
			);
		}
		echo '</select>';

		$stato_options = [
			''         => 'Tutti gli stati',
			'attivo'   => 'Attivo',
			'sospeso'  => 'Sospeso',
			'scaduto'  => 'Scaduto',
			'cessato'  => 'Cessato',
		];
		$sel_stato = isset($_GET['filter_stato']) ? sanitize_text_field($_GET['filter_stato']) : '';
		echo '<select name="filter_stato" style="margin-left:8px">';
		foreach ($stato_options as $val => $label) {
			printf('<option value="%s"%s>%s</option>',
				esc_attr($val),
				selected($sel_stato, $val, false),
				esc_html($label)
			);
		}
		echo '</select>';
	}

	/**
	 * Applica i filtri alla query principale della lista contratti.
	 */
	public static function apply_admin_filters($query) {
		if (!is_admin() || !$query->is_main_query()) return;
		if ($query->get('post_type') !== 'contratti') return;

		$meta_query = [];
		$meta_query['relation'] = 'AND';

		// Servizio
		if (!empty($_GET['filter_servizio'])) {
			$servizio_id = intval($_GET['filter_servizio']);
			if ($servizio_id > 0) {
				$meta_query[] = [ 'key' => 'servizio', 'value' => $servizio_id, 'compare' => '=' ];
			}
		}

		// Frequenza
		if (!empty($_GET['filter_frequenza'])) {
			$freq = sanitize_text_field($_GET['filter_frequenza']);
			$meta_query[] = [ 'key' => 'frequenza', 'value' => $freq, 'compare' => '=' ];
		}

		// Stato
		if (!empty($_GET['filter_stato'])) {
			$stato = sanitize_text_field($_GET['filter_stato']);
			$meta_query[] = [ 'key' => 'stato', 'value' => $stato, 'compare' => '=' ];
		}

		// Scadenza (intervallo date YYYY-MM-DD)
		$from = !empty($_GET['filter_scadenza_from']) ? sanitize_text_field($_GET['filter_scadenza_from']) : '';
		$to   = !empty($_GET['filter_scadenza_to'])   ? sanitize_text_field($_GET['filter_scadenza_to'])   : '';

		if ($from && $to) {
			$meta_query[] = [
				'key'     => 'data_prossima_scadenza',
				'value'   => [$from, $to],
				'compare' => 'BETWEEN',
				'type'    => 'CHAR',
			];
		} elseif ($from) {
			$meta_query[] = [
				'key'     => 'data_prossima_scadenza',
				'value'   => $from,
				'compare' => '>=',
				'type'    => 'CHAR',
			];
		} elseif ($to) {
			$meta_query[] = [
				'key'     => 'data_prossima_scadenza',
				'value'   => $to,
				'compare' => '<=',
				'type'    => 'CHAR',
			];
		}

		if (count($meta_query) > 1) {
			$query->set('meta_query', $meta_query);
		}
	}

	/* ================== LOCK UI (ACF) ================== */

	public static function acf_lock_cliente($field){
		if (!is_admin()) return $field;
		global $post;
		if (!$post || $post->post_type !== 'contratti') return $field;

		$val = get_field('cliente', $post->ID);
		$is_initialized = $post->post_status !== 'auto-draft' && !empty($val);

		if ($is_initialized) {
			$field['readonly'] = 1; // mantieni il valore nel POST
			$field['disabled'] = 0;
			$field['wrapper']['class'] = ($field['wrapper']['class'] ?? '').' spm-locked';
			$field['instructions'] = trim(($field['instructions'] ?? '').' (bloccato dopo la creazione)');
		}
		return $field;
	}

	public static function acf_lock_servizio($field){
		if (!is_admin()) return $field;
		global $post;
		if (!$post || $post->post_type !== 'contratti') return $field;

		$val = get_field('servizio', $post->ID);
		$is_initialized = $post->post_status !== 'auto-draft' && !empty($val);

		if ($is_initialized) {
			$field['readonly'] = 1;
			$field['disabled'] = 0;
			$field['wrapper']['class'] = ($field['wrapper']['class'] ?? '').' spm-locked';
			$field['instructions'] = trim(($field['instructions'] ?? '').' (bloccato dopo la creazione)');
		}
		return $field;
	}

	public static function acf_lock_stato_if_cessato($field){
		if (!is_admin()) return $field;
		global $post;
		if (!$post || $post->post_type !== 'contratti') return $field;

		$stato = get_field('stato', $post->ID);
		if ($stato === 'cessato') {
			$field['readonly'] = 1;  // non disabled, così il valore resta nel POST
			$field['disabled'] = 0;
			$field['wrapper']['class'] = ($field['wrapper']['class'] ?? '').' spm-locked';
			$field['instructions'] = trim(($field['instructions'] ?? '').' ');
		}
		return $field;
	}

	public static function admin_css_locked_fields(){
		echo '<style>
			.acf-field.spm-locked .acf-input select,
			.acf-field.spm-locked .acf-input input[type="text"],
			.acf-field.spm-locked .acf-input .select2-selection{
				background:#f6f7f7 !important; pointer-events:none; opacity:.9; cursor:not-allowed;
			}
		</style>';
	}

	/* ============ ENFORCEMENT SERVER-SIDE (ACF) ============ */

	public static function acf_enforce_cliente($value, $post_id, $field){
		$prev = get_field('cliente', $post_id, false);
		if (!empty($prev)) {
			return $prev; // già impostato: non cambiare
		}
		return $value;
	}

	public static function acf_enforce_servizio($value, $post_id, $field){
		$prev = get_field('servizio', $post_id, false);
		if (!empty($prev)) {
			return $prev; // già impostato: non cambiare
		}
		return $value;
	}

	public static function acf_enforce_stato($value, $post_id, $field){
		$prev = get_field('stato', $post_id, false);
		if ($prev === 'cessato') {
			return 'cessato'; // resta bloccato
		}
		return $value;
	}

	/* ============== Hardening lista (Quick Edit) ============== */

	public static function remove_quick_edit($actions, $post){
		if ($post->post_type === 'contratti') {
			unset($actions['inline hide-if-no-js']); // "Modifica rapida"
		}
		return $actions;
	}

	/**
	 * Mantiene sincronizzate le statistiche quando il post cambia “stato di esistenza”.
	 */
	public static function on_trash_untrash_delete($post_id){
		if (get_post_type($post_id) !== 'contratti') {
			return;
		}
		self::stats_touch_month((int)$post_id);
		self::touch_servizio_stats($post_id);
	}

	/**
	 * PRE: intercetta i valori ACF in arrivo e confronta con quelli in DB.
	 * Va eseguito PRIMA che ACF scriva i meta (priority 1).
	 */
	public static function pre_acf_capture_diffs($post_id) {
		if (get_post_type($post_id) !== 'contratti') return;

		$acf = $_POST['acf'] ?? null;
		if (!$acf || !is_array($acf)) return;

		// Mappa ACF field_key => meta_key
		$map = [
			'field_spm_contratto_prezzo'               => 'prezzo_contratto',
			'field_spm_contratto_frequenza'            => 'frequenza',
			'field_spm_contratto_cadenza_fatturazione' => 'cadenza_fatturazione',
			'field_spm_contratto_rinnovo_auto'         => 'rinnovo_automatico',
			'field_spm_contratto_giorni_preavviso'     => 'giorni_preavviso',
			'field_spm_contratto_note'                 => 'note_interne',
		];

		$changes = [];

		foreach ($map as $acf_key => $meta_key) {
			if (!array_key_exists($acf_key, $acf)) continue;

			$new = $acf[$acf_key];
			$old = get_field($meta_key, $post_id);

			// Normalizzazioni minime
			if (is_string($old)) $old = trim($old);
			if (is_string($new)) $new = trim($new);

			// true_false e select possono arrivare come stringhe
			if ($old !== $new) {
				$changes[$meta_key] = ['from' => $old, 'to' => $new];
			}
		}

		if ($changes) {
			self::$pending_diffs[$post_id] = ['changes' => $changes];
		}
	}

	/**
	 * Ricalcola la scadenza solo quando serve:
	 * - post nuovo (auto-draft/draft) O
	 * - campo data_prossima_scadenza vuoto/non ancora inizializzato
	 */
	private static function maybe_calculate_scadenza($post_id) {
		$status   = get_post_status($post_id);
		$has_date = (bool) get_field('data_prossima_scadenza', $post_id);

		if (in_array($status, ['auto-draft','draft'], true) || !$has_date) {
			self::force_calculate_scadenza($post_id);
		}
		// altrimenti, non toccare la scadenza durante i salvataggi successivi
	}

	public static function ajax_acf_save() {
		check_ajax_referer('spm_acf_save', '_wpnonce');

		$post_id = intval($_POST['post_id'] ?? 0);
		if (!$post_id || get_post_type($post_id) !== 'contratti') {
			wp_send_json_error(['message' => 'Post non valido']);
		}
		if (!current_user_can('edit_post', $post_id)) {
			wp_send_json_error(['message' => 'Non autorizzato']);
		}

		// campi ACF serializzati come array field_key => value
		$fields = isset($_POST['fields']) && is_array($_POST['fields']) ? $_POST['fields'] : [];

		if (empty($fields)) {
			wp_send_json_error(['message' => 'Nessun dato da salvare']);
		}

		// Scrive i meta ACF (usa field_keys!).
		if (function_exists('acf_update_values')) {
			acf_update_values($fields, $post_id);
		} else {
			foreach ($fields as $field_key => $value) {
				acf_update_value($value, $post_id, $field_key);
			}
		}

		wp_send_json_success(['message' => 'Dati ACF salvati']);
	}
}
