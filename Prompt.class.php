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

//require_once 'autoload.inc.php';


define('ZENITY','/usr/bin/zenity');
define('ZENITY_NOTIFICATION',ZENITY.' --info --text ');
define('ZENITY_ERROR',ZENITY.' --error --text ');
define('ZENITY_FILESELECT',ZENITY.' --file-selection --save --confirm-overwrite ');
define('ZENITY_FILEOPEN',ZENITY.' --file-selection  ');
define('ZENITY_DATESELECT',ZENITY.'--calendar --title "Pick a day" --text ');
define('CHANGE_DIRECTORY','cd');

Class Prompt
{
	protected $in;
	protected $emergencyQuit = true;
	static $useZenity = true;//<--- DON'T CHANGE HERE FOR NO ZENITY
	static $quit = 'quit';//this is a "magic" word. do what you want


	function __construct($in = STDIN)
	{
		$this->in = $in;
		$this->emergencyQuit;
	}

	/**
	 *
	 **/
	function chooseBest($question,$suggestions)
	{
		$num = array_values($suggestions);
		do
		{
			$c = true;
			$k = $this->displaySuggestions($question,$num);
			$answer = trim( $this->readline());
			if ( strlen($answer)!=0 and  sscanf($answer,'%d',$a)  )
			{
				if ( 0 <= $a and $a <= $k )
				{
					$c = false;
				}
			}
		}while($c);

		return $this->returnOneSuggestion($suggestions,$a);
	}

	/**
	 *
	 **/
	function saveAs($directory=null)
	{
		$cd = empty($directory)?'':CHANGE_DIRECTORY.' '.$directory.';';
		if ( self::$useZenity )
		{
			$answer = trim(shell_exec(ZENITY_FILESELECT));
//			var_dump($answer);
		}
		else
		{
			do
			{
				echo 'Save as ?',PHP_EOL;
				$answer = $this->readline();
				$b = $this->confirm('Confirm "'.$answer.'"');
			}while(!$b);
		}
		return $answer;
	}


	function openFile($directory=null)
	{
		$cd = empty($directory)?'':CHANGE_DIRECTORY.' '.$directory.';';

		if ( self::$useZenity )
		{
			$answer = trim(shell_exec($cd.ZENITY_FILEOPEN));
//			var_dump($answer);
		}
		else
		{
			do
			{
				echo 'Open which file ?',PHP_EOL;
				$answer = $this->readline();
				$b = ( $this->confirm('Confirm "'.$answer.'"') and file_exists($answer) );
				if ( $b = ($b and is_readable($answer) ) )
				{
					echo 'file not readable...';
				}
			}while(!$b);
		}
		return $answer;
	}


	/**
	 *
	 **/
	function confirm($question)
	{
		$a = false;
		do
		{
			echo $question,' [yes/no]?',PHP_EOL;
			$answer = $this->readline();
			$c = ( ($a = preg_match('/^ye?s?$/i',$answer)) or preg_match('/^no?$/i',$answer) );
		}while(! $c);
		return $a;
	}




	/**
	 *
	 **/
	protected function displaySuggestions($question, $suggestions )
	{
		echo $question,PHP_EOL;
		$k = 0;
		foreach($suggestions as $k => $values)
		{
			echo '[',$k,"]\t",$values,PHP_EOL;
		}
		echo 'please, choose [0 to ',$k,']',PHP_EOL;
		return $k;
	}

	/**
	 *
	 **/
	function readline()
	{
		$tmp = fgets( $this->in ) ;
		$tmp = trim($tmp);
//		var_dump($tmp);
//		var_dump($this->emergencyQuit);
		if ( $this->emergencyQuit and $tmp == self::$quit )
		{
			throw new Exception('emergency quit requested');
		}
		return $tmp;
	}

	function pause($txt = 'press enter to continue...')
	{
		echo $txt,PHP_EOL;
		$this->readline();
	}


	function question($questionText)
	{
		echo $questionText,PHP_EOL;
		return $this->readline();
	}


	protected function returnOneSuggestion($suggestions, $permutation)
	{
		reset( $suggestions );
		for( $it = 0; $it < $permutation ; $it++ )
		{
			next($suggestions);
		}
		return array(key($suggestions) => current($suggestions) );
	}

	/**
	 *
	 **/
	function setEmergencyQuit($a)
	{
		$this->emergencyQuit = $a?true:false;
		return $this;
	}



}
