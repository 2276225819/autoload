<?php
set_time_limit(100);
ini_set('display_errors','On');  

if(isset($_GET['installed'])){ 
	//确认初始化完成不再继续安装
	file_put_contents(__DIR__.'/autoload.lock',''); 
	echo "<script>location='/'</script>";
	exit;
} 
elseif(isset($_GET['n'])){  
	echo json_encode( vendor($_GET['n']) );
	exit;
} 
elseif(isset($_GET['require'])){
	if(!empty($_GET['require'])) $_GET['require'] = __DIR__.'/'.$_GET['require'].'/';
	if(!file_exists($_GET['require'].'composer.json'))die("0");
	$data = json_decode( file_get_contents( $_GET['require'].'composer.json'),true );
	
	foreach ($data['require'] as $key => $value) { 
		if(!strstr($key,'/'))  unset($data['require'][$key]) ;    
	}	 
	echo json_encode( $data['require'] );
	exit;
} 
elseif(!file_exists(__DIR__.'/autoload.lock')){ 
	//安装未完成继续安装
	?> 
	<style>
		@keyframes f{
			0%{background-position-y: 0px; } 
			100%{background-position-y:36px;}
		}
		body{ line-height:32px;}
		.list{padding-left:20px;}
		input{    margin: 18px 0;    padding: 12px 0;    width: 100%;}
		i{ background: linear-gradient(0, #ccf, #fff); box-shadow: 1px 2px 3px #ccc; padding: 10px; animation:f 2s infinite linear;}
		i.act {animation:none; background:#ccf; border: 1px solid #000;}
		i.err{animation:none;background:#ddd;  }
	</style>
	<div id="root"></div>
	<input type="button" value="安装完成" onclick="location='?installed'">
	<script src="http://cdn.bootcss.com/jquery/3.0.0-beta1/jquery.min.js"></script> 
	<script>
		window.loaded = [];
		(function T(url,id){ 
			$.get("?require="+url,function(d){
				if(d.length)d=Function("return "+d)(); 
				for(var k in d){
					if(loaded[k])continue; loaded[k]=true;  
					var nid = 'r'+ Math.random().toString().substr(2);
					var $pb = $('<div><div class="list" id="'+nid+'"></div></div>').appendTo(id);  
					var $i = $('<i>[<span>正在下载</span>]'+k+' '+d[k] +'</i>').prependTo($pb);
					var $s = $i.find('span');
					with({$i:$i,$s:$s,$pb:$pb,k:k})$.get("?n="+k,function CB(d){   
						$pb.unbind();
						try{
							$i.addClass('act'); 
							$s.html('下载完成');
							if(d.length)d=Function("return "+d)(); 
							if(d['name'])T(d['name'],'#'+nid); 
						}catch(e){
							$i.addClass('err');
							$s.html('下载失败,点击重新下载');
							$pb.click(function(){
								$i.removeClass('err').removeClass('act');
								$.get("?n="+k,CB);
							});
							console.log([e,d]);
						}
					}); 
				}
			}); 
		})('','#root');
	</script>
	<?php
	exit;
} 
 
elseif(true){  
	//自动加载
	spl_autoload_register(function($name){  
		$cache = &autoload_cache();
		if( isset($cache[$name]) && file_exists($cache[$name]) )
			return (require_once $cache[$name]) && true;
		autoload_find($name); 
	});   
}


 

function cget($src){
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL, $src); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);  
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
	return curl_exec($ch);
}

function download($src,$name){  
	if(file_exists($name))return;
	
	if ( !is_dir($dir = dirname($name) ) )  
		if (!@mkdir($dir, 777, true));  
 
	file_put_contents($name,$c = cget($src)); 
	if(strlen($c)<256){ 
		unlink($name);
		die("\n<br>NOT FOUND ".$src);
	} 
}
function source($name,$ver){  
	$data = cget('https://packagist.org/packages/'.$name); 
	preg_match('`<a href="(https://github.com[^\."]+)`',$data,$arr);      
	$data = cget($arr[1]); 
	$info = parse_url($arr[1]);  
	
	//return 'https://github.com/'.$info['path'].'/archive/master.zip';
	$v = 'master';//preg_replace('/[^0-9.]/','',$ver);//'5.2';
	return 'https://codeload.github.com'.$info['path'].'/zip/'.$v ; 
} 
function zip($file,$temp){ 
	$zip = new ZipArchive; 
	if( ($res=$zip->open( $file )) !== true){ 
		print_r($zip);
		die(   "\n<br>failed :" . $file  ); 
	}
	
	$subdir = $zip->getNameIndex(0);  
	for ($i = 1; $i < $zip->numFiles; $i++)
	{
		$filename = $zip->getNameIndex($i); 
        if (substr($filename, 0, mb_strlen($subdir, "UTF-8")) != $subdir)
			continue;
		if(substr($filename,-1)=='/')
			continue;
		$file = substr($filename, mb_strlen($subdir, "UTF-8")-1); 
		$dir = dirname($temp.$file); 
		  
		if ( !is_dir($dir) )  
			if (!@mkdir($dir, 777, true));  
			
		 file_put_contents($temp.$file ,$zip->getFromIndex($i))  ;
			 
	}
	
	$zip->close();  
}

function vendor($name,$ver=''){ 
	//$base = dirname($_SERVER['SCRIPT_FILENAME']).'/';  
	$dir =  __DIR__.'/'.$name .'/';
	$zip = $dir .'zip.zip';//已经下载
	$json = $dir .'composer.zip';//已经解压 
	$src = 'https://packagist.org/packages/'.$name;
	if(!file_exists($zip)){
 		$src = source($name,$ver); 
		$src && download($src , $zip );  
	}
	if(!file_exists($json)){ 
		zip($zip, $dir ); 
	}
	return array('name'=>$name,'src'=>@$src);
}





// 缓存函数
function &autoload_cache($key=null,$val=null ){
	$savepath = __DIR__.'/autoload.lock';
	static $cache;
	if(!$cache && file_exists($savepath) )
		$cache = ( include $savepath )?:array();
	if($val && $key){
		//print_r($savepath);
		$cache[$key]=$val;
		//@mkdir(dirname($savepath),777,true);
		file_put_contents( $savepath, '<?php return '.var_export((array)$cache,true).';//'.date('Y-m-d H:i:s') );
	}
	return $cache;
}

function autoload_find($name=''){ 
	static $psr; 
	static $files; 
	if(empty($psr)){   
		$js = glob(__DIR__.'/*/*/composer.json'); 
		$js[] =  'composer.json';
		foreach ($js as $v) {
			$data = json_decode( file_get_contents($v),true );  
			if(isset($data['autoload']['psr-4'])){
				foreach ($data['autoload']['psr-4'] as  $key=>$value) {
					if(isset($psr[$key]))die(print_r($psr[$key],true));
					$psr[$key ]=array('key'=>$key,'type'=>'psr-4','src'=>$value,'vendor'=>$v);
				} 
			} 
			if(isset($data['autoload']['psr-0'])){
				foreach ($data['autoload']['psr-0'] as  $key=>$value) {
					if(isset($psr[$key]))die(print_r($psr[$key],true));
					$psr[$key ]=array('key'=>$key,'type'=>'psr-0','src'=>$value,'vendor'=>$v);
				} 
			} 
			if(isset($data['autoload']['files'])){ 
				$files[$v]=array_merge(@(array)$files[$v], $data['autoload']['files'] );
			}
		}   
	} 
	foreach ($psr as $key => $value) {
		if( strstr($name,$key) and $ss = join(explode($key,$name)) ){    
			$value['src'] = preg_replace('/\/$/','',$value['src']);
			$file =  dirname($value['vendor']).'/'.$value['src'].'/'. str_replace('\\','/',$ss)  .'.php';
			if(isset($files[$value['vendor']])){ 
				foreach ($files[$value['vendor']] as $src)  
					if(file_exists($d = dirname($value['vendor']).'/'.$src)) require_once $d;
					else  die($d); 
				unset($files[$value['vendor']]);
			}
			//echo "\n".$name. '        '.$file; 
			if(file_exists($file)){
				require_once $file;  
				return true; 
			}
		}
	}  
	return false;
}
 