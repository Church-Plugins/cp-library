# User Management & Permissions

## Role-Based Access Control

CP Sermon Library integrates with WordPress user roles to control access to sermon management features.

### Default Permission Structure

The plugin uses the following WordPress capabilities:

- **Administrator** - Full access to all sermon features
- **Editor** - Can create, edit, and delete sermons, series, and speakers
- **Author** - Can create and edit their own sermons
- **Contributor** - Can create sermons but not publish them
- **Subscriber** - No sermon management capabilities

## Managing Contributor & Admin Permissions

You can adjust permissions for sermon management using role management plugins or by customizing capabilities.

### Key Capabilities

- `edit_cpl_items` - Ability to edit sermons
- `publish_cpl_items` - Ability to publish sermons
- `edit_others_cpl_items` - Ability to edit sermons created by others
- `delete_cpl_items` - Ability to delete sermons
- `manage_cpl_taxonomies` - Ability to manage sermon categories

### Restricting Access to Settings

By default, only administrators can access the plugin settings. This behavior ensures that critical configurations aren't changed accidentally.

## Bulk Editing Sermon Data via the WordPress UI

CP Sermon Library provides tools for managing sermon data in bulk:

### Bulk Actions

1. Navigate to Library → Sermons
2. Select multiple sermons using the checkboxes
3. Choose an action from the Bulk Actions dropdown:
   - Edit
   - Move to Trash
   - Change series
   - Change speaker
   - Add/remove topics
4. Click "Apply" to execute the bulk action

### Bulk Edit Options

When using the "Edit" bulk action:

1. Select the fields you want to change
2. Enter new values for those fields
3. Leave other fields blank to keep their current values
4. Click "Update" to apply changes to all selected sermons

### Quick Edit

For single sermon quick edits:

1. Hover over a sermon in the sermon list
2. Click "Quick Edit"
3. Modify sermon details
4. Click "Update" to save changes