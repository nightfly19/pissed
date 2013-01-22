<?php

define('TOKEN_OP', '(');
define('TOKEN_CP', ')');
define('TOKEN_PATTERN',  '/^(\(|\)|"([^"]|\\")*|[^\s\(\)\"]+)$/s');
define('TOKEN_COMPLETE', '/^(\(|\)|"([^"]|\\")*")$/m');
define('TOKEN_INTEGER', '/^\d+$/');
define('TOKEN_FLOAT', '/^(\d+\.\d*|\d*\.\d+)$/');
define('TOKEN_STRING', '/^".*"$/');

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

function report_token($token){
  print "Token: ".$token."\n";
  return $token;
}

function resolve_primative($token){
  if(preg_match(TOKEN_INTEGER,$token)){
    return intval($token);
  }
  elseif(preg_match(TOKEN_FLOAT,$token)){
    return floatval($token);
  }
  elseif(preg_match(TOKEN_STRING,$token)){
    return substr($token, 1,-1);
  }
  else{
    return $token;
  }
}

function read_token($bstream){
  $buffer = '';
  $first = true;
  peel_whitespace($bstream);
  do{
    $buffer .= $bstream->getc();
    if(preg_match(TOKEN_COMPLETE, $buffer)){
      return report_token($buffer);
    }
  } while(is_token($buffer));

  $bstream->putc(substr($buffer,-1));
  return report_token(substr($buffer,0,-1));
}

function cons($car,$cdr){
  return Array($car,$cdr);
}

function read_list($bstream){
  $sexp = read_sexp($bstream);
  if($sexp == TOKEN_CP){
    return NULL;
  }
  else{
    return cons($sexp,read_list($bstream));
  }
}

function read_sexp($bstream){
  $token = read_token($bstream);
  if($token == TOKEN_OP){
    $next_token = read_sexp($bstream);
    if($next_token == TOKEN_CP){
      return null;
    }
    else{
      return cons($next_token,read_list($bstream));
    }
  }
  else{
    return resolve_primative($token);
  }
  //  print "is list: ".((TOKEN_OP == $token) ? "yes" : "no")."\n";
}

$input = new BufferedStream(fopen('php://stdin','r'));
print_r(read_sexp($input));
?>
