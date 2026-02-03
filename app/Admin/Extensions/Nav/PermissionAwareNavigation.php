<?php

namespace App\Admin\Extensions\Nav;

use Encore\Admin\Layout\Navbar;
use Encore\Admin\Facades\Admin;

class PermissionAwareNavigation extends Navbar
{
    /**
     * Override the menu method to filter out items user doesn't have access to.
     */
    public function menu()
    {
        $user = Admin::user();
        
        if (!$user) {
            return parent::menu();
        }

        // Get the original menu
        $menu = Admin::menu();
        
        // Filter menu items based on permissions
        $filteredMenu = $menu->filter(function ($item) use ($user) {
            // If no permission is set, allow access (backward compatibility)
            if (empty($item['permission'])) {
                return true;
            }
            
            // Check if user has the specific permission
            if ($user->can($item['permission'])) {
                return true;
            }
            
            // For our custom admin role check
            if ($item['permission'] === 'admin.departments' || 
                $item['permission'] === 'admin.system-configurations' || 
                $item['permission'] === 'admin.event-logs') {
                return $user->isRole('admin');
            }
            
            return false;
        });
        
        return $filteredMenu;
    }
}