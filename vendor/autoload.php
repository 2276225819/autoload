<?php
namespace ___; 
//todo list: 
//版本匹配还是有问题（ 版本号的~和^有什么区别？ 
//动态更新的文件缓存（用多少存多少不要一次查找全部目录
//可选的自动更新加载文件缓存（原版需要手动 composer update

class Autoload { 
	public $config = '{
		"require":[],
		"require-dev":[],
		"autoload":[],
		"autoload-dev":[],
		"repositories":[ '.
		// '{"type": "composer", "url":"https://packagist.phpcomposer.com/"},'. 
		 '{"type": "composer", "url":"https://packagist.org/"} 
		] 
	}';
	public function __construct($package=''){ 
		$this->vendor = $package;
		$this->data = json_decode($this->config,true) ;  
 
		$req = __DIR__.'/../';  
		if( $package ) {
			if(file_exists($req.'composer.json')){ 
				$local_data = json_decode( file_get_contents( $req.'composer.json'),true ); 
				foreach (array('repositories') as $root)  //root
					if(isset($local_data[$root]))
						$this->data[$root] = array_merge_recursive( $local_data[$root],$this->data[$root]  );   
			}
			$req = __DIR__.'/'.$package.'/'; 
		}      
 
		if( file_exists($req.'composer.json')) {
			$data = json_decode( file_get_contents( $req.'composer.json'),true );
			$this->data = array_merge_recursive($this->data,$data); 
		}       
	}
	public function getComposerItem($vendor,$base_url){ 
		$data = json_decode( $this->cget($base_url.'packages.json') ,true ) ;   
		if(count($data['provider-includes']) < 2){
			$key = 'all_'.$base_url;
			if($all = $this->cache($key));else{ echo "E";
				foreach ($data['provider-includes'] as $url => $sha);//last    
				$all = json_decode( $this->cget($base_url.str_replace('%hash%',end($sha),$url) ) ,true ) ;  
				$this->cache($key,$all);
			} 
			if(empty($all['providers'][$vendor]))return;
			$hash = end($all['providers'][$vendor]);  
			$providers_url  = str_replace(array('%package%','%hash%'),array($vendor, $hash ),$data['providers-url']);
			$providers = json_decode( $this->cget($base_url. $providers_url ) ,true ) ;   
			ksort($providers['packages']);
		} else { 
			$providers_url  = "/p/".($vendor).'.json';
			$providers = json_decode( $this->cget($base_url. $providers_url ) ,true ) ;     
		}  

		$arr = ($providers['packages'][$vendor]);  
		if(empty($arr))return array();
		uksort($arr,function($a,$b){
			return $this->version_compare($a,$b); 
		});  
		return $arr;  
	}
	public function getPackageItem($vendor,$pk){ 
		if($vendor!=$pk['name'] || empty($pk['version']) )return array();
		return array( $pk['version']=>$pk );
	}

	
	public function getTagList($vendor){
		$arr = array(); 
		foreach ($this->data['repositories'] as $value) { 
			switch ($value['type']) {
				case 'composer':$packages = $this->getComposerItem($vendor,$value['url']);  break;
				case 'package':$packages = $this->getPackageItem($vendor,$value['package']);  break; 
				default: $packages=array();break;
			}
			if($packages)  break; 
		} 

		
		if($packages)foreach ($packages as $key => $value) 
			$arr[$key] = $value['dist']['url']; 
		return $arr;
	} 
	public function getRequire(){  
		$data = $this->data;
		if(empty($data['require']) ){ 
			return array();
		} 
 		//append require-dev:*
		if(empty($this->vendor) && !empty($data['require-dev']) ){ 
			$data['require'] +=$data['require-dev'];  
		}
		//remove require:php
		foreach ($data['require'] as $key => $value) { 
			if(!strstr($key,'/'))  unset($data['require'][$key]) ;    
		}  
		return $data['require'];
	}
	public function loadVendor($req, $v ){   
		$vendor = $req;
		$vers = $this->getTagList($req);  
 		$ver = $this->version_match(array_keys($vers),$v); 
 		//print_r($vers);exit;

		foreach ($vers as $key => $value) {
			$out[]= $key.' '.$value ;
		}
		if(empty($vers[$ver]))
			return array(  'msg'=>'package not found', 'match'=>$v, 'vers'=>$vers, 'result'=>$ver  );

		$src = $vers[$ver]; 
		$dir = __DIR__.'/'.$vendor;
		$name = $dir.'/'.basename($src);
		$json = $dir.'/composer.json';//解压 

		
		if(file_exists($json)){ 
			if(file_exists($name)) return array(
				'msg'=>'已经完成',
				'name'=>$vendor,
				'ver'=>$ver , 
				//'v'=>$v, "vs"=>$out,
			); 
			$this->del($vendor); 
		} 
 

		$this->setup($vendor,$src,$name); 
		return array(
			'name'=>$vendor,
			'ver'=>$ver, 
			//'v'=>$v, "vs"=>$out,
		);
	}
	public function version_compare($a,$b,$c='<') {	 
		if($a[0]=='v')$a=substr($a,1);
		if($b[0]=='v')$b=substr($b,1);
		$aa = explode('.',$a); $bb = explode('.',$b); 
		$mysort = function($aa,$bb) use(&$mysort,$c){ 
			$va=array_shift($aa);
			$vb=array_shift($bb); 
			if(!isset($va) and !isset($vb))return 1;
			if($va==$vb)return $mysort($aa,$bb); 
			if(!is_numeric($va))$va=-1;
			if(!is_numeric($vb))$vb=-1;
			switch ($c) {
				case '<':return ($va) < ($vb);
				case '<=':return ($va) <= ($vb);
				case '>':return ($va) > ($vb);
				case '>=':return ($va) >= ($vb); 
				case '=': return ($va) == ($vb);
				default: die("version_compare error :".$c);
			}  
		}; 
		return $mysort($aa,$bb);  
	}


	public function cache($k,$v=''){
		/*/
		if(!isset($_SESSION))session_start();
		if(empty($v)) return isset($_SESSION[$k])?$_SESSION[$k]:null;
		else $_SESSION[$k]=$v;
		/*/
		static $data;
		if(empty($data))$data=json_decode(@file_get_contents(__DIR__.'/.cache')?:"{}",true);
		if(empty($v))return $data[$k];
		$data[$k]=$v; 
		file_put_contents(__DIR__.'/.cache',json_encode($data));
	}
 
	public function unzip($vendor,$file ){
		$basedir = __DIR__.'/'.$vendor.'/'; 
		$zip = new \ZipArchive; 
		if( ($res=$zip->open( $file )) !== true){  
			die(   "\n<br>解压文件失败 :" . $file  ); 
		}
		
		$subdir = $zip->getNameIndex(0);  
		for ($i = 1; $i < $zip->numFiles; $i++)
		{
			$filename = $zip->getNameIndex($i); 
			if (substr($filename, 0,  strlen($subdir )) != $subdir)
				continue;
			if(substr($filename,-1)=='/')
				continue;
			$file = substr($filename,  strlen($subdir )-1); 
			$dir = dirname($basedir.$file); 
			
			if ( !is_dir($dir) )  
				if (!@mkdir($dir, 777, true));   
				
		 
			file_put_contents($basedir.$file ,$zip->getFromIndex($i))  ; 
		} 
		$zip->close();  
		return true; 
	} 
	public function cget($src){
		if($src[0]=='/')$src="http://{$_SERVER['SERVER_NAME']}:{$_SERVER['SERVER_PORT']}{$src}";
		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_URL, $src); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0); 
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); //重定向
		return curl_exec($ch);
	} 
	public function version_match($list,$ver){  
		$ors = (array)explode('|',$ver) ; 
		foreach (array_reverse($ors) as $val) {
			if(!$val)continue; 
			preg_match('/(\D*)([\d\.\*]+)/',$val,$arr);
			array_shift($arr);   
			if(empty($arr[0])){  
				foreach ($list as $value)  
					if(preg_match('/'.str_replace('\\*','[^\.]+',preg_quote($arr[1])).'$/',$value)) 
						return $value;   
			}else
			if($arr[0]=='~' or $arr[0]=='^'){
				$op = $arr[0];
				$ix = array('^'=>0,'~'=>0,); 
				$arrv = explode('.',$arr[1]);
				$arrv[ $ix[$op] ]+=1; 
				for ($i= $ix[$op] +1; $i < count($arrv); $i++) 
					$arrv[$i]=0; 
				$a = $arr[1]; 	$b = implode('.',$arrv); 
				foreach ($list as $value)  {
					$v = $value[0]=='v'? substr($value,1):$value;//v5.1.1 -> 5.1.1 
					if($this->version_compare($v,$a,'>=')  && $this->version_compare($v,$b,'<') )
						return $value;  
				}
			}else{  
				foreach ($list as $value)   {
					$v = $value[0]=='v'? substr($value,1):$value;//v5.1.1 -> 5.1.1
					if($this->version_compare($value,$arr[1], $arr[0]) )
						return $value; 
				}
			} 
		}    
	}  
	public function del($vendor){ 
		$basedir = __DIR__.'/'.$vendor; 
		$d = function($basedir)use(&$d){ 
			foreach ( array_slice(scandir($basedir ),2) as $url) {
				$url = $basedir.'/'.$url;
				if(is_dir($url))$d($url);
				else unlink($url );
				// echo "<br>".$url;
			}  
			rmdir($basedir);
		};
		$d($basedir);
		sleep(1);
	} 
	public function setup($vendor,$src,$name){  
		if ( !is_dir($dir = dirname($name) ) )  
			if (!@mkdir($dir, 777, true));  

		$c = $this->cget($src);	 
		if(strlen($c)<300){  
			die("CURL NOT FOUND ".$src."   \n<br>" . ($c));
		}  
		file_put_contents($name,$c); 
		$this->unzip($vendor,$name);
	}



		 
	public function install_psr($class,$value){ 
		$this->ns['------------------------------------']=''; 
  		$value['src'] = preg_replace('/\/$/','',$value['src']);
		if($value['type']=='psr-0'){ 
		} 
		foreach ($value['src'] as $src) { 
			$src = dirname($value['vendor'])."/$src";  
			$this->install_classmap($src,array($class,$value['src'],$value['type']));
		} 
	} 
	public function _install_classmap($src=NULL,$cc=''){  
		if(strstr($src,'.php') || strstr($src,'.inc') ){
			$data = file_get_contents($src); 
			if(preg_match('/(class|interface|trait)\s+([\w_]+)[\s\w\\\, ]*{/',$data,$arrC)) { 
				list($_,$_,$cn) = $arrC;  
				$class = $cn;
				if(preg_match('/namespace\s+([\w\\\_]+);/',$data,$arrN)){
					$class = $arrN[1].'\\'.$cn;			
				}  
  				$this->ns[$class] = $src;    
				if($class != $cc.$cn ){
					$this->ns[$cc.$cn] = $src;  
					//print_r([$class,$cc.' '.$cn,$src]);   
				} 
			}
			else{ 
				//echo $src."\n"; 
				//array_unshift($this->in,$src); 
			} 
		}else{
			foreach( glob($src.'/*') as $path){
				if($c2 = $cc){
					if( is_dir($path)) $c2.= basename( $path )  ;
					if( substr($c2,-1,1) !='\\') $c2.='\\'; 
				}
				$this->install_classmap($path,$c2);    
			}
		}   
	}/**/

	public function install_classmap($path,$cc=''){ 
		foreach ((array)$path as $src) { 
			if( is_dir($src) ){
				if(substr($src,-1)!='/') $src.='/';//
				foreach( glob($src.'*') as $p){ /*
					if($c2 = $cc){
						if( is_dir($path)) $c2.= basename( $path )  ;
						if( substr($c2,-1,1) !='\\') $c2.='\\'; 
					} */
					$this->install_classmap( $p ,$cc);///,$c2  );    
				}  
			}elseif(strstr($src,'.php') || strstr($src,'.inc')){
				$data = file_get_contents($src); 
				if(preg_match('/(class|interface|trait)\s+([\w_]+)[\s\w\\\, ]*{/',$data,$arrC)) { 
					list($_,$_,$cn) = $arrC;  
					$class = $cn;
					if(preg_match('/namespace\s+([\w\\\_]+);/',$data,$arrN)){
						$class = $arrN[1].'\\'.$cn;			
					}  
					$this->ns[$class] = $src;  
					$this->ns[$class.'|class']=array($cc);
					
					 /*
					if($class != $cc.$cn ){
						$this->ns[$cc.$cn] = $src;  
						//print_r([$class,$cc.' '.$cn,$src]);   
					} */
				}
				else{ 
					//echo $src."\n"; 
					//array_unshift($this->in,$src); 
				} 
			}
		} 
	}


	public function install_files($files){
		$in=array();
		foreach ($files as $src => $value) {
			foreach ($value as  $path) {
				$in[]=dirname($src).'/'.$path;
				//array_unshift($this->in,dirname($src).'/'.$path);  
			} 
		}  
		return $in;
	}   
	public static function install(){
		//确认初始化完成不再继续安装
		$js = glob(__DIR__.'/*/*/composer.json'); 
		$js[] =  __DIR__.'/../composer.json';
		$files = $vendor=array();
		foreach ($js as $v) {
			$data = json_decode( file_get_contents($v),true );   
			if(isset($data['autoload']['psr-4'])){
				foreach ($data['autoload']['psr-4'] as  $key=>$value) {
					if(isset($psr[$key]))die(print_r($psr[$key],true));
					//$files[$v]['psr'][$key ]=array( 'type'=>'psr-4','src'=>$value,'vendor'=>$v);
					$vendor[$key]=array( 'type'=>'psr-4','src'=>(array)$value,'vendor'=>$v);
				} 
			} 
			if(isset($data['autoload']['psr-0'])){
				foreach ($data['autoload']['psr-0'] as  $key=>$value) {
					if(isset($psr[$key]))die(print_r($psr[$key],true));
					//$files[$v]['psr'][$key ]=array( 'type'=>'psr-0','src'=>$value,'vendor'=>$v);
					$vendor[$key]=array( 'type'=>'psr-0','src'=>(array)$value,'vendor'=>$v);
				} 
			} 
			if(isset($data['autoload']['files'])){ 
				$files[$v] = array_merge(@(array)$files[$v] , $data['autoload']['files'] );
			} 
			if(isset($data['autoload']['classmap'])){ 
				$classmap[$v] = array_merge(@(array)$classmap[$v] , $data['autoload']['classmap'] );
			} 
		}      

		$class = get_called_class();
		$al = new $class('');  
		$al->prs  = $al->ns = array();
 
		$in = $al->install_files($files);
		foreach ($classmap as $src=>$value) 
			foreach ($value as $path)  
				$al->install_classmap( dirname($src).'/'.$path );    

			

		foreach ($vendor as $class=>$value) 
			$al->install_psr($class,$value); 
		//$al->prs=$vendor; 　
		$result = array($al->ns,$in,$al->prs);
		file_put_contents( self::$lock_file,
			"<?php return ". var_export($result,true).';' );   
 		return $result;
	}


  
	public static $lock_file= __DIR__."/autoload.lock";
	public static function bootstrap(){
		$class = get_called_class();
		if( $class::autoload() ); else{
			set_time_limit(0);// 
			if(isset($_GET['install'])){   
				$class::install();
				exit("loading...<script>location=location.pathname</script>");  
			}
			elseif(isset($_GET['package']) && isset($_GET['req']) && isset($_GET['ver']) ){    
				$al = new $class($_GET['req']); 
				if(empty($_GET['ver']))$_GET['ver']='*';
				echo json_encode( $al->loadVendor($_GET['req'], $_GET['ver']) ); exit;
			} 
			elseif(isset($_GET['package'])){   
				$al = new $class( $_GET['package'] );   
				echo json_encode( $al->getRequire( ) );  exit;
			}
			//elseif(isset($_GET['tags'])){ 
			//	echo json_encode( $al->getTagList($_GET['tags'])  ); exit;
			//} 
			else{
				$class::view(); exit;
			}   
		}
	}  
	public static function autoload(){ 
		if(file_exists(self::$lock_file)){ 
			list($ns,$in,$prs) = include self::$lock_file;    
			spl_autoload_register(function($name)use(&$ns){    
				if( !isset($ns[$name]) ) list($ns) = self::install();  
				if( !isset($ns[$name]) ){
					list($_,$err) =  debug_backtrace() ; 
					 die( "** [$name] {$err['file']}:{$err['line']}  ** " );
				}
				include_once $ns[$name];   
				//echo $name."<br> ";  
			});    
			if($in)foreach ($in as $value) { 
				include_once $value;
			}   
			return true;
		}else{
			return false;
		} 	
	}    
	public static function view(){
		
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
		button{     border: none; padding: 5px;}
		i{  box-shadow: 1px 2px 3px #ccc; padding: 10px;} 
		i.load{  background: linear-gradient(30deg, #ccf, #fff); animation:f 2s infinite linear; }
		i.load.act { background:#ccf; border: 1px solid #000;}
		i.load.err{ background:#ddd;  }
		</style>
		<div id="root"></div>
		<input type="button" value="完成" onclick="location='?install'">
		
		<!--<script src="/jquery.min.js"></script>  -->
		<script src="http://cdn.bootcss.com/jquery/3.0.0-beta1/jquery.min.js"></script>
		<script>
			window.loaded = []; 
			$(function(){
				R('','#root');   
			})
			
			function R(vendor,id){ 
				$.get("?package="+vendor,function(d){
					if(d && d.substr)d=Function("return "+d)(); 
					for(var k in d){
						if(loaded[k])continue; loaded[k]=true;  
						with({k:k})setTimeout(function(){ 
							//console.log([k,d[k]]);
							var $tb = L(vendor,k,d[k],id); //is loaded
							//setTimeout(function(){ 	T(k,$tb); },1000)
						});
					}
				}); 
			} 
			function L(base,vendor,ver,id){ 
				var nid = 'r'+ Math.random().toString().substr(2); 
				var $pb = $('<div><div class="list" id="'+nid+'">').appendTo(id);
				var $tb = $('<span>').prependTo($pb);
				var $i = $('<i class="load" title="'+ver+'" >'+vendor+' '+ver +'</i>').appendTo($tb); 
				//var $btn = $("<button>下载中</button>").prependTo($pb);
				//[<span>正在下载</span>]
				//var $s = $i.find('span');
				setTimeout(function UPD(){
					//var $b = $('<i class="" >' +ver+'</i>').appendTo($tb);
					$i.attr("class","load");  
					$.ajax({ 
						url:"?package="+base+"&req="+vendor+'&ver='+ver ,
						error:function(){
							$i.addClass('err');//$s.html('下载失败,点击重新下载'); 
							setTimeout(UPD,1000);
						},
						success:function(d){   
							try{
								if(d && d.substr)d=Function("return "+d)();   
								console.log(d); 
								//if(d.vers)d.vers.error(); 
								$i.addClass('act').html(d['name'] +' '+d['ver']);  
								if(d['name']) R(d['name'],'#'+nid);	   
							}catch(e){
								console.log([e,d]);
								$i.addClass('err');//$s.html('下载失败,点击重新下载'); 
								setTimeout(UPD,1000);
								return;
							} 
						}
						
					});
				},500);//.click();  
				
				return $tb;
			}
			function T(vendor,$tb){
				$.get("?tags",function(d){
					if(d && d.substr)d=Function("return "+d)(); 
					var $sel = $('<select></select>').appendTo($tb);
					for(var k in d){
						$sel.append("<option>"+vendor+'-'+d[k]+"</option>");
					}
				}); 
			} 
			// $(document).on('click','i.load',function(){
			// 	$(this).parent().parent().find('>div').toggle();
			// });
			 
		</script>
		<?php
		exit; 
	}

 
}


return Autoload::bootstrap();