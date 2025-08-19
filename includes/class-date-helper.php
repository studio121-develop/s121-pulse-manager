<?php
/**
 * S121 Pulse Manager - Date Helper (TZ-safe)
 *
 * Obiettivi di questa versione:
 * - Usare SEMPRE la timezone di WordPress (Settings > General) per ogni parsing/calcolo
 * - Mantenere il formato DB come 'Y-m-d' (senza orario) per campi data
 * - Rendere i confronti (oggi/scaduto/tra quanti giorni) stabili, ignorando l'orario
 *
 * Note:
 * - Per mostrare date/ore in output libero usa wp_date() nel resto del codice (già fatto nei log).
 * - Qui dentro lavoriamo con DateTime ancorati a wp_timezone() per coerenza lato server.
 */

defined('ABSPATH') || exit;

class SPM_Date_Helper {

	/** Formato interno standard (database) es. 2025-08-19 */
	const DB_FORMAT = 'Y-m-d';

	/** Formato display italiano es. 19/08/2025 */
	const DISPLAY_FORMAT = 'd/m/Y';

	/** Formato ACF date picker (campo "Date" in modalità Ymd) es. 20250819 */
	const ACF_FORMAT = 'Ymd';

	/**
	 * Restituisce la timezone configurata in WordPress.
	 * Usiamo una funzione dedicata per evitare ripetizioni e centralizzare eventuali cambi.
	 */
	private static function tz(): DateTimeZone {
		return wp_timezone();
	}

	/**
	 * Restituisce un oggetto DateTime settato a "oggi 00:00:00" nella TZ WP.
	 * Utile per confronti che ignorano l'orario.
	 */
	private static function today_midnight(): DateTime {
		$tz = self::tz();
		$now = new DateTime('now', $tz);
		$now->setTime(0, 0, 0);
		return $now;
	}

	/**
	 * Converte una data da vari formati al formato DB (Y-m-d).
	 *
	 * Accetta:
	 * - 'Y-m-d' (ritorna com'è)
	 * - 'd/m/Y' (display IT)
	 * - 'Ymd'   (ACF)
	 *
	 * @param string $date
	 * @return string '' se parsing fallisce, altrimenti 'Y-m-d'
	 */
	public static function to_db_format($date) {
		if (empty($date)) return '';

		// Se già nel formato corretto
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			return $date;
		}

		$tz = self::tz();

		// Da formato italiano d/m/Y
		if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
			$dt = DateTime::createFromFormat('d/m/Y', $date, $tz);
			return $dt ? $dt->format(self::DB_FORMAT) : '';
		}

		// Da formato ACF Ymd
		if (preg_match('/^\d{8}$/', $date)) {
			$dt = DateTime::createFromFormat(self::ACF_FORMAT, $date, $tz);
			return $dt ? $dt->format(self::DB_FORMAT) : '';
		}

		// Formato sconosciuto
		return '';
	}

	/**
	 * Converte una data in formato DB in formato display (d/m/Y).
	 * Accetta anche input in 'd/m/Y' o 'Ymd' e li normalizza prima.
	 *
	 * @param string $date
	 * @return string '' se parsing fallisce, altrimenti 'd/m/Y'
	 */
	public static function to_display_format($date) {
		if (empty($date)) return '';

		$db_date = self::to_db_format($date);
		if (!$db_date) return '';

		$dt = new DateTime($db_date, self::tz());
		return $dt->format(self::DISPLAY_FORMAT);
	}

	/**
	 * Calcola la prossima scadenza basata su frequenza, partendo da una data di riferimento.
	 * La logica è "start_date + [periodo]".
	 *
	 * Frequenze supportate:
	 * - mensile        → +1 month
	 * - trimestrale    → +3 months
	 * - quadrimestrale → +4 months
	 * - semestrale     → +6 months
	 * - annuale        → +1 year
	 *
	 * @param string $start_date accetta d/m/Y, Y-m-d o Ymd
	 * @param string $frequency  una delle stringhe sopra
	 * @return string '' se input non valido, altrimenti 'Y-m-d'
	 */
	public static function calculate_next_due_date($start_date, $frequency) {
		$db_date = self::to_db_format($start_date);
		if (!$db_date) return '';

		$dt = new DateTime($db_date, self::tz());

		switch ($frequency) {
			case 'mensile':
				$dt->modify('+1 month');
				break;
			case 'trimestrale':
				$dt->modify('+3 months');
				break;
			case 'quadrimestrale':
				$dt->modify('+4 months');
				break;
			case 'semestrale':
				$dt->modify('+6 months');
				break;
			case 'annuale':
				$dt->modify('+1 year');
				break;
			default:
				// frequenza ignota → non modifico
				break;
		}

		return $dt->format(self::DB_FORMAT);
	}

	/**
	 * Calcola i giorni rimanenti alla scadenza (ignorando l'orario).
	 *
	 * Ritorna:
	 *  - valore positivo  = mancano N giorni
	 *  - 0                = scade oggi
	 *  - valore negativo  = scaduto da N giorni
	 *
	 * @param string $due_date accetta d/m/Y, Y-m-d o Ymd
	 * @return int 999 se data non valida
	 */
	public static function days_until_due($due_date) {
		$db_date = self::to_db_format($due_date);
		if (!$db_date) return 999;

		$tz   = self::tz();
		$today= self::today_midnight();            // oggi 00:00 locale
		$due  = new DateTime($db_date, $tz);       // scadenza (data pura)

		// differenza (oggi -> scadenza) con segno invertito via invert/days
		$interval = $today->diff($due);

		// invert = 1 se $due < $today (quindi già scaduto)
		return $interval->invert ? -$interval->days : $interval->days;
	}

	/**
	 * Giorni trascorsi rispetto alla scadenza (con segno, ignorando l'orario).
	 *
	 * Ritorna:
	 *   > 0  = scadenza nel passato (da quanti giorni è scaduto)
	 *     0  = scade oggi
	 *   < 0  = scadenza nel futuro (mancano N giorni)
	 *  null  = data non valida
	 *
	 * @param string $due_date
	 * @return int|null
	 */
	public static function days_since_due($due_date) {
		$db_date = self::to_db_format($due_date);
		if (!$db_date) return null;

		$tz    = self::tz();
		$today = self::today_midnight();           // oggi 00:00 locale
		$due   = new DateTime($db_date, $tz);

		// %r%a produce numero con segno; positivo = passato, negativo = futuro
		return (int)$due->diff($today)->format('%r%a');
	}

	/**
	 * True se la data è già scaduta (ieri o prima).
	 * (Usa days_until_due < 0, quindi se "scade oggi" ritorna false.)
	 *
	 * @param string $date
	 * @return bool
	 */
	public static function is_expired($date) {
		return self::days_until_due($date) < 0;
	}

	/**
	 * True se la data è in scadenza entro X giorni (incluso oggi).
	 *
	 * @param string $date
	 * @param int    $days_threshold default 30
	 * @return bool
	 */
	public static function is_expiring_soon($date, $days_threshold = 30) {
		$days = self::days_until_due($date);
		return $days >= 0 && $days <= $days_threshold;
	}
	
	/* ===================== BACKFILL API ===================== */
	
	/**
	 * Backfill di TUTTI i contratti non cessati.
	 * @param string|null $fromYm  es. '2024-01' (opzionale)
	 * @param string|null $toYm    es. '2025-08' (opzionale)
	 * @param bool        $reset   se true, cancella le righe ledger nel range prima di rigenerare
	 * @return array {processed:int, deleted:int}
	 */
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
	
	/**
	 * Backfill di un singolo contratto, con range opzionale e reset.
	 * @param int         $contract_id
	 * @param string|null $fromYm es. '2024-01'
	 * @param string|null $toYm   es. '2025-08'
	 * @param bool        $reset
	 * @return int numero righe cancellate se reset
	 */
	public static function backfill_contract(int $contract_id, ?string $fromYm = null, ?string $toYm = null, bool $reset = false): int {
	  $deleted = 0;
	  if ($reset) $deleted = self::delete_ledger_range_for_contract($contract_id, $fromYm, $toYm);
	  self::materialize_for_range($contract_id, $fromYm, $toYm);
	  return $deleted;
	}
	
	/* ===================== HELPERS BACKFILL ===================== */
	
	/**
	 * Materializza righe per un contratto LIMITANDO al range (YYYY-MM) se passato.
	 * Se nessun range, usa la logica standard: da attivazione fino a (EOM + horizon).
	 */
	private static function materialize_for_range(int $contract_id, ?string $fromYm, ?string $toYm): void {
	  $stato = get_field('stato', $contract_id);
	  if ($stato === 'cessato') return;
	
	  $cadence = get_field('cadenza_fatturazione', $contract_id);
	  if (!$cadence) return;
	
	  $attivazione = get_field('data_attivazione', $contract_id);
	  $start_db = SPM_Date_Helper::to_db_format($attivazione);
	  if (!$start_db) return;
	
	  $tz = wp_timezone();
	
	  // Calcola limiti
	  $defaultStart = (new DateTime($start_db, $tz))->modify('first day of this month')->setTime(0,0,0);
	  $defaultEnd   = (new DateTime('today', $tz))->modify('last day of this month')->modify('+'.self::horizon_months().' months')->setTime(0,0,0);
	
	  $rangeStart = $fromYm ? self::first_day_of_ym($fromYm, $tz) : $defaultStart;
	  $rangeEnd   = $toYm   ? self::last_day_of_ym($toYm,   $tz) : $defaultEnd;
	
	  // Se l'attivazione è dopo l'inizio range, parte dall'attivazione
	  if ($defaultStart > $rangeStart) $rangeStart = $defaultStart;
	
	  // Cursor = inizio range (allineato a primo del mese)
	  $cursor = (clone $rangeStart)->modify('first day of this month')->setTime(0,0,0);
	
	  while ($cursor <= $rangeEnd) {
		[$pStart, $pEnd] = self::period_bounds($cursor, $cadence);
		if ($pStart > $rangeEnd) break;            // fuori range
		if ($pEnd   < $rangeStart) {               // prima del range → salta
		  $cursor = (clone $pEnd)->modify('+1 day');
		  continue;
		}
		$due = self::due_date($pEnd);
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
	
		$cursor = (clone $pEnd)->modify('+1 day');
	  }
	}
	
	/** Cancella righe ledger di un contratto in un range YYYY-MM (inclusivo). Ritorna righe cancellate. */
	private static function delete_ledger_range_for_contract(int $contract_id, ?string $fromYm, ?string $toYm): int {
	  global $wpdb;
	  $t = $wpdb->prefix.'spm_billing_ledger';
	  if (!$fromYm && !$toYm) {
		// Cancella tutto per il contratto
		return (int)$wpdb->query($wpdb->prepare("DELETE FROM $t WHERE contract_id=%d", $contract_id));
	  }
	  $tz = wp_timezone();
	  $from = $fromYm ? self::first_day_of_ym($fromYm, $tz)->format('Y-m-d') : '0001-01-01';
	  $to   = $toYm   ? self::last_day_of_ym($toYm,   $tz)->format('Y-m-d') : '9999-12-31';
	  return (int)$wpdb->query($wpdb->prepare(
		"DELETE FROM $t WHERE contract_id=%d AND period_start >= %s AND period_end <= %s",
		$contract_id, $from, $to
	  ));
	}
	
	/** First-day helper per 'YYYY-MM' */
	private static function first_day_of_ym(string $ym, DateTimeZone $tz): DateTime {
	  // safe parse: 'YYYY-MM'
	  if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
		return new DateTime('first day of this month', $tz);
	  }
	  return DateTime::createFromFormat('Y-m-d', $ym.'-01', $tz)->setTime(0,0,0);
	}
	
	/** Last-day helper per 'YYYY-MM' */
	private static function last_day_of_ym(string $ym, DateTimeZone $tz): DateTime {
	  $d = self::first_day_of_ym($ym, $tz);
	  return $d->modify('last day of this month')->setTime(0,0,0);
	}

}

/**
 * Helper functions globali (retrocompatibilità).
 * Mantengono le firme usate altrove nel plugin/tema.
 */
function spm_convert_date_to_db($date) {
	return SPM_Date_Helper::to_db_format($date);
}

function spm_convert_date_to_display($date) {
	return SPM_Date_Helper::to_display_format($date);
}

function spm_calculate_next_due($date, $frequency) {
	return SPM_Date_Helper::calculate_next_due_date($date, $frequency);
}
