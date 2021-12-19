<?php

// Klassendefinition
class TabletControlsTemperature extends IPSModule {
 
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
		$this->RegisterPropertyString("Sender","TabletControlsTemperature");
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyInteger("SourceVariable",0);
		
		// Variable profiles
		$variableProfileTabCtrlTemperature = "TABCTRL.Temperature";
		if (IPS_VariableProfileExists($variableProfileTabCtrlTemperature) ) {
		
			IPS_DeleteVariableProfile($variableProfileTabCtrlTemperature);
		}			
		IPS_CreateVariableProfile($variableProfileTabCtrlTemperature, 2);
		IPS_SetVariableProfileIcon($variableProfileTabCtrlTemperature, "Temperature");
		IPS_SetVariableProfileAssociation($variableProfileTabCtrlTemperature, 0, "-", "", -1);
		IPS_SetVariableProfileAssociation($variableProfileTabCtrlTemperature, 1, "%2.0f °C", "", -1);
		IPS_SetVariableProfileAssociation($variableProfileTabCtrlTemperature, 100, "+", "", -1);
		
		// Variables
		$this->RegisterVariableFloat("Temperatur","Temperatur",$variableProfileTabCtrlTemperature);
		
		//Actions
		$this->EnableAction("Temperatur");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'TABCTRLTEMPERATURE_RefreshInformation($_IPS[\'TARGET\']);');
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
		$form['elements'][] = Array("type" => "SelectVariable", "name" => "SourceVariable", "caption" => "Source Variable");

				
		// Add the buttons for the test center
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'TABCTRLTEMPERATURE_RefreshInformation($id);');

		
		// Return the completed form
		return json_encode($form);

	}
	
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {
		
		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
		
		// Register Variables
		$this->RegisterMessage($this->ReadPropertyInteger("SourceVariable"), VM_UPDATE);
		$this->RegisterReference($this->ReadPropertyInteger("SourceVariable"));
		
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
				if ($Value == 0) {
					
					$currentValue = GetValue($this->ReadPropertyInteger("SourceVariable"));
					$newValue = $currentValue - 1;
					
					$this->LogMessage("Request to lower temperature from $currentValue to $newValue","DEBUG");
					
					RequestAction($this->ReadPropertyInteger("SourceVariable"), $newValue);
					break;
				}
				if ($Value == 100) {
					
					$currentValue = GetValue($this->ReadPropertyInteger("SourceVariable"));
					$newValue = $currentValue + 1;
					
					$this->LogMessage("Request to lower temperature from $currentValue to $newValue","DEBUG");
					
					RequestAction($this->ReadPropertyInteger("SourceVariable"), $newValue);
					break;
				}
				$this->LogMessage("Click on current Termpature. Ignoring.","DEBUG");
				break;
			default:
				$this->LogMessage("An undefined compare mode was used","CRIT");
		}
	}
	
	public function RefreshInformation() {

		SetValue($this->GetIdForIdent("Temperatur"), GetValue($this->ReadPropertyInteger("SourceVariable") ) );
	}
	
	public function MessageSink($TimeStamp, $SenderId, $Message, $Data) {
	
		$this->LogMessage("$TimeStamp - $SenderId - $Message - " . implode(" ; ",$Data), "DEBUG");
		
		$this->RefreshInformation();
	}
}
