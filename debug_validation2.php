<?php

$output = 'Random text without oha indicators';

$patterns = [
    '/^oha\s+v\d/i',  
    '/Requests\/sec:\s*\d/i',  
    '/Reqs\/sec\s+\d/i',       
    '/Success rate:\s*\d/i',   
    '/Total:\s*\d+\s+requests/i'  
];

foreach ($patterns as $i => $pattern) {
    if (preg_match($pattern, $output)) {
        echo "Pattern $i matched: $pattern\n";
    } else {
        echo "Pattern $i did not match: $pattern\n";
    }
}