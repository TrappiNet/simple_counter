<?php

/**
* @author    Eric Sizemore <admin@secondversion.com>
* @package   SV's Simple Counter
* @link      http://www.secondversion.com
* @version   3.0.0
* @copyright (C) 2006 - 2012 Eric Sizemore
* @license   GNU Lesser General Public License
*
*	SV's Simple Counter is free software: you can redistribute it and/or modify
*	it under the terms of the GNU Lesser General Public License as published by
*	the Free Software Foundation, either version 3 of the License, or
*	(at your option) any later version.
*
*	This program is distributed in the hope that it will be useful, but WITHOUT 
*	ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
*	FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public License for more 
*	details.
*
*	You should have received a copy of the GNU Lesser General Public License
*	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
* Just starting to branch out to namespaces, etc. Please be gentle, this is only a start.
*/
namespace SimpleCounter;

class Counter
{
	/** User Configuration **/
	// There should be no need to edit these
	const COUNT_FILE  = 'counter/logs/counter.txt';
	const IP_FILE     = 'counter/logs/ips.txt';

	// Use file locking?
	const USE_FLOCK   = true;

	// Count only unique visitors?
	const ONLY_UNIQUE = true;

	// Show count as images?
	const USE_IMAGES  = false;

	// Path to the images
	const IMAGE_DIR   = 'counter/images/';

	// Image extension
	const IMAGE_EXT   = '.gif';
	/** End User Configuration **/

	//
	private static $instance;

	//
	private function __construct() {}

	//
	public static function getInstance()
	{
		if (!self::$instance)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	//
	private function getIpAddress()
	{
		$ip = $_SERVER['REMOTE_ADDR'];

		if ($_SERVER['HTTP_X_FORWARDED_FOR'])
		{
			if (preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches))
			{
				foreach ($matches[0] AS $match)
				{
					if (!preg_match('#^(10|172\.16|192\.168)\.#', $match))
					{
						$ip = $match;
						break;
					}
				}
				unset($matches);
			}
		}
		else if ($_SERVER['HTTP_CLIENT_IP'])
		{
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		else if ($_SERVER['HTTP_FROM'])
		{
			$ip = $_SERVER['HTTP_FROM'];
		}
		return $ip;
	}

	/**
	* We use this function to open, read/write to files.
	*
	* @param  string   Filename
	* @param  string   Mode (r, w, a, etc..)
	* @param  string   If writing to the file, the data to write
	* @return mixed
	*/
	private function readWriteFile($file, $mode, $data = '')
	{
		if (!file_exists($file) OR !is_writable($file))
		{
			throw new \Exception("\SimpleCounter\Counter\\readWriteFile() - '$file' does not exist or is not writable.");
		}

		if (!($fp = @fopen($file, $mode)))
		{
			throw new \Exception("\SimpleCounter\Counter\\readWriteFile() - '$file' could not be opened.");
		}

		if (self::USE_FLOCK AND @flock($fp, LOCK_EX))
		{
			if ($mode == 'r')
			{
				return @fread($fp, @filesize($file));
			}
			else
			{
				@fwrite($fp, $data);
			}
			@flock($fp, LOCK_UN);
		}
		else
		{
			if ($mode == 'r')
			{
				return @fread($fp, filesize($file));
			}
			@fwrite($fp, $data);
		}
		@fclose($fp);
	}

	//
	public function process()
	{
		$display = '';

		$count = self::readWriteFile(self::COUNT_FILE, 'r');

		// Do we only want to count 'unique' visitors?
		if (self::ONLY_UNIQUE)
		{
			$ip = self::getIpAddress();

			$ips = trim(self::readWriteFile(self::IP_FILE, 'r'));
			$ips = preg_split("#\n#", $ips, -1, PREG_SPLIT_NO_EMPTY);

			// They've not visited before
			if (!in_array($ip, $ips))
			{
				self::readWriteFile(self::IP_FILE, 'a', "$ip\n");
				self::readWriteFile(self::COUNT_FILE, 'w', $count + 1);
			}
			unset($ips);
		}
		else
		{
			// No, we wish to count all visitors
			self::readWriteFile(self::COUNT_FILE, 'w', $count + 1);
		}

		// Do we want to display the # visitors as graphics?
		if (self::USE_IMAGES)
		{
			$count = preg_split("##", $count, -1, PREG_SPLIT_NO_EMPTY);
			$length = count($count);

			for ($i = 0; $i < $length; $i++)
			{
				$display .= '<img src="' . self::IMAGE_DIR . $count[$i] . self::IMAGE_EXT . '" border="0" alt="' . $count[$i] . '" />&nbsp;';
			}
		}
		else
		{
			// Nope, let's just show it as plain text
			$display = $count;
		}
		echo $display;
	}
}

\SimpleCounter\Counter::getInstance()->process();