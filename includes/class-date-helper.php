<?php
/**
 * S121 Pulse Manager - Date Helper Semplificato
 * Fix immediato per gestione date consistente
 */

defined('ABSPATH') || exit;

class SPM_Date_Helper {
	
	/**
	 * Formato interno standard (database)
	 */
	const DB_FORMAT = 'Y-m-d';
	
	/**
	 * Formato display italiano
	 */
	const DISPLAY_FORMAT = 'd/m/Y';
	
	/**
	 * Formato ACF date picker
	 */
	const ACF_FORMAT = 'Ymd';
	
	/**
	 * Converte una data da qualsiasi formato al formato DB
	 */
	public static function to_db_format($date) {
		if (empty($date)) return '';
		
		// Se già nel formato corretto
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			return $date;
		}
		
		// Da formato italiano d/m/Y
		if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
			$dt = DateTime::createFromFormat('d/m/Y', $date);
			return $dt ? $dt->format('Y-m-d') : '';
		}
		
		// Da formato ACF Ymd
		if (preg_match('/^\d{8}$/', $date)) {
			$dt = DateTime::createFromFormat('Ymd', $date);
			return $dt ? $dt->format('Y-m-d') : '';
		}
		
		return '';
	}
	
	/**
	 * Converte una data dal formato DB al formato display
	 */
	public static function to_display_format($date) {
		if (empty($date)) return '';
		
		$db_date = self::to_db_format($date);
		if (!$db_date) return '';
		
		$dt = new DateTime($db_date);
		return $dt->format('d/m/Y');
	}
	
	/**
	 * Calcola la prossima scadenza basata su frequenza
	 */
	public static function calculate_next_due_date($start_date, $frequency) {
		$db_date = self::to_db_format($start_date);
		if (!$db_date) return '';
		
		$dt = new DateTime($db_date);
		
		switch ($frequency) {
			case 'mensile':
				$dt->modify('+1 month');
				break;
			case 'trimestrale':
				$dt->modify('+3 months');
				break;
			case 'semestrale':
				$dt->modify('+6 months');
				break;
			case 'annuale':
				$dt->modify('+1 year');
				break;
		}
		
		return $dt->format('Y-m-d');
	}
	
	/**
	 * Calcola giorni rimanenti alla scadenza
	 */
	public static function days_until_due($due_date) {
		$db_date = self::to_db_format($due_date);
		if (!$db_date) return 999;
		
		$today = new DateTime();
		$due = new DateTime($db_date);
		$interval = $today->diff($due);
		
		return $interval->invert ? -$interval->days : $interval->days;
	}
	
	/**
	 * Verifica se una data è scaduta
	 */
	public static function is_expired($date) {
		return self::days_until_due($date) < 0;
	}
	
	/**
	 * Verifica se una data è in scadenza entro X giorni
	 */
	public static function is_expiring_soon($date, $days_threshold = 30) {
		$days = self::days_until_due($date);
		return $days >= 0 && $days <= $days_threshold;
	}
}

/**
 * Helper functions globali per retrocompatibilità
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