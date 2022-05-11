<?php

namespace NetLinker\DelivererAgrip\Sections\Configurations\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Configuration extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'url_1' => $this->url_1,
            'url_2' => $this->url_2,
            'login' =>$this->login,
            'pass' =>$this->pass,
            'login2' =>$this->login2,
            'pass2' =>$this->pass2,
            'token' =>$this->token,
            'debug' =>$this->debug,
            'baselinker' =>$this->baselinker,
        ];
    }
}
