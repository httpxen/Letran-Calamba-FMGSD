<?php

// dump something on the screen and die
function dd($value) {
  echo '<pre>';
  var_dump($value);
  echo '</pre>';

  die();
}

function filter($input) {
  $input = trim($input);                  
  $input = stripslashes($input);          
  $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
  return $input;
}
