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

    private function Filter(): bool
    {
        $sourceId = $this->ReadPropertyInteger('Source');

        if (!IPS_VariableExists($sourceId)) return false;

        $sourceValue = GetValue($sourceId);

        if($this->ReadAttributeInteger('SelectedType') == 1 || $this->ReadAttributeInteger('SelectedType') == 2)
        {
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
        }
        
        return $this->Map($sourceValue);
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
        }
        else
        {
            if($this->ReadAttributeInteger('SelectedType') == 1 || $this->ReadAttributeInteger('SelectedType') == 2)
            {
                $newValue = $value *-1;
                if($newValue != $oldValue) $this->SetValue('Value', $newValue);
            }
            else if($this->ReadAttributeInteger('SelectedType') == 0)
            {
                $newBoolValue = !$value;
                if($newBoolValue != $oldValue) $this->SetValue('Value', $newBoolValue);
            }
        }            
      
        return true;
    }

    public function VariableSelected(int $ident) : bool
    {
        $this->SendDebug('VariableSelected', 'Ident: ' . $ident, 0);
        //$sourceId = $this->ReadPropertyInteger('Source');
        $sourceType = IPS_GetVariable($ident)['VariableType'];

        switch($sourceType)
        {
            case VARIABLETYPE_BOOLEAN:
                $this->UpdateFormField('IsLowFilterActive', 'visible', false);
                $this->UpdateFormField('LowFilterValue', 'visible', false);
                $this->UpdateFormField('IsHighFilterActive', 'visible', false);
                $this->UpdateFormField('HigFilterValue', 'visible', false);
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
        
        $form = json_decode(file_get_contents(__DIR__ . "/form.json"));
        
        $form = [
            'elements' => [
                [ 
                    'type' => 'CheckBox',
                    'name' => 'IsSelectedTypeLocked',
                    'caption' => $this->Translate('Protect from Type or Profile Changing.')
                ],
                [                      
                    'type' => 'Label',
                    'caption' => $this->Translate('At first configure must this checkbox to off.'),
                    'italic' => true,
                    'width' => '80%'
                ],
                [ 
                    'type' => 'Label',
                    'caption' => $this->Translate('Supported are Integer, Float and Boolean.'),
                    'italic' => true,
                    'width' => '80%'
                ],
                [ 
                    'type' => 'SelectVariable',
                    'caption' => $this->Translate('Source Variable'),
                    'name' => 'Source',
                    'validVariableTypes' => [
                        0,
                        1,
                        2
                    ],
                    'onChange' => 'DC_VariableSelected($id, $Source);'
                ],
                [ 
                    'type' => 'CheckBox',
                    'name' => 'IsLowFilterActive',
                    'caption' => $this->Translate('Low Filter Active'),
                    'visible' => $sourceType == VARIABLETYPE_INTEGER || $sourceType == VARIABLETYPE_FLOAT
                ],
                [ 
                    'type' => 'NumberSpinner',
                    'name' => 'LowFilterValue',
                    'caption' => $this->Translate('Low Filter Value'),
                    'visible' => $sourceType == VARIABLETYPE_INTEGER || $sourceType == VARIABLETYPE_FLOAT
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'IsHighFilterActive',
                    'caption' => $this->Translate('High Filter Active'),
                    'visible' => $sourceType == VARIABLETYPE_INTEGER || $sourceType == VARIABLETYPE_FLOAT
                ],
                [
                    'type' => 'NumberSpinner',
                    'name' => 'HighFilterValue',
                    'caption' => $this->Translate('High Filter Value'),
                    'visible' => $sourceType == VARIABLETYPE_INTEGER || $sourceType == VARIABLETYPE_FLOAT
                ],
                [ 
                    'type' => 'CheckBox',
                    'name' => 'UseValueInverting',
                    'caption' => 'Use Value Inverting'
                ]                
            ]
        ];   

        return json_encode($form);
    }
}
