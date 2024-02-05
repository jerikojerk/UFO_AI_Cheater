<?php


class A {

	public $x=0;
}


$a =new A();

$b = $a;
$c = &$a;
$a->x=1;
$a = new A();

var_dump( $a );
var_dump( $b) ;
var_dump($c);
