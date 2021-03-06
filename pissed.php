<?php

//Constants


define('TOKEN_OP', '(');
define('TOKEN_CP', ')');
define('TOKEN_BACKTICK', '`');
define('TOKEN_PATTERN',  '/^(\(|\)|`|"([^"]|\\")*|[^\s\(\)\"]+)$/s');
define('TOKEN_COMPLETE', '/^(\(|\)|`|"([^"]|\\")*")$/m');
define('TOKEN_COMMENT', '/^;.*[\n\r]$/m');
define('TOKEN_INTEGER', '/^-?\d+$/');
define('TOKEN_FLOAT', '/^-?(\d+\.\d*|\d*\.\d+)$/');
define('TOKEN_STRING', '/^".*"$/');


//Globals


$GLOBALS['special_forms']  = new Context();
$GLOBALS['global_context'] = new Context();
$GLOBALS['buffered_stdin'] = new BufferedStream(fopen('php://stdin','r'));


//Classes


abstract class LispObject{
  abstract public function lisp_print();
}


abstract class Applicible extends LispObject{
  abstract public function lisp_eval($args, $context);

  public static function bind_args($arg_list, $arguments, $context, $evaled = true){
    $rem_arg_list = $arg_list;
    $rem_arguments = $arguments;
    while($rem_arg_list){
      $context->def(Cell::car($rem_arg_list),
                    (($evaled) ? sexp_eval(Cell::car($rem_arguments), $context) :
                     Cell::car($rem_arguments)));
      $rem_arg_list = Cell::cdr($rem_arg_list);
      $rem_arguments = Cell::cdr($rem_arguments);
    }
  }

  public static function execute_forms($forms, $context){
    $body_forms = $forms;
    $result = null;
    while($body_forms){
      $form = Cell::car($body_forms);
      if(is_a($form, 'Cell')){
        $form = eval_in_list($form, $context);
      }
      $result = sexp_eval($form, $context);
      $body_forms = Cell::cdr($body_forms);
    }
    return $result;
  }
}


class Cell extends LispObject{
  public $_car;
  public $_cdr;

  function __construct($car, $cdr){
    $this->_car = $car;
    $this->_cdr = $cdr;
  }

  public static function cons($car,$cdr=null){return new Cell($car, $cdr);}

  public static function car($cell){
    if(is_null($cell)){return null;}
    return $cell->_car;
  }

  public static function cdr($cell){
    if(is_null($cell)){return null;}
    return $cell->_cdr;
  }

  public static function setcar($cell,$car){$cell->_car = $car;return $cell;}
  public static function setcdr($cell,$cdr){$cell->_cdr = $cdr;return $cdr;}

  public function lisp_print($in_list = false){
    if(is_a(Cell::cdr($this), 'Cell') or is_null(Cell::cdr($this))){
      return ($in_list ? "" : "(")
        .sexp_print(Cell::car($this))
        .sexp_print(Cell::cdr($this), true)."";
    }
    else{
      return "(cons "
        .sexp_print(Cell::car($this))." "
        .sexp_print(Cell::cdr($this)).") ";
    }
  }
}


class Symbol extends LispObject{
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


  public static function is($current,$desired){
    return ((is_a($current, 'Symbol') and ($current == Symbol::symbol($desired)))) ? true : false;
  }

  public function lisp_print(){
    return $this->symbol_name." ";
  }
}


class Lambda extends Applicible{
  public $arg_list;
  public $body;
  public $context;

  function __construct($arg_list, $body, $context = null){
    $this->arg_list = $arg_list;
    $this->body = $body;
    $this->context = $context;
  }

  function lisp_eval($args, $context){
    //If a Applicible object has it's own context run with that
    $lambda_context = new Context($this->context ? $this->context->copy($context) : $context);
    Applicible::bind_args($this->arg_list, $args, $lambda_context);
    $lambda_context->immutable = true;
    return Applicible::execute_forms($this->body, $lambda_context);
  }

  public function lisp_print(){
    return sexp_print(Cell::cons(Symbol::symbol("lambda")
                                 ,Cell::cons($this->arg_list
                                             ,$this->body)));
  }
}


class Macro extends Applicible{
  public $arg_list;
  public $body;
  public $context;

  function __construct($arg_list, $body, $context = null){
    $this->arg_list = $arg_list;
    $this->body = $body;
    $this->context = $context;
  }

  function lisp_eval($args, $context){
    //If a Applicible object has it's own context run with that
    $lambda_context = new Context($this->context ? $this->context->copy($context) : $context);
    Applicible::bind_args($this->arg_list, $args, $lambda_context, false);
    $lambda_context->immutable = true;
    return sexp_eval(Applicible::execute_forms($this->body, $lambda_context), $lambda_context);
  }

  public function lisp_print(){
    return sexp_print(Cell::cons(Symbol::symbol("macro")
                                 ,Cell::cons($this->arg_list
                                             ,$this->body)));
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
    if($this->contains($symbol)){
      $this->symbols[$symbol->symbol_name] = Cell::cons($value);
      return $this->deref($symbol);
    }
    else{
      //Immutable contexts can only have existing bindings changed, not create new bindings.
      if($this->immutable){
        return $this->parent->def($symbol, $value);
      }

      $this->symbols[$symbol->symbol_name] = Cell::cons($value);
      return $this->deref($symbol);
    }
  }

  function contains($symbol){
    return array_key_exists($symbol->symbol_name, $this->symbols);
  }

  function deref($symbol){
    if($this->contains($symbol)){
      return Cell::car($this->symbols[$symbol->symbol_name]);
    }
    elseif($this->parent){
      return $this->parent->deref($symbol);
    }
    else{
      return null;
    }
  }

  function copy($parent_context){
    $new_copy = clone $this;
    $new_copy->parent = $parent_context;
    return $new_copy;
  }
}


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


//Reader section


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
    return stripcslashes(substr($token, 1,-1));
  }
  else{
    return Symbol::symbol($token);
  }
}


function read_token($bstream){
  $buffer = '';
  $first = true;
  peel_whitespace($bstream);
  do{
    $buffer .= $bstream->getc();
    if(preg_match(TOKEN_COMPLETE, $buffer)){
      return resolve_primative($buffer);
    }
  } while(is_token($buffer));

  $bstream->putc(substr($buffer,-1));
  return resolve_primative(substr($buffer,0,-1));
}


function list_read($bstream){
  $sexp = sexp_read($bstream);
  if(Symbol::is($sexp, TOKEN_CP)){
    return NULL;
  }
  else{
    return Cell::cons($sexp, list_read($bstream));
  }
}


function sexp_read($bstream){
  $token = read_token($bstream);
  if(Symbol::is($token, TOKEN_OP)){
    $next_token = sexp_read($bstream);
    if(Symbol::is($next_token, TOKEN_CP)){
      return null;
    }
    else{
      return Cell::cons($next_token, list_read($bstream));
    }
  }
  else{
    return $token;
  }
}


//Eval Stuff


function list_to_array($list){
  $array = Array();
  $cur = $list;
  while($cur){
    array_push($array, Cell::car($cur));
    $cur = Cell::cdr($cur);
  }
  return $array;
}


function eval_in_list($list,$context){
  return (is_null($list) ? null :
          Cell::cons(sexp_eval(Cell::car($list), $context),
                     eval_in_list(Cell::cdr($list), $context)));
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
  case "object":
    switch(get_class($sexp)){
    case "Cell":
      $car = sexp_eval(Cell::car($sexp), $context);
      $cdr = Cell::cdr($sexp);
      if(is_a($car,'Symbol') and special_form($car)){
        $special = special_form($car);
        return $special($cdr,$context);
      }
      elseif(is_a($car,'Applicible')){
        return $car->lisp_eval($cdr,$context);
      }
      else{
        return "<INVALID FUNCTION>";
      }
      break;
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


//Print stuff


function sexp_print($form, $in_list=false){
  switch(gettype($form)){
  case "NULL":
    return ($in_list) ? ")" : "()";
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
  case "object":
    if(is_a($form, 'LispObject')){
      return($form->lisp_print($in_list));
    }
    else{
      return "<UNKOWN CLASS: ".get_class($form)." >";
    }
    break;
  default:
    return "<OTHER TYPE: ".typeof($form)." >\n";
    break;
  }
}


//Runtime


function repl($input = false, $context = false){
  $input = $input ? $input : $GLOBALS['buffered_stdin'];
  $context = $context ? $context : $GLOBALS['global_context'];

  while(true){
    print "\n> ";
    $sexp = sexp_read($input);
    $result = sexp_eval($sexp,$context);
    print "\n".sexp_print($result);
  }
}


function load_file($path, $context = false){
  $input = new BufferedStream(fopen($path,'r'));
  $context = $context ? $context : $GLOBALS['global_context'];
  $result = null;
  peel_whitespace($input);

  while(!$input->eof()){
    $sexp = sexp_read($input);
    peel_whitespace($input);
    $result = sexp_eval($sexp,$context);
  }

  return $result;
}


function def_special_form($name,$function){
  return $GLOBALS['special_forms']->def(Symbol::symbol($name), $function);
}


def_special_form('+', function ($args, $context){
    $temp = 0;
    $pointer = $args;
    while(!is_null($pointer)){
      $temp += sexp_eval(Cell::car($pointer), $context);
      $pointer = Cell::cdr($pointer);
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
        $temp = sexp_eval(Cell::car($pointer), $context);
        $first = false;
        if(Cell::cdr($pointer) === null){
          return -$temp;
        }
      }
      else{
        $temp -= sexp_eval(Cell::car($pointer), $context);
      }
      $pointer = Cell::cdr($pointer);
    }
    return $temp;
  });

def_special_form('cons', function ($args, $context){
    $car = sexp_eval(Cell::car($args), $context);
    $cdr = sexp_eval(Cell::car(Cell::cdr($args)), $context);
    return Cell::cons($car, $cdr);
  });

def_special_form('car', function($args, $context){
    $car = sexp_eval(Cell::car($args), $context);
    return Cell::car($car);
  });

def_special_form('cdr', function($args, $context){
    $car = sexp_eval(Cell::car($args), $context);
    return Cell::cdr($car);
  });

def_special_form('quote', function ($args, $context){
    return Cell::car($args);
  });

def_special_form('list', function ($args, $context){
    $car = Cell::car($args);
    $cdr = Cell::cdr($args);
    $list = special_form(Symbol::symbol('list'));
    return Cell::cons(sexp_eval($car, $context),
                      (($cdr === null) ? null : $list($cdr, $context)));
  });

def_special_form('def', function ($args, $context){
    $symbol = Cell::car($args);
    $value = sexp_eval(Cell::car(Cell::cdr($args)), $context);
    $context->def($symbol, $value);
    return $value;
  });

def_special_form('exit', function ($args, $context){
    exit(Cell::car($args));
  });

/*
  def_special_form('do', function ($args, $context){
  $output = null;
  $current = $args;
  while(!is_null($current)){
  $form = Cell::car($current);
  if(is_a($form, 'Cell')){
  $form = eval_in_list($form, $context);
  }
  $output = sexp_eval($form, $context);
  $current = Cell::cdr($current);
  }
  return $output;
  });

  def_special_form('let', function ($args, $context){
  $sub_context = new Context($context);
  $sym_defs = Cell::car($args);
  while($sym_defs){
  $var_def = Cell::car($sym_defs);
  $sub_context->def(Cell::car($var_def), Cell::car(Cell::cdr($var_def)));
  $sym_defs = Cell::cdr($sym_defs);
  }

  $sub_context->immutable = true;

  $do = special_form(Symbol::symbol('do'));
  return $do(Cell::cdr($args),$sub_context);
  });
*/

def_special_form('lambda', function ($args, $context){
    return new Lambda(Cell::car($args), Cell::cdr($args));
  });

def_special_form('macro', function ($args, $context){
  return new Macro(Cell::car($args), Cell::cdr($args));
  });

def_special_form('foreign', function ($args, $context){
    $fun = Cell::car($args);
    $args = Cell::cdr($args);
    $args = list_to_array(eval_in_list($args, $context));
    return (call_user_func_array(__NAMESPACE__.$fun,$args));
  });

def_special_form('foreign-object', function ($args, $context){
    $class = Cell::car($args);
    $args = list_to_array(Cell::cdr($args));
    $reflect = new ReflectionClass($class);
    return $reflect->newInstanceArgs($args);
  });

def_special_form('foreign-global', function ($args, $context){
    $name = sexp_eval(Cell::car($args), $context);
    return $GLOBALS[$name];
  });

def_special_form('foreign-print', function ($args, $context){
    $sexp = sexp_eval(Cell::car($args), $context);
    return Print "".$sexp;
  });

def_special_form('foreign-var', function($args, $context){
    $name = sexp_eval(Cell::car($args), $context);
    $value = sexp_eval(Cell::car(Cell::cdr($args)), $context);
    if(!is_null(Cell::cdr($args))){
      $$name = $value;
      return $value;
    }
    else{
      return $$name;
    }
  });

def_special_form('foreign-list', function($args, $context){
    $list = sexp_eval(Cell::car($args), $context);
    return list_to_array($list);
  });

def_special_form('foreign-deref', function($args, $context){
    $name = sexp_eval(Cell::car($args), $context);
    $key = sexp_eval(Cell::car(Cell::cdr($args)), $context);
    return $$name[$key];
  });

def_special_form('foreign-concat', function($args, $context){
    $first = sexp_eval(Cell::car($args), $context);
    $second = sexp_eval(Cell::car(Cell::cdr($args)), $context);
    return "".$first.$second;
  });

def_special_form('if', function ($args, $context){
    $case = Cell::car($args);
    $a_case = Cell::car(Cell::cdr($args));
    $b_case = Cell::car(Cell::cdr(Cell::cdr($args)));
    if(!is_null(sexp_eval($case, $context))){
      return sexp_eval($a_case,$context);
    }
    else{
      return sexp_eval($b_case,$context);
    }
  });

def_special_form('eval', function ($args, $context){
    $sexp = Cell::car($args);
    return sexp_eval($sexp, $context);
  });

def_special_form('when', function ($args, $context){
    $condition = Cell::car($args);
    $action = Cell::car(Cell::cdr($args));
    if(!is_null(sexp_eval($condition, $context))){
      $do = special_form(Symbol::symbol('do'));
      return $do($action, $context);
    }
    else{
      return null;
    }
  });

load_file('./pissed.lisp');
?>
