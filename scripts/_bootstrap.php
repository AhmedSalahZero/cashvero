<?php

if (! defined('STDERR')) {
    define('STDERR', fopen('php://stderr', 'w') ?: fopen('php://output', 'w'));
}
