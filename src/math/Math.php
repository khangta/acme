<?php

namespace Acme\Math;

class Math
{
    public function sum($num1, $num2) {
        return $num1 + $num2;
    }
    
    public function subtract($num1, $num2) {
        return $num1 - $num2;
    }
    
    public function multiply($num1, $num2) {
        return $num1 * $num2;
    }
    
    public function divide($num1, $num2) {
        if ($num2 == 0) {
            throw new Exception('Divide by zero');
        } else {
            return $num1 / $num2;
        }
    }
}

