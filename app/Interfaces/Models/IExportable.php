<?php

namespace App\Interfaces\Models;

interface IExportable
{
        public static function exportViewName():string ; 
        public static function getFileName():string;
}