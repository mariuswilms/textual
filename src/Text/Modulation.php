<?php
/**
 * textual: tools for text
 *
 * Copyright (c) 2012-2014 David Persson
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

class Text_Modulation {

	protected static $_adapters = [];

	protected static function _adapter($name) {
		if (isset(static::$_adapters[$name])) {
			return static::$_adapters[$name];
		}
		$class = 'Text_Modulation_Adapter_' . ucfirst($name);
		require_once dirname(__DIR__) . '/' . str_replace('_', '/', $class) . '.php';

		return static::$_adapters[$name] = new $class();
	}

	// html support
	public static function limit($string, $length = 50, $options = []) {
		$options += [
			'html' => false,
			'end' => '…',
			'exact' => true // html mode only
		];
		if ($options['html']) {
			return static::_adapter('html')->limit($string, $length, $options['end'], $options['exact']);
		}
		return static::_adapter('text')->limit($string, $length, $options['end']);
	}

	// html only
	public static function highlight($text, $phrase, $options = []) {
		$options += [
			'html' => false
		];
		return static::_adapter($options['html'] ? 'html' : 'text')->highlight($text, $phrase);
	}

	// html and text
	public static function excerpt($string, $length = 50, $options = []) {
		$options += [
			'html' => false,
			'end' => '…',
			'minLineLength' => 100, // non-html mode only
			'start' => '…', // non-html mode only
			'phrase' => null // html-mode only
		];
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
	public static function lines($string, $lines = 15, $end = '…', $options = []) {
		$options += [
			'end' => '…',
			'html' => false
		];
		return static::_adapter($options['html'] ? 'html' : 'text')->lines($string, $lines, $options['end']);
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

	public static function autoLink($text, $options = []) {
		$options += [
			'html' => false
		];
		$text = static::_adapter($options['html'] ? 'html' : 'text')->autoLinkUrls($text);
		$text = static::_adapter($options['html'] ? 'html' : 'text')->autoLinkEmails($text);

		return $text;
	}
}

?>