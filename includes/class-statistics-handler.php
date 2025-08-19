<?php
/**
 * Statistics Handler - materializza MRR/ARR/ARPU in tabelle dedicate.
 * - storicizza 1 riga per mese x contratto
 * - calcola rollup KPI mensili
 * - espone API di sola lettura per la dashboard
 */
if ( ! defined('ABSPATH') ) exit;

final class SPM_Statistics_Handler {
	private static $instance = null;
	private $history_table;
	private $kpi_table;

	public static function instance() {
		return self::$instance ?: (self::$instance = new self());
	}

	private function __construct() {
		global $wpdb;
		$this->history_table = $wpdb->prefix . 'spm_contract_history';
		$this->kpi_table     = $wpdb->prefix . 'spm_kpi_monthly';

		add_action('admin_init', [$this, 'maybe_install']);

		// Quando salvi un contratto tramite ACF, materializza (dopo il tuo handler: tu sei priority 20, noi 30)
		add_action('acf/save_post', [$this, 'hook_on_contract_save'], 30);

		// Al tuo cron giornaliero, dopo i tuoi check/rinnovi (tu registri spm_daily_check): noi in coda
		add_action('spm_daily_check', [$this, 'hook_on_daily_check'], 50);
		
		// AGGIUNGI QUESTO:
		add_action('wp_trash_post',  [$this, 'on_trash_contract']);   // quando va nel cestino
		add_action('untrash_post',   [$this, 'on_untrash_contract']); // quando viene ripristinato
		add_action('before_delete_post', [$this, 'on_delete_contract']); // già presente: delete definitivo

	}

	/* =======================
	 * INSTALL / MIGRA
	 * ======================= */
	public function maybe_install() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql1 = "CREATE TABLE {$this->history_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			month DATE NOT NULL,
			contract_id BIGINT UNSIGNED NOT NULL,
			customer_id BIGINT UNSIGNED NULL,
			service_id BIGINT UNSIGNED NULL,
			status VARCHAR(16) NOT NULL DEFAULT 'attivo',
			billing_freq VARCHAR(20) NOT NULL,
			amount_original DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			mrr_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			currency CHAR(3) NOT NULL DEFAULT 'EUR',
			source_event VARCHAR(32) NOT NULL DEFAULT 'materialize',
			effective_from DATE NULL,
			generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_month_contract (month, contract_id),
			KEY idx_month (month),
			KEY idx_customer (customer_id),
			KEY idx_service (service_id)
		) $charset_collate;";

		$sql2 = "CREATE TABLE {$this->kpi_table} (
			month DATE NOT NULL,
			mrr_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			arr_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			active_customers INT UNSIGNED NOT NULL DEFAULT 0,
			arpu DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (month)
		) $charset_collate;";

		dbDelta($sql1);
		dbDelta($sql2);
	}

	/* =======================
	 * HOOKS
	 * ======================= */

	/** Dopo il salvataggio ACF di un CONTRATTO materializza il mese corrente */
	// public function hook_on_contract_save($post_id) {
	// 	if (get_post_type($post_id) !== 'contratti') return;
	// 	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return; 
	// 	$yyyymm = current_time('Y-m');
	// 	$this->materialize_month((int)$post_id, $yyyymm);
	// }
	
	public function hook_on_contract_save($post_id) {
		if (get_post_type($post_id) !== 'contratti') return;
		if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
	
		global $wpdb;
		$cur = current_time('Y-m');
	
		// C'è già history per questo contratto?
		$has_history = (int)$wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->history_table} WHERE contract_id = %d",
			$post_id
		));
	
		// Mese di partenza dedotto dai meta (data_attivazione o post_date)
		$start = $this->detect_start_month((int)$post_id);
	
		if (!$has_history && $start && strcmp($start, $cur) < 0) {
			// Primo salvataggio di un contratto nato mesi fa → riempi tutto fino ad oggi
			$this->backfill_contract((int)$post_id, $start, $cur);
		} else {
			// Aggiorna solo il mese corrente (idempotente)
			$this->materialize_month((int)$post_id, $cur);
		}
	}

	/** Al cron giornaliero del tuo plugin (dopo i rinnovi/scadenze) garantiamo il mese per tutti gli attivi */
	public function hook_on_daily_check() {
		$yyyymm = current_time('Y-m');
		$q = new WP_Query([
			'post_type'      => 'contratti',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		]);
		if (!empty($q->posts)) {
			foreach ($q->posts as $cid) {
				$this->materialize_month((int)$cid, $yyyymm);
			}
		}
	}

	/* =======================
	 * API PUBBLICA (per dashboard)
	 * ======================= */
	public function get_monthly_series(string $from_yyyymm, string $to_yyyymm): array {
		global $wpdb;
		$from = $this->month_first_day($from_yyyymm);
		$to   = $this->month_first_day($to_yyyymm);
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT month, mrr_total, arr_total, active_customers, arpu
				 FROM {$this->kpi_table}
				 WHERE month BETWEEN %s AND %s
				 ORDER BY month ASC",
				$from, $to
			),
			ARRAY_A
		);
		$out = [];
		foreach ($rows as $r) {
			$key = substr($r['month'], 0, 7);
			$out[$key] = [
				'mrr' => (float)$r['mrr_total'],
				'arr' => (float)$r['arr_total'],
				'arpu'=> (float)$r['arpu'],
				'active_customers' => (int)$r['active_customers'],
			];
		}
		return $out;
	}

	public function get_breakdown(string $yyyymm, string $by='customer'): array {
		global $wpdb;
		$month = $this->month_first_day($yyyymm);
		$col = ($by === 'service') ? 'service_id' : 'customer_id';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$col} AS key_id, SUM(mrr_value) AS mrr
				 FROM {$this->history_table}
				 WHERE month = %s
				 GROUP BY {$col}
				 ORDER BY mrr DESC",
				$month
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	public function get_contract_trace(int $contract_id): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->history_table}
				 WHERE contract_id = %d
				 ORDER BY month ASC",
				$contract_id
			),
			ARRAY_A
		) ?: [];
	}

	/* =======================
	 * MATERIALIZZAZIONE
	 * ======================= */

	/** 1 mese x 1 contratto (idempotente su UNIQUE month+contract) */
	public function materialize_month(int $contract_id, string $yyyymm): bool {
		$cd = $this->load_contract_data($contract_id);
		if (!$cd) return false;

		$month_date = $this->month_first_day($yyyymm);

		// Policy MVP: niente prorata; sospesi/scaduti/cessati non contribuiscono
		$status = strtolower($cd['status']);
		$is_active_like = in_array($status, ['attivo','rinnovo'], true);

		$amount = (float)$cd['amount'];              // importo del PERIODO
		$cad    = (string)$cd['cad'];                // cadenza_fatturazione oppure frequenza
		$mrr    = $is_active_like ? $this->normalize_to_mrr($amount, $cad) : 0.0;

		$row = [
			'month'           => $month_date,
			'contract_id'     => $contract_id,
			'customer_id'     => $cd['customer_id'],
			'service_id'      => $cd['service_id'],
			'status'          => $status,
			'billing_freq'    => $cad,
			'amount_original' => $amount,
			'mrr_value'       => $mrr,
			'currency'        => 'EUR',
			'source_event'    => 'materialize',
			'effective_from'  => $month_date,
			'generated_at'    => current_time('mysql'),
			'version'         => 1,
		];

		$ok = $this->upsert_history_row($row);
		if ($ok) $this->update_kpi_for_month($yyyymm);
		return $ok;
	}

	/** Rollup KPI dal dettaglio del mese */
	public function update_kpi_for_month(string $yyyymm): bool {
		global $wpdb;
		$month_date = $this->month_first_day($yyyymm);

		$mrr_total = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(mrr_value),0) FROM {$this->history_table} WHERE month = %s",
			$month_date
		));

		$active_customers = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT customer_id)
			   FROM {$this->history_table}
			  WHERE month = %s AND mrr_value > 0",
			$month_date
		));

		$arr_total = round($mrr_total * 12, 2);
		$arpu = $active_customers > 0 ? round($mrr_total / $active_customers, 2) : 0.0;

		$sql = $wpdb->prepare(
			"INSERT INTO {$this->kpi_table}
			   (month, mrr_total, arr_total, active_customers, arpu, generated_at)
			 VALUES (%s, %f, %f, %d, %f, %s)
			 ON DUPLICATE KEY UPDATE
			   mrr_total=VALUES(mrr_total),
			   arr_total=VALUES(arr_total),
			   active_customers=VALUES(active_customers),
			   arpu=VALUES(arpu),
			   generated_at=VALUES(generated_at)",
			$month_date, $mrr_total, $arr_total, $active_customers, $arpu, current_time('mysql')
		);

		return (false !== $wpdb->query($sql));
	}

	/* =======================
	 * HELPERS
	 * ======================= */

	private function month_first_day(string $ym_or_date): string {
		$ym = substr($ym_or_date, 0, 7);
		return $ym . '-01';
	}

	/** Legge i meta del contratto secondo i tuoi nomi/ACF */
	private function load_contract_data(int $contract_id): ?array {
		$post = get_post($contract_id);
		if (!$post || $post->post_type !== 'contratti') return null;

		$customer_id = (int) get_field('cliente',  $contract_id);
		$service_id  = (int) get_field('servizio', $contract_id);
		$status      = (string) (get_field('stato', $contract_id) ?: 'attivo');

		// Importo: prezzo_contratto, fallback al servizio->prezzo_base
		$amount = get_field('prezzo_contratto', $contract_id);
		if ($amount === '' || $amount === null) {
			$val = $service_id ? get_field('prezzo_base', $service_id) : null;
			$amount = ($val !== '' && $val !== null) ? $val : 0;
		}
		$amount = (float) $amount;

		// Cadenza fatturazione primaria; fallback a "frequenza"
		$cad = get_field('cadenza_fatturazione', $contract_id);
		if ($cad === '' || $cad === null) {
			$cad = get_field('frequenza', $contract_id);
		}
		$cad = $cad ? (string)$cad : 'mensile';

		return [
			'customer_id' => $customer_id ?: null,
			'service_id'  => $service_id ?: null,
			'status'      => $status,
			'cad'         => $cad,
			'amount'      => $amount,
		];
	}

	/** INSERT ON DUPLICATE per history */
	private function upsert_history_row(array $data): bool {
		global $wpdb;
		$table = $this->history_table;

		$cols = [
			'month','contract_id','customer_id','service_id','status',
			'billing_freq','amount_original','mrr_value','currency',
			'source_event','effective_from','generated_at','version'
		];
		$ph   = ['%s','%d','%d','%d','%s','%s','%f','%f','%s','%s','%s','%s','%d'];

		$vals = [];
		foreach ($cols as $c) $vals[] = $data[$c] ?? null;

		$insert_sql = "INSERT INTO {$table} (" . implode(',', $cols) . ")
					   VALUES (" . implode(',', $ph) . ")
					   ON DUPLICATE KEY UPDATE
						 customer_id=VALUES(customer_id),
						 service_id=VALUES(service_id),
						 status=VALUES(status),
						 billing_freq=VALUES(billing_freq),
						 amount_original=VALUES(amount_original),
						 mrr_value=VALUES(mrr_value),
						 currency=VALUES(currency),
						 source_event=VALUES(source_event),
						 effective_from=VALUES(effective_from),
						 generated_at=VALUES(generated_at),
						 version=VALUES(version)";

		$prepared = $wpdb->prepare($insert_sql, $vals);
		return (false !== $wpdb->query($prepared));
	}

	/** Normalizza “importo per periodo” in MRR; include quadrimestrale */
	private function normalize_to_mrr(float $amount_for_period, string $cadenza): float {
		$months = $this->period_months($cadenza);
		return $months > 0 ? round($amount_for_period / $months, 2) : $amount_for_period;
	}

	private function period_months(string $cad): int {
		$cad = strtolower(trim($cad));
		switch ($cad) {
			case 'mensile':        return 1;
			case 'trimestrale':    return 3;
			case 'quadrimestrale': return 4;
			case 'semestrale':     return 6;
			case 'annuale':        return 12;
			default:               return 1;
		}
	}
	
	/* =======================
	 * BACKFILL & RANGE
	 * ======================= */
	
	/** Esegue backfill per 1 contratto dal primo mese utile al mese corrente (o intervallo dato) */
	public function backfill_contract(int $contract_id, ?string $from_yyyymm = null, ?string $to_yyyymm = null): int {
		// start: data_attivazione se presente, altrimenti mese del post_date
		$start = $from_yyyymm ?: $this->detect_start_month($contract_id);
		if (!$start) return 0;
	
		// stop: cessazione se presente, altrimenti mese corrente o limite esplicito
		$cess = $this->detect_cessation_month($contract_id); // YYYY-MM o null
		$end  = $to_yyyymm ?: ($cess ?: current_time('Y-m'));
	
		$months = $this->iterate_months($start, $end);
		$count = 0;
		foreach ($months as $ym) {
			if ($this->materialize_month($contract_id, $ym)) {
				$count++;
			}
		}
		// aggiorna KPI per i mesi toccati (più veloce per blocchi)
		$this->rebuild_kpis_range($start, $end);
		return $count;
	}
	
	/** Backfill per tutti i contratti pubblicati (batch semplice) */
	public function backfill_all(?string $from_yyyymm = null, ?string $to_yyyymm = null): array {
		$q = new WP_Query([
			'post_type'      => 'contratti',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		]);
		$ok = 0; $fail = 0;
		foreach ($q->posts as $cid) {
			$n = $this->backfill_contract((int)$cid, $from_yyyymm, $to_yyyymm);
			if ($n >= 0) $ok++; else $fail++;
		}
		return ['ok' => $ok, 'fail' => $fail];
	}
	
	/** Ricostruisce KPI per intervallo */
	public function rebuild_kpis_range(string $from_yyyymm, string $to_yyyymm): void {
		$months = $this->iterate_months($from_yyyymm, $to_yyyymm);
		foreach ($months as $ym) {
			$this->update_kpi_for_month($ym);
		}
	}
	
	/* =======================
	 * HELPERS BACKFILL
	 * ======================= */
	
	/** Restituisce YYYY-MM di partenza (data_attivazione o post_date) */
	private function detect_start_month(int $contract_id): ?string {
		$att = get_field('data_attivazione', $contract_id);
		if ($att) return substr($att, 0, 7);
		$post = get_post($contract_id);
		if ($post && $post->post_date) return substr($post->post_date, 0, 7);
		return null;
	}
	
	/** Se nel repeater "storico_contratto" trovi una 'cessazione', torna YYYY-MM di quell’evento */
	private function detect_cessation_month(int $contract_id): ?string {
		$storico = get_field('storico_contratto', $contract_id);
		if (!$storico || !is_array($storico)) return null;
		// il repeater è in ordine decrescente (unshift), quindi scorri tutto
		foreach ($storico as $entry) {
			$tipo = $entry['tipo_operazione'] ?? '';
			if ($tipo === 'cessazione') {
				$data = $entry['data_operazione'] ?? '';
				if ($data && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
					return substr($data, 0, 7);
				}
			}
		}
		return null;
	}
	
	/** Iteratore di mesi [from..to] inclusivo */
	private function iterate_months(string $from_yyyymm, string $to_yyyymm): array {
		$out = [];
		$y = (int) substr($from_yyyymm, 0, 4);
		$m = (int) substr($from_yyyymm, 5, 2);
		$y2 = (int) substr($to_yyyymm, 0, 4);
		$m2 = (int) substr($to_yyyymm, 5, 2);
	
		while ($y < $y2 || ($y === $y2 && $m <= $m2)) {
			$out[] = sprintf('%04d-%02d', $y, $m);
			$m++;
			if ($m > 12) { $m = 1; $y++; }
		}
		return $out;
	}
	
	public function delete_contract_history(int $contract_id): void {
		global $wpdb;
		$wpdb->delete($this->history_table, ['contract_id' => $contract_id], ['%d']);
	}
	
	public function purge_deleted_contracts_and_rebuild_kpis() {
		$this->purge_deleted_contracts();
		$this->rebuild_kpis_range('2020-01', current_time('Y-m'));
	}
	
	public function on_delete_contract($post_id){
		if (get_post_type($post_id) !== 'contratti') return;
		$this->delete_contract_history((int)$post_id);
		// Ricostruisci i KPI su una finestra ragionevole
		$this->rebuild_kpis_range('2020-01', current_time('Y-m'));
	}
	
	public function on_trash_contract($post_id){
		if (get_post_type($post_id) !== 'contratti') return;
		$this->delete_contract_history((int)$post_id);
		$this->rebuild_kpis_range('2020-01', current_time('Y-m'));
	}
	
	public function on_untrash_contract($post_id){
		if (get_post_type($post_id) !== 'contratti') return;
		// Ricostruisci lo storico dal mese di attivazione ad oggi
		$this->backfill_contract((int)$post_id, null, current_time('Y-m'));
	}
	
	private function purge_deleted_contracts(): int {
		global $wpdb;
	
		// Trova contract_id presenti nella history che non esistono più in wp_posts
		$ids = $wpdb->get_col("
			SELECT DISTINCT h.contract_id
			FROM {$this->history_table} h
			LEFT JOIN {$wpdb->posts} p
			  ON p.ID = h.contract_id AND p.post_type = 'contratti'
			WHERE p.ID IS NULL
		");
	
		if (empty($ids)) return 0;
	
		$in = implode(',', array_map('intval', $ids));
		$wpdb->query("DELETE FROM {$this->history_table} WHERE contract_id IN ($in)");
		return count($ids);
	}
	
	/** Trova il mese più antico tra i contratti (YYYY-MM) */
	public function find_earliest_month(): ?string {
		$q = new WP_Query([
			'post_type'      => 'contratti',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
		]);
		$min = null;
		foreach ($q->posts as $cid) {
			$m = $this->detect_start_month((int)$cid);
			if ($m && (!$min || strcmp($m, $min) < 0)) $min = $m;
		}
		return $min ?: '2020-01';
	}
	
	/** Solo KPI: ricostruisce i KPI su un range (non tocca history) */
	public function rebuild_kpis_only(?string $from_yyyymm = null, ?string $to_yyyymm = null): void {
		$from = $from_yyyymm ?: $this->find_earliest_month();
		$to   = $to_yyyymm   ?: current_time('Y-m');
		$this->rebuild_kpis_range($from, $to);
	}
	
	/** Pulisce righe orfane (contratti cancellati) e ricostruisce KPI */
	public function purge_orphans_and_rebuild(?string $from_yyyymm = null, ?string $to_yyyymm = null): array {
		$from = $from_yyyymm ?: '2020-01';
		$to   = $to_yyyymm   ?: current_time('Y-m');
		$purged = $this->purge_deleted_contracts();
		$this->rebuild_kpis_range($from, $to);
		return ['purged_contracts' => $purged, 'rebuilt_from' => $from, 'rebuilt_to' => $to];
	}
	
	/**
	 * HARD REINDEX: svuota completamente tabelle history+KPI e rimaterializza tutto
	 * Usa solo se vuoi un rebuild totale da zero.
	 */
	public function hard_reindex_all(?string $from_yyyymm = null, ?string $to_yyyymm = null): array {
		global $wpdb;
		$from = $from_yyyymm ?: $this->find_earliest_month();
		$to   = $to_yyyymm   ?: current_time('Y-m');
	
		// Svuota
		$wpdb->query("TRUNCATE TABLE {$this->history_table}");
		$wpdb->query("TRUNCATE TABLE {$this->kpi_table}");
	
		// Backfill di tutti i contratti nel range
		$q = new WP_Query([
			'post_type'      => 'contratti',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
		]);
		$ok=0; $fail=0;
		foreach ($q->posts as $cid) {
			$n = $this->backfill_contract((int)$cid, $from, $to);
			if ($n >= 0) $ok++; else $fail++;
		}
	
		// KPI finali
		$this->rebuild_kpis_range($from, $to);
	
		return [
			'contracts_ok'   => $ok,
			'contracts_fail' => $fail,
			'from'           => $from,
			'to'             => $to,
		];
	}

	
}

// Bootstrap
SPM_Statistics_Handler::instance();
	
	if (defined('WP_CLI') && WP_CLI) {
		WP_CLI::add_command('spm stats:rebuild-kpis', function($args, $assoc_args){
			$h = SPM_Statistics_Handler::instance();
			$h->rebuild_kpis_only($assoc_args['from'] ?? null, $assoc_args['to'] ?? null);
			WP_CLI::success('KPI rebuilt.');
		});
	
		WP_CLI::add_command('spm stats:purge-orphans', function($args, $assoc_args){
			$h = SPM_Statistics_Handler::instance();
			$r = $h->purge_orphans_and_rebuild($assoc_args['from'] ?? null, $assoc_args['to'] ?? null);
			WP_CLI::success('Purged: ' . $r['purged_contracts'] . ' | Range KPI: ' . $r['rebuilt_from'] . ' → ' . $r['rebuilt_to']);
		});
	
		WP_CLI::add_command('spm stats:hard-reindex', function($args, $assoc_args){
			$h = SPM_Statistics_Handler::instance();
			$r = $h->hard_reindex_all($assoc_args['from'] ?? null, $assoc_args['to'] ?? null);
			WP_CLI::success('Hard reindex done. OK=' . $r['contracts_ok'] . ' FAIL=' . $r['contracts_fail'] . ' | Range: ' . $r['from'] . ' → ' . $r['to']);
		});
	}

