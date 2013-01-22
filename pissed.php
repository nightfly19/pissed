<?php

define('TOKEN_PATTERN', '/^(\(|\)|[^\s"]+|"([^"]|\\")*")$/');

//Prefix: There can be any ammount of whitespace at the beginning of the token.
//Single char tokens: These can only be 1 char long ( )
//If the first char is a " the token lasts until the another " is found that isn't preceded by \
//Otherwise the token lasts until a whitespace character or ( or ) is found


function is_token($token){
  return preg_match(TOKEN_PATTERN, $token);
}

function test_token($token){
  print $token.": ".(is_token($token) ? "yes" : "no")."\n";
}

foreach(Array('',
              '(',
              '( ',
              ')',
              ') ',
              'cow',
              'cow ',
              '"cow',
              '"cow"',
              '"cow"ssd',
              '"cows\" and more"',
              '"cows\" and more') as $token){
  test_token($token);
}

?>
