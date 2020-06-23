<?php

namespace WpPageForPostType;

class App
{
    public function __construct()
    {
        new Settings();
        new Rewrite();
        new Template();
        new NavClasses();
        new Archive();
    }
}
