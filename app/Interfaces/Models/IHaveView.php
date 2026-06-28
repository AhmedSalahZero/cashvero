<?php
namespace App\Interfaces\Models ;
interface IHaveView
{
    public static function getViewVars():array;
    public static function getFileName():string ;
}