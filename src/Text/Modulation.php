<?php

class Text_Modulation {

	protected static $_adapters = array();

	protected static function _adapter($name) {
		if (isset(static::$_adapters[$name])) {
			return static::$_adapters[$name];
		}
		$class = 'Text_Modulation_Adapter_' . ucfirst($name);
		require_once dirname(__DIR__) . '/' . str_replace('_', '/', $class) . '.php';

		return static::$_adapters[$name] = new $class();
	}

	// html support
	public static function limit($string, $length = 50, $options = array()) {
		$options += array(
			'html' => false,
			'end' => '…',
			'exact' => true // html mode only
		);
		if ($options['html']) {
			return static::_adapter('html')->limit($string, $length, $options['end'], $options['exact']);
		} else {
			return static::_adapter('text')->limit($string, $length, $options['end']);
		}
	}

	// html only
	public static function highlight($text, $phrase, $options = array()) {
		$options += array(
			'html' => false
		);
		if ($options['html']) {
			return static::_adapter('html')->highlight($text, $phrase);
		}
		throw new Exception('Unimplemented; highlight support only for type `html`.');
	}

	// html and text
	public static function excerpt($string, $length = 50, $options = array()) {
		$options += array(
			'html' => false,
			'end' => '…',
			'minLineLength' => 100, // non-html mode only
			'start' => '…', // non-html mode only
			'phrase' => null // html-mode only
		);
		if ($options['html']) {
			if (!$options['phrase']) {
				$message = 'You must provide a phrase for html mode; fallback to whole string.';
				trigger_error($message, E_USER_WARNING);

				return $string;
			}
			return static::_adapter('html')->excerpt(
				$string, $options['phrase'], $length, $options['end']
			);
		}
		return static::_adapter('text')->excerpt(
			$string, $length, $options['minLineLength'], $options['start'], $options['end']
		);
	}

	// text only
	public static function lines($string, $lines = 15, $end = '…', $options = array()) {
		$options += array(
			'html' => false
		);
		if ($options['html']) {
			throw new Exception('Unimplemented; no lines support for type `html`.');
		}
		return static::_adapter('text')->lines($string, $lines, $end);
	}

	/**
	 * Creates a comma separated list where the last two items are joined with
	 * 'and', forming natural English.
	 *
	 * @param array $list The list to be joined
	 * @param string $and The word used to join the last and second last items
	 *                    together with. Defaults to 'and'
	 * @param string $separator The separator used to join all the other items
	 *                          together. Defaults to ', '
	 * @return string The glued together string.
	 */
	public static function toList($list, $and = 'and', $separator = ', ') {
		if (count($list) > 1) {
			return implode($separator, array_slice($list, null, -1)) . ' ' . $and . ' ' . array_pop($list);
		} else {
			return array_pop($list);
		}
	}

	public static function autoLink($text, $options = array()) {
		$options += array(
			'html' => false
		);
		if (!$options['html']) {
			throw new Exception('Unimplemented; autolinking only for type `html`.');
		}

		$text = static::_adapter('html')->autoLinkUrls($text);
		$text = static::_adapter('html')->autoLinkEmails($text);
		return $text;
	}
}

?>