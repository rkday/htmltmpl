<?php
/* HTMLTmpl - verze kompilující TMPL šablony do PHP kódu, poznámky:
- párová značka jde ukončit jakoukoliv /TMPL_ (např. <TMPL_IF>...</TMPL_UNLESS>) - pozor na skryté chyby
- neobejde se bez ukládání zkompilovaných souborů do cache, protože se následně includují
- rozšíření <TMPL_VAR ESCAPE="DATE|TIME|NUMBER|PRICE"> - je volána uživatelská funkce format_date|number|price, JS, TMPL_SELECT, TMPL_ELSEIF, TMPL_ELSEUNLESS
//! TMPL_LOOP_INT by se dal nahradit TMPL_LOOP + array_fill(0, $num, array()), pouze $num != 0

co mi chybí: OR v podmínkách
*/

// zpracování vložených souborů - vložené soubory nejsou kompilované, protože se mohou vyskytovat v různých hloubkách rekurze a musí dostat i $vars (kvůli global)
function htmltmpl_compile_include($filename, $recursion = array())
{
	if (isset($recursion[$filename])) {
		exit("Recursive include of $filename.\n");
	}
	$recursion[$filename] = true;
	$file = file_get_contents($filename);
	$file = preg_replace_callback('~<TMPL_INCLUDE\\s(?:NAME="?)?([^>" ]+)[^>]*>~i', create_function('$matches', 'return htmltmpl_compile_include("' . dirname($filename) . '/" . $matches[1], ' . var_export($recursion, true) . ');'), $file);
	return $file;
}

// překlad TMPL značek, callback funkce preg_replace_callback
function htmltmpl_compile_tag($matches)
{
	static $loops = array();
	static $loops_used = array();
	$tag = strtoupper($matches[2]);
	$attrs = preg_replace('~(/| --)+$~', '', $matches[3]);
	
	if ($tag == "TMPL_ELSE") {
		$return = "<?php } else { ?>";
	} elseif ($tag == "TMPL_BOUNDARY") {
		$return = "";
	} elseif ($tag == "/TMPL_IF" || $tag == "/TMPL_UNLESS") {
		$return = "<?php } ?>";
	} elseif ($tag == "/TMPL_LOOP" || $tag == "/TMPL_LOOP_INT") {
		array_pop($loops);
		$return = "<?php } ?>";
	} else { // značky obsahující atribut NAME
		$name = (preg_match('~\\sNAME=("[^"]+"|[^ ]+)~i', $attrs, $matches2) ? trim($matches2[1], '"') : preg_replace('~^\\s+([^\\s]+).*~s', '\\1', $attrs));
		/* zpracováno dříve
		if ($tag == "TMPL_INCLUDE") {
			$return = "<?php htmltmpl_compile('$name', \$vars, \$lang_function, \$cache_dir); ?>";
		} else */
		if ($tag == "TMPL_STATIC") {
			$return = "$matches[1]<?php echo constant('$name'); ?>$matches[4]";
		} elseif ($tag == "TMPL_SELECT") {
			$return = "<select name='" . (strpos($name, '$') !== false ? "<?php echo \"$name\"; ?>" : $name) . "'" . (preg_match('~\\sEXTRA=("[^"]+"|[^ ]+)~i', $attrs, $matches2) ? " " . trim($matches2[1], '"') : "") . ">";
			$return .= "<?php\nforeach (\$vars[\"$name\"] as \$key => \$val) {\n\techo '<option value=\"' . htmlspecialchars(\$key) . '\"' . (\$key == \$_REQUEST[\"$name\"] ? ' selected=\"selected\"' : '') . '>' . htmlspecialchars(\$val) . '</option>';\n}\n?>";
			$return .= "</select>";
		} elseif ($tag == "TMPL_LOOP") {
			$loop = count($loops) + 1;
			$var_name = ($loop == 1 ? "vars" : "row" . ($loop - 1));
			$loops[] = $name;
			$return = "<?php foreach (\$" . $var_name . "[\"$name\"] as \$i$loop => \$row$loop) { ?>";
		} elseif ($tag == "TMPL_LOOP_INT") {
			$loops[] = ":$name";
			$loop = count($loops);
			$return = "<?php for(\$i$loop = 0; \$i$loop < \$" . ($loop == 1 ? "vars" : "row" . ($loop - 1)) . "[\"$name\"]; \$i$loop++) { ?>";
		} else {
			$loop = count($loops);
			$count = (substr(end($loops), 0, 1) == ":" ? '$vars["' . substr(end($loops), 1) . '"]' : 'count($vars["' . end($loops) . '"])');
			switch ($name) {
				case '__FIRST__': $return = "\$i$loop == 0"; break;
				case '__LAST__': $return = "\$i$loop == $count-1"; break;
				case '__INNER__': $return = "\$i$loop != 0 && \$i$loop != $count-1"; break;
				case '__ODD__': $return = "\$i$loop % 2 == 0"; break;
				case '__PASS__': $return = "\$i$loop + 1"; break;
				case '__PASSTOTAL__': $return = $count; break;
				default: if (preg_match('~^__EVERY__([1-9][0-9]*)$~', $name, $matches2)) {
					$return = "\$i$loop % $matches2[1] == 0";
				} else {
					$global = (!$loop || preg_match('~\\sGLOBAL="?1~i', $attrs));
					$return = ($global ? "\$vars[\"$name\"]" : '$row' . $loop . "[\"$name\"]");
					/* hledání v nadřazených polích - odpovídá funkčnosti s global_vars
					for ($i = ($global ? 1 : 2); $i <= $loop; $i++) {
						$return = "(isset(\$row" . $i . "[\"$name\"]) ? \$row" . $i . "[\"$name\"] : $return)";
					}
					*/
					if ($tag == "TMPL_VAR") {
						preg_match('~\\sESCAPE="?([^\\s"]+)~i', $attrs, $matches2);
						switch (strtoupper($matches2[1])) {
							case 'NONE': $return = "(is_array($return) ? count($return) : $return)"; break;
							case 'DATE':
							case 'TIME':
							case 'NUMBER':
							case 'PRICE': $return = "format_" . strtolower($matches2[1]) . "($return)"; break;
							case 'JS': $return = 'addcslashes(' . $return . ', "\'\r\n\\\\")'; break;
							case 'URL': $return = "urlencode($return)"; break;
							case 'WAP': $return = "str_replace('\$', '\$\$', $return)"; // break left intentionally
							case 'HTML':
							default: $return = "nl2br(htmlspecialchars($return))";
						}
					} elseif (preg_match('~\\sVALUE=("[^"]+"|[^ ]+)~i', $attrs, $matches2)) {
						$return .= ' == "' . addcslashes(html_entity_decode(trim($matches2[1], '"')), '\\"') . '"';
					} else {
						//~ $return = "!empty($return)";
					}
				}
			}
			if ($tag == "TMPL_VAR") {
				$return = "$matches[1]<?php echo $return; ?>$matches[4]"; // zachovat mezery na začátku řádku a \n na konci
			} elseif ($tag == "TMPL_IF" || $tag == "TMPL_ELSEIF") {
				$return = "<?php " . ($tag == "TMPL_ELSEIF" ? "} else" : "") . "if ($return) { ?>";
			} elseif ($tag == "TMPL_UNLESS" || $tag == "TMPL_ELSEUNLESS") {
				$return = "<?php " . ($tag == "TMPL_ELSEUNLESS" ? "} else" : "") . "if (!($return)) { ?>";
			} else { // neznámá značka
				trigger_error("Unknown tag $tag", E_USER_WARNING);
				return $matches[0];
			}
		}
	}
	return "$return$matches[4]";
}

// překlad jazykových značek
function htmltmpl_compile_lang($matches)
{
	static $lang_function;
	if (is_string($matches)) {
		$lang_function = $matches;
		return;
	}
	$backslashes = substr($matches[1], 0, floor(strlen($matches[1]) / 2));
	if (strlen($matches[1]) % 2 == 1) { // escapované [[
		return $backslashes . "[[$matches[2]]]$matches[3]";
	}
	$return = "$lang_function('" . addcslashes($matches[2], "\\'") . "'" . (trim($matches[3]) ? ", false" : "") . ")";
	return $backslashes . ($lang_function ? "<?php echo " . ($matches[3] == "'" ? "addslashes($return)" : $return) . "; ?>" : $matches[2]) . $matches[3] . (!trim($matches[3]) ? $matches[3] : "");
}

// kompilace šablony $filename do PHP kódu
function htmltmpl_compile($filename, $lang_function = "", $cache_dir = "")
{
	htmltmpl_compile_lang($lang_function);
	$cache_filename = (!$cache_dir ? "./" : $cache_dir) . basename($filename) . "c"; //! basename
	if (!file_exists($cache_filename) || filemtime($filename) > filemtime($cache_filename)) {
		$file = htmltmpl_compile_include($filename);
		$file = preg_replace('~<\\?~', "<?php echo '\\0'; ?>", $file);
		$file = preg_replace_callback("~[ \t]*### (.*)~", create_function('$matches', 'return "<?php /* " . str_replace("*/", "* /", $matches[1]) . " */ ?>";'), $file);
		$file = preg_replace_callback("~(\\\\*)\\[\\[(.*?)\\]\\]([\"']|(?: -)?</option>|\r?\n)?~s", 'htmltmpl_compile_lang', $file);
		$file = preg_replace_callback("~(^[ \t]+)?<(?:!-- )?(/?TMPL_[^\\s>]*)([^>]*)>(\r?\n?)~im", 'htmltmpl_compile_tag', $file);
		$tempnam = tempnam((!$cache_dir ? "./" : $cache_dir), "tmp"); // při zápisu rovnou do $cache_filename by se před zapsáním dat vkládal prázdný soubor
		$fp = fopen($tempnam, "wb");
		fwrite($fp, $file);
		fclose($fp);
		copy($tempnam, $cache_filename); // rename() není použito proto, že soubor už mohl existovat a je potřeba, aby ani na chvíli nezmizel, takže ho nelze smazat
		unlink($tempnam);
	}
	return $cache_filename;
}

// zkompilování a vložení šablony s využitím proměnných $vars
function htmltmpl_include($filename, $vars, $lang_function = "", $cache_dir = "")
{
	// z funkce to je vloženo proto, že zkompilovaný kód používá proměnnou $vars
	include htmltmpl_compile($filename, $lang_function, $cache_dir);
}
