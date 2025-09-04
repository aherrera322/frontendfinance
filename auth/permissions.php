<?php
/**
 * Role-based permissions configuration for Zimple Travel Group
 * Defines what each department/role can access and modify
 */

// Define all available permissions
define('PERMISSIONS', [
    // User Management
    'users_view' => 'View user list',
    'users_create' => 'Create new users',
    'users_edit' => 'Edit existing users',
    'users_delete' => 'Delete users',
    'users_manage_roles' => 'Manage user roles and permissions',
    
    // Client Management
    'clients_view' => 'View client list',
    'clients_create' => 'Create new clients',
    'clients_edit' => 'Edit existing clients',
    'clients_delete' => 'Delete clients',
    
    // Partner Management
    'partners_view' => 'View partner list',
    'partners_create' => 'Create new partners',
    'partners_edit' => 'Edit existing partners',
    'partners_delete' => 'Delete partners',
    
    // Reports and Data
    'reports_view' => 'View reports',
    'reports_export' => 'Export reports',
    'data_import' => 'Import data files',
    'data_export' => 'Export data',
    
    // Aerovision Specific
    'aerovision_view' => 'View Aerovision data',
    'aerovision_edit' => 'Edit Aerovision data',
    'aerovision_import' => 'Import Aerovision data',
    
    // Zimple Specific
    'zimple_view' => 'View Zimple data',
    'zimple_edit' => 'Edit Zimple data',
    'zimple_import' => 'Import Zimple data',
    
    // Accounting
    'accounting_view' => 'View accounting data',
    'accounting_edit' => 'Edit accounting data',
    'accounting_reports' => 'Generate accounting reports',
    'commission_manage' => 'Manage commission rates',
    
    // System Administration
    'system_config' => 'System configuration',
    'database_manage' => 'Database management',
    'logs_view' => 'View system logs'
]);

// Define role permissions
define('ROLE_PERMISSIONS', [
    'site_administrator' => [
        // Full access to everything
        'users_view', 'users_create', 'users_edit', 'users_delete', 'users_manage_roles',
        'clients_view', 'clients_create', 'clients_edit', 'clients_delete',
        'partners_view', 'partners_create', 'partners_edit', 'partners_delete',
        'reports_view', 'reports_export', 'data_import', 'data_export',
        'aerovision_view', 'aerovision_edit', 'aerovision_import',
        'zimple_view', 'zimple_edit', 'zimple_import',
        'accounting_view', 'accounting_edit', 'accounting_reports', 'commission_manage',
        'system_config', 'database_manage', 'logs_view'
    ],
    
    'agent_aerovision' => [
        // Aerovision agent permissions
        'aerovision_view', 'aerovision_edit',
        'clients_view', 'clients_create', 'clients_edit',
        'reports_view', 'reports_export'
    ],
    
    'agent_zimple' => [
        // Zimple agent permissions
        'zimple_view', 'zimple_edit',
        'clients_view', 'clients_create', 'clients_edit',
        'reports_view', 'reports_export'
    ],
    
    'accounting_aerovision' => [
        // Aerovision accounting permissions
        'aerovision_view',
        'accounting_view', 'accounting_edit', 'accounting_reports',
        'commission_manage',
        'reports_view', 'reports_export', 'data_export'
    ],
    
    'accounting_zimple' => [
        // Zimple accounting permissions
        'zimple_view',
        'accounting_view', 'accounting_edit', 'accounting_reports',
        'commission_manage',
        'reports_view', 'reports_export', 'data_export'
    ],
    
    'accounting_manager' => [
        // Accounting manager permissions
        'aerovision_view', 'zimple_view',
        'accounting_view', 'accounting_edit', 'accounting_reports',
        'commission_manage',
        'reports_view', 'reports_export', 'data_export',
        'clients_view', 'partners_view'
    ]
]);

/**
 * Check if a user has a specific permission
 * @param array $user User data from database
 * @param string $permission Permission to check
 * @return bool
 */
function hasPermission($user, $permission) {
    // Site administrators have all permissions
    if ($user['department'] === 'site_administrator') {
        return true;
    }
    
    // Check if user's role has the permission
    $rolePermissions = ROLE_PERMISSIONS[$user['department']] ?? [];
    return in_array($permission, $rolePermissions);
}

/**
 * Get all permissions for a specific role
 * @param string $role Role name
 * @return array
 */
function getRolePermissions($role) {
    return ROLE_PERMISSIONS[$role] ?? [];
}

/**
 * Get all available roles
 * @return array
 */
function getAvailableRoles() {
    return array_keys(ROLE_PERMISSIONS);
}

/**
 * Get role display names
 * @return array
 */
function getRoleDisplayNames() {
    return [
        'site_administrator' => 'Site Administrator',
        'agent_aerovision' => 'Agent Aerovision',
        'agent_zimple' => 'Agent Zimple',
        'accounting_aerovision' => 'Accounting Aerovision',
        'accounting_zimple' => 'Accounting Zimple',
        'accounting_manager' => 'Accounting Manager'
    ];
}

/**
 * Get role description
 * @param string $role Role name
 * @return string
 */
function getRoleDescription($role) {
    $descriptions = [
        'site_administrator' => 'Full system access with ability to manage all users, data, and system settings',
        'agent_aerovision' => 'Can view and edit Aerovision data, manage clients, and view reports',
        'agent_zimple' => 'Can view and edit Zimple data, manage clients, and view reports',
        'accounting_aerovision' => 'Can view Aerovision data, manage accounting records, and generate financial reports',
        'accounting_zimple' => 'Can view Zimple data, manage accounting records, and generate financial reports',
        'accounting_manager' => 'Can view all data, manage accounting records, commissions, and generate financial reports'
    ];
    
    return $descriptions[$role] ?? 'No description available';
}
?>

