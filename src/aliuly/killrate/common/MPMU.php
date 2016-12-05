<?php
namespace aliuly\killrate\common;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;
use pocketmine\utils\MainLogger;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\command\PluginCommand;

use aliuly\killrate\common\mc;

/**
 * My PocketMine Utils class
 */
abstract class MPMU {
	/** @var str[] $items Nice names for items */
	static protected $items = [];
	/** @const str VERSION plugin version string */
	const VERSION = "1.1.0";

	/**
	 * libcommon library version.  If a version is provided it will check
	 * the version using apiCheck.
	 *
	 * @param str version Version to check
	 *
	 * @return str|bool
	 */
	static public function version($version = "") {
		if ($version == "") return self::VERSION;
		return self::apiCheck(self::VERSION,$version);
	}
	/**
	 * Used to check the PocketMine API version
	 *
	 * @param str version Version to check
	 *
	 * @return str|bool
	 */
	static public function apiVersion($version = "") {
		if ($version == "") return \pocketmine\API_VERSION;
		return self::apiCheck(\pocketmine\API_VERSION,$version);
	}
	/**
	 * Checks API compatibility from $api against $version.  $version is a
	 * string containing the version.  It can contain the following operators:
	 *
	 * >=, <=, <> or !=, =, !|~, <, >
	 *
	 * @param str api Installed API version
	 * @param str version API version to compare against
	 *
	 * @return bool
	 */
	static public function apiCheck($api,$version) {
		switch (substr($version,0,2)) {
			case ">=":
				return version_compare($api,trim(substr($version,2))) >= 0;
			case "<=":
				return version_compare($api,trim(substr($version,2))) <= 0;
			case "<>":
			case "!=":
				return version_compare($api,trim(substr($version,2))) != 0;
		}
		switch (substr($version,0,1)) {
			case "=":
				return version_compare($api,trim(substr($version,1))) == 0;
			case "!":
			case "~":
				return version_compare($api,trim(substr($version,1))) != 0;
			case "<":
				return version_compare($api,trim(substr($version,1))) < 0;
			case ">":
				return version_compare($api,trim(substr($version,1))) > 0;
		}
		if (intval($api) != intval($version)) return 0;
		return version_compare($api,$version) >= 0;
	}
	/**
	 * Returns a localized string for the gamemode
	 *
	 * @param int mode
	 * @return str
	 */
	static public function gamemodeStr($mode) {
		if (class_exists(__NAMESPACE__."\\mc",false)) {
			switch ($mode) {
				case 0: return mc::_("Survival");
				case 1: return mc::_("Creative");
				case 2: return mc::_("Adventure");
				case 3: return mc::_("Spectator");
			}
			return mc::_("%1%-mode",$mode);
		}
		switch ($mode) {
			case 0: return "Survival";
			case 1: return "Creative";
			case 2: return "Adventure";
			case 3: return "Spectator";
		}
		return "$mode-mode";
	}
	/**
	 * Check's player or sender's permissions and shows a message if appropriate
	 *
	 * @param CommandSender $sender
	 * @param str $permission
	 * @param bool $msg If false, no message is shown
	 * @return bool
	 */
	static public function access(CommandSender $sender, $permission,$msg=true) {
		if($sender->hasPermission($permission)) return true;
		if ($msg)
			$sender->sendMessage(mc::_("You do not have permission to do that."));
		return false;
	}
	/**
	 * Check's if $sender is a player in game
	 *
	 * @param CommandSender $sender
	 * @param bool $msg If false, no message is shown
	 * @return bool
	 */
	static public function inGame(CommandSender $sender,$msg = true) {
		if (!($sender instanceof Player)) {
			if ($msg) $sender->sendMessage(mc::_("You can only do this in-game"));
			return false;
		}
		return true;
	}
	/**
	 * Takes a player and creates a string suitable for indexing
	 *
	 * @param Player|str $player - Player to index
	 * @return str
	 */
	static public function iName($player) {
		if ($player instanceof Player) {
			$player = strtolower($player->getName());
		}
		return $player;
	}
	/**
	 * Lile file_get_contents but for a Plugin resource
	 *
	 * @param Plugin $plugin
	 * @param str $filename
	 * @return str|null
	 */
	static public function getResourceContents($plugin,$filename) {
		$fp = $plugin->getResource($filename);
		if($fp === null){
			return null;
		}
		$contents = stream_get_contents($fp);
		fclose($fp);
		return $contents;
	}
	/**
	 * Call a plugin's function
	 *
	 * @param Server $server - pocketmine server instance
	 * @param str $plug - plugin to call
	 * @param str $method - method to call
	 * @param mixed $default - If the plugin does not exist or it is not enable, this value uis returned
	 * @return mixed
	 */
	static public function callPlugin($server,$plug,$method,$args,$default = null) {
		if (($plugin = $server->getPluginManager()->getPlugin($plug)) !== null
			 && $plugin->isEnabled()) {
			$fn = [ $plugin, $method ];
			return $fn(...$args);
		}
		return $default;
	}
	/**
	 * Register a command
	 *
	 * @param Plugin $plugin - plugin that "owns" the command
	 * @param CommandExecutor $executor - object that will be called onCommand
	 * @param str $cmd - Command name
	 * @param array $yaml - Additional settings for this command.
	 */
	static public function addCommand($plugin, $executor, $cmd, $yaml) {
		$newCmd = new PluginCommand($cmd,$plugin);
		if (isset($yaml["description"]))
			$newCmd->setDescription($yaml["description"]);
		if (isset($yaml["usage"]))
			$newCmd->setUsage($yaml["usage"]);
		if(isset($yaml["aliases"]) and is_array($yaml["aliases"])) {
			$aliasList = [];
			foreach($yaml["aliases"] as $alias) {
				if(strpos($alias,":")!== false) {
					$this->owner->getLogger()->info("Unable to load alias $alias");
					continue;
				}
				$aliasList[] = $alias;
			}
			$newCmd->setAliases($aliasList);
		}
		if(isset($yaml["permission"]))
			$newCmd->setPermission($yaml["permission"]);
		if(isset($yaml["permission-message"]))
			$newCmd->setPermissionMessage($yaml["permission-message"]);
		$newCmd->setExecutor($executor);
		$cmdMap = $plugin->getServer()->getCommandMap();
		$cmdMap->register($plugin->getDescription()->getName(),$newCmd);
	}
	/**
	 * Unregisters a command
   * @param Server|Plugin $obj - Access path to server instance
	 * @param str $cmd - Command name to remove
	 */
	static public function rmCommand($srv, $cmd) {
		$cmdMap = $srv->getCommandMap();
		$oldCmd = $cmdMap->getCommand($cmd);
		if ($oldCmd === null) return false;
		$oldCmd->setLabel($cmd."_disabled");
		$oldCmd->unregister($cmdMap);
		return true;
	}
	/**
	 * Send a PopUp, but takes care of checking if there are some
	 * plugins that might cause issues.
	 *
	 * Currently only supports SimpleAuth and BasicHUD.
	 *
	 * @param Player $player
	 * @param str $msg
	 */
	static public function sendPopup($player,$msg) {
		$pm = $player->getServer()->getPluginManager();
		if (($sa = $pm->getPlugin("SimpleAuth")) !== null) {
			// SimpleAuth also has a HUD when not logged in...
			if ($sa->isEnabled() && !$sa->isPlayerAuthenticated($player)) return;
		}
		if (($hud = $pm->getPlugin("BasicHUD")) !== null) {
			// Send pop-ups through BasicHUD
			$hud->sendPopup($player,$msg);
			return;
		}
		$player->sendPopup($msg);
	}


}
