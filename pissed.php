<?php

define('TOKEN_OP', '(');
define('TOKEN_CP', ')');
define('TOKEN_PATTERN',  '/^(\(|\)|"([^"]|\\")*|[^\s\(\)\"]+)$/s');
define('TOKEN_COMPLETE', '/^(\(|\)|"([^"]|\\")*")$/m');
define('TOKEN_INTEGER', '/^-?\d+$/');
define('TOKEN_FLOAT', '/^-?(\d+\.\d*|\d*\.\d+)$/');
define('TOKEN_STRING', '/^".*"$/');

//Prefix: There can be any ammount of whitespace at the beginning of the token.
//Single char tokens: These can only be 1 char long ( )
//If the first char is a " the token lasts until the another " is found that isn't preceded by \
//Otherwise the token lasts until a whitespace character or ( or ) is found

function cons($car,$cdr = null){
  return Array($car,$cdr);
}

function car($list){return $list[0];}

function cdr($list){return $list[1];}

function setcar($list,$car){$list[0] = $car;return $list;}

function setcdr($list,$cdr){$list[1] = $cdr;return $list;}

class BufferedStream{
  public $stream;
  public $buffer;

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



class Symbol{
  public $symbol_name;
  public static $symbols = Array();

  function __construct($symbol){
    $this->symbol_name = $symbol;
  }

  public static function symbol($symbol_name){
    if(array_key_exists($symbol_name, self::$symbols)){
      return self::$symbols[$symbol_name];
    }
    else{
      $new_symbol = new Symbol($symbol_name);
      self::$symbols[$symbol_name] = $new_symbol;
      return $new_symbol;
    }
  }

}



class Context{
  public $parent;
  public $symbols;
  public $immutable;

  function __construct($parent = null, $immutable = false){
    $this->parent = $parent;
    $this->symbols = Array();
    $this->immutable = $immutable;
  }

  function def($symbol, $value=null){
    if($this->immutable){
      return $this->parent->def($symbol, $value);
    }

    if($this->contains($symbol)){
      return car(setcar($this->symbols[$symbol], $value));
    }
    else{
      $this->symbols[$symbol->symbol_name] = cons($value);
      return car($this->symbols[$symbol->symbol_name]);
    }
  }

  function contains($symbol){
    return array_key_exists($symbol->symbol_name, $this->symbols);
  }

  function deref($symbol){
    if($this->contains($symbol)){
      return car($this->symbols[$symbol->symbol_name]);
    }
    elseif($this->parent){
      return $this->parent->deref($symbol);
    }
    else{
      return null;
    }
  }

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
    return Symbol::symbol($token);
  }
}



function m_symbol($symbol_name){
  return Symbol::symbol($symbol_name);
}



function is_symbol($current,$desired){
  if(is_object($current)
     and (get_class($current) == "Symbol")
     and ($current == m_symbol($desired))){
    return true;
  }
  else{
    return false;
  }
}



function report_token($token){
  return resolve_primative($token);
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




function list_read($bstream){
  $sexp = sexp_read($bstream);
  if(is_symbol($sexp, TOKEN_CP)){
    return NULL;
  }
  else{
    return cons($sexp, list_read($bstream));
  }
}



function sexp_read($bstream){
  $token = read_token($bstream);
  if(is_symbol($token, TOKEN_OP)){
    $next_token = sexp_read($bstream);
    if(is_symbol($next_token, TOKEN_CP)){
      return null;
    }
    else{
      return cons($next_token, list_read($bstream));
    }
  }
  else{
    return $token;
  }
}



//Print stuff
function sexp_print($form, $in_list=false){
  switch(gettype($form)){
  case "NULL":
    if($in_list){
      return ")";
    }
    else{
      return "()";
    }
    break;
  case "integer":
    return "$form"." ";
    break;
  case "double":
    return "$form"." ";
    break;
  case "string":
    return '"'.str_replace('"','\"',$form).'"'." ";
    break;
  case "array":
    return ($in_list ? "" : "(")
      .sexp_print(car($form))
      .sexp_print(cdr($form), true)."";
    break;
  case "object":
    switch(get_class($form)){
    case "Symbol":
      return $form->symbol_name." ";
      break;
    default:
      return "<UNKOWN CLASS>";
    }
    break;
  default:
    return "Something else\n";
    break;
  }
}

function pissed_add($args, $context){
  $temp = 0;
  $pointer = $args;
  while(!is_null($pointer)){
    $temp += sexp_eval(car($pointer), $context);
    $pointer = cdr($pointer);
  }
  return $temp;
}

function pissed_sub($args, $context){
  $pointer = $args;
  $first = true;
  $temp = 0;

  if($pointer === null){
    throw new Exception("Not enough args to -");
  }
  
  while(!is_null($pointer)){
    if($first){
      $temp = sexp_eval(car($pointer), $context);
      $first = false;
      if(cdr($pointer) === null){
        return -$temp;
      }
    }
    else{
      $temp -= sexp_eval(car($pointer), $context);
    }
    $pointer = cdr($pointer);
  }
  return $temp;
}

function special_form($form, $args, $context){
  switch($form->symbol_name){
  case "special*":
    return "It's really special";
    break;
  case "+":
    return pissed_add($args, $context);
    break;
  case "-":
    return pissed_sub($args, $context);
    break;
  default:
    return "nothing at all!";
    break;
  }
}

function sexp_eval($sexp, $context){
  switch(gettype($sexp)){
  case "NULL":
    return null;
    break;
  case "integer":
    return $sexp;
    break;
  case "double":
    return $sexp;
    break;
  case "string":
    return $sexp;
    break;
  case "array":
    $car = car($sexp);
    $cdr = cdr($sexp);
    if($GLOBALS['special_forms']->contains($car)){
      return special_form($car,$cdr,$context);
    }
    else{
      return "FAIL";
    }
    break;
  case "object":
    switch(get_class($sexp)){
    case "Symbol":
      if($GLOBALS['special_forms']->contains($sexp)){
        return $sexp;
      }
      else{
        return $context->deref($sexp);
      }
      break;
    default:
      break;
    }
    break;
  default:
    break;
  }
}

$input = new BufferedStream(fopen('php://stdin','r'));
//print_r(sexp_read($input));
$context = new Context();
$GLOBALS['special_forms'] = new Context();
$context->def(m_symbol("hello"), "Something here");
$context->def(m_symbol("cow"), m_symbol("mooo"));
$context->def(m_symbol("this"), "moo");
$special_forms->def(m_symbol("+"));
$special_forms->def(m_symbol("-"));
print sexp_print(sexp_eval(sexp_read($input), $context));
//print_r($context);
//print $context->deref(m_symbol("hello"));
?>
