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

        $this->RegisterPropertyBoolean('IsMaxValueChangeActive', false);
        $this->RegisterPropertyFloat('MaxValueChange', 0);
        
        $this->RegisterAttributeInteger('SelectedType', 1);
        $this->RegisterPropertyBoolean('IsSelectedTypeLocked', false);

        $this->RegisterPropertyBoolean('UseValueInverting', false);
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
        if($sourceId > 0)
            $this->UnregisterReference($sourceId);

        //Register
        $this->RegisterMessage($sourceId, VM_UPDATE);
        $this->RegisterReference($sourceId);
      
        $this->Filter();
    }

    private function Filter(): bool
    {
        $sourceId = $this->ReadPropertyInteger('Source');

        if (!IPS_VariableExists($sourceId)) return false;

        $sourceValue = GetValue($sourceId);

        if($this->ReadAttributeInteger('SelectedType') == VARIABLETYPE_INTEGER || $this->ReadAttributeInteger('SelectedType') == VARIABLETYPE_FLOAT)
        {
            if(!$this->FilterNumber($sourceValue))
                return false;
        }        
        
        return $this->Map($sourceValue);
    }

    private function FilterNumber($value): bool
    {
        if($this->ReadPropertyBoolean('IsMaxValueChangeActive'))
        {
            $oldValue = $this->GetValue('Value');
            
            if($this->ReadPropertyBoolean('UseValueInverting'))
                $oldValue = $oldValue * -1;

            $maxChange = $this->ReadPropertyFloat('MaxValueChange');            
            $this->SendDebug('MaxChange', $maxChange, 0);
            if(abs($oldValue - $value) > $maxChange)
                return false;
        }

        if($this->ReadPropertyBoolean('IsLowFilterActive'))
        {
            if($value <= $this->ReadPropertyFloat('LowFilterValue'))
                return false;
        }

        if($this->ReadPropertyBoolean('IsHighFilterActive'))
        {
            if($value >= $this->ReadPropertyFloat('HighFilterValue'))
                return false;
        }

        return true;
    }

    private function Map($value): bool
    {  
        $oldValue = $this->GetValue('Value');
        
        //- 1 zu 1 
        //Invertieren bool != bool oder *-1
        //Immer Aktuallisieren oder nur bei werte änderung

        if(!$this->ReadPropertyBoolean('UseValueInverting'))
        {
            if($oldValue != $value) $this->SetValue('Value', $value);
            return true;
        }        
        
        if($this->ReadAttributeInteger('SelectedType') == VARIABLETYPE_INTEGER || $this->ReadAttributeInteger('SelectedType') == VARIABLETYPE_FLOAT)
        {
            $newValue = $value *-1;
            if($newValue != $oldValue) $this->SetValue('Value', $newValue);
            return true;
        }
        
        if($this->ReadAttributeInteger('SelectedType') == VARIABLETYPE_BOOLEAN)
        {
            $newBoolValue = !$value;
            if($newBoolValue != $oldValue) $this->SetValue('Value', $newBoolValue);
            return true;
        }                
    
        return true;
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
        if($this->ReadPropertyBoolean('IsSelectedTypeLocked'))
            return;

        //Get Variable infos to define the output value type and profile
        $sourceInfo = IPS_GetVariable($sourceId);
        
        $variableProfile = "";
        if($sourceInfo['VariableCustomProfile'] != "")
            $variableProfile = $sourceInfo['VariableCustomProfile'];
        else if($sourceInfo['VariableProfile'] != "")
            $variableProfile = $sourceInfo['VariableProfile'];
        
        $this->UnregisterVariable('Value');
        
            //0: Boolean, 1: Integer, 2: Float, 3: String
        if($sourceInfo['VariableType'] == 1) $this->RegisterVariableInteger('Value', 'Value', $variableProfile, 0);
        else if($sourceInfo['VariableType'] == 2) $this->RegisterVariableFloat('Value', 'Value', $variableProfile, 0);
        else if($sourceInfo['VariableType'] == 0) $this->RegisterVariableBoolean('Value', 'Value', $variableProfile, 0);
        else return;

        $this->WriteAttributeInteger('SelectedType', $sourceInfo['VariableType']);
    }    

    public function VariableSelected(int $ident) : bool
    {
        $this->SendDebug('VariableSelected', 'Ident: ' . $ident, 0);
        $sourceType = IPS_GetVariable($ident)['VariableType'];

        switch($sourceType)
        {
            case VARIABLETYPE_BOOLEAN:
                $this->UpdateFormField('IsLowFilterActive', 'visible', false);
                $this->UpdateFormField('LowFilterValue', 'visible', false);
                $this->UpdateFormField('IsHighFilterActive', 'visible', false);
                $this->UpdateFormField('HighFilterValue', 'visible', false);
                break;
            case VARIABLETYPE_INTEGER:
                $this->UpdateFormField('IsLowFilterActive', 'visible', true);
                $this->UpdateFormField('LowFilterValue', 'visible', true);
                $this->UpdateFormField('IsHighFilterActive', 'visible', true);
                $this->UpdateFormField('HighFilterValue', 'visible', true);
                break;
            case VARIABLETYPE_FLOAT:
                $this->UpdateFormField('IsLowFilterActive', 'visible', true);
                $this->UpdateFormField('LowFilterValue', 'visible', true);
                $this->UpdateFormField('IsHighFilterActive', 'visible', true);
                $this->UpdateFormField('HighFilterValue', 'visible', true);
                break;
        }

        return true;
    }

    public function GetConfigurationForm()
    {
        $sourceId = $this->ReadPropertyInteger('Source');
        $sourceType = IPS_GetVariable($sourceId)['VariableType'];        
        
        $isNumberVariable = $sourceType == VARIABLETYPE_INTEGER || $sourceType == VARIABLETYPE_FLOAT;

        $form = json_decode(file_get_contents(__DIR__ . "/form.json"));

        $form->elements[4]->visible = $isNumberVariable;
        $form->elements[5]->visible = $isNumberVariable;
        $form->elements[6]->visible = $isNumberVariable;
        $form->elements[7]->visible = $isNumberVariable;
        $form->elements[8]->visible = $isNumberVariable;
        $form->elements[9]->visible = $isNumberVariable;

        return json_encode($form);
    }
}
