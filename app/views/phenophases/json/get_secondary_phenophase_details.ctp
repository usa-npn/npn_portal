<?php 
    echo 
    ($pretty) ? json_encode($sspis, JSON_PRETTY_PRINT) :
    json_encode($sspis);