# User Role Management Guide

## Overview
This guide explains how to add and remove roles from users in the Cape Tennis system.

## Accessing User Management

1. Log in as an **admin** or **super-user**
2. Navigate to: **Backend** → **Users** (or `/backend/user`)
3. You'll see a table with all registered users

## Available Roles

The system supports the following roles:
- **super-user** - Full system access (red badge)
- **admin** - Administrative access (yellow badge)
- **convenor** - Event management access
- **player** - Regular player access
- Other custom roles defined in the system

## Adding a Role to a User

1. Find the user in the user table
2. Click the **green user-plus icon** (Add Role button)
3. A modal will appear titled "Add Role to User"
4. Select the role from the dropdown
5. Click **"Add Role"**
6. Success message will appear and the table will refresh

**Button Icon**: 🟢 <i class="ti ti-user-plus"></i> (Green button with plus icon)

## Removing a Role from a User

1. Find the user in the user table
2. Click the **orange user-minus icon** (Remove Role button)
3. A modal will appear titled "Remove Role from User"
4. Select the role you want to remove from the dropdown
5. Click **"Remove Role"**
6. Success message will appear and the table will refresh

**Button Icon**: 🟠 <i class="ti ti-user-minus"></i> (Orange/warning button with minus icon)

**Note**: If a user has no roles, the dropdown will be disabled and show "This user has no roles to remove."

## Action Buttons in User Table

Each user row has the following action buttons:

| Icon | Color | Action | Description |
|------|-------|--------|-------------|
| 👁️ | Blue | View | View user details |
| ➕ | Green | Add Role | Add a role to the user |
| ➖ | Orange | Remove Role | Remove a role from the user |
| 🗑️ | Red | Delete | Delete the user |

## Role Display

Roles are displayed as colored badges in the "Roles" column:
- **Red badge**: super-user
- **Yellow badge**: admin
- **Blue badge**: Other roles (convenor, player, etc.)

## Permissions Required

To manage user roles, you must be logged in as:
- **super-user**, OR
- **admin**

Regular users cannot add or remove roles.

## API Endpoints

For developers:

### Add Role
```
POST /backend/user/{userId}/add-role
Body: { role: "role-name" }
```

### Remove Role
```
POST /backend/users/{userId}/remove-role
Body: { role: "role-name" }
```

## Common Use Cases

### Making a User an Admin
1. Click the green **Add Role** button for the user
2. Select **admin** from the dropdown
3. Click **Add Role**
4. The user will now have admin privileges

### Removing Admin Access
1. Click the orange **Remove Role** button for the user
2. Select **admin** from the dropdown
3. Click **Remove Role**
4. The user will no longer have admin privileges

### Promoting to Super User
1. Click the green **Add Role** button
2. Select **super-user** from the dropdown
3. Click **Add Role**
4. ⚠️ **Warning**: Super users have full system access!

## Troubleshooting

### "No roles to remove" message
- The user currently has no roles assigned
- Add a role first before trying to remove one

### Permission denied
- Ensure you're logged in as an admin or super-user
- Regular users cannot manage roles

### Changes not appearing
- Refresh the page
- Click the **Refresh** button in the user table header
- Clear your browser cache

## Security Notes

- Only admins and super-users can manage roles
- Users can manage their own roles (with permission check)
- All role changes are logged (check application logs)
- Be careful when removing super-user role - you may lock yourself out!

---

**Last Updated**: 2024-06-18  
**Related Files**:
- `resources/views/backend/user/index.blade.php` - User management UI
- `app/Http/Controllers/Backend/UserController.php` - Role management logic
- `routes/web.php` - Route definitions
