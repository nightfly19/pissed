<?php

define('TOKEN_PATTERN',  '/^(\(|\)|"([^"]|\\")*|[a-z]+)$/s');
define('TOKEN_COMPLETE', '/^(\(|\)|"([^"]|\\")*")$/m');

//Prefix: There can be any ammount of whitespace at the beginning of the token.
//Single char tokens: These can only be 1 char long ( )
//If the first char is a " the token lasts until the another " is found that isn't preceded by \
//Otherwise the token lasts until a whitespace character or ( or ) is found

class BufferedStream{
  var $stream;
  var $buffer;

  function __construct($stream){
    $this->stream = $stream;
    $this->buffer = Array();
  }

  function getc(){
    return ((sizeof($this->buffer) > 0) ?
            array_pop($this->buffer) : fgetc($this->stream));
  }

  function putc($char){array_unshift($this->buffer,$char);}
}

function is_token($token){
  return (preg_match(TOKEN_PATTERN, $token) and !preg_match('/[\n\r]$/',$token));
}

function peel_whitespace($bstream){
  $byte = $bstream->getc();
  if($byte === false){ return;}
  elseif(preg_match('/\s/',"$byte")){ peel_whitespace($bstream);}
  else{$bstream->putc($byte);}
}

function read_token($bstream){
  $buffer = '';
  $first = true;
  peel_whitespace($bstream);
  do{
    $buffer .= $bstream->getc();
    if(preg_match(TOKEN_COMPLETE, $buffer)){return $buffer;}
  } while(is_token($buffer));

  $bstream->putc(substr($buffer,-1));
  return substr($buffer,0,-1);
}

$input = new BufferedStream(fopen('php://stdin','r'));
print("Token: '".read_token($input)."'\n");
print("Token: '".read_token($input)."'\n");
print("Token: '".read_token($input)."'\n");
?>
