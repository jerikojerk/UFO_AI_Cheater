#!/usr/bin/php
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

//CHANGE THIS ACCORDING WHAT YOU HAVE.
require_once 'EnhancedXMLElement.class.php';
require_once 'Prompt.class.php';
require_once 'UfoSavegame.php';


//FOR DEVEL USAGE...
//require_once 'Debug.class.php';

// CHANGE HERE FOR NO ZENITY (comment and uncomment)
Prompt::$useZenity = true;
//Prompt::$useZenity = false;

/**
 *
 **/
function print_usage()
{
	echo __FILE__,' [FILE]',PHP_EOL;
	echo "\t",'the script is interactive now...',PHP_EOL;
}


/***
 *
 **/
function controler(object $o )
{
	$tab = get_class_methods($o);

}


/**
 *
 **/
function error($text)
{
	echo "----\n", $text,"\n---\n";
}


/***
 *
 *
 **/
function main_internal(UfoAiGame &$game)
{
	$menu = array(
	'quit'	=>	'Quit editor (and save)',
	'change_difficulty'	=>	'game...... change difficulty level',
	'more_money'		=>	'game...... gives you more money',
	'update_nation'		=>	'game...... improve happiness, remove XVI.',
	'reduce_interest'	=>  'game...... reduce alien interest',
	'add_civilian'		=>	'employee.. clone some civilians in your bases',
	'equip_soldier'		=>	'employee.. equip soldier with template package',
	'unequip_soldier'	=>	'employee.. UNequip soldier ',
	'forgive_soldier'	=>	'employee.. forgive the civilian\'s slaughter',
	'update_soldier'	=>	'employee.. update soldier - transform soldiers into supersoldats',
	'update_pilot'		=>	'employee.. update pilot - transform pilots into supersoldats (unstable).',
	'sort_employee'		=>	'employee.. sort employee ',
	'fire_civilian'		=>	'employee.. fire civilian (ask for confirm before...)',
	'show_building'		=>	'base...... show the building in a base',
	'move_building'		=>	'base...... move building',
	'finish_building'	=>	'base...... finish the buildings that are in construction.',
	'remove_granit' 	=>	'base...... remove granit blocks form buildspace...(do this before moving buildings!)',
	'fix_building_position'	=>	'base...... fix building position force <buildingspace> to <buildings> values (only to fix manual edit).',
	'read'				=>	'reload savegame from file',
	'save'				=>	'save modification to file',
	'printToFile'		=>	'pretty xml export in file (this is different from saving)',
	'importFromFile'	=>	'replace xml tree (within the savegame) by the one specified (into xml file)',
	'fix_missionid'		=>	'fix missionid - expert use only');


	$prompt = new Prompt();
	echo 'Welcome in this cheater program. You may write "'.Prompt::$quit.'" anytime to quit without saving.',"\n\n";
	$prompt->pause();

	while(1){
		$answer = $prompt->chooseBest('Select your action ',$menu);
//		var_dump($answer);
		$method = key($answer);
		if( $method == 'quit' ){
			break;
		}
		elseif ( $method == 'printToFile' ){
			$filename = $prompt->saveAs();
			if ( !empty($filename) ){
				$game->printToFile($filename);
			}
		}
		elseif ( $method == 'importFromFile' ){
			$filename = $prompt->openFile();
			if ( !empty($filename) ){
				$game->import($filename);

			}
		}
		else{
			$game->$method();
		}
	}
}

function identifySavegame() {
	//var_dump($_SERVER['argv']);
	if ( count($_SERVER['argv'])>1){
		$filename = $_SERVER['argv'][1];
	}

	if ( empty( $filename )){
		$filename=UfoAiSave::selectSaveGame();
	}
	else {
	//	if ($filename[0] != '/' ) $filename = getcwd().'/'.$filename;
		$test = file_exists($filename) ;

		if ( !$test ){
			error("fichier xml pas trouvé in ".getcwd());
			print_usage();
			exit();
		}

	}
	return $filename ;
}

/**
 *
 **/
function main()
{
	try{
		$filename=identifySavegame();

	}
	catch ( Exception $e ){
		die( '!Detected:'. $e->getMessage().PHP_EOL);
	}

	$file=new UfoAiSave($filename);
	$game = new UfoAiGame($file);

	try
	{
		main_internal($game);
	}
	catch(Exception $e)
	{
		//what did i?
		$game->saveDemoMode();
	}

}


main();
