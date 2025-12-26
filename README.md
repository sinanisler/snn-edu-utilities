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

## Auto-Updates

This plugin includes GitHub auto-update functionality. When a new release is published on GitHub, WordPress will automatically detect and offer the update in the admin dashboard.

## Development

### Creating a Release

To create a new release, commit your changes with the keyword `release` in the commit message:

```bash
git add .
git commit -m "release: Add new feature"
git push origin main
```

The GitHub Actions workflow will:
1. Automatically bump the version number
2. Create a new release tag
3. Generate a changelog
4. Create a zip file for WordPress installation

### Creating a Pre-release

For testing purposes, use `alphatag` in your commit message:

```bash
git commit -m "alphatag: Testing new feature"
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher

## Author

**sinanisler**
- Website: [sinanisler.com](https://sinanisler.com)
- GitHub: [@sinanisler](https://github.com/sinanisler)

## License

GPL v2 or later - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
