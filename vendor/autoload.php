<?php
namespace ___;

class Basic{ 
	public $config = '{
		"require":[],
		"require-dev":[],
		"autoload":[],
		"autoload-dev":[],
		"repositories":[
			{"type": "composer", "url":"https://packagist.phpcomposer.com/"},
			{"type": "composer", "url":"https://packagist.org/"}
		] 
	}'; 	 
	public function getComposePackages($vendor){ 
		$cfg = json_decode($this->config,true);
		foreach ($cfg['repositories'] as $value) { 
			$data = json_decode( $this->cget($value['url'].'packages.json') ,true ) ;   
			if(count($data['provider-includes']) < 2){
				session_start();
				if($all = @$_SESSION['_all']);else{ 
					foreach ($data['provider-includes'] as $url => $sha);//last    
					$all = json_decode( $this->cget($value['url'].str_replace('%hash%',end($sha),$url) ) ,true ) ;  
					$_SESSION['_all'] = $all;
				} 
				if(empty($all['providers'][$vendor]))return;
				$hash = end($all['providers'][$vendor]);  
				$providers_url  = str_replace(array('%package%','%hash%'),array($vendor, $hash ),$data['providers-url']);
				$providers = json_decode( $this->cget($value['url']. $providers_url ) ,true ) ;   
				ksort($providers['packages']);
			} else { 
				$providers_url  = "/p/".($vendor).'.json';
				$providers = json_decode( $this->cget($value['url']. $providers_url ) ,true ) ;    
			}  
			$arr = end($providers['packages']);  
			uksort($arr,function($a,$b){ 
				$aa = explode('.',$a); $bb = explode('.',$b); 
				$mysort = function($aa,$bb) use(&$mysort){ 
					$va=array_shift($aa);
					$vb=array_shift($bb); 
					if($va==$vb)return $mysort($aa,$bb);
					return $va < $vb; 
				}; 
				return $mysort($aa,$bb);  
				//return -version_compare($a,$b); 
			});  
			return $arr; 
		}  
	}
	public function getTagList($vendor){
		$arr = array();
		$packages = $this->getComposePackages($vendor); 
		if($packages)foreach ($packages as $key => $value) 
			$arr[$key] = $value['dist']['url']; 
		return $arr;
	} 
	public function getRequire($req){ 
		if(!empty($req)) $req = __DIR__.'/'.$req.'/';
		else $req = __DIR__.'/../';  
		if(!file_exists($req.'composer.json'))
			die($req.'composer.json is not exists');
		$data = json_decode( file_get_contents( $req.'composer.json'),true );  
		if(empty($data['require']) ){ 
			return array();
		} 
		foreach ($data['require'] as $key => $value) { 
			if(!strstr($key,'/'))  unset($data['require'][$key]) ;    
		}	 
		if( empty($req) and !empty($data['require-dev']) ){ 
			foreach ($data['require-dev'] as $key => $value) { 
				if(!strstr($key,'/'))  unset($data['require-dev'][$key]) ;    
			}	 
			$data['require'] +=$data['require-dev']; 
		}
		return $data['require'];
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
			if (substr($filename, 0, mb_strlen($subdir, "UTF-8")) != $subdir)
				continue;
			if(substr($filename,-1)=='/')
				continue;
			$file = substr($filename, mb_strlen($subdir, "UTF-8")-1); 
			$dir = dirname($basedir.$file); 
			
			if ( !is_dir($dir) )  
				if (!@mkdir($dir, 777, true));   
				
		 
			file_put_contents($basedir.$file ,$zip->getFromIndex($i))  ; 
		} 
		$zip->close();  
		return true; 
	}
 
	public function cget($src){
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
					if(preg_match('/'.str_replace('\\*','\d+',preg_quote($arr[1])).'$/',$value)) 
						return $value;   
			}else
			if($arr[0]=='~' or $arr[0]=='^'){
				$op = $arr[0];
				$ix = array('^'=>0,'~'=>0,); //BUG为什么本体和标准不同？（这里是用本体的 
				$arrv = explode('.',$arr[1]);
				$arrv[ $ix[$op] ]+=1; 
				for ($i= $ix[$op] +1; $i < count($arrv); $i++) 
					$arrv[$i]=0; 
				$a = $arr[1]; 	$b = implode('.',$arrv); 
				foreach ($list as $value)  {
					$v = $value[0]=='v'? substr($value,1):$value;//v5.1.1 -> 5.1.1 
					if(version_compare($v,$a)>=0  && version_compare($v,$b)<0 )
						return $value;  
				}
			}else{  
				foreach ($list as $value)   {
					$v = $value[0]=='v'? substr($value,1):$value;//v5.1.1 -> 5.1.1
					if(version_compare($value,$arr[1], $arr[0]) >0)
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
	}

}

class Autoload extends Basic {
	public $lock_file= __DIR__."/composer.lock";
	public static function bootstrap(){
		$class = get_called_class();
		$al = new $class( );   
		if($al->autoload()); else{
			set_time_limit(0);//
			if(isset($_GET['install'])){   
				$al->install();   exit;
			}
			elseif(isset($_GET['l'])){   
				echo json_encode( $al->vendor($_GET['l'],$_GET['v']) ); exit;
			} 
			elseif(isset($_GET['tags'])){ 
				echo json_encode( $al->getTagList($_GET['tags'])  ); exit;
			} 
			elseif(isset($_GET['require'])){  
				echo json_encode( $al->getRequire($_GET['require']) );  exit;
			}
			else{
				$al->view(); exit;
			}   
		}
	} 
	//
	function autoload(){ 
		if(file_exists($this->lock_file)){ 
			list($ns,$in,$prs) = include $this->lock_file;    
			spl_autoload_register(function($name)use($ns){    
				
				if( isset($ns[$name]) ){
					include_once $ns[$name];   
				} else{  
				//	echo "******"; 
				}
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
	//
	function install(){
		//确认初始化完成不再继续安装
		$js = glob(__DIR__.'/*/*/composer.json'); 
		$js[] =  __DIR__.'/../composer.json';
		$this->ns = $this->in = $this->prs = array();
		$files=$vendor=array();
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
		
		$this->install_files($files);
		foreach ($classmap as $src=>$value) 
			foreach ($value as $path)  
				$this->install_classmap( dirname($src).'/'.$path );    
		foreach ($vendor as $class=>$value) 
			$this->install_psr($class,$value);
		   
		//$this->prs=$vendor; 
  
		file_put_contents( $this->lock_file,
			"<?php return ". var_export(array($this->ns,$this->in,$this->prs),true).';' );   
 
		echo "<script>location=location.pathname</script>"; 
	}
 
	function install_psr($class,$value){ 
		$this->ns['------------------------------------']=''; 
  		$value['src'] = preg_replace('/\/$/','',$value['src']);
		if($value['type']=='psr-0'){ 
		} 
		foreach ($value['src'] as $src) { 
			$src = dirname($value['vendor'])."/$src";  
			$this->install_classmap($src,$class);
		} 
	} 
	 
	function install_classmap($src=NULL,$cc=''){  
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

	 
	}
	
	function install_files($files){
		foreach ($files as $src => $value) {
			foreach ($value as  $path) {
				$this->in[]=dirname($src).'/'.$path;
				//array_unshift($this->in,dirname($src).'/'.$path);  
			} 
		}  
	}
	//	
	function vendor($vendor,$v='*'){   
		$vers = $this->getTagList($vendor); 
		$ver = $this->version_match(array_keys($vers),$v); 
		foreach ($vers as $key => $value) {
			$out[]= $key.' '.$value ;
		}
		if(empty($vers[$ver]))
			return array(  'msg'=>'package not found', 'match'=>$v, );

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
	function setup($vendor,$src,$name){  
		if ( !is_dir($dir = dirname($name) ) )  
			if (!@mkdir($dir, 777, true));  

		$c = $this->cget($src);	 
		if(strlen($c)<300){  
			die("NOT FOUND ".$src.'   \n<br>'. ($c));
		}  
		file_put_contents($name,$c); 
		$this->unzip($vendor,$name);
	}

	
	public function view(){
		
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
				$.get("?require="+vendor,function(d){
					if(d && d.substr)d=Function("return "+d)(); 
					for(var k in d){
						if(loaded[k])continue; loaded[k]=true;  
						with({k:k})setTimeout(function(){ 
							//console.log([k,d[k]]);
							var $tb = L(k,d[k],id); //is loaded
							//setTimeout(function(){ 	T(k,$tb); },1000)
						});
					}
				}); 
			} 
			function L(vendor,ver,id){ 
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
						url:"?l="+vendor+'&v='+ver ,
						error:UPD,
						success:function(d){   
							if(d && d.substr)d=Function("return "+d)(); 
							try{ 	
								console.log(d); 
								if(d.vers)d.vers.error();
								
								
								$i.addClass('act').html(d['name'] +' '+d['ver']);  
								if(d['name']) R(d['name'],'#'+nid);	 
						
							}catch(e){
								console.log([e,d]);
								$i.addClass('err');
								//$s.html('下载失败,点击重新下载'); 
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