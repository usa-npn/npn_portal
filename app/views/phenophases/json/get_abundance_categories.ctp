<?php 
    echo 
    ($pretty) ? json_encode($categories, JSON_PRETTY_PRINT) :
    json_encode($categories);

