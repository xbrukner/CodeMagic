<?php
class parser {
	protected $rules;
	private $read;
	protected $input;
	protected $addedError;
	protected $neededTokens;
	protected $state = null;
	protected $loaded = null;
	
	function __construct($input) {
		$this->input = $input;
		$this->read = 0;
		$this->rules = array();
		while ($newName = $this->newRule()) {
			if (array_key_exists($newName, $this->rules)) {
				$this->addedError = '';
				$this->error("Rule '$newName' is already defined (".count($this->rules).". rule)");
			}
			$expansion = $this->findExpansions($newName);
			$this->rules[$newName] = $expansion;
		}
		//var_dump($this->rules);
		$this->getNeededTokens();
		//var_dump($this->neededTokens);
		//foreach ($this->rules as $key=>$value) {
		//	var_dump($key, $this->getFinalTokens($value, $key));
		//}
		//var_dump($this->getFinalTokens(array_pop($this->rules)));
		$t = $this->getFinalTokens($this->rules['typename'], 'typename');
		//var_dump($t);
		var_dump($this->getNext($t[1][0]));
		$this->checkOneAmbiguity($this->getNext($t[1][0]));
	}
	
	protected function error($message) {
		echo $message.$this->addedError;
		exit;
	}
	
	protected function findNext($what, $wrong = NULL) {
		if (is_string($what)) $what = array($what);
		$input = $this->input;
		$read = $this->read;
		$positions = array_map(function ($find) use ($input, $read) { return strpos($input, $find, $read); }, $what);
		$filtered = array_filter($positions, function ($i) { return $i !== false; });
		if (!$filtered) return false; //If no string is find -> end
		$minVal = min($filtered);
		
		if ($wrong) {
			if (is_string($wrong)) $wrong = array($wrong);
			$wrongFound = array_map(function($find) use ($input, $read, $minVal) 
				{ return ($pos = strpos($input, $find, $read)) ? $pos < $minVal : false; }, $wrong);
			if (array_filter($wrongFound)) {
				$this->error("Invalid format, found ".$wrong[array_search(true, $wrongFound)]." instead of ".implode(", ", $what));
			}
		}
		return array($what[array_pop(array_keys($positions, $minVal))], $minVal); //What is found, position
	}
	
	private function newRule() {
		$this->addedError = " when searching for ".(count($this->rules) + 1).". rule".
			(count($this->rules) ? " (after ".array_pop(array_reverse(array_keys($this->rules))).")": '').".";
		$found = self::findNext('=', str_split("|;"));
		if (!$found) return false;
		$name = trim(substr($this->input, $this->read, $found[1] - $this->read));
		if (array_filter(array_map(
			function ($space) use ($name) { return strpos($name, $space); }, str_split(" \t\n")))) {
			$this->error("Invalid format, name '$name' has whitespace in it");
		}
		$this->read = $found[1] + 1; //+1 for =
		return $name;
	}
	
	private function findExpansions($name) {
		$ret = array();
		for ($new = array(true); $new[0]; $ret[] = $new[1]) {
			$new = $this->findExpansion($name, count($ret) + 1);
		}
		return $ret;
	}
	
	private function findExpansion($name, $n) {
		$this->addedError = " when searching for $n. expansion of rule '$name'.";
		$found = self::findNext(array('|', ';'), "=");
		if (!$found) $this->error("End of file (missing ;?)");
		$data = substr($this->input, $this->read, $found[1] - $this->read);
		$data = preg_split("/[ \\t\\n]+/", $data, -1, PREG_SPLIT_NO_EMPTY);	
		$this->checkTokens($data);
		$this->read = $found[1] + 1;
		return array($found[0] == '|', $data);
	}
	
	private function checkTokens($tokens) {
		$searched = array_map(array('self', 'checkAsterisk'), $tokens);
		if (($key = array_search(false, $searched)) !== false) {
			$this->error("Token $tokens[$key] has asterisk in it");
		}
	}
	
	static function checkAsterisk($token) {
		return (($pos = strpos($token, '*')) !== false ? 
			$token[strlen($token) - 1] == '*' and $pos == strlen($token) - 1 : true);
	}
	
	static function hasAsterisk($token) {
		return $token[strlen($token) - 1] == '*';
	}
	
	static function removeAsterisk($token) {
		return rtrim($token, '*');
	}
	
	static function flatten($array) {
		$res = array();
		foreach ($array as $value) {
			if (is_array($value)) {
				$res = array_merge($res, self::flatten($value));
			}
			else {
				$res []= $value;
			}
		}
		return $res;
	}
	
	private function getNeededTokens() {
		$defined = array_keys($this->rules);
		$usedTokens = array_unique(array_map(array('self', 'removeAsterisk'), self::flatten($this->rules)));
		$this->neededTokens = array_values(array_filter($usedTokens, function ($token) use ($defined) { return !in_array($token, $defined); } ));
	}
	
	private function getFinalTokens($derivations, $from = null, $k1 = 0, $k2 = 0) {
		if ($from === null) {
			$from = array_keys($this->rules);
			$from = $from[0];
		}
		$ret = array();
		$sub = array(false, null);
		$finished = false;
		foreach ($derivations as $key=>$derivation) {
			if ($k1 > $key) continue;
			foreach ($derivation as $key2=>$value) {
				if ($k2 > $key2) continue;
				$token = self::removeAsterisk($value);
				if (in_array($token, $this->neededTokens)) {
					$ret[]= array($token, $from, $key, $key2, $value);
				}
				else {
					$sub = $this->getFinalTokens($this->rules[$token], array($token, $from, $key, $key2, $value));
					$ret = array_merge($ret, $sub[1]);
				}
				if ($token == $value and !$sub[0]) {
					continue 2;
				}
				$sub[0] = false;
			}
			$finished = true;
		}
		return array($finished, $ret);
	}
	
	private function getNext($state, $stopAtNeeded = false) {
		$ret = array();
		$thisRule = (is_array($state[1]) ? $state[1][0] : $state[1]);
		if (in_array($state[0], $this->neededTokens)) {
			if ($state[4] != $state[0]) {
				$ret []= $state;
			}
		}
		else {
			if ($state[4] != $state[0]) {
				$sub = $this->getFinalTokens($this->rules[$thisRule], $state[1], $state[2], $state[3]);
				$ret = array_merge($ret, $sub[1]);
			}
		}
		
		if (count($this->rules[$thisRule][$state[2]]) - 1 == $state[3]) {
			if (is_array($state[1])) {
				$ret = array_merge($ret, $this->getNext($state[1]));
			}
		}
		else {
			$nextRule = $this->rules[$thisRule][$state[2]][$state[3] + 1];
			$isNeeded = in_array($nextRule, $this->neededTokens);
			$sub = array(false);
			if (!self::hasAsterisk($nextRule) and !$isNeeded) {
				$sub = $this->getFinalTokens($this->rules[$nextRule], array($nextRule, $state[1], $state[2], $state[3] + 1, $nextRule));
				$ret = array_merge($ret, $sub[1]);
			}
			if (self::hasAsterisk($nextRule) or $sub[0]) {
				$ret = array_merge($ret, $this->getNext(
					array(self::removeAsterisk($nextRule), $state[1], $state[2], $state[3] + 1, $nextRule)
				));
			}
			if($isNeeded) {	
				$ret []= array(self::removeAsterisk($nextRule), $state[1], $state[2], $state[3] + 1, $nextRule);
			}
		}
		return $ret;
	}
	
	static function backtrace($trace) {
		return (is_string($trace) ? $trace : self::backtrace($trace[1])."[$trace[2]][$trace[3]]>".$trace[4]);
	}
	
	private function checkOneAmbiguity($state) {
		$keys = array();
		foreach ($state as $key=>$s) {
			if (!array_key_exists($s[0], $keys)) {
				$keys[$s[0]] = array($key);
			}
			else {
				foreach ($keys[$s[0]] as $prev) {
					if (self::backtrace($s) != self::backtrace($state[$prev])) {
						$this->error("Ambigous grammar for token $s[0] with backtraces ".self::backtrace($s)." and ".self::backtrace($state[$prev]));
					}
				}
				$keys[$s[0]] []= $key;
			}
		}
	}
}
$parse = <<<'EOF'
exp = varname ws eq ws varname ws sc
| varname ws eq ws type sc
| varname ws eq ws function sc
| function sc
;
ws = w*;
function = funcname ws lp params rp ws | funcname ws lp ws rp ws;
type = typename lp typedata rp ws;
all = eq | sc | lp | rp | bs | cm | d | big | small | rest;
typedata = ws | eq | sc | lp | bs rp | bs | cm | d | big | small | rest;
typename = typedata;
funcname = small all*;
varname = d all*;
params = ws param params2*;
params2 = cm ws param;
param = type | varname ws | function;
EOF;
//$a = array(1); $b = array(2);
//var_dump($a + $b);
$a = new parser($parse);
?>
