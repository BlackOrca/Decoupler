<?php

declare(strict_types=1);
class VaFiMap extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger('Source', 0);
        
        $this->RegisterVariableFloat('Value', $this->Translate('Value'), '', 0);        
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

    private function Filter(): bool
    {
        $sourceId = $this->ReadPropertyInteger('Source');

        if (!IPS_VariableExists($sourceId)) return false;

        $sourceValue = GetValue($sourceId);

        //Filter
        //if Value Valid -> Map()
        //else false
        return $this->Map($sourceValue);
    }

    private function Map($value): bool
    {  
        $oldValue = $this->GetValue('Value');
        
        if($oldValue != $value) $this->SetValue('Value', $value);
      
        return true;
    }    
}
