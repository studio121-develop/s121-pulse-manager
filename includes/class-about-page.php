<?php
/**
 * SPM_About_Page
 *
 * Pagina "Informazioni" del plugin S121 Pulse Manager:
 * - Panoramica e funzionalità principali
 * - Come funziona la fatturazione (ledger, stati)
 * - Integrazione con Fatture in Cloud
 * - Dove trovare le cose (mappa sezioni)
 * - Stato sistema / prerequisiti rapidi
 * - Link rapidi (azioni amministrative)
 *
 * Stile coerente con la Dashboard: card bianche, bordi colorati, layout a due colonne.
 */

defined('ABSPATH') || exit;

if (!class_exists('SPM_About_Page')) {

	class SPM_About_Page {

		const SLUG = 'spm-about';

		/** Bootstrap */
		public static function init() {
			add_action('admin_menu', [__CLASS__, 'register_menu']);
		}

		/** Registra il sottomenu sotto "Pulse Manager" */
		public static function register_menu() {
			// Parent slug definito nel tuo SPM_Admin_Menu per il top-level
			$parent = 's121-pulse-manager';

			add_submenu_page(
				$parent,
				'Informazioni',
				'ℹ️ About',
				'manage_options',
				self::SLUG,
				[__CLASS__, 'render']
			);
		}

		/** Render pagina About */
		public static function render() {
			if (!current_user_can('manage_options')) {
				return;
			}

			$meta = self::collect_meta();
			$checks = self::system_checks();
			?>
			<div class="wrap">
				<h1>ℹ️ Informazioni su S121 Pulse Manager</h1>

				<!-- INTRO / HEADLINE -->
				<div style="margin:12px 0 18px; background:#fff; padding:16px; border:1px solid #ddd;">
					<p style="font-size:14px; color:#444; margin:0;">
						<strong>S121 Pulse Manager</strong> è il plugin pensato per gestire <em>servizi ricorrenti</em>, <em>contratti</em> e
						<em>fatturazione periodica</em> dentro WordPress, con integrazione diretta a <strong>Fatture in Cloud</strong>.
					</p>
				</div>

				<div style="display:flex; gap:20px; align-items:flex-start; flex-wrap:wrap;">
					<!-- COLONNA SINISTRA -->
					<div style="flex:1 1 640px; min-width:520px;">

						<!-- COSA FA -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #2271b1;">
							<h2 style="margin-top:0; color:#2271b1;">🔎 Panoramica</h2>
							<p style="color:#555; margin-top:8px;">
								Il plugin centralizza la gestione di <strong>Clienti</strong>, <strong>Servizi</strong> e <strong>Contratti</strong>,
								automatizzando scadenze e generazione dei periodi di fatturazione. Offre una dashboard con KPI (MRR/ARR),
								strumenti di riconciliazione e azioni rapide.
							</p>
							<ul style="margin:10px 0 0 18px; list-style:disc;">
								<li>Custom Post Types: <em>clienti</em>, <em>servizi</em>, <em>contratti</em></li>
								<li>Rinnovi automatici e promemoria</li>
								<li>Fatturazione ricorrente con ledger dedicato</li>
								<li>Integrazione OAuth2 con Fatture in Cloud</li>
								<li>Dashboard con KPI (MRR/ARR/ARPU) e scadenze</li>
							</ul>
						</div>

						<!-- COME FUNZIONA LA FATTURAZIONE -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #1e8cbe;">
							<h2 style="margin-top:0; color:#1e8cbe;">🧾 Fatturazione: come funziona</h2>
							<p style="color:#555;">
								Per ogni <strong>contratto</strong> attivo il sistema materializza i periodi in una tabella dedicata
								(<code><?php echo esc_html($checks['tables']['spm_billing_ledger']['name']); ?></code>), secondo la cadenza
								(mensile, trimestrale, …). Ogni riga ha:
							</p>
							<ul style="margin:10px 0 0 18px; list-style:disc;">
								<li><strong>Periodo</strong> (inizio/fine), <strong>scadenza fattura</strong> e <strong>importo</strong></li>
								<li><strong>Stato</strong>:
									<ul style="margin:6px 0 0 18px; list-style:circle;">
										<li><strong>Da emettere</strong> (prima della scadenza)</li>
										<li><strong>Emessa</strong> (segnata manualmente o via integrazione)</li>
										<li><strong>Saltata</strong> (con motivo)</li>
									</ul>
								</li>
								<li><strong>Log sintetico</strong> nel contratto per tracciabilità (storico operazioni)</li>
							</ul>
							<p style="color:#555; margin-top:10px;">
								La pagina <em>Fatturazione</em> consente filtri rapidi per stato e data, azioni di “Segna emessa”, “Salta” e
								“Elimina” (anche su righe già emesse/saltate). Se un contratto viene eliminato, le righe collegate vengono rimosse.
							</p>
						</div>

						<!-- DOVE TROVO LE COSE -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #00a32a;">
							<h2 style="margin-top:0; color:#00a32a;">🧭 Dove trovare le cose</h2>
							<ul style="margin:10px 0 0 18px; list-style:disc;">
								<li><strong>Dashboard</strong>: panoramica KPI, scadenze e export CSV</li>
								<li><strong>Fatturazione</strong>: elenco periodi con stato/azioni (slug: <code>spm-billing-due</code>)</li>
								<li><strong>Contratti</strong>: ciclo di vita e storico azioni</li>
								<li><strong>Impostazioni</strong>: giorni di preavviso, grace period, orizzonte mesi</li>
								<li><strong>Integrazione FIC</strong>: setup OAuth e sincronizzazione clienti</li>
							</ul>
						</div>

						<!-- INTEGRAZIONE FIC -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #6c2eb9;">
							<h2 style="margin-top:0; color:#6c2eb9;">🔗 Integrazione Fatture in Cloud</h2>
							<p style="color:#555;">
								Autenticazione sicura via OAuth2, sincronizzazione anagrafiche clienti e normalizzazione dati. Il plugin gestisce
								refresh token, rate limiting e retry. La sincronizzazione può essere avviata manualmente o schedulata.
							</p>
							<p style="margin:10px 0 0;">
								<a class="button" href="<?php echo esc_url(admin_url('admin.php?spm_test_sync=1')); ?>">🔄 Avvia sincronizzazione clienti</a>
								<a class="button" style="margin-left:8px;" href="<?php echo esc_url(admin_url('admin.php?page=spm-settings')); ?>">⚙️ Apri Impostazioni</a>
							</p>
						</div>

					</div>

					<!-- COLONNA DESTRA (INFO / CHECK) -->
					<div style="flex:0 0 340px; min-width:320px;">
						<!-- VERSIONE / META -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #dba617;">
							<h2 style="margin-top:0; color:#dba617;">🏷️ Versione & Meta</h2>
							<table class="widefat striped" style="margin-top:6px;">
								<tbody>
									<tr>
										<td><strong>Versione plugin</strong></td>
										<td><?php echo esc_html($meta['version'] ?: 'n/d'); ?></td>
									</tr>
									<tr>
										<td><strong>PHP</strong></td>
										<td><?php echo esc_html(PHP_VERSION); ?></td>
									</tr>
									<tr>
										<td><strong>WordPress</strong></td>
										<td><?php echo esc_html(get_bloginfo('version')); ?></td>
									</tr>
									<tr>
										<td><strong>Timezone WP</strong></td>
										<td><?php echo esc_html(wp_timezone_string()); ?></td>
									</tr>
								</tbody>
							</table>
						</div>

						<!-- STATO SISTEMA -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #dc3232;">
							<h2 style="margin-top:0; color:#dc3232;">🧪 Stato sistema</h2>
							<table class="widefat fixed striped" style="margin-top:6px;">
								<tbody>
									<?php foreach ($checks['rows'] as $row): ?>
										<tr>
											<td style="width:60%;"><strong><?php echo esc_html($row['label']); ?></strong><br><span style="color:#666;"><?php echo esc_html($row['hint']); ?></span></td>
											<td>
												<span style="display:inline-block; padding:2px 8px; border-radius:12px; color:#fff; background:<?php echo esc_attr($row['ok'] ? '#00a32a' : '#dc3232'); ?>">
													<?php echo $row['ok'] ? 'OK' : 'Assente'; ?>
												</span>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
							<?php if (!empty($checks['notes'])): ?>
								<p style="color:#666; margin-top:8px;"><?php echo esc_html($checks['notes']); ?></p>
							<?php endif; ?>
						</div>

						<!-- LINK RAPIDI -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #00a32a;">
							<h2 style="margin-top:0; color:#00a32a;">⚡ Link rapidi</h2>
							<p><a class="button button-primary" style="width:100%;" href="<?php echo esc_url(admin_url('admin.php?page=s121-pulse-manager')); ?>">📊 Apri Dashboard</a></p>
							<p><a class="button" style="width:100%;" href="<?php echo esc_url(admin_url('admin.php?page=spm-billing-due')); ?>">🧾 Apri Fatturazione</a></p>
							<p><a class="button" style="width:100%;" href="<?php echo esc_url(admin_url('edit.php?post_type=contratti')); ?>">📁 Elenco Contratti</a></p>
							<p><a class="button" style="width:100%;" href="<?php echo esc_url(admin_url('admin.php?page=spm-settings')); ?>">⚙️ Impostazioni</a></p>
						</div>

						<!-- SUPPORTO -->
						<div style="background:white; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #2271b1;">
							<h2 style="margin-top:0; color:#2271b1;">🆘 Supporto</h2>
							<p style="color:#555; margin-top:6px;">
								Per assistenza o richieste di funzionalità: <a href="mailto:info@studio121.it">info@studio121.it</a> · <a href="https://studio121.it" target="_blank" rel="noopener noreferrer">studio121.it</a>
							</p>
						</div>
					</div>
				</div>

			</div>
			<?php
		}

		/** Raccoglie meta versione plugin in modo "robusto" */
		private static function collect_meta(): array {
			$version = '';

			// Proviamo a leggere il file principale del plugin se presente nella cartella padre
			$main = dirname(__DIR__) . '/s121-pulse-manager.php';
			if (file_exists($main)) {
				if (!function_exists('get_plugin_data')) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$data = get_plugin_data($main, false, false);
				$version = $data['Version'] ?? '';
			}

			// Fallback opzionale da opzione
			if (!$version) {
				$version = get_option('spm_plugin_version') ?: '';
			}

			return [
				'version' => $version,
			];
		}

		/** Controlli di stato/integrazione (table exists, token, cron, ACF) */
		private static function system_checks(): array {
			global $wpdb;

			$tables = [
				'spm_billing_ledger' => [
					'name' => $wpdb->prefix . 'spm_billing_ledger',
					'ok'   => false
				],
				'spm_kpi_monthly' => [
					'name' => $wpdb->prefix . 'spm_kpi_monthly',
					'ok'   => false
				],
				'spm_contract_history' => [
					'name' => $wpdb->prefix . 'spm_contract_history',
					'ok'   => false
				],
			];

			foreach ($tables as $k => $t) {
				$exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t['name'])) === $t['name'];
				$tables[$k]['ok'] = (bool)$exists;
			}

			$has_acf = function_exists('get_field');
			$cron_evt = wp_get_scheduled_event('spm_daily_check');
			$has_cron = (bool)$cron_evt;

			$has_fic = (bool) get_option('spm_fic_access_token');

			$rows = [
				[
					'label' => 'Tabella ledger fatturazione',
					'hint'  => $tables['spm_billing_ledger']['name'],
					'ok'    => $tables['spm_billing_ledger']['ok'],
				],
				[
					'label' => 'Tabella KPI mensili',
					'hint'  => $tables['spm_kpi_monthly']['name'],
					'ok'    => $tables['spm_kpi_monthly']['ok'],
				],
				[
					'label' => 'Tabella storico contratti',
					'hint'  => $tables['spm_contract_history']['name'],
					'ok'    => $tables['spm_contract_history']['ok'],
				],
				[
					'label' => 'ACF Pro attivo',
					'hint'  => 'Funzioni get_field()/update_field() disponibili',
					'ok'    => $has_acf,
				],
				[
					'label' => 'Cron giornaliero attivo',
					'hint'  => 'Hook: spm_daily_check',
					'ok'    => $has_cron,
				],
				[
					'label' => 'Token Fatture in Cloud presente',
					'hint'  => 'Opzione: spm_fic_access_token',
					'ok'    => $has_fic,
				],
			];

			$notes = '';
			if (!$has_cron) {
				$notes = 'Suggerimento: programma il cron per la materializzazione giornaliera e promemoria.';
			}

			return [
				'rows'   => $rows,
				'tables' => $tables,
				'notes'  => $notes,
			];
		}
	}

	SPM_About_Page::init();
}
