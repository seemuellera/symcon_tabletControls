<?php

// Klassendefinition
class TabletControlsEventDaily extends IPSModule {
 
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
		$this->RegisterPropertyString("Sender","TabletControlsEventDaily");
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyInteger("ObjectIdEvent",0);
		
		// Attributes
		
		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
		$this->RegisterVariableInteger("StartTime","Start Time", "~UnixTimestampTime");
				
		//Actions
		$this->EnableAction("Status");
		$this->EnableAction("StartTime");
		
		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'TABCTRLEVDAY_RefreshInformation($_IPS[\'TARGET\']);');
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
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'TABCTRLEVDAY_RefreshInformation($id);');
		$form['actions'][] = Array(	"type" => "Button", "label" => "Enable Event", "onClick" => 'TABCTRLEVDAY_EnableEvent($id);');
		$form['actions'][] = Array(	"type" => "Button", "label" => "Disable Event", "onClick" => 'TABCTRLEVDAY_DisableEvent($id);');

		
		// Return the completed form
		return json_encode($form);

	}
	
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {
		
		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);

		// Clean old references
		$referenceList = $this->GetReferenceList();
		foreach ($referenceList as $currentReference) {

			$this->UnregisterReference($currentReference);
		}

		// Clean old message registration
		$messagesList = $this->GetMessageList();
		foreach ($messagesList as $currentMessage) {

			$this->UnregisterMessage($currentMessage, VM_UPDATE);
		}				
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
			default:
				$this->LogMessage("An undefined Ident was used","CRIT");
		}
	}
	
	public function RefreshInformation() {

		$this->LogMessage("Refresh in progress","DEBUG");
		
		SetValue($this->GetIDForIdent("Status"), $this->GetEventState() );
		SetValue($this->GetIDForIdent("StartTime"), $this->GetStartTime() );
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

	protected function GetEventState() {

		$event = IPS_GetEvent($this->ReadPropertyInteger("ObjectIdEvent"));

		$eventActive = $event["EventActive"];
		
		return $eventActive;
	}	

	protected function GetStartTime() {

		$event = IPS_GetEvent($this->ReadPropertyInteger("ObjectIdEvent"));
		
		$startHour = $event["CyclicTimeFrom"]["Hour"];
		$startMinute = $event["CyclicTimeFrom"]["Minute"];
		$startSecond = $event["CyclicTimeFrom"]["Second"];

		$startTime = mktime($startHour, $startMinute, $startSecond);

		return $startTime;
	}	

	public function SetStartTime(int $startTime) {

		$eventId = $this->ReadPropertyInteger("ObjectIdEvent");
		
		$data = getdate($startTime);

		$startHour = $data['hours'];
		$startMinute = $data['minutes'];
		$startSecond = $data['seconds'];

		$result = IPS_SetEventCyclicTimeFrom($eventId, $startHour, $startMinute, $startSecond);

		if (! $result) {

			$this->LogMessage("Unable to set start time","CRIT");
		}
	}

}
