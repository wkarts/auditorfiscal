<?php
namespace Tests\Feature; use Tests\TestCase; class HealthTest extends TestCase {public function test_live_endpoint():void{$this->getJson('/api/v1/health/live')->assertOk()->assertJson(['status'=>'ok','service'=>'api']);}}
