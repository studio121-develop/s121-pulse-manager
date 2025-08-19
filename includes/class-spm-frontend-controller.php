<?php
/**
 * SPM Frontend Controller
 * - Modalità: normal | static | off
 * - Whitelist: admin, login, AJAX, REST, cron, xmlrpc, favicon/robots/sitemap
 * - Static: pagina minimale con pulsante "Accedi"
 */

defined('ABSPATH') || exit;

final class SPM_Frontend_Controller {

	public static function init(): void {
		// run il prima possibile, ma dopo che WP ha calcolato le query
		add_action('template_redirect', [__CLASS__, 'maybe_intercept'], 0);
	}

	/** Entry point: decide se intercettare la richiesta frontend */
	public static function maybe_intercept(): void {
		// niente backend
		if (is_admin()) return;

		// lascia passare richieste "di servizio"
		if (self::is_exempt_request()) return;

		$mode = self::opt('frontend_mode', 'static'); // normal | static | off
		if ($mode === 'normal') return;

		if ($mode === 'off') {
			self::handle_off();
		} else {
			self::serve_static();
		}
	}

	/** Modalità OFF: nessun frontend pubblico */
	private static function handle_off(): void {
		// non loggato → manda a login (con redirect back)
		if (!is_user_logged_in()) {
			auth_redirect();
			exit;
		}
		// loggato → bacheca
		wp_safe_redirect(admin_url());
		exit;
	}

	/** Modalità STATIC: pagina di cortesia */
	private static function serve_static(): void {
		$redirect_logged = (bool) self::opt('frontend_redirect_logged_in', 1);
		if ($redirect_logged && is_user_logged_in()) {
			wp_safe_redirect(admin_url());
			exit;
		}

		status_header(200);
		nocache_headers();

		// opzionale: header per noindex
		if ((bool) self::opt('frontend_noindex', 1)) {
			header('X-Robots-Tag: noindex, nofollow', true);
		}

		header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

		// contenuto customizzabile via setting o filtro
		$html = self::opt('frontend_static_html', null);
		if (!$html) $html = self::default_static_html();

		// filtro finale per personalizzazioni
		$html = apply_filters('spm/frontend/static_html', $html);

		echo $html;
		exit;
	}

	/* ---------- Helpers ---------- */

	/** Whitelist di richieste che non vanno intercettate */
	private static function is_exempt_request(): bool {
		// AJAX / REST / CRON
		if (defined('DOING_AJAX') && DOING_AJAX) return true;
		if (defined('REST_REQUEST') && REST_REQUEST) return true;
		if (defined('DOING_CRON') && DOING_CRON) return true;

		// login / cron / xmlrpc (script name è più robusto qui)
		$script = $_SERVER['SCRIPT_NAME'] ?? '';
		if (strpos($script, 'wp-login.php') !== false) return true;
		if (strpos($script, 'wp-cron.php')  !== false) return true;
		if (strpos($script, 'xmlrpc.php')   !== false) return true;

		// REST anche via path, nel caso SCRIPT_NAME non sia affidabile
		$uri = $_SERVER['REQUEST_URI'] ?? '';
		if (strpos($uri, '/wp-json/') === 0) return true;
		if (strpos($uri, '/wp-admin/') === 0) return true; // lasciamo a WP gestire eventuali redirect

		// asset pubblici noti
		if (preg_match('#/(favicon\.ico|robots\.txt|sitemap(_index)?\.xml|sitemap-\d+\.xml)$#i', $uri)) {
			return true;
		}

		return false;
	}

	/** Recupera opzioni: Settings Page → costante → default */
	private static function opt(string $key, $default = null) {
		// 1) tua settings page (se presente)
		if (class_exists('SPM_Settings_Page')) {
			$val = SPM_Settings_Page::get($key);
			if ($val !== null && $val !== '') return $val;
		}
		// 2) costante fallback (es. SPM_FRONTEND_MODE)
		$const = 'SPM_' . strtoupper($key);
		if (defined($const)) return constant($const);

		// 3) default
		return $default;
	}

	/** HTML di default della pagina statica */
	private static function default_static_html(): string {
		$name = get_bloginfo('name');
		$desc = get_bloginfo('description') ?: 'Gestione interna';
		$login_url = wp_login_url();
		$admin_url = admin_url();
		$year = date_i18n('Y');

		ob_start(); ?>
<!doctype html>
<html lang="it">
<head>
	<meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html($name); ?></title>
	<meta name="robots" content="noindex, nofollow">
	<style>
		html,body{height:100%}
		body{
			margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;
			background:#f6f7f9; color:#222; display:flex; align-items:center; justify-content:center;
		}
		.card{
			background:#fff; padding:28px; border-radius:16px; box-shadow:0 8px 30px rgba(0,0,0,.06);
			max-width:720px; width:92%;
		}
		h1{margin:0 0 8px; font-size:28px}
		p{margin:0 0 12px; line-height:1.5}
		.badge{display:inline-block; background:#2271b1; color:#fff; padding:4px 10px; border-radius:999px; font-size:12px}
		.footer{margin-top:14px; font-size:12px; color:#666}
		.btn{display:inline-block; padding:10px 14px; border-radius:10px; background:#2271b1; color:#fff; text-decoration:none}
		.btn:hover{filter:brightness(1.05)}
		.btn.secondary{background:#2f9e44}
	</style>
</head>
<body>
	<main class="card" role="main" aria-labelledby="title">
		<span class="badge">Area pubblica</span>
		<h1 id="title"><?php echo esc_html($name); ?></h1>
		<p><?php echo esc_html($desc); ?></p>
		<p>Questo sito usa WordPress come pannello gestionale. L’area pubblica è volutamente minimale.</p>
		<p>
			<a class="btn" href="<?php echo esc_url($login_url); ?>">Accedi</a>
			<?php if ( is_user_logged_in() ): ?>
				<a class="btn secondary" style="margin-left:8px" href="<?php echo esc_url($admin_url); ?>">Vai alla Bacheca</a>
			<?php endif; ?>
		</p>
		<div class="footer">© <?php echo esc_html($year); ?> — Tutti i diritti riservati</div>
	</main>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}
}

SPM_Frontend_Controller::init();