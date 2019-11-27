<?php

/**
 * Simple component for cleaning up  text that might contain
 * -character that should not be inserted into the database
 * -characters that cannot be parsed via XML and will fail SOAP validation
 *
 * Runs three seperate cleans:
 *  - uses strip tags function to remove any html
 *  - whitlists certain characters mainly to remove any invalid characters coming from MS word
 *  - replaces any double quotes with single quotes
 *
 * This is used in variety of different places within the service. Mainly needed by functions
 * returning phenophases information as they are heavily populated with text copy-pasted from
 * MS Word.
 */
class CleanTextComponent extends Object{
        
        
    public function cleanText($text){
        return str_replace("\"", "'", preg_replace("/[^:a-zA-Z0-9&'-,_\.\/()\"#\s%-\?\!]/", " ", strip_tags($text)));

    }
        
        
}


