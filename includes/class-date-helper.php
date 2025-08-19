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
