<?php


Class EnhancedXMLElement extends SimpleXMLElement
{
	static $spacer = "\t";

	public function addXML(SimpleXMLElement &$x){
		if (strlen(trim((string) $x))==0){
			$xml = $this->addChild($x->getName());
			foreach($x->children() as $child){
				$xml->addXML($child);
			}
		}
		else{
			$xml = $this->addChild($x->getName(), (string) $x);
		}
		foreach($x->attributes() as $n => $v){
			$xml->addAttribute($n, $v);
		}
    }


	/***
	 * non namespace
	 * */
	public function removeAll($childName=''){
		if (empty($childName) ){
			foreach($this->children as $x){
				unset($x);
			}
		}
		else{
			unset($this->$childName);
		}
	}



	protected function getQualifiedName(){
//		echo "+-+-+-+-+";
		$tab = $this->getNamespaces();
//		var_dump($tab);
		if ( count($tab) ){
			return key($tab).':'.$this->getName();
		}
		else{
			return $this->getName();
		}
	}

	protected function printCurrentAttributes()	{
		foreach($this->attributes() as $a => $b){
			echo ' ',$a,'="',$b,'"';
		}
	}

	protected function getAllNamespaceChildren(&$namespaces){
		$tab = array();
		foreach(  $this->children() as $child )	{
			$tab[]=$child;
		}
		foreach( $namespaces as $ns ){
			foreach(  $this->children($ns) as $child ){
				$tab[]=$child;
			}
		}
		return $tab;
	}

	protected function 	printNamespaceDeclaration(){
		foreach( $this->getDocNamespaces(false) as $ns => $urn ){
			echo ' xmlns:',$ns,'=','"',$urn,'"';
		}
	}

	function printPretty($hasProlog = true){
		$namespaces = $this->getNamespaces(TRUE);
		if ( $hasProlog ) echo '<?xml version="1.0" ?>';
		echo "\n";
		$this->printXml($namespaces,null);
	}



	protected function printXml(&$namespaces,$decalage)	{
		echo $decalage,'<';
		echo $this->getQualifiedName();
//		$this->printNamespaceDeclaration();
		$this->printCurrentAttributes();
		if ( is_null($decalage) ){
			$this->printNamespaceDeclaration();
		}

		$children = $this->getAllNamespaceChildren($namespaces);

		if ( count($children) > 0 )	{
			echo '>',"\n";
			foreach( $children as $child ){
					$child->printXml($namespaces, self::$spacer.$decalage);
			}
			echo $decalage,'</',$this->getQualifiedName(),'>',"\n";
		}
		else{
			$str = $this->__toString();
			if ( empty($str ) )	{
				echo '/>',"\n";
			}
			else{
				echo '>',$str,'</',$this->getQualifiedName(),'>',"\n";
			}
		}
	}


	/**
	 *
	 **/
	function printNodeOnly(){
		$namespaces = $this->getNamespaces(TRUE);
		echo '<',$this->getQualifiedName();
		$this->printCurrentAttributes();
		$children = $this->getAllNamespaceChildren($namespaces);
		if ( count($children) > 0 )	{
			echo '><!-- omitting ',count($children),' first-level children --></',$this->getQualifiedName(),'>',"\n";
		}
		else{
			$str = $this->__toString();
			if ( empty($str ) )	{
				echo '/>',"\n";
			}
			else{
				echo '>',$str,'</',$this->getQualifiedName(),'>',"\n";
			}
		}
	}

	function duplicate (){
		return simplexml_load_string($employee->asXML());

	}

}
