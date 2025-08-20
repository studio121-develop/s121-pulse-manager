<?php
/**
 * SPM_About_Page - Versione Arricchita
 *
 * Pagina "Informazioni" del plugin S121 Pulse Manager:
 * - Panoramica e funzionalità principali
 * - Come funziona la gestione clienti e servizi
 * - Ciclo di vita dei contratti e automazioni
 * - Sistema di fatturazione e integrazione FIC
 * - Dashboard e analytics
 * - Stato sistema / prerequisiti rapidi
 * - Link rapidi (azioni amministrative)
 *
 * Stile coerente: card bianche, bordi colorati, layout a due colonne.
 */

defined('ABSPATH') || exit;

if (!class_exists('SPM_About_Page')) {

	class SPM_About_Page {

		const SLUG = 'spm-about';

		/** Bootstrap - NON registra il menu (gestito da SPM_Admin_Menu) */
		public static function init() {
			// Il menu viene già registrato da SPM_Admin_Menu
			// Qui manteniamo solo la classe per il rendering
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
						<strong>S121 Pulse Manager</strong> è il plugin completo per gestire <em>servizi ricorrenti</em>, <em>contratti</em> e
						<em>fatturazione periodica</em> dentro WordPress, con integrazione diretta a <strong>Fatture in Cloud</strong>.
						Progettato per agenzie e fornitori di servizi che necessitano di un controllo preciso sui ricavi ricorrenti.
					</p>
				</div>

				<div style="display:flex; gap:20px; align-items:flex-start; flex-wrap:wrap;">
					<!-- COLONNA SINISTRA -->
					<div style="flex:1 1 640px; min-width:520px;">

						<!-- PANORAMICA GENERALE -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #2271b1;">
							<h2 style="margin-top:0; color:#2271b1;">🔎 Panoramica del Sistema</h2>
							<p style="color:#555; margin-top:8px;">
								Il plugin centralizza la gestione di <strong>Clienti</strong>, <strong>Servizi</strong> e <strong>Contratti</strong>,
								automatizzando scadenze, rinnovi e generazione dei periodi di fatturazione. Offre una dashboard completa con KPI 
								avanzati (MRR/ARR/ARPU), strumenti di riconciliazione e azioni rapide per la gestione quotidiana.
							</p>
							<ul style="margin:10px 0 0 18px; list-style:disc;">
								<li><strong>Custom Post Types</strong>: clienti, servizi, contratti con campi personalizzati avanzati</li>
								<li><strong>Automazioni intelligenti</strong>: rinnovi automatici, controlli scadenze, promemoria email</li>
								<li><strong>Sistema di fatturazione</strong>: ledger dedicato con materializzazione automatica dei periodi</li>
								<li><strong>Integrazione OAuth2</strong> sicura con Fatture in Cloud per sincronizzazione clienti</li>
								<li><strong>Dashboard analitica</strong> con KPI materializzati (MRR/ARR/ARPU) e export CSV</li>
								<li><strong>Gestione stati avanzata</strong>: attivo → scaduto → cessato con policy configurabili</li>
							</ul>
						</div>

						<!-- GESTIONE CLIENTI -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #00a32a;">
							<h2 style="margin-top:0; color:#00a32a;">👤 Gestione Clienti</h2>
							<p style="color:#555;">
								I <strong>clienti</strong> sono il cuore del sistema. Ogni cliente ha una scheda completa con dati anagrafici,
								informazioni di contatto e collegamento diretto con Fatture in Cloud per la sincronizzazione automatica.
							</p>
							<ul style="margin:10px 0 0 18px; list-style:disc;">
								<li><strong>Anagrafica completa</strong>: email, partita IVA, telefono, note personalizzate</li>
								<li><strong>Sincronizzazione FIC</strong>: import automatico da Fatture in Cloud con normalizzazione dati</li>
								<li><strong>Indicatori visivi</strong>: LED verde lampeggiante per clienti con contratti attivi</li>
								<li><strong>Vista scadenze</strong>: prossima scadenza tra tutti i contratti del cliente</li>
								<li><strong>Prevenzione duplicati</strong>: sistema di deduplicazione tramite ID Fatture in Cloud</li>
								<li><strong>Aggiornamenti automatici</strong>: dati clienti sempre allineati con il gestionale</li>
							</ul>
						</div>

						<!-- CATALOGO SERVIZI -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #6c2eb9;">
							<h2 style="margin-top:0; color:#6c2eb9;">🧩 Catalogo Servizi</h2>
							<p style="color:#555;">
								I <strong>servizi</strong> fungono da template per i contratti. Ogni servizio definisce prezzi, cadenze,
								durate e comportamenti di default che vengono applicati automaticamente ai nuovi contratti.
							</p>
							<ul style="margin:10px 0 0 18px; list-style:disc;">
								<li><strong>Template configurabili</strong>: prezzo base, cadenza fatturazione, durata contratto</li>
								<li><strong>Categorie organizzate</strong>: hosting, manutenzioni, social, advertising, altro</li>
								<li><strong>Automazioni email</strong>: template personalizzati per promemoria con placeholder dinamici</li>
								<li><strong>Rinnovo automatico</strong>: comportamento predefinito configurabile per servizio</li>
								<li><strong>Statistiche integrate</strong>: contratti attivi, ricavo mensile totale per servizio</li>
								<li><strong>Indicatore utilizzo</strong>: LED verde per servizi utilizzati in almeno un contratto</li>
							</ul>
						</div>

						<!-- CICLO DI VITA CONTRATTI -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #d98300;">
							<h2 style="margin-top:0; color:#d98300;">📄 Ciclo di Vita dei Contratti</h2>
							<p style="color:#555;">
								I <strong>contratti</strong> rappresentano l'accordo tra cliente e servizio. Il sistema gestisce automaticamente
								le transizioni di stato basate su date e policy configurabili, con storico completo di ogni operazione.
							</p>
							<ul style="margin:10px 0 0 18px; list-style:disc;">
								<li><strong>Stati automatici</strong>: attivo → scaduto → cessato con transizioni intelligenti</li>
								<li><strong>Policy flessibili</strong>: tolleranza rinnovi e soglie auto-cessazione configurabili</li>
								<li><strong>Rinnovi smart</strong>: catch-up automatico per contratti scaduti entro tolleranza</li>
								<li><strong>Storico completo</strong>: log dettagliato di ogni operazione con data, ora, utente e note</li>
								<li><strong>Calcoli automatici</strong>: scadenze calcolate dinamicamente da attivazione + frequenza</li>
								<li><strong>Blocchi di sicurezza</strong>: campi cliente/servizio bloccati dopo creazione</li>
							</ul>
						</div>

						<!-- COME FUNZIONA LA FATTURAZIONE -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #1e8cbe;">
							<h2 style="margin-top:0; color:#1e8cbe;">🧾 Sistema di Fatturazione</h2>
							<p style="color:#555;">
								Per ogni <strong>contratto</strong> attivo il sistema materializza automaticamente i periodi di fatturazione 
								in una tabella dedicata (<code><?php echo esc_html($checks['tables']['spm_billing_ledger']['name']); ?></code>), 
								seguendo la cadenza configurata (mensile, trimestrale, quadrimestrale, semestrale, annuale).
							</p>
							<ul style="margin:10px 0 0 18px; list-style:disc;">
								<li><strong>Materializzazione automatica</strong>: periodi generati in background con CRON giornaliero</li>
								<li><strong>Gestione stati avanzata</strong>:
									<ul style="margin:6px 0 0 18px; list-style:circle;">
										<li><strong>Da emettere</strong> (prima della scadenza con finestra configurabile)</li>
										<li><strong>Emessa</strong> (segnata manualmente o via integrazione futura)</li>
										<li><strong>Saltata</strong> (con motivo obbligatorio per audit)</li>
									</ul>
								</li>
								<li><strong>Finestre intelligenti</strong>: grace period post-fine-mese e preavviso pre-emissione</li>
								<li><strong>Azioni bulk</strong>: gestione multipla di righe con log automatico sui contratti</li>
								<li><strong>Pulizia automatica</strong>: rimozione righe quando contratti vengono eliminati</li>
								<li><strong>Log sintetico</strong> nel contratto per tracciabilità completa delle operazioni</li>
							</ul>
							<p style="color:#555; margin-top:10px;">
								La pagina <em>Fatturazione</em> offre filtri avanzati per stato e data, azioni rapide di gestione
								e indicatori visivi per fatture in ritardo. Supporta eliminazione anche di righe già processate
								per massima flessibilità gestionale.
							</p>
						</div>

						<!-- DASHBOARD E ANALYTICS -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #dc3232;">
							<h2 style="margin-top:0; color:#dc3232;">📊 Dashboard e Analytics</h2>
							<p style="color:#555;">
								La dashboard offre una vista completa delle performance aziendali con KPI materializzati in tempo reale,
								grafici interattivi e strumenti di export per analisi avanzate.
							</p>
							<ul style="margin:10px 0 0 18px; list-style:disc;">
								<li><strong>KPI materializzati</strong>: MRR, ARR, ARPU calcolati su dati storici consolidati</li>
								<li><strong>Vista temporale</strong>: serie storica con visualizzazione grafico/tabella configurabile</li>
								<li><strong>Scadenze prioritarie</strong>: contratti in scadenza con codici colore e giorni mancanti</li>
								<li><strong>Export avanzati</strong>: CSV di serie KPI e dettaglio mensile per riconciliazioni</li>
								<li><strong>Debug integrato</strong>: riconciliazione KPI vs dettaglio righe per audit</li>
								<li><strong>Filtri temporali</strong>: ultimi 6/12/24 mesi, anno corrente o range personalizzato</li>
							</ul>
						</div>

						<!-- AUTOMAZIONI -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #72aee6;">
							<h2 style="margin-top:0; color:#72aee6;">🤖 Automazioni e Controlli</h2>
							<p style="color:#555;">
								Il sistema è progettato per ridurre al minimo l'intervento manuale grazie a controlli automatici
								giornalieri e logiche di business intelligenti.
							</p>
							<ul style="margin:10px 0 0 18px; list-style:disc;">
								<li><strong>CRON giornaliero</strong>: controllo scadenze, aggiornamento stati, rinnovi automatici</li>
								<li><strong>Promemoria intelligenti</strong>: email personalizzate con template e timing configurabili</li>
								<li><strong>Transizioni automatiche</strong>: attivo → scaduto → cessato secondo policy aziendali</li>
								<li><strong>Sincronizzazione notturna</strong>: aggiornamento clienti da Fatture in Cloud</li>
								<li><strong>Materializzazione KPI</strong>: aggiornamento statistiche in background</li>
								<li><strong>Rate limiting</strong>: gestione intelligente delle chiamate API con retry automatico</li>
							</ul>
						</div>

						<!-- DOVE TROVO LE COSE -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #00a32a;">
							<h2 style="margin-top:0; color:#00a32a;">🧭 Navigazione del Sistema</h2>
							<ul style="margin:10px 0 0 18px; list-style:disc;">
								<li><strong>Dashboard</strong>: panoramica KPI, scadenze prioritarie, export CSV e debug mese</li>
								<li><strong>Contratti</strong>: gestione ciclo di vita, azioni rapide, filtri avanzati e storico</li>
								<li><strong>Fatturazione</strong>: ledger periodi con stati, azioni bulk e finestre temporali</li>
								<li><strong>Clienti</strong>: anagrafica completa, indicatori attività, sincronizzazione FIC</li>
								<li><strong>Servizi</strong>: template configurabili, statistiche utilizzo, automazioni email</li>
								<li><strong>Impostazioni</strong>: policy contratti, controllo frontend, manutenzione statistiche</li>
								<li><strong>About</strong>: panoramica sistema, stato componenti, supporto tecnico</li>
							</ul>
						</div>

						<!-- INTEGRAZIONE FIC -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #6c2eb9;">
							<h2 style="margin-top:0; color:#6c2eb9;">🔗 Integrazione Fatture in Cloud</h2>
							<p style="color:#555;">
								Connessione sicura e affidabile con Fatture in Cloud tramite protocollo OAuth2, con sincronizzazione
								intelligente e gestione avanzata degli errori per massima stabilità.
							</p>
							<ul style="margin:10px 0 0 18px; list-style:disc;">
								<li><strong>Autenticazione OAuth2</strong>: flusso sicuro con refresh automatico dei token</li>
								<li><strong>Sincronizzazione completa</strong>: import anagrafiche con deduplicazione intelligente</li>
								<li><strong>Normalizzazione dati</strong>: standardizzazione email, partite IVA, indirizzi, telefoni</li>
								<li><strong>Rate limiting avanzato</strong>: backoff esponenziale e retry automatico per stabilità</li>
								<li><strong>Gestione paginazione</strong>: import completo anche con migliaia di clienti</li>
								<li><strong>Schedulazione automatica</strong>: sincronizzazione notturna con log dettagliato</li>
							</ul>
							<p style="margin:10px 0 0;">
								<a class="button" href="<?php echo esc_url(admin_url('admin.php?smp_test_sync=1')); ?>">🔄 Avvia sincronizzazione clienti</a>
								<a class="button" style="margin-left:8px;" href="<?php echo esc_url(admin_url('admin.php?page=spm-settings')); ?>">⚙️ Apri Impostazioni</a>
							</p>
						</div>

					</div>

					<!-- COLONNA DESTRA (INFO / CHECK) -->
					<div style="flex:0 0 340px; min-width:320px;">
						<!-- VERSIONE / META -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #dba617;">
							<h2 style="margin-top:0; color:#dba617;">🏷️ Informazioni Sistema</h2>
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
										<td><strong>Timezone</strong></td>
										<td><?php echo esc_html(wp_timezone_string()); ?></td>
									</tr>
									<tr>
										<td><strong>Environment</strong></td>
										<td><?php echo defined('WP_DEBUG') && WP_DEBUG ? '<span style="color:#d98300">Debug</span>' : '<span style="color:#00a32a">Produzione</span>'; ?></td>
									</tr>
								</tbody>
							</table>
						</div>

						<!-- METRICHE RAPIDE -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #2271b1;">
							<h2 style="margin-top:0; color:#2271b1;">📈 Snapshot Sistema</h2>
							<?php 
							$quick_stats = self::get_quick_stats();
							?>
							<table class="widefat striped" style="margin-top:6px;">
								<tbody>
									<tr>
										<td><strong>Clienti totali</strong></td>
										<td><?php echo (int)$quick_stats['clienti']; ?></td>
									</tr>
									<tr>
										<td><strong>Servizi catalogati</strong></td>
										<td><?php echo (int)$quick_stats['servizi']; ?></td>
									</tr>
									<tr>
										<td><strong>Contratti attivi</strong></td>
										<td><span style="color:#00a32a"><?php echo (int)$quick_stats['contratti_attivi']; ?></span></td>
									</tr>
									<tr>
										<td><strong>In scadenza (30gg)</strong></td>
										<td><span style="color:#d98300"><?php echo (int)$quick_stats['in_scadenza']; ?></span></td>
									</tr>
									<tr>
										<td><strong>Ultima sincronizzazione</strong></td>
										<td><?php echo esc_html($quick_stats['last_sync'] ?: 'Mai'); ?></td>
									</tr>
								</tbody>
							</table>
						</div>

						<!-- STATO SISTEMA -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #dc3232;">
							<h2 style="margin-top:0; color:#dc3232;">🧪 Componenti Sistema</h2>
							<table class="widefat fixed striped" style="margin-top:6px;">
								<tbody>
									<?php foreach ($checks['rows'] as $row): ?>
										<tr>
											<td style="width:60%;"><strong><?php echo esc_html($row['label']); ?></strong><br><span style="color:#666; font-size:11px;"><?php echo esc_html($row['hint']); ?></span></td>
											<td>
												<span style="display:inline-block; padding:2px 8px; border-radius:12px; color:#fff; background:<?php echo esc_attr($row['ok'] ? '#00a32a' : '#dc3232'); ?>; font-size:11px;">
													<?php echo $row['ok'] ? 'OK' : 'Assente'; ?>
												</span>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
							<?php if (!empty($checks['notes'])): ?>
								<p style="color:#666; margin-top:8px; font-size:12px;"><?php echo esc_html($checks['notes']); ?></p>
							<?php endif; ?>
						</div>

						<!-- LINK RAPIDI -->
						<div style="background:white; padding:20px; margin:0 0 20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #00a32a;">
							<h2 style="margin-top:0; color:#00a32a;">⚡ Navigazione Rapida</h2>
							<p><a class="button button-primary" style="width:100%;" href="<?php echo esc_url(admin_url('admin.php?page=s121-pulse-manager')); ?>">📊 Dashboard Principale</a></p>
							<p><a class="button" style="width:100%;" href="<?php echo esc_url(admin_url('edit.php?post_type=contratti')); ?>">📄 Gestione Contratti</a></p>
							<p><a class="button" style="width:100%;" href="<?php echo esc_url(admin_url('admin.php?page=spm-billing-due')); ?>">🧾 Sistema Fatturazione</a></p>
							<p><a class="button" style="width:100%;" href="<?php echo esc_url(admin_url('edit.php?post_type=clienti')); ?>">👤 Anagrafica Clienti</a></p>
							<p><a class="button" style="width:100%;" href="<?php echo esc_url(admin_url('edit.php?post_type=servizi')); ?>">🧩 Catalogo Servizi</a></p>
							<p><a class="button" style="width:100%;" href="<?php echo esc_url(admin_url('admin.php?page=spm-settings')); ?>">⚙️ Configurazione Sistema</a></p>
						</div>

						<!-- SUPPORTO -->
						<div style="background:white; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-left:4px solid #2271b1;">
							<h2 style="margin-top:0; color:#2271b1;">🆘 Supporto Tecnico</h2>
							<p style="color:#555; margin-top:6px; line-height:1.5;">
								Per assistenza tecnica, richieste di funzionalità o personalizzazioni del sistema:
							</p>
							<p style="margin:10px 0;">
								<strong>Email:</strong> <a href="mailto:info@studio121.it">info@studio121.it</a><br>
								<strong>Sito web:</strong> <a href="https://studio121.it" target="_blank" rel="noopener noreferrer">studio121.it</a>
							</p>
							<p style="color:#666; font-size:12px; margin-top:12px;">
								Tempo di risposta medio: 4-8 ore lavorative<br>
								Supporto prioritario per clienti con contratto di manutenzione attivo
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

		/** Statistiche rapide del sistema */
		private static function get_quick_stats(): array {
			$clienti = wp_count_posts('clienti');
			$servizi = wp_count_posts('servizi');
			
			// Contratti attivi
			$contratti_attivi = new WP_Query([
				'post_type' => 'contratti',
				'posts_per_page' => -1,
				'meta_query' => [['key' => 'stato', 'value' => 'attivo']],
				'fields' => 'ids'
			]);

			// In scadenza prossimi 30 giorni
			$date_limit = date('Y-m-d', strtotime('+30 days'));
			$in_scadenza = new WP_Query([
				'post_type' => 'contratti',
				'posts_per_page' => -1,
				'meta_query' => [
					'relation' => 'AND',
					['key' => 'stato', 'value' => 'attivo'],
					['key' => 'data_prossima_scadenza', 'value' => date('Y-m-d'), 'compare' => '>=', 'type' => 'DATE'],
					['key' => 'data_prossima_scadenza', 'value' => $date_limit, 'compare' => '<=', 'type' => 'DATE'],
				],
				'fields' => 'ids'
			]);

			$last_sync = get_option('spm_last_sync_timestamp');
			if ($last_sync) {
				$last_sync = date_i18n('d/m/Y H:i', strtotime($last_sync));
			}

			return [
				'clienti' => (int)($clienti->publish ?? 0),
				'servizi' => (int)($servizi->publish ?? 0),
				'contratti_attivi' => (int)$contratti_attivi->found_posts,
				'in_scadenza' => (int)$in_scadenza->found_posts,
				'last_sync' => $last_sync
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
			$has_composer = file_exists(dirname(__DIR__) . '/vendor/autoload.php');

			$rows = [
				[
					'label' => 'Ledger fatturazione',
					'hint'  => 'Tabella periodi di fatturazione',
					'ok'    => $tables['spm_billing_ledger']['ok'],
				],
				[
					'label' => 'KPI mensili',
					'hint'  => 'Tabella statistiche materializzate',
					'ok'    => $tables['spm_kpi_monthly']['ok'],
				],
				[
					'label' => 'Storico contratti',
					'hint'  => 'Tabella history dettagliata',
					'ok'    => $tables['spm_contract_history']['ok'],
				],
				[
					'label' => 'ACF Pro',
					'hint'  => 'Advanced Custom Fields richiesto',
					'ok'    => $has_acf,
				],
				[
					'label' => 'Automazioni CRON',
					'hint'  => 'Controlli giornalieri programmati',
					'ok'    => $has_cron,
				],
				[
					'label' => 'Token Fatture in Cloud',
					'hint'  => 'Autenticazione OAuth2 attiva',
					'ok'    => $has_fic,
				],
				[
					'label' => 'Dipendenze Composer',
					'hint'  => 'SDK Fatture in Cloud installato',
					'ok'    => $has_composer,
				],
			];

			$notes = '';
			if (!$has_cron) {
				$notes = 'Suggerimento: attiva i controlli automatici per materializzazione e promemoria.';
			} elseif (!$has_fic) {
				$notes = 'Configura l\'integrazione con Fatture in Cloud per sincronizzazione clienti.';
			} elseif (!$has_acf) {
				$notes = 'Advanced Custom Fields Pro è richiesto per il corretto funzionamento.';
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