<?php	 
namespace _;
set_time_limit(100);
ini_set('display_errors','On');   
session_start();
 
class GithubLoader extends AutoLoader{
	
	/** 获取所有版本的下载地址
	 * 
	 */
	public function getTagList($vendor){
		list($a,$b)=explode('/',$vendor);
		$html = $this->cget('https://packagist.org/packages/'.$vendor);
		preg_match('`github.com\/([\w_\-]+\/[\w_\-]+)`',$html,$arr);
		$vendor = $arr[1]; 	
		//print_r($vendor);
		
		
		$list = array(); 
		for ($i=1; $i < 5; $i++) { // 500tages 
			$data = json_decode($this->cget("https://api.github.com/repos/".$vendor."/tags?page={$i}&per_page=100")); 
			if( !is_array($data) or count($data)==0  )break;
			$list = array_merge($list,$data); 
		}  
 
		$ver = array();
		foreach ($list as $value)  
			$ver[$value->name] = "https://codeload.github.com/".$vendor."/zip/".$value->name;// $value->zipball_url; 
		return $ver; 
	}
	
	/** 安装该文件
	 *
	 */
	public function setup($vendor,$src){
		$dir = __DIR__.'/'.$vendor;
		$name = $dir.'/zip.zip'; 
		$json = $dir .'/composer.json';//解压 
 
		//print_r([$name,$src]);
		if ( !is_dir($dir = dirname($name) ) )  
			if (!@mkdir($dir, 777, true));  
	
		file_put_contents($name,$c = $this->cget($src)); 
		if(strlen($c)<256){ 
			unlink($name);
			die("\n<br>NOT FOUND ".$src.'   '.strlen($c));
		} 
		 
		$this->unzip($vendor);
	}
	
	/** 
	 */
	public function unzip($vendor){
		$basedir = __DIR__.'/'.$vendor.'/';
		$file =  $basedir.'zip.zip';
		$zip = new \ZipArchive; 
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
			$dir = dirname($basedir.$file); 
			
			if ( !is_dir($dir) )  
				if (!@mkdir($dir, 777, true));   
				
		 
			file_put_contents($basedir.$file ,$zip->getFromIndex($i))  ; 
		} 
		$zip->close();  
		return true; 
	}
	
 
}
 
 

 
class AutoLoader{
	public $ns ;
	public $in ;
	public $prs;
	public $lock_file = __DIR__.'/autoload.lock';
	public function __construct($opt=array()){
		foreach ((array)$opt as $key => $value) {
			$this->$key = $value; 
		} 
	}
	
	
	public function version_match($list,$ver){ 
 		$list = array_reverse($list); 
		foreach ((array)explode('|',$ver) as  $val) {
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
				$ix = array('^'=>0,'~'=>1,);  
				$arrv = explode('.',$arr[1]);
				$arrv[ $ix[$op] ]+=1; 
				for ($i= $ix[$op] +1; $i < count($arrv); $i++) 
					$arrv[$i]=0; 
				$a = $arr[1]; 	$b = implode('.',$arrv);
				//print_r([$a,$b]);
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
	
	
	
	function getZipName($vendor,$subdir=''){
		$dir = __DIR__.'/'.$vendor;
		$file = $dir.'/zip.zip'; 
		$json = $dir .'/composer.json';//解压 
		
		$z = new \ZipArchive; 
		if( $z->open($file) ){
			$subdir =substr( $z->getNameIndex(0),0,-1); 
			$z->close();
			 
			$arr = explode('-',$subdir); 
			return $arr[1];
		} 
	}
	function zip($file,$temp){  
		$zip = new \ZipArchive; 
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
		return true;
	}

		
	function cget($src){
		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_URL, $src); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0); 
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
		return curl_exec($ch);
	}

	function download($src,$name){  
		if(file_exists($name))return;
		
		if ( !is_dir($dir = dirname($name) ) )  
			if (!@mkdir($dir, 777, true));  
	
		file_put_contents($name,$c = $this->cget($src)); 
		if(strlen($c)<256){ 
			unlink($name);
			die("\n<br>NOT FOUND ".$src.'   '.strlen($c));
		} 
	}
	function getSource($name,$ver){  
		return '';
	} 
	
	function vendor($vendor,$v='*'){   
		$dir = __DIR__.'/'.$vendor;
		$name = $dir.'/zip.zip';
		$json = $dir.'/composer.json';//解压 
		if(file_exists($name)){
			if(!file_exists($json))  
				$this->unzip($vendor); 
		 	return array(
				'msg'=>'已经完成',
				'name'=>$vendor,
				'ver'=>$this->getZipName($vendor)
			); 
		} 
 
		// echo '<pre>'.$name."<hr>";
		// if($vers = @$_SESSION[$vendor]);else{
		// 	$vers = $this->getTagList($vendor);
		// 	$_SESSION[$vendor]=$vers;
		// }
		$vers = $this->getTagList($vendor);
		$ver = $this->version_match(array_keys($vers),$v);
		 
		if(empty($vers[$ver]))
			return array(  'msg'=>'找不到', 'match'=>$v, 'url'=>$_SERVER['REQUEST_URI'] );
		$this->setup($vendor,$vers[$ver]);
		
		return array(
			'name'=>$vendor,
			'ver'=>$this->getZipName($vendor)
		);
		
		/*
		$dir =  __DIR__.'/'.$name .'/';
		$json = $dir .'composer.json';//解压 
		$zip = $dir .'zip.zip';//下载
		if(isset($_GET['update'])){
			if(file_exists($zip))unlink($zip); 
		} 
		if(isset($_GET['update']) or !file_exists($json)){  
			if(isset($_GET['update']) or !file_exists($zip)){
				$src = $this->getSource($name,$ver); 
				$src && $this->download($src , $zip );  
			} 
			zip($zip, $dir );  
		}
		$file = $this->getZipName($zip);
		list($_,$ver) = explode('-', $file);
	
		return array(
			'name'=>$name,
			'src'=>@$src,
			'file'=>$file, 
			'ver'=> $ver,
		);*/
	}
	
	public function getRequire($req){
		if(!empty($req)) $req = __DIR__.'/'.$req.'/';
		
		if(!file_exists($req.'composer.json'))die("0");
		$data = json_decode( file_get_contents( $req.'composer.json'),true );
		
		if(empty($data['require']) ){ 
			exit ;
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
	
	
	
	function install(){
		//确认初始化完成不再继续安装
		$js = glob(__DIR__.'/*/*/composer.json'); 
		$js[] =  __DIR__.'/../composer.json';
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
		//print_r($vendor);exit;
		
		$this->install_files($files);
		foreach ($classmap as $src=>$value) 
			foreach ($value as $path)  
				$this->install_classmap( dirname($src).'/'.$path );    
		foreach ($vendor as $class=>$value) 
			$this->install_psr($class,$value);
			
			 
		//$this->prs=$vendor; 
		 
		//file_put_contents( $file, json_encode(array($al->ns,$al->in,$al->prs)) );  
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
			$src =  dirname($value['vendor'])."/$src";  
			$this->install_classmap($src);
		}
		
	} 
	 
	function install_classmap($src=NULL){  
		if(strstr($src,'.php') || strstr($src,'.inc') ){
			$data = file_get_contents($src);
			if(preg_match('/(class|interface|trait)\s+([\w_]+)[\s\w\\\, ]+{/',$data,$arrC)) { 
				list($_,$_,$class) = $arrC;  
				if(preg_match('/namespace\s+([\w\\\_]+)/',$data,$arrN)){
					$class = $arrN[1].'\\'.$class;					
				} 
				$this->ns[$class] = $src; 
			}
			else{ 
				//echo $src."\n"; 
				//array_unshift($this->in,$src); 
			} 
		}else{
			foreach( glob($src.'/*') as $path)
				$this->install_classmap($path);   
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
 
	function autoload(){
		if(file_exists($this->lock_file)){ 
			//list($a->ns,$a->in,$a->prs) = json_decode( file_get_contents($file) , true);   
			list($ns,$in,$prs) = include $this->lock_file;       
			spl_autoload_register(function($name)use($ns){   
				//echo $name."<br>\n"; 
				
				if( isset($ns[$name]) ){
					include_once $ns[$name];   
				}
				else{     
					//debug_print_backtrace();
					echo "<hr>";
					echo $name;
					echo "<hr>";
					//exit;
					 
					// foreach ($this->prs as $key => $value) {
					// 	if( strstr($name,$key) and $ss = join(explode($key,$name)) ){    
					// 		$value['src'] = preg_replace('/\/$/','',$value['src']);
							
					// 		foreach ($value['src'] as $src) { 
					// 			$file =  dirname($value['vendor'])."/$src/". str_replace('\\','/',$ss)  .'.php'; 
					// 			if(file_exists($file)){
					// 				require_once $this->ns[$name] = $file; 
					// 				//echo "\n".$name. '        '.$file; 
					// 				return;
					// 			}
					// 		} 
					// 	}
					// }    
				
					//echo "<pre>\n";
					//echo $name."\n"; 
					//print_r($this);
			 
				} 
				
			});   
			foreach ($in as $value) { 
				include_once $value;
			}  
			return true;
		}else{
			return false;
		}
				
	}
	 	

	public static function bootstrap(){   
		$class = get_called_class();
		$al = new $class( );   
		if($al->autoload());
		elseif(isset($_GET['install'])){   
			$al->install();   exit;
		}
		elseif(isset($_GET['l'])){  
			echo json_encode( $al->vendor($_GET['l'],$_GET['v']) ); 	exit;
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
	
	
	
	
	
	function view(){
		
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
		<input type="button" value="安装完成" onclick="location='?install'">
		
		<script src="/jquery.min.js"></script>  
		<!--<script src="http://cdn.bootcss.com/jquery/3.0.0-beta1/jquery.min.js"></script>-->
		<script>
			window.loaded = []; 
			$(function(){
				R('','#root'); 
				//require : { name:ver* , name:ver* ... }
					//isloaded
					//verlist
				
				
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

return GithubLoader::bootstrap();