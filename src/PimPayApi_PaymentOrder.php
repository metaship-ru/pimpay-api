<?php

namespace PimPayRu\PimPayApi;

class PimPayApi_PaymentOrder {
    public $num;
    public $date;
    public $sum;

    public function __construct($num, $date, $sum)
    {
        $this->num  = $num;
        $this->date = $date;
        $this->sum  = $sum;
    }
}