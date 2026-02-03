<?php

namespace App\Services;

use Encore\Admin\Facades\Admin;

class AccessControlService
{
    /**
     * Check if user has access to a specific resource
     */
    public static function hasAccess($permission)
    {
        $user = Admin::user();
        
        if (!$user) {
            return false;
        }
        
        // Administrators have access to everything
        if (self::isAdministrator($user)) {
            return true;
        }
        
        // Check specific admin-only permissions
        $adminOnlyPermissions = [
            'admin.departments',
            'admin.system-configurations', 
            'admin.event-logs'
        ];
        
        if (in_array($permission, $adminOnlyPermissions)) {
            return $user->isRole('admin');
        }
        
        // Use Laravel Admin's built-in permission system
        return $user->can($permission);
    }
    
    /**
     * Check if user is administrator
     */
    public static function isAdministrator($user = null)
    {
        if (!$user) {
            $user = Admin::user();
        }
        
        if (!$user) {
            return false;
        }
        
        // Check if user has the 'Administrator' role or is admin
        return $user->isRole('administrator') || $user->isRole('admin');
    }
    
    /**
     * Get filtered menu items based on user permissions
     */
    public static function getFilteredMenuItems()
    {
        $user = Admin::user();
        
        if (!$user) {
            return collect();
        }
        
        return Admin::menu()->filter(function ($item) use ($user) {
            if (empty($item['permission'])) {
                return true;
            }
            
            return self::hasAccess($item['permission']);
        });
    }
}