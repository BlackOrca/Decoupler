<?php

declare(strict_types=1);
class Decoupler extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger('Source', 0);
        
        $this->RegisterPropertyBoolean('IsLowFilterActive', false);
        $this->RegisterPropertyFloat('LowFilterValue', 0);

        $this->RegisterPropertyBoolean('IsHighFilterActive', false);
        $this->RegisterPropertyFloat('HighFilterValue', 0);      
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $sourceId = $this->ReadPropertyInteger('Source');
      
        if (!IPS_VariableExists($sourceId)) {
            $this->SetStatus(200);
            return;
        } 
        else {
            $this->SetStatus(102);
        }

        $this->MaintainValueVariable($sourceId);

        //Unregister first
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }
      
        //Register
        $this->RegisterMessage($sourceId, VM_UPDATE);
      
        $this->Filter();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('Sender ' . $SenderID, 'Message ' . $Message, 0);
        if ($Message === VM_UPDATE) {
            $this->Filter();
        }
    }

    private function MaintainValueVariable($sourceId)
    {
        //Get Variable infos to define the output value type and profile
        $sourceInfo = IPS_GetVariable($sourceId);
        
        $variableProfile = "";
        if($sourceInfo['VariableCustomProfile'] != "")
            $variableProfile = $sourceInfo['VariableCustomProfile'];
        else if($sourceInfo['VariableProfile'] != "")
            $variableProfile = $sourceInfo['VariableProfile'];
           
        $this->MaintainVariable('Value', "Value", $sourceInfo['VariableType'], $variableProfile, 1, true);
    }

    private function Filter(): bool
    {
        $sourceId = $this->ReadPropertyInteger('Source');

        if (!IPS_VariableExists($sourceId)) return false;

        $sourceValue = GetValue($sourceId);

        if($this->ReadPropertyBoolean('IsLowFilterActive'))
        {
            if($sourceValue <= $this->ReadPropertyFloat('LowFilterValue'))
                return false;
        }

        if($this->ReadPropertyBoolean('IsHighFilterActive'))
        {
            if($sourceValue >= $this->ReadPropertyFloat('HighFilterValue'))
                return false;
        }
        
        return $this->Map($sourceValue);
    }

    private function Map($value): bool
    {  
        $oldValue = $this->GetValue('Value');
        
        if($oldValue != $value) $this->SetValue('Value', $value);
      
        return true;
    }    
}
