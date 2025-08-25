<?php
/**
 * Rate Limiter per operazioni sensibili
 * Previene abusi su sync, backfill e altre operazioni costose
 */

defined('ABSPATH') || exit;

class SPM_Rate_Limiter {
	
	private static $instance = null;
	
	public static function instance() {
		return self::$instance ?: (self::$instance = new self());
	}
	
	/**
	 * Verifica se un'operazione può essere eseguita in base al rate limit
	 * 
	 * @param string $operation Nome operazione (es: 'manual_sync', 'backfill')
	 * @param int $limit Numero massimo di operazioni
	 * @param int $window Finestra temporale in secondi (default: 1 ora)
	 * @return bool True se può procedere, False se limitato
	 */
	public function can_proceed($operation, $limit = 5, $window = 3600) {
		$user_id = get_current_user_id();
		if (!$user_id) return false;
		
		$key = 'spm_rate_limit_' . $operation . '_' . $user_id;
		$attempts = get_transient($key);
		
		if ($attempts === false) {
			// Prima volta, inizializza
			set_transient($key, 1, $window);
			return true;
		}
		
		if ($attempts >= $limit) {
			return false;
		}
		
		// Incrementa counter
		set_transient($key, $attempts + 1, $window);
		return true;
	}
	
	/**
	 * Ottieni informazioni sul rate limit corrente
	 * 
	 * @param string $operation Nome operazione
	 * @return array Info su attempts, limit, reset_time
	 */
	public function get_limit_info($operation) {
		$user_id = get_current_user_id();
		if (!$user_id) return null;
		
		$key = 'spm_rate_limit_' . $operation . '_' . $user_id;
		$attempts = get_transient($key) ?: 0;
		
		// Prova a determinare quando scade il transient (approssimativo)
		$timeout_option = '_transient_timeout_' . $key;
		$reset_time = get_option($timeout_option, 0);
		
		return [
			'attempts' => $attempts,
			'reset_time' => $reset_time,
			'reset_in' => max(0, $reset_time - time())
		];
	}
	
	/**
	 * Reset manuale di un rate limit (solo per admin)
	 * 
	 * @param string $operation Nome operazione
	 * @param int $user_id ID utente (opzionale, default corrente)
	 * @return bool True se resettato
	 */
	public function reset_limit($operation, $user_id = null) {
		if (!current_user_can('manage_options')) return false;
		
		$user_id = $user_id ?: get_current_user_id();
		$key = 'spm_rate_limit_' . $operation . '_' . $user_id;
		
		return delete_transient($key);
	}
	
	/**
	 * Helper per generare messaggio di errore user-friendly
	 * 
	 * @param string $operation Nome operazione
	 * @param array $info Info dal get_limit_info()
	 * @return string Messaggio errore
	 */
	public function get_limit_message($operation, $info = null) {
		if (!$info) {
			$info = $this->get_limit_info($operation);
		}
		
		$reset_in_min = ceil($info['reset_in'] / 60);
		
		return sprintf(
			'Rate limit raggiunto per "%s". Riprova tra %d minuti. (Tentativi: %d)',
			$operation,
			$reset_in_min,
			$info['attempts']
		);
	}
}