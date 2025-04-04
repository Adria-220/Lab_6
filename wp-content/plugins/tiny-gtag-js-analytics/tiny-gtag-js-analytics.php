<?php
/*
Plugin Name: Tiny gtag.js Analytics
Description: Simple, customisable gtag.js for Analytics and/or AdWords.
Version: 3.1.0
Author: Roy Orbitson
Author URI: https://profiles.wordpress.org/lev0/
Licence: GPLv2 or later
*/

define('TINY_GTAG_BASE', basename(__FILE__, '.php'));

if (is_admin()) {

add_action(
	'admin_menu',
	function() {
		/* translators: gtag.js */
		$title = sprintf(__('Tiny %s Analytics', 'tiny-gtag-js-analytics'), 'gtag.js');
		$slug_admin = TINY_GTAG_BASE . '-admin';
		$slug_settings = TINY_GTAG_BASE . '-settings';

		add_options_page(
			esc_html($title),
			'gtag.js',
			'administrator',
			$slug_admin,
			function() use ($title, $slug_admin, $slug_settings) {
				?>
				<div class=wrap>
					<h1><?php echo esc_html($title); ?></h1>
					<form action=options.php method=post>
						<?php
						settings_fields($slug_settings);
						do_settings_sections($slug_admin);
						submit_button();
						?>
					</form>
				</div>
				<?php
			},
		);

		add_action(
			'admin_init',
			function() use ($slug_admin, $slug_settings) {
				$sanitised = false; # https://core.trac.wordpress.org/ticket/21989
				register_setting(
					$slug_settings,
					TINY_GTAG_BASE,
					[
						'sanitize_callback' => function($inputs) use (&$sanitised) {
							if ($sanitised) {
								return $inputs;
							}
							$sanitised = true;
							foreach ($inputs as $setting => $val) {
								switch ($setting) {
									case 'enabled':
									case 'body':
									case 'limit':
									case 'limit_roles_exclude':
										$inputs[$setting] = (bool) (int) $val;
										break;
									case 'limit_roles':
										$inputs[$setting] = array_fill_keys($val, true);
										break;
									default:
										$inputs[$setting] = trim($val);
								}
							}
							$id = $inputs['ga4'] ?: $inputs['ua'] ?: $inputs['aw'];
							$inputs['script_param'] = urlencode($id);
							$inputs['configs'] = array_map('json_encode', array_filter(
								[
									$inputs['ga4'],
									$inputs['ua'],
									$inputs['aw'],
								],
								'strlen',
							));
							return $inputs;
						},
					],
				);

				$options = get_option(TINY_GTAG_BASE);

				$slug_sect = TINY_GTAG_BASE . '-output';
				add_settings_section(
					$slug_sect,
					_x('Activation', 'Settings page output title', 'tiny-gtag-js-analytics'),
					'__return_null',
					$slug_admin,
				);
				$name = 'enabled';
				add_settings_field(
					$name,
					__('Enabled', 'tiny-gtag-js-analytics'),
					function() use (&$options, $name) {
						printf(
							'<input type=hidden name="%1$s[%2$s]" value="0">'
								. '<input type=checkbox name="%1$s[%2$s]" id=%2$s value="1"%3$s>',
							TINY_GTAG_BASE,
							$name,
							isset($options[$name]) && !$options[$name] ? '' : ' checked',
						);
						echo "\n", '<p class="description">',
							esc_html__('When unchecked, the plugin will not output anything to the front end of the site.', 'tiny-gtag-js-analytics'),
							'</p>';
					},
					$slug_admin,
					$slug_sect,
				);
				$name = 'limit';
				add_settings_field(
					$name,
					__('Limit', 'tiny-gtag-js-analytics'),
					function() use (&$options, $name) {
						printf(
							'<input type=hidden name="%1$s[%2$s]" value="0">'
								. '<label><input type=checkbox name="%1$s[%2$s]" id=%2$s value="1"%3$s> %4$s</label><br>',
							TINY_GTAG_BASE,
							$name,
							empty($options[$name]) ? '' : ' checked',
							esc_html__('Enabled only for logged out users', 'tiny-gtag-js-analytics'),
						);

						$namex = 'limit_roles_exclude';
						$name = 'limit_roles';
						if (!isset($options[$namex])) {
							$options[$namex] = false;
							if (!isset($options[$name])) {
								# sensible default applies only if limit is enabled
								$options[$name] = [
									'subscriber' => true,
									'customer' => true,
								];
							}
						}

						foreach (
							[
								esc_html__('…and the roles selected below', 'tiny-gtag-js-analytics'), # false
								esc_html__('…except the roles selected below', 'tiny-gtag-js-analytics'), # true
							]
							as $exclude => $label
						) {
							printf(
								'<br><label><input type=radio name="%1$s[%2$s]" id="%2$s-%3$d" value="%3$d"%4$s> %5$s</label>',
								TINY_GTAG_BASE,
								$namex,
								$exclude,
								$options[$namex] == $exclude ? ' checked' : '', # weak comparison for int to bool
								$label,
							);
						}

						echo '<br>';
						foreach (wp_roles()->get_names() as $role => $label) {
							printf(
								'<br><label><input type=checkbox name="%1$s[%2$s][]" id="%2$s-%3$s" value="%3$s"%4$s> %5$s</label>',
								TINY_GTAG_BASE,
								$name,
								esc_attr($role),
								empty($options[$name][$role]) ? '' : ' checked',
								translate_user_role($label),
							);
						}
					},
					$slug_admin,
					$slug_sect,
				);

				$slug_sect = TINY_GTAG_BASE . '-config';
				add_settings_section(
					$slug_sect,
					_x('Configuration', 'Settings page config title', 'tiny-gtag-js-analytics'),
					function() {
						echo '<p>',
							sprintf(
								/* translators: 1: G-XXXXXXXXXX, 2: UA-XXXXXXXX-X, 3: AW-XXXXXXXXX */
								esc_html__('Provide one or more of %1$s, %2$s and %3$s.', 'tiny-gtag-js-analytics'),
								'<code>G-XXXXXXXXXX</code>',
								'<code>UA-XXXXXXXX-X</code>',
								'<code>AW-XXXXXXXXX</code>',
							),
							'</p>';
					},
					$slug_admin,
				);
				$name = 'body';
				add_settings_field(
					$name,
					__('Output scripts after opening body tag', 'tiny-gtag-js-analytics'),
					function() use (&$options, $name) {
						printf(
							'<input type=hidden name="%1$s[%2$s]" value="0">'
								. '<input type=checkbox name="%1$s[%2$s]" id=%2$s value="1"%3$s>'
								. "\n" . '<p class="description">'
								. sprintf(
									/* translators: wp_body_open */
									esc_html__('Recommended, but your theme must support the %s action. Try it out.', 'tiny-gtag-js-analytics'),
									'<code>wp_body_open</code>',
								)
								. '</p>',
							TINY_GTAG_BASE,
							$name,
							empty($options[$name]) ? '' : ' checked',
						);
					},
					$slug_admin,
					$slug_sect,
				);
				$name = 'delay';
				add_settings_field(
					$name,
					__('Delay output of the external gtag script', 'tiny-gtag-js-analytics'),
					function() use (&$options, $name) {
						printf(
							'<input type=hidden name="%1$s[%2$s]" value="0">'
								. '<input type=checkbox name="%1$s[%2$s]" id=%2$s value="1"%3$s>'
								. "\n" . '<p class="description">'
								. sprintf(
									/* translators: wp_footer */
									esc_html__('Enabling this causes the main script loaded from Google to be output separately, at the %s action, instead of with the configuration script. May improve page performance.', 'tiny-gtag-js-analytics'),
									'<code>wp_footer</code>',
								)
								. '</p>',
							TINY_GTAG_BASE,
							$name,
							empty($options[$name]) ? '' : ' checked',
						);
					},
					$slug_admin,
					$slug_sect,
				);
				$name = 'ga4';
				add_settings_field(
					$name,
					'G-XXXXXXXXXX',
					function() use (&$options, $name) {
						printf(
							'<input type=text pattern="\\s*G-[A-Z\\d]+\\s*" title="'
								. esc_attr(sprintf(
									/* translators: G-XXXXXXXXXX */
									_x('%s (X\'s are uppercase alphanumeric characters)', 'GA4 ID validation', 'tiny-gtag-js-analytics'),
									'G-XXXXXXXXXX',
								))
								. '" class=regular-text id=%1$s-%2$s name="%1$s[%2$s]" value="%3$s">',
							TINY_GTAG_BASE,
							$name,
							isset($options[$name]) ? esc_attr($options[$name]) : '',
						);
					},
					$slug_admin,
					$slug_sect,
					[
						'label_for' => TINY_GTAG_BASE . "-$name",
					],
				);
				$name = 'ua';
				add_settings_field(
					$name,
					'UA-XXXXXXXX-X',
					function() use (&$options, $name) {
						printf(
							'<input type=text pattern="\\s*UA-\\d+-\\d+\\s*" title="'
								. esc_attr(sprintf(
									/* translators: UA-XXXXXXXX-X */
									_x('%s (X\'s are digits)', 'Analytics ID validation', 'tiny-gtag-js-analytics'),
									'UA-XXXXXXXX-X',
								))
								. '" class=regular-text id=%1$s-%2$s name="%1$s[%2$s]" value="%3$s">',
							TINY_GTAG_BASE,
							$name,
							isset($options[$name]) ? esc_attr($options[$name]) : '',
						);
					},
					$slug_admin,
					$slug_sect,
					[
						'label_for' => TINY_GTAG_BASE . "-$name",
					],
				);
				$name = 'aw';
				add_settings_field(
					$name,
					'AW-XXXXXXXXX',
					function() use (&$options, $name) {
						printf(
							'<input type=text pattern="\\s*AW-\\d+\\s*" title="'
								. esc_attr(sprintf(
									/* translators: AW-XXXXXXXXX */
									_x('%s (X\'s are digits)', 'AdWords ID validation', 'tiny-gtag-js-analytics'),
									'AW-XXXXXXXXX',
								))
								. '" class=regular-text id=%1$s-%2$s name="%1$s[%2$s]" value="%3$s">',
							TINY_GTAG_BASE,
							$name,
							isset($options[$name]) ? esc_attr($options[$name]) : '',
						);
					},
					$slug_admin,
					$slug_sect,
					[
						'label_for' => TINY_GTAG_BASE . "-$name",
					],
				);
				$name = 'extra';
				add_settings_field(
					$name,
					__('Additional Tracking JavaScript', 'tiny-gtag-js-analytics'),
					function() use (&$options, $name) {
						$option = isset($options[$name]) ? esc_html($options[$name]) : '';
						printf(
							'<textarea class="regular-text code" id=%1$s-%2$s name="%1$s[%2$s]" rows=%3$d>%4$s</textarea>'
								. '<p class="description">'
								. esc_html__('Optional. Be careful, syntax errors here could break your site.', 'tiny-gtag-js-analytics')
								. '</p>',
							TINY_GTAG_BASE,
							$name,
							max(6, 1 + substr_count($option, "\n")),
							$option,
						);
					},
					$slug_admin,
					$slug_sect,
					[
						'label_for' => TINY_GTAG_BASE . "-$name",
					],
				);
				$name = 'pre_extra';
				add_settings_field(
					$name,
					__('Preliminary JavaScript', 'tiny-gtag-js-analytics'),
					function() use (&$options, $name) {
						$option = isset($options[$name]) ? esc_html($options[$name]) : '';
						printf(
							'<textarea class="regular-text code" id=%1$s-%2$s name="%1$s[%2$s]" rows=%3$d>%4$s</textarea>'
								. '<p class="description">'
								. sprintf(
									/* translators: gtag.js */
									esc_html__('Normally not required. Further %s set-up script output before the standard config and Additional Tracking JavaScript.', 'tiny-gtag-js-analytics'),
									'gtag.js',
								)
								. '</p>',
							TINY_GTAG_BASE,
							$name,
							max(6, 1 + substr_count($option, "\n")),
							$option,
						);
					},
					$slug_admin,
					$slug_sect,
					[
						'label_for' => TINY_GTAG_BASE . "-$name",
					],
				);
			},
		);
	},
	9999,
	0,
);
add_filter(
	'plugin_action_links_' . plugin_basename(__FILE__),
	function($links) {
		array_unshift(
			$links,
			'<a href="' . admin_url('options-general.php?page=' . TINY_GTAG_BASE . '-admin') . '">'
				. esc_html_x('Settings', 'Plugin page link text', 'tiny-gtag-js-analytics') . '</a>',
		);
		return $links;
	},
);

} else {

add_action(
	'wp_head',
	function() {
		$options = get_option(TINY_GTAG_BASE);
		if (!$options) { # assume incomplete install
			return;
		}

		# upgrade existing
		$configs = [];
		if (isset($options['config'])) {
			if ($options['config']) {
				$configs[] = $options['config'];
			}
			if (!empty($options['config2'])) {
				$configs[] = $options['config2'];
			}
			unset($options['config'], $options['config2']);
		}
		$options += [
			'enabled' => true,
			'limit' => false,
			'limit_roles_exclude' => false,
			'limit_roles' => [],
			'body' => false,
			'delay' => false,
			'ga4' => '',
			'aw' => '',
			'pre_extra' => '',
			'configs' => $configs,
		];

		/**
		 * Modify the script tags that are output by Tiny gtag.js Analytics
		 *
		 * @since 1.0.0
		 *
		 * @param array $options  {
		 *     Options + raw output variables of script tags.
		 *
		 *     @type boolean $enabled Whether plugin output is enabled at all, default: admin setting, true.
		 *     @type boolean $limit Whether plugin output should be disabled if the user is logged in, default: admin setting, false.
		 *     @type boolean $limit_roles_exclude Whether to treat the $limit_roles list as an exclude list instead of the default include list, default: admin setting, false.
		 *     @type array $limit_roles Associative list of rolename => enabled to include (or exclude) when limited to logged out users, default: admin setting, empty array.
		 *     @type boolean $body Whether plugin output should be after the opening <body>, default: admin setting, false.
		 *     @type boolean $delay Whether plugin output for main external script should be delayed until the closing of <body>, default: admin setting, false.
		 *     @type string $ga4 The G-XXXXXXXXXX ID for your reference, changing this only affects subsequent filters as it's not output directly.
		 *     @type string $ua The UA-XXXXXXXX-X ID for your reference, changing this only affects subsequent filters as it's not output directly.
		 *     @type string $aw The AW-XXXXXXXXX ID for your reference, changing this only affects subsequent filters as it's not output directly.
		 *     @type string $script_param Library script parameter, default: URL-encoded $ga4, $ua or $aw.
		 *     @type array $configs any supplied gtag config parameter(s), default: JSON-encoded $ga, $uai, and/or $aw, or empty array.
		 *     @type string $pre_extra Extra JavaScript to place on the current page before configs, default: code entered in admin settings.
		 *     @type string $extra Extra JavaScript to place on the current page after configs, default: code entered in admin settings.
		 * }
		 */
		$options = array_replace(
			array_intersect_key(
				(array) apply_filters('tiny_gtag_js_analytics_output', $options),
				$options,
			),
			$options,
		);

		if (
			!$options['enabled']
			|| !$options['script_param']
			|| !$options['configs']
			|| (
				$options['limit']
				&& is_user_logged_in()
				&& (
					!$options['limit_roles_exclude']
					xor (
						$options['limit_roles']
						&& is_array($options['limit_roles'])
						&& ($options['limit_roles'] = array_filter($options['limit_roles']))
						&& array_intersect(
							array_keys($options['limit_roles']),
							wp_get_current_user()->roles,
						)
					)
				)
			)
		) {
			return;
		}

		$options['js_options'] = (object) array_filter(array_intersect_key(
			$options,
			array_flip([
				'ga4',
				'ua',
				'aw',
			]),
		));
		$main_done = false;
		$datalayer_done = false;
		$output = function() use ($options, &$main_done, &$datalayer_done) {
			extract($options, EXTR_SKIP);

			if (!$main_done && ($datalayer_done || !$delay)) {
				$main_done = true;
				echo <<<EOHTML
<script async src="https://www.googletagmanager.com/gtag/js?id=$script_param"></script>

EOHTML;
			}

			if ($datalayer_done) {
				return;
			}
			$datalayer_done = true;
			if ($pre_extra) {
				$pre_extra = rtrim("\n$pre_extra");
			}
			foreach ($configs as $k => $config) {
				$configs[$k] = "\ngtag('config', $config);";
			}
			$configs = implode('', $configs);
			if ($extra) {
				$extra = rtrim("\n$extra");
			}
			$js_options = json_encode($js_options);

			echo <<<EOHTML
<script>
var tinyGtagJsOptions = $js_options;
window.dataLayer || (dataLayer = []);
function gtag(){dataLayer.push(arguments);}$pre_extra
gtag('js', new Date());$configs$extra
</script>

EOHTML;
		};

		if (!$options['body'] || !function_exists('wp_body_open')) {
			$output();
		}
		else {
			add_action('wp_body_open', $output, 5, 0);
		}
		if ($options['delay']) {
			add_action('wp_footer', $output, 55, 0);
		}
	},
	5,
	0,
);

}
