<?php  
include '../vendor/autoload.php';
 
echo Jqview::view("tpl/item.html",array(
	'data'=>array(
		'0'=>array('id'=>'1','name'=>'aa'),
		'1'=>array('id'=>'2','name'=>'bb'),
		'2'=>array('id'=>'3','name'=>'cc'),
		'3'=>array('id'=>'4','name'=>''),
	)
));

