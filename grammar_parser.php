<?php
class parser {
	protected $rules;
	private $read;
	protected $input;
	protected $addedError;
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
		var_dump($this->rules);
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
typename = big all*;
funcname = small all*;
varname = d all*;
params = ws param params2*;
params2 = cm ws param;
param = type | varname ws | function;
EOF;
$a = new parser($parse);
?>
