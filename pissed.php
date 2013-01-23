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

  function eof(){
    return feof($this->stream);
  }
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
      //return car(setcar($this->symbols[$symbol->symbol_name], $value));
      $this->symbols[$symbol->symbol_name] = cons($value);
      return $this->deref($symbol);
    }
    else{
      $this->symbols[$symbol->symbol_name] = cons($value);
      return $this->deref($symbol);
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

$GLOBALS['special_forms']  = new Context();
$GLOBALS['global_context'] = new Context();
$GLOBALS['buffered_stdin'] = new BufferedStream(fopen('php://stdin','r'));

function def_special_form($name,$function){
  //print "Name: ".m_symbol($name)->symbol_name."\n";
  //print_r($GLOBALS['special_forms']);
  return $GLOBALS['special_forms']->def(m_symbol($name), $function);
}

class Lambda{
  public $arg_list;
  public $body;

  function __construct($arg_list, $body){
    $this->arg_list = $arg_list;
    $this->body = $body;
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



function list_to_array($list){
  $array = Array();
  $cur = $list;
  while($cur){
    array_push($array, car($cur));
    $cur = cdr($cur);
  };

  return $array;
}



function m_symbol($symbol_name){
  return Symbol::symbol($symbol_name);
}



function is_a_symbol($thing){
  if(is_object($thing)
     and (get_class($thing) == "Symbol")){
    return true;
  }
  else{
    return false;
  }
}



function is_a_lambda($lambda){
  if(is_object($lambda)
     and (get_class($lambda) == "Lambda")){
    return true;
  }
  else{
    return false;
  }
}



function is_symbol($current,$desired){
  if(is_a_symbol($current)
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
    case "Lambda":
      //return "<LAMBDA>";
      return sexp_print(cons(m_symbol("lambda")
                             ,cons($form->arg_list
                                   ,$form->body)));
      break;
    default:
      return "<UNKOWN CLASS>";
    }
    break;
  default:
    return "<OTHER THING>\n";
    break;
  }
}

def_special_form('+', function ($args, $context){
    $temp = 0;
    $pointer = $args;
    while(!is_null($pointer)){
      $temp += sexp_eval(car($pointer), $context);
      $pointer = cdr($pointer);
    }
    return $temp;
  });

def_special_form('-', function ($args, $context){
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
  });

def_special_form('cons', function ($args, $context){
    $car = sexp_eval(car($args), $context);
    $cdr = sexp_eval(car(cdr($args)), $context);
    return cons($car, $cdr);
  });

def_special_form('car', function($args, $context){
    $car = sexp_eval(car($args), $context);
    return car($car);
  });

def_special_form('car', function($arg, $context){
    $car = sexp_eval(car($args), $context);
    return cdr($car);
  });

def_special_form('quote', function ($args, $context){
    return $args;
  });

def_special_form('list', function ($args, $context){
    $car = car($args);
    $cdr = cdr($args);
    return cons(sexp_eval($car, $context),
                (($cdr === null) ? null : pissed_list($cdr, $context)));
  });

def_special_form('def', function ($args, $context){
    $symbol = car($args);
    $value = sexp_eval(car(cdr($args)), $context);
    $context->def($symbol, $value);
    return $value;
  });

def_special_form('exit', function ($args, $context){
    exit(car($args));
  });

def_special_form('do', function ($args, $context){
    $output = null;
    $current = $args;
    while(!is_null($current)){
      $output = sexp_eval(car($current), $context);
      $current = cdr($current);
    }

    return $output;
  });

def_special_form('let', function ($args, $context){
    $sub_context = new Context($context);
    $sym_defs = car($args);

    while($sym_defs){
      $var_def = car($sym_defs);
      $sub_context->def(car($var_def), car(cdr($var_def)));
      $sym_defs = cdr($sym_defs);
    }

    $sub_context->immutable = true;

    $do = special_form(m_symbol('do'));
    return $do(cdr($args),$sub_context);
  });


def_special_form('lambda', function ($args, $context){
    return new Lambda(car($args), cdr($args));
  });

def_special_form('foreign', function ($args, $context){
    $fun = car($args);
    $args = list_to_array(cdr($args));
    return (call_user_func_array(__NAMESPACE__.$fun, $args));
  });

def_special_form('if', function ($args, $context){
    $case = car($args);
    $a_case = car(cdr($args));
    $b_case = car(cdr(cdr($args)));
    if(!is_null(sexp_eval($case, $context))){
      return sexp_eval($a_case,$context);
    }
    else{
      return sexp_eval($b_case,$context);
    }
  });

def_special_form('when', function ($args, $context){
    $condition = car($args);
    $action = car(cdr($args));
    if(!is_null(sexp_eval($condition, $context))){
      $do = special_form(m_symbol('do'));
      return $do($action, $context);
    }
    else{
      return null;
    }
  });

function pissed_call_lambda($lambda, $args, $context){
  $r_arg_list = $lambda->arg_list;
  $r_args = $args;
  $zipped = null;
  while($r_arg_list){
    $zipped = cons(cons(car($r_arg_list), cons(car($r_args))), $zipped);
    $r_arg_list = cdr($r_arg_list);
    $r_args = cdr($r_args);
  }

  $let = special_form(m_symbol('let'));
  return $let(cons($zipped, $lambda->body), $context);
}



function special_form($form){
  return ($GLOBALS['special_forms']->deref($form));
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
    $car = sexp_eval(car($sexp), $context);
    $cdr = cdr($sexp);
    if(is_a_symbol($car) and special_form($car)){
      $special = special_form($car);
      return $special($cdr,$context);
    }
    elseif(is_a_lambda($car)){
      return pissed_call_lambda($car,$cdr,$context);
    }
    else{
      return "<INVALID FUNCTION>";
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
      return $sexp;
      break;
    }
    break;
  default:
    return $sexp;
    break;
  }
}

function repl($input = false, $context = false){
  $input = $input ? $input : $GLOBALS['buffered_stdin'];
  $context = $context ? $context : $GLOBALS['global_context'];

  while(true){
    print "> ";
    $sexp = sexp_read($input);
    $result = sexp_eval($sexp,$context);
    print sexp_print($result)."\n";
  }
}

function load_file($path, $context = false){
  $input = new BufferedStream(fopen($path,'r'));
  $context = $context ? $context : $GLOBALS['global_context'];

  peel_whitespace($input);

  while(!$input->eof()){
    $sexp = sexp_read($input);
    peel_whitespace($input);
    $result = sexp_eval($sexp,$context);
    //print sexp_print($result)."\n";
  }
}

load_file('./pissed.lisp');
repl();

?>
