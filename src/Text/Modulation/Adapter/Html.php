<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 */

class Text_Modulation_Adapter_Html {
	/**
	 * Truncates text. Cuts a string to the length of $length and replaces the
	 * last characters with the ending if the text is longer than length.
	 *
	 * @param string $text String to truncate.
	 * @param integer $length Length of returned string, including ellipsis.
	 * @param string $end Will be used as Ending and appended to the trimmed string
	 * @param boolean $exact If false, $text will not be cut mid-word
	 * @return string Trimmed string.
	 */
	public function limit($text, $length = 100, $end = '…', $exact = true) {
		if (mb_strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
			return $text;
		}
		$totalLength = mb_strlen(strip_tags($end));
		$openTags = array();
		$truncate = '';

		preg_match_all('/(<\/?([\w+]+)[^>]*>)?([^<>]*)/', $text, $tags, PREG_SET_ORDER);
		foreach ($tags as $tag) {
			if (!preg_match('/img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param/s', $tag[2])) {
				if (preg_match('/<[\w]+[^>]*>/s', $tag[0])) {
					array_unshift($openTags, $tag[2]);
				} elseif (preg_match('/<\/([\w]+)[^>]*>/s', $tag[0], $closeTag)) {
					$pos = array_search($closeTag[1], $openTags);
					if ($pos !== false) {
						array_splice($openTags, $pos, 1);
					}
				}
			}
			$truncate .= $tag[1];

			$contentLength = mb_strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', ' ', $tag[3]));
			if ($contentLength + $totalLength > $length) {
				$left = $length - $totalLength;
				$entitiesLength = 0;
				if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', $tag[3], $entities, PREG_OFFSET_CAPTURE)) {
					foreach ($entities[0] as $entity) {
						if ($entity[1] + 1 - $entitiesLength <= $left) {
							$left--;
							$entitiesLength += mb_strlen($entity[0]);
						} else {
							break;
						}
					}
				}

				$truncate .= mb_substr($tag[3], 0 , $left + $entitiesLength);
				break;
			} else {
				$truncate .= $tag[3];
				$totalLength += $contentLength;
			}
			if ($totalLength >= $length) {
				break;
			}
		}
		if (!$exact) {
			$spacepos = mb_strrpos($truncate, ' ');
			$truncateCheck = mb_substr($truncate, 0, $spacepos);
			$lastOpenTag = mb_strrpos($truncateCheck, '<');
			$lastCloseTag = mb_strrpos($truncateCheck, '>');

			if ($lastOpenTag > $lastCloseTag) {
				preg_match_all('/<[\w]+[^>]*>/s', $truncate, $lastTagMatches);
				$lastTag = array_pop($lastTagMatches[0]);
				$spacepos = mb_strrpos($truncate, $lastTag) + mb_strlen($lastTag);
			}
			$bits = mb_substr($truncate, $spacepos);
			preg_match_all('/<\/([a-z]+)>/', $bits, $droppedTags, PREG_SET_ORDER);

			if (!empty($droppedTags)) {
				if (!empty($openTags)) {
					foreach ($droppedTags as $closingTag) {
						if (!in_array($closingTag[1], $openTags)) {
							array_unshift($openTags, $closingTag[1]);
						}
					}
				} else {
					foreach ($droppedTags as $closingTag) {
						array_push($openTags, $closingTag[1]);
					}
				}
			}
			$truncate = mb_substr($truncate, 0, $spacepos);
		}
		$truncate .= $end;

		foreach ($openTags as $tag) {
			$truncate .= '</' . $tag . '>';
		}
		return $truncate;
	}

	/**
	 * Highlights a given phrase in a text. You can specify any expression in
	 * highlighter that may include the \1 expression to include the $phrase found.
	 *
	 * @param string $text Text to search the phrase in
	 * @param string $phrase The phrase that will be searched
	 * @param string $format The piece of html with that the phrase will be highlighted
	 * @param string $regex A custom regex rule that is ued to match words, default is '|$tag|iu'
	 * @return string The highlighted text
	 */
	public function highlight($text, $phrase, $format = '<span class="highlight">\1</span>', $regex = '|%s|iu') {
		if (empty($phrase)) {
			return $text;
		}

		if (is_array($phrase)) {
			$replace = array();
			$with = array();

			foreach ($phrase as $key => $segment) {
				$segment = '(' . preg_quote($segment, '|') . ')';
				$segment = "(?![^<]+>)$segment(?![^<]+>)";

				$with[] = (is_array($format)) ? $format[$key] : $format;
				$replace[] = sprintf($regex, $segment);
			}

			return preg_replace($replace, $with, $text);
		}
		$phrase = '(' . preg_quote($phrase, '|') . ')';
		$phrase = "(?![^<]+>)$phrase(?![^<]+>)";

		return preg_replace(sprintf($regex, $phrase), $format, $text);
	}

	/**
	 * Extracts an excerpt from the text surrounding the phrase with a number
	 * of characters on each side determined by radius.
	 *
	 * @param string $text String to search the phrase in
	 * @param string $phrase Phrase that will be searched for
	 * @param integer $radius The amount of characters that will be returned on each side of the founded phrase
	 * @param string $end Ending that will be appended
	 * @return string Modified string
	 */
	public function excerpt($text, $phrase, $radius = 100, $end = '…') {
		if (empty($text) || empty($phrase)) {
			return self::truncate($text, $radius * 2, array('end' => $end));
		}

		$append = $prepend = $end;

		$phraseLen = mb_strlen($phrase);
		$textLen = mb_strlen($text);

		$pos = mb_strpos(mb_strtolower($text), mb_strtolower($phrase));
		if ($pos === false) {
			return mb_substr($text, 0, $radius) . $end;
		}

		$startPos = $pos - $radius;
		if ($startPos <= 0) {
			$startPos = 0;
			$prepend = '';
		}

		$endPos = $pos + $phraseLen + $radius;
		if ($endPos >= $textLen) {
			$endPos = $textLen;
			$append = '';
		}

		$excerpt = mb_substr($text, $startPos, $endPos - $startPos);
		$excerpt = $prepend . $excerpt . $append;

		return $excerpt;
	}

	/**
	 * Adds links (<a href=....) to a given text, by finding text that begins with
	 * strings like http:// and ftp://.
	 *
	 * @param string $text Text
	 * @param array $options Array of HTML options, and options listed above.
	 * @return string The text with links
	 */
	public function autoLinkUrls($text) {
		$placeholders = array();
		$replace = array();

		$insertPlaceholder = function($matches) use (&$placeholders) {
			$key = md5($matches[0]);
			$placeholders[$key] = $matches[0];

			return $key;
		};

		$pattern = '#(?<!href="|src="|">)((?:https?|ftp|nntp)://[a-z0-9.\-:]+(?:/[^\s]*)?)#i';
		$text = preg_replace_callback($pattern, $insertPlaceholder, $text);

		$pattern = '#(?<!href="|">)(?<!\b[[:punct:]])(?<!http://|https://|ftp://|nntp://)www.[^\n\%\ <]+[^<\n\%\,\.\ <](?<!\))#i';
		$text = preg_replace_callback($pattern, $insertPlaceholder, $text);

		foreach ($placeholders as $hash => $url) {
			$link = $url;

			if (!preg_match('#^[a-z]+\://#', $url)) {
				$url = 'http://' . $url;
			}
			$replace[$hash] = "<a href=\"{$url}\">{$link}</a>";
		}
		return strtr($text, $replace);
	}

	// This doesn't use the placholder/escaping workaround from CakePHP 2's TextHelper yet.
	public function autoLinkEmails($text) {
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