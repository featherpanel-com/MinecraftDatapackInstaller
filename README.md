# Minecraft Datapack Installer

A FeatherPanel addon that allows you to easily install [Vanilla Tweaks](https://vanillatweaks.net/) datapacks, resource packs, and crafting tweaks directly to your Minecraft servers.

## Features

- 🎮 **Multiple Pack Types**: Install datapacks, resource packs, and crafting tweaks
- 🔍 **Automatic Version Detection**: Automatically detects your server's Minecraft version
- 🌍 **World Selection**: Choose which world to install packs to
- 📦 **Comprehensive Pack Browser**: Browse all available Vanilla Tweaks packs with descriptions, versions, and compatibility info
- 🔄 **Smart Caching**: Uses FeatherPanel's built-in caching system to reduce API calls and improve performance
- 🎨 **Modern UI**: Beautiful, responsive Vue 3 interface with dark mode support
- ⚠️ **Compatibility Warnings**: Shows incompatible packs before installation
- 📹 **Video Links**: Direct links to demonstration videos for each pack
- 🔐 **Permission-Based**: Respects FeatherPanel's permission system

## Requirements

- PHP 8.4+
- PHP extensions: `curl`, `pdo`
- FeatherPanel v2
- Wings daemon (for server file operations)

## Installation

1. Install the addon through FeatherPanel's Plugin Manager
2. The addon will automatically register routes and frontend assets
3. Access the installer from your server's sidebar under "Datapack Installer"

## Usage

### For Server Owners

1. Navigate to your server's control panel
2. Click on "Datapack Installer" in the sidebar
3. Select your Minecraft version (or let it auto-detect)
4. Choose the world where you want to install packs
5. Select the pack type (Data Packs, Resource Packs, or Crafting Tweaks)
6. Browse and select the packs you want to install
7. Click "Install" to install all selected packs
8. Restart your server or run `/reload` to apply changes

### Pack Information

Each pack displays:

- **Display Name**: The friendly name of the pack
- **Version**: The pack version number
- **Description**: What the pack does
- **Incompatible Packs**: Warning if the pack conflicts with others
- **Video Link**: Link to demonstration video (if available)

Click the info button (ℹ️) on any pack card to see full details in a dialog.

## Supported Versions

The addon supports major Minecraft versions:

- 1.13
- 1.14
- 1.15
- 1.16
- 1.17
- 1.18
- 1.19
- 1.20
- 1.21

## API Endpoints

The addon provides the following API endpoints (all require server access):

- `GET /api/user/servers/{uuid}/addons/datapackinstaller/detect-version` - Detect server's Minecraft version
- `GET /api/user/servers/{uuid}/addons/datapackinstaller/worlds` - List available worlds
- `GET /api/user/servers/{uuid}/addons/datapackinstaller/packs?mcVersion={version}&type={type}` - Get packs for a version
- `GET /api/user/servers/{uuid}/addons/datapackinstaller/image?pack={name}&mcVersion={version}&type={type}` - Get pack image
- `POST /api/user/servers/{uuid}/addons/datapackinstaller/install` - Install selected packs

## Architecture

### Backend

- **Controller**: `Controllers/VanillaTweaksController.php` - Handles all API logic
- **Routes**: `Routes/vanilla-tweaks.php` - Defines API endpoints
- **Caching**: Uses `App\Cache\Cache` for caching pack data (60 min) and images (24 hours)
- **File Operations**: Uses `App\Services\Wings\Wings` for server file operations

### Frontend

- **Framework**: Vue 3 with Composition API and TypeScript
- **UI Components**: shadcn-vue components with TailwindCSS v4
- **Composables**: `useDatapackAPI.ts` - Handles all API communication
- **Pages**: `DataPackInstaller.vue` - Main installer interface

## Caching Strategy

The addon implements intelligent caching:

- **Pack Data**: Cached for 60 minutes to reduce API calls to Vanilla Tweaks
- **Pack Images**: Cached for 24 hours with fallback to transparent placeholder on 404
- **Cache Keys**: Uses format `vanillatweaks:packs:{version}:{type}` and `vanillatweaks:image:{pack}:{version}:{type}`

## File Structure

```
minecraftdatapackinstaller/
├── MinecraftDatapackInstaller.php    # Main plugin class
├── conf.yml                           # Plugin configuration
├── Controllers/
│   └── VanillaTweaksController.php    # API controller
├── Routes/
│   └── vanilla-tweaks.php            # Route definitions
└── Frontend/
    ├── App/                           # Vue 3 frontend application
    │   ├── src/
    │   │   ├── pages/
    │   │   │   └── DataPackInstaller.vue
    │   │   ├── composables/
    │   │   │   └── useDatapackAPI.ts
    │   │   └── router/
    │   │       └── index.ts
    │   └── package.json
    └── sidebar.json                    # Sidebar configuration
```

## Permissions

The addon respects FeatherPanel's permission system:

- Users need access to the server to use the installer
- Subuser permissions are checked via `CheckSubuserPermissionsTrait`
- File operations require appropriate server permissions

## Troubleshooting

### Worlds Not Showing

- Ensure your server has at least one world with a `level.dat` file
- Check that the Wings daemon has proper file system access

### Images Not Loading

- Images are cached for 24 hours
- If an image fails to load, a placeholder icon will be shown
- Check your server's internet connection to Vanilla Tweaks API

### Installation Fails

- Verify the selected world exists and is accessible
- Check Wings daemon logs for file operation errors
- Ensure the server has write permissions to the world directory
- The `datapacks` folder will be created automatically if it doesn't exist

## Credits

- **Vanilla Tweaks**: [vanillatweaks.net](https://vanillatweaks.net/) - The amazing pack collection
- **FeatherPanel**: The panel framework this addon is built for
- **Authors**: NaysKutzu, soluslabs

## License

This addon is provided as-is for use with FeatherPanel.

## Support

For issues related to:

- **The addon**: Check FeatherPanel's support channels
- **Vanilla Tweaks packs**: Visit [vanillatweaks.net](https://vanillatweaks.net/)
- **Minecraft servers**: Consult your server documentation