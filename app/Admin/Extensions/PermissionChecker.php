<?php

namespace App\Admin\Extensions;

use Encore\Admin\Facades\Admin;
use Encore\Admin\Auth\Database\Permission;
use App\Services\AccessControlService;

class PermissionChecker
{
    /**
     * Override Laravel Admin's permission checking
     */
    public static function intercept()
    {
        // Override the User model's can method for our specific permissions
        $user = Admin::user();
        
        if (!$user) {
            return;
        }
        
        // Create a custom permission checker that will be used by Laravel Admin
        $originalCanMethod = [$user, 'can'];
        
        // This is a bit of a hack, but necessary to override vendor behavior
        // We'll intercept permission checks for our admin-only permissions
        app()->bind('admin.permission.checker', function() {
            return new class {
                public function check($permission) {
                    return AccessControlService::hasAccess($permission);
                }
            };
        });
    }
    
    /**
     * Check if user can access a route based on Laravel Admin's routing patterns
     */
    public static function checkRouteAccess($request)
    {
        $path = $request->path();
        
        // Define protected paths and their required permissions
        $protectedPaths = [
            'admin/departments' => 'admin.departments',
            'admin/system-configurations' => 'admin.system-configurations', 
            'admin/event-logs' => 'admin.event-logs',
            'admin/import-user-datas' => 'admin.role',
            'admin/import-attendance-records' => 'admin.role',
        ];
        
        foreach ($protectedPaths as $protectedPath => $permission) {
            if (str_starts_with($path, $protectedPath)) {
                if ($permission === 'admin.role') {
                    $user = Admin::user();
                    return $user && $user->isRole('admin');
                }
                return AccessControlService::hasAccess($permission);
            }
        }
        
        return true; // Allow access to non-protected routes
    }
}