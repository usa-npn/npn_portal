<?php 
    echo 
    ($pretty) ? json_encode($datasets, JSON_PRETTY_PRINT) :
    json_encode($datasets);