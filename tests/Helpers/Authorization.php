<?php


namespace NetLinker\DelivererAgrip\Tests\Helpers;


use Illuminate\Support\Facades\Auth;
use NetLinker\DelivererAgrip\Tests\Stubs\Owner;
use NetLinker\DelivererAgrip\Tests\Stubs\User;

trait Authorization
{

    /**
     * Auth as first
     */
    public function authAsFirst(){

        $user = User::first();

        if (!$user){
            $owner = factory(Owner::class)->create();
            $user = factory(User::class)->create(['owner_uuid' => $owner->uuid,]);
        }

        Auth::login($user);
    }
}