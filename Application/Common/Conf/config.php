<?php
    //数据库配置
    $mysql = include("config_mysql.php");


    //品牌相关配置
    $brand = include("config_brand.php");

    //可以添加其他的配置项
    $config = array();

    return array_merge($config,$mysql,$brand);
