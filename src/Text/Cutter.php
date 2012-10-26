<?php

class Text_Cutter {

	protected static $_adapters = array();

	protected static function _adapter($name) {
		if (isset(static::$_adapters[$name])) {
			return static::$_adapters[$name];
		}
		$class = 'Text_Cutter_Adapter_' . ucfirst($name);
		require_once str_replace('_', '/', $class) . '.php';

		return static::$_adapters[$name] = new $class();
	}

	public static function limit($string, $length = 50, $options = array()) {
		$options += array(
			'html' => false,
			'exact' => true,
			'end' => '…'
		);
		if ($options['html']) {
			return static::_adapter('html')->limit($string, $length, $options['end'], $options['exact']);
		} else {
			return static::_adapter('text')->limit($string, $length, $options['end']);
		}
	}

	public static function highlight($text, $phrase, $options = array()) {
		$options += array(
			'html' => false
		);
		if ($options['html']) {
			unset($options['html']);
			return static::_adapter('html')->highlight($text, $phrase, $options);
		}
		throw new Exception('Unimplemented; highlight support only for type `html`.');
	}

	public static function excerpt($string, $length = 50, $options = array()) {
		$options += array(
			'html' => false,
			'end' => '…',
			'minLineLength' => 100, // non-html mode only
			'start' => '…', // non-html mode only
			'phrase' => null, // html-mode only
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

	public static function lines($string, $lines = 15, $end = '…') {
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
		$text = static::autoLinkUrls($text);
		$text = static::autoLinkEmails($text);
		return $text;
	}

	public static function autoLinkUrls($text) {
		return static::_adapter('html')->autoLinkUrls($text);
	}

	public static function autoLinkEmails($text) {
		$atom  = '[a-z0-9!#$%&\'*+\/=?^_`{|}~-]';
		$regex = '/(' . $atom . '+(?:\.' . $atom . '+)*@[a-z0-9-]+(?:\.[a-z0-9-]+)+)/i';

		// Encodes an email address as a mailto link with each character
		// of the address encoded as either a decimal or hex entity, in
		// the hopes of foiling most address harvesting spam bots.
		//
		// Based upon the implementation as available in PHP Markdown.
		// Copyright (c) 2004-2008 Michel Fortin
		// Licensed under the 3-clause BSD license.
		//
		// Originally Based by a filter by Matthew Wickline, posted to BBEdit-Talk.
		// With some optimizations by Milian Wolff.
		$encode = function($addr) {
			$addr = "mailto:{$addr}";
			$chars = preg_split('/(?<!^)(?!$)/', $addr);
			$seed = (int) abs(crc32($addr) / strlen($addr)); # Deterministic seed.

			foreach ($chars as $key => $char) {
				$ord = ord($char);
				// Ignore non-ascii chars.
				if ($ord < 128) {
					$r = ($seed * (1 + $key)) % 100; // Pseudo-random function.
					// roughly 10% raw, 45% hex, 45% dec
					// '@' *must* be encoded. I insist.
					if ($r > 90 && $char != '@') /* do nothing */;
					else if ($r < 45) $chars[$key] = '&#x'.dechex($ord).';';
					else              $chars[$key] = '&#'.$ord.';';
				}
			}
			$addr = implode('', $chars);
			$text = implode('', array_slice($chars, 7)); // text without `mailto:`

			return "<a href=\"{$addr}\">{$text}</a>";
		};

		return preg_replace_callback($regex, function($matches) use ($encode) {
			return $encode($matches[0]);
		}, $text);
	}
}

?>