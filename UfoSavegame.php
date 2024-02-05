<?php
/**
 * @package framework
 *
 * @author Pierre-Emmanuel Périllon
 * @license GPL2
 *
 *      This program is free software; you can redistribute it and/or modify
 *      it under the terms of the GNU General Public License as published by
 *      the Free Software Foundation; either version 2 of the License, or
 *      (at your option) any later version.
 *
 *      This program is distributed in the hope that it will be useful,
 *      but WITHOUT ANY WARRANTY; without even the implied warranty of
 *      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *      GNU General Public License for more details.
 *
 *      You should have received a copy of the GNU General Public License
 *      along with this program; if not, write to the Free Software
 *      Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 *      MA 02110-1301, USA.
 **/


Class UfoAiFile{
	public $pathname;
	public $filename;
	public $gamedate;
	public $savedtime;
	public $gamename;
	public $level;
	public $credits;


	function __construct($p,$f,UfoAiSave &$x){
			$this->pathname=$p;
			$this->filename=$f;
			$this->gamedate=	$x->readInGameDate();
			$this->savedtime=	$x->readSaveGameTime();
			$this->gamename=	$x->readSaveGameName();
			$this->level=	$x->readDifficultyLevel();
			$this->credits=	$x->readCredits();

	}

	function __toString(){
		return $this->gamename.' - '.$this->level.' - '.$this->gamedate.' - '.$this->credits.'c ['.$this->filename.' '.$this->savedtime.'] ';
	}
}


Class UfoAiSave {
	const HEADER_SIZE_OCTET = 180;
	const SAVE_PATTERN='/^slot[0-9]\.savx?$/';
	protected $isCompressed;
	protected $xml;
	protected $filename;
	protected $header;

	static $defaultPath='~/.ufoai/2.6-dev/base/save/campaign/';


	/**
	 * load a a savegame into the current object...
	 * @uses EnhancedXMLElement::__construct()
	 * @uses self::readUfoSaveGame()
	 **/

	function __construct($filename){
		$this->filename =$filename;
		$this->load();
	}

	/**
	 * serialiser.
	 * @uses EnhancedXmlElement::asXML()
	 * */
	function __toString(){
		return $this->xml->asXML();
	}


	protected function load(){
		$body="";
		$body=$this->readUfoAiFile();
		$this->xml = new EnhancedXMLElement($body);
		$this->isModifiedXml = false;
		if ($this->xml['saveVersion']<>4){
			echo 'untested version';
		}
		//echo 'using savegame:', $this->readSaveGameName(), ' created on ', $this->readSaveGameTime(),'.';
		return $this;
	}




	function readCredits(){
		return (int)$this->xml->campaign[0]['credits'];
	}
	function readDifficultyLevel(){
		return (string)$this->xml->campaign['id'];
	}
	function readInGameDate(){
		return (string)$this->xml['gameDate'];
	}
	function readSaveGameName(){
		return (string)$this->xml['comment'];
	}

	function readSaveGameTime(){
		return (string)$this->xml['realDate'];
	}
		/**
	 * well i'm not proud of this but we may have a way to read a file
	 * without being in a object context... this will be helpfull in corrupt
	 * savegame situation.
	 * We don't interpret the header part... it may be a big mistake.
	 * @param string $filename a file to read (rb) right
	 * @param string&  $header header part of the file...
	 * @param string& $body other part of the file
	 * @param boolean& $isCompressed true if savegame was compressed...
	 * **/

	protected function readUfoAiFile(){
		$fp = fopen($this->filename,'rb');
		$this->header = fread($fp,self::HEADER_SIZE_OCTET);
		$fstat = fstat($fp);
		$body = fread($fp,$fstat['size']);// we should meet eof before but i don't care about 180 first chars...
		fclose($fp);
		if ( substr($body, 0, 5) == '<?xml' ){
			$this->isCompressed = false;
	//		echo 'File is not compressed',PHP_EOL;
		}
		else{
	//		echo 'File is compressed.',PHP_EOL;
			$this->isCompressed = true;
			$body = @gzuncompress($body);
		}
		return $body;
	}




	/**
	 * this function write the new xml tree to file  but duplicate the file before.
	 **/
	function write(){
		echo 'writing .. ',$this->filename,PHP_EOL;
		copy($this->filename,$this->filename.'_'.date('Y-m-d_h-i-s') );

		$fp = fopen($this->filename, 'w+b'); // reinit the file.
		fwrite($fp, $this->header);
		if ( $this->isCompressed ){
			$data = gzcompress($this->__toString());
		}
		else{
			$data = $this->__toString();
		}
		fwrite($fp,$data);
		fclose($fp);
		$this->isModifiedXml = false; // now xml tree and file are in sync
		return $this;
	}

	/**
	 * here we need to let people have access to the inner property.
	 * */
	function getXml(){
		return $this->xml;
	}

	/**
	 * this allow to reimport a xml file into the running object.
	 * **/
	function import($filename){
		 debug_print_backtrace();
		$this->xml = simplexml_load_file($filename,'EnhancedXMLElement');
	}



	static function selectSaveGame( $context =null ){
		if ( empty( $context) ){
			$context=self::$defaultPath;
			$context=trim(shell_exec('ls -d '.$context));
		}

		$dir= new DirectoryIterator($context);
		$res=array();
		foreach( $dir as $file ){
			if ( $file->isDot() ){
				continue;
			}
			elseif ( $file->isFile() and preg_match(self::SAVE_PATTERN, $file->getFilename()) ){
				try{
					$x=new UfoAiSave( $file->getPathname() );
					$x=new UfoAiFile($file->getPathname(),$file->getFilename(),$x);
					$res[$file->getFilename()]=$x;
				}catch( Exception $e ){
					echo PHP_EOL,'!Detected file ',$file->getFilename(),' should be ignored as it looks dammaged. ',$e->getMessage(),PHP_EOL,PHP_EOL;
				}
			}
		}

		if (count($res)===0){
			throw new Exception('No file found?!?');
		}
		if ( count($res)===1){
			$tmp=first($res);
			return $tmp->pathname;

		}
		else {
			ksort($res);
			$prompt=new Prompt();
			$answer=$prompt->chooseBest('Which savegame to use ?', $res );
			//var_dump($answer);
			$tmp = current($answer);
			return $tmp->pathname;
		}
	}
}







Class UfoAiGame {

	//you may change this if you know a little about the game...
	static $soldierHP = 110;
	static $soldierMorale = 130;
	static $soldierMeanSkill = 33;
	static $pilotHP = 50;
	static $pilotMorale = 130;
	static $pilotMeanSkill = 15;
	static $warriorRank = 5;


	//Never change that. trust me.
	const SK_EXPLOSIVE='explosive';
	const SK_ASSAULT='assault';
	const SK_CLOSE='close';
	const SK_SNIPER='sniper';
	const SK_MIND='mind';
	const SK_SPEED='speed';
	const SK_POWER='power';
	const SK_ACCURACY='accuracy';
	protected $file;
	protected $saveChange = true;
	protected $isModifiedXml=false;
	private $prompt;

	static $bigBuilding = array('building_aliencontainment','building_hangar','building_workshop');
	static $availableDifficulties= array('veryeasy','easy','main','hard','veryhard');
	static $inventoryBeginning = '
<inventory>
<item container="headgear" x="0" y="0" weaponid="irgoggles" amount="1"/>
<item container="backpack" x="0" y="1" weaponid="fraggrenade" amount="1"/>
<item container="backpack" x="0" y="2" weaponid="fraggrenade" amount="1"/>
<item container="backpack" x="1" y="1" weaponid="knife" amount="1"/>
<item container="backpack" x="1" y="0" weaponid="flashbang" amount="1"/>
<item container="backpack" x="3" y="0" weaponid="incgrenade" amount="1"/>
<item container="holster" x="1" y="0" weaponid="medikit" amount="1"/>
<item container="holster" x="0" y="0" weaponid="fraggrenade" amount="1"/>
<item container="holster" x="0" y="1" weaponid="fraggrenade" amount="1"/>
<item container="armour" x="0" y="0" weaponid="armour_light" amount="1"/>
</inventory>';


	function __construct( UfoAiSave $x ){
		$this->xml = &$x->getXml();
		$this->file=$x;
		$this->prompt=new Prompt();
	}

	/**
	 * @param $filename le fichier à lire dans lequel
	 * @uses self::load()
	 * @uses
	 **/



	/**
	 * do save the xml tree ... if needed.
	 **/
	function save($force=false){
		if (( $this->isModifiedXml and $this->saveChange ) or $force ){
			$this->file->write();
		}
		return $this;
	}

	/**
	 * when i quit, i would likely save my work, in fact it will be controled by an internal flag.
	 * @uses self::save()
	 **/
	function __destruct(){
		$this->save();
	}




	/***
	 * @param SimpleXMLElement &$collection
	 * @uses self::getNextUniqueCharacterNumber()
	 * @uses EnhancedXmlElement::addXML()
	 * **/
	protected function addEmployees(SimpleXMLElement &$collection){
		$it = rand(0,5);
		$mod = ceil( $collection->count()/5 );
		$tab = array();
		$suffix = ' (clone)';
		foreach( $collection as $employee ){
			$it++;
			if ( ($it % $mod) == 0 ){

				if ( strpos( $employee->character['name'], $suffix ) === false ){
					$tmp = clone $employee;
					$tmp->character['ucn'] = $this->getNextUniqueCharacterNumber();
					$tmp->character['name'] = $tmp->character['name'].$suffix;
					unset($tmp['baseHired']);
					unset($tmp['assigned']);
					$tab[] = $tmp;
				}
				else{
					$it--;
				}
			}
		}
		foreach( $tab as $employee ){
			$collection->addXML($employee);
		}
		echo count($tab),' peoples added.',PHP_EOL;
	}


	function readSaveGameName(){
		return (string)$this->xml['comment'];
	}

	function readSaveGameTime(){
		return (string)$this->xml['realDate'];
	}

	function readDifficultyLevel(){
		return (string)$this->xml->campaign['id'];
	}

	/**
	 *
	 * @uses self::addEmployees()
	 * @uses self::sortEmployee()
	 * **/
	function add_civilian(){
		foreach( $this->xml->employees as $collection )	{
			if ( 'scientist' == $collection['type'] or  'worker' == $collection['type'] ){
				echo $collection['type'],': ';
				$this->addEmployees($collection);
				$this->sortEmployee($collection);
				$this->isModifiedXml = true;
			}
		}
	}

	function change_difficulty(){
		$curr = $this->readDifficultyLevel();
		$sug = array_merge( array('don\'t change[ '.$curr.' ]'), self::$availableDifficulties );
		$answer = $this->prompt->chooseBest('Select your new difficulty'	, $sug );
		if (  key($answer) !== 0  and $this->setDifficultyLevel(current($answer) ) ){
			echo 'difficulty successfully changed',PHP_EOL;
		}
		else{
			echo 'No change !',PHP_EOL;
		}
	}



	function setDifficultyLevel($diff){
		if ( in_array($diff,self::$availableDifficulties ) ){
			$this->xml->campaign['id'] = $diff;
			$this->isModifiedXml = true;
			return true;
		}
		else
			return false;
	}



	/**
	 * @uses self::chooseBaseName()
	 * */
	function chooseBase(){
		$answer = $this->chooseBaseName();
		$list_base = $this->xml->xpath('bases/base[@name="'.current($answer).'"]');
		if( count($list_base) !== 1 ){
			echo 'non unique base, taking the first...',PHP_EOL;
		}
		return current($list_base);
	}

	/**
	 * @uses self::listBases()
	 * @uses Prompt::__construct()
	 * @uses Prompt::chooseBest()
	 * */
	function chooseBaseName(){
		$question = 'please choose your base';
		$suggestions = $this->listBases();
//		$suggestions[] = 'abort';
		return $this->prompt->chooseBest($question,$suggestions);
	}

	/**
	 * a callback function
	 * */
	static protected function compareEmployeeByName(SimpleXMLElement $a , SimpleXMLElement $b ){
		return strcmp( (string) $a->character['name'],  (string) $b->character['name']);
	}

	/**
	 * an alternative callback function
	 * */
	static protected function compareEmployeeByScore(SimpleXMLElement $a , SimpleXMLElement $b ){
		return self::getEmployeeScore($b)-self::getEmployeeScore($a);
	}

	/**
	 * yet another callback function
	 * */
	static protected function compareEmployeeByUCN(SimpleXMLElement $a , SimpleXMLElement $b ){
		return intval($a->character['ucn'])-intval($b->character['ucn']);
	}

	/**
	 * @uses self::searchBuildingArea()
	 * **/
	static protected function correctBuildingInBuildings(SimpleXMLElement $base, $area)	{
		foreach( $base->buildings->building as $building ){
			$col = intval($building->pos['x']);
			$row = intval($building->pos['y']);
			$idx = (string)$building['buildingPlace'];
			echo "($col - $row - ",$idx,") ";

			if ( self::searchBuildingArea($area,$idx,$new_row,$new_col) ){
				if ( $new_col != $col or $new_row != $row ){
					echo "change...";
					$building->pos['y']=$new_row;
					$building->pos['x']=$new_col;
				}
			}
			else{
				echo "not found ????\n";
			}
		}
	}

	/***
	 * @param SimpleXMLElement& $base the node to modify, not sure the & is necessary but to make things easy to understand
	 * @param $area new base layout
	 **/
	static protected function correctBuildingInBuildspace(SimpleXMLElement &$base,$area){
		$it = 0;
		foreach( $base->buildingSpace->building as $building ){
			$it++;
			$col = intval($building['y']);
			$row = intval($building['x']);
			$val = isset($building['buildingIDX'])?(string)$building['buildingIDX']:null;
			$ref = isset($area[$row][$col])?$area[$row][$col]['idx']:null; //there is a transposition in the file.
			if ( $val !== $ref ){
				if ( is_null($ref) ){
					unset($building['buildingIDX']);
				}
				else{
					$building['buildingIDX'] = $ref;
				}
				echo "\t",'correcting building x=',$col,' y=',$row,PHP_EOL;
			}
			else{
				echo "\t",'matching building x=',$col,' y=',$row,PHP_EOL;
			}
		}
		if ( $it != 25 ){
			echo "\t",'building count error :( ',$it,PHP_EOL;
		}
	}

	/**
	 * quick wait to print a base layout...
	 * */
	private static function debugArea($area){
		for( $row = 0; $row < 5 ; $row++ ){
			for ( $col = 0; $col < 5; $col++ )	{
				echo isset($area[$row][$col])?$area[$row][$col]['idx']:'_';
				echo " ";
			}
			echo "\n";
		}

	}


	/***
	 * a efficiency function, it won't add any item in your storage ;)
	 * @uses Prompt::__construct()
	 * @uses Prompt::chooseBest()
	 * @uses self::providePackage()
	 * */
	function equip_soldier(){
		$answer = $this->prompt->chooseBest('Select your inventory?',array(
			'inventoryBeginning' => 'template at beginning'
			));
		$var = self::${key($answer)};
		$package = simplexml_load_string($var);
//		print_r($package);
		foreach( $this->xml->employees as $collection ){
			if ( 'soldier' == $collection['type'] ){
				$this->isModifiedXml = true;
				self::providePackage($collection,$package);
			}
		}
	}


	/**
	 * oh it's a cheat...
	 * @uses self::chooseBaseName();
	 * */
	function finish_building(){
		$answer = $this->chooseBaseName();
		foreach( $this->xml->xpath('bases/base[@name="'.current($answer).'"]/buildings/building[not(@buildingStatus="working")]') as $building ){
			$this->isModifiedXml = true;
			$building['buildingStatus']='working';
			$building->buildingTimeStart['day']=0;
			$building->buildingTimeStart['sec']=0;
		}
		return $this;
	}


	/****
	 * this is no cheat, it just allow you to fire every scientist/worker from all your base.
	 * it will save you some clicks...
	 * @uses Prompt::__construct()
	 * @uses Prompt::confirm()
	 * **/
	function fire_civilian(){
		$i = 0;
		if ( $x =  $this->prompt->confirm('Fire Scientists ?') ){
			foreach( $this->xml->xpath('employees[@type="scientist"]/employee') as $employee ){
				if ( isset($employee['baseHired']) or isset($employee['assigned']) ){
					unset($employee['baseHired']);
					unset($employee['assigned']);
					$i++;
				}
			}//for
		}
		$this->isModifiedXml = ($this->isModifiedXml or $i>0 );
		echo 'fired ',$i, 'scientist(s)',PHP_EOL;
		$i = 0;

		if ( $this->prompt->confirm('Fire Workers?') ){
			foreach( $this->xml->xpath('employees[@type="worker"]/employee') as $employee ){
				if ( isset($employee['baseHired']) or isset($employee['assigned']) ){
					unset($employee['baseHired']);
//					unset($employee['assigned']);
					$i++;
				}
			}//for
		}
		$this->isModifiedXml = ($this->isModifiedXml or $i>0 );
		echo 'fired ',$i, 'worker(s)',PHP_EOL;

	}


	/***
	 * @deprecated
	 * @uses self::readBuildingPosition()
	 * @uses self::printBuildingPosition()
	 * @uses self::correctBuildingInBuildspace()
	 * */
	function fix_building_position(){
		foreach( $this->xml->bases->base as $base ){
			echo '+++++++++++++ current base:',$base['name'],' ++++++++++++ ',PHP_EOL;
			$this->printBuildingPosition($area);
			self::correctBuildingInBuildspace($base,$area);
			$this->isModifiedXml = true;
		}
	}


	/**
	 * this does not work.
	 * @deprecated
	 **/
	function fix_missionid(){
		$target = $this->xml->xpath('/savegame/UFOs/craft[1]');
		$source = $this->xml->xpath('/savegame/missions/mission[last()]');
		$target[0]['missionid']=$source[0]['id'];
		$this->isModifiedXml = true;
		return $this;
	}


	/**
	 * this is a cheat that allow military to keep on being promoted to higher rank
	 * @uses Prompt::__construct()
	 * */
	function forgive_soldier(){
		$p = new Prompt();
		$tab = $this->xml->xpath('employees[@type="soldier"]/employee/character/scores/kill[@type="civilian" and @killed>0]/../../..');
		$killed=$pardon=0;
		foreach( $tab as $soldier ){
//			var_dump($soldier);
//			$p->pause();
			foreach( $soldier->character->scores->kill as $kill ){
				if ($kill['type'] == 'civilian' ){
					if ( $p->confirm('Do you want to forgive: '.$soldier->character['name'].' (ucn='.$soldier->character['ucn'].')'.
					"\n".'He slaugtered '.$kill['killed'].' civilian(s).') ){
						$killed+=intval($kill['killed']);
						$kill['killed']=0;
						$pardon++;
					}
				}
			}
		}//foreach
		$this->isModifiedXml = ($this->isModifiedXml or $pardon>0);
		echo 'There are ',count($tab),' soldiers that killed civilians, you pardon yourself for ',$pardon,' soldiers',PHP_EOL;
		echo 'you better remember the ',$killed,'  civilian(s) that you failed to rescue.',PHP_EOL;
	}



	/**
	 * this is a function that is used for sorting by score.. it retrive the "score" (here it's experience)
	 * */
	static protected function getEmployeeScore(SimpleXMLElement &$a ){
		foreach( $a->character->scores->skill as $skill ){
			if (  isset($skill['type']) and $skill['type'] == 'hp' ){
				if ( isset($skill['experience']) )	{
					echo $a->character['name'],': ',$skill['experience'],PHP_EOL;
					return intval($skill['experience']);
				}
			}
		}
		return 0;
	}


	/**
	 * this allow the cheat code to generate clone with unique id...
	 * */
	protected function getNextUniqueCharacterNumber(){
		$ucn = intval( $this->xml->campaign['nextUniqueCharacterNumber'] );
		//echo 'ucn:',
		$this->xml->campaign['nextUniqueCharacterNumber'] = $ucn+1;//,PHP_EOL;
		return $ucn;
	}



	function import($filename){
		$this->file->import($filename);
		$this->isModifiedXml=true;
	}

	/**
	 * this method is an helper that do what the name suggested
	 **/
	protected function listBases(){
		$list=array();
		foreach( $this->xml->bases->children() as $base ){
			$list[intval($base['idx'])] = $base['name'] ;
		}
		return $list;
	}




	static protected function makeInventory(EnhancedXMLElement $character,SimpleXMLElement $inventory){
//		echo $character->inventory->asXML();
//		echo ' --------------------------------- ',PHP_EOL;
//		print_r($character);
//		echo "count :",$character->inventory->item->count();
		if( $character->inventory->item->count() < 1 ){
			unset($character->inventory);
			$character->addXML($inventory);
			echo 'soldier:',$character['name'],' (',$character['ucn'],') gets default equipment.',PHP_EOL;
		}
	}



	/**
	 *
	 **/
	function more_money($credit = 250000 ){
		echo "adding $credit .. \n";
		$this->xml->campaign[0]['credits'] = $this->xml->campaign[0]['credits'] + $credit;
		echo "you've now ",$this->xml->campaign[0]['credits'],PHP_EOL;
		$this->isModifiedXml = true;
		return $this;
	}

	/**
	 * a cheat: this function allow to rework for free your base layout
	 * @uses self::readBuildingPosition
	 * @uses self::romptForMoveInput()
	 * @uses self::correctBuildingInBuildings()
	 * @uses self::correctBuildingInBuildspace()
	 * **/
	function move_building(){
		$base = $this->chooseBase();
		$area = self::readBuildingPosition($base);
		if ( $this->promptForMoveInput($area) ){
			echo 'will now apply changes to xml',PHP_EOL;
			self::correctBuildingInBuildings($base, $area);
			self::correctBuildingInBuildspace($base,$area);
			$this->isModifiedXml = true;
		}
		else{
			echo 'nothing to do',PHP_EOL;
		}
	}


	/**
	 * display a console version of one base layout
	 **/
	static protected function printBuildingPosition($area){
		echo "\n",sprintf("%'=-35s", $area['basename']),str_repeat('=',77),PHP_EOL;
		for ( $row = 0; $row < 5; $row++ ){
			if( 0 == $row ){
//				echo $yellow;
				printf("%2s  %20d  %20d  %20d  %20d  %20d  \n",'x',0,1,2,3,4);
			}
			for( $col = 0; $col < 5 ; $col++ ){
				if ( 0 == $col ){
//					echo $yellow;
					printf("%2d  ",$row);
				}

				if ( isset($area[$row][$col]) )	{
//					echo $white;
					printf("%'_20s",str_ireplace('building_','',$area[$row][$col]['type']).'>'.$area[$row][$col]['idx']);
//					echo $blue;
				}
				else{
//					echo $white;
					echo str_repeat('_',20);
				}
				echo '  ';
			}
			echo "\n";
		}
//		echo $white;
	}


	/**
	 * @param $filename file where to write.
	 * */
	function printToFile($filename){
		ob_start();
		$this->xml->printPretty();
		$buf = ob_get_clean();
		file_put_contents($filename,$buf);
		return $this;
	}

	/***
	 * an interactive prompt to request what to move in the base.
	 * as you can not design a base in just one day...
	 * todo: rework the loop
	 * @uses Prompt::__construct()
	 * @uses self::printBuildingPosition()
	 * @uses  self::moveSymmetry()
	 * @uses self::searchBuildingArea()
	 * @uses self::unputBuildingAreaByPosition()
	 * @uses self::putBuildingArea()
	 *
	 *
	 * */
	protected  function promptForMoveInput( &$area ){
		$backup = $area;
		$cpt = 0;
		while(true){
			do{
				self::printBuildingPosition($area);
				$answer = $this->prompt->question('provide in order: [id building to move] [row] [column], write "horizontal"/"vertical" for symmetry, "abort" to cancel, "validate" to apply change');
				if ( $answer === 'abort'){
					return 0;
				}
				else if ( $answer === 'validate'){
					return $cpt;
				}
				if ( $answer === 'horizontal' ){
					$backup=$area = self::moveSymmetry($area,true);
				}
				else if ( $answer === 'vertical' ){
					$backup=$area = self::moveSymmetry($area,false);
				}
				$ok = preg_match('#([0-9]+)[^0-9]+([0-9]+)[^0-9]+([0-9]+)#i',$answer,$matches );
			} while( !$ok );
			$idx= $matches[1];
			$new_row	= $matches[2];
			$new_col	= $matches[3];
			$col = $row = null;
			if (  self::searchBuildingArea($area,$idx,$row,$col) ){
			// remove
				$ref = $area[$row][$col];
				$deleted = self::unputBuildingAreaByPosition($area,$row,$col);
				$moved = self::putBuildingArea($area,$new_row, $new_col,$ref);
				$ok = ( $deleted == $moved );
				if ( $ok ){
					echo 'no pb, preparing for next move',PHP_EOL;
					$backup = $area;
					$cpt++;
				}
				else{
					echo 'problem during building move, cancel',PHP_EOL;
					$area = $backup;
				}
			}
			else{
				echo 'building not found.',PHP_EOL;
			}
		}//while
		//dead end
	}

	/**
	 * this allow to perform an isomorphism on the base map :)
	 * */
	protected function moveSymmetry($area,$horizontal = true ){
		$sym['basename']=$area['basename'];
		unset($area['basename']);
		foreach( $area as $row => $line ){
			foreach( $line as $col => $val ){
				if ( $horizontal ){
					$sym[$row][4-$col] = $val;
				}
				else{
					$sym[4-$row][$col] = $val;
				}
			}
		}
		return $sym;
	}


	/**
	 * this is a stupid attempt to rework default package for my soldiers...
	 *
	 * */
	protected function providePackage(SimpleXMLElement $collection, $inventory){
		$this->isModifiedXml = true;
		foreach( $collection as $soldier ){
			if ( isset($soldier['baseHired']) ){
				if ( is_null($inventory) ){
					unset( $soldier->character->inventory->item);
				}
				else{
					self::makeInventory($soldier->character, $inventory);
				}
				echo 'hired: ';
			}
			else{
				echo 'not hired:';
			}
			echo $soldier->character['name'],PHP_EOL;
		}
	}



	/**
	 * put familly method are building oriented (do multiple set)
	 * @param array& $area the base layout to modify
	 * @param integer $row the number of the row (as displayed)
	 * @param integer $col the column number (as displayed)
	 * @param Array $ref it's the "building" to put at position $row x $col
	 * @uses self::setBuildingArea()
	 * @uses self::setBuildingArea()
	 **/
	protected static function putBuildingArea(&$area, $row, $col, $ref){
//		var_dump($ref);
		$written = self::setBuildingArea($area,$row,$col,$ref);
//		echo "written=$written, type=",$ref['type']," \n";
		if ( in_array($ref['type'],self::$bigBuilding ) ){
			$written += self::setBuildingArea($area,$row,$col+1,$ref);
		}
//		self::debugArea($area);
//		echo "written=$written \n";
		return $written;
	}

function readCredits(){
		return (int)$this->xml->campaign[0]['credits'];
	}

	/***
	 * @param SimpleXMLElement $base
	 * @uses self::putBuildingArea()
	 **/
	static protected function readBuildingPosition(SimpleXMLElement $base){
		$area['basename']=$base['name'];
		foreach( $base->buildings->building as $building ){
			//TODO
			$col = intval($building->pos['x']);
			$row = intval($building->pos['y']);
			$ref = array(
					'idx' => (string)$building['buildingPlace'],
					'type' => (string)$building['buildingType']);
			echo "($col - $row - ",(string)$building['buildingType'],
			' ) write ',(string)$building['buildingType'], ' : ',
			self::putBuildingArea($area,$row,$col,$ref),
			"\n";
		}
		return $area;
	}


	/**
	 * a cheat !
	 * @uses self::chooseBase()
	 * **/
	function remove_granit(){
		$base = $this->chooseBase();
		$i=0;
		foreach($base->buildingSpace->building as $building){
			if ( isset($building['blocked'])){
				unset($building['blocked']);
				$i++;
			}
		}
		$this->isModifiedXml = ($this->isModifiedXml  or $i );
		echo 'removed:',$i,' granit blocks',PHP_EOL;
	}

	function reduce_interest (){
		$this->isModifiedXml=true;
		$this->xml->interests['lastIncreaseDelay']=10;
		$this->xml->interests['lastMissionSpawnedDelay']=9;
		$cumul=0;
		foreach( $this->xml->xpath('interests/interest') as $interest ){
			$interest['value']=max(floor($interest['value']/2)-5,0);
			$cumul+=$interest['value'];
			echo "\t",'Alien interest is now ',$interest['value'],' for ',$interest['id'],'.',PHP_EOL;
		}
		$this->xml->interests['overall']=$cumul;
	}



	/**
	 * useless
	 **/
	function saveDemoMode(){
		$this->saveChange=false;
		return $this;
	}

	/**
	 * useless
	 **/
	function saveRealMode(){
		$this->saveChange=true;
		return $this;
	}


	/**
	 * @param Array $area base layout
	 * @param string? $idx the in-base building identifier
	 * @param integer& $row it's a result
	 * @param integer& $col it's a result
	 * @return true if building was found, so $row and $col are valid
	 **/
	static protected function searchBuildingArea($area /*map*/, $idx /* what to look for*/, &$row, &$col){
		for( $row = 0; $row < 5 ; $row++ ){
			for ( $col = 0; $col < 5; $col++ ){
				if ( isset($area[$row][$col]) ){
					if ( $area[$row][$col]['idx'] === $idx ){
						return true;
					}
				}
			}
		}
		return false;
	}



	/***
	 * set method family is "area-cell" oriented
	 * @param Array& $area base layout
	 * @param integer $row
	 * @param integer $col
	 * @param Array $value the description about the building.
	 **/
	static protected function setBuildingArea(&$area, $row, $col, $value ){
		if( isset($area[$row][$col]) ){
			echo 'building overlaping:',$value['type'],' overwrite ',$area[$row][$col]['type'],' at x=',$col,', y=',$row,PHP_EOL;
			return 0;
		}
		else if(  $col < 0  or $col > 4 or $row < 0 or $row > 4){
			echo 'out of bound ![x=',$col,', y=',$row,', idx=',$value['type'],']',PHP_EOL;
			return 0;
		}
		else{
			$area[$row][$col]=$value;
			return 1;
		}
	}

	/**
	 * @uses self::chooseBase()
	 * @uses self::readBuildingPosition()
	 * @uses Prompt::__construct()
	 * @uses Prompt::question()
	 **/
	function show_building(){
		$base = $this->chooseBase();
		self::readBuildingPosition($base);
		$p = new Prompt();
		$p->question('Press [enter] to continue...');
	}


	/**
	 * @WIP
	 * */
	function show_transfert(){
		$bases = $this->listBases();
		$this->xpath('');//todo
	}

	/***
	 * simply sort the employee by name... use callback!
	 * @uses EnhancedXMLElement::addXML()
	 * @uses self::compareEmployeeByName()
	 * @uses self::compareEmployeeByScore()
	 * @uses self::compareEmployeeByUCN()
	 * */
	static protected function sortEmployee(EnhancedXMLElement &$collection,$callback = 'self::compareEmployeeByName' )	{
		$tab=array();
		foreach($collection  as $employee ){
			$tab[] = clone $employee;
		}
		unset($collection->employee);
		usort($tab,$callback);
		foreach( $tab as $employee ){
			$collection->addXML($employee);
		}
	}


	/**
	 * sort the employees... but not a cheat.
	 * @uses Prompt::__construct()
	 * @uses Prompt::chooseBest()
	 * @uses self::sortEmployee()
	 * */
	function sort_employee(){
		$answer = $this->prompt->chooseBest('Choose the sort you want',
			array('compareEmployeeByName'=>'sort by name',
			'compareEmployeeByScore'	=> 'sort (soldiers) by experience',
			'compareEmployeeByUCN'	=>	'initial ordering'));

		foreach( $this->xml->employees as $collection )	{
			self::sortEmployee($collection,'self::'.key($answer));
			$this->isModifiedXml = true;
		}
		echo 'employees sorted',PHP_EOL;
	}


	/**
	 * productivity function
	 * @uses self::providePackage()
	 * **/
	function unequip_soldier(){
//		print_r($package);
		foreach( $this->xml->employees as $collection ){
			if ( 'soldier' == $collection['type'] ){
				self::providePackage($collection,null);
				$this->isModifiedXml = true;
			}
		}
	}




	/***
	 * put familly method are building oriented (do multiple set)
	 * @uses self::searchBuildingArea()
	 * @uses self::unputBuildingAreaByPosition()
	 ***/
	static protected function unputBuildingAreaByIdx(&$area,$idx){
		$col = $row = null;
		if ( self::searchBuildingArea($area, $idx, $row, $col)){
			return self::unputBuildingAreaByPosition($area, $row, $col);
		}
		// not found.
		return 0;
	}






	/**
	 * put familly method are building oriented (do multiple set)
	 * @uses self::unsetBuildingAreaPosition()
	 * @uses self::unsetBuildingAreaPosition()
	 **/
	static protected function unputBuildingAreaByPosition(&$area, $row, $col){
//		self::debugArea($area);
		$deleted = 0;
		if( in_array( $area[$row][$col]['type'], self::$bigBuilding ) ) //ça sert un peut à rien
		{
//			echo __FILE__,":",__LINE__," large file \n";
			$xx = $col+1;
			if ( isset($area[$row][$xx]) and $area[$row][$xx]['idx'] === $area[$row][$col]['idx'] )	{
				$deleted += self::unsetBuildingAreaPosition($area,$row,$xx);
			}
		}
		$deleted += self::unsetBuildingAreaPosition($area,$row,$col);
		return $deleted;
	}


	/***
	 * set family method are $area cell oriented.
	 **/
	static protected function unsetBuildingAreaPosition(&$area, $row, $col ){
		$deleted = 0;
//		echo __FILE__,":",__LINE__,"=> $row $col:",$area[$row][$col]['type'],PHP_EOL;
		if ( isset($area[$row][$col]) )	{
			unset($area[$row][$col]);
			$deleted++;
		}
//		self::debugArea($area);
		return $deleted;
	}


	/**
	 *
	 **/
	function update_nation(){
		foreach( $this->xml->xpath('nations//month') as $month ){
			echo 'happiness ', $month['happiness'], ' => ';
			$month['happiness'] = number_format( round( ( 1.0 + floatval($month['happiness']) )/2, 6), 6, '.', '' );
			echo $month['happiness'], "\n";
			$month['XVI'] = '0';
		}

		$this->isModifiedXml = true;
		return $this;
	}



	/**
	 *
	 * version 2.4  (soldier)
<scores missions="128" rank="8">
	<skill type="power" initial="40" experience="16949" improve="21"/>
	<skill type="speed" initial="40" experience="14921" improve="19"/>
	<skill type="accuracy" initial="40" experience="16675" improve="20"/>
	<skill type="mind" initial="40" experience="28036" improve="28"/>
	<skill type="close" initial="40" experience="10920" improve="15"/>
	<skill type="heavy" initial="40" experience="13133" improve="18"/>
	<skill type="assault" initial="38" experience="12016" improve="17"/>
	<skill type="sniper" initial="42" experience="13172" improve="18"/>
	<skill type="explosive" initial="44" experience="32094" improve="31"/>
	<skill type="hp" initial="130" experience="21365"/>
	<kill type="enemy" killed="60"/>
</scores>
	**/
	static protected function makeSoldierBonus(&$result){
		$person = array(self::SK_POWER,self::SK_SPEED,self::SK_ACCURACY,self::SK_MIND);
		$bonus = array(1,0,0,1);
		shuffle($bonus); shuffle($bonus);shuffle($bonus); // yes twice...
		$person_bonus = array_combine($person,$bonus);

		$skills = array(self::SK_ASSAULT,self::SK_SNIPER,self::SK_CLOSE,/*'heavy',*/self::SK_EXPLOSIVE);
		$refbonus= $skills;

		$x=array( rand(-3,-1),rand(-2,0),rand(2,3));
		$x[]=4-$x[0]-$x[1]-$x[2];
		sort($x);
		list($d,$c,$b,$a) = $x;

		$refbonus[self::SK_ASSAULT][self::SK_ASSAULT]=$a;
		$refbonus[self::SK_ASSAULT][self::SK_SNIPER]=$d;
		$refbonus[self::SK_ASSAULT][self::SK_CLOSE]=$c;
		$refbonus[self::SK_ASSAULT][self::SK_EXPLOSIVE]=$b;

		$refbonus[self::SK_SNIPER][self::SK_ASSAULT]=$c;
		$refbonus[self::SK_SNIPER][self::SK_SNIPER]=$a;
		$refbonus[self::SK_SNIPER][self::SK_CLOSE]=$d;
		$refbonus[self::SK_SNIPER][self::SK_EXPLOSIVE]=$b;

		$refbonus[self::SK_CLOSE][self::SK_ASSAULT]=$c;
		$refbonus[self::SK_CLOSE][self::SK_SNIPER]=$d;
		$refbonus[self::SK_CLOSE][self::SK_CLOSE]=$a;
		$refbonus[self::SK_CLOSE][self::SK_EXPLOSIVE]=$b;

		$refbonus[self::SK_EXPLOSIVE][self::SK_ASSAULT]=$d;
		$refbonus[self::SK_EXPLOSIVE][self::SK_SNIPER]=$b;
		$refbonus[self::SK_EXPLOSIVE][self::SK_CLOSE]=$c;
		$refbonus[self::SK_EXPLOSIVE][self::SK_EXPLOSIVE]=$a;

		$weapon_best = array(self::SK_ASSAULT,self::SK_ASSAULT,self::SK_ASSAULT,self::SK_ASSAULT,self::SK_SNIPER,self::SK_SNIPER,self::SK_CLOSE/*,'heavy'*/,self::SK_EXPLOSIVE,self::SK_EXPLOSIVE);
		shuffle($weapon_best);shuffle($weapon_best);shuffle($weapon_best);
		$speciality = reset($weapon_best);

		$weapon_bonus=$refbonus[$speciality];
		$result = array_merge($person_bonus,$weapon_bonus);
		switch( $speciality ){
			case self::SK_EXPLOSIVE:
				$result[self::SK_POWER]++;
				break;
			case self::SK_ASSAULT:
				$result[self::SK_MIND]++;
				break;
			case self::SK_SNIPER:
				$result[self::SK_ACCURACY]++;
				break;
			case self::SK_CLOSE:
				$result[self::SK_SPEED]++;
		}
		return $speciality;
	}//fct


	static protected function makePilotBonus(&$result){
		$person = array('piloting','targeting','evading');
		$bonus = array(rand(-1,2),rand(1,2),rand(0,2));
		shuffle($bonus); shuffle($bonus);shuffle($bonus); // yes twice...
		$result = array_combine($person,$bonus);
		return 'Eagle';
	}//fct


	/**
	 * a cheat that gives you super soldier... but if you're bad at strategie, it won't be enough.
	 * @uses self::upgradeCharacterSkills()
	 **/
	function update_soldier(){
		$stats = array(self::SK_ASSAULT=>0,self::SK_CLOSE=>0,self::SK_SNIPER=>0,self::SK_EXPLOSIVE=>0);
		$total = $cpt =0;
		foreach( $this->xml->xpath('employees[@type = "soldier"]/employee') as $soldier ){
			$total++;
			if (  intval($soldier->character['maxHp']) < self::$soldierHP ){
				$cpt ++;
				$bonus=array();
				$speciality=self::makeSoldierBonus($bonus);
				$speciality=self::upgradeCharacterSkills($soldier, $speciality, $bonus,
					self::$soldierHP, self::$soldierMorale, self::$soldierMeanSkill, true);
				$stats[$speciality]++;
			}
		}
		$this->isModifiedXml = ( $this->isModifiedXml or $cpt );
		echo  $cpt,' soldiers were updated over a total of ',$total,PHP_EOL;
		foreach( $stats as $k => $v ){
			echo "\t",ucfirst($k),': +',$v,' soldier(s)',PHP_EOL;
		}
		return $this;
	}// update_soldier



	/**
	 * @uses self::upgradeCharacterSkills()
	 **/
	function update_pilot(){
		$cpt = 0;
		foreach( $this->xml->xpath('employees[@type = "pilot"]/employee') as $soldier ){
			//echo $soldier->character['name'],PHP_EOL;
			if (  $soldier->character['maxHp'] < self::$pilotHP ){
				$cpt++;
				$bonus=array();
				$speciality=self::makePilotBonus($bonus);
				$speciality=self::upgradeCharacterSkills($soldier, $speciality, $bonus,
					self::$pilotHP,self::$pilotMorale,self::$pilotMeanSkill, true);
			}
		}

		$this->isModifiedXml = ($this->isModifiedXml or $cpt);
		echo "$cpt soldier updated ...\n";
		return $this;
	}//method update_pilot




	static protected function upgradeCharacterSkills(SimpleXMLElement &$Character,$speciality, $bonus, $hp, $morale, $target_level, $rename = false ){
		$maxHp=$hp+rand(0,2);
		//var_dump($bonus);
		$Character->character['maxHp'] = $maxHp;
		$Character->character['hp'] = $maxHp;
		$Character->character['morale'] = $morale;
		//what is the best skill ?
		//$pilot->character->scores['rank']=self::$warriorRank;
		foreach( $Character->character->scores->skill as $skill ){
			$type = (string)$skill['type'];
			if ($type=='hp'){
				$skill['initial'] = $maxHp;
			}
			else{
				$tmp=round((($skill['initial']+2*$target_level)/3)+$bonus[$type],0);
				if ( $skill['initial'] < $tmp  ){
				$skill['initial'] = $tmp;
				}
			}
		}
		if ( $rename ){
			$tmp = str_ireplace(  array_keys($bonus), '', $Character->character['name']);
			$tmp = str_ireplace(', ', '', $tmp);
			echo $Character->character['name'] = ucfirst($speciality).', '.$tmp, "\n";
		}
		return $speciality;
	}






}//class
