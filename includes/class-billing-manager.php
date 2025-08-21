<?php
/**
 * SPM_Billing_Manager (MVP emissione) — WP-timezone safe
 */

defined('ABSPATH') || exit;

class SPM_Billing_Manager {

  const DB_VERSION = '1.0.0'; // bumpa se cambi lo schema

  /* ===================== BOOTSTRAP ===================== */

  public static function init() {
	// AJAX
	add_action('wp_ajax_spm_billing_mark',   [__CLASS__, 'ajax_mark']);
	add_action('wp_ajax_spm_billing_delete', [__CLASS__, 'ajax_delete']);

	// Materializzazione quotidiana (riuso del tuo cron)
	add_action('spm_daily_check', [__CLASS__, 'daily_materialize']);

	// Safety net: installazione schema
	add_action('plugins_loaded', [__CLASS__, 'maybe_install']);

	// Hook su trash/delete/untrash contratti
	add_action('before_delete_post', [__CLASS__, 'on_contract_delete_or_trash']);
	add_action('wp_trash_post',      [__CLASS__, 'on_contract_delete_or_trash']);
	add_action('untrash_post',       [__CLASS__, 'on_contract_untrash']);
  }

  /** Controlla versione e (ri)crea/aggiorna tabella se serve */
  public static function maybe_install() {
	$installed = get_option('spm_billing_db_version');
	if ($installed === self::DB_VERSION) return;
	self::install_schema();
	update_option('spm_billing_db_version', self::DB_VERSION);
  }

  /** Crea/aggiorna lo schema della tabella ledger */
  private static function install_schema() {
	global $wpdb;
	$table   = $wpdb->prefix . 'spm_billing_ledger';
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table (
	  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	  contract_id BIGINT UNSIGNED NOT NULL,
	  cliente_id BIGINT UNSIGNED NULL,
	  servizio_id BIGINT UNSIGNED NULL,
	  cadence VARCHAR(20) NOT NULL,
	  period_start DATE NOT NULL,
	  period_end DATE NOT NULL,
	  due_date DATE NOT NULL,
	  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
	  status ENUM('due','issued','skipped') NOT NULL DEFAULT 'due',
	  notes TEXT NULL,
	  created_at DATETIME NOT NULL,
	  updated_at DATETIME NOT NULL,
	  UNIQUE KEY uniq_contract_period (contract_id, period_start, period_end),
	  KEY idx_due (due_date, status),
	  KEY idx_contract (contract_id)
	) $charset;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta($sql);
  }

  /* ===================== SETTINGS MVP ===================== */

  private static function grace_days(): int {
	if (class_exists('SPM_Settings_Page')) {
	  $v = (int) SPM_Settings_Page::get('billing_grace_days');
	  if ($v > 0) return $v;
	}
	return 5;
  }

  private static function notice_days_before_eom(): int {
	if (class_exists('SPM_Settings_Page')) {
	  $v = (int) SPM_Settings_Page::get('billing_notice_days_before_eom');
	  if ($v > 0) return $v;
	}
	return 10;
  }

  private static function horizon_months(): int {
	if (class_exists('SPM_Settings_Page')) {
	  $v = (int) SPM_Settings_Page::get('billing_horizon_months');
	  if ($v > 0) return $v;
	}
	return 3;
  }

  /* ===================== HELPERS TEMPO (WP-safe) ===================== */

  /** Timezone di WordPress */
  private static function tz(): DateTimeZone {
	return wp_timezone();
  }

  /** Ora corrente secondo WP come DateTimeImmutable */
  private static function now(): DateTimeImmutable {
	$ts = (int) current_time('timestamp');       // WP time
	$dt = new DateTimeImmutable('@' . $ts);      // UTC
	return $dt->setTimezone(self::tz());         // nel TZ WP
  }

  /** Oggi alle 00:00 nel timezone WP */
  private static function today_start(): DateTimeImmutable {
	$now = self::now();
	return $now->setTime(0,0,0);
  }

  /** Inizio mese della data passata (00:00) */
  private static function month_start(DateTimeInterface $ref): DateTimeImmutable {
	return (new DateTimeImmutable($ref->format('Y-m-01'), self::tz()))->setTime(0,0,0);
  }

  /** Fine mese (ultimo giorno 00:00) — end “inclusivo” lo gestiamo a livello di logica */
  private static function month_end(DateTimeInterface $ref): DateTimeImmutable {
	return self::month_start($ref)->modify('last day of this month')->setTime(0,0,0);
  }

  /** Crea una DateTimeImmutable da 'Y-m-d' rispettando il TZ WP */
  private static function from_db_date(string $ymd): ?DateTimeImmutable {
	$dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd, self::tz());
	return $dt ?: null;
  }

  /* ===================== API PUBBLICHE ===================== */

  public static function touch_contract(int $contract_id): void {
	self::materialize_for($contract_id);
  }

  /** Richiamata dal cron giornaliero */
  public static function daily_materialize(): void {
	$q = new WP_Query([
	  'post_type' => 'contratti',
	  'posts_per_page' => -1,
	  'fields' => 'ids',
	  'meta_query' => [[
		'key' => 'stato',
		'value' => ['attivo','sospeso','scaduto'],
		'compare' => 'IN',
	  ]],
	]);
	if ($q->have_posts()) {
	  foreach ($q->posts as $cid) self::materialize_for((int)$cid);
	}
  }

  /* ===================== MATERIALIZZAZIONE ===================== */

  /** Genera/aggiorna le righe ledger per un contratto (idempotente) */
  private static function materialize_for(int $contract_id): void {
	// Escludi cessati
	$stato = get_field('stato', $contract_id);
	if ($stato === 'cessato') return;

	// Cadenza fatturazione obbligatoria
	$cadence = get_field('cadenza_fatturazione', $contract_id);
	if (!$cadence) return;

	// Data di attivazione obbligatoria
	$attivazione = get_field('data_attivazione', $contract_id);
	$start_db = SPM_Date_Helper::to_db_format($attivazione);
	if (!$start_db) return;

	$start = self::from_db_date($start_db);
	if (!$start) return;

	// Cursor all'inizio mese di attivazione
	$cursor = self::month_start($start);

	// Limite = fine mese corrente + orizzonte
	$end_limit = self::month_end(self::now())->modify('+' . self::horizon_months() . ' months');

	while ($cursor <= $end_limit) {
	  [$pStart, $pEnd] = self::period_bounds($cursor, $cadence);
	  $due    = self::due_date($pEnd);
	  $amount = self::resolve_amount($contract_id);

	  self::upsert([
		'contract_id' => $contract_id,
		'cliente_id'  => (int) get_field('cliente', $contract_id),
		'servizio_id' => (int) get_field('servizio', $contract_id),
		'cadence'     => $cadence,
		'period_start'=> $pStart->format('Y-m-d'),
		'period_end'  => $pEnd->format('Y-m-d'),
		'due_date'    => $due->format('Y-m-d'),
		'amount'      => (float) $amount,
	  ]);

	  // Avanza al periodo successivo (giorno dopo la fine)
	  $cursor = $pEnd->modify('+1 day');
	}
  }

  /** Importo robusto: prezzo_contratto → prezzo_base servizio → 0 */
  private static function resolve_amount(int $contract_id): float {
	$val = get_field('prezzo_contratto', $contract_id);
	if ($val !== '' && $val !== null) return (float)$val;
	$serv = get_field('servizio', $contract_id);
	$base = $serv ? get_field('prezzo_base', $serv) : 0;
	return (float)($base ?: 0);
  }

  /** Bordi del periodo su mesi naturali, in base alla cadenza (immutabile) */
  private static function period_bounds(DateTimeInterface $anchor, string $cadence): array {
	$start = self::month_start($anchor);
	switch ($cadence) {
	  case 'bimestrale':     $len = '+2 months'; break;
	  case 'trimestrale':    $len = '+3 months'; break;
	  case 'quadrimestrale': $len = '+4 months'; break;
	  case 'semestrale':     $len = '+6 months'; break;
	  case 'annuale':        $len = '+12 months'; break;
	  case 'mensile':
	  default:               $len = '+1 month'; break;
	}
	$end = $start->modify($len)->modify('-1 day');
	return [$start, $end];
  }

  /** Fatturazione posticipata: fine periodo + grace_days */
  private static function due_date(DateTimeInterface $period_end): DateTimeImmutable {
	return (new DateTimeImmutable($period_end->format('Y-m-d'), self::tz()))->modify('+' . self::grace_days() . ' days');
  }

  /** UPSERT idempotente (mantiene status/notes esistenti) */
  private static function upsert(array $row): void {
	global $wpdb;
	$t = $wpdb->prefix . 'spm_billing_ledger';
	$nowMysql = current_time('mysql'); // WP time

	// ON DUPLICATE: aggiorno metadati ma NON tocco status/notes
	$wpdb->query($wpdb->prepare(
	  "INSERT INTO $t 
		(contract_id, cliente_id, servizio_id, cadence, period_start, period_end, due_date, amount, status, notes, created_at, updated_at)
	   VALUES (%d,%d,%d,%s,%s,%s,%s,%f,'due','',%s,%s)
	   ON DUPLICATE KEY UPDATE
		 cliente_id = VALUES(cliente_id),
		 servizio_id= VALUES(servizio_id),
		 cadence    = VALUES(cadence),
		 due_date   = VALUES(due_date),
		 amount     = VALUES(amount),
		 updated_at = VALUES(updated_at)",
	  $row['contract_id'], $row['cliente_id'], $row['servizio_id'], $row['cadence'],
	  $row['period_start'], $row['period_end'], $row['due_date'], $row['amount'], $nowMysql, $nowMysql
	));
  }

  /* ===================== BACKFILL API ===================== */

  public static function backfill_all(?string $fromYm = null, ?string $toYm = null, bool $reset = false): array {
	$q = new WP_Query([
	  'post_type' => 'contratti',
	  'posts_per_page' => -1,
	  'fields' => 'ids',
	  'meta_query' => [[
		'key' => 'stato',
		'value' => ['attivo','sospeso','scaduto'],
		'compare' => 'IN',
	  ]],
	]);
	$deleted = 0;
	if ($q->have_posts()) {
	  foreach ($q->posts as $cid) {
		$cid = (int)$cid;
		if ($reset) $deleted += self::delete_ledger_range_for_contract($cid, $fromYm, $toYm);
		self::materialize_for_range($cid, $fromYm, $toYm);
	  }
	}
	return ['processed' => (int)$q->found_posts, 'deleted' => $deleted];
  }

  public static function backfill_contract(int $contract_id, ?string $fromYm = null, ?string $toYm = null, bool $reset = false): int {
	$deleted = 0;
	if ($reset) $deleted = self::delete_ledger_range_for_contract($contract_id, $fromYm, $toYm);
	self::materialize_for_range($contract_id, $fromYm, $toYm);
	return $deleted;
  }

  /** Materializza righe limitando a un range YYYY-MM (inclusivo). */
  private static function materialize_for_range(int $contract_id, ?string $fromYm, ?string $toYm): void {
	$stato = get_field('stato', $contract_id);
	if ($stato === 'cessato') return;

	$cadence = get_field('cadenza_fatturazione', $contract_id);
	if (!$cadence) return;

	$attivazione = get_field('data_attivazione', $contract_id);
	$start_db = SPM_Date_Helper::to_db_format($attivazione);
	if (!$start_db) return;

	$start = self::from_db_date($start_db);
	if (!$start) return;

	// Limiti default (basati su WP-now)
	$defaultStart = self::month_start($start);
	$defaultEnd   = self::month_end(self::now())->modify('+' . self::horizon_months() . ' months');

	// Limiti range parametrici
	$rangeStart = $fromYm ? self::first_day_of_ym($fromYm) : $defaultStart;
	$rangeEnd   = $toYm   ? self::last_day_of_ym($toYm)   : $defaultEnd;

	// Non andare prima dell'attivazione
	if ($defaultStart > $rangeStart) $rangeStart = $defaultStart;

	// Cursor
	$cursor = self::month_start($rangeStart);

	while ($cursor <= $rangeEnd) {
	  [$pStart, $pEnd] = self::period_bounds($cursor, $cadence);
	  if ($pStart > $rangeEnd) break;
	  if ($pEnd   < $rangeStart) { $cursor = $pEnd->modify('+1 day'); continue; }

	  $due    = self::due_date($pEnd);
	  $amount = self::resolve_amount($contract_id);

	  self::upsert([
		'contract_id' => $contract_id,
		'cliente_id'  => (int) get_field('cliente', $contract_id),
		'servizio_id' => (int) get_field('servizio', $contract_id),
		'cadence'     => $cadence,
		'period_start'=> $pStart->format('Y-m-d'),
		'period_end'  => $pEnd->format('Y-m-d'),
		'due_date'    => $due->format('Y-m-d'),
		'amount'      => (float) $amount,
	  ]);

	  $cursor = $pEnd->modify('+1 day');
	}
  }

  /** Cancella righe ledger di un contratto in un range YYYY-MM (inclusivo). */
  private static function delete_ledger_range_for_contract(int $contract_id, ?string $fromYm, ?string $toYm): int {
	global $wpdb;
	$t = $wpdb->prefix.'spm_billing_ledger';
	if (!$fromYm && !$toYm) {
	  return (int)$wpdb->query($wpdb->prepare("DELETE FROM $t WHERE contract_id=%d", $contract_id));
	}
	$from = $fromYm ? self::first_day_of_ym($fromYm)->format('Y-m-d') : '0001-01-01';
	$to   = $toYm   ? self::last_day_of_ym($toYm)->format('Y-m-d')   : '9999-12-31';
	return (int)$wpdb->query($wpdb->prepare(
	  "DELETE FROM $t WHERE contract_id=%d AND period_start >= %s AND period_end <= %s",
	  $contract_id, $from, $to
	));
  }

  /** First-day helper per 'YYYY-MM' nel TZ WP */
  private static function first_day_of_ym(string $ym): DateTimeImmutable {
	if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
	  return self::month_start(self::now());
	}
	$dt = DateTimeImmutable::createFromFormat('Y-m-d', $ym.'-01', self::tz());
	return ($dt ?: self::today_start())->setTime(0,0,0);
  }

  /** Last-day helper per 'YYYY-MM' nel TZ WP */
  private static function last_day_of_ym(string $ym): DateTimeImmutable {
	return self::first_day_of_ym($ym)->modify('last day of this month')->setTime(0,0,0);
  }

  /* ===================== ADMIN: RENDERER ===================== */

  public static function render_admin_page() {
	if (!current_user_can('manage_options')) return;
	require plugin_dir_path(__FILE__) . 'class-billing-page.php';
  }

  /* ===================== AJAX ACTIONS ===================== */

  /** Segna riga come emessa o saltata (con motivo) + log nel contratto */
  public static function ajax_mark() {
	check_ajax_referer('spm_billing_mark');
	if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Non autorizzato'], 403);

	$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
	$to     = sanitize_text_field($_POST['to'] ?? '');
	$reason = sanitize_textarea_field($_POST['reason'] ?? '');

	if ($id<=0 || !in_array($to, ['issued','skipped'], true)) {
	  wp_send_json_error(['message'=>'Parametri non validi'], 400);
	}
	if ($to==='skipped' && $reason==='') {
	  wp_send_json_error(['message'=>'Motivo obbligatorio'], 400);
	}

	global $wpdb;
	$t   = $wpdb->prefix.'spm_billing_ledger';
	$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id), ARRAY_A);
	if (!$row) wp_send_json_error(['message'=>'Record non trovato'], 404);
	if ($row['status'] !== 'due') wp_send_json_error(['message'=>'Stato non modificabile'], 400);

	$ok = $wpdb->update($t, [
	  'status'     => $to,
	  'notes'      => $to==='skipped' ? $reason : $row['notes'],
	  'updated_at' => current_time('mysql'), // WP time
	], ['id'=>$id], ['%s','%s','%s'], ['%d']);

	if ($ok===false) wp_send_json_error(['message'=>'Update fallito'], 500);

	// Log sintetico nel contratto (replica dello storico locale)
	self::append_contract_log((int)$row['contract_id'], $to, $row['period_start'], $row['period_end'], $reason);

	wp_send_json_success(['message'=>'OK']);
  }

  /* ============== LOG CONTRATTO ============== */

  private static function append_contract_log(int $contract_id, string $action, string $pStart, string $pEnd, string $reason=''): void {
	$storico = get_field('storico_contratto', $contract_id) ?: [];

	$current_user = wp_get_current_user();
	$utente = ($current_user && $current_user->display_name) ? $current_user->display_name : 'Sistema';

	if ($action === 'issued') {
	  $note = 'Fattura EMESSA per periodo ' . SPM_Date_Helper::to_display_format($pStart) . ' → ' . SPM_Date_Helper::to_display_format($pEnd);
	} else { // skipped
	  $note = 'Fattura SALTATA per periodo ' . SPM_Date_Helper::to_display_format($pStart) . ' → ' . SPM_Date_Helper::to_display_format($pEnd) . ' (motivo: ' . $reason . ')';
	}

	// Importo robusto per log
	$importo = get_field('prezzo_contratto', $contract_id);
	if ($importo === '' || $importo === null) {
	  $servizio_id = get_field('servizio', $contract_id);
	  $val = $servizio_id ? get_field('prezzo_base', $servizio_id) : null;
	  $importo = ($val !== '' && $val !== null) ? $val : 0;
	}

	$entry = [
	  'data_operazione' => wp_date('Y-m-d'),
	  'ora_operazione'  => wp_date('H:i'),
	  'tipo_operazione' => 'modifica',
	  'importo'         => (float)$importo,
	  'utente'          => $utente,
	  'note'            => $note,
	];

	array_unshift($storico, $entry);
	$storico = array_slice($storico, 0, 50);
	update_field('storico_contratto', $storico, $contract_id);
  }

  /** Log cancellazione riga billing */
  private static function append_contract_log_delete(int $contract_id, string $status, string $pStart, string $pEnd, string $reason=''): void {
	$storico = get_field('storico_contratto', $contract_id) ?: [];

	$current_user = wp_get_current_user();
	$utente = ($current_user && $current_user->display_name) ? $current_user->display_name : 'Sistema';

	$label = self::status_label($status);
	$note  = 'Riga fatturazione ELIMINATA (stato: ' . $label . ') per periodo '
		   . SPM_Date_Helper::to_display_format($pStart) . ' → '
		   . SPM_Date_Helper::to_display_format($pEnd);
	if ($reason !== '') $note .= ' (motivo: ' . $reason . ')';

	// Importo robusto
	$importo = get_field('prezzo_contratto', $contract_id);
	if ($importo === '' || $importo === null) {
	  $servizio_id = get_field('servizio', $contract_id);
	  $val = $servizio_id ? get_field('prezzo_base', $servizio_id) : null;
	  $importo = ($val !== '' && $val !== null) ? $val : 0;
	}

	$entry = [
	  'data_operazione' => wp_date('Y-m-d'),
	  'ora_operazione'  => wp_date('H:i'),
	  'tipo_operazione' => 'modifica',
	  'importo'         => (float)$importo,
	  'utente'          => $utente,
	  'note'            => $note,
	];

	array_unshift($storico, $entry);
	$storico = array_slice($storico, 0, 50);
	update_field('storico_contratto', $storico, $contract_id);
  }

  /** Etichetta italiana per lo stato del ledger */
  public static function status_label(string $status): string {
	$map = [
	  'due'     => 'Da emettere',
	  'issued'  => 'Emessa',
	  'skipped' => 'Saltata',
	];
	return $map[$status] ?? ucfirst($status);
  }

  /* ===================== HOOKS DI PULIZIA ===================== */

  /** Elimina righe ledger di un contratto. Se $only_due=true elimina solo 'due', altrimenti TUTTI gli stati. */
  public static function delete_by_contract(int $contract_id, bool $only_due = true): int {
	global $wpdb;
	$t = $wpdb->prefix . 'spm_billing_ledger';
	if ($only_due) {
	  return (int) $wpdb->query($wpdb->prepare("DELETE FROM $t WHERE contract_id=%d AND status='due'", $contract_id));
	}
	return (int) $wpdb->query($wpdb->prepare("DELETE FROM $t WHERE contract_id=%d", $contract_id));
  }

  /** Quando un contratto va nel cestino o viene eliminato: puliamo le 'due' (da emettere). */
  public static function on_contract_delete_or_trash($post_id) {
	if (get_post_type($post_id) !== 'contratti') return;
	self::delete_by_contract((int)$post_id, true); // solo 'due'
  }

  /** Quando un contratto viene ripristinato: rimaterializziamo le righe future. */
  public static function on_contract_untrash($post_id) {
	if (get_post_type($post_id) !== 'contratti') return;
	self::touch_contract((int)$post_id);
  }

}
