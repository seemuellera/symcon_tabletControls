<?php

// Klassendefinition
class TabletControlsEventWeekly extends IPSModule {
 
	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);

		// Selbsterstellter Code
	}

	// Überschreibt die interne IPS_Create($id) Funktion
	public function Create() {
		
		// Diese Zeile nicht löschen.
		parent::Create();

		// Properties
		$this->RegisterPropertyString("Sender","TabletControlsEventWeekly");
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyInteger("ObjectIdEvent",0);
		
		// Attributes
		
		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
		$this->RegisterVariableInteger("StartTime","Start Time", "~UnixTimestampTime");
		$this->RegisterVariableInteger("StopTime","Stop Time", "~UnixTimestampTime");
		$this->RegisterVariableString("NameAction1","Name of Action 1");
		$this->RegisterVariableString("NameAction2","Name of Action 2");
				
		//Actions
		$this->EnableAction("Status");
		$this->EnableAction("StartTime");
		$this->EnableAction("StopTime");
		
		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'TABCTRLEVWEEK_RefreshInformation($_IPS[\'TARGET\']);');
    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
	public function GetConfigurationForm() {
        	
		// Initialize the form
		$form = Array(
            		"elements" => Array(),
					"actions" => Array()
        		);

		// Add the Elements
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "RefreshInterval", "caption" => "Refresh Interval");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "DebugOutput", "caption" => "Enable Debug Output");
		$form['elements'][] = Array("type" => "SelectEvent", "name" => "ObjectIdEvent", "caption" => "Select Target Event");
				
		// Add the buttons for the test center
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'TABCTRLEVWEEK_RefreshInformation($id);');
		$form['actions'][] = Array(	"type" => "Button", "label" => "Enable Event", "onClick" => 'TABCTRLEVWEEK_EnableEvent($id);');
		$form['actions'][] = Array(	"type" => "Button", "label" => "Disable Event", "onClick" => 'TABCTRLEVWEEK_DisableEvent($id);');

		
		// Return the completed form
		return json_encode($form);

	}
	
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {
		
		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
				
		// Diese Zeile nicht löschen
		parent::ApplyChanges();
	}
	
	// Version 1.0
	protected function LogMessage($message, $severity = 'INFO') {
		
		$logMappings = Array();
		// $logMappings['DEBUG'] 	= 10206; Deactivated the normal debug, because it is not active
		$logMappings['DEBUG'] 	= 10201;
		$logMappings['INFO']	= 10201;
		$logMappings['NOTIFY']	= 10203;
		$logMappings['WARN'] 	= 10204;
		$logMappings['CRIT']	= 10205;
		
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == false )) {
			
			return;
		}
		
		$messageComplete = $severity . " - " . $message;
		parent::LogMessage($messageComplete, $logMappings[$severity]);
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			case "Status":
				if ($Value) {
					
					$this->EnableEvent();
				}
				else {
					
					$this->DisableEvent();
				}
				break;
			case "StartTime":
				SetValue($this->GetIDForIdent($Ident), $Value);
				$this->SetStartTime($Value);
				break;
			case "StopTime":
				SetValue($this->GetIDForIdent($Ident), $Value);
				$this->SetStopTime($Value);
				break;
			default:
				$this->LogMessage("An undefined Ident was used","CRIT");
		}
	}
	
	public function RefreshInformation() {

		$this->LogMessage("Refresh in progress","DEBUG");
		$nameAction1 = $this->GetNameOfScheduleAction(1);
		$nameAction2 = $this->GetNameOfScheduleAction(2);

		if ( (! $nameAction1) || (! $nameAction2) ) {

			$this->LogMessage("Unable to fetch names of the events","WARN");
			return;
		}

		SetValue($this->GetIDForIdent("NameAction1"), $nameAction1);
		SetValue($this->GetIDForIdent("NameAction2"), $nameAction2);

		SetValue($this->GetIDForIdent("Status"), $this->GetEventState() );
		SetValue($this->GetIDForIdent("StartTime"), $this->GetStartTime() );
		SetValue($this->GetIDForIdent("StopTime"), $this->GetStopTime() );
	}
	
	public function MessageSink($TimeStamp, $SenderId, $Message, $Data) {
	
		$this->LogMessage("$TimeStamp - $SenderId - $Message - " . implode(" ; ",$Data), "DEBUG");
		
		$this->RefreshInformation();
	}
	
	public function EnableEvent() {
		
		IPS_SetEventActive($this->ReadPropertyInteger("ObjectIdEvent"), true);
		SetValue($this->GetIdForIdent("Status"), true);
	}

	public function DisableEvent() {
		
		IPS_SetEventActive($this->ReadPropertyInteger("ObjectIdEvent"), false);
		SetValue($this->GetIdForIdent("Status"), false);
	}

	protected function GetNameOfScheduleAction(int $actionId) {

		$event = IPS_GetEvent($this->ReadPropertyInteger("ObjectIdEvent"));

		$scheduleActions = $event["ScheduleActions"];

		foreach ($scheduleActions as $currentAction) {

			if ($currentAction['ID'] == $actionId) {

				return $currentAction['Name'];
			}
		}

		return false;
	}

	protected function GetEventState() {

		$event = IPS_GetEvent($this->ReadPropertyInteger("ObjectIdEvent"));

		$eventActive = $event["EventActive"];
		
		return $eventActive;
	}	

	protected function GetStartTime() {

		$event = IPS_GetEvent($this->ReadPropertyInteger("ObjectIdEvent"));
		
		$startHour = $event["ScheduleGroups"][0]["Points"][1]["Start"]["Hour"];
		$startMinute = $event["ScheduleGroups"][0]["Points"][1]["Start"]["Minute"];
		$startSecond = $event["ScheduleGroups"][0]["Points"][1]["Start"]["Second"];

		$startTime = mktime($startHour, $startMinute, $startSecond);

		return $startTime;
	}	

	protected function GetStopTime() {

		$event = IPS_GetEvent($this->ReadPropertyInteger("ObjectIdEvent"));
		
		$stopHour = $event["ScheduleGroups"][0]["Points"][2]["Start"]["Hour"];
		$stopMinute = $event["ScheduleGroups"][0]["Points"][2]["Start"]["Minute"];
		$stopSecond = $event["ScheduleGroups"][0]["Points"][2]["Start"]["Second"];

		$stopTime = mktime($stopHour, $stopMinute, $stopSecond);

		return $stopTime;
	}

	public function SetStartTime(int $startTime) {

		$eventId = $this->ReadPropertyInteger("ObjectIdEvent");
		$groupId = 0;
		$pointId = 1;
		$actionId = 2;

		$data = getdate($startTime);

		$startHour = $data['hours'];
		$startMinute = $data['minutes'];
		$startSecond = $data['seconds'];

		$result = IPS_SetEventScheduleGroupPoint($eventId, $groupId, $pointId, $startHour, $startMinute, $startSecond, $actionId);

		if (! $result) {

			$this->LogMessage("Unable to set start time","CRIT");
		}
	}

	public function SetStopTime(int $stopTime) {

		$eventId = $this->ReadPropertyInteger("ObjectIdEvent");
		$groupId = 0;
		$pointId = 2;
		$actionId = 1;

		$data = getdate($stopTime);

		$stopHour = $data['hours'];
		$stopMinute = $data['minutes'];
		$stopSecond = $data['seconds'];

		$result = IPS_SetEventScheduleGroupPoint($eventId, $groupId, $pointId, $stopHour, $stopMinute, $stopSecond, $actionId);

		if (! $result) {

			$this->LogMessage("Unable to set stop time","CRIT");
		}
	}
}
