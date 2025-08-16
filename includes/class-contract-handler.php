<?php
/**
 * Contract Handler - Gestione Migliorata Contratti
 * 
 * Migliorie implementate:
 * 1. Data scadenza sempre calcolata automaticamente
 * 2. Storico completo di tutte le operazioni
 * 3. Log dettagliato con data/ora/utente
 * 4. Precompilazione automatica da servizio
 */

defined('ABSPATH') || exit;

class SPM_Contract_Handler {
	
	private static $is_saving = false; // Previeni loop infiniti
	
	/**
	 * Inizializza la classe
	 */
	public static function init() {
		// Hook salvataggio contratto
		add_action('acf/save_post', [__CLASS__, 'on_contract_save'], 20);
		
		// AJAX per recupero dati servizio
		add_action('wp_ajax_spm_get_servizio_defaults', [__CLASS__, 'ajax_get_servizio_defaults']);
		
		// Cron giornaliero
		add_action('spm_daily_check', [__CLASS__, 'daily_check']);
		
		// Azioni AJAX per pulsanti
		add_action('wp_ajax_spm_contract_action', [__CLASS__, 'handle_ajax_action']);
		
		// Colonne admin
		add_filter('manage_contratti_posts_columns', [__CLASS__, 'add_admin_columns']);
		add_action('manage_contratti_posts_custom_column', [__CLASS__, 'render_admin_columns'], 10, 2);
		
		// Metabox azioni
		add_action('add_meta_boxes', [__CLASS__, 'add_action_metabox']);
		
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
		}
	}
	
	/**
	 * AJAX: Recupera defaults dal servizio selezionato
	 */
	public static function ajax_get_servizio_defaults() {
		// Verifica nonce e permessi
		if (!current_user_can('edit_posts')) {
			wp_send_json_error(['message' => 'Non autorizzato']);
		}
		
		$servizio_id = intval($_POST['servizio_id'] ?? 0);
		if (!$servizio_id) {
			wp_send_json_error(['message' => 'ID servizio non valido']);
		}
		
		// Recupera dati servizio
		$data = [
			'prezzo_base' => get_field('prezzo_base', $servizio_id),
			'frequenza_ricorrenza' => get_field('frequenza_ricorrenza', $servizio_id),
			'giorni_pre_reminder' => get_field('giorni_pre_reminder', $servizio_id),
			'descrizione_admin' => get_field('descrizione_admin', $servizio_id),
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
	
		// 2) SEMPRE ricalcola scadenza (non è più editabile)
		self::force_calculate_scadenza($post_id);
	
		// --- DECISIONE PRIMO LOG PRIMA DI UPDATE_STATO ---
		$storico = get_field('storico_contratto', $post_id);
		$is_first_log = empty($storico) || (is_array($storico) && count($storico) === 0);
	
		// 3) Se è il primo salvataggio, logga subito "creazione"
		if ($is_first_log) {
			self::log_operazione($post_id, 'creazione', null, 'Contratto creato');
		}
	
		// 4) Aggiorna stato basato su scadenza (potrebbe loggare "scadenza")
		self::update_stato($post_id);
	
		// 5) Imposta titolo automatico
		self::set_contract_title($post_id);
	
		// 6) Se NON era il primo salvataggio, logga "modifica"
		if (!$is_first_log) {
			self::log_operazione($post_id, 'modifica', null, 'Contratto modificato');
		}
	
		self::$is_saving = false;
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
			
			// Aggiorna sempre, ignorando valore esistente
			update_field('data_prossima_scadenza', $nuova_scadenza, $post_id);
		}
	}
	
	/**
	 * Aggiorna stato basato su scadenza
	 */
	private static function update_stato($post_id) {
		$scadenza = get_field('data_prossima_scadenza', $post_id);
		$stato = get_field('stato', $post_id);
		
		// Non toccare stati manuali (sospeso/cessato)
		if (in_array($stato, ['sospeso', 'cessato'])) {
			return;
		}
		
		if ($scadenza) {
			$is_expired = SPM_Date_Helper::is_expired($scadenza);
			
			if ($is_expired && $stato !== 'scaduto') {
				update_field('stato', 'scaduto', $post_id);
				self::log_operazione($post_id, 'scadenza', null, 'Contratto scaduto automaticamente');
			} elseif (!$is_expired && $stato === 'scaduto') {
				update_field('stato', 'attivo', $post_id);
				self::log_operazione($post_id, 'riattivazione', null, 'Contratto riattivato automaticamente');
			}
		}
	}
	
	/**
	 * Imposta titolo automatico del contratto
	 */
	private static function set_contract_title($post_id) {
		$cliente_id = get_field('cliente', $post_id);
		$servizio_id = get_field('servizio', $post_id);
		
		if ($cliente_id && $servizio_id) {
			$cliente_nome = get_the_title($cliente_id);
			$servizio_nome = get_the_title($servizio_id);
			
			$title = sprintf('#%d - %s - %s', $post_id, $cliente_nome, $servizio_nome);
			
			// Evita loop infinito
			remove_action('acf/save_post', [__CLASS__, 'on_contract_save'], 20);
			
			wp_update_post([
				'ID' => $post_id,
				'post_title' => $title
			]);
			
			add_action('acf/save_post', [__CLASS__, 'on_contract_save'], 20);
		}
	}
	
	/**
	 * LOG OPERAZIONE - Nuovo sistema di storico completo
	 */
	private static function log_operazione($post_id, $tipo_operazione, $importo = null, $note = '') {
		$storico = get_field('storico_contratto', $post_id) ?: [];
		
		// Se importo non specificato, calcolalo SEMPRE dal contratto/servizio
		if ($importo === null) {
			// attenzione: "0" (string) o 0 (int) sono valori validi, quindi uso controlli stretti
			$val = get_field('prezzo_contratto', $post_id);
			if ($val !== '' && $val !== null) {
				$importo = $val;
			} else {
				$servizio_id = get_field('servizio', $post_id);
				$val = $servizio_id ? get_field('prezzo_base', $servizio_id) : null;
				$importo = ($val !== '' && $val !== null) ? $val : 0; // fallback finale
			}
		}
		
		// Ottieni utente corrente
		$current_user = wp_get_current_user();
		$utente = $current_user->display_name ?: 'Sistema';
		
		// Aggiungi nuova voce al log
		$nuova_voce = [
			'data_operazione' => date('Y-m-d'),
			'ora_operazione' => date('H:i'),
			'tipo_operazione' => $tipo_operazione,
			'importo' => $importo,
			'utente' => $utente,
			'note' => $note
		];
		
		array_unshift($storico, $nuova_voce); // Aggiungi in cima (più recenti prima)
		
		// Mantieni solo ultimi 50 record per performance
		$storico = array_slice($storico, 0, 50);
		
		update_field('storico_contratto', $storico, $post_id);
	}
	
	/**
	 * RINNOVA CONTRATTO - Versione migliorata
	 */
	public static function rinnova_contratto($post_id) {
		$stato = get_field('stato', $post_id);
		
		// Verifica se rinnovabile
		if ($stato === 'cessato') {
			return ['success' => false, 'message' => 'Contratto cessato non rinnovabile'];
		}
		
		$scadenza_attuale = get_field('data_prossima_scadenza', $post_id);
		$frequenza = get_field('frequenza', $post_id);
		
		if (!$scadenza_attuale || !$frequenza) {
			return ['success' => false, 'message' => 'Dati mancanti per il rinnovo'];
		}
		
		// Calcola nuova scadenza
		$nuova_scadenza = SPM_Date_Helper::calculate_next_due_date($scadenza_attuale, $frequenza);
		
		// Aggiorna dati contratto
		update_field('data_prossima_scadenza', $nuova_scadenza, $post_id);
		update_field('stato', 'attivo', $post_id);
		
		// Log operazione
		$importo = get_field('prezzo_contratto', $post_id);
		if (!$importo) {
			$servizio_id = get_field('servizio', $post_id);
			$importo = get_field('prezzo_base', $servizio_id);
		}
		
		self::log_operazione($post_id, 'rinnovo_manuale', $importo, 'Rinnovo effettuato manualmente fino al ' . SPM_Date_Helper::to_display_format($nuova_scadenza));
		
		return [
			'success' => true, 
			'message' => 'Contratto rinnovato fino al ' . SPM_Date_Helper::to_display_format($nuova_scadenza)
		];
	}
	
	/**
	 * SOSPENDI CONTRATTO
	 */
	public static function sospendi_contratto($post_id) {
		$stato_precedente = get_field('stato', $post_id);
		
		update_field('stato', 'sospeso', $post_id);
		
		self::log_operazione($post_id, 'sospensione', null, "Contratto sospeso (era: $stato_precedente)");
		
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
		
		return ['success' => true, 'message' => "Contratto riattivato (stato: $nuovo_stato)"];
	}
	
	/**
	 * CESSA CONTRATTO
	 */
	public static function cessa_contratto($post_id) {
		$stato_precedente = get_field('stato', $post_id);
		
		update_field('stato', 'cessato', $post_id);
		
		self::log_operazione($post_id, 'cessazione', null, "Contratto cessato definitivamente (era: $stato_precedente)");
		
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
		
		// 3. Invia reminder
		self::invia_reminder();
	}
	
	/**
	 * Controlla contratti scaduti
	 */
	private static function check_contratti_scaduti() {
		$args = [
			'post_type' => 'contratti',
			'posts_per_page' => -1,
			'meta_query' => [
				[
					'key' => 'stato',
					'value' => 'attivo'
				]
			]
		];
		
		$query = new WP_Query($args);
		
		if ($query->have_posts()) {
			foreach ($query->posts as $post) {
				$old_stato = get_field('stato', $post->ID);
				self::update_stato($post->ID);
				
				// Se cambiato stato, logga
				$new_stato = get_field('stato', $post->ID);
				if ($old_stato !== $new_stato) {
					self::log_operazione($post->ID, 'scadenza', null, 'Stato aggiornato automaticamente da controllo giornaliero');
				}
			}
		}
	}
	
	/**
	 * Processa rinnovi automatici
	 */
	private static function processa_rinnovi_automatici() {
		$args = [
			'post_type' => 'contratti',
			'posts_per_page' => -1,
			'meta_query' => [
				'relation' => 'AND',
				[
					'key' => 'rinnovo_automatico',
					'value' => '1'
				],
				[
					'key' => 'stato',
					'value' => ['attivo', 'scaduto'],
					'compare' => 'IN'
				],
				[
					'key' => 'data_prossima_scadenza',
					'value' => date('Y-m-d'),
					'compare' => '<='
				]
			]
		];
		
		$query = new WP_Query($args);
		
		if ($query->have_posts()) {
			foreach ($query->posts as $post) {
				// Rinnovo automatico
				$scadenza_attuale = get_field('data_prossima_scadenza', $post->ID);
				$frequenza = get_field('frequenza', $post->ID);
				
				if ($scadenza_attuale && $frequenza) {
					$nuova_scadenza = SPM_Date_Helper::calculate_next_due_date($scadenza_attuale, $frequenza);
					
					update_field('data_prossima_scadenza', $nuova_scadenza, $post->ID);
					update_field('stato', 'attivo', $post->ID);
					
					// Log rinnovo automatico
					$importo = get_field('prezzo_contratto', $post->ID);
					if (!$importo) {
						$servizio_id = get_field('servizio', $post->ID);
						$importo = get_field('prezzo_base', $servizio_id);
					}
					
					self::log_operazione($post->ID, 'rinnovo_automatico', $importo, 'Rinnovo automatico eseguito fino al ' . SPM_Date_Helper::to_display_format($nuova_scadenza));
					
					// Log sistema
					error_log('SPM: Rinnovo automatico contratto #' . $post->ID . ' fino al ' . $nuova_scadenza);
				}
			}
		}
	}
	
	/**
	 * Invia reminder scadenze
	 */
	private static function invia_reminder() {
		$args = [
			'post_type' => 'contratti',
			'posts_per_page' => -1,
			'meta_query' => [
				[
					'key' => 'stato',
					'value' => 'attivo'
				]
			]
		];
		
		$query = new WP_Query($args);
		
		if ($query->have_posts()) {
			foreach ($query->posts as $post) {
				$scadenza = get_field('data_prossima_scadenza', $post->ID);
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
		$email = get_field('email', $cliente_id);
		
		if (!$email) return false;
		
		$servizio_id = get_field('servizio', $post_id);
		$scadenza = get_field('data_prossima_scadenza', $post_id);
		$giorni = SPM_Date_Helper::days_until_due($scadenza);
		
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
			'cb' => $columns['cb'],
			'title' => 'Contratto',
			'cliente' => 'Cliente',
			'servizio' => 'Servizio',
			'scadenza' => 'Scadenza',
			'stato' => 'Stato',
			'azioni' => 'Azioni Rapide'
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
					$giorni = SPM_Date_Helper::days_until_due($scadenza);
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
				
			case 'stato':
				$stato = get_field('stato', $post_id);
				$stato_key = $stato ?: 'indefinito'; // fallback
				$colors = [
					'attivo' => 'green',
					'sospeso' => 'orange',
					'scaduto' => 'red',
					'cessato' => 'gray'
				];
				$emoji = [
					'attivo' => '🟢',
					'sospeso' => '🟡',
					'scaduto' => '🔴',
					'cessato' => '⚫'
				];
				$color = $colors[$stato] ?? 'gray';
				$icon = $emoji[$stato] ?? '';
				// Label sicura: se $stato è null, mostra "—"
				$label = ($stato !== null && $stato !== '') 
					? ucfirst((string)$stato) 
					: '—';
				echo '<span style="color:' . $color . '">' . $icon . ' ' . ucfirst($stato) . '</span>';
				break;
				
			case 'azioni':
				$stato = get_field('stato', $post_id);
				
				if ($stato !== 'cessato') {
					echo '<button class="button button-small spm-action" data-action="rinnova" data-id="' . $post_id . '">Rinnova</button> ';
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
	public static function add_action_metabox() {
		add_meta_box(
			'spm_contract_actions',
			'⚡ Azioni Rapide',
			[__CLASS__, 'render_action_metabox'],
			'contratti',
			'side',
			'high'
		);
	}
	
	public static function render_action_metabox($post) {
		$stato = get_field('stato', $post->ID);
		$scadenza = get_field('data_prossima_scadenza', $post->ID);
		$giorni_mancanti = $scadenza ? SPM_Date_Helper::days_until_due($scadenza) : 999;
		?>
		<div id="spm-actions-box">
			<!-- Info stato -->
			<div style="background: #f9f9f9; padding: 10px; margin-bottom: 15px; border-left: 4px solid #0073aa;">
				<?php
				$stato_label = $stato !== null && $stato !== '' ? ucfirst((string)$stato) : '—';
				?>
				<strong>Stato:</strong> <?php echo esc_html($stato_label); ?><br>
				<?php if ($scadenza): ?>
					<strong>Scadenza:</strong> <?php echo SPM_Date_Helper::to_display_format($scadenza); ?><br>
					<strong>Giorni mancanti:</strong> <?php echo $giorni_mancanti; ?>
				<?php endif; ?>
			</div>
			
			<!-- Azioni -->
			<?php if ($stato !== 'cessato'): ?>
				<p><button class="button button-primary spm-action" data-action="rinnova" data-id="<?php echo $post->ID; ?>">
					🔄 Rinnova Contratto
				</button></p>
			<?php endif; ?>
			
			<?php if ($stato === 'attivo'): ?>
				<p><button class="button spm-action" data-action="sospendi" data-id="<?php echo $post->ID; ?>">
					⏸️ Sospendi
				</button></p>
			<?php elseif ($stato === 'sospeso'): ?>
				<p><button class="button spm-action" data-action="riattiva" data-id="<?php echo $post->ID; ?>">
					▶️ Riattiva
				</button></p>
			<?php endif; ?>
			
			<?php if ($stato !== 'cessato'): ?>
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
				
				if (action === 'cessa' && !confirm('Sei sicuro di voler cessare definitivamente il contratto?')) {
					return;
				}
				
				btn.prop('disabled', true).text('Elaborazione...');
				
				$.post(ajaxurl, {
					action: 'spm_contract_action',
					contract_action: action,
					post_id: id,
					_wpnonce: '<?php echo wp_create_nonce('spm_contract_action'); ?>'
				}, function(response) {
					if (response.success) {
						alert(response.data.message);
						location.reload();
					} else {
						alert('Errore: ' + (response.data?.message || 'Operazione fallita'));
						btn.prop('disabled', false);
					}
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
		$action = sanitize_text_field($_POST['contract_action']);
		
		if (!current_user_can('edit_post', $post_id)) {
			wp_send_json_error(['message' => 'Non autorizzato']);
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
}