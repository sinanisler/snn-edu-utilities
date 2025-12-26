# SNN Edu Utilities

Educational utilities WordPress plugin including admin restrictions, dashboard notepad, and custom author permalinks.

## Features

### 🔒 Admin Access Control
- **Restrict wp-admin**: Limit WordPress admin area access to administrators only
- **Hide Admin Bar**: Remove admin bar from front-end for non-administrators

### 📝 Dashboard Notepad
Personal notepad widget for each user's dashboard with TinyMCE editor and AJAX saving.

### 🔗 Custom Author Permalinks
Role-based author archive URLs using numeric IDs:
- Regular users: `/user/123`
- Instructors: `/instructor/456`
- Automatic redirects from old `/author/` URLs

## Installation

### From GitHub Release
1. Download the latest `snn-edu-utilities.zip` from [Releases](https://github.com/sinanisler/snn-edu-utilities/releases)
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the zip file and activate

### Manual Installation
1. Clone this repository into your `wp-content/plugins/` directory
2. Activate the plugin in WordPress Admin → Plugins

## Configuration

Navigate to **Settings → SNN Edu Utilities** to enable/disable features:

- ☑️ Restrict wp-admin to Administrators
- ☑️ Hide Admin Bar for Non-Admins
- ☑️ Enable Dashboard Notepad Widget
- ☑️ Enable Custom Author Permalinks

**Important:** After enabling/disabling Custom Author Permalinks, flush permalinks by visiting Settings → Permalinks and clicking Save.
