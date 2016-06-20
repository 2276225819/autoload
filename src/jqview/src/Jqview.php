<?php 
 
class Jqview{ 
	public static $debug=false;
	public static function view($html,$opt=array()){    
		$outdir = __DIR__.'/temp/'.md5($html).'.x';  
		if( !self::$debug and @filemtime($html) < @filemtime($outdir) ); else{   
			$doc = phpQuery::newDocumentFileHTML($html); 
			self::_extend($doc,dirname($html).'/');
			self::_phps($doc);  
			self::_each($doc);  
			self::_text($doc);  
			file_put_contents($outdir,$doc); 
		} 
		extract($opt);
		include $outdir;
	} 
	static function _each($doc){
		$doc['[_each]']->each(function($me){
			$self = pq($me); 
			$self->before("<?php foreach(".$self->attr('_each')."){ ?>");
			$self->after("<?php } ?>"); 
			$self->removeAttr('_each');
		}); 
		
	}
	static function _text($doc){ 
		$doc['[_text]']->each(function($me){
			$self = pq($me);
			$self->html('<?php if($_='.$self->attr('_text').'){echo $_;}else{?>'.$self->html().'<?php } ?>');
			$self->removeAttr('_text'); 
		}); 
	}
	static function _phps($doc){ 
		$doc['[_php]']->each(function($me){
			$self = pq($me);
			$self->append("<?php".$self->attr('_php')." ?>");
			$self->removeAttr('_php');  
		});   
	}
	static function _extend(&$doc,$dir){ 
		$dom = $doc['extend']; 
		if( $dom->length ){
			$n = phpQuery::newDocumentFileHTML($dir.$dom->attr('_src')); 
			$n['[_extend]']->each(function($t) use($n,$dom){ 
				$tx = "[_extend=".pq($t)->attr('_extend')."]"; 
			    $n[$tx]->html(  $dom[$tx]->html() ); 
				$n[$tx]->removeAttr('_extend');
			}); 
			$doc = $n;
		} 
	} 
	static function _include($doc){
		$doc['_include']->each(function($me){
			$self = pq($me);
			$self->html("<?php include '".$self->attr('_include')."' ?>");
			$self->removeAttr('_include');  
		});   
	}
}