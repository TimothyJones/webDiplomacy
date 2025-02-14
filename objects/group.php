<?php
/*
    Copyright (C) 2004-2010 Kestas J. Kuliukas

	This file is part of webDiplomacy.

    webDiplomacy is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    webDiplomacy is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with webDiplomacy.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once(l_r('objects/basic/set.php'));

/**
 * An object representing a relationship between users. Many to many between users and groups,
 * intended for associating people who know each other in real life, to allow people who know
 * each other to play together in a transparent way. Also can be used to allow users to associate
 * with one another, moderators to group people 
 * 
 *
 * @package Base
 */
class Group
{
	/**
	 * The group ID
	 * @var int
	 */
	var $id;

	/**
	 * @var string
	 */
	var $name;

    static $validTypes = array('Person','Family','School','Work','Other','Unknown');

	/**
	 * The game ID, if applicable
	 * @var int|null
	 */
	var $gameID;

	/**
	 * The type of group
     * 
	 * @var string
	 */
	var $type;

	/**
	 * Is active
     * 
	 * @var bool
	 */
	var $isActive;

	/**
	 * Text with info about the group from the creator
     * 
	 * @var string
	 */
	var $description;
    
	/**
	 * Any notes from moderators
     * 
	 * @var string
	 */
	var $moderatorNotes;

	/**
	 * When was this group creasted
     * @var int
	 */
	var $timeCreated;

	/**
	 * When was this group last changed. This is used to detect what tags on users and members need updating
     * @var int
	 */
	var $lastChanged;

	/**
	 * Who owns this group
     * @var int
	 */
	var $ownerUserID;

	/**
	 * Who owns this group, the country ID if the owner is from a game
     * @var int|null
	 */
	var $ownerCountryID;

	/**
	 * The variant ID of the game, if applicable
	 * @var int|null
	 */
	var $variantID;

	/**
	 * Is the game anonymous 'Yes'/'No'/NULL
	 * @var string|null
	 */
	var $anon;

	/**
	 * An array of GroupUser objects for this group
	 * 
	 * @var GroupUser[]
	 */
	var $GroupUsers;

	/**
	 * Who owns this group, the country ID if the owner is from a game
     * @var int|null
	 */
	var $ownerUsername;

	/**
	 * Who owns this group, the country ID if the owner is from a game
     * @var int|null
	 */
	var $modUsername;

	/**
	 * This is called if a suspicion is submitted directly from a game. The intention is that when created this way 
	 * suspicions can be created for anonymous games, tying them to the user IDs while keeping them anonymous by
	 * linking to the gameID and getting the country.
	 * 
	 * @return int ID of the group created
	 */
	static function createSuspicionFromGame($gameID, $countriesSuspected, $suspicionStrength, $explanation, $suspectingCountryID = null )
	{
		global $DB, $User;

		$gameID = (int)$gameID;
		$filteredCountriesSuspected = array();
		foreach($countriesSuspected as $countrySuspected)
			$filteredCountriesSuspected[] = (int)$countrySuspected;
		$countriesSuspected = $filteredCountriesSuspected;
		unset($filteredCountriesSuspected);
		$suspicionStrength = (int) $suspicionStrength;
		// $explanation = Pass this in as-is, will be filtered within
		
		// Take the gameID and countries and get the user IDs
		$Variant = libVariant::loadFromGameID($gameID);
		$Game = $Variant->Game($gameID);

		$suspectedUsers = array();
		foreach($countriesSuspected as $countrySuspected)
		{
			$suspectedUsers[$countrySuspected] = new User($Game->Members->ByCountryID[$countrySuspected]->userID);
		}
	
		$groupID = self::create('Unknown', $Game->name . ' - #' . $Game->turn, $explanation, $Game->id, $Game->id, $suspectingCountryID);
		$Group = new Group($groupID);
		foreach($suspectedUsers as $countrySuspected=>$suspectedUser)
		{
			$Group->userAdd($User, $suspectedUser, $suspicionStrength, $countrySuspected);
		}

		return $groupID;
	}



	/**
	 * Validate the inputs and permissions and create a group in the DB, returning an ID, or else throw exception
	 * @return int ID of the group created
	 */
	static function create($groupType, $groupName, $groupDescription, $groupGameReference, $gameID = null, $createdByCountryID = null)
	{
		global $User, $DB;
		if( Group::canUserCreate($User) )
		{
			if( in_array($groupType, Group::$validTypes, true) )
			{
				$groupGameReference = $DB->msg_escape($groupGameReference);
				$groupDescription = $DB->msg_escape($groupDescription);
				if( strlen($groupDescription) < 5 )
				{
					throw new Exception("Description / explanation does not contain enough detail, please enter a description / explanation.");
				}
				if( $groupType === 'Unknown' && ( strlen($groupGameReference) < 5 && !$User->type['Moderator'] ) )
				{
					throw new Exception("Please select a game you are actively/recently playing against this user, which is causing you to suspect the user.");
				}
				$groupDescription .= '<br />Game Reference: ' . $groupGameReference;

				$groupName = $DB->msg_escape($groupName);
				$DB->sql_put("INSERT INTO wD_Groups (`name`,isActive,`type`,`display`,ownerUserID,timeCreated,timeChanged,`description`,`gameID`,ownerCountryID) VALUES ('".$groupName."',1,'" .$groupType ."','Moderators',".$User->id.",".time().",".time().",'".$groupDescription."',".($gameID == null ? "NULL" : $gameID).",".($createdByCountryID == null ? "NULL" : $createdByCountryID).")");
				list($groupID) = $DB->sql_row("SELECT LAST_INSERT_ID()");
				return $groupID;
			}
			throw new Exception("Group type provided is invalid.");
		}
		throw new Exception("User does not have permission to create groups.");
	}
	public function userAdd($userAdding, $userToBeAdded, $groupUserStrength = 0, $countrySuspected = null)
	{
		global $DB;
		if( !$this->canUserAdd($userAdding, $userToBeAdded) )
		{
			throw new Exception("User does not have permission to add given user.");
		}

		$groupUserStrength = intval($groupUserStrength);
		if( $groupUserStrength < 0 ) $groupUserStrength = 0;
		if( $groupUserStrength > 100 ) $groupUserStrength = 100;

		$userWeighting = 0;
		$ownerWeighting = 0;
		$modWeighting = 0;
		if( $userAdding->type['Moderator'] ) $modWeighting = $groupUserStrength;
		if( $userAdding->id == $userToBeAdded->id ) $userWeighting = $groupUserStrength;
		if( $userAdding->id == $this->ownerUserID ) $ownerWeighting = $groupUserStrength;
		
		$DB->sql_put("INSERT INTO wD_GroupUsers (userID, groupID, isActive, userWeighting, ownerWeighting, modWeighting, timeChanged, timeCreated, countryID) VALUES (".
			$userToBeAdded->id.", ". 
			$this->id.", ".
			"1, ".$userWeighting.", ".$ownerWeighting.", ".$modWeighting.", ".
			time().", ".
			time().", ".
			($countrySuspected == null ? "NULL":$countrySuspected).
			") ON DUPLICATE KEY UPDATE timeChanged = VALUES(timeChanged)");
	}

	static private function canUserCreate($User)
	{
		return ( $User->type['User'] || $User->type['Moderator']);
	} 
	private function canUserAdd($userAdding, $userToBeAdded)
	{
		if ( !$this->canUserModify($userAdding) ) return false;

		if( $userToBeAdded->type['User'] ) return true;

		return false;
	}
	private function canUserModify($userModifying)
	{
		if( $userModifying->type['Moderator'] ) return true;

		if( !$userModifying->type['User'] ) return false;

		if( $this->ownerUserID == $userModifying->id ) return true;

		return false;
	}
	public function userSetDescription($userModifying, $groupDescription)
	{
		global $DB;

		if( !$this->canUserModify($userModifying) ) throw new Exception("Permission denied for description update.");
		
		$groupDescription = $DB->msg_escape($groupDescription);

		$DB->sql_put("UPDATE wD_Groups SET `description` = '" . $groupDescription . "' WHERE id = " . $this->id);
	}
	public function userSetModNotes($userModifying, $modNotes)
	{
		global $DB;

		if( !$userModifying->type['Moderator'] ) throw new Exception("Permission denied for mod notes update.");
		
		$modNotes = $DB->msg_escape($modNotes . "-" . $userModifying->username);

		$DB->sql_put("UPDATE wD_Groups SET `moderatorNotes` = '" . $modNotes . "' WHERE id = " . $this->id);
	}
	public function userSetActive($userModifying, $groupActive)
	{
		global $DB;

		if( !$this->canUserModify($userModifying) ) throw new Exception("Permission denied for active update.");
		
		$groupActive = intval($groupActive) ? 1 : 0;

		$DB->sql_put("UPDATE wD_Groups SET `isActive` = '" . $groupActive . "' WHERE id = " . $this->id);
	}
	private function canUserUpdateUserWeighting($userUpdating, $groupUserToUpdate)
	{
		return ( $userUpdating->id == $groupUserToUpdate->userID );
	}
	private function canUserUpdateOwnerWeighting($userUpdating)
	{
		return ( $userUpdating->id == $this->ownerUserID );
	}
	private function canUserUpdateModWeighting($userUpdating)
	{
		return $userUpdating->type['Moderator'];
	}
	public function userUpdateUserWeighting($userUpdating, $groupUserToUpdate, $newWeighting)
	{
		global $DB;

		$newWeighting = self::getClosestWeighting($newWeighting);
		if( $groupUserToUpdate->userWeighting == $newWeighting ) return;
		
		if( $this->canUserUpdateUserWeighting($userUpdating, $groupUserToUpdate) )
		{
			$DB->sql_put("UPDATE wD_GroupUsers SET userWeighting = " . $newWeighting . ", timeChanged = ".time()." WHERE userID = ". $groupUserToUpdate->userID." AND groupID = ".$groupUserToUpdate->groupID);
		}
	}
	public function userUpdateOwnerWeighting($userUpdating, $groupUserToUpdate, $newWeighting)
	{
		global $DB;

		$newWeighting = self::getClosestWeighting($newWeighting);
		if( $groupUserToUpdate->ownerWeighting == $newWeighting ) return;
		
		if( $this->canUserUpdateOwnerWeighting($userUpdating, $groupUserToUpdate) )
		{
			$DB->sql_put("UPDATE wD_GroupUsers SET ownerWeighting = " . $newWeighting . ", timeChanged = ".time()." WHERE userID = ". $groupUserToUpdate->userID." AND groupID = ".$groupUserToUpdate->groupID);
		}
	}
	public function userUpdateModWeighting($userUpdating, $groupUserToUpdate, $newWeighting)
	{
		global $DB;

		$newWeighting = self::getClosestWeighting($newWeighting);
		if( $groupUserToUpdate->modWeighting == $newWeighting ) return;
		
		if( $this->canUserUpdateModWeighting($userUpdating, $groupUserToUpdate) )
		{
			$DB->sql_put("UPDATE wD_GroupUsers SET modWeighting = " . $newWeighting . ", modUserID = ".$userUpdating->id.", timeChanged = ".time()." WHERE userID = ". $groupUserToUpdate->userID." AND groupID = ".$groupUserToUpdate->groupID);
		}
	}
	public function canUserComment($userCommenting)
	{
		if( $userCommenting->type['Moderator'] ) return true;

		if( $this->ownerUserID == $userCommenting->id ) return true;

		foreach( $this->GroupUsers as $groupUser )
		{
			if( $groupUser->userID == $userCommenting->id ) return true;
		}

		return false;
	}
	
	/**
	 * Create a Group object
	 * @param int|array $id Group id, or an array containing the group data
	 */
	public function __construct($id)
	{
		global $DB;

		if( !is_array($id) )
		{
			$row = $DB->sql_hash("SELECT 
				gr.id, 
				gr.`name`, 
				gr.`type`, 
				gr.isActive, 
				gr.`description`, 
				gr.moderatorNotes, 
				gr.timeCreated, 
				gr.timeChanged, 
				gr.ownerUserID, 
				gr.gameID, 
				gr.ownerCountryID,
				g.variantID,
				g.anon
			FROM wD_Groups gr 
			LEFT JOIN wD_Games g ON g.id = gr.gameID 
			WHERE gr.id = " . intval($id));
				
			if( !$row ) throw new Exception("Group ID not found.");

			if( $row['gameID'] != null )
			{
				$Variant = libVariant::loadFromGameID($row['gameID']);
				$Game = $Variant->Game($row['gameID']);
			}			
		}
		else
		{
			$row = $id;
		}

		foreach ( $row as $name => $value )
		{
			$this->{$name} = $value;
		}

		$this->loadUsers();
	}
	private function loadUsers()
	{
		$this->GroupUsers = self::getUsers("gr.id = " . $this->id, $this);
	}
	// This function has to operate to get the users for a group page with full details,
	// and also get a list of group-users for multiple groups to show on a user profile page etc
	public static function getUsers($whereClause, $Group = null)
	{
		global $DB, $User;

		// This is pretty nasty because it associates relations between user IDs, but for anonymous games it has to only show the country info and hide any user ID info,
		// but internally it has to be user ID bound or else a user could take over a country and also take over the suspicion.
		$users = $DB->sql_tabl("SELECT g.userID, g.countryID, g.groupID, g.isActive, g.userWeighting, g.ownerWeighting, g.modWeighting, g.timeCreated, g.timeChanged, g.modUserID, ".
			"gr.id Group_id, ".
			"gr.name Group_name, ".
			"gr.type Group_type, ".
			"gr.isActive Group_isActive, ".
			"gr.gameID Group_gameID, ".
			"gr.display Group_display, ".
			"gr.timeCreated Group_timeCreated, ".
			"gr.ownerUserID Group_ownerUserID, ".
			"gr.ownerCountryID Group_ownerCountryID, ".
			"gr.timeChanged Group_timeChanged, ".
			"u.username userUsername, ".
			"o.username Group_ownerUsername, ".
			"m.username Group_modUsername ".
			"FROM wD_GroupUsers g ".
			"INNER JOIN wD_Groups gr ON gr.id = g.groupID ".
			"INNER JOIN wD_Users u ON u.id = g.userID ".
			"INNER JOIN wD_Users o ON o.id = gr.ownerUserID ".
			"LEFT JOIN wD_Users m ON m.id = g.modUserID ".
			"LEFT JOIN wD_Games game ON game.id = gr.gameID ".
			"LEFT JOIN wD_Members ucountry ON ucountry.gameID = gr.gameID AND ucountry.userID = g.userID ".
			"LEFT JOIN wD_Members ocountry ON ocountry.gameID = gr.gameID AND ocountry.userID = gr.ownerUserID ".
			"LEFT JOIN wD_Members modcountry ON modcountry.gameID = gr.gameID AND modcountry.userID = ".($User->type['Moderator'] ? $User->id : -1 )." ".
			// Either this isn't game related, or it's a non-anonymous game, or the user is a moderator who isn't in the game:
			"WHERE ( ( game.id IS NULL OR game.anon='No' ) ".($User->type['Moderator'] ? " OR modcountry.id IS NULL " : "")." ) ".
			// And whatever else (e.g. show all the user's suspicions and relations if on the user's relation page,
			// or show all the user's suspicions if another user is looking at their profile)
			"AND (".$whereClause.")");
			// Don't show records relating to anonymous games unless the viewer is a mod
		$groupsCache = array();
		$groupUsers = array();
		while($userRec = $DB->tabl_hash($users) )
		{
			if( $Group == null )
			{
				if( !isset($groupsCache[$userRec['Group_id']]) )
				{
					$groupRec = array();
					foreach($userRec as $key=>$value)
					{
						if( strlen($key) > 6 && substr($key,0,6) == 'Group_' )
						{
							$groupRec[substr($key, 6)] = $value;
						}
					}
					$groupsCache[$userRec['Group_id']] = new Group($groupRec);
				}
				$currentGroup = $groupsCache[$userRec['Group_id']];
			}
			else
			{
				$currentGroup = $Group;
			}
			$groupUsers[] = new GroupUser($userRec, $currentGroup);
		}
		return $groupUsers;
	}
	public static function ownedGroupNamesByID($User, $activeOnly = true)
	{
		global $DB;
		
		$groups = $DB->sql_tabl('SELECT id, `name`, `type` FROM wD_Groups WHERE ownerUserID = '.$User->id.($activeOnly?' AND isActive = 1 ':' ').' ORDER BY timeChanged DESC');
		$groupNames = array();
		while($row = $DB->tabl_hash($groups))
		{
			$groupNames[$row['id']] = '#'.$row['id'] . ' ' . $row['name'] . ' - ' . $row['type'];
		}

		return $groupNames;
	}

	/**
	 * Declared group names that this user is in
	 */
	public static function declaredGroupNamesByID($User, $activeOnly = true)
	{
		global $DB;
		
		$groups = $DB->sql_tabl('SELECT g.id, g.`name`, g.`type` FROM wD_Groups g INNER JOIN wD_GroupUsers u ON u.groupID = g.id WHERE u.userID = '.$User->id.($activeOnly?' AND g.isActive = 1 AND u.isActive = 1 ':' ').' AND `type`<>"Unknown" AND (modWeighting > 0.0 OR userWeighting > 0.0)  ORDER BY g.timeChanged DESC');
		$groupNames = array();
		while($row = $DB->tabl_hash($groups))
		{
			$groupNames[$row['id']] = '#'.$row['id'] . ' ' . $row['name'] . ' - ' . $row['type'];
		}

		return $groupNames;
	}
	/**
	 * Suspected groups that this user has created
	 */
	public static function suspectedGroupNamesByID($User, $activeOnly = true)
	{
		global $DB;
		
		$groups = $DB->sql_tabl('SELECT id, `name` FROM wD_Groups WHERE ownerUserID = '.$User->id.($activeOnly?' AND isActive = 1 ':' ').' AND `type`="Unknown" ORDER BY id DESC');
		$groupNames = array();
		while($row = $DB->tabl_hash($groups))
		{
			$groupNames[$row['id']] = '#'.$row['id'] . ' ' . $row['name'];
		}

		return $groupNames;
	}
	public static function validGroupNamesByID($User, $activeOnly = true)
	{
		global $DB;
		
		$groups = $DB->sql_tabl('SELECT g.id, g.`name`, g.`type` FROM wD_Groups g INNER JOIN wD_GroupUsers u ON u.groupID = g.id WHERE u.userID = '.$User->id.($activeOnly?' AND g.isActive = 1 AND u.isActive = 1 ':' ').' AND (modWeighting > 0.0 OR userWeighting > 0.0)  ORDER BY g.timeChanged DESC');
		$groupNames = array();
		while($row = $DB->tabl_hash($groups))
		{
			$groupNames[$row['id']] = '#'.$row['id'] . ' ' . $row['name'] . ' - ' . $row['type'];
		}

		return $groupNames;
	}
	public function outputUserTable($User = null)
	{
		$Game = null;
		if( $this->gameID != null )
		{
			// This is associated with a game; if it is an anonymous game
			// we need to ensure the user ID is not shown.
			$Variant = libVariant::loadFromGameID($this->gameID);
			$Game = $Variant->Game($this->gameID);
		}
		return self::outputUserTable_static($this->GroupUsers, $User, $Game);
	}
	public static function outputUserTable_static($groupUsers, $User = null, $Game)
	{
		$userID = -1;
		$isModerator = false;
		$creatorID = -1;

		if( $User != null )
		{
			$userID = $creatorID = $User->id;
			$isModerator = $User->type['Moderator'];	
		}
		
		$buf = '';
		$buf .= '<table class="rrInfo" style="text-align:center">';
		$buf .= '<tr><th style="text-align:right">Link / Type</th><th style="text-align:center">User / Rating</th><th style="text-align:center">Creator / Rating</th><th style="text-align:center">Moderator / Rating</th><th style="text-align:left">Created / Updated</th></tr>';
		foreach($groupUsers as $groupUser)
		{
			
			
			$buf .= '<tr>';
			$buf .= '<td style="text-align:right">';
			$buf .= '<a href="group.php?groupID='.$groupUser->groupID.'">#'.$groupUser->groupID.' '.$groupUser->Group->name.'</a>';
			$buf .= ' <br /> ';
			$buf .= $groupUser->Group->type;
			$buf .= '</td>';
			$buf .= '<td>';
			$buf .= $groupUser->userUsername; //User::profile_link_static($groupUser->userUsername, $groupUser->userID, $groupUser->userType, $groupUser->userPoints);
			$buf .= ' <br /> ';
			if( $userID == $groupUser->userID )
			{
				$buf .= self::getSelectWeighting('user', $groupUser->userID, $groupUser->userWeighting);
			}
			else
			{
				$buf .= self::getClosestWeightingName($groupUser->userWeighting);
			}
			
			$buf .= '</td>';
			$buf .= '<td>';
			$buf .= $groupUser->Group->ownerUsername; //User::profile_link_static($groupUser->ownerUsername, $groupUser->ownerUserID, $groupUser->ownerType, $groupUser->ownerPoints);
			$buf .= ' <br /> ';
			if( $userID == $groupUser->Group->ownerUserID )
			{
				$buf .= self::getSelectWeighting('owner', $groupUser->userID, $groupUser->ownerWeighting);
			}
			else
			{
				$buf .= self::getClosestWeightingName($groupUser->ownerWeighting);
			}
			
			$buf .= '</td>';
			$buf .= '<td>';
			if( $groupUser->modUserID )
			{
				$buf .= $groupUser->modUsername; //User::profile_link_static($groupUser->modUsername, $groupUser->modUserID, $groupUser->modType, $groupUser->modPoints);
			}
			else
			{
				$buf .= 'N/A';
			}
			$buf .= ' <br /> ';
			if( $isModerator )
			{
				$buf .= self::getSelectWeighting('mod', $groupUser->userID, $groupUser->modWeighting);
			}
			else
			{
				$buf .= self::getClosestWeightingName($groupUser->modWeighting);
			}
			$buf .= '</td>';
			$buf .= '<td style="text-align:left">';
			$buf .= libTime::text($groupUser->timeCreated);
			if( $groupUser->timeCreated != $groupUser->timeChanged)
			{
				$buf .= ' <br /> ';
				$buf .= libTime::text($groupUser->timeChanged);
			}
			$buf .= '</td>';
			$buf .= '</tr>';
		}
		$buf .= '</table>';
		return $buf;
	}
	
	private static $allowedWeightings = array(-100=>'Deny',-50=>'Doubt',0=>'None',33=>'Weak',66=>'Mid',100=>'Strong');
	private static function getClosestWeighting($givenWeighting)
	{
		$givenWeighting = intval($givenWeighting);
		foreach(self::$allowedWeightings as $weighting=>$weightingName)
		{
			if( $givenWeighting <= $weighting ) return $weighting;
		}
		return 0;
	}
	private static function getClosestWeightingName($givenWeighting)
	{
		$closestWeighting = self::getClosestWeighting($givenWeighting);
		return self::$allowedWeightings[$closestWeighting];
	}
	public static function getSelectWeighting($weightingType, $userID, $weighting)
	{
		$closestWeighting = self::getClosestWeightingName($weighting);
		$buf = '<select name="'.$weightingType.'Weighting'.$userID.'">';
		foreach(self::$allowedWeightings as $weighting=>$weightingName)
		{
			$buf .= '<option value=';
			$buf .= $weighting;
			
			if( $closestWeighting == $weightingName) {
				$buf .= ' selected ';
			}
			$buf .= '>';
			$buf .= $weightingName;
			$buf .= '</option>';
		}
		$buf .= '</select>';
		return $buf;
	}
}
