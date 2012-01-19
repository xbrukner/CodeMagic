<?
class lexer {
	private $lines;
	private $addedError;
	private $string;
	private $offsets = false;
	const types = " |-*";
	function __construct($lines, $string) {
		foreach (explode("\n", $lines) as $i=>$line) {
			$this->addedError = " on line ".$i;
			$this->lines[] = $this->parseLine($line);
		}
		$this->string = $string;
	}
	function error($cond, $text) {
		if ($cond === false) {
			echo $text.$this->addedError;
			exit;
		}
	}
	function parseLine($line) {
		$result = array();
		$trimmed = ltrim($line, "abcdefghijklmnopqrstuvwxyz");
		$this->error(strlen($trimmed), "Only token name");
		$result[] = substr($line, 0, -strlen($trimmed)); //Name of token
		$result[] = $trimmed[0];
		$this->error(strpos(self::types, $trimmed[0]), "Invalid type of token recognition - ".$trimmed[0]);
		$result[] = (string) substr($trimmed, 1);
		return $result;
	}
	function tokenize($offset) {
		if (!$this->offset) {
			$this->offset = array();
			foreach ($this->lines as $value) {
				$this->offset[] = $this->findNext($value, $offset);
			}
		}
		else {
			foreach ($this->offset as $key=>&$value) {
				if ($value < $offset) {
					$value = $this->findNext($this->lines[$key], $offset);
				}
			}
		}
	}
	function beginTokens($offset) {
		$this->offsets = array();
		foreach ($this->lines as $value) {
			$this->offsets[] = $this->findNext($value, $offset);
		}
	}
	
	function token($offset) {
		foreach ($this->offsets as $key=>&$value) {
			if (is_array($value)) {
				if (in_array($offset, $value, true)) {
					return array($this->lines[$key][0], $this->returnString($this->lines[$key], $offset));
				}
				if (min($value) < $offset) {
					$value = $this->findNext($this->lines[$key], $offset);
				}
				if (in_array($offset, $value, true)) {
					return array($this->lines[$key][0], $this->returnString($this->lines[$key], $offset));
				}				
			}
			else {
				if ($value < $offset) {
					$value = $this->findNext($this->lines[$key], $offset);
				}
				if ($value === $offset) {
					return array($this->lines[$key][0], $this->returnString($this->lines[$key], $offset));
				}				
			}
		}
	}
	
	static function unescape($string, $not="") {
		if (!$string) return '';
		$special = array('n' => "\n", 't' => "\t");
		$splitted = explode('\\', $string);
		return $splitted[0].implode(array_map(function ($str) use ($special, $not) {
			if ($str) {
				if (strpos($not, $str[0]) !== FALSE) {
					return '\\'.$str;
				}
				else {
					if (array_key_exists($str[0], $special)) return $special[$str[0]].substr($str, 1);
					else return $str;
				}
			}
			else {
				return '\\';
			}
		}, array_slice($splitted, 1)));
	}
	
	static function explode($separator, $string) {
		$result = array();
		$tmp = '';
		foreach (explode($separator, $string) as $value) {
			if ((strlen($value) - strlen(rtrim($value, '\\'))) % 2) {
				$tmp .= $value.$separator;
				continue;
			}
			else {
				$result []= $tmp.$value;
				$tmp = '';
			}
		}
		return $result;
	}
		
	function findNext($line, $offset) {
		switch ($line[1]) {
			case ' ':
				return strpos($this->string, self::unescape($line[2]), $offset);
			case '*': 
				return $offset;
			case '|':
				$string = &$this->string;
				return array_map(function ($char) use ($string, $offset) { return strpos($string, lexer::unescape($char), $offset); }, self::explode('|', $line[2]));
			case '-':
				$string = &$this->string;
				return array_map(function ($char) use ($string, $offset) { return strpos($string, $char, $offset); }, array_map('chr', range(ord($line[2][0]), ord($line[2][1]))) );				
		}
	}
	
	function returnString($line, $offset) {
		switch ($line[1]) {
			case ' ':
				return self::unescape($line[2]);
			case '*':
				return $this->string[$offset];
			case '|':				
				foreach (self::explode('|', $line[2]) as $char) {
					$c = self::unescape($char);
					if (substr($this->string, $offset, strlen($c)) == $c) {
						return $c;
					}
				}
			case '-':
				return $this->string[$offset];
		}
	}
}
$data = <<<'EOF'
w| |\t|\n
eq =
sc ;
lp (
rp )
bs \\
cm ,
d $
big-AZ
small-az
rest*
EOF;
$helloworld = <<<'EOF'
echo(String(Hello World!)); comment
data;
EOF;
$a = new lexer($data, $helloworld);
$a->beginTokens(0);
$token = 0;
for ($i = 4; $i < strlen($helloworld); $i += strlen($token[1])) {
	$token = $a->token($i);
	var_dump($token, $i);
}
//var_dump(array_map('chr', range(ord('a'), ord('z'))));
?>
