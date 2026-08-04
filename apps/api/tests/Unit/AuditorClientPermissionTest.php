<?php

namespace Tests\Unit;

use Database\Seeders\PermissionSeeder;
use PHPUnit\Framework\TestCase;

class AuditorClientPermissionTest extends TestCase
{
    public function test_auditor_role_can_view_and_manage_audited_clients(): void
    {
        $this->assertContains('clients.view', PermissionSeeder::AUDITOR_PERMISSIONS);
        $this->assertContains('clients.manage', PermissionSeeder::AUDITOR_PERMISSIONS);
        $this->assertNotContains('accounts.manage', PermissionSeeder::AUDITOR_PERMISSIONS);
        $this->assertNotContains('users.manage', PermissionSeeder::AUDITOR_PERMISSIONS);
    }
}
