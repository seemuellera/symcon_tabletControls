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
		
		// Attributes
		$this->RegisterAttributeInteger("ObjectIdEvent",0);
		
		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
				
		//Actions
		$this->EnableAction("Status");
		
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
		
		// Create event if it does not exist already
		if ( ($this->ReadAttributeInteger("ObjectIdEvent") == 0) || (! IPS_ObjectExists($this->ReadAttributeInteger("ObjectIdEvent")) ) ) {
		
			$objectId = IPS_CreateEvent(2);
			$this->WriteAttributeInteger("ObjectIdEvent", $objectId);
			IPS_SetParent($objectId, $this->InstanceID);
			
			$this->LogMessage("Created event with Object ID $objectId");
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
			default:
				$this->LogMessage("An undefined compare mode was used","CRIT");
		}
	}
	
	public function RefreshInformation() {

		$this->LogMessage("Refresh in progress","DEBUG");
	}
	
	public function MessageSink($TimeStamp, $SenderId, $Message, $Data) {
	
		$this->LogMessage("$TimeStamp - $SenderId - $Message - " . implode(" ; ",$Data), "DEBUG");
		
		$this->RefreshInformation();
	}
	
	public function EnableEvent() {
		
		IPS_SetEventActive($this->ReadAttributeInteger("ObjectIdEvent"), true);
		SetValue($this->GetIdForIdent("Status"), true);
	}

	public function DisableEvent() {
		
		IPS_SetEventActive($this->ReadAttributeInteger("ObjectIdEvent"), false);
		SetValue($this->GetIdForIdent("Status"), false);
	}
}
