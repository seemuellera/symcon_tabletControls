<?php

// Klassendefinition
class TabletControlsSwitch extends IPSModule {
 
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
		$this->RegisterPropertyString("Sender","TabletControlsSwitch");
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyInteger("SourceVariable",0);
		$this->RegisterPropertyBoolean("ReadOnly",false);
		
		// Variable profiles
		$variableProfileTabCtrlSwitch = "TABCTRL.Switch";
		if (IPS_VariableProfileExists($variableProfileTabCtrlSwitch) ) {
		
			IPS_DeleteVariableProfile($variableProfileTabCtrlSwitch);
		}			
		IPS_CreateVariableProfile($variableProfileTabCtrlSwitch, 1);
		IPS_SetVariableProfileIcon($variableProfileTabCtrlSwitch, "Power");
		IPS_SetVariableProfileAssociation($variableProfileTabCtrlSwitch, 100, "An", "", 0x00FF00);
		IPS_SetVariableProfileAssociation($variableProfileTabCtrlSwitch, 99, "", "", 0xFFA500);
		IPS_SetVariableProfileAssociation($variableProfileTabCtrlSwitch, 0, "Aus", "", -1);
		
		// Variables
		$this->RegisterVariableInteger("Status","Status",$variableProfileTabCtrlSwitch);
		
		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'TABCTRLSWITCH_RefreshInformation($_IPS[\'TARGET\']);');
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
		$form['elements'][] = Array("type" => "CheckBox", "name" => "ReadOnly", "caption" => "Read Only (No action assigned)");

				
		// Add the buttons for the test center
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'TABCTRLSWITCH_RefreshInformation($id);');

		
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

		//Actions
		if (! $this->ReadPropertyBoolean("ReadOnly") ) {
		
			$this->EnableAction("Status");
		}
		else {
			
			$this->DisableAction("Status");
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
				if ( ($Value == 0) || ($Value == 100) ) {
					
					$this->RequestActionWithBackOff($this->ReadPropertyInteger("SourceVariable"), $Value);
				}
				break;
			default:
				$this->LogMessage("An undefined compare mode was used","CRIT");
		}
	}
	
	public function RefreshInformation() {

		if ($this->GetIdForIdent("Status") != 1) {
			
			if (GetValue($this->ReadPropertyInteger("SourceVariable") ) {
				
				SetValue($this->GetIDForIdent("Status"), 100);
			}
			else {
				
				SetValue($this->GetIDForIdent("Status"), 0);
			}
		}
		else {
			
			$variableDetails = IPS_GetVariable($this->GetIDForIdent("Status"));
			$variableLastUpdated = $variableDetails['VariableUpdated'];
			
			$threshold = time() - 300;
			
			if ($variableLastUpdated < $threshold) {
				
				if (GetValue($this->ReadPropertyInteger("SourceVariable") ) {
					
					SetValue($this->GetIDForIdent("Status"), 100);
				}
				else {
					
					SetValue($this->GetIDForIdent("Status"), 0);
				}	
			}
		}
	}
	
	public function MessageSink($TimeStamp, $SenderId, $Message, $Data) {
	
		$this->LogMessage("$TimeStamp - $SenderId - $Message - " . implode(" ; ",$Data), "DEBUG");
		
		$this->RefreshInformation();
	}
	
	// Version 1.0
	protected function RequestActionWithBackOff($variable, $value) {
		
		$retries = 4;
		$baseWait = 200;
		
		if ($value == 0) {
			$targetValue = false;
		}
		if ($value == 100) {
			$targetValue = true;
		}
		
		SetValue($this->GetIDForIdent("Status"), 1);
		
		for ($i = 0; $i <= $retries; $i++) {
			
			$wait = $baseWait * $i;
			
			if ($wait > 0) {
				
				$this->LogMessage("Waiting for $wait milliseconds, retry $i of $retries", "DEBUG");
				IPS_Sleep($wait);
			}
			
			$result = RequestAction($variable, $targetValue);
			
			// Return success if executed successfully
			if ($result) {
				
				SetValue($this->GetIDForIdent("Status"), $value);
				return true;
			}
			else {
				
				$this->LogMessage("Switching Variable $variable to Value $value failed, but will be retried", "WARN");
			}
			
		}
		
		// return false as switching was not possible after all these times
		$this->LogMessage("Switching Variable $variable to Value $value failed after $retries retries. Aborting", "CRIT");
		return false;
	}
}
