<?php
class HTMLTmpl {
	var $lang_function = "gettext";
	var $call_function = "";
	var $format_function = "";
	var $tmpl_dir = "./";
	var $cache_dir = "./";
	
	function compile_tag($match) {
		static $loops = array();
		static $loops_used = array();
		$tag = strtoupper($match[2]);
		$attrs = preg_replace('~(/| --)+$~', '', $match[3]);
		
		if ($tag == "TMPL_ELSE") {
			$return = "<?php } else { ?>";
		} elseif ($tag == "TMPL_BOUNDARY") {
			$return = "";
		} elseif ($tag == "/TMPL_IF" || $tag == "/TMPL_UNLESS") {
			$return = "<?php } ?>";
		} elseif ($tag == "/TMPL_LOOP") {
			array_pop($loops);
			$return = "<?php } ?>";
		} else { // tags containing NAME attribute
			$name = (preg_match('~\\sNAME=("[^"]+"|[^ ]+)~i', $attrs, $match2) ? trim($match2[1], '"') : preg_replace('~^\\s+([^\\s]+).*~s', '\\1', $attrs));
			$php_var = preg_replace('~\\.~', '"]["', $name);
			$loop = count($loops);
			if ($tag == "TMPL_INCLUDE") {
				$return = "<?php \$this->read('$name', " . ($loop ? '$row' . $loop : '$vars') . ', $globals); ?>';
			} elseif ($tag == "TMPL_STATIC") {
				$return = "$match[1]<?php echo constant('$name'); ?>$match[4]";
			} elseif ($tag == "TMPL_CALL") {
				if ($this->call_function) {
					$return = "<?php $this->call_function('$name', " . ($loop ? '$row' . $loop : '$vars') . ", \$globals); ?>";
				}
			} elseif ($tag == "TMPL_SELECT") {
				$return = "<select name='$name'" . (preg_match('~\\sEXTRA=("[^"]+"|[^ ]+)~i', $attrs, $match2) ? " " . trim($match2[1], '"') : "") . ">";
				$return .= "<?php\nforeach (\$vars[\"$php_var\"] as \$key => \$val) {\n\techo '<option value=\"' . htmlspecialchars(\$key) . '\"' . (\$key == \$_REQUEST[\"$name\"] ? ' selected=\"selected\"' : '') . '>' . htmlspecialchars(\$val) . '</option>';\n}\n?>";
				$return .= "</select>";
			} elseif ($tag == "TMPL_LOOP") {
				$loop++;
				$loops[] = $name;
				$return = "<?php foreach (" . ($loop == 1 ? '$vars' : '$row' . ($loop - 1)) . "[\"$php_var\"] as \$i$loop => \$row$loop) { ?>";
			} else {
				$count = 'count($vars["' . end($loops) . '"])';
				switch ($name) {
					case '__FIRST__': $return = "\$i$loop == 0"; break;
					case '__LAST__': $return = "\$i$loop == $count-1"; break;
					case '__INNER__': $return = "\$i$loop != 0 && \$i$loop != $count-1"; break;
					case '__ODD__': $return = "\$i$loop % 2 == 0"; break;
					case '__PASS__': $return = "\$i$loop + 1"; break;
					case '__PASSTOTAL__': $return = $count; break;
					default: if (preg_match('~^__EVERY__([1-9][0-9]*)$~', $name, $match2)) {
						$return = "\$i$loop % $match2[1] == 0";
					} else {
						$return = (preg_match('~\\sGLOBAL="?1~i', $attrs) ? '$globals' : (!$loop ? '$vars' : '$row' . $loop)) . "[\"$php_var\"]";
						if ($tag == "TMPL_VAR") {
							preg_match('~\\sESCAPE="?([^\\s"]+)~i', $attrs, $match2);
							switch ($match2 ? strtoupper($match2[1]) : "HTML") {
								case 'NONE': $return = "(is_array($return) ? count($return) : $return)"; break;
								case 'JS': $return = 'addcslashes(' . $return . ', "\'\r\n\\\\")'; break;
								case 'URL': $return = "urlencode($return)"; break;
								case 'WAP': $return = "str_replace('\$', '\$\$', $return)"; // break left intentionally
								case 'HTML': $return = "nl2br(htmlspecialchars($return))"; break;
								default: if ($this->format_function) {
									$return = "$this->format_function('" . strtolower($match2[1]) . "', $return)";
								}
							}
						} elseif (preg_match('~\\sVALUE=("[^"]+"|[^ ]+)~i', $attrs, $match2)) {
							$return .= ' == "' . addcslashes(html_entity_decode(trim($match2[1], '"')), '\\"') . '"';
						} else {
							//~ $return = "!empty($return)";
						}
					}
				}
				if ($tag == "TMPL_VAR") {
					$return = "$match[1]<?php echo $return; ?>$match[4]"; // preserve spaces in the beginning and the end and \n at end
				} elseif ($tag == "TMPL_IF" || $tag == "TMPL_ELSEIF") {
					$return = "<?php " . ($tag == "TMPL_ELSEIF" ? "} else" : "") . "if ($return) { ?>";
				} elseif ($tag == "TMPL_UNLESS" || $tag == "TMPL_ELSEUNLESS") {
					$return = "<?php " . ($tag == "TMPL_ELSEUNLESS" ? "} else" : "") . "if (!($return)) { ?>";
				} else {
					trigger_error("Unknown tag $tag", E_USER_WARNING);
					return $match[0];
				}
			}
		}
		return "$return$match[4]";
	}
	
	// translation of language tags, callback function of preg_replace_callback
	function compile_lang($match) {
		$backslashes = substr($match[1], 0, floor(strlen($match[1]) / 2));
		if (strlen($match[1]) % 2 == 1) { // escaped [[
			return $backslashes . "[[$match[2]]]$match[3]";
		}
		$return = "$this->lang_function('" . addcslashes($match[2], "\\'") . "'" . (trim($match[3]) ? ", false" : "") . ")";
		return $backslashes . ($this->lang_function ? "<?php echo " . ($match[3] == "'" ? "addslashes($return)" : $return) . "; ?>" : $match[2]) . $match[3] . (!trim($match[3]) ? $match[3] : "");
	}
	
	function compile_comment($match) {
		return "<?php /* " . str_replace("*/", "* /", $match[1]) . " */ ?>";
	}
	
	function compile($filename) {
		$cache_filename = $this->cache_dir . $filename . ".php";
		if (!file_exists($cache_filename) || filemtime($this->tmpl_dir . $filename) > filemtime($cache_filename)) {
			$file = file_get_contents($this->tmpl_dir . $filename);
			$file = preg_replace('~<\\?~', "<?php echo '\\0'; ?>", $file);
			$file = preg_replace_callback("~[ \t]*### (.*)~", array($this, 'compile_comment'), $file);
			$file = preg_replace_callback("~(\\\\*)\\[\\[(.*?)\\]\\]((?:[\"']|(?: -)?</option>|\r?\n)?)~s", array($this, 'compile_lang'), $file);
			$file = preg_replace_callback("~(^[ \t]+)?<(?:!-- )?(/?TMPL_[^\\s>]*)([^>]*)>(\r?\n?)~im", array($this, 'compile_tag'), $file);
			$dirname = dirname($cache_filename);
			if (!is_dir($dirname)) {
				@mkdir($dirname, 0770, true);
				chmod($dirname, 0770);
			}
			$tempnam = tempnam($this->cache_dir, "tmp"); // writing to $cache_filename would cause inclusion of empty file before end of writing
			$fp = fopen($tempnam, "wb");
			fwrite($fp, $file);
			fclose($fp);
			copy($tempnam, $cache_filename); // unlink() + rename() would cause absence of the file for a moment
			unlink($tempnam);
		}
		return $cache_filename;
	}
	
	function read($filename, $vars, $globals = null) {
		if (!isset($globals)) {
			$globals = $vars;
		}
		include $this->compile($filename);
	}
}
